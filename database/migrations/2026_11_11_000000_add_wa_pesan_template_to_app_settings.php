<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template pesan WhatsApp untuk fitur premium (nota & piutang).
 * Diisi operator sendiri dari halaman /premium/piutang-wa - dengan
 * placeholder {nama} {nota} {tanggal} {total} {status} {wa}.
 * NULL = gunakan bawaan aplikasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('app_settings', 'wa_pesan_template')) {
                $table->text('wa_pesan_template')->nullable()->after('nota_footer_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            if (Schema::hasColumn('app_settings', 'wa_pesan_template')) {
                $table->dropColumn('wa_pesan_template');
            }
        });
    }
};
