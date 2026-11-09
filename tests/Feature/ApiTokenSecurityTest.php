<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\TokenApiPengujian;
use Tests\TestCase;

/**
 * Regresi keamanan API-5: token Bearer 256-bit dari CSPRNG, tersimpan hanya
 * sebagai SHA-256 (users.api_token_hash) dengan kedaluwarsa wajib, rotasi
 * atomik (token lama dicabut pada commit yang sama dengan token baru),
 * logout mencabut, jalur ?api_token= dibunuh total, dan token era plaintext
 * tidak pernah sah lagi.
 *
 * Semua test mengunci PERILAKU lewat jalur produksi (ApiTokenService, HTTP
 * /api/auth/token, /api/auth/logout) - bukan menanam nilai kolom jalan pintas.
 */
class ApiTokenSecurityTest extends TestCase
{
    use RefreshDatabase;

    /* ================================================================
     | Utilitas
     * ================================================================ */

    /** Aktor API: role di-insert sendiri + menu 3 (Transaksi Baru) view. */
    private function buatAktor(string $namaRole = 'Admin API5', bool $bolehTulis = false): array
    {
        $role = Role::factory()->create(['nama_role' => $namaRole]);
        DB::table('menus')->insertOrIgnore([
            ['id_menu' => 3, 'nama_menu' => 'Transaksi Baru', 'url_link' => 'penjualan', 'parent_id' => 0],
        ]);
        DB::table('role_permissions')->insertOrIgnore([
            [
                'id_role' => $role->id_role, 'id_menu' => 3,
                'can_view' => 1, 'can_add' => $bolehTulis ? 1 : 0,
                'can_edit' => 0, 'can_delete' => 0,
            ],
        ]);
        $user = User::factory()->create(['id_role' => $role->id_role]);

        return [$user, TokenApiPengujian::token($user)];
    }

    private function bukaApi(): void
    {
        config()->set('orion_api.enabled', true);
    }

