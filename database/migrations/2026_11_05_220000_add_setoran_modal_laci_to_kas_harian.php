<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kas_harian', function (Blueprint $table) {
            if (!Schema::hasColumn('kas_harian', 'jumlah_disetor')) {
                $table->decimal('jumlah_disetor', 15, 2)->default(0)->after('status_rolling');
            }
            if (!Schema::hasColumn('kas_harian', 'sisa_modal_laci')) {
                $table->decimal('sisa_modal_laci', 15, 2)->default(0)->after('jumlah_disetor');
            }
            if (!Schema::hasColumn('kas_harian', 'akun_tujuan')) {
                $table->string('akun_tujuan', 10)->nullable()->after('sisa_modal_laci');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kas_harian', function (Blueprint $table) {
            foreach (['jumlah_disetor', 'sisa_modal_laci', 'akun_tujuan'] as $col) {
                if (Schema::hasColumn('kas_harian', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
