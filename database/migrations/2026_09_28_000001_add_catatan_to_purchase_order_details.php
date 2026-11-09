<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// purchase_order_details.catatan - catatan per item PO (mis. "merk harus
// sama dengan PR", "harga max 50rb/m"), terbawa dari catatan item PR saat
// Buat PO. Header PO sudah punya catatan sendiri sejak awal.
//
// Idempoten: di sebagian environment kolom ini sempat terlanjur dibuat oleh
// versi pertama migrasi (sebelum rollback tgl_dibutuhkan), sehingga migrasi
// ulang gagal "Duplicate column name 'catatan'". Guard hasColumn membuat
// `php artisan migrate` aman dijalankan dari kondisi mana pun.
return new class extends Migration {
    public function up()
    {
        if (!Schema::hasColumn('purchase_order_details', 'catatan')) {
            Schema::table('purchase_order_details', function (Blueprint $table) {
                $table->string('catatan', 255)->nullable()->after('subtotal');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('purchase_order_details', 'catatan')) {
            Schema::table('purchase_order_details', function (Blueprint $table) {
                $table->dropColumn('catatan');
            });
        }
    }
};
