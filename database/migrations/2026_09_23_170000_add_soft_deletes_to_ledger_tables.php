<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit (Phase 2) menemukan nol pemakaian SoftDeletes di seluruh 49 model,
// termasuk tabel ledger keuangan - artinya ->delete() di controller
// (KasKecilController, PembelianBahanController, JerseyFinanceController::
// destroyPayment) selama ini HARD delete permanen tanpa jejak, padahal
// baris jurnal/pembayaran secara prinsip akuntansi seharusnya tidak pernah
// hilang total. Migration ini cuma menambah kolom deleted_at nullable -
// tidak mengubah/menghapus data apapun yang sudah ada.
return new class extends Migration {
    public function up()
    {
        Schema::table('jurnal_umum', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('kas_kecil', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('konsumen_deposit', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('pembelian_bahan', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('jersey_order_payments', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::table('jurnal_umum', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('kas_kecil', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('konsumen_deposit', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('pembelian_bahan', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::table('jersey_order_payments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
