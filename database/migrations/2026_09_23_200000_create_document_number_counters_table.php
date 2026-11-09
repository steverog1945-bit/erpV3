<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Document Number Engine (roadmap LEVEL 1 - WAJIB). Satu tabel counter
// atomik dipakai bersama oleh SEMUA jenis dokumen (nota, order jersey,
// deposit, dst) - lihat App\Services\DocumentNumberService.
//
// (kode_dokumen, periode) SENGAJA jadi PRIMARY KEY komposit - TIDAK ada
// kolom id auto_increment terpisah. Ditemukan lewat pengujian: MariaDB
// memprioritaskan kolom auto_increment tabel itu sendiri di atas trik
// `LAST_INSERT_ID(expr)` untuk kolom lain dalam INSERT yang sama (baris
// baru selalu melaporkan nilai id asli, bukan nilai counter yang
// dimaksud) - komposit PK tanpa auto_increment menghindari konflik itu
// sepenuhnya karena tidak ada auto_increment yang bersaing sama sekali.
return new class extends Migration {
    public function up()
    {
        Schema::create('document_number_counters', function (Blueprint $table) {
            $table->string('kode_dokumen', 20); // 'INV', 'JO', dst
            $table->string('periode', 20); // hasil format tanggal, mis. '20260923'
            $table->unsignedInteger('counter')->default(0);
            $table->timestamps();

            $table->primary(['kode_dokumen', 'periode']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('document_number_counters');
    }
};
