<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\StokReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P0-1 (audit hardening): sebelumnya StokReversalService SELALU menghitung
 * ulang qty bahan dari BOM produk yang LIVE - kalau BOM produk diedit
 * (bahan diganti / formula diubah) di antara order dibuat dan item itu
 * dibatalkan, reversal akan mengembalikan bahan/qty yang SALAH (mengikuti
 * BOM baru, bukan yang benar-benar dipotong dulu). Tes ini membuktikan:
 * (1) snapshot per-item (transaksi_detail_bahan_pakai) membuat reversal
 * tetap benar walau BOM sudah berubah, dan (2) transaksi lama tanpa
 * snapshot tetap punya fallback yang berfungsi (walau tetap berisiko
 * seperti sebelumnya - itulah kenapa snapshot ini dibuat).
 */
class StokReversalSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idKonsumen;
    private int $idGol;
    private int $idBahanLama;
    private int $idBahanBaru;
    private int $idProduk;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00:00')); // Senin - lihat catatan sama di TintaStokTest

        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M1', 'tipe_mesin' => 'Cetak']);
        $idKat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);

        $this->idBahanLama = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'LAMA', 'nama_bahan' => 'Bahan Lama', 'id_mesin' => $idMesin, 'id_kategori' => $idKat,
            'stok' => 100, 'nilai_konversi' => 1, 'harga_rata_rata' => 1000, 'satuan_stok' => 'lembar', 'status' => 'aktif',
        ]);
        $this->idBahanBaru = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'BARU', 'nama_bahan' => 'Bahan Baru', 'id_mesin' => $idMesin, 'id_kategori' => $idKat,
            'stok' => 100, 'nilai_konversi' => 1, 'harga_rata_rata' => 2000, 'satuan_stok' => 'lembar', 'status' => 'aktif',
        ]);

        $this->idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'Produk Uji', 'metode_hitung' => 'quantity', 'status' => 'aktif']);
        DB::table('bom_detail')->insert(['id_produk' => $this->idProduk, 'id_bahan' => $this->idBahanLama, 'cara_hitung_pemakaian' => 'Q', 'created_at' => now(), 'updated_at' => now()]);

        $this->idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $this->idGol]);
        DB::table('setting_harga')->insert(['id_produk' => $this->idProduk, 'id_golongan' => $this->idGol, 'range_min' => 0, 'harga_jual' => 10000]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reversal_tetap_mengembalikan_bahan_yang_benar_walau_bom_sudah_diganti_setelah_order(): void
    {
        // Order dibuat SAAT BOM masih memakai Bahan Lama, qty 5.
        $this->actingAs($this->admin)->postJson('/penjualan/store', [
            'id_konsumen' => $this->idKonsumen, 'id_golongan' => $this->idGol,
            'items' => [['id_produk' => $this->idProduk, 'nama_file' => 'a', 'p' => 0, 'l' => 0, 'qty_input' => 5, 'biaya_cetak' => 10000]],
        ])->assertOk();

        $this->assertEquals(95, (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahanLama)->value('stok'), 'Bahan Lama harus terpotong 5');
        $this->assertEquals(100, (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahanBaru)->value('stok'), 'Bahan Baru belum tersentuh sama sekali');
        $this->assertDatabaseHas('transaksi_detail_bahan_pakai', ['id_bahan' => $this->idBahanLama, 'qty_pakai' => 5]);

        // BOM produk DIEDIT setelah order dibuat: Bahan Lama diganti Bahan Baru (skenario nyata: operator mengganti pemasok/jenis kertas).
        DB::table('bom_detail')->where('id_produk', $this->idProduk)->delete();
        DB::table('bom_detail')->insert(['id_produk' => $this->idProduk, 'id_bahan' => $this->idBahanBaru, 'cara_hitung_pemakaian' => 'Q', 'created_at' => now(), 'updated_at' => now()]);

        $detail = DB::table('transaksi_detail')->first();
        $ringkasan = StokReversalService::kembalikan($detail, 'BATAL-UJI', 'uji pembatalan setelah BOM berubah');

        // HARUS mengembalikan ke Bahan Lama (yang benar-benar dipotong) - BUKAN Bahan Baru (BOM yang sekarang berlaku).
        $this->assertEquals(100, (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahanLama)->value('stok'), 'Bahan Lama harus kembali penuh ke 100');
        $this->assertEquals(100, (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahanBaru)->value('stok'), 'Bahan Baru TIDAK BOLEH ikut bertambah - tidak pernah dipotong untuk order ini');
        $this->assertStringContainsString('Bahan Lama', implode(',', $ringkasan));
        $this->assertStringNotContainsString('Bahan Baru', implode(',', $ringkasan));
    }

    public function test_fallback_recompute_bom_live_tetap_berfungsi_untuk_transaksi_tanpa_snapshot(): void
    {
        // Simulasikan transaksi LAMA (dibuat sebelum fitur snapshot ada) - insert langsung, tanpa lewat store(), jadi TIDAK ADA baris transaksi_detail_bahan_pakai.
        $idTransaksi = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'LAMA-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $this->idKonsumen, 'id_user_kasir' => $this->admin->id_user,
            'tgl_order' => '2026-09-20 09:00:00', 'created_at' => '2026-09-20 09:00:00',
            'total_bayar' => 1, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif',
        ]);
        $idDetail = DB::table('transaksi_detail')->insertGetId([
            'id_transaksi' => $idTransaksi, 'id_produk' => $this->idProduk, 'nama_file' => 'a',
            'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 7, 'subtotal' => 1, 'status_spk' => 'Antre',
        ]);
        DB::table('bahan_baku')->where('id_bahan', $this->idBahanLama)->update(['stok' => 93]); // seolah 7 sudah terpotong manual (tanpa snapshot)

        $this->assertDatabaseMissing('transaksi_detail_bahan_pakai', ['id_detail' => $idDetail]);

        StokReversalService::kembalikan(DB::table('transaksi_detail')->where('id_detail', $idDetail)->first(), 'BATAL-LAMA', 'uji fallback');

        $this->assertEquals(100, (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahanLama)->value('stok'), 'Fallback recompute BOM live harus tetap mengembalikan 7 seperti sebelumnya');
    }

    /**
     * Audit H-2: nota SESUDAH snapshot mulai dipakai (27 Sep 2026 21:26:42)
     * tanpa baris snapshot TIDAK BOLEH memicu recompute BOM live, walau
     * tanggalnya sebelum 14 Okt (batas keliru lama). Skenario: produk tanpa
     * komponen BOM saat order dibuat, BOM ditambah belakangan, lalu batal.
     */
    public function test_nota_era_snapshot_tanpa_snapshot_tidak_recompute_bom_live(): void
    {
        $idTransaksi = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'ERA-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $this->idKonsumen, 'id_user_kasir' => $this->admin->id_user,
            'tgl_order' => '2026-09-28 08:00:00', 'created_at' => '2026-09-28 08:00:00',
            'total_bayar' => 1, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif',
        ]);
        $idDetail = DB::table('transaksi_detail')->insertGetId([
            'id_transaksi' => $idTransaksi, 'id_produk' => $this->idProduk, 'nama_file' => 'a',
            'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 7, 'subtotal' => 1, 'status_spk' => 'Antre',
        ]);
        $stokSebelum = (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahanLama)->value('stok');

        $ringkasan = StokReversalService::kembalikan(DB::table('transaksi_detail')->where('id_detail', $idDetail)->first(), 'BATAL-ERA', 'uji era snapshot');

        $this->assertSame([], $ringkasan);
        $this->assertEquals($stokSebelum, (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahanLama)->value('stok'), 'Stok fiktif tidak boleh muncul');
    }

    public function test_batas_era_tepat_pada_detik_snapshot_mulai(): void
    {
        $this->assertSame('2026-09-27 21:26:42', StokReversalService::SNAPSHOT_MULAI);
    }

    /**
     * Audit stok: konversi satuan dan harga rata-rata bahan berubah SETELAH order dibuat,
     * lalu item dibatalkan. Stok harus kembali persis sebesar yang benar-benar dipotong dulu,
     * bukan dihitung ulang dari konversi/harga yang berlaku sekarang.
     */
    public function test_reversal_tidak_terpengaruh_perubahan_konversi_dan_harga_setelah_order(): void
    {
        $this->actingAs($this->admin)->postJson('/penjualan/store', [
            'id_konsumen' => $this->idKonsumen, 'id_golongan' => $this->idGol,
            'items' => [['id_produk' => $this->idProduk, 'nama_file' => 'a', 'p' => 0, 'l' => 0, 'qty_input' => 7, 'biaya_cetak' => 10000]],
        ])->assertOk();
        $this->assertEquals(93, (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahanLama)->value('stok'));

        DB::table('bahan_baku')->where('id_bahan', $this->idBahanLama)->update(['nilai_konversi' => 500, 'harga_rata_rata' => 99999]);

        StokReversalService::kembalikan(DB::table('transaksi_detail')->first(), 'BATAL-KONVERSI', 'uji konversi berubah');

        $this->assertEquals(100, (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahanLama)->value('stok'), 'Kembali 7, bukan 7 x konversi baru');
        $in = DB::table('kartu_stok')->where('referensi', 'BATAL-KONVERSI')->where('id_bahan', $this->idBahanLama)->first();
        $this->assertEquals(7, (float) $in->qty);
    }

    public function test_reversal_kedua_untuk_item_yang_sama_tidak_menambah_stok_lagi(): void
    {
        $this->actingAs($this->admin)->postJson('/penjualan/store', [
            'id_konsumen' => $this->idKonsumen, 'id_golongan' => $this->idGol,
            'items' => [['id_produk' => $this->idProduk, 'nama_file' => 'a', 'p' => 0, 'l' => 0, 'qty_input' => 4, 'biaya_cetak' => 10000]],
        ])->assertOk();
        $detail = DB::table('transaksi_detail')->first();

        StokReversalService::kembalikan($detail, 'BATAL-1', 'pertama');
        StokReversalService::kembalikan($detail, 'BATAL-2', 'kedua (klik ganda / dua pengguna)');

        $this->assertEquals(100, (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahanLama)->value('stok'), 'Stok tidak boleh melebihi awal');
        $this->assertSame(1, DB::table('kartu_stok')->where('tipe', 'IN')->where('id_bahan', $this->idBahanLama)->whereIn('referensi', ['BATAL-1', 'BATAL-2'])->count());
    }
}
