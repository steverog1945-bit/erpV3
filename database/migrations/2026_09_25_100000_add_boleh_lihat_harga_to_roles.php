<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hak "boleh melihat & mengubah harga/nilai uang" per role. Role tanpa hak ini
 * (Operator Desain dan Operator Produksi) hanya bekerja pada input nota dan
 * produksi: halaman/kolom yang memuat harga (Finishing Produk, Quotation,
 * biaya waste/QC, timeline nilai pelanggan, dst) ditutup atau disembunyikan.
 * Bisa diatur di Pengaturan Hak Akses.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('roles', 'boleh_lihat_harga')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->boolean('boleh_lihat_harga')->default(true);
            });
        }

        DB::table('roles')->whereIn('nama_role', ['Operator Desain', 'Operator Produksi'])->update(['boleh_lihat_harga' => false]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('roles', 'boleh_lihat_harga')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('boleh_lihat_harga');
            });
        }
    }
};
