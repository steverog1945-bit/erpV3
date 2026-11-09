<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Batal SPK per item dengan pengembalian stok: bahan kembali ke gudang, jadi HPP-nya juga kembali ke Persediaan
 * (referensi nomor nota, sehingga pembatalan nota sesudahnya tetap membalik sisanya dengan benar). Tanpa pengembalian
 * stok, HPP tetap biaya.
 */
class BatalSpkHppTest extends TestCase
{
    use RefreshDatabase;

    private int $bahan;
    private int $idDetail;
    private int $idTransaksi;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($admin);
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'x']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K', 'kode_kategori' => 'K']);
        $this->bahan = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'B', 'nama_bahan' => 'Flexi', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 96, 'nilai_konversi' => 1, 'harga_rata_rata' => 1000, 'satuan_stok' => 'm2', 'status' => 'aktif']);
        AccountingService::postSaldoAwalPersediaan('SA-B', 'Saldo awal Flexi', 100000);

        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'A', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        $produk = DB::table('produk')->insertGetId(['nama_produk' => 'Banner', 'metode_hitung' => 'quantity', 'status' => 'aktif']);
        $this->idTransaksi = DB::table('transaksi')->insertGetId(['nomor_nota' => 'INV-SPK', 'tipe_order' => 'Reguler', 'id_konsumen' => $kons, 'id_user_kasir' => $admin->id_user,
            'tgl_order' => now(), 'total_bayar' => 50000, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir', 'status_collecting' => 'Belum Diambil', 'created_at' => now()]);
        $this->idDetail = DB::table('transaksi_detail')->insertGetId(['id_transaksi' => $this->idTransaksi, 'id_produk' => $produk, 'nama_file' => 'banner.pdf', 'p_ukuran' => 2, 'l_ukuran' => 2,
            'qty' => 1, 'status_urgensi' => 'Normal', 'status_finishing' => 'Standar', 'subtotal' => 50000, 'hpp_snapshot' => 4000, 'harga_jual_snapshot' => 50000,
            'status_spk' => 'Antre', 'biaya_cetak' => 50000, 'biaya_desain' => 0]);
        // Saat order dibuat: 4 m2 dipotong (snapshot + kartu OUT) dan HPP 4.000 dijurnal.
        DB::table('transaksi_detail_bahan_pakai')->insert(['id_detail' => $this->idDetail, 'id_bahan' => $this->bahan, 'qty_pakai' => 4, 'is_tinta' => 0, 'created_at' => now()]);
        DB::table('kartu_stok')->insert(['id_bahan' => $this->bahan, 'tanggal' => now(), 'tipe' => 'OUT', 'qty' => 4, 'stok_sebelum' => 100, 'stok_sesudah' => 96,
            'referensi' => 'INV-SPK', 'keterangan' => 'order', 'created_at' => now(), 'updated_at' => now()]);
        AccountingService::postPenjualan('INV-SPK', 50000, 4000);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()->subMinute()]);
    }

    private function saldo(string $kode): float
    {
        return round((float) DB::table('jurnal_umum')->where('kode_akun', $kode)->sum(DB::raw('debet - kredit')), 2);
    }

    public function test_batal_spk_dengan_pengembalian_stok_mengembalikan_hpp_ke_persediaan(): void
    {
        $this->patch("/produksi/{$this->idDetail}/cancel", ['kembalikan_stok' => 1, 'alasan_batal' => 'salah ukuran'])->assertSessionMissing('error');

        $this->assertEquals(100, (float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('stok'));
        $this->assertEquals(100000, $this->saldo('1200'), 'nilai persediaan harus mengikuti stok yang kembali');
        $this->assertEquals(0, $this->saldo('5000'));
        $this->assertEquals((float) DB::table('jurnal_umum')->sum('debet'), (float) DB::table('jurnal_umum')->sum('kredit'));

        // Nota lalu dibatalkan: HPP tidak dibalik dua kali.
        $this->patch("/penjualan/{$this->idTransaksi}/batalkan-nota", ['alasan' => 'batal', 'kembalikan_stok' => 1]);
        $req = ApprovalRequest::where('tipe_aksi', 'batalkan_nota')->firstOrFail();
        $this->patch("/approval/{$req->id}/approve", ['catatan' => 'ok']);
        $this->assertSame('Dibatalkan', DB::table('transaksi')->where('id_transaksi', $this->idTransaksi)->value('status_nota'));
        $this->assertEquals(100, (float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('stok'));
        $this->assertEquals(100000, $this->saldo('1200'));
        $this->assertEquals(0, $this->saldo('5000'));
    }

    public function test_batal_spk_tanpa_pengembalian_stok_hpp_tetap_biaya(): void
    {
        $this->patch("/produksi/{$this->idDetail}/cancel", ['alasan_batal' => 'bahan rusak'])->assertSessionMissing('error');

        $this->assertEquals(96, (float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('stok'));
        $this->assertEquals(96000, $this->saldo('1200'));
        $this->assertEquals(4000, $this->saldo('5000'));
    }

    public function test_pembalikan_hpp_memakai_nilai_saat_order_bukan_harga_rata_rata_terbaru(): void
    {
        // Harga rata-rata naik setelah order dibuat (mis. ada pembelian baru yang lebih mahal).
        DB::table('bahan_baku')->where('id_bahan', $this->bahan)->update(['harga_rata_rata' => 2000]);

        $this->patch("/produksi/{$this->idDetail}/cancel", ['kembalikan_stok' => 1, 'alasan_batal' => 'salah ukuran'])->assertSessionMissing('error');

        // HPP yang dulu dijurnal 4.000 (4 m2 x 1.000): yang dibalik juga 4.000, bukan 4 x 2.000.
        $this->assertEquals(0, $this->saldo('5000'));
        $this->assertEquals(100000, $this->saldo('1200'));
    }

    public function test_batal_spk_tanpa_kembali_lalu_batal_nota_dengan_kembali_hpp_item_itu_tetap_biaya(): void
    {
        // Bahan item ini sudah terpakai: SPK dibatalkan TANPA pengembalian.
        $this->patch("/produksi/{$this->idDetail}/cancel", ['alasan_batal' => 'bahan rusak'])->assertSessionMissing('error');

        // Notanya kemudian dibatalkan dengan pilihan kembalikan stok (berlaku untuk item yang masih aktif).
        $this->patch("/penjualan/{$this->idTransaksi}/batalkan-nota", ['alasan' => 'batal', 'kembalikan_stok' => 1]);
        $req = ApprovalRequest::where('tipe_aksi', 'batalkan_nota')->firstOrFail();
        $this->patch("/approval/{$req->id}/approve", ['catatan' => 'ok']);

        $this->assertSame('Dibatalkan', DB::table('transaksi')->where('id_transaksi', $this->idTransaksi)->value('status_nota'));
        $this->assertEquals(96, (float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('stok'), 'bahan yang sudah terpakai tidak kembali');
        $this->assertEquals(4000, $this->saldo('5000'), 'HPP bahan yang terpakai ikut terbalik');
        $this->assertEquals(96000, $this->saldo('1200'), 'nilai persediaan lebih besar dari stok fisik');
        $this->assertEquals((float) DB::table('jurnal_umum')->sum('debet'), (float) DB::table('jurnal_umum')->sum('kredit'));
    }

    public function test_persetujuan_batal_nota_ditolak_bila_barang_sudah_jadi_atau_diambil_sejak_diajukan(): void
    {
        // Diajukan saat item masih antre.
        $this->patch("/penjualan/{$this->idTransaksi}/batalkan-nota", ['alasan' => 'batal', 'kembalikan_stok' => 1])->assertSessionHas('success');
        $req = ApprovalRequest::where('tipe_aksi', 'batalkan_nota')->firstOrFail();

        // Sambil menunggu persetujuan, produksi menyelesaikan item dan barang diambil pelanggan.
        DB::table('transaksi_detail')->where('id_detail', $this->idDetail)->update(['status_spk' => 'Selesai']);
        DB::table('transaksi')->where('id_transaksi', $this->idTransaksi)->update(['status_collecting' => 'Sudah Diambil']);

        $this->patch("/approval/{$req->id}/approve", ['catatan' => 'ok'])->assertSessionHas('error');

        $this->assertSame('Aktif', DB::table('transaksi')->where('id_transaksi', $this->idTransaksi)->value('status_nota'), 'nota yang barangnya sudah diserahkan ikut batal');
        $this->assertSame('Selesai', DB::table('transaksi_detail')->where('id_detail', $this->idDetail)->value('status_spk'));
        $this->assertEquals(96, (float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('stok'), 'stok bahan yang sudah jadi barang dikembalikan');
        $this->assertSame('Menunggu', $req->fresh()->status, 'pengajuan seharusnya tetap menunggu untuk ditolak');
    }

    public function test_kembalikan_stok_hanya_untuk_batal_permanen_bukan_antre_ulang(): void
    {
        // Diantrekan ulang = akan dicetak lagi tanpa potong stok: bahannya tidak boleh dikembalikan.
        $this->patch("/produksi/{$this->idDetail}/cancel", ['tindakan' => 'antre', 'kembalikan_stok' => 1, 'alasan_batal' => 'cetak ulang'])->assertSessionHas('error');

        $this->assertEquals(96, (float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('stok'));
        $this->assertSame('Antre', DB::table('transaksi_detail')->where('id_detail', $this->idDetail)->value('status_spk'));
        $this->assertSame(1, DB::table('transaksi_detail_bahan_pakai')->where('id_detail', $this->idDetail)->count(), 'catatan pemakaian bahan hilang');

        // Diantrekan ulang tanpa pengembalian stok tetap boleh.
        $this->patch("/produksi/{$this->idDetail}/cancel", ['tindakan' => 'pending', 'alasan_batal' => 'tunggu konfirmasi'])->assertSessionMissing('error');
        $this->assertSame('Pending', DB::table('transaksi_detail')->where('id_detail', $this->idDetail)->value('status_spk'));
    }
}
