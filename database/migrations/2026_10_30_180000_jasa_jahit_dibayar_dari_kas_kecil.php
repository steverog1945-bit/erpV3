<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Upah jahit borongan dibayar dari Kas Kecil (pola sama dengan Komisi Desainer):
 *  - jasa_jahit_catat.id_kas_kecil / dibayar_at: kosong = belum dibayar ke penjahit.
 *  - Akun Beban 5220 dan kategori Kas Kecil "Upah Jahit Borongan".
 * Tanpa foreign key ke kas_kecil karena kas_kecil memakai soft delete; pembatalan kas kecil
 * mengosongkan kolom ini lewat KasKecilService::batalkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jasa_jahit_catat', function (Blueprint $t) {
            if (!Schema::hasColumn('jasa_jahit_catat', 'id_kas_kecil')) {
                $t->unsignedBigInteger('id_kas_kecil')->nullable()->after('id_user');
                $t->timestamp('dibayar_at')->nullable()->after('id_kas_kecil');
                $t->index(['id_penjahit', 'id_kas_kecil'], 'jasa_jahit_catat_penjahit_bayar_index');
                $t->index('id_kas_kecil');
            }
        });

        DB::table('bag_akun')->insertOrIgnore([
            'kode_akun' => '5220', 'nama_akun' => 'Beban Upah Jahit Borongan', 'tipe_akun' => 'Beban', 'saldo_normal' => 'Debet', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if (!DB::table('kategori_kas_kecil')->where('nama_kategori', 'Upah Jahit Borongan')->exists()) {
            DB::table('kategori_kas_kecil')->insert([
                'nama_kategori' => 'Upah Jahit Borongan', 'jenis' => 'Keluar', 'kode_akun' => '5220', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('jasa_jahit_catat', function (Blueprint $t) {
            if (Schema::hasColumn('jasa_jahit_catat', 'id_kas_kecil')) {
                $t->dropIndex('jasa_jahit_catat_penjahit_bayar_index');
                $t->dropIndex(['id_kas_kecil']);
                $t->dropColumn(['id_kas_kecil', 'dibayar_at']);
            }
        });
        DB::table('kategori_kas_kecil')->where('nama_kategori', 'Upah Jahit Borongan')->delete();
        DB::table('bag_akun')->where('kode_akun', '5220')->delete();
    }
};
