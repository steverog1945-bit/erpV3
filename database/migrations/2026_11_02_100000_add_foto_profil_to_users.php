<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom foto_profil untuk tabel users.
 *
 * Controller UserController::uploadFoto()/hapusFoto() menyimpan nama file
 * foto profil pengguna ke kolom ini. Di env produksi kolom itu ternyata
 * belum ada (mestinya dibuat oleh migration awal users) sehingga unggah
 * foto profil gagal: SQLSTATE[42S22] Unknown column 'foto_profil'
 * (log production.ERROR 2026-11-02 12:30:56). Migration ini aditif.
 */
return new class extends Migration
{
    /**
     * Nama file foto di disk 'public' (folder profil/), boleh NULL —
     * NULL = tampilkan inisial.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'foto_profil')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('foto_profil')->nullable()->after('id_role');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'foto_profil')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('foto_profil');
        });
    }
};
