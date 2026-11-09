<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Rollback fitur "Dibutuhkan Tanggal" Purchase Request (permintaan user:
// cukup satu field tanggal). Drop dibuat idempoten karena kolom ini baru
// ditambah di commit fb2b904 dan kondisi migrate di tiap environment bisa
// berbeda (ada yang sudah migrasi, ada yang belum).
return new class extends Migration {
    public function up()
    {
        if (Schema::hasColumn('purchase_requests', 'tgl_dibutuhkan')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->dropColumn('tgl_dibutuhkan');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasColumn('purchase_requests', 'tgl_dibutuhkan')) {
            Schema::table('purchase_requests', function (Blueprint $table) {
                $table->date('tgl_dibutuhkan')->nullable()->after('tanggal');
            });
        }
    }
};
