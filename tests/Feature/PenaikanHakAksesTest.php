<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit Fase 4: pengelola akun/hak akses yang BUKAN Administrator tidak boleh
 * menaikkan hak siapa pun ke atas dirinya (buat akun admin, naikkan diri
 * sendiri, ubah role Administrator). Semuanya dicek di server.
 */
class PenaikanHakAksesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $pengelola;
    private Role $roleAdmin;
    private Role $rolePengelola;
    private Role $roleBiasa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roleAdmin = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->rolePengelola = Role::factory()->create(['nama_role' => 'Staf HRD']);
        $this->roleBiasa = Role::factory()->create(['nama_role' => 'Kasir Uji']);
        $this->admin = User::factory()->create(['id_role' => $this->roleAdmin->id_role]);
        $this->pengelola = User::factory()->create(['id_role' => $this->rolePengelola->id_role]);

        DB::table('menus')->insertOrIgnore([
            ['id_menu' => 4, 'nama_menu' => 'Setting Akses', 'url_link' => 'setting-akses', 'parent_id' => 0],
            ['id_menu' => 6, 'nama_menu' => 'Data User', 'url_link' => 'users', 'parent_id' => 0],
        ]);
        foreach ([4, 6] as $m) {
            DB::table('role_permissions')->insert(['id_role' => $this->rolePengelola->id_role, 'id_menu' => $m, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1]);
        }
    }

    private function karyawan(string $nama): int
    {
        return DB::table('karyawan')->insertGetId(['nama_karyawan' => $nama, 'bagian' => 'Produksi', 'telepon' => '1', 'status_aktif' => 1]);
    }

    public function test_pengelola_tidak_bisa_membuat_akun_administrator(): void
    {
        $this->actingAs($this->pengelola)->post('/users/store', [
            'id_karyawan' => $this->karyawan('Calon Admin'), 'username' => 'calonadmin', 'password' => 'rahasia123', 'id_role' => $this->roleAdmin->id_role,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'calonadmin']);
    }

    public function test_pengelola_tetap_bisa_membuat_akun_role_biasa_dan_tercatat_di_audit(): void
    {
        $this->actingAs($this->pengelola)->post('/users/store', [
            'id_karyawan' => $this->karyawan('Kasir Baru'), 'username' => 'kasirbaru', 'password' => 'rahasia123', 'id_role' => $this->roleBiasa->id_role,
        ])->assertSessionHas('success');

        $id = DB::table('users')->where('username', 'kasirbaru')->value('id_user');
        $this->assertNotNull($id);
        $log = DB::table('audit_logs')->where('auditable_type', 'users')->where('auditable_id', $id)->where('event', 'created')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('rahasia123', json_encode($log), 'Password tidak boleh masuk audit log');
    }

    public function test_role_yang_tidak_ada_ditolak_validasi(): void
    {
        $this->actingAs($this->admin)->post('/users/store', [
            'id_karyawan' => $this->karyawan('X'), 'username' => 'tanparole', 'password' => 'rahasia123', 'id_role' => 987654,
        ])->assertSessionHasErrors('id_role');
    }

    public function test_pengelola_tidak_bisa_menaikkan_diri_sendiri_atau_mengubah_akun_administrator(): void
    {
        $this->actingAs($this->pengelola)->put("/users/update/{$this->pengelola->id_user}", [
            'username' => $this->pengelola->username, 'id_role' => $this->roleAdmin->id_role,
        ])->assertForbidden();
        $this->assertEquals($this->rolePengelola->id_role, $this->pengelola->fresh()->id_role);

        // Bahkan ke role biasa: akun sendiri bukan wewenang pengelola non-admin.
        $this->actingAs($this->pengelola)->put("/users/update/{$this->pengelola->id_user}", [
            'username' => $this->pengelola->username, 'id_role' => $this->roleBiasa->id_role,
        ])->assertForbidden();

        $this->actingAs($this->pengelola)->put("/users/update/{$this->admin->id_user}", [
            'username' => 'diambilalih', 'password' => 'passwordbaru1', 'id_role' => $this->roleBiasa->id_role,
        ])->assertForbidden();
        $this->assertNotSame('diambilalih', $this->admin->fresh()->username);

        $this->actingAs($this->pengelola)->delete("/users/delete/{$this->admin->id_user}")->assertForbidden();
        $this->assertNotNull(User::find($this->admin->id_user));
    }

    public function test_pengelola_boleh_mengubah_akun_biasa_dan_perubahan_role_tercatat(): void
    {
        $biasa = User::factory()->create(['id_role' => $this->roleBiasa->id_role]);

        $this->actingAs($this->pengelola)->put("/users/update/{$biasa->id_user}", [
            'username' => $biasa->username, 'id_role' => $this->rolePengelola->id_role,
        ])->assertSessionHas('success');

        $this->assertEquals($this->rolePengelola->id_role, $biasa->fresh()->id_role);
        $log = DB::table('audit_logs')->where('auditable_type', 'users')->where('auditable_id', $biasa->id_user)->where('event', 'updated')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('role berubah', $log->keterangan ?? json_encode($log));
    }

    public function test_pengelola_tidak_bisa_mengubah_izin_role_administrator_atau_rolenya_sendiri(): void
    {
        $sebelum = DB::table('role_permissions')->where('id_role', $this->rolePengelola->id_role)->count();

        $this->actingAs($this->pengelola)->post('/setting-akses/update', ['role_id' => $this->roleAdmin->id_role, 'izin' => [7 => ['view' => 1]]])->assertForbidden();
        $this->actingAs($this->pengelola)->post('/setting-akses/update', ['role_id' => $this->rolePengelola->id_role, 'izin' => [16 => ['view' => 1, 'add' => 1]]])->assertForbidden();

        $this->assertSame($sebelum, DB::table('role_permissions')->where('id_role', $this->rolePengelola->id_role)->count());
        $this->assertSame(0, DB::table('role_permissions')->where('id_role', $this->roleAdmin->id_role)->count());
    }

    public function test_setting_akses_khusus_administrator_pengelola_lain_ditolak_di_semua_jalur(): void
    {
        DB::table('menus')->insertOrIgnore(['id_menu' => 16, 'nama_menu' => 'Kasir', 'url_link' => 'kasir', 'parent_id' => 0]);
        $sebelum = DB::table('role_permissions')->where('id_role', $this->rolePengelola->id_role)->orderBy('id_menu')->pluck('id_menu')->all();

        // Walau rolenya punya menu Setting Akses (4), middleware hanya.admin menolak: hak akses tidak boleh mengatur dirinya sendiri.
        $this->actingAs($this->pengelola)->get('/setting-akses')->assertForbidden();
        $this->actingAs($this->pengelola)->post('/setting-akses/update', ['role_id' => $this->roleBiasa->id_role, 'izin' => [7 => ['view' => 1]]])->assertForbidden();
        $this->actingAs($this->pengelola)->post('/setting-akses/update', [
            'permissions' => [$this->rolePengelola->id_role => [4, 6, 16], $this->roleAdmin->id_role => [4, 6, 16]],
        ])->assertForbidden();

        $this->assertSame($sebelum, DB::table('role_permissions')->where('id_role', $this->rolePengelola->id_role)->orderBy('id_menu')->pluck('id_menu')->all());
        $this->assertSame(0, DB::table('role_permissions')->where('id_role', $this->roleAdmin->id_role)->count());
        $this->assertSame(0, DB::table('role_permissions')->where('id_role', $this->roleBiasa->id_role)->count());
    }

    /** Lapisan kedua (bila middleware suatu hari dilonggarkan): aturan Izin menolak menaikkan hak ke atas pelaku. */
    public function test_aturan_izin_menolak_role_administrator_dan_role_sendiri_bagi_non_admin(): void
    {
        $this->assertTrue(\App\Support\Izin::bolehKelolaRole($this->admin, (int) $this->roleAdmin->id_role));
        $this->assertTrue(\App\Support\Izin::bolehKelolaRole($this->admin, (int) $this->roleBiasa->id_role));

        $this->assertFalse(\App\Support\Izin::bolehKelolaRole($this->pengelola, (int) $this->roleAdmin->id_role));
        $this->assertFalse(\App\Support\Izin::bolehKelolaRole($this->pengelola, (int) $this->rolePengelola->id_role));
        $this->assertTrue(\App\Support\Izin::bolehKelolaRole($this->pengelola, (int) $this->roleBiasa->id_role));

        $this->assertFalse(\App\Support\Izin::bolehKelolaUser($this->pengelola, $this->admin));
        $this->assertFalse(\App\Support\Izin::bolehKelolaUser($this->pengelola, $this->pengelola));
        $this->assertTrue(\App\Support\Izin::bolehKelolaUser($this->admin, $this->pengelola));

        $this->assertFalse(\App\Support\Izin::bolehBerikanRole($this->pengelola, (int) $this->roleAdmin->id_role));
        $this->assertTrue(\App\Support\Izin::bolehBerikanRole($this->pengelola, (int) $this->rolePengelola->id_role), 'Memberi role setara dirinya bukan penaikan hak');
    }

    public function test_pengelola_tidak_bisa_memberi_pengecualian_izin_ke_dirinya_atau_administrator(): void
    {
        $this->actingAs($this->pengelola)->post("/users/{$this->pengelola->id_user}/akses", ['izin' => [16 => ['view' => 1]]])->assertForbidden();
        $this->actingAs($this->pengelola)->post("/users/{$this->admin->id_user}/akses", ['izin' => [16 => ['view' => 1]]])->assertForbidden();
        $this->actingAs($this->pengelola)->post("/users/{$this->pengelola->id_user}/akses/reset")->assertForbidden();
    }

    public function test_administrator_tetap_bebas_mengelola_semuanya(): void
    {
        $this->actingAs($this->admin)->post('/users/store', [
            'id_karyawan' => $this->karyawan('Admin Kedua'), 'username' => 'admin2', 'password' => 'rahasia123', 'id_role' => $this->roleAdmin->id_role,
        ])->assertSessionHas('success');
        $this->actingAs($this->admin)->post('/setting-akses/update', ['role_id' => $this->rolePengelola->id_role, 'izin' => [4 => ['view' => 1]]])->assertRedirect();

        $this->assertDatabaseHas('users', ['username' => 'admin2']);
    }

    public function test_pengelola_tidak_bisa_memberi_atau_mengambil_alih_hak_yang_tidak_dimilikinya(): void
    {
        // Role "Penyetuju" punya menu Approval (50) yang tidak dimiliki pengelola.
        $penyetuju = Role::factory()->create(['nama_role' => 'Penyetuju Uji']);
        DB::table('menus')->insertOrIgnore(['id_menu' => 50, 'nama_menu' => 'Approval', 'url_link' => 'approval', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $penyetuju->id_role, 'id_menu' => 50, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 0]);

        // Membuat akun dengan role yang haknya melebihi pengelola: ditolak.
        $this->actingAs($this->pengelola)->post('/users/store', [
            'id_karyawan' => $this->karyawan('Calon Penyetuju'), 'username' => 'calonpenyetuju', 'password' => 'rahasia123', 'id_role' => $penyetuju->id_role,
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['username' => 'calonpenyetuju']);

        // Mengganti kata sandi akun penyetuju lalu masuk sebagai dia: ditolak.
        $akunPenyetuju = User::factory()->create(['id_role' => $penyetuju->id_role]);
        $hashLama = $akunPenyetuju->password;
        $this->actingAs($this->pengelola)->put("/users/update/{$akunPenyetuju->id_user}", [
            'username' => $akunPenyetuju->username, 'password' => 'passwordbaru1', 'id_role' => $penyetuju->id_role,
        ])->assertForbidden();
        $this->assertSame($hashLama, $akunPenyetuju->fresh()->password);
    }
}
