<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah toggle "Wajib persetujuan Administrator untuk edit nota".
     *
     * Default 1 (aktif) supaya perilaku sebelumnya (semua edit nota harus
     * diajukan dulu lalu disetujui) TETAP berlaku kalau admin tidak
     * mengubahnya. Nilai 0 = operator boleh edit nota langsung tanpa
     * persetujuan (still dibatasi oleh guard creator/hari yang sama/SPK
     * selesai yang sudah ada).
     */
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->boolean('approval_edit_nota')->default(true)->after('batasi_jam_kerja');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn('approval_edit_nota');
        });
    }
};
