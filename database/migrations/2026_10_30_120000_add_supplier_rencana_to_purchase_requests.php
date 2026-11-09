<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan Pembelian bisa menyebut supplier yang direncanakan (opsional). Dipakai di daftar PR supaya
 * penyetuju tahu rencananya tanpa membuka dokumen, dan sebagai isian awal saat PR dijadikan Pesanan Pembelian.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_requests') || Schema::hasColumn('purchase_requests', 'id_supplier_rencana')) {
            return;
        }
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('id_supplier_rencana')->nullable()->after('catatan');
            $table->foreign('id_supplier_rencana')->references('id_supplier')->on('supplier')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_requests') && Schema::hasColumn('purchase_requests', 'id_supplier_rencana')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->dropForeign(['id_supplier_rencana']);
                $table->dropColumn('id_supplier_rencana');
            });
        }
    }
};
