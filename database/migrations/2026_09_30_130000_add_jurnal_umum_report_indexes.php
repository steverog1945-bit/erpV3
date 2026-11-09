<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks laporan buku besar (audit Fase 3, performa) - diputuskan dari
 * pengukuran EXPLAIN/waktu pada salinan dengan 600.000 baris jurnal:
 *
 *   query                                   sebelum   sesudah
 *   jurnal per bulan (whereBetween tgl)      7.449 ms     549 ms   (ALL -> range)
 *   saldo kumulatif per akun (tgl <= X)      3.906 ms     257 ms   (scan tabel -> scan indeks penutup)
 *   laba rugi bulan berjalan (join akun)       290 ms     172 ms
 *   saldo satu akun (kas kecil 1005, dst)    1.386 ms     102 ms   (ref+baca tabel -> indeks penutup)
 *
 * Indeks lama (kode_akun, tgl_jurnal) TIDAK bisa melayani filter tanggal
 * tanpa kode_akun, dan tidak menutup kolom debet/kredit. Dua indeks:
 *  - (tgl_jurnal): rentang tanggal lintas semua akun + urutan tanggal.
 *  - (kode_akun, tgl_jurnal, debet, kredit): agregat saldo per akun cukup
 *    dari indeks (covering), tanpa membaca baris tabel.
 * Indeks lama (kode_akun, tgl_jurnal) kini prefix dari indeks kedua - sengaja
 * TIDAK dihapus di sini (menghapus indeks bukan perbaikan mendesak dan
 * migrasi lama tidak diedit); catat sebagai kandidat pembersihan.
 * Idempoten. Hanya menambah indeks: tidak mengubah/menyentuh data.
 */
return new class extends Migration
{
    private const TGL = 'jurnal_umum_tgl_jurnal_index';
    private const SALDO = 'jurnal_umum_akun_tgl_debet_kredit_index';

    public function up(): void
    {
        if (!Schema::hasTable('jurnal_umum')) {
            return;
        }

        if (!Schema::hasIndex('jurnal_umum', self::TGL)) {
            Schema::table('jurnal_umum', fn ($t) => $t->index('tgl_jurnal', self::TGL));
        }
        if (!Schema::hasIndex('jurnal_umum', self::SALDO)) {
            Schema::table('jurnal_umum', fn ($t) => $t->index(['kode_akun', 'tgl_jurnal', 'debet', 'kredit'], self::SALDO));
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('jurnal_umum')) {
            return;
        }

        foreach ([self::TGL, self::SALDO] as $nama) {
            if (Schema::hasIndex('jurnal_umum', $nama)) {
                Schema::table('jurnal_umum', fn ($t) => $t->dropIndex($nama));
            }
        }
    }
};
