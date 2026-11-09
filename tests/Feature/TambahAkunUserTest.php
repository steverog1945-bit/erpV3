<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Regression: "Tambah Akun tidak tersimpan" di Manajemen Pengguna.
//
// Jalur yang diuji (semua lewat HTTP sungguhan ke route users.store):
//   form modal → POST /users/store → UserController::store → users table
//
// Fixture memakai kolom aktual:
//   karyawan : nama_karyawan, bagian (enum), telepon
//   roles    : nama_role
//   users    : id_karyawan, username, password, id_role
class TambahAkunUserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idKaryawan;

    protected function setUp(): void
    {
        parent::setUp();

        $idRoleAdmin = (int) DB::table('roles')->insertGetId(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $idRoleAdmin]);

        $this->idKaryawan = (int) DB::table('karyawan')->insertGetId([
            'nama_karyawan' => 'Karyawan Uji',
            'bagian' => 'Kasir',
            'telepon' => '081234567890',
        ]);
    }

    private function payload(array $ubah = []): array
    {
        return array_merge([
            'id_karyawan' => $this->idKaryawan,
            'username' => 'akun_baru_uji',
            'password' => 'rahasia123',
            'id_role' => $this->admin->id_role,
        ], $ubah);
    }

    /* ---------- positif ---------- */

    public function test_admin_bisa_membuka_halaman_pengguna(): void
    {
        $r = $this->actingAs($this->admin)->get('/users');

        $r->assertOk();
        $html = (string) $r->getContent();
        $this->assertStringContainsString('Manajemen Pengguna', $html);
        $this->assertStringContainsString('Tambah', $html);
    }

    public function test_akun_valid_tersimpan(): void
    {
        $r = $this->actingAs($this->admin)
            ->post('/users/store', $this->payload());

        $r->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['username' => 'akun_baru_uji']);
    }

    public function test_username_tersimpan(): void
    {
        $this->actingAs($this->admin)->post('/users/store', $this->payload());

        $this->assertSame(
            'akun_baru_uji',
            DB::table('users')->where('username', 'akun_baru_uji')->value('username')
        );
    }

    public function test_role_tersimpan_sesuai_pilihan(): void
    {
        $this->actingAs($this->admin)->post('/users/store', $this->payload());

        $user = DB::table('users')->where('username', 'akun_baru_uji')->first();
        $this->assertSame((int) $this->admin->id_role, (int) $user->id_role);
    }

    public function test_karyawan_terkait_tersimpan(): void
    {
        $this->actingAs($this->admin)->post('/users/store', $this->payload());

        $user = DB::table('users')->where('username', 'akun_baru_uji')->first();
        $this->assertSame($this->idKaryawan, (int) $user->id_karyawan);
    }

    public function test_password_tersimpan_sebagai_hash(): void
    {
        $this->actingAs($this->admin)->post('/users/store', $this->payload());

        $hash = DB::table('users')->where('username', 'akun_baru_uji')->value('password');
        $this->assertNotSame('rahasia123', $hash, 'Password tidak boleh plaintext');
        $this->assertTrue(Hash::check('rahasia123', $hash), 'Hash harus bisa diverifikasi');
    }

    public function test_response_redirect_kembali(): void
    {
        $r = $this->actingAs($this->admin)->post('/users/store', $this->payload());

        $this->assertTrue($r->isRedirect(), 'Harus redirect balik ke halaman pengguna');
    }

    /* ---------- negatif ---------- */

    public function test_username_kosong_ditolak(): void
    {
        $r = $this->actingAs($this->admin)
            ->post('/users/store', $this->payload(['username' => '']));

        $r->assertSessionHasErrors('username');
        $this->assertDatabaseMissing('users', ['username' => '']);
    }

    public function test_password_kosong_ditolak(): void
    {
        $r = $this->actingAs($this->admin)
            ->post('/users/store', $this->payload(['password' => '']));

        $r->assertSessionHasErrors('password');
    }

    public function test_password_kurang_dari_8_ditolak(): void
    {
        $r = $this->actingAs($this->admin)
            ->post('/users/store', $this->payload(['password' => 'pendek']));

        $r->assertSessionHasErrors('password');
    }

    public function test_karyawan_kosong_ditolak(): void
    {
        $r = $this->actingAs($this->admin)
            ->post('/users/store', $this->payload(['id_karyawan' => '']));

        $r->assertSessionHasErrors('id_karyawan');
    }

    public function test_role_kosong_ditolak(): void
    {
        $r = $this->actingAs($this->admin)
            ->post('/users/store', $this->payload(['id_role' => '']));

        $r->assertSessionHasErrors('id_role');
    }

    public function test_username_duplicate_ditolak(): void
    {
        $this->actingAs($this->admin)->post('/users/store', $this->payload());

        $r = $this->actingAs($this->admin)
            ->post('/users/store', $this->payload(['password' => 'rahasia123']));

        $r->assertSessionHasErrors('username');
        $this->assertSame(1, DB::table('users')->where('username', 'akun_baru_uji')->count());
    }

    public function test_karyawan_sudah_punya_user_ditolak(): void
    {
        $this->actingAs($this->admin)->post('/users/store', $this->payload());

        $r = $this->actingAs($this->admin)
            ->post('/users/store', $this->payload(['username' => 'akun_kedua']));

        $r->assertSessionHasErrors('id_karyawan');
    }

    public function test_tanpa_login_ditolak(): void
    {
        $r = $this->post('/users/store', $this->payload());

        $this->assertTrue($r->isRedirect() || $r->status() === 401 || $r->status() === 403);
        $this->assertDatabaseMissing('users', ['username' => 'akun_baru_uji']);
    }

    public function test_user_non_admin_tidak_bisa_buat_akun_admin(): void
    {
        $idRoleBiasa = (int) DB::table('roles')->insertGetId(['nama_role' => 'Operator Produksi']);
        $userBiasa = User::factory()->create(['id_role' => $idRoleBiasa]);

        $r = $this->actingAs($userBiasa)
            ->post('/users/store', $this->payload(['id_role' => $this->admin->id_role]));

        $this->assertSame(403, $r->status());
        $this->assertDatabaseMissing('users', ['username' => 'akun_baru_uji']);
    }

    /* ---------- umpan balik validasi ---------- */

    // Akar masalah "tidak terjadi apa-apa": validasi server menolak (mis. password
    // < 8 karakter, tidak dicegah HTML), tetapi layout tidak pernah merender
    // $errors sehingga pengguna tidak melihat alasan penolakan sama sekali.
    public function test_penolakan_validasi_terlihat_oleh_pengguna(): void
    {
        $r = $this->actingAs($this->admin)
            ->from('/users')
            ->followingRedirects()
            ->post('/users/store', $this->payload(['password' => '123']));

        $html = (string) $r->getContent();
        $this->assertStringContainsString('Periksa kembali isian', $html);
        $this->assertStringContainsString('Password minimal berisi 8 karakter', $html);
        $this->assertDatabaseMissing('users', ['username' => 'akun_baru_uji']);
    }

    public function test_penolakan_validasi_edit_terlihat_oleh_pengguna(): void
    {
        $idUser = (int) DB::table('users')->insertGetId([
            'id_karyawan' => $this->idKaryawan,
            'username' => 'akun_edit_uji',
            'password' => Hash::make('rahasia123'),
            'id_role' => $this->admin->id_role,
        ]);

        $r = $this->actingAs($this->admin)
            ->from('/users')
            ->followingRedirects()
            ->put('/users/update/' . $idUser, [
                'username' => 'akun_edit_uji',
                'id_role' => $this->admin->id_role,
                'password' => '123',
            ]);

        $this->assertStringContainsString('Periksa kembali isian', (string) $r->getContent());
        $this->assertTrue(Hash::check('rahasia123', DB::table('users')->where('id_user', $idUser)->value('password')));
    }

    public function test_edit_valid_tersimpan(): void
    {
        $idUser = (int) DB::table('users')->insertGetId([
            'id_karyawan' => $this->idKaryawan,
            'username' => 'akun_edit_uji2',
            'password' => Hash::make('rahasia123'),
            'id_role' => $this->admin->id_role,
        ]);
        $idRoleLain = (int) DB::table('roles')->insertGetId(['nama_role' => 'Kasir']);

        $this->actingAs($this->admin)->put('/users/update/' . $idUser, [
            'username' => 'akun_edit_uji2',
            'id_role' => $idRoleLain,
        ])->assertSessionHas('success');

        $this->assertSame($idRoleLain, (int) DB::table('users')->where('id_user', $idUser)->value('id_role'));
    }

    /* ---------- double submit ---------- */

    public function test_dua_post_berurutan_hanya_membuat_satu_akun(): void
    {
        $this->actingAs($this->admin)->post('/users/store', $this->payload());
        $this->actingAs($this->admin)->post('/users/store', $this->payload());

        $this->assertSame(1, DB::table('users')->where('username', 'akun_baru_uji')->count(),
            'Username unik - post kedua ditolak validasi, bukan insert kedua');
    }
}