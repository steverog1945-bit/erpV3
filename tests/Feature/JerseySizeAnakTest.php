<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JerseySizeAnakTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
    }

    private function sizes(): array
    {
        $now = now();
        DB::table('jersey_sizes')->updateOrInsert(['name' => 'M'], ['sort_order' => 12, 'is_active' => 1, 'price_surcharge' => 0, 'kelompok' => 'dewasa', 'price_percent' => 0, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('jersey_sizes')->updateOrInsert(['name' => 'Anak 8'], ['sort_order' => 3, 'is_active' => 1, 'price_surcharge' => 0, 'kelompok' => 'anak', 'price_percent' => -25, 'created_at' => $now, 'updated_at' => $now]);

        return [(int) DB::table('jersey_sizes')->where('name', 'M')->value('id'), (int) DB::table('jersey_sizes')->where('name', 'Anak 8')->value('id')];
    }

    public function test_ukuran_anak_diskon_persen_dari_harga_tier(): void
    {
        [$m, $anak] = $this->sizes();
        $tipe = DB::table('jersey_product_types')->insertGetId(['name' => 'JerseyUji', 'has_sleeve_collar' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('jersey_price_settings')->insert(['jersey_product_type_id' => $tipe, 'qty_min' => 1, 'qty_max' => 100, 'price' => 100000, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $res = $this->actingAs($this->admin)->postJson('/jersey/preview-harga', ['lines' => [[
            'nama_barang' => 'X', 'order_type' => 'quantity_only', 'jersey_product_type_id' => $tipe,
            'size_quantities' => [$m => 2, $anak => 2],
        ]]])->assertOk();

        // 4 pcs x 100.000 + 2 anak x (-25% = -25.000) = 350.000
        $this->assertEquals(350000, (float) $res->json('lines.0.subtotal'));
    }

    public function test_form_order_menampilkan_kelompok_anak_dan_dewasa_terpisah(): void
    {
        $this->sizes();
        $this->actingAs($this->admin)->get('/jersey/create')->assertOk()->assertSee('Ukuran Anak')->assertSee('Ukuran Dewasa')->assertSee('Anak 8')->assertSee('qty-total-line', false);
    }

    public function test_master_bisa_menyimpan_kelompok_dan_persen(): void
    {
        $this->actingAs($this->admin)->postJson('/jersey/master/sizes', ['name' => 'Anak 99', 'kelompok' => 'anak', 'price_percent' => -12.5])->assertOk();
        $row = DB::table('jersey_sizes')->where('name', 'Anak 99')->first();
        $this->assertEquals('anak', $row->kelompok);
        $this->assertEquals(-12.5, (float) $row->price_percent);

        $this->actingAs($this->admin)->postJson('/jersey/master/sizes', ['name' => 'Anak 98', 'price_percent' => -95])->assertStatus(422);
    }

    public function test_tier_harga_khusus_anak_dipakai_untuk_ukuran_anak(): void
    {
        [$m, $anak] = $this->sizes();
        DB::table('jersey_sizes')->where('id', $anak)->update(['price_percent' => 0]);
        $tipe = DB::table('jersey_product_types')->insertGetId(['name' => 'JerseyUji2', 'has_sleeve_collar' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('jersey_price_settings')->insert([
            ['jersey_product_type_id' => $tipe, 'qty_min' => 1, 'qty_max' => 100, 'price' => 100000, 'is_active' => 1, 'kelompok' => 'dewasa', 'created_at' => now(), 'updated_at' => now()],
            ['jersey_product_type_id' => $tipe, 'qty_min' => 1, 'qty_max' => 100, 'price' => 80000, 'is_active' => 1, 'kelompok' => 'anak', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $res = $this->actingAs($this->admin)->postJson('/jersey/preview-harga', ['lines' => [[
            'nama_barang' => 'X', 'order_type' => 'quantity_only', 'jersey_product_type_id' => $tipe, 'size_quantities' => [$m => 2, $anak => 3],
        ]]])->assertOk();

        // 2 x 100.000 + 3 x 80.000 = 440.000; harga satuan rata-rata tertimbang 88.000
        $this->assertEquals(440000, (float) $res->json('lines.0.subtotal'));
        $this->assertEquals(88000, (float) $res->json('lines.0.harga_satuan'));
    }

    public function test_bahan_jersey_bisa_disinkronkan_dengan_master_bahan_baku(): void
    {
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'x']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $bahan = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'KB1', 'nama_bahan' => 'Kain Brazil Uji', 'id_mesin' => $mesin, 'id_kategori' => $kat, 'stok' => 25.35, 'harga_rata_rata' => 58000, 'nilai_konversi' => 1, 'satuan_stok' => 'kg', 'status' => 'aktif']);
        $mat = DB::table('jersey_materials')->insertGetId(['name' => 'Brazil Uji', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $r = $this->actingAs($this->admin)->patchJson("/jersey/master/materials/{$mat}/bahan", ['id_bahan' => $bahan])->assertOk();
        $this->assertEquals(58000, $r->json('bahan.harga'));
        $this->assertEquals($bahan, DB::table('jersey_materials')->where('id', $mat)->value('id_bahan'));

        $this->actingAs($this->admin)->get('/jersey/master')->assertOk()->assertSee('Sinkron dengan Master Bahan Baku')->assertSee('Kain Brazil Uji');

        $this->actingAs($this->admin)->patchJson("/jersey/master/materials/{$mat}/bahan", ['id_bahan' => null])->assertOk();
        $this->assertNull(DB::table('jersey_materials')->where('id', $mat)->value('id_bahan'));
    }
}
