<?php

namespace Tests\Support;

use App\Models\User;
use App\Services\ApiTokenService;

/**
 * Helper penerbitan token untuk pengujian API - MEMAKAI jalur produksi
 * yang sama (ApiTokenService::terbitkan) supaya persis menghasilkan baris
 * api_token_hash + api_token_expires_at yang dicocokkan middleware, bukan
 * menanam token jalan pintas yang tak akan pernah valid di produksi.
 *
 * Parameter $jam boleh negatif untuk membuat token yang sudah kedaluwarsa.
 */
final class TokenApiPengujian
{
    /** @return array{user: User, token: string, expires_at: string} */
    public static function terbitkan(User $user, int $jam = 24): array
    {
        config()->set('orion_api.token_masa_aktif_jam', $jam);

        try {
            $hasil = app(ApiTokenService::class)->terbitkan($user);
        } finally {
            config()->set('orion_api.token_masa_aktif_jam', 24);
        }

        return [
            'user'       => $user,
            'token'      => $hasil['token'],
            'expires_at' => $hasil['expires_at']->toISOString(),
        ];
    }

    /** Tepat yang sering dipakai test: token siap Bearer. */
    public static function token(User $user, int $jam = 24): string
    {
        return self::terbitkan($user, $jam)['token'];
    }

    /** Header Bearer standar. */
    public static function header(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
    }
}
