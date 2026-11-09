<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * StatusPembayaran - SATU-SATUNYA tempat aturan "nota ini sudah lunas atau belum"
 * ditulis.
 *
 * Latar belakang (bug nyata, bukan hypothetis): `transaksi.status_pembayaran`
 * adalah kolom DENORMALISASI - hasil salinan dari hitungan total
 * `pembayaran_riwayat`. Kolom itu dihitung di KasirController::prosesBayar(),
 * tapi tidak ada penjaga yang memaksa kolom itu ikut berubah kalau
 * `pembayaran_riwayat` bertambah lewat jalur lain (koreksi, impor, void, atau
 * nota dibayar 0 lalu total nota disesuaikan). Akibatnya kolom itu bisa
 * KEBALIKAN dari kenyataan, dan layar menampilkan dua cerita yang bertentangan
 * di satu halaman yang sama: badge "Lunas" di satu baris, tapi di bawahnya
 * "Belum Bayar" - atau sebaliknya. Kasir lalu tidak tahu mana yang benar,
 * dan biasanya kasir akan percaya label yang paling berwarna.
 *
 * Aturan di sini sengaja dibuat sederhana dan eksplisit, karena dipakai di tiga
 * tempat yang tidak boleh berbeda pendapat satu sama lain:
 *
 *   1. PHP  - Transaksi::display_status_pembayaran (accessor model)
 *   2. SQL  - KasirController::index() (Monitoring Kasir) dan kelola_kas()
 *   3. CLI  - artisan kas:selaraskan-status (memperbaiki baris yang rusak)
 *
 * Kalau aturannya berubah, ketiganya ikut berubah, dan tidak akan ada lagi
 * tempat keempat yang diam-diam punya versinya sendiri.
 */
class StatusPembayaran
{
    public const BELUM_BAYAR = 'Belum Bayar';
    public const DP          = 'DP';
    public const LUNAS       = 'Lunas';

    /**
     * Urutan resmi status. Dipakai StatusTransitionGuard (yang menentukan
     * boleh/tidaknya pembalikan status) supaya keduanya memakai satu daftar
     * yang sama.
     */
    public const URUTAN = [self::BELUM_BAYAR, self::DP, self::LUNAS];

    /**
     * Aturan aslinya, dalam PHP.
     *
     * @param  float  $totalBayar    tagihan nota (transaksi.total_bayar)
     * @param  float  $totalTerbayar total seluruh baris pembayaran_riwayat nota ini
     */
    public static function dari(float $totalBayar, float $totalTerbayar): string
    {
        // Ubah harga/diskon bisa membuat total nota 0. Nominal 0 tidak boleh
        // dilacak sebagai "belum bayar" - tidak ada yang perlu ditagih.
        if ($totalBayar <= 0) {
            return self::LUNAS;
        }

        // Toleransi 0.004 rupiah: pembayaran dan total nota sama-sama DECIMAL,
        // sehingga penjumlahan yang secara ekonomi "pas" bisa tersisa pecahan
        // sen yang lebih kecil. Tanpa toleransi ini, nota yang lunas bisa
        // terlihat "DP" hanya karena pembulatan floating point.
        if ($totalTerbayar + 0.004 >= $totalBayar) {
            return self::LUNAS;
        }

        return $totalTerbayar > 0 ? self::DP : self::BELUM_BAYAR;
    }

    /**
     * Aturan yang sama persis, dalam SQL.
     *
     * Dipakai halaman yang query-nya memang sudah SQL murni dan tidak boleh
     * dibongkar menjadi Eloquent: Monitoring Kasir sengaja memakai DB::table
     * + JOIN dalam satu query supaya bebas N+1 (lihat QueryCountTest).
     *
     * @param  string  $kolomTotalBayar    kolom atau nama-alias untuk total tagihan
     * @param  string  $kolomTotalTerbayar kolom atau nama-alias untuk total terbayar
     * @return string fragmen "CASE ... END", tanpa "as nama_kolom"
     */
    public static function sqlCase(string $kolomTotalBayar, string $kolomTotalTerbayar): string
    {
        $tagihan  = trim($kolomTotalBayar);
        $terbayar = trim($kolomTotalTerbayar);

        return "CASE"
            . " WHEN {$tagihan} <= 0 THEN '" . self::LUNAS . "'"
            . " WHEN {$terbayar} + 0.004 >= {$tagihan} THEN '" . self::LUNAS . "'"
            . " WHEN {$terbayar} > 0 THEN '" . self::DP . "'"
            . " ELSE '" . self::BELUM_BAYAR . "'"
            . " END";
    }

