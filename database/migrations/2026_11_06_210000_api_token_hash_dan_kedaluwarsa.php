<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API-5: hashing & kedaluwarsa token REST API (ADDITIVE - cutover token).
 *
 * Yang DITAMBAHKAN ke tabel users:
 *  - api_token_hash       : SHA-256 hex (64 karakter, UNIQUE) dari token
 *                           Bearer. Token plaintext TIDAK PERNAH disimpan.
 *  - api_token_expires_at : kedaluwarsa token; NULL diperlakukan token
 *                           tidak sah (fail-closed) oleh ApiTokenService.
 *
 * Yang SENGAJA TIDAK disentuh:
 *  - Kolom lama users.api_token (plaintext) TIDAK dihapus dan TIDAK
 *    di-backfill ke hash. Pemegang token lama wajib menerima token baru
 *    via POST /api/auth/token pada cutover yang disetujui owner; token
 *    lama otomatis tidak sah karena kode baru hanya mencocokkan hash.
 *  - Penghapusan kolom api_token adalah tahap pembersihan TERPISAH
 *    setelah cutover selesai di semua klien.
 *
 * CATATAN PENAMAAN: file ini disengaja berurutan SETELAH migrasi
 * 2026_11_04_110000_tambah_api_token_ke_users.php supaya posisi
 * ->after('api_token') valid saat migrasi dijalankan pada database baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // UNIQUE: lookup autentikasi satu pass saja, dan menjamin satu
            // hash tidak pernah dipakai dua user (collision - identik)
            // jadi tak mungkin token cocok ke salah satu dari dua baris.
            $table->char('api_token_hash', 64)->nullable()->after('api_token')->unique();
            $table->timestamp('api_token_expires_at')->nullable()->after('api_token_hash');
        });
    }

    public function down(): void
    {
        // Rollback aman: cukup membuang kolom baru. Kolom api_token lama
        // tidak pernah diubah oleh migrasi ini, jadi kembali ke kode lama
        // (plaintext) tidak kehilangan token yang ada.
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['api_token_hash']);
            $table->dropColumn(['api_token_hash', 'api_token_expires_at']);
        });
    }
};
