<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_dengan_kredensial_benar_berhasil(): void
    {
        $user = User::factory()->create([
            'username' => 'budi_test',
            'password' => Hash::make('rahasia123'),
        ]);

        $response = $this->post('/login', [
            'username' => 'budi_test',
            'password' => 'rahasia123',
        ]);

        $response->assertRedirect('dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_dengan_password_salah_ditolak(): void
    {
        User::factory()->create([
            'username' => 'budi_test',
            'password' => Hash::make('rahasia123'),
        ]);

        $response = $this->post('/login', [
            'username' => 'budi_test',
            'password' => 'password_salah',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_login_dengan_username_tidak_terdaftar_ditolak(): void
    {
        $response = $this->post('/login', [
            'username' => 'tidak_ada_user_seperti_ini',
            'password' => 'apapun',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_halaman_terproteksi_redirect_ke_login_kalau_belum_masuk(): void
    {
        $response = $this->get('/dashboard');

        // Checkpoint 4E.1: tujuan redirect adalah route('login') yang kini
        // menunjuk /masuk - endpoint yang selalu memuat form login. Sebelumnya
        // route('login') = '/', yang bisa jadi halaman builder tanpa form.
        $response->assertRedirect(route('login'));
        $response->assertRedirect(url('/masuk'));
    }

    public function test_user_yang_sudah_login_bisa_akses_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }

    public function test_logout_menghapus_sesi(): void
    {
        // Gate B OFF adalah konfigurasi default proyek ini.
        config(['landing_page_builder.enabled' => false]);

        $user = User::factory()->create();

        $this->actingAs($user);
        $this->assertAuthenticated();

        $response = $this->post('/logout');

        // Sesi berakhir dan staf diarahkan ke halaman login publik /masuk.
        // Sebelumnya tujuan ini '/' yang me-render halaman depan lama.
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_logout_dengan_gate_b_aktif_kembali_ke_beranda_publik(): void
    {
        config(['landing_page_builder.enabled' => true]);
        $user = User::factory()->create();

        $this->actingAs($user);
        $response = $this->post('/logout');

        // Gate B ON: '/' adalah Landing Page Published, jadi staf yang keluar
        // mendarat di beranda publik - bukan Dashboard, bukan form login.
        $response->assertRedirect(route('beranda'));
        $this->assertGuest();
    }

    public function test_role_administrator_selalu_lolos_menu_access(): void
    {
        $adminRole = Role::factory()->create(['nama_role' => 'Administrator']);
        $user = User::factory()->create(['id_role' => $adminRole->id_role]);

        // Administrator selalu lolos CheckMenuAccess tanpa perlu row
        // role_permissions apapun (lihat app/Http/Middleware/CheckMenuAccess.php)
        $response = $this->actingAs($user)->get('/kartu-stok');

        $response->assertOk();
    }
}
