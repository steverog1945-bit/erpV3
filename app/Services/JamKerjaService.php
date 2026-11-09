<?php

namespace App\Services;

use App\Models\AppSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Jam kerja per hari (tabel jam_kerja, diatur di Pengaturan > Jam Kerja).
 * Dipakai KasSesiService untuk memblokir transaksi baru pada hari libur / (opsional) di luar jam buka.
 */
class JamKerjaService
{
    public const HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    public static function semua()
    {
        return DB::table('jam_kerja')->orderBy('hari')->get()->keyBy('hari');
    }

    /** Alasan transaksi baru diblokir pada waktu $waktu (default sekarang), atau null bila boleh. */
    public static function alasanTutup(?Carbon $waktu = null): ?string
    {
        $waktu ??= now();
        $h = DB::table('jam_kerja')->where('hari', $waktu->dayOfWeekIso)->first();
        if (!$h) {
            return null; // belum dikonfigurasi = tidak membatasi
        }

        $nama = self::HARI[$waktu->dayOfWeekIso];
        if (!$h->buka) {
            return "Hari {$nama} toko libur - transaksi tidak bisa dicatat. Closing kas tetap bisa dilakukan; ubah di Pengaturan > Jam Kerja bila toko buka.";
        }

        if (AppSetting::current()->batasi_jam_kerja) {
            $jam = $waktu->format('H:i:s');
            if ($jam < $h->jam_mulai || $jam > $h->jam_selesai) {
                return sprintf('Di luar jam kerja (%s - %s). Transaksi baru hanya bisa dicatat pada jam kerja; ubah di Pengaturan > Jam Kerja.', substr($h->jam_mulai, 0, 5), substr($h->jam_selesai, 0, 5));
            }
        }

        return null;
    }
}
