<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi bug "diskon tidak memotong di perkiraan" (input order Jersey).
 * Server preview HARUS membagikan diskon ke tiap baris (diskon_baris) supaya
 * kartu perkiraan harga barang menampilkan harga SETELAH diskon, dan jumlah
 * potongan per baris HARUS persis = diskon yang diketik (sisa pembulatan ke
 * baris terbesar, tidak pernah lebih/kurang).
 */
class JerseyDiskonPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
    }

    private function sizes(): int
    {
        $now = now();
        DB::table('jersey_sizes')->updateOrInsert(
            ['name' => 'M'],
            ['sort_order' => 12, 'is_active' => 1, 'price_surcharge' => 0, 'kelompok' => 'dewasa', 'price_percent' => 0, 'created_at' => $now, 'updated_at' => $now]
        );

        return (int) DB::table('jersey_sizes')->where('name', 'M')->value('id');
    }

    private function tipeDenganTier(): int
    {
        $now = now();
        $tipe = DB::table('jersey_product_types')->insertGetId(['name' => 'Jersey Diskon Uji', 'has_sleeve_collar' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('jersey_price_settings')->insert(['jersey_product_type_id' => $tipe, 'qty_min' => 1, 'qty_max' => null, 'price' => 100000, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);

        return $tipe;
    }

    public function test_diskon_memotong_total_order_di_preview(): void
    {
        $m = $this->sizes();
        $tipe = $this->tipeDenganTier();

        $res = $this->actingAs($this->admin)->postJson('/jersey/preview-harga', [
            'lines' => [[
                'nama_barang' => 'X', 'order_type' => 'quantity_only', 'jersey_product_type_id' => $tipe,
                'size_quantities' => [$m => 4],
            ]],
            'discount' => '200.000', // format ribuan id-ID seperti yang tersimpan di input format-ribuan
        ])->assertOk();

        // 4 pcs x 100.000 = 400.000; diskon 200.000 -> 200.000
        $this->assertEquals(400000, (float) $res->json('total_barang'));
        $this->assertEquals(200000, (float) $res->json('total_order'));
        $this->assertEquals(200000, (float) $res->json('diskon'));
        $this->assertEquals(200000, (float) $res->json('lines.0.diskon_baris'));
    }

    public function test_diskon_dibagi_proporsional_dan_jumlahnya_persis_sama(): void
    {
        $m = $this->sizes();
        $tipe = $this->tipeDenganTier();

        // Dua baris: 1 pcs (100.000) & 3 pcs (300.000); diskon 200.000.
        $res = $this->actingAs($this->admin)->postJson('/jersey/preview-harga', [
            'lines' => [
                ['nama_barang' => 'A', 'order_type' => 'quantity_only', 'jersey_product_type_id' => $tipe, 'size_quantities' => [$m => 1]],
                ['nama_barang' => 'B', 'order_type' => 'quantity_only', 'jersey_product_type_id' => $tipe, 'size_quantities' => [$m => 3]],
            ],
            'discount' => '200000',
        ])->assertOk();

        $this->assertEquals(50000, (float) $res->json('lines.0.diskon_baris'));
        $this->assertEquals(150000, (float) $res->json('lines.1.diskon_baris'));
        $this->assertEquals(
            200000,
            (float) $res->json('lines.0.diskon_baris') + (float) $res->json('lines.1.diskon_baris'),
            'Jumlah potongan per baris harus persis = diskon'
        );
    }

    public function test_sisa_pembulatan_ditaruh_ke_baris_terbesar(): void
    {
        $m = $this->sizes();
        $tipe = $this->tipeDenganTier();

        // Tiga baris @1 pcs (100.000); diskon 100 -> raw 33,33,33 (Σ 99) -> sisa 1 ke baris pertama (semua sama besar).
        $res = $this->actingAs($this->admin)->postJson('/jersey/preview-harga', [
            'lines' => [
                ['nama_barang' => 'A', 'order_type' => 'quantity_only', 'jersey_product_type_id' => $tipe, 'size_quantities' => [$m => 1]],
                ['nama_barang' => 'B', 'order_type' => 'quantity_only', 'jersey_product_type_id' => $tipe, 'size_quantities' => [$m => 1]],
                ['nama_barang' => 'C', 'order_type' => 'quantity_only', 'jersey_product_type_id' => $tipe, 'size_quantities' => [$m => 1]],
            ],
            'discount' => '100',
        ])->assertOk();

        $bag = [(int) $res->json('lines.0.diskon_baris'), (int) $res->json('lines.1.diskon_baris'), (int) $res->json('lines.2.diskon_baris')];
        $this->assertEquals(100, array_sum($bag), 'Sisa pembulatan tidak boleh hilang');
        foreach ($bag as $b) {
            $this->assertContains($b, [33, 34]);
        }
    }

    public function test_biaya_tambahan_menambah_dan_diskon_melebihi_bruto_dijepit_nol(): void
    {
        $m = $this->sizes();
        $tipe = $this->tipeDenganTier();

        // Tambahan +50.000, diskon 100.000 -> tetap positif.
        $res = $this->actingAs($this->admin)->postJson('/jersey/preview-harga', [
            'lines' => [[
                'nama_barang' => 'X', 'order_type' => 'quantity_only', 'jersey_product_type_id' => $tipe,
                'size_quantities' => [$m => 1],
            ]],
            'discount' => '50000', 'additional_charge' => '50.000',
        ])->assertOk();
        $this->assertEquals(100000, (float) $res->json('total_order'));
        $this->assertEquals(50000, (float) $res->json('biaya_tambahan'));

        // Diskon lebih besar dari bruto -> total 0, pembagian per baris di-clamp.
        $res2 = $this->actingAs($this->admin)->postJson('/jersey/preview-harga', [
            'lines' => [[
                'nama_barang' => 'Y', 'order_type' => 'quantity_only', 'jersey_product_type_id' => $tipe,
                'size_quantities' => [$m => 1],
            ]],
            'discount' => '99999999',
        ])->assertOk();
        $this->assertEquals(0.0, (float) $res2->json('total_order'));
        $this->assertEquals(100000, (float) $res2->json('lines.0.diskon_baris'), 'Potongan per baris tidak boleh melebihi bruto baris');
    }
}
