<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JerseyInputCepatHargaTest extends TestCase
{
    use RefreshDatabase;

    private int $tipe;
    private int $kategori;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->actingAs(User::factory()->create(['id_role' => $role->id_role]));

        $now = now();
        DB::table('jersey_product_types')->updateOrInsert(['name' => 'Jersey Uji'], ['is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->tipe = (int) DB::table('jersey_product_types')->where('name', 'Jersey Uji')->value('id');
        DB::table('jersey_categories')->updateOrInsert(['name' => 'Kategori Uji'], ['is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->kategori = (int) DB::table('jersey_categories')->where('name', 'Kategori Uji')->value('id');
    }

    private function bulk(array $tiers, ?int $bahan = null)
    {
        return $this->postJson('/jersey/master/price-settings/bulk', [
            'jersey_product_type_id' => $this->tipe, 'jersey_material_id' => $bahan, 'tiers' => $tiers,
        ]);
    }

    public function test_simpan_semua_tier_sekaligus_tanpa_kategori_dan_menggantikan_tier_lama_pada_cakupan_sama(): void
    {
        $this->bulk([['qty_min' => 1, 'qty_max' => 11, 'price' => 90000], ['qty_min' => 12, 'qty_max' => null, 'price' => 80000]])->assertOk();
        $this->assertEquals(2, DB::table('jersey_price_settings')->where('jersey_product_type_id', $this->tipe)->whereNull('jersey_category_id')->whereNull('jersey_material_id')->count());

        // Tier khusus kategori tidak boleh ikut terhapus.
        DB::table('jersey_price_settings')->insert(['jersey_product_type_id' => $this->tipe, 'jersey_category_id' => $this->kategori, 'qty_min' => 1, 'price' => 99000, 'is_active' => 1]);

        $this->bulk([['qty_min' => 1, 'qty_max' => null, 'price' => 70000]])->assertOk();

        $umum = DB::table('jersey_price_settings')->where('jersey_product_type_id', $this->tipe)->whereNull('jersey_category_id')->get();
        $this->assertCount(1, $umum);
        $this->assertEquals(70000, (float) $umum[0]->price);
        $this->assertEquals(1, DB::table('jersey_price_settings')->where('jersey_category_id', $this->kategori)->count());
    }

    public function test_rentang_tumpang_tindih_ditolak_dan_tidak_mengubah_apa_pun(): void
    {
        $this->bulk([['qty_min' => 1, 'qty_max' => 11, 'price' => 90000]])->assertOk();

        $this->bulk([['qty_min' => 1, 'qty_max' => 20, 'price' => 1], ['qty_min' => 15, 'qty_max' => 30, 'price' => 2]])->assertStatus(422);
        $this->bulk([['qty_min' => 1, 'qty_max' => null, 'price' => 1], ['qty_min' => 5, 'qty_max' => 9, 'price' => 2]])->assertStatus(422);

        $baris = DB::table('jersey_price_settings')->where('jersey_product_type_id', $this->tipe)->get();
        $this->assertCount(1, $baris);
        $this->assertEquals(90000, (float) $baris[0]->price);
    }

    public function test_tier_per_bahan_terpisah_dari_tier_umum_dan_halaman_master_tampil(): void
    {
        DB::table('jersey_materials')->updateOrInsert(['name' => 'Bahan Uji'], ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $bahan = (int) DB::table('jersey_materials')->where('name', 'Bahan Uji')->value('id');

        $this->bulk([['qty_min' => 1, 'qty_max' => null, 'price' => 90000]])->assertOk();
        $this->bulk([['qty_min' => 1, 'qty_max' => null, 'price' => 80000]], $bahan)->assertOk();

        $this->assertEquals(2, DB::table('jersey_price_settings')->where('jersey_product_type_id', $this->tipe)->count());
        $this->get('/jersey/master')->assertOk()->assertSee('Input Cepat per Tipe Produk');
    }

    public function test_validasi_tipe_wajib_dan_tier_tidak_boleh_kosong(): void
    {
        $this->postJson('/jersey/master/price-settings/bulk', ['tiers' => [['qty_min' => 1, 'price' => 1]]])->assertStatus(422);
        $this->bulk([])->assertStatus(422);
    }

    public function test_tipe_produk_nonaktif_tidak_muncul_di_dropdown_tier_harga(): void
    {
        DB::table("jersey_product_types")->updateOrInsert(["name" => "Tipe Mati Uji"], ["is_active" => 0, "created_at" => now(), "updated_at" => now()]);

        $html = $this->get("/jersey/master")->assertOk()->getContent();

        // Boleh muncul di tabel daftar tipe (untuk diaktifkan kembali), tapi tidak sebagai <option> pilihan.
        $this->assertStringNotContainsString(">Tipe Mati Uji</option>", $html);
        $this->assertStringContainsString(">Jersey Uji</option>", $html);
    }
}
