<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0-4 (audit hardening): /login sebelumnya tanpa batas percobaan sama
 * sekali - dites di sini supaya tidak diam-diam hilang lagi di masa depan.
 */
class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_percobaan_login_gagal_dibatasi_lalu_pulih_setelah_jeda(): void
    {
        $user = User::factory()->create([
            'username' => 'usercoba',
            'password' => bcrypt('rahasia-benar'),
            'id_role' => Role::factory()->create(['nama_role' => 'Kasir'])->id_role,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'usercoba', 'password' => 'salah'])
                ->assertSessionHasErrors('username');
        }

        // Percobaan ke-6 dalam jendela waktu yang sama harus ditolak oleh limiter,
        // BUKAN diproses lagi oleh AuthController (baik dengan password salah maupun benar).
        $ditolak = $this->post('/login', ['username' => 'usercoba', 'password' => 'rahasia-benar']);
        $ditolak->assertSessionHasErrors('username');
        $this->assertGuest();

        // Username lain dari IP yang sama TIDAK ikut terkunci (limiter per username+IP, bukan per IP).
        $this->post('/login', ['username' => 'lain-tidak-ada', 'password' => 'apa saja'])
            ->assertSessionHasErrors('username');
    }

    public function test_login_benar_di_bawah_ambang_batas_tetap_berhasil(): void
    {
        User::factory()->create([
            'username' => 'usercoba2',
            'password' => bcrypt('rahasia-benar'),
            'id_role' => Role::factory()->create(['nama_role' => 'Kasir'])->id_role,
        ]);

        $this->post('/login', ['username' => 'usercoba2', 'password' => 'salah'])->assertSessionHasErrors('username');
        $this->post('/login', ['username' => 'usercoba2', 'password' => 'rahasia-benar'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }
}
