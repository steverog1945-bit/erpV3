<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TokenApiPengujian;
use Tests\TestCase;

/**
 * Rate limiter API (temuan audit API-1 diperbaiki):
 *  - /api/auth/token digembok 5 percobaan/menit per username+IP, seperti
 *    /login web - brute-force password lewat jalur API berhenti di 429.
 *  - Seluruh endpoint API ber-token digembok 60/menit per klien.
 *  - Wajib diuji dengan token yang sah SAHAJA, bukan 401/503: sebelumnya
 *    throttle API dikomentari sehingga 7x401 berurutan tak pernah 429.
 *
 * RateLimiter memakai cache 'array' yang hidup hanya dalam satu aplikasi
 * test - tiap test mulai bersih, jadi hitungan limiter tidak bocor antar
 * test meski satu proses.
 */
class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_digembok_lima_percobaan_per_menit(): void
    {
        // Gate persiapan (503) melingkupi seluruh /api, termasuk /token;
        // limiter diuji ketika API dibuka.
        config()->set('orion_api.enabled', true);

        for ($i = 1; $i <= 5; $i++) {
            $resp = $this->postJson('/api/auth/token', [
                'username' => 'brute',
                'password' => 'salah-sekali',
            ], ['Accept' => 'application/json']);

            // 401 (kredensial salah), bukan 429 - limiter memberi jatah 5.
            $this->assertTrue(
                $resp->status() === 401,
                "Percobaan ke-$i harus 401 kredensial salah, dapat " . $resp->status()
            );
        }

        // Percobaan ke-6: limiter naik tangan.
        $this->postJson('/api/auth/token', [
            'username' => 'brute',
            'password' => 'salah-sekali',
        ], ['Accept' => 'application/json'])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Terlalu banyak percobaan token. Coba lagi dalam beberapa saat.');

        // Kunci per username+IP: username lain di IP yang sama tetap jalan.
        $this->postJson('/api/auth/token', [
            'username' => 'brute-lain',
            'password' => 'salah-sekali',
        ], ['Accept' => 'application/json'])->assertStatus(401);
    }

    public function test_token_sah_tak_tergantung_limiter_bruteforce_lain(): void
    {
        config()->set('orion_api.enabled', true);

        // Kemudahan: setelah 5x gagal username A, login/token username yang
        // betul tetap sukses (limiter tidak mengunci user sah).
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $user = User::factory()->create(['username' => 'owner-uji', 'password' => \Illuminate\Support\Facades\Hash::make('pw-benar-123')]);
        $user->forceFill(['id_role' => $role->id_role])->save();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/token', ['username' => 'brute', 'password' => 'x'], ['Accept' => 'application/json']);
        }

        $this->postJson('/api/auth/token', [
            'username' => 'owner-uji',
            'password' => 'pw-benar-123',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonMissing(['error']);
    }

    public function test_endpoint_api_digembok_enam_puluh_per_menit(): void
    {
        config()->set('orion_api.enabled', true);

        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $user = User::factory()->create(['id_role' => $role->id_role]);
        $token = TokenApiPengujian::token($user);

        $headerApi = ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];

        // Jatah 60/menit: 60 permintaan pertama lolos.
        for ($i = 1; $i <= 60; $i++) {
            $resp = $this->getJson('/api/konsumen', $headerApi);
            if ($resp->status() === 429) {
                // Jangan gagal diam: sebut percobaan ke berapa menaranya.
                $this->fail("Terkena 429 pada permintaan ke-$i (seharusnya lolos).");
            }
        }
        $this->getJson('/api/konsumen', $headerApi)
            ->assertStatus(429)
            ->assertJsonPath('message', 'Terlalu banyak permintaan API. Coba lagi sebentar.');
    }

    public function test_limiter_api_tak_aktif_saat_api_tutup(): void
    {
        // API tertutup: gate 503 jalan duluan - limiter tidak, dan RBAC
        // tidak, jadi tidak ada efek samping keduanya di keadaan produksi.
        $this->postJson('/api/auth/token', ['username' => 'x', 'password' => 'y'], ['Accept' => 'application/json'])
            ->assertStatus(503);
    }
}
