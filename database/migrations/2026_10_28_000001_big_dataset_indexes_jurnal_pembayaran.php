<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit kesiapan dataset besar (perf):
 *
 * - jurnal_umum SELAMA INI TANPA SATU INDEX PUN selain primary key, padahal
 *   setiap pembayaran memanggil AccountingService::referensiPembayaran()
 *   (COUNT ... WHERE no_referensi LIKE '{nota}-BYR-%'), setiap pembatalan/
 *   koreksi/waste/kas kecil memanggil reversePenjualan()/reverseByReferensi()
 *   (WHERE no_referensi = ? / LIKE '{nota}-%'), dan semua laporan akuntansi
 *   (Buku Besar: kode_akun + tgl_jurnal; Neraca Saldo/Laba Rugi: tgl_jurnal
 *   BETWEEN + GROUP BY kode_akun) menyaring tabel ini. Pada ratusan ribu
 *   baris, semuanya full scan. Dua index: (kode_akun, tgl_jurnal) untuk
 *   Buku Besar & agregat per akun, (no_referensi) untuk lookup/reversal/
 *   penomoran per dokumen (prefix-LIKE tetap bisa memakai index ini).
 *
 * - pembayaran_riwayat.tgl_bayar tanpa index, dipakai Dashboard (kas masuk
 *   hari ini/kemarin + tren 7 hari), KasirController::previewClosing
 *   (pembayaran antara mulai/akhir sesi), dan Laporan Kas: WHERE tgl_bayar
 *   pada rentang. Satu index tunggal cukup.
 *
 * Migration aditif murni (tidak mengubah/menghapus kolom manapun) dan
 * idempoten ala 2026_10_18_000002: cek dulu lewat SHOW INDEX sebelum
 * membuat, jadi aman dijalankan ulang / aman kalau index pernah dibuat
 * manual di database yang sudah jalan. MySQL-only (SHOW INDEX) - sama
 * dengan pendahulunya; aplikasi ini memang MySQL/MariaDB (XAMPP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jurnal_umum', function (Blueprint $table) {
            if (!$this->adaIndex('jurnal_umum', 'jurnal_umum_kode_akun_tgl_jurnal_index')) {
                $table->index(['kode_akun', 'tgl_jurnal'], 'jurnal_umum_kode_akun_tgl_jurnal_index');
            }
            if (!$this->adaIndex('jurnal_umum', 'jurnal_umum_no_referensi_index')) {
                $table->index('no_referensi', 'jurnal_umum_no_referensi_index');
            }
        });

        Schema::table('pembayaran_riwayat', function (Blueprint $table) {
            if (!$this->adaIndex('pembayaran_riwayat', 'pembayaran_riwayat_tgl_bayar_index')) {
                $table->index('tgl_bayar', 'pembayaran_riwayat_tgl_bayar_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jurnal_umum', function (Blueprint $table) {
            if ($this->adaIndex('jurnal_umum', 'jurnal_umum_kode_akun_tgl_jurnal_index')) {
                $table->dropIndex('jurnal_umum_kode_akun_tgl_jurnal_index');
            }
            if ($this->adaIndex('jurnal_umum', 'jurnal_umum_no_referensi_index')) {
                $table->dropIndex('jurnal_umum_no_referensi_index');
            }
        });

        Schema::table('pembayaran_riwayat', function (Blueprint $table) {
            if ($this->adaIndex('pembayaran_riwayat', 'pembayaran_riwayat_tgl_bayar_index')) {
                $table->dropIndex('pembayaran_riwayat_tgl_bayar_index');
            }
        });
    }

    private function adaIndex(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table}"))->pluck('Key_name')->contains($index);
    }
};
