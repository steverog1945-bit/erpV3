<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kolom `produk.id_mesin` ditambahkan manual di luar migration manapun
// (pola sama seperti tabel-tabel yang didokumentasikan di
// 2026_09_10_045900_create_finishing_table.php, kali ini kolom bukan
// tabel). Tipe INT nullable dipertahankan sesuai bentuk aslinya -
// 2026_09_23_160000_fix_inconsistent_id_column_types.php yang nanti
// menaikkannya ke BIGINT UNSIGNED (konsisten dengan konvensi PK di
// seluruh app).
return new class extends Migration {
    public function up()
    {
        if (!Schema::hasColumn('produk', 'id_mesin')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->integer('id_mesin')->nullable()->after('nama_produk');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('produk', 'id_mesin')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->dropColumn('id_mesin');
            });
        }
    }
};
