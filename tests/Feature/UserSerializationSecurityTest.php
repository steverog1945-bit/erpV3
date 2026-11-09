<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi keamanan SEC-USER-001 & SEC-USER-002.
 *
 * Yang dikunci di sini:
 *  - SEC-USER-001: serialisasi model User TIDAK PERNAH membocorkan
 *    kredensial (password bcrypt, remember_token, api_token, dan
 *    api_token_hash) ke bentuk JSON/HTML mana pun - tetapi id_user,
 *    username, dan id_role tetap tersedia untuk UI.
 *  - SEC-USER-002: username yang mengandung apostrof, kutip ganda, tag
 *    HTML, backslash, dan entitas TIDAK BISA memutus string JavaScript
 *    di dalam atribut onclick halaman Manajemen Pengguna - payload
 *    tiba sebagai DATA (di-encode \uXXXX), tidak pernah dieksekusi.
 *
 * Catatan render yang jujur: assertion HTTP berikut mengevaluasi blade
 * sungguhan via m HTTP feature client - jauh melebihi php -l yang hanya
 * mengecek sintaks. Hasil RENDER browser penuh ditindaklanjuti pada
 * tahap GATE 3 (test interaksi) sesuai perencanaan persetujuan owner.
 *
 * GAYA: mengikuti konvensi suite lain (RefreshDatabase, seed baris menu
 * tabla menyuntik sendiri karena RefreshDatabase hanya menjalankan
 * migrasi tanpa seeder).
 */
class UserSerializationSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** Halaman Manajemen Pengguna dipetakan 'users' => [6] (menu_access). */
    private function seedMenuUsers(): void
    {
        DB::table('menus')->insertOrIgnore([
            ['id_menu' => 6, 'nama_menu' => 'Manajemen User', 'url_link' => 'users', 'parent_id' => 0],
        ]);
    }

    /** User dengan role $namaRole + hak lihat menu 6 (halaman /users). */
    private function buatUserBerhak(string $namaRole, ?string $username = null): User
    {
        $this->seedMenuUsers();
        $idRole = $this->idRoleMurni($namaRole);
        DB::table('role_permissions')->insert([
            'id_role' => $idRole,
            'id_menu' => 6,
            'can_view' => true,
            'can_add' => false,
            'can_edit' => false,
            'can_delete' => false,
        ]);

        return $this->buatUserMurni(
            $username ?? ('pemegang-' . strtolower(str_replace(' ', '-', $namaRole)) . '-001'),
            $idRole
        );
    }

    /**
     * Insert role via DB murni (TANPA Role::factory): definisi RoleFactory
     * memanggil fake()->unique() dua kali dan tick-nya hidup lintas-test
     * dalam satu proses - pool元素nya terbatas sehingga OverflowException.
     */
    private function idRoleMurni(string $namaRole): int
    {
        DB::table('roles')->insert(['nama_role' => $namaRole]);

        return (int) DB::getPdo()->lastInsertId();
    }

    /**
     * User via factory dengan username + id_role EKSPLISIT supaya generator
     * unique() (username) maupun nested Role::factory() di definisi
     * UserFactory tidak pernah dievaluasi - keduanya sumber
     * OverflowException lintas-test dalam satu proses PHPUnit.
     */
    private function buatUserMurni(string $username, ?int $idRole = null): User
    {
        return User::factory()->create([
            'id_role'  => $idRole ?? $this->idRoleMurni('Role-' . $username),
            'username' => $username,
        ]);
    }

    /* ------------------------------------------------------------- */
    /* SEC-USER-001                                                   */
    /* ------------------------------------------------------------- */

    public function test_serialisasi_user_tidak_membocorkan_kredensial(): void
    {
        $user = $this->buatUserMurni('u-serial-001');
        $tokenRecall = 'recall-token-rahasia-untuk pengujian';
        $user->forceFill(['remember_token' => $tokenRecall])->save();

        $fresh = $user->fresh();
        $json = json_encode($fresh);
        $array = $fresh->toArray();

        // password: tidak boleh ada bentuk kata 'password' maupun hash bcrypt
        self::assertStringNotContainsString('$2y$', $json);
        self::assertStringNotContainsString($user->getAuthPassword() ?? '', $json); // nilai hash-nya sendiri
        self::assertArrayNotHasKey('password', $array);
        self::assertArrayNotHasKey('password', json_decode($json, true));

        // remember_token
        self::assertStringNotContainsString($tokenRecall, $json);
        self::assertArrayNotHasKey('remember_token', $array);

        // kredensial API (API-5): hash token TIDAK boleh tampil. (Kolom
        // api_token_expires_at sengaja TIDAK dites sekretaris - itu
        // timestamp, bukan kredensial, dan tiada di scope patch.)
        $jsonDecode = json_decode($json, true);
        self::assertArrayNotHasKey('api_token', $jsonDecode);
        self::assertArrayNotHasKey('api_token_hash', $jsonDecode);

        // field UI tetap tersedia
        self::assertSame($user->id_user, $jsonDecode['id_user']);
        self::assertSame($user->username, $jsonDecode['username']);
        self::assertSame((int) $user->id_role, (int) $jsonDecode['id_role']);
    }

    public function test_halaman_users_tanpa_hash_password_dan_remember_token(): void
    {
        $me = $this->buatUserBerhak('Administrator Kelola User');
        $korban = $this->buatUserMurni('korban-dashboard', $me->id_role);
        $korban->forceFill(['remember_token' => 'recall-bsi-akan-dicuri'])->save();

        $res = $this->actingAs($me)->get('/users');
        $res->assertOk();
        $html = (string) $res->getContent();

        // hash bcrypt TIDAK menempel di HTML (sebelum patch: json_encode($u) membocorkannya)
        self::assertStringNotContainsString('$2y$', $html);
        // hash password user korban tidak boleh tampil apa adanya
        self::assertStringNotContainsString($korban->getAuthPassword(), $html);
        // remember_token tidak muncul
        self::assertStringNotContainsString('recall-bsi-akan-dicuri', $html);
        self::assertStringNotContainsString('remember_token', $html);
    }

    /* ------------------------------------------------------------- */
    /* SEC-USER-002 (payload tidak dieksekusi sebagai kode)            */
    /* ------------------------------------------------------------- */

    /** @return array<int, string> */
    private function payloadBerbahaya(): array
    {
        return [
            "'x'); alert('pwn'); t('",            // memutus string JS single-quoted
            'x"onmouseover="alert(1)',            // mengincar atribut double-quoted
            '<img src=x onerror=alert(1)>',       // tag/atribut HTML
            '</script><script>alert(1)</script>', // pemutus konteks script
            'A&B"C\\D',                            // entitas & backslash kutip
        ];
    }

    public function test_username_payload_tak_eksekusi_dan_tak_muncul_mentah(): void
    {
        $me = $this->buatUserBerhak('Administrator Kelola User');
        foreach ($this->payloadBerbahaya() as $i => $p) {
            $terakhir = $this->buatUserMurni('korban' . $i . hash('crc32', $p), $me->id_role);
            $terakhir->username = $p;
            $terakhir->save();
        }

        $res = $this->actingAs($me)->get('/users');
        $res->assertOk();
        $html = (string) $res->getContent();

        // BENTUK MENTAH payload tidak boleh ada di mana pun pada halaman -
        // di atribut JS kini memakai \uXXXX, pada teks tampilan memakai
        // entitas HTML. Versi mentah otomatis = parameter lolos encoding.
        self::assertStringNotContainsString("'); alert('pwn'); t('", $html);
        self::assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        self::assertStringNotContainsString('</script><script>alert(1)</script>', $html);
        self::assertStringNotContainsString('A&B"C\\D', $html);

        // bentuk encoding \uXXXX HARUS ada - walau tindak lanjut browser
        // penuhnya adalah uji interaksi pada GATE 3, tanda encoding ini
        // membuktikan payload memasuki halaman sebagai DATA, bukan kode.
        self::assertNotFalse(stripos($html, '\u0027'));      // apostrof ter-encode
        self::assertNotFalse(stripos($html, '\u003c'));      // '<' ter-encode (JSON_HEX_TAG)
        self::assertNotFalse(stripos($html, '\u003e'));      // '>' ter-encode
        self::assertNotFalse(stripos($html, '\u0026'));      // '&' ter-encode
    }

    public function test_id_delete_selalu_integer_tanpa_kutip_dalam_js(): void
    {
        $me = $this->buatUserBerhak('Administrator Kelola User');
        for ($i = 1; $i <= 3; $i++) {
            $this->buatUserMurni('calon-hapus-' . $i, $me->id_role);
        }

        $html = (string) $this->actingAs($me)->get('/users')->getContent();

        // pemanggilan menetap id tanpa tanda kutip: confirmDeleteUser(7, ...)
        self::assertStringNotContainsString("confirmDeleteUser('", $html);
        // dan minimal memuat pola (int-setelah-penkapan) yang tepat
        self::assertMatchesRegularExpression('/confirmDeleteUser\(\d+,/', $html);
    }

    public function test_kontrak_data_modal_edit_hanya_tiga_field(): void
    {
        $me = $this->buatUserBerhak('Administrator Kelola User');
        $this->buatUserMurni('aktor-modal', $me->id_role);

        $html = (string) $this->actingAs($me)->get('/users')->getContent();

        // kontrak openEditUser: id_user / username / id_role tetap ada
        self::assertStringContainsString('id_user', $html);
        self::assertStringContainsString('username', $html);
        self::assertStringContainsString('id_role', $html);

        // SEC-USER-001: full-model tidak lagi tersentak ke atribut JS -
        // canary: kolom users 'id_kelas_desainer' tidak pernah ditampilkan
        // di halaman ini (dahulu ikut dalam json_encode($u) penuh).
        self::assertStringNotContainsString('id_kelas_desainer', $html);
    }

    /* ------------------------------------------------------------- */
    /* RBAC halaman /users                                            */
    /* ------------------------------------------------------------- */

    public function test_role_tanpa_hak_menu6_ditolak_dan_administrator_dibuka(): void
    {
        // role tanpa permission apa pun -> /users = 403 (CheckMenuAccess/Izin::bolehPath)
        $me = $this->buatUserBerhak('Staf Tanpa Hak Ke User');
        DB::table('role_permissions')->where('id_role', $me->id_role)->delete();
        $this->actingAs($me)->get('/users')->assertForbidden();

        // Administrator selalu lolos (bypass middleware)
        User::query()->where('id_user', $me->id_user)->delete();
        $admin = $this->buatUserMurni('admin-bypass-001', $this->idRoleMurni('Administrator'));
        $this->actingAs($admin)->get('/users')->assertOk();
    }
}
