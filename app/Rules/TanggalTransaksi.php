<?php

namespace App\Rules;

use App\Services\AccountingPeriodService;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Tanggal dokumen yang menyentuh uang atau stok: tidak boleh di masa depan dan tidak boleh jatuh di
 * periode akuntansi yang sudah ditutup.
 *
 * Latar: jurnal selalu bertanggal hari ini, sedangkan tanggal dokumen diisi pengguna. Tanpa aturan ini
 * dokumen bisa dimundurkan ke bulan yang sudah ditutup (laporan operasional bulan itu berubah setelah
 * tutup buku, sementara jurnalnya masuk bulan berjalan) atau diberi tanggal tahun depan.
 */
class TanggalTransaksi implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $tgl = Carbon::parse((string) $value);
        } catch (\Throwable $e) {
            return; // format salah sudah ditangani aturan `date`
        }

        if ($tgl->copy()->startOfDay()->gt(now()->endOfDay())) {
            $fail('Tanggal tidak boleh melewati hari ini.');

            return;
        }

        if (AccountingPeriodService::isClosed($tgl->toDateString())) {
            $fail('Periode ' . $tgl->locale('id')->translatedFormat('F Y') . ' sudah ditutup. Pakai tanggal di periode yang masih terbuka, atau minta Administrator membuka periodenya.');
        }
    }
}
