<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Master Finishing harus bisa mengelola mode Potong Manual (LINEAR_GRID).
 *
 * Sebelumnya metode_hitung di halaman Master Finishing hanya menerima
 * luas / keliling / quantity, padahal Potong Manual memakai LINEAR_GRID.
 * Akibatnya finishing Potong Manual tidak bisa dibuat maupun diubah tarifnya
 * lewat UI - harus lewat SQL. Dan "Potong Manual A3" (mesin KONICA MINOLTA)
 * yang dipakai untuk produk A3 ikut tidak bisa dikelola.
 */
class FinishingPotongManualTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
        DB::table('roles')->where('nama_role', 'Administrator')->update(['boleh_lihat_harga' => true]);
    }

    public function test_halaman_master_finishing_menampilkan_potong_manual_dengan_label_yang_tepat(): void
    {
        $id = DB::table('finishing')->insertGetId([
            'nama_finishing' => 'Potong Manual A3', 'harga_default' => 450,
            'metode_hitung' => 'LINEAR_GRID', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $r = $this->actingAs($this->admin)->get('/finishing');

        $r->assertOk();
        $r->assertSee('Potong Manual A3');
        $r->assertSee('Potong Manual (m)');
        // Opsi metode hitung di form tambah/ubah harus memuat LINEAR_GRID.
        $r->assertSee('Potong Manual - tarif per meter potong', false);
    }

    public function test_finishing_potong_manual_bisa_ditambah_lewat_form(): void
    {
        $r = $this->actingAs($this->admin)->post('/finishing', [
            'nama_finishing' => 'Potong Manual Uji',
            'harga_default' => 500,
            'id_mesin' => null,
            'id_kategori' => null,
            'metode_hitung' => 'LINEAR_GRID',
            'keterangan' => 'uji',
        ]);

        $r->assertSessionHasNoErrors();
        $this->assertDatabaseHas('finishing', ['nama_finishing' => 'Potong Manual Uji', 'metode_hitung' => 'LINEAR_GRID']);
    }

    public function test_tarif_finishing_potong_manual_bisa_diubah_lewat_form(): void
    {
        $id = DB::table('finishing')->insertGetId([
            'nama_finishing' => 'Potong Manual A3', 'harga_default' => 450,
            'metode_hitung' => 'LINEAR_GRID', 'minimum_charge' => 5000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $r = $this->actingAs($this->admin)->put("/finishing/{$id}", [
            'nama_finishing' => 'Potong Manual A3',
            'harga_default' => 600,
            'minimum_charge' => 8000,
            'id_mesin' => null,
            'id_kategori' => null,
            'metode_hitung' => 'LINEAR_GRID',
            'keterangan' => 'tarif baru',
        ]);

        $r->assertSessionHasNoErrors();
        $this->assertSame(600.0, (float) DB::table('finishing')->where('id_finishing', $id)->value('harga_default'));
        $this->assertSame(8000.0, (float) DB::table('finishing')->where('id_finishing', $id)->value('minimum_charge'));
    }

    /** Minimum charge harus bisa diubah dari UI - inilah alasan kolomnya ada. */
    public function test_minimum_charge_bisa_diubah_lewat_form(): void
    {
        $id = DB::table('finishing')->insertGetId([
            'nama_finishing' => 'Potong Manual A3', 'harga_default' => 450,
            'metode_hitung' => 'LINEAR_GRID', 'minimum_charge' => 5000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $r = $this->actingAs($this->admin)->put("/finishing/{$id}", [
            'nama_finishing' => 'Potong Manual A3',
            'harga_default' => 450,
            'minimum_charge' => 2500,
            'id_mesin' => null,
            'id_kategori' => null,
            'metode_hitung' => 'LINEAR_GRID',
        ]);

        $r->assertSessionHasNoErrors();
        $this->assertSame(2500.0, (float) DB::table('finishing')->where('id_finishing', $id)->value('minimum_charge'));
    }

    /** Kolom minimum charge harus ada di form tambah & ubah. */
    public function test_form_master_finishing_memiliki_field_minimum_charge(): void
    {
        $r = $this->actingAs($this->admin)->get('/finishing');

        $r->assertOk();
        $r->assertSee('Minimum Charge (Rp)');
        $r->assertSee('name="minimum_charge"', false);
        $r->assertSee('id="edit_minimum_charge"', false);
    }
}
