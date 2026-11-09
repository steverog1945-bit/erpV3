<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('satuan', function (Blueprint $table) {
        $table->string('tipe_satuan')->default('Barang')->after('nilai_konversi');
    });
}

public function down()
{
    Schema::table('satuan', function (Blueprint $table) {
        $table->dropColumn('tipe_satuan');
    });
}
};
