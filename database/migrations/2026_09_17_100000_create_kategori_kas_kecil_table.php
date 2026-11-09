<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori Kas Kecil sebelumnya isian bebas (kolom string kas_kecil.kategori)
 * dengan datalist autocomplete saja - berisiko variasi penulisan (mis. "ATK"
 * vs "Atk") membuat filter/laporan per kategori terpecah. Diganti jadi tabel
 * referensi tetap (mengikuti pola tabel `kategori` untuk bahan baku), dipilih
 * lewat dropdown.
 *
 * kas_kecil masih KOSONG (0 baris riil) saat migrasi ini dibuat, jadi kolom
 * `kategori` (string) aman diganti langsung jadi `id_kategori` (FK) tanpa
 * perlu backfill data - sudah dibackup ke folder backups/ sebagai jaga-jaga.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('kategori_kas_kecil')) {
            Schema::create('kategori_kas_kecil', function (Blueprint $table) {
                $table->id('id_kategori_kas_kecil');
                $table->string('nama_kategori', 100)->unique();
                $table->timestamps();
            });

            DB::table('kategori_kas_kecil')->insert([
                ['nama_kategori' => 'ATK', 'created_at' => now(), 'updated_at' => now()],
                ['nama_kategori' => 'Konsumsi', 'created_at' => now(), 'updated_at' => now()],
                ['nama_kategori' => 'Transport / Ongkos Kirim', 'created_at' => now(), 'updated_at' => now()],
                ['nama_kategori' => 'Listrik & Utilitas', 'created_at' => now(), 'updated_at' => now()],
                ['nama_kategori' => 'Lain-lain', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (Schema::hasColumn('kas_kecil', 'kategori') && !Schema::hasColumn('kas_kecil', 'id_kategori')) {
            Schema::table('kas_kecil', function (Blueprint $table) {
                $table->dropColumn('kategori');
            });
            Schema::table('kas_kecil', function (Blueprint $table) {
                $table->foreignId('id_kategori')->after('tanggal')->constrained('kategori_kas_kecil', 'id_kategori_kas_kecil');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kas_kecil', 'id_kategori')) {
            Schema::table('kas_kecil', function (Blueprint $table) {
                $table->dropForeign(['id_kategori']);
                $table->dropColumn('id_kategori');
            });
            Schema::table('kas_kecil', function (Blueprint $table) {
                $table->string('kategori', 100)->after('tanggal');
            });
        }

        Schema::dropIfExists('kategori_kas_kecil');
    }
};
