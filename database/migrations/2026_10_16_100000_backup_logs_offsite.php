<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hardening P2-2 (audit): backup sebelumnya HANYA tersimpan di disk yang
 * sama dengan aplikasi & database (storage/app/backups/) - kalau disk
 * server rusak/hilang, backup ikut hilang bersamanya, padahal syarat
 * deploy yang sudah dicatat sendiri di proyek ini adalah "backup ke luar
 * server". Kolom ini mencatat status salinan kedua (ke disk terpisah,
 * mis. S3-compatible via config('backup.secondary_disk')) TERPISAH dari
 * status backup lokal utama - kegagalan menyalin ke lokasi kedua tidak
 * boleh membuat backup lokal yang sudah berhasil ikut ditandai gagal,
 * tapi tetap harus terlihat & bisa memicu notifikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_logs', function (Blueprint $table) {
            $table->string('offsite_status')->nullable()->after('status'); // null = tidak dikonfigurasi, 'Sukses'/'Gagal'
            $table->text('offsite_error')->nullable()->after('pesan_error');
        });
    }

    public function down(): void
    {
        Schema::table('backup_logs', function (Blueprint $table) {
            $table->dropColumn(['offsite_status', 'offsite_error']);
        });
    }
};
