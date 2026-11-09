<?php

namespace App\Services;

use App\Models\KasKecil;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Pembayaran upah jahit borongan ke penjahit dari Kas Kecil. */
class UpahJahitService
{
    public const KATEGORI = 'Upah Jahit Borongan';

    /**
     * Bayar catatan jahit milik satu penjahit yang belum dibayar: satu pengeluaran Kas Kecil, jurnal
     * Dr Beban Upah Jahit / Cr Kas Kecil, lalu catatan ditandai sudah dibayar.
     *
     * @param  int[]  $idsCatat
     * @return array{total: float, jumlah: int, id_kas_kecil: int, nama: string}
     */
    public static function bayar(int $idPenjahit, array $idsCatat): array
    {
        return DB::transaction(function () use ($idPenjahit, $idsCatat) {
            $catat = DB::table('jasa_jahit_catat')
                ->where('id_penjahit', $idPenjahit)
                ->whereIn('id_jasa_jahit_catat', $idsCatat)
                ->whereNull('id_kas_kecil')
                ->lockForUpdate()
                ->get(['id_jasa_jahit_catat', 'total']);

            if ($catat->isEmpty()) {
                throw new \RuntimeException('Tidak ada catatan jahit yang belum dibayar untuk penjahit ini.');
            }
            if ($catat->count() !== count(array_unique($idsCatat))) {
                throw new \RuntimeException('Sebagian catatan yang dipilih sudah dibayar atau bukan milik penjahit ini. Muat ulang halaman lalu coba lagi.');
            }

            $total = round((float) $catat->sum('total'), 2);
            if ($total <= 0) {
                throw new \RuntimeException('Total upah yang dipilih Rp 0, tidak ada yang perlu dibayar.');
            }
            KasKecilService::pastikanSaldoCukup($total);

            $kategori = DB::table('kategori_kas_kecil')->where('nama_kategori', self::KATEGORI)->first();
            if (! $kategori || ! $kategori->kode_akun) {
                throw new \RuntimeException('Kategori Kas Kecil "' . self::KATEGORI . '" belum dipetakan ke akun Beban.');
            }
            $nama = (string) DB::table('penjahit')->where('id_penjahit', $idPenjahit)->value('nama');

            $kk = KasKecil::create([
                'tanggal' => now()->toDateString(), 'jenis' => 'Keluar', 'sumber' => 'manual', 'metode' => 'Tunai',
                'id_kategori' => $kategori->id_kategori_kas_kecil,
                'keterangan' => "Upah jahit borongan {$nama} ({$catat->count()} catatan)",
                'nominal' => $total, 'id_user' => Auth::id(),
            ]);
            AccountingService::postKasKecil('KK-' . $kk->id, $kategori->kode_akun, $total);

            DB::table('jasa_jahit_catat')->whereIn('id_jasa_jahit_catat', $catat->pluck('id_jasa_jahit_catat'))
                ->update(['id_kas_kecil' => $kk->id, 'dibayar_at' => now(), 'updated_at' => now()]);

            return ['total' => $total, 'jumlah' => $catat->count(), 'id_kas_kecil' => $kk->id, 'nama' => $nama];
        });
    }
}
