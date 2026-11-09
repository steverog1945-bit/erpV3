<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Audit Fase 4: header pengaman dasar dan jejak audit masuk/keluar/gagal masuk. */
class KeamananDasarTest extends TestCase
{
    use RefreshDatabase;

    public function test_semua_respons_web_membawa_header_pengaman_dan_kamera_kasir_tetap_boleh(): void
    {
        $r = $this->get('/');

        $r->assertOk();
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $r->headers->get('X-Frame-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $r->headers->get('Referrer-Policy'));
        // Kasir memindai barcode nota dengan kamera: tidak boleh ikut dimatikan.
        $this->assertStringContainsString('camera=(self)', $r->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('microphone=()', $r->headers->get('Permissions-Policy'));
    }

    public function test_masuk_dan_keluar_tercatat_di_audit_tanpa_password(): void
    {
        $user = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Kasir Uji'])->id_role,
            'username' => 'sari_kasir', 'password' => Hash::make('kataSandiRahasia1'),
        ]);

        $this->post('/login', ['username' => 'sari_kasir', 'password' => 'kataSandiRahasia1'])->assertRedirect('dashboard');
        $this->post('/logout');

        $events = DB::table('audit_logs')->where('auditable_type', 'users')->where('auditable_id', $user->id_user)->pluck('event')->all();
        $this->assertContains('login', $events);
        $this->assertContains('logout', $events);
        $this->assertStringNotContainsString('kataSandiRahasia1', json_encode(DB::table('audit_logs')->get()));
    }

    public function test_login_gagal_tercatat_dengan_nama_akun_bila_akun_ada(): void
    {
        $user = User::factory()->create(['username' => 'dewi_gudang', 'password' => Hash::make('benar12345')]);

        $this->post('/login', ['username' => 'dewi_gudang', 'password' => 'salah-total'])->assertSessionHasErrors('username');

        $log = DB::table('audit_logs')->where('event', 'login_gagal')->first();
        $this->assertNotNull($log);
        $this->assertSame((int) $user->id_user, (int) $log->auditable_id);
        $this->assertStringContainsString('dewi_gudang', $log->keterangan);
        $this->assertStringNotContainsString('salah-total', json_encode($log));
    }

    public function test_password_yang_salah_diketik_di_kolom_username_tidak_masuk_log(): void
    {
        $this->post('/login', ['username' => 'PasswordKu!2026', 'password' => 'x'])->assertSessionHasErrors('username');

        $log = DB::table('audit_logs')->where('event', 'login_gagal')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->auditable_id);
        $this->assertStringNotContainsString('PasswordKu', json_encode(DB::table('audit_logs')->get()));
        $this->assertStringContainsString('tidak dikenal', $log->keterangan);
    }

    public function test_halaman_log_aktivitas_menampilkan_aksi_masuk_dengan_bahasa_manusia(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->post('/login', ['username' => 'tidak_ada', 'password' => 'x']);

        $this->actingAs($admin)->get('/log-aktivitas')->assertOk()->assertSee('Gagal masuk');
    }
}