    /**
     * Subquery untuk total terbayar - dipakai halaman yang butuh sekaligus
     * total terbayar dan status turunannya dalam satu query. Dipisah dari
     * sqlCase() supaya nama alias-nya jelas, bukan menebak kolom apa yang
     * kebetulan ada di query pemanggil.
     */
    public static function subqueryTotalTerbayar(): string
    {
        return '(SELECT SUM(p.nominal) FROM pembayaran_riwayat p'
            . ' WHERE p.id_transaksi = transaksi.id_transaksi)';
    }

    /**
     * Cari baris transaksi yang status_pembayaran-nya tidak sama dengan
     * kenyataan di pembayaran_riwayat. Return-nya hanya untuk ditelusuri dan
     * diuji - tidak mengubah apa pun.
     */
    public static function cariYangSelaras()
    {
        return DB::table('transaksi')
            ->select('transaksi.id_transaksi', 'transaksi.nomor_nota', 'transaksi.total_bayar', 'transaksi.status_pembayaran')
            ->selectRaw('COALESCE(' . self::subqueryTotalTerbayar() . ', 0) AS total_terbayar_real')
            ->selectRaw(self::sqlCase('transaksi.total_bayar', 'COALESCE(' . self::subqueryTotalTerbayar() . ', 0)') . ' AS status_sebenarnya')
            ->get()
            ->filter(function ($t) {
                return self::normalisasi($t->status_pembayaran) !== self::normalisasi($t->status_sebenarnya);
            })
            ->values();
    }

    /**
     * TULIS BENAR-BENARNYA status_pembayaran supaya cocok dengan pembayaran
     * yang tercatat. Dipakai artisan kas:selaraskan-status.
     *
     * Catatan: StatusTransitionGuard sengaja menolak pembalikan status
     * (Lunas -> Belum Bayar) karena di alur normal itu berarti ada bug lain.
     * Di sini justru itu yang DIPERBAIKI - baris yang tersesat dikembalikan
     * ke kenyataan, dan setiap perubahan dicetak supaya bisa ditelusuri.
     *
     * @return array{diperbaiki:int, rincian:array<int,array>}
     */
    public static function selaraskan(): array
    {
        $selaras    = self::cariYangSelaras();
        $diperbaiki = 0;
        $rincian    = [];

        foreach ($selaras as $t) {
            $sebelum = (string) $t->status_pembayaran;
            $sesudah = (string) $t->status_sebenarnya;

            DB::table('transaksi')
                ->where('id_transaksi', $t->id_transaksi)
                ->update(['status_pembayaran' => $sesudah]);

            $diperbaiki++;
            $rincian[] = [
                'id_transaksi'   => (int) $t->id_transaksi,
                'nomor_nota'     => (string) $t->nomor_nota,
                'total_bayar'    => (float) $t->total_bayar,
                'total_terbayar' => (float) $t->total_terbayar_real,
                'dari'           => $sebelum,
                'ke'             => $sesudah,
            ];
        }

        return ['diperbaiki' => $diperbaiki, 'rincian' => $rincian];
    }

    /**
     * Samakan cara membandingkan: database bisa menyimpan "lunas", "Lunas",
     * atau " LUNAS ". Untuk pertanyaan "sudah cocok atau belum", semua itu
     * dianggap sama.
     */
    public static function normalisasi(?string $status): string
    {
        return mb_strtolower(trim((string) $status));
    }
}