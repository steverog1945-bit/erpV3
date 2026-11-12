<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Autentikasi API publik (API-5).
 *
 * - POST /api/auth/token - Verifikasi username/password, lalu TERBITKAN
 *   token Bearer baru (rotasi ATOMIK: token lama user dicabut pada
 *   commit yang sama). Body: { username, password }.
 *   Aset kembalian: `token` (plaintext, sekali saja - server hanya
 *   menyimpan SHA-256-nya di users.api_token_hash) dan `expires_at`
 *   (kedaluwarsa eksplisit, default 24 jam, config
 *   orion_api.token_masa_aktif_jam).
 * - POST /api/auth/logout - Cabut token aktif (hash + kedaluwarsa di
 *   hapus). Header: Authorization: Bearer <token>. Setelah logout token
 *   tidak bisa dipakai lagi.
 *
 * LIMIT: jalur /auth/token di-throttle api-token (5/menit per
 * username+IP, AppServiceProvider) sebagai rem kegagalan kredensial.
 *
 * Tabel users tidak punya kolom email/is_active - autentikasi lewat
 * username + password. Kolom lama users.api_token (plaintext) tidak lagi
 * berfungsi: kode ini tidak pernah mencocokkannya. Pemegang token era
 * sebelum API-5 WAJIB menerima token baru lewat endpoint ini pada
 * cutover yang disetujui owner (lihat docs/API_TOKEN_CUTOVER.md).
 */
class AuthController extends Controller
{
    public function __construct(private ApiTokenService $tokenService)
    {
    }

    public function generateToken(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            // min:8 tahan penuh tidak ditetapkan di sini supaya tidak
            // mengunci akun lama yang kebetulan pendek; password salah
            // tetap ditolak Hash::check dan jalur ini tak pernah bocor
            // pola panjang mana pun.
            'password' => 'required|string',
        ]);

        $user = User::where('username', $credentials['username'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json(['error' => 'Username atau password salah.'], 401);
        }

        // Akun nonaktif (karyawan resign) tidak boleh menerbitkan token
        // baru - menonaktifkan akun tidak boleh menyisakan pintu ke samping.
        if (!(int) ($user->is_active ?? 1)) {
            return response()->json(['error' => 'Akun tidak aktif. Hubungi Administrator.'], 401);
        }

        $hasil = $this->tokenService->terbitkan($user);

        return response()->json([
            'status'  => 'success',
            'token'   => $hasil['token'],
            'user'    => [
                'id'       => $user->id_user,
                'username' => $user->username,
                'role'     => optional($user->role)->nama_role,
            ],
            'expires_at' => $hasil['expires_at']->toISOString(),
        ]);
    }

    public function logout(Request $request)
    {
        $this->tokenService->cabut($request->user());

        return response()->json(['status' => 'success', 'message' => 'Token API dicabut.']);
    }
}
