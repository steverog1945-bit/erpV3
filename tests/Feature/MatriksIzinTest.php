<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MatriksIzinTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        DB::table('menus')->insertOrIgnore([
            ['id_menu' => 7, 'nama_menu' => 'Data Konsumen', 'url_link' => 'konsumen', 'parent_id' => 0],
            ['id_menu' => 16, 'nama_menu' => 'Kasir', 'url_link' => 'kasir', 'parent_id' => 0],
        ]);
    }

    private function userRole(string $nama, array $izinMenu7): User
    {
        $r = Role::factory()->create(['nama_role' => $nama]);
        $this->actingAs($this->admin)->post('/setting-akses/update', ['role_id' => $r->id_role, 'izin' => [7 => $izinMenu7]])->assertRedirect();

        return User::factory()->create(['id_role' => $r->id_role]);
    }

    public function test_hanya_lihat_boleh_get_tapi_tidak_boleh_tambah_ubah_hapus(): void
    {
        $u = $this->userRole('Viewer', ['view' => 1]);

        $this->actingAs($u)->get('/konsumen')->assertOk();
        $this->actingAs($u)->post('/konsumen', ['nama_konsumen' => 'X'])->assertForbidden();
        $this->actingAs($u)->put('/konsumen/1', [])->assertForbidden();
        $this->actingAs($u)->delete('/konsumen/1')->assertForbidden();
    }

    public function test_tambah_otomatis_menyalakan_lihat_dan_hapus_terpisah(): void
    {
        $u = $this->userRole('Penambah', ['add' => 1]);

        $this->assertEquals(1, DB::table('role_permissions')->where('id_role', $u->id_role)->where('id_menu', 7)->value('can_view'));
        $this->actingAs($u)->get('/konsumen')->assertOk();
        $this->actingAs($u)->delete('/konsumen/1')->assertForbidden();
    }

    public function test_pengecualian_per_user_menang_atas_role_dan_bisa_direset(): void
    {
        $u = $this->userRole('Penambah2', ['view' => 1, 'add' => 1, 'edit' => 1, 'delete' => 1]);
        $this->actingAs($u)->get('/konsumen')->assertOk();

        // Cabut semua hak modul 7 khusus user ini.
        $this->actingAs($this->admin)->post("/users/{$u->id_user}/akses", ['izin' => []])->assertRedirect();
        $this->assertEquals(1, DB::table('user_permissions')->where('id_user', $u->id_user)->count());
        $this->actingAs($u)->get('/konsumen')->assertForbidden();

        $this->actingAs($this->admin)->post("/users/{$u->id_user}/akses/reset")->assertRedirect();
        $this->actingAs($u)->get('/konsumen')->assertOk();
    }

    public function test_halaman_matriks_render_untuk_role_dan_user(): void
    {
        $r = Role::factory()->create(['nama_role' => 'Kasir']);
        $u = User::factory()->create(['id_role' => $r->id_role]);
        $this->actingAs($this->admin)->get('/setting-akses?role=' . $r->id_role)->assertOk()->assertSee('Data Konsumen')->assertSee('Kelola');
        $this->actingAs($this->admin)->get("/users/{$u->id_user}/akses")->assertOk()->assertSee('Data Konsumen');
    }
}