    /** True bila salah satu nilai kolom audit_logs mengandung rahasia. */
    private function auditBocor(string ...$rahasia): bool
    {
        foreach (DB::table('audit_logs')->get() as $baris) {
            foreach ((array) $baris as $nilai) {
                if (!is_string($nilai)) {
                    continue;
                }
                foreach ($rahasia as $r) {
                    if ($r !== '' && str_contains($nilai, $r)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function login(string $username, string $password)
    {
        return $this->postJson('/api/auth/token', [
            'username' => $username,
            'password' => $password,
        ], ['Accept' => 'application/json']);
    }

    /* ================================================================
     | 1. Terbitan token: format, kedaluwarsa eksplisit, hash di DB
     * ================================================================ */

    public function test_login_sah_mengembalikan_token_format_ketat_kedaluwarsa_eksplisit(): void
    {
        $this->bukaApi();
        User::factory()->create(['username' => 'penerbit', 'password' => \Illuminate\Support\Facades\Hash::make('rahasia-benar')]);

        $r = $this->login('penerbit', 'rahasia-benar');
        $r->assertOk()->assertJsonPath('status', 'success');

        $token = $r->json('token');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token, 'Token baru harus tepat 64 hex (256-bit entropi).');
        $this->assertNotNull($r->json('expires_at'), 'kedaluwarsa WAJIB eksplisit, bukan null.');

        $menit = now()->diffInMinutes(Carbon::parse($r->json('expires_at')));
        $this->assertLessThanOrEqual(25 * 60, $menit);
        $this->assertGreaterThanOrEqual(23 * 60, $menit, 'default masa berlaku ~24 jam.');

        // Rotasi kedua menghasilkan token acak BERBEDA, bukan nil yang sama.
        $r2 = $this->login('penerbit', 'rahasia-benar');
        $r2->assertOk();
        $this->assertNotSame($token, $r2->json('token'));
    }

    public function test_plaintext_tidak_tersimpan_di_db_hanya_hash_nya(): void
    {
        $this->bukaApi();
        User::factory()->create(['username' => 'pemegang', 'password' => \Illuminate\Support\Facades\Hash::make('rahasia-benar')]);
        $token = $this->login('pemegang', 'rahasia-benar')->json('token');

        $baris = (array) DB::table('users')->where('username', 'pemegang')->first();

        $this->assertSame(hash('sha256', $token), $baris['api_token_hash'], 'kolom hash harus SHA-256 dari plaintext, bukan plaintext.');
        $this->assertNotSame($token, $baris['api_token_hash']);
        $this->assertNotNull($baris['api_token_expires_at']);

        // Tidak ada kolom users mana pun yang menyimpan plaintext token.
        foreach ($baris as $kolom => $nilai) {
            if (!is_string($nilai)) {
                continue;
            }
            $this->assertStringNotContainsString($token, $nilai, "kolom '{$kolom}' memuat plaintext token.");
        }
    }

    /* ================================================================
     | 2. Matikan jalur token lama (plaintext) dan jalur URL
     * ================================================================ */

    public function test_token_plaintext_era_lama_tidak_sah_namun_kolomnya_tak_dirusak(): void
    {
        $this->bukaApi();
        [$user, $tokenSah] = $this->buatAktor();

        // Keadaan "hanya deploy kode, belum apa-apa lagi": kolom lama masih
        // berisi token era sebelumnya; API-5 mematikan pencocokannya.
        $tokenLama = bin2hex(random_bytes(40));
        $user->forceFill(['api_token' => $tokenLama])->save();

        $this->getJson('/api/konsumen', TokenApiPengujian::header($tokenLama))->assertUnauthorized();
        $this->getJson('/api/konsumen', TokenApiPengujian::header($tokenSah))->assertOk();

        // Kolom api_token tidak dihapus/dibersihkan oleh API-5 (pembersihan
        // terpisah, persetujuan owner) - yang mati jalur pencocokannya.
        $nilai = DB::table('users')->where('id_user', $user->id_user)->value('api_token');
        $this->assertSame($tokenLama, $nilai, 'kolom api_token lama harus tetap utuh.');
    }

    public function test_param_query_api_token_ditolak_sekalipun_bearer_sah(): void
    {
        $this->bukaApi();
        [$user, $token] = $this->buatAktor();

        $this->getJson('/api/konsumen?api_token=' . $token, TokenApiPengujian::header($token))
            ->assertUnauthorized()
            ->assertJsonPath('error', 'Token API tidak boleh lewat URL. Kirim header Authorization: Bearer <token>.');

        // Parameternya hadir tapi kosong pun ditolak (hadir = salah jalur).
        $this->getJson('/api/konsumen?api_token=', TokenApiPengujian::header($token))
            ->assertUnauthorized();

        // Tanpa parameter itu sama sekali => jalan seperti normal.
        $this->getJson('/api/konsumen', TokenApiPengujian::header($token))->assertOk();
    }

    /* ================================================================
     | 3. Bearer hilang / salah format / salah nilai / kedaluwarsa
     * ================================================================ */

    public function test_bearer_hilang_salah_format_salah_nilai_semua_401(): void
    {
        $this->bukaApi();
        [$user, $token] = $this->buatAktor();

        // (a) tidak ada header sama sekali.
        $this->getJson('/api/konsumen')->assertUnauthorized();
        // (b) skema bukan Bearer.
        $this->getJson('/api/konsumen', ['Authorization' => 'Basic ' . $token])->assertUnauthorized();
        // (c) Bearer tanpa isi.
        $this->getJson('/api/konsumen', ['Authorization' => 'Bearer'])->assertUnauthorized();
        // (d) panjang salah: 63, 65, dan 80-hex era lama.
        $this->getJson('/api/konsumen', ['Authorization' => 'Bearer ' . substr($token, 0, 63)])->assertUnauthorized();
        $this->getJson('/api/konsumen', ['Authorization' => 'Bearer ' . ($token . 'a')])->assertUnauthorized();
        $this->getJson('/api/konsumen', ['Authorization' => 'Bearer ' . bin2hex(random_bytes(40))])->assertUnauthorized();
        // (e) karakter di luar hex.
        $this->getJson('/api/konsumen', ['Authorization' => 'Bearer ' . str_repeat('g', 64)])->assertUnauthorized();
        // (f) huruf besar: format token sengaja ketat lowercase.
        $this->getJson('/api/konsumen', ['Authorization' => 'Bearer ' . strtoupper($token)])->assertUnauthorized();
        // (g) spasi ganda setelah skema.
        $this->getJson('/api/konsumen', ['Authorization' => 'Bearer  ' . $token])->assertUnauthorized();
        // (h) format cocok tapi tidak pernah diterbitkan.
        $this->getJson('/api/konsumen', ['Authorization' => 'Bearer ' . str_repeat('a', 64)])
            ->assertUnauthorized()
            ->assertJsonPath('error', 'Token API tidak valid, kedaluwarsa, atau sudah dicabut.');

        // Token yang benar tetap sah setelah semua variasi gagal di atas.
        $this->getJson('/api/konsumen', TokenApiPengujian::header($token))->assertOk();
    }

    public function test_token_kedaluwarsa_ditolak(): void
    {
        $this->bukaApi();
        [$user, $tokenSah] = $this->buatAktor();

        $tokenMati = TokenApiPengujian::token($user, -5); // terbit "5 jam lalu"

        $this->getJson('/api/konsumen', TokenApiPengujian::header($tokenMati))
            ->assertUnauthorized()
            ->assertJsonPath('error', 'Token API tidak valid, kedaluwarsa, atau sudah dicabut.');
    }

    /* ================================================================
     | 4. Logout mencabut; login salah menerbitkan nol token
     * ================================================================ */

    public function test_logout_mencabut_token_dan_tak_bisa_dipakai_lagi(): void
    {
        $this->bukaApi();
        [$user, $token] = $this->buatAktor();

        $this->getJson('/api/konsumen', TokenApiPengujian::header($token))->assertOk();
        $this->postJson('/api/auth/logout', [], TokenApiPengujian::header($token))
            ->assertOk()
            ->assertJsonPath('status', 'success');

        // Token yang sama: pasti 401 (hash sudah dihilangkan).
        $this->getJson('/api/konsumen', TokenApiPengujian::header($token))->assertUnauthorized();

        $baris = (array) DB::table('users')->where('id_user', $user->id_user)->first();
        $this->assertNull($baris['api_token_hash']);
        $this->assertNull($baris['api_token_expires_at']);
    }

    public function test_login_salah_tidak_menerbitkan_token_apa_pun(): void
    {
        $this->bukaApi();
        User::factory()->create(['username' => 'kunci', 'password' => \Illuminate\Support\Facades\Hash::make('kunci-acak')]);

        $this->login('kunci', 'kunci-salah')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'Username atau password salah.');

        $this->assertSame(0, DB::table('users')->whereNotNull('api_token_hash')->count());
    }

    public function test_bruteforce_percobaan_keenam_kena_limiter_429(): void
    {
        $this->bukaApi();

        for ($i = 1; $i <= 5; $i++) {
            $this->login('bruteforce-api5', 'salah')->assertUnauthorized();
        }

        $this->login('bruteforce-api5', 'salah')
            ->assertStatus(429)
            ->assertJsonPath('message', 'Terlalu banyak percobaan token. Coba lagi dalam beberapa saat.');
    }

    /* ================================================================
     | 5. Rotasi: berulang; galat DB merollback (token lama tetap sah)
     * ================================================================ */

    public function test_rotasi_berulang_hanya_token_terakhir_sah(): void
    {
        $this->bukaApi();
        [$user, $tokenLama] = $this->buatAktor();

        // Tiga rotasi beruntun via jalur produksi (login ulang + terbit baru).
        $hasil = [];
        for ($ronde = 0; $ronde < 3; $ronde++) {
            $hasil[] = $this->login($user->username, 'password')->json('token');
        }
        $this->assertNotContains(null, $hasil, 'ketiga login harus sukses.');

        $this->assertNotSame($tokenLama, $hasil[0]);
        $this->assertNotSame($hasil[0], $hasil[1]);
        $this->assertNotSame($hasil[1], $hasil[2]);

        // HANYA token paling akhir yang masih dibaca sah.
        $this->getJson('/api/konsumen', TokenApiPengujian::header($tokenLama))->assertUnauthorized();
        $this->getJson('/api/konsumen', TokenApiPengujian::header($hasil[0]))->assertUnauthorized();
        $this->getJson('/api/konsumen', TokenApiPengujian::header($hasil[1]))->assertUnauthorized();
        $this->getJson('/api/konsumen', TokenApiPengujian::header($hasil[2]))->assertOk();

        $hash = DB::table('users')->where('id_user', $user->id_user)->value('api_token_hash');
        $this->assertSame(hash('sha256', $hasil[2]), $hash, 'slot hash tunggal harus milik token terakhir.');
    }

    public function test_galat_db_saat_rotasi_merollback_token_lama_tetap_sah(): void
    {
        $this->bukaApi();
        [$user, $tokenLama] = $this->buatAktor();
        $this->getJson('/api/konsumen', TokenApiPengujian::header($tokenLama))->assertOk();

        // Suntik kegagalan DI DALAM transaksi rotasi: setiap UPDATE users
        // ditembakkan sesudah eksekusi, lemparan di sini memotong aplikasi
        // sehingga DB::transaction() ApiTokenService memutar-balikan penuh.
        DB::listen(function ($query) {
            if (str_contains($query->sql, 'update `users`')) {
                throw new \RuntimeException('simulasi galat DB saat rotasi');
            }
        });

        $r = $this->login($user->username, 'password');
        $r->assertStatus(500);
        $r->assertJsonMissing(['token', 'status']);

        // Kondisi akhir di DB: hash belum pernah berganti - terbukti rollback,
        // TEBARAN "kedua token mati bersamaan" tidak pernah terjadi.
        $hash = DB::table('users')->where('id_user', $user->id_user)->value('api_token_hash');
        $this->assertSame(hash('sha256', $tokenLama), $hash, 'gagal rotasi harus merollback ke token lama.');
    }

    /* ================================================================
     | 6. RBAC & checkout gate tetap kokoh di atas token hash
     * ================================================================ */

    public function test_role_tanpa_hak_tetap_403_dengan_token_hash_sah(): void
    {
        $this->bukaApi();
        $role = Role::factory()->create(['nama_role' => 'Bebas Hak']);
        $user = User::factory()->create(['id_role' => $role->id_role]);
        $token = TokenApiPengujian::token($user);

        $this->getJson('/api/konsumen', TokenApiPengujian::header($token))->assertForbidden();
    }

    public function test_checkout_gate_503_pesan_persis_tetap_dengan_token_hash(): void
    {
        $this->bukaApi();
        // checkout.enabled default OFF. Role 'Administrator' dilewati ApiRbac
        // (pola ApiCheckoutGuardTest), lalu adapter checkout menutup sendiri.
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $user = User::factory()->create(['id_role' => $role->id_role]);
        $token = TokenApiPengujian::token($user);

        $this->postJson('/api/transaksi', [
            'id_konsumen' => 1,
            'items' => [['id_produk' => 1, 'qty_input' => 10]],
        ], TokenApiPengujian::header($token))
            ->assertStatus(503)
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonPath('message', 'Checkout API belum tersedia; gunakan alur ERP web.');
    }

    /* ================================================================
     | 7. Kredensial tidak menempel di audit_logs (plaintext/hash/password)
     * ================================================================ */

    public function test_token_dan_password_tidak_menempel_di_audit_logs(): void
    {
        $this->bukaApi();
        [$user, $token] = $this->buatAktor();

        // Perjalanan penuh: pakai, cabut, terbit baru, pakai lagi, cabut lagi.
        $this->getJson('/api/konsumen', TokenApiPengujian::header($token))->assertOk();
        $this->postJson('/api/auth/logout', [], TokenApiPengujian::header($token))->assertOk();

        $tokenBaru = TokenApiPengujian::token($user);
        $this->getJson('/api/konsumen', TokenApiPengujian::header($tokenBaru))->assertOk();
        $this->postJson('/api/auth/logout', [], TokenApiPengujian::header($tokenBaru))->assertOk();

        $passwordHash = (string) DB::table('users')->where('id_user', $user->id_user)->value('password');

        $bocor = $this->auditBocor($token, $tokenBaru, hash('sha256', $token), $passwordHash);
        $this->assertFalse($bocor, 'audit_logs tidak boleh memuat token maupun hash kredensial.');
    }
}
