<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P0-3 (audit hardening): controller yang mengubah harga & BOM menulis
 * lewat DB::table() sehingga trait Auditable (Eloquent) tidak pernah
 * terpicu, dan sebelumnya tidak satu pun memanggil AuditLogger::log()
 * manual - perubahan harga jual & formula BOM sama sekali tidak
 * tercatat. Tes ini memastikan titik-titik itu sekarang tercatat,
 * tanpa mengganggu response/alur yang sudah ada.
 */
class AuditTrailHargaBomTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idGolongan;
    private int $idBahan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->idGolongan = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin Uji', 'tipe_mesin' => 'Cetak']);
        $idKategori = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kategori Uji']);
        // Jangan hardcode id_bahan = 1: seed yang dijalankan otomatis saat
        // test (produk "Cetak Kain Jersey" dll.) sudah terlanjur memakan id 1.
        $this->idBahan = DB::table('bahan_baku')->insertGetId([
            'nama_bahan' => 'Bahan A', 'kode_bahan' => 'BA1', 'satuan_stok' => 'roll',
            'id_mesin' => $idMesin, 'id_kategori' => $idKategori,
            'nilai_konversi' => 1, 'stok' => 100, 'harga_rata_rata' => 1000,
            'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_bom_produk_baru_tercatat_di_audit_log(): void
    {
        $this->actingAs($this->admin)->post('/produk', [
            'nama_produk' => 'Produk Uji', 'metode_hitung' => 'quantity', 'id_bahan' => [1], 'cara_hitung_pemakaian' => ['Q'],
        ])->assertRedirect();

        $idProduk = DB::table('produk')->where('nama_produk', 'Produk Uji')->value('id_produk');
        $this->assertNotNull($idProduk);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => 'produk', 'auditable_id' => $idProduk]);
    }

    public function test_bom_produk_yang_diedit_tercatat_di_audit_log(): void
    {
        $idProduk = DB::table('produk')->insertGetId([
            'nama_produk' => 'Produk Edit', 'metode_hitung' => 'quantity', 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('bom_detail')->insert(['id_produk' => $idProduk, 'id_bahan' => 1, 'cara_hitung_pemakaian' => 'Q', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->admin)->put("/produk/{$idProduk}", [
            'nama_produk' => 'Produk Edit', 'metode_hitung' => 'quantity', 'id_bahan' => [1], 'cara_hitung_pemakaian' => ['Q*2'],
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['event' => 'updated', 'auditable_type' => 'produk', 'auditable_id' => $idProduk]);
        $this->assertSame('Q*2', DB::table('bom_detail')->where('id_produk', $idProduk)->value('cara_hitung_pemakaian'), 'perilaku BOM sync tidak boleh berubah oleh penambahan audit log');
    }

    public function test_produk_yang_dihapus_tercatat_di_audit_log(): void
    {
        $idProduk = DB::table('produk')->insertGetId([
            'nama_produk' => 'Produk Hapus', 'metode_hitung' => 'quantity', 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)->delete("/produk/{$idProduk}")->assertRedirect();

        $this->assertDatabaseMissing('produk', ['id_produk' => $idProduk]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'auditable_type' => 'produk', 'auditable_id' => $idProduk]);
    }

    public function test_perubahan_harga_price_list_tercatat_di_audit_log(): void
    {
        $idProduk = DB::table('produk')->insertGetId([
            'nama_produk' => 'Produk Harga', 'metode_hitung' => 'quantity', 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)->postJson("/setting-harga/{$idProduk}/tier", [
            'id_golongan' => $this->idGolongan, 'range_min' => 1, 'range_max' => null, 'harga_jual' => 15000,
        ])->assertOk()->assertJson(['success' => true]);

        $idTier = DB::table('setting_harga')->where('id_produk', $idProduk)->value('id_harga');
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => 'setting_harga', 'auditable_id' => $idTier]);

        $this->actingAs($this->admin)->patchJson("/setting-harga-tier/{$idTier}", [
            'range_min' => 1, 'range_max' => null, 'harga_jual' => 20000,
        ])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event' => 'updated', 'auditable_type' => 'setting_harga', 'auditable_id' => $idTier]);

        $this->actingAs($this->admin)->deleteJson("/setting-harga-tier/{$idTier}")->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'auditable_type' => 'setting_harga', 'auditable_id' => $idTier]);
    }
}
