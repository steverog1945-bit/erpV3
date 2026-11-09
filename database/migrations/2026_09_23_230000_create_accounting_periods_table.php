<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Accounting Period (roadmap LEVEL 1 - WAJIB). Satu baris dibuat hanya
// saat sebuah periode (tahun+bulan) PERTAMA KALI ditutup - tidak ada
// baris berarti periode itu masih Open secara default (lihat
// App\Services\AccountingPeriodService::isClosed()). Baris yang sudah
// ada dipakai ulang (update status) saat dibuka lagi, bukan dihapus -
// supaya riwayat buka/tutup tetap lengkap lewat Audit Trail.
return new class extends Migration {
    public function up()
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan'); // 1-12
            $table->enum('status', ['Open', 'Closed'])->default('Open');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('catatan', 255)->nullable();
            $table->timestamps();

            $table->unique(['tahun', 'bulan']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('accounting_periods');
    }
};
