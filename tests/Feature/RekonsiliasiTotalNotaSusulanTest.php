<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rekonsiliasi total nota di layar kasir (AccountingService::sesuaikanTotalNota)
 * harus menghitung SELURUH pendapatan nota - termasuk item susulan yang
 * dijurnal dengan sufiks "-susulan".
 *
 * Bug yang dikunci test ini: rekonsiliasi dulu hanya membaca baris jurnal
 * dengan referensi PERSIS nomor nota. Tambahan item susulan dijurnal
 * terpisah ("NOMOR-susulan"), jadi kelihatan seolah belum pernah terjurnal.
 * Rekonsiliasi lalu menganggap total nota masih kurang sebesar tambahan
 * itu, dan menjurnal Piutang Usaha (1100) lebih besar dari tagihan yang
 * sebenarnya - satu akibat: Piutang 1100 tidak sinkron dengan sisa
 * tagihan nota.
 */
class RekonsiliasiTotalNotaSusulanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $idProduk;

    private int $idKonsumen;

    private int $idGolongan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);

        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'MESIN UJI', 'tipe_mesin' => 'Direct Print']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $m2 = DB::table('satuan')->insertGetId(['nama_satuan' => 'm2', 'nilai_konversi' => 1]);
        $bahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'TC1', 'nama_bahan' => 'TC Textile Uji', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 10, 'nilai_konversi' => 1, 'lebar_roll' => 1.2, 'satuan_stok' => 'm2',
            'id_satuan_hitung' => $m2, 'id_satuan_stok' => $m2, 'status' => 'aktif',
            'harga_rata_rata' => 1000,
        ]);
        $this->idProduk = DB::table('produk')->insertGetId([
            'nama_produk' => 'Produk Uji Rekonsiliasi', 'metode_hitung' => 'ukuran', 'status' => 'Aktif',
            'id_mesin' => $mesin, 'spk_lengkap' => 1,
        ]);
        DB::table('bom_detail')->insert([
            'id_produk' => $this->idProduk, 'id_bahan' => $bahan, 'cara_hitung_pemakaian' => '(P*L)/10000',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->idGolongan = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Konsumen Uji Rekonsiliasi', 'telepon' => '0812', 'alamat' => '-', 'id_golongan' => $this->idGolongan,
        ]);
        DB::table('setting_harga')->insert([
            'id_produk' => $this->idProduk, 'id_golongan' => $this->idGolongan,
            'range_min' => 0, 'range_max' => null, 'harga_jual' => 50000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('kas_harian')->insert([
            'tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user,
            'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()->subMinute(),
        ]);
    }

    /** Nota 100.000 dengan satu item produk, jurnal penjualan sudah diposting. */
    private function buatNota(string $nomor = 'N-REK-1'): int
    {
        $id = DB::table('transaksi')->insertGetId([
            'nomor_nota' => $nomor, 'tipe_order' => 'Reguler', 'id_konsumen' => $this->idKonsumen,
            'id_user_kasir' => $this->admin->id_user, 'tgl_order' => now(), 'total_bayar' => 100000,
            'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir',
        ]);

        $idDetail = DB::table('transaksi_detail')->insertGetId([
            'id_transaksi' => $id, 'id_produk' => $this->idProduk, 'nama_file' => 'awal.jpg',
            'p_ukuran' => 100, 'l_ukuran' => 100, 'qty' => 1, 'subtotal' => 100000,
            'status_urgensi' => 'Normal', 'status_finishing' => 'Standar', 'status_spk' => 'Antre',
            'biaya_cetak' => 100000, 'biaya_desain' => 0, 'biaya_finishing' => 0,
            'harga_jual_snapshot' => 100000, 'hpp_snapshot' => 1000,
        ]);
        DB::table('transaksi_detail_bahan_pakai')->insert([
            'id_detail' => $idDetail, 'id_bahan' => DB::table('bahan_baku')->value('id_bahan'),
            'qty_pakai' => 1, 'is_tinta' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        \App\Services\AccountingService::postPenjualan($nomor, 100000, 1000);

        return $id;
    }

    private function saldo(string $kode): float
    {
        return round((float) DB::table('jurnal_umum')->where('kode_akun', $kode)->sum(DB::raw('debet - kredit')), 2);
    }

    public function test_item_susulan_ikut_dihitung_saat_rekonsiliasi_total_nota(): void
    {
        $id = $this->buatNota('N-REK-2');

        // Item susulan 50.000 -> jurnal "-susulan", total nota jadi 150.000.
        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", [
                'items' => [[
                    'id_produk' => $this->idProduk, 'nama_file' => 'susulan.jpg',
                    'p' => 100, 'l' => 100, 'qty_input' => 1, 'biaya_cetak' => 50000,
                    'biaya_desain' => 0, 'file_siap_cetak' => 0,
                ]],
            ])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertEquals(150000.0, (float) DB::table('transaksi')->where('id_transaksi', $id)->value('total_bayar'));

        // Kasir melunasi nota: di titik ini total nota dibandingkan dengan
        // pendapatan yang sudah terjurnal.
        $this->actingAs($this->admin)
            ->post('/kasir/proses-bayar', [
                'id_transaksi' => $id, 'jumlah_bayar' => 150000, 'diskon' => 0,
                'metode_pembayaran' => 'Transfer', 'submit_id' => 'rek-1',
            ])
            ->assertSessionHasNoErrors();

        // Pendapatan harus sama dengan total nota - tidak boleh ada penyesuaian
        // yang menambahkannya dua kali.
        $this->assertEquals(-150000.0, $this->saldo('4000'), 'pendapatan jurnal harus sama dengan total nota');

        // Piutang = sisa tagihan nota. Kalau item susulan tidak ikut dihitung,
        // piutang jadi lebih besar 50.000 dari sisa tagihan.
        $sisaTagihan = (float) DB::table('transaksi')->where('id_transaksi', $id)->value('total_bayar')
            - (float) DB::table('pembayaran_riwayat')->where('id_transaksi', $id)->sum('nominal');
        $this->assertEquals(0.0, $sisaTagihan, 'nota harus lunas');
        $this->assertEquals(0.0, $this->saldo('1100'), 'piutang harus nol setelah nota lunas');

        // Tidak boleh ada baris "Penyesuaian total nota" untuk kasus ini:
        // jurnalnya sudah benar, tidak perlu dicocokkan ulang.
        $this->assertSame(0, DB::table('jurnal_umum')
            ->where('no_referensi', 'N-REK-2')
            ->where('keterangan', 'like', 'Penyesuaian total nota%')
            ->count());
    }

    public function test_rekonsiliasi_tetap_berjalan_saat_total_nota_benar_benar_berubah(): void
    {
        $id = $this->buatNota('N-REK-3');

        // Kasir memberi diskon 20.000: total nota jadi 80.000, pendapatan
        // jurnal harus ikut turun 20.000 (piutang & pendapatan lawan).
        $this->actingAs($this->admin)
            ->post('/kasir/proses-bayar', [
                'id_transaksi' => $id, 'jumlah_bayar' => 80000, 'diskon' => 20000,
                'metode_pembayaran' => 'Transfer', 'submit_id' => 'rek-2',
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals(80000.0, (float) DB::table('transaksi')->where('id_transaksi', $id)->value('total_bayar'));
        $this->assertEquals(-80000.0, $this->saldo('4000'), 'pendapatan harus mengikuti total nota yang sudah diskon');
        $this->assertEquals(0.0, $this->saldo('1100'), 'nota lunas tidak boleh menyisakan piutang');
    }
}
