<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\BomCalculationService;
use App\Services\StokReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TintaStokTest extends TestCase
{
    use RefreshDatabase;

    private int $idProduk;
    private int $idTinta;
    private User $admin;
    private int $idKonsumen;
    private int $idGol;

    protected function setUp(): void
    {
        parent::setUp();
        // Dipatok ke hari kerja (Senin) - toko tutup hari Minggu (lihat
        // JamKerjaService) menolak SEMUA transaksi baru terlepas dari
        // kas_harian, jadi tes ini tidak boleh bergantung pada hari
        // sungguhan saat suite dijalankan.
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00:00'));
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $role->id_role]);

        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M1', 'tipe_mesin' => 'Printer']);
        $idKat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $this->idTinta = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'T1', 'nama_bahan' => 'TINTA UJI', 'id_mesin' => $idMesin, 'id_kategori' => $idKat,
            'stok' => 2, 'nilai_konversi' => 1000, 'harga_rata_rata' => 500000, 'satuan_stok' => 'botol', 'status' => 'aktif',
        ]);
        DB::table('pemakaian_tinta')->insert(['id_mesin' => $idMesin, 'id_bahan' => $this->idTinta, 'dasar' => 'm2', 'pemakaian' => 10, 'is_active' => 1]);
        $this->idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'Banner Uji', 'metode_hitung' => 'ukuran', 'status' => 'Aktif', 'id_mesin' => $idMesin]);

        $this->idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $this->idGol]);
        DB::table('setting_harga')->insert(['id_produk' => $this->idProduk, 'id_golongan' => $this->idGol, 'range_min' => 0, 'harga_jual' => 20000]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_komponen_menyertakan_tinta_mesin_produk_dalam_satuan_hitung(): void
    {
        // 100 x 200 cm x 3 = 6 m2 -> 60 ml
        $k = collect(BomCalculationService::komponen($this->idProduk, 100, 200, 3))->firstWhere('id_bahan', $this->idTinta);
        $this->assertNotNull($k);
        $this->assertEqualsWithDelta(60, $k->qty_pakai, 0.0001);
        $this->assertTrue($k->is_tinta);
    }

    public function test_order_memotong_stok_tinta_dan_pembatalan_mengembalikannya(): void
    {
        $this->actingAs($this->admin)->postJson('/penjualan/store', [
            'id_konsumen' => $this->idKonsumen, 'id_golongan' => $this->idGol,
            'items' => [['id_produk' => $this->idProduk, 'nama_file' => 'a', 'p' => 100, 'l' => 200, 'qty_input' => 3, 'biaya_cetak' => 50000]],
        ])->assertOk();

        // 60 ml / 1000 = 0,06 botol
        $this->assertEqualsWithDelta(1.94, (float) DB::table('bahan_baku')->where('id_bahan', $this->idTinta)->value('stok'), 0.0001);
        $this->assertGreaterThan(0, (float) DB::table('transaksi_detail')->value('hpp_snapshot')); // biaya tinta ikut HPP

        $detail = DB::table('transaksi_detail')->first();
        StokReversalService::kembalikan($detail, 'BATAL-UJI', 'uji');
        $this->assertEqualsWithDelta(2.0, (float) DB::table('bahan_baku')->where('id_bahan', $this->idTinta)->value('stok'), 0.0001);
    }

    public function test_nota_lama_tanpa_potongan_tinta_tidak_menambah_stok_saat_dibatalkan(): void
    {
        $idN = DB::table('transaksi')->insertGetId(['nomor_nota' => 'LAMA-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $this->idKonsumen, 'id_user_kasir' => $this->admin->id_user, 'tgl_order' => now(), 'total_bayar' => 1, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif']);
        $idD = DB::table('transaksi_detail')->insertGetId(['id_transaksi' => $idN, 'id_produk' => $this->idProduk, 'nama_file' => 'a', 'p_ukuran' => 100, 'l_ukuran' => 200, 'qty' => 3, 'subtotal' => 1, 'status_spk' => 'Antre']);

        StokReversalService::kembalikan(DB::table('transaksi_detail')->where('id_detail', $idD)->first(), 'BATAL-UJI', 'uji');
        $this->assertEqualsWithDelta(2.0, (float) DB::table('bahan_baku')->where('id_bahan', $this->idTinta)->value('stok'), 0.0001);
    }
}
