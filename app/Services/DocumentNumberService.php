<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Document Number Engine (roadmap LEVEL 1 - WAJIB). Satu sumber
 * penomoran dokumen terpusat & ATOMIK - sebelumnya tiap controller
 * menghitung nomor berikutnya sendiri-sendiri dengan pola
 * `COUNT(*) + 1` yang dihitung di halaman form (GET, sebelum submit),
 * lalu dipercaya begitu saja dari input client saat disimpan (POST).
 * Ini rawan race condition NYATA: dua order dibuat nyaris bersamaan bisa
 * sama-sama melihat "nomor berikutnya" yang sama sebelum salah satu
 * commit (lihat PenjualanController sebelum diperbaiki).
 *
 * generate() ATOMIK di level database (bukan read-then-write PHP) lewat
 * `INSERT ... ON DUPLICATE KEY UPDATE counter = LAST_INSERT_ID(counter+1)`
 * - idiom standar MySQL/MariaDB untuk counter atomik satu round-trip,
 * aman dipanggil concurrent tanpa perlu lockForUpdate()/transaction
 * terpisah dari pemanggil.
 */
class DocumentNumberService
{
    /**
     * @param  string  $kodeDokumen  Kode pendek, mis. 'INV', 'JO', 'DEP'
     * @param  string  $formatPeriode  Format tanggal PHP untuk reset counter, default harian ('Ymd')
     * @param  int  $panjangUrut  Jumlah digit nomor urut (padded dengan 0 di depan)
     * @return string  Mis. "INV-20260923-001"
     */
    public static function generate(string $kodeDokumen, string $formatPeriode = 'Ymd', int $panjangUrut = 3): string
    {
        $periode = now()->format($formatPeriode);

        // LAST_INSERT_ID(1) di klausa VALUES JUGA wajib ada (bukan cuma di
        // ON DUPLICATE KEY UPDATE) - tabel ini punya kolom `id` auto_increment
        // sendiri yang terpisah dari `counter`. Tanpa LAST_INSERT_ID(1) di
        // sini, baris PERTAMA untuk (kode_dokumen, periode) baru akan
        // membuat LAST_INSERT_ID() balik ke nilai `id` auto_increment asli
        // (bukan nilai counter=1 yang dimaksud) - ditemukan & dikonfirmasi
        // lewat pengujian langsung sebelum dipakai di controller manapun.
        DB::statement(
            'INSERT INTO document_number_counters (kode_dokumen, periode, counter, created_at, updated_at)
             VALUES (?, ?, LAST_INSERT_ID(1), ?, ?)
             ON DUPLICATE KEY UPDATE counter = LAST_INSERT_ID(counter + 1), updated_at = ?',
            [$kodeDokumen, $periode, now(), now(), now()]
        );

        $urut = (int) DB::selectOne('SELECT LAST_INSERT_ID() as urut')->urut;

        return $kodeDokumen . '-' . $periode . '-' . str_pad((string) $urut, $panjangUrut, '0', STR_PAD_LEFT);
    }
}
