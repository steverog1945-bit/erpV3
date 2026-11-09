<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * API-5: satu-satunya jalur terbit/cabut/match token REST API.
 *
 * Keteguhan keamanan yang dikunci di sini:
 *  - Token dibuat lewat CSPRNG 32 byte (256-bit entropi) => plaintext
 *    64 karakter hex; hanya SHA-256 plaintext-nya yang disimpan kolom
 *    users.api_token_hash. Plaintext kembali ke klien SATU kali saja.
 *  - Rotasi = transaksi DB: token lama dicabut dan token baru ditulis
 *    dalam SATU commit; gagal DB => rollback => token lama tetap sah
 *    (tidak ada jendela "kedua token mati").
 *  - Kunci baris user (lockForUpdate) saat rotasi supaya dua rotasi
 *    bersamaan terserialisasi: hasil akhir pasti tepat satu token sah,
 *    bukan dua token jika terjadi race.
 *  - Token kadaluarsa / tanpa kedaluwarsa (NULL) / tanpa hash => tidak
 *    sah (fail-closed). Token plaintext era lama di users.api_token
 *    TIDAK pernah dicocokkan di sini - ia mati otomatis, dan cutover
 *    resminya adalah penerbitan token baru saat disetujui owner.
 *
 * Token & password tidak pernah ditulis ke log/audit; hash token juga
 * disembunyikan dari audit_logs lewat $hiddenFromAudit di model User.
 */
class ApiTokenService
{
    /** Entropi token: 32 byte = 256 bit (standar minimum keamanan). */
    public const BAJT_ENTROPI = 32;

    /** Panjang format plaintext yang diterima: 64 hex lowercase. */
    public const PANJANG_PLAINTEXT = self::BAJT_ENTROPI * 2;

    /**
     * Terbitkan token baru untuk user: rotasi atomik (cabut lama + isi
     * baru dalam satu transaksi). Nilai kembalinya memuat plaintext
     * sekali-sekali untuk respons dan kedaluwarsa eksplisit.
     *
     * @return array{token: string, expires_at: CarbonInterface}
     */
    public function terbitkan(User $user): array
    {
        $token = bin2hex(random_bytes(self::BAJT_ENTROPI));
        // Angka negatif sengaja diizinkan (dipakai test membuat token yang
        // sudah kedaluwarsa); owner yang memilih nilai konfigurasi sendiri.
        $masaJam = (int) config('orion_api.token_masa_aktif_jam', 24);
        $kedaluwarsa = now()->addHours($masaJam);

        DB::transaction(function () use ($user, $token, $kedaluwarsa) {
            // Kunci baris user supaya dua rotasi bersamaan tidak meng
            // hasilkan dua token sah: yang kedua menunggu, lalu menimpa
            // token yang baru saja ditulis rotasi pertama.
            User::where('id_user', $user->getKey())->lockForUpdate()->first();

            // Menulis hash baru SAMPAI dengan mengganti slot yang lama:
            // hanya ada satu hash per user, jadi token lama otomatis
            // dicabut pada commit yang sama di mana token baru sah.
            $user->forceFill([
                'api_token_hash'       => $this->hash($token),
                'api_token_expires_at' => $kedaluwarsa,
            ])->save();
        });

        return ['token' => $token, 'expires_at' => $kedaluwarsa];
    }

    /** Cabut token aktif user (logout): hapus hash + kedaluwarsa saja. */
    public function cabut(User $user): void
    {
        $user->forceFill([
            'api_token_hash'       => null,
            'api_token_expires_at' => null,
        ])->save();
    }

    /**
     * Cocokkan Bearer plaintext ke user; keputusan fail-closed:
     * format salah, hash tak dikenal, atau kedaluwarsa lewat/NULL => null.
     */
    public function cocokkan(?string $token): ?User
    {
        if (!$this->formatSah($token)) {
            return null;
        }

        $user = User::where('api_token_hash', $this->hash($token))
            ->whereNotNull('api_token_expires_at')
            ->where('api_token_expires_at', '>', now())
            ->first();

        return $user instanceof User ? $user : null;
    }

    /** Validasi format ketat: tepat 64 karakter hex lowercase. */
    public function formatSah(?string $token): bool
    {
        return is_string($token)
            && preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }

    /** Checksum SHA-256 token - identik pembanding di middleware. */
    public function hash(string $token): string
    {
        if ($token === '') {
            throw new RuntimeException('Token kosong tidak boleh di-hash.');
        }

        return hash('sha256', $token);
    }
}
