<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Alur penuh satu pesanan lewat layar aplikasi: Input Order (Menunggu Kasir) -> kasir Terima Transaksi
 * (Diterima Kasir, SPK aktif) -> DP di kasir -> ambil SPK -> produksi selesai ->
 * collecting ditahan karena belum lunas -> pelunasan -> collecting. Di akhir: stok, status nota, buku besar, dan
 * audit:integritas harus cocok satu sama lain, dan laporan Perbandingan membaca angka yang sama.
 */
class AlurPenuhOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $bahan;
    private int $produk;
    private int $kons;
    private int $gol;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($this->admin);

        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M1', 'tipe_mesin' => 'Cetak']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K', 'kode_kategori' => 'K']);
        $this->bahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'ALR-1', 'nama_bahan' => 'Kertas Alur', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 100, 'nilai_konversi' => 1, 'harga_rata_rata' => 1000, 'satuan_stok' => 'lembar', 'status' => 'aktif',
        ]);
        DB::table('kartu_stok')->insert(['id_bahan' => $this->bahan, 'tanggal' => now()->subDay(), 'tipe' => 'IN', 'qty' => 100, 'stok_sebelum' => 0, 'stok_sesudah' => 100,
            'referensi' => 'SALDO-AWAL', 'keterangan' => 'saldo awal', 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
        AccountingService::postSaldoAwalPersediaan('SA-ALR', 'Saldo awal Kertas Alur', 100000);

        $this->produk = DB::table('produk')->insertGetId(['nama_produk' => 'Brosur', 'metode_hitung' => 'quantity', 'status' => 'aktif']);
        DB::table('bom_detail')->insert(['id_produk' => $this->produk, 'id_bahan' => $this->bahan, 'cara_hitung_pemakaian' => 'Q', 'created_at' => now(), 'updated_at' => now()]);
        $this->gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Pelanggan Alur', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $this->gol]);
        DB::table('setting_harga')->insert(['id_produk' => $this->produk, 'id_golongan' => $this->gol, 'range_min' => 0, 'harga_jual' => 10000]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()->subMinute()]);
    }

    private function saldo(string $kode): float
    {
        return round((float) DB::table('jurnal_umum')->where('kode_akun', $kode)->sum(DB::raw('debet - kredit')), 2);
    }

    private function bayar(int $idTrx, string $jumlah, string $metode, string $sid)
    {
        return $this->post('/kasir/proses-bayar', ['id_transaksi' => $idTrx, 'jumlah_bayar' => $jumlah, 'metode_pembayaran' => $metode, 'submit_id' => $sid]);
    }

    public function test_order_sampai_diambil_semua_angka_saling_cocok(): void
    {
        // 1. Input Order: 5 lembar, biaya cetak 50.000. Stok terpotong 5, HPP 5 x 1.000.
        $this->postJson('/penjualan/store', ['id_konsumen' => $this->kons, 'id_golongan' => $this->gol, 'submit_id' => 'alur-1',
            'items' => [['id_produk' => $this->produk, 'nama_file' => 'brosur.pdf', 'p' => 0, 'l' => 0, 'qty_input' => 5, 'biaya_cetak' => 50000]]])->assertOk();
        $nota = DB::table('transaksi')->first();
        $detail = DB::table('transaksi_detail')->where('id_transaksi', $nota->id_transaksi)->first();
        $this->assertEquals(50000, (float) $nota->total_bayar);
        $this->assertEquals(95, (float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('stok'));
        $this->assertSame('Menunggu Kasir', $nota->status_order);

        // 1b. Kasir Terima Transaksi: SPK aktif, bayarForm terbuka.
        $this->post(route('kasir.terimaTransaksi', $nota->id_transaksi), ['submit_id' => 'alur-terima'])->assertSessionHas('success');
        $this->assertSame('Diterima Kasir', DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->value('status_order'));

        // 2. DP tunai 20.000 di kasir.
        $this->bayar($nota->id_transaksi, '20.000', 'Tunai', 'alur-dp')->assertSessionHas('success');
        $this->assertSame('DP', DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->value('status_pembayaran'));

        // 3-4. SPK diambil lalu produksi selesai.
        $this->patch("/spk/{$detail->id_detail}/ambil")->assertSessionHas('success');
        $this->patch("/produksi/{$detail->id_detail}/selesai")->assertSessionHas('success');
        $this->assertSame('Selesai', DB::table('transaksi_detail')->where('id_detail', $detail->id_detail)->value('status_spk'));

        // 5. Collecting ditahan: belum lunas dan tanpa konfirmasi.
        $this->patch("/collecting/{$nota->id_transaksi}/proses", ['jenis' => 'Diambil Sendiri'])->assertSessionHas('error');
        $this->assertNotSame('Sudah Diambil', DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->value('status_collecting'));

        // 6. Pelunasan transfer 30.000, lalu barang diambil.
        $this->bayar($nota->id_transaksi, '30.000', 'Transfer', 'alur-lunas')->assertSessionHas('success');
        $this->patch("/collecting/{$nota->id_transaksi}/proses", ['jenis' => 'Diambil Sendiri'])->assertSessionHas('success');

        $akhir = DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->first();
        $this->assertSame(['Aktif', 'Diterima Kasir', 'Lunas', 'Sudah Diambil'], [$akhir->status_nota, $akhir->status_order, $akhir->status_pembayaran, $akhir->status_collecting]);

        // Buku besar: piutang habis, pendapatan & HPP sesuai, uang di laci dan bank, persediaan = stok x harga.
        $this->assertEquals(0, $this->saldo('1100'), 'nota lunas masih menyisakan piutang');
        $this->assertEquals(-50000, $this->saldo('4000'));
        $this->assertEquals(5000, $this->saldo('5000'));
        $this->assertEquals(95000, $this->saldo('1200'));
        $this->assertEquals(20000, $this->saldo('1000'));
        $this->assertEquals(30000, $this->saldo('1010'));
        $this->assertEquals((float) DB::table('jurnal_umum')->sum('debet'), (float) DB::table('jurnal_umum')->sum('kredit'));

        // Pemeriksaan integritas otomatis tidak menemukan apa pun.
        Artisan::call('audit:integritas', ['--json' => true]);
        $this->assertSame([], json_decode(Artisan::output(), true)['temuan']);

        // Laporan membaca angka yang sama.
        $hari = now()->toDateString();
        $this->get("/akuntansi/perbandingan?awal={$hari}&akhir={$hari}")->assertOk()
            ->assertViewHas('totalPemasukan', 50000.0)->assertViewHas('uangDiterima', 50000.0);
        $this->get("/akuntansi/laba-rugi?awal={$hari}&akhir={$hari}")->assertOk()->assertViewHas('labaBersih', 45000.0);
    }

    public function test_input_order_bertotal_nol_ditolak_tanpa_memotong_stok(): void
    {
        $r = $this->postJson('/penjualan/store', ['id_konsumen' => $this->kons, 'id_golongan' => $this->gol, 'submit_id' => 'nol-1',
            'items' => [['id_produk' => $this->produk, 'nama_file' => 'gratis.pdf', 'p' => 0, 'l' => 0, 'qty_input' => 5, 'biaya_cetak' => 0]]]);

        $this->assertFalse($r->isSuccessful());
        $this->assertStringContainsString('Rp 0', (string) $r->json('msg'));
        $this->assertSame(0, DB::table('transaksi')->count());
        $this->assertEquals(100, (float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('stok'), 'stok terpotong untuk nota yang tidak jadi');
    }
}
