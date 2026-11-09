<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * API harga Input Order: hanya untuk peran yang punya menu Input Order, dan isinya hanya harga JUAL (yang memang
 * tampil di form Input Order, dihitung ulang di server saat simpan). Data biaya (HPP, harga beli bahan) tidak pernah
 * ikut terkirim, termasuk ke peran tanpa hak lihat harga.
 */
class AksesApiHargaTest extends TestCase
{
    use RefreshDatabase;

    private int $idProduk;
    private int $idGol;

    protected function setUp(): void
    {
        parent::setUp();
        $this->idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'Spanduk', 'metode_hitung' => 'ukuran', 'status' => 'Aktif']);
        DB::table('setting_harga')->insert(['id_produk' => $this->idProduk, 'id_golongan' => $this->idGol, 'range_min' => 0.01, 'harga_jual' => 25000]);
    }

    private function peran(string $nama, array $menu): User
    {
        $role = Role::factory()->create(['nama_role' => $nama]);
        foreach ($menu as $id) {
            DB::table('menus')->insertOrIgnore(['id_menu' => $id, 'nama_menu' => "M{$id}", 'url_link' => "m{$id}", 'parent_id' => 0]);
            DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => $id, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 0, 'can_delete' => 0]);
        }

        return User::factory()->create(['id_role' => $role->id_role]);
    }

    public function test_tanpa_menu_input_order_ditolak(): void
    {
        $this->actingAs($this->peran('Gudang', [7]))
            ->getJson("/api/penjualan/get-harga/{$this->idProduk}/{$this->idGol}/1")->assertForbidden();
    }

    public function test_input_order_tanpa_hak_harga_hanya_menerima_harga_jual(): void
    {
        $this->actingAs($this->peran('Operator Desain', [3, 41]));

        $harga = $this->getJson("/api/penjualan/get-harga/{$this->idProduk}/{$this->idGol}/1")->assertOk()->json();
        $this->assertEqualsCanonicalizing(['harga_jual', 'is_promo', 'promo_selesai'], array_keys($harga));
        $this->assertEquals(25000, $harga['harga_jual']);

        $produk = $this->getJson("/api/penjualan/produk/{$this->idProduk}")->assertOk()->json();
        foreach (array_keys($produk) as $kolom) {
            $this->assertDoesNotMatchRegularExpression('/hpp|beli|modal|rata/i', $kolom, "kolom biaya {$kolom} ikut terkirim");
        }
    }
}
