<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyambungkan Kas Kecil ke akuntansi - sebelumnya kategori_kas_kecil
 * cuma daftar nama tanpa kaitan ke Chart of Accounts sama sekali, jadi
 * pencatatan Kas Kecil TIDAK PERNAH masuk ke jurnal_umum/Laporan Laba
 * Rugi (lihat KasKecilController::store() versi lama). kode_akun
 * (nullable, BUKAN foreign key keras - kolom kode_akun di bag_akun
 * sudah unique tapi tidak semua kategori WAJIB langsung punya akun
 * saat baru dibuat) menentukan akun Beban mana yang di-debit tiap kali
 * kategori itu dipakai mencatat pengeluaran.
 *
 * kas_kecil masih 0 baris riil (dicek langsung sebelum migrasi ini
 * dibuat) - 5 kategori generik lama (ATK/Konsumsi/Transport/Listrik &
 * Utilitas/Lain-lain) AMAN diganti total dengan 11 kategori baru yang
 * lebih spesifik & masing-masing sudah terhubung akun Beban terkait
 * (migrasi add_beban_usaha_accounts sebelumnya), tanpa perlu backfill
 * data existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('kategori_kas_kecil', 'kode_akun')) {
            Schema::table('kategori_kas_kecil', function (Blueprint $table) {
                $table->string('kode_akun', 20)->nullable()->after('nama_kategori');
            });
        }

        if (DB::table('kas_kecil')->count() === 0) {
            DB::table('kas_kecil')->delete();
            DB::table('kategori_kas_kecil')->delete();

            DB::table('kategori_kas_kecil')->insert([
                ['nama_kategori' => 'Beban Administrasi & Umum', 'kode_akun' => '5110', 'created_at' => now(), 'updated_at' => now()],
                ['nama_kategori' => 'Beban BPJS', 'kode_akun' => '5120', 'created_at' => now(), 'updated_at' => now()],
                ['nama_kategori' => 'Beban Internet', 'kode_akun' => '5130', 'created_at' => now(), 'updated_at' => now()],
                ['nama_kategori' => 'Beban Listrik & Telepon', 'kode_akun' => '5140', 'created_at' => now(), 'updated_at' => now()],
                ['nama_kategori' => 'Beban PDAM', 'kode_akun' => '5150', 'created_at' => now(), 'updated_at' => now()],
                ['nama_kategori' => 'Beban Perlengkapan Kantor', 'kode_akun' => '5160', 'created_at' => now(), 'updated_at' => now()],
                ['nama_kategori' => 'Beban Perlengkapan Toko', 'kode_akun' => '5170', 'created_at' => now(), 'updated_at' => now()],
                ['nama_kategori' => 'Biaya Marketing', 'kode_akun' => '5180', 'created_at' => now(), 'updated_at' => now()],
                ['nama_kategori' => 'Biaya Ongkos Kirim & Ekspedisi', 'kode_akun' => '5190', 'created_at' => now(), 'updated_at' => now()],
                ['nama_kategori' => 'Biaya Operasional Kantor', 'kode_akun' => '5200', 'created_at' => now(), 'updated_at' => now()],
                ['nama_kategori' => 'Biaya Lain-lain', 'kode_akun' => '5100', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kategori_kas_kecil', 'kode_akun')) {
            Schema::table('kategori_kas_kecil', function (Blueprint $table) {
                $table->dropColumn('kode_akun');
            });
        }
    }
};
