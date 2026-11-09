<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\AksesHarga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AksesHargaRoleTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $namaRole, bool $bolehHarga, array $menu = []): User
    {
        $role = Role::factory()->create(['nama_role' => $namaRole]);
        DB::table('roles')->where('id_role', $role->id_role)->update(['boleh_lihat_harga' => $bolehHarga]);
        foreach ($menu as $id) {
            DB::table('menus')->insertOrIgnore(['id_menu' => $id, 'nama_menu' => "Menu {$id}", 'url_link' => "m{$id}", 'parent_id' => 0]);
            DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => $id]);
        }

        return User::factory()->create(['id_role' => $role->id_role]);
    }

    public function test_operator_produksi_tidak_bisa_membuka_atau_mengubah_finishing_walau_menunya_diberikan(): void
    {
        $op = $this->user('Operator Produksi', false, [21, 22, 23]);

        $this->actingAs($op)->get('/finishing')->assertForbidden();
        $this->actingAs($op)->post('/finishing/1/harga', ['range_min' => 1, 'harga' => 100])->assertForbidden();
        $this->actingAs($op)->patch('/finishing-harga/1', ['range_min' => 1, 'harga' => 100])->assertForbidden();
        $this->actingAs($op)->delete('/finishing-harga/1')->assertForbidden();

        // Halaman produksi tetap terbuka.
        $this->actingAs($op)->get('/spk')->assertOk();
    }

    public function test_operator_desain_tidak_bisa_quotation_dan_timeline_pelanggan(): void
    {
        $od = $this->user('Operator Desain', false, [7, 41, 58]);
        $idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $idK = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Uji', 'telepon' => '0811', 'alamat' => '-', 'id_golongan' => $idGol]);

        $this->actingAs($od)->get('/quotation')->assertForbidden();
        $this->actingAs($od)->get("/konsumen/{$idK}/timeline")->assertForbidden();
        $this->actingAs($od)->get('/konsumen')->assertOk();
    }

    public function test_kolom_biaya_waste_dan_qc_disembunyikan_dari_role_tanpa_hak_harga(): void
    {
        $op = $this->user('Operator Produksi', false, [23, 57, 56]);
        $admin = $this->user('Administrator', true);

        $this->actingAs($op)->get("/waste")->assertOk()->assertDontSee(">Biaya</th>", false)->assertDontSee("font-extrabold text-rose-600\">Rp", false);
        $this->actingAs($op)->get('/qc')->assertOk()->assertDontSee('Biaya Material');

        $this->actingAs($admin)->get('/waste')->assertOk()->assertSee('>Biaya</th>', false);
        $this->actingAs($admin)->get('/qc')->assertOk()->assertSee('Biaya Material');
    }

    public function test_role_dengan_hak_harga_tidak_terpengaruh_dan_hak_bisa_diubah_di_pengaturan_akses(): void
    {
        $kasir = $this->user('Kasir', true, [21, 58]);
        $op = $this->user('Operator Produksi', false, [21]);
        $admin = $this->user('Administrator', true);

        $this->actingAs($kasir)->get('/finishing')->assertOk();
        $this->assertTrue(AksesHarga::bolehLihat($kasir));
        $this->assertFalse(AksesHarga::bolehLihat($op));
        $this->assertTrue(AksesHarga::bolehLihat($admin));

        $this->actingAs($admin)->get('/setting-akses')->assertOk()->assertSee('Boleh melihat');

        // Centang hak lihat harga untuk Operator Produksi, lalu Kasir dicabut. Administrator tetap Selalu.
        $this->actingAs($admin)->post('/setting-akses/update', ['permissions' => [$op->id_role => [21]], 'lihat_harga' => [$op->id_role]])->assertSessionHas('success');
        $this->assertTrue(AksesHarga::bolehLihat($op->fresh()));
        $this->assertFalse(AksesHarga::bolehLihat($kasir->fresh()));
        $this->assertTrue(AksesHarga::bolehLihat($admin->fresh()));
        $this->actingAs($op->fresh())->get('/finishing')->assertOk();
    }

    public function test_link_quotation_tidak_tampil_di_sidebar_operator_desain_tapi_tampil_untuk_kasir(): void
    {
        $od = $this->user('Operator Desain', false, [3, 41]);
        $kasir = $this->user('Kasir', true, [3, 41]);

        $this->actingAs($od)->get('/penjualan/kasir')->assertOk()->assertDontSee("href=\"" . url('/quotation') . "\"", false);
        $this->actingAs($kasir)->get('/penjualan/kasir')->assertOk()->assertSee("href=\"" . url('/quotation') . "\"", false);
    }
}
