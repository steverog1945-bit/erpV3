<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit POS: pengiriman nota yang diulang (jaringan putus, dua tab) tidak boleh
 * melahirkan nota kedua. Server memproses satu submit_id hanya sekali.
 */
class PenjualanSekaliJalanTest extends TestCase
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
        Cache::flush();

        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'Spanduk', 'metode_hitung' => 'quantity', 'status' => 'aktif']);
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

    private function kirim(array $tambahan = [], ?int $idProduk = null)
    {
        return $this->actingAs($this->admin)->postJson('/penjualan/store', array_merge([
            'id_konsumen' => $this->idKonsumen, 'id_golongan' => $this->idGol,
            'items' => [['id_produk' => $idProduk ?? $this->idProduk, 'nama_file' => 'a.pdf', 'p' => 0, 'l' => 0, 'qty_input' => 5, 'biaya_cetak' => 50000]],
        ], $tambahan));
    }

    public function test_pengiriman_ulang_dengan_submit_id_sama_menghasilkan_satu_nota_dan_hasil_yang_sama(): void
    {
        $a = $this->kirim(['submit_id' => 'uji-abc'])->assertOk();
        $b = $this->kirim(['submit_id' => 'uji-abc'])->assertOk();   // klien mengira gagal lalu kirim lagi

        $this->assertSame(1, DB::table('transaksi')->count());
        $this->assertSame($a->json('nomor_nota'), $b->json('nomor_nota'));
        $this->assertSame($a->json('id_transaksi'), $b->json('id_transaksi'));
        $this->assertTrue($b->json('ulang'));
        $this->assertNull($a->json('ulang'));
    }

    public function test_submit_id_berbeda_atau_tanpa_submit_id_tetap_membuat_nota_terpisah(): void
    {
        $this->kirim(['submit_id' => 'satu'])->assertOk();
        $this->kirim(['submit_id' => 'dua'])->assertOk();
        $this->kirim()->assertOk();   // klien lama tanpa submit_id

        $this->assertSame(3, DB::table('transaksi')->count());
    }

    public function test_pengiriman_yang_gagal_melepas_kunci_sehingga_perbaikan_bisa_dikirim_dengan_id_sama(): void
    {
        // Produk tidak ada -> ditolak (422). Tidak boleh mengunci id itu.
        $this->kirim(['submit_id' => 'uji-perbaiki'], 987654)->assertStatus(422);
        $this->assertSame(0, DB::table('transaksi')->count());

        $this->kirim(['submit_id' => 'uji-perbaiki'])->assertOk();

        $this->assertSame(1, DB::table('transaksi')->count());
    }

    public function test_kunci_terpisah_per_pengguna(): void
    {
        $lain = User::factory()->create(['id_role' => $this->admin->id_role]);

        $this->kirim(['submit_id' => 'sama'])->assertOk();
        $this->actingAs($lain)->postJson('/penjualan/store', [
            'id_konsumen' => $this->idKonsumen, 'id_golongan' => $this->idGol, 'submit_id' => 'sama',
            'items' => [['id_produk' => $this->idProduk, 'nama_file' => 'b.pdf', 'p' => 0, 'l' => 0, 'qty_input' => 1, 'biaya_cetak' => 10000]],
        ])->assertOk();

        $this->assertSame(2, DB::table('transaksi')->count());
    }
}
