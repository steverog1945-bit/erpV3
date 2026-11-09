<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit Fase 4 (alur ERP): kasir/admin berhak menimpa biaya cetak, tetapi
 * setiap penyimpangan dari price list HARUS meninggalkan jejak (nota, baris,
 * dari berapa ke berapa, siapa) - potongan harga tidak boleh diam-diam.
 */
class PenjualanJejakHargaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idKonsumen;
    private int $idGol;
    private int $idProduk;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00:00'));

        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'Spanduk Uji', 'metode_hitung' => 'quantity', 'status' => 'aktif']);
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

    private function jual(float $biayaCetak, int $qty = 5)
    {
        return $this->actingAs($this->admin)->postJson('/penjualan/store', [
            'id_konsumen' => $this->idKonsumen, 'id_golongan' => $this->idGol,
            'items' => [['id_produk' => $this->idProduk, 'nama_file' => 'banner.pdf', 'p' => 0, 'l' => 0, 'qty_input' => $qty, 'biaya_cetak' => $biayaCetak]],
        ]);
    }

    public function test_harga_sesuai_price_list_tidak_menimbulkan_catatan_penyimpangan(): void
    {
        $this->jual(50000)->assertOk();

        $this->assertSame(0, DB::table('audit_logs')->where('event', 'harga_manual')->count());
    }

    public function test_potongan_dari_price_list_tercatat_lengkap_dengan_pelaku_dan_selisih(): void
    {
        $this->jual(30000)->assertOk();   // price list 5 x 10.000 = 50.000, kasir menurunkan jadi 30.000

        $log = DB::table('audit_logs')->where('event', 'harga_manual')->first();
        $this->assertNotNull($log, 'Potongan harga harus meninggalkan jejak');
        $this->assertSame('transaksi', $log->auditable_type);
        $this->assertSame($this->admin->username, $log->user_label);
        $this->assertStringContainsString('potongan Rp 20.000', $log->keterangan);

        $baru = json_decode($log->new_values, true);
        $this->assertEquals(30000, $baru['dipakai']);
        $this->assertEquals(-20000, $baru['baris'][0]['selisih']);
        $this->assertEquals(50000, $baru['baris'][0]['price_list']);
        $this->assertSame('banner.pdf', $baru['baris'][0]['nama_file']);
        // Nota tersimpan dengan harga yang dipakai, bukan yang di price list.
        $this->assertEquals(30000, (float) DB::table('transaksi')->value('total_bayar'));
    }

    public function test_markup_di_atas_price_list_juga_tercatat_sebagai_tambahan(): void
    {
        $this->jual(65000)->assertOk();

        $log = DB::table('audit_logs')->where('event', 'harga_manual')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('tambahan Rp 15.000', $log->keterangan);
    }
}
