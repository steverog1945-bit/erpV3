<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Desainer (Operator Desain) pada order jersey + flag file siap cetak.
 *
 * Paritas dengan percetakan (transaksi_detail): bila file desain sudah siap cetak,
 * desainer/komisi tidak diperlukan. Role Operator Desain terkunci ke dirinya
 * sendiri; Administrator bebas memilih.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jersey_orders', function (Blueprint $t) {
            if (!Schema::hasColumn('jersey_orders', 'id_desainer')) {
                $t->unsignedBigInteger('id_desainer')->nullable()->after('jersey_designer_id');
                $t->foreign('id_desainer')->references('id_user')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('jersey_orders', 'file_siap_cetak')) {
                $t->boolean('file_siap_cetak')->default(false)->after('id_desainer');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jersey_orders', function (Blueprint $t) {
            if (Schema::hasColumn('jersey_orders', 'id_desainer')) {
                $t->dropForeign(['id_desainer']);
                $t->dropColumn('id_desainer');
            }
            if (Schema::hasColumn('jersey_orders', 'file_siap_cetak')) {
                $t->dropColumn('file_siap_cetak');
            }
        });
    }
};