<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use Carbon\Carbon;

/**
 * Accounting Period (roadmap LEVEL 1 - WAJIB). Mengunci periode (tahun+bulan)
 * yang sudah "ditutup" supaya AccountingService::postJournal() menolak
 * posting baru bertanggal periode itu - mencegah transaksi bulan lalu
 * diubah/ditambah sembarangan setelah laporan keuangan bulan itu difinalkan.
 *
 * TIDAK ADA BARIS di tabel accounting_periods berarti periode itu masih
 * Open secara default (lihat isClosed()) - baris hanya dibuat/diupdate saat
 * periode benar-benar ditutup atau dibuka kembali, supaya admin tidak perlu
 * pra-membuat baris untuk setiap bulan yang mungkin ada.
 */
class AccountingPeriodService
{
    public static function isClosed(string $tanggal): bool
    {
        $tgl = Carbon::parse($tanggal);

        return AccountingPeriod::where('tahun', $tgl->year)
            ->where('bulan', $tgl->month)
            ->where('status', 'Closed')
            ->exists();
    }

    public static function tutup(int $tahun, int $bulan, int $userId, ?string $catatan = null): AccountingPeriod
    {
        return AccountingPeriod::updateOrCreate(
            ['tahun' => $tahun, 'bulan' => $bulan],
            ['status' => 'Closed', 'closed_by' => $userId, 'closed_at' => now(), 'catatan' => $catatan]
        );
    }

    public static function buka(int $tahun, int $bulan): ?AccountingPeriod
    {
        $periode = AccountingPeriod::where('tahun', $tahun)->where('bulan', $bulan)->first();

        if (!$periode) {
            return null;
        }

        $periode->update(['status' => 'Open', 'closed_by' => null, 'closed_at' => null]);

        return $periode;
    }
}
