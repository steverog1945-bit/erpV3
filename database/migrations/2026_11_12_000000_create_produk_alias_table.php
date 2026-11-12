<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alias istilah publik per produk - kamus istilah percetakan yang dipakai
 * asisten landing (chat konsumen) untuk menerjemahkan kata konsumen
 * ("banner", "spanduk", "baliho") ke produk nyata (mis. FLS280 = Frontlite
 * 280gr). SENGAJA tabel data, bukan hardcode di service: owner mengelola
 * isinya lewat Master Produk, jadi istilah baru tidak butuh perubahan kode.
 *
 * Aliasan bisa mengarah ke BEBERAPA produk sekaligus (mis. "banner" ke
 * FLS280 1.6/2.2/2.6/3.2m) - asisten akan menampilkan daftar varian dengan
 * harganya masing-masing.
 *
 * Data awal: alias umum untuk produk FLS280 (bahan Frontlite 280gr) supaya
 * asisten publik langsung berguna; daftar ini bisa diedit/ditambah kapan
 * saja dari Master Produk tanpa deploy ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('produk_alias')) {
            return;
        }

        Schema::create('produk_alias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_produk');
            $table->string('alias', 60);
            $table->timestamps();

            $table->foreign('id_produk')->references('id_produk')->on('produk')->cascadeOnDelete();
            $table->unique(['id_produk', 'alias']);
            $table->index('alias');
        });

        // Kamus awal (data, bukan logika): istilah pasaran untuk bahan
        // Frontlite 280gr. Owner bisa mengubah/menambah via Master Produk.
        $fls = DB::table('produk')->where('nama_produk', 'like', 'FLS280%')->pluck('id_produk');
        $alias = ['banner', 'spanduk', 'baliho', 'flexi', 'frontlite'];
        $rows = [];
        foreach ($fls as $id) {
            foreach ($alias as $a) {
                $rows[] = ['id_produk' => $id, 'alias' => $a, 'created_at' => now(), 'updated_at' => now()];
            }
        }
        if (!empty($rows)) {
            DB::table('produk_alias')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('produk_alias');
    }
};
