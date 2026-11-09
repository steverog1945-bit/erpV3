<?php

namespace App\Services;

use App\Models\AppSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat yang menentukan SESI KAS AKTIF.
 *
 * Aturan: kas kasir TETAP OPEN sampai di-closing - bukan otomatis standby
 * tiap pergantian tanggal. Sebelumnya semua modul mencari sesi dengan syarat
 * tgl_kas = hari ini, sehingga sesi yang lupa di-closing "hilang" saat lewat
 * tengah malam (status jadi Standby padahal masih Open, uangnya tak pernah
 * direkonsiliasi) dan pembayaran setelah tengah malam tidak ikut terhitung
 * saat closing. Sekarang:
 * - sesi aktif = sesi berstatus open, tanpa melihat tanggal (eksklusif: hanya
 *   satu sesi open pada satu waktu),
 * - rentang waktu sesi = sejak dibuka (created_at) sampai ditutup/sekarang,
 *   dipakai untuk merekap pembayaran & mutasi,
 * - sesi yang open lebih dari 24 jam ditandai "basi" (peringatan),
 * - hari Minggu libur memblokir TRANSAKSI baru, tetapi closing tetap boleh.
 */
class KasSesiService
{
    public const BATAS_JAM = 24;

    /** Sesi kas yang sedang open (siapa pun pemiliknya), atau null. */
    public static function aktif(): ?object
    {
        return DB::table('kas_harian')->where('status_lock', 'open')->orderByDesc('id_kas')->first();
    }

    /** Waktu sesi dimulai (created_at; untuk data lama tanpa created_at = awal tanggal kas). */
    public static function mulai(object $kas): Carbon
    {
        return $kas->created_at ? Carbon::parse($kas->created_at) : Carbon::parse($kas->tgl_kas)->startOfDay();
    }

    /** Batas akhir rentang rekap: waktu tutup bila sudah closing, kalau belum = sekarang. */
    public static function akhir(object $kas): Carbon
    {
        return !empty($kas->waktu_tutup) ? Carbon::parse($kas->waktu_tutup) : now();
    }

    /**
     * Uang tunai yang seharusnya ada di laci untuk sesi ini: saldo awal + pembayaran tunai selama sesi
     * + kas tidak terduga masuk - kas tidak terduga keluar. Rumus sama dengan Preview Closing.
     */
    /**
     * $terkunci = true dipakai PENJAGA saldo di dalam transaksi: hitungan dibaca sebagai locking read, yang selalu
     * melihat data terbaru yang sudah di-commit. Pembacaan biasa di MySQL (REPEATABLE READ) memakai potret yang
     * terbentuk pada bacaan biasa pertama di transaksi; bila potret itu terbentuk sebelum kunci didapat, dua
     * pengeluaran bersamaan sama-sama melihat saldo lama dan sama-sama lolos (saldo minus).
     */
    public static function saldoLaci(object $kas, bool $terkunci = false): float
    {
        $tunai = (float) DB::table('pembayaran_riwayat')
            ->where('tgl_bayar', '>=', self::mulai($kas))
            ->where('tgl_bayar', '<=', self::akhir($kas))
            ->where('metode', 'Tunai')
            ->when($terkunci, fn ($q) => $q->sharedLock())
            ->sum('nominal');
        $manual = DB::table('kas_manual')->where('id_kas', $kas->id_kas)->when($terkunci, fn ($q) => $q->sharedLock())
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'Masuk' THEN nominal ELSE 0 END), 0) masuk, COALESCE(SUM(CASE WHEN status = 'Keluar' THEN nominal ELSE 0 END), 0) keluar")
            ->first();

        return round((float) $kas->saldo_awal + $tunai + (float) $manual->masuk - (float) $manual->keluar, 2);
    }

    /**
     * Tolak pengambilan uang dari laci yang melebihi isinya. Wajib dipanggil di dalam transaksi: baris sesi kas
     * dikunci, jadi dua pengambilan bersamaan tidak sama-sama lolos.
     */
    public static function pastikanLaciCukup(object $kas, float $nominal): void
    {
        $kas = DB::table('kas_harian')->where('id_kas', $kas->id_kas)->lockForUpdate()->first();
        $saldo = self::saldoLaci($kas, true);
        if (round($nominal, 2) > $saldo + 0.01) {
            throw new \RuntimeException('Uang di laci menurut sistem Rp ' . number_format(max(0, $saldo), 0, ',', '.') . ', tidak cukup untuk mengeluarkan Rp ' . number_format($nominal, 0, ',', '.') . '.');
        }
    }

    public static function umurJam(object $kas): int
    {
        return (int) self::mulai($kas)->diffInHours(now());
    }

    public static function basi(?object $kas): bool
    {
        return $kas !== null && self::umurJam($kas) >= self::BATAS_JAM;
    }

    /** Alasan transaksi kas diblokir (mis. libur Minggu), atau null bila boleh. */
    public static function alasanTerblokir(): ?string
    {
        // Jam kerja (Pengaturan > Jam Kerja): hari tutup selalu memblokir transaksi baru; di luar jam buka
        // hanya memblokir bila "batasi transaksi di luar jam kerja" diaktifkan. Closing kas tetap boleh.
        $alasanJam = JamKerjaService::alasanTutup();
        if ($alasanJam) {
            return $alasanJam;
        }

        return null;
    }

    /** Sesi aktif yang boleh dipakai bertransaksi sekarang (null bila tidak ada atau diblokir libur). */
    public static function aktifUntukTransaksi(): ?object
    {
        return self::alasanTerblokir() ? null : self::aktif();
    }
}
