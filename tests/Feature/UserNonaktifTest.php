<?php

namespace Tests\Feature;

use App\Models\Karyawan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\TokenApiPengujian;
use Tests\TestCase;

/**
 * Fitur "Nonaktifkan akun pengguna" (karyawan resign, dsb.).
 *
 * Yang dikunci test:
 *  1. Admin bisa menonaktifkan & mengaktifkan kembali (data TIDAK dihapus).
 *  2. Akun nonaktif ditolak di pintu login (pesannya BUKAN "password salah").
 *  3. Sesi yang sudah berjalan dipaksa keluar pada request berikutnya.
 *  4. Admin tidak bisa menonaktifkan akun sendiri; non-admin tidak bisa
 *     menonaktifkan akun Administrator.
 *  5. Karyawan resign tidak bisa dibuatkan akun (server-side, bukan cuma
 *     dropdown form).
 *  6. Pintu API: token baru ditolak, token lama juga ditolak.
 */
class UserNonaktifTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
    }

    private function karyawanBaru(int $aktif = 1): Karyawan
    {
        return Karyawan::factory()->create(['status_aktif' => $aktif]);
    }

    private function userDenganKaryawan(int $aktifKaryawan = 1): User
    {
        $k = $this->karyawanBaru($aktifKaryawan);

        return User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Kasir Uji'])->id_role,
            'id_karyawan' => $k->id_karyawan,
        ]);
    }

    public function test_admin_bisa_menonaktifkan_dan_mengaktifkan_kembali(): void
    {
        $admin = $this->admin();
        $target = $this->userDenganKaryawan();

        $r = $this->actingAs($admin)->post(route('users.toggle-aktif', $target->id_user));
        $r->assertRedirect()->assertSessionHas('success');
        $this->assertSame(0, (int) DB::table('users')->where('id_user', $target->id_user)->value('is_active'));

        // Aktifkan lagi lewat tombol yang sama - data tidak pernah dihapus.
        $r2 = $this->actingAs($admin)->post(route('users.toggle-aktif', $target->id_user));
        $r2->assertRedirect()->assertSessionHas('success');
        $this->assertSame(1, (int) DB::table('users')->where('id_user', $target->id_user)->value('is_active'));
    }

    public function test_akun_nonaktif_ditolak_di_pintu_login(): void
    {
        $k = $this->karyawanBaru();
        $user = User::factory()->create([
            'username' => 'nonaktif_ui',
            'password' => Hash::make('rahasia-benar'),
            'id_karyawan' => $k->id_karyawan,
            'id_role' => Role::factory()->create(['nama_role' => 'Operator Uji'])->id_role,
        ]);
        DB::table('users')->where('id_user', $user->id_user)->update(['is_active' => 0]);

        $r = $this->post('/login', ['username' => 'nonaktif_ui', 'password' => 'rahasia-benar']);
        $r->assertRedirect(route('login'));
        $pesan = implode(' ', session('errors')->get('username') ?? []);
        $this->assertStringContainsString('dinonaktifkan', $pesan, 'Pesan login akun nonaktif harus jelas, bukan "password salah"');
        $this->assertGuest();
    }

    public function test_akun_nonaktif_ditendang_di_tengah_sesi(): void
    {
        $user = $this->userDenganKaryawan();
        DB::table('users')->where('id_user', $user->id_user)->update(['is_active' => 0]);

        // actAs = sesi lama dari sebelum penonaktifan masih "hidup".
        // Middleware PastikanUserAktif harus menghentikan request itu dan
        // mengarahkannya ke halaman masuk.
        $r = $this->actingAs(User::find($user->id_user))->get('/dashboard');
        $r->assertRedirect(route('login'));
    }

    public function test_admin_tidak_bisa_menonaktifkan_akun_sendiri(): void
    {
        $admin = $this->admin();
        $r = $this->actingAs($admin)->post(route('users.toggle-aktif', $admin->id_user));
        $r->assertSessionHas('error');
        $this->assertSame(1, (int) DB::table('users')->where('id_user', $admin->id_user)->value('is_active'));
    }

    public function test_non_admin_tidak_bisa_menonaktifkan_akun_administrator(): void
    {
        $nonAdmin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Kasir Biasa Uji'])->id_role]);
        $admin = $this->admin();

        $this->actingAs($nonAdmin)->post(route('users.toggle-aktif', $admin->id_user))->assertStatus(403);
        $this->assertSame(1, (int) DB::table('users')->where('id_user', $admin->id_user)->value('is_active'));
    }

    public function test_karyawan_resign_tidak_bisa_dibuatkan_akun(): void
    {
        $admin = $this->admin();
        $k = $this->karyawanBaru(0);

        $r = $this->actingAs($admin)->post(route('users.store'), [
            'id_karyawan' => $k->id_karyawan,
            'username' => 'karyawan_resign_1',
            'password' => 'rahasia-benar',
            'id_role' => Role::factory()->create(['nama_role' => 'Gudang Uji'])->id_role,
        ]);

        $r->assertSessionHas('error');
        $this->assertSame(0, DB::table('users')->where('username', 'karyawan_resign_1')->count());
    }

    public function test_token_api_baru_ditolak_dan_token_lama_dicabut_efektif(): void
    {
        config()->set('orion_api.enabled', true);
        $k = $this->karyawanBaru();
        $user = User::factory()->create([
            'username' => 'api_resign',
            'password' => Hash::make('rahasia-benar'),
            'id_karyawan' => $k->id_karyawan,
            'id_role' => Role::factory()->create(['nama_role' => 'Admin API Uji', 'boleh_lihat_harga' => 1])->id_role,
        ]);
        DB::table('menus')->insertOrIgnore([['id_menu' => 3, 'nama_menu' => 'Transaksi Baru', 'url_link' => 'penjualan', 'parent_id' => 0]]);
        DB::table('role_permissions')->insertOrIgnore([['id_role' => $user->id_role, 'id_menu' => 3, 'can_view' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0]]);

        // Token diterbitkan SEBELUM penonaktifan - harus tetap ditolak SETELAHNYA.
        $token = TokenApiPengujian::token($user);
        $this->getJson('/api/konsumen', TokenApiPengujian::header($token))->assertOk();

        DB::table('users')->where('id_user', $user->id_user)->update(['is_active' => 0]);

        $this->getJson('/api/konsumen', TokenApiPengujian::header($token))
            ->assertStatus(401)
            ->assertJsonPath('error', 'Akun tidak aktif. Hubungi Administrator.');

        // Token baru juga tidak bisa diterbitkan untuk akun ini.
        $this->postJson('/api/auth/token', ['username' => 'api_resign', 'password' => 'rahasia-benar'])
            ->assertStatus(401)
            ->assertJsonPath('error', 'Akun tidak aktif. Hubungi Administrator.');
    }
}
