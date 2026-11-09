<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Angka untuk Dashboard Eksekutif, Profitabilitas, dan Analisis Pemakaian
 * Bahan (LEVEL 4). Murni baca dari data yang sudah ada - tidak ada tabel
 * ringkasan terpisah, jadi angka selalu sama dengan sumbernya.
 *
 * Definisi (dinyatakan juga di layar supaya tidak disalahartikan):
 *  - Omzet = total nota aktif (bukan Dibatalkan) menurut tanggal order.
 *  - Biaya aktual = HPP material saat order + rework + waste yang diterapkan
 *    pada periode. Tenaga kerja, mesin, overhead, dan beban operasional
 *    (kas kecil dst) TIDAK termasuk, jadi "laba kotor" di sini bukan laba
 *    bersih.
 *  - Saldo Kas/Bank/Piutang/Hutang diambil dari buku besar (jurnal_umum).
 */
class DashboardEksekutifService
{
    public static function ringkasan(Carbon $dari, Carbon $sampai): array
    {
        $tgl = [$dari->toDateString(), $sampai->toDateString()];

        $omzet = (float) DB::table('transaksi')->where('status_nota', '!=', 'Dibatalkan')->whereTanggalAntara('tgl_order', $tgl)->sum('total_bayar');

        $hpp = (float) DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->where('t.status_nota', '!=', 'Dibatalkan')->whereTanggalAntara('t.tgl_order', $tgl)
            ->sum('d.hpp_snapshot');

        $rework = (float) DB::table('rework')->where('status', 'Selesai')->whereTanggalAntara('selesai_at', $tgl)->sum('biaya_material');
        $waste = (float) DB::table('waste_log')->where('status', 'Diterapkan')->whereBetween('tanggal', $tgl)->sum('biaya');
        $aktual = round($hpp + $rework + $waste, 2);
        $laba = round($omzet - $aktual, 2);

        $jersey = DB::table('jersey_orders')->whereBetween('order_date', $tgl);

        return [
            'omzet' => $omzet,
            'hpp' => $hpp,
            'rework' => $rework,
            'waste' => $waste,
            'biaya_aktual' => $aktual,
            'laba_kotor' => $laba,
            'margin' => $omzet > 0 ? round($laba / $omzet * 100, 1) : 0.0,
            'jumlah_nota' => DB::table('transaksi')->where('status_nota', '!=', 'Dibatalkan')->whereTanggalAntara('tgl_order', $tgl)->count(),
            'omzet_jersey' => (float) (clone $jersey)->sum('total'),
            'jumlah_jersey' => (clone $jersey)->count(),
            'saldo_kas' => self::saldo('1000'),
            'saldo_bank' => self::saldo('1010'),
            'piutang' => self::saldo('1100'),
            'hutang' => -self::saldo('2000'),
        ];
    }

    /** Saldo akun dari buku besar: debet - kredit. */
    private static function saldo(string $kodeAkun): float
    {
        return round((float) DB::table('jurnal_umum')->where('kode_akun', $kodeAkun)->sum(DB::raw('debet - kredit')), 2);
    }

    /** Omzet nota per bulan untuk $jumlahBulan bulan terakhir (termasuk bulan berjalan), urut lama -> baru. */
    public static function trenOmzet(int $jumlahBulan = 6): array
    {
        $awal = now()->startOfMonth()->subMonths($jumlahBulan - 1);

        $data = DB::table('transaksi')
            ->where('status_nota', '!=', 'Dibatalkan')
            ->where('tgl_order', '>=', $awal)
            ->selectRaw("DATE_FORMAT(tgl_order, '%Y-%m') as bulan, SUM(total_bayar) as omzet")
            ->groupBy(DB::raw("DATE_FORMAT(tgl_order, '%Y-%m')"))
            ->pluck('omzet', 'bulan');

        $hasil = [];
        for ($i = 0; $i < $jumlahBulan; $i++) {
            $b = $awal->copy()->addMonths($i);
            $hasil[] = ['bulan' => $b->format('Y-m'), 'label' => $b->translatedFormat('M Y'), 'omzet' => (float) ($data[$b->format('Y-m')] ?? 0)];
        }

        return $hasil;
    }

    public static function topPelanggan(Carbon $dari, Carbon $sampai, int $limit = 5)
    {
        return DB::table('transaksi as t')
            ->leftJoin('konsumen as k', 'k.id_konsumen', '=', 't.id_konsumen')
            ->where('t.status_nota', '!=', 'Dibatalkan')
            ->whereTanggalAntara('t.tgl_order', [$dari->toDateString(), $sampai->toDateString()])
            ->groupBy('t.id_konsumen', 'k.nama_konsumen')
            ->selectRaw("COALESCE(k.nama_konsumen, 'Tanpa pelanggan') as nama, COUNT(*) as jumlah_nota, SUM(t.total_bayar) as omzet")
            ->orderByDesc('omzet')->limit($limit)->get();
    }

    public static function topProduk(Carbon $dari, Carbon $sampai, int $limit = 5)
    {
        return DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->leftJoin('produk as p', 'p.id_produk', '=', 'd.id_produk')
            ->leftJoin('jasa as j', 'j.id_jasa', '=', 'd.id_jasa')
            ->where('t.status_nota', '!=', 'Dibatalkan')
            ->whereTanggalAntara('t.tgl_order', [$dari->toDateString(), $sampai->toDateString()])
            ->groupBy(DB::raw("COALESCE(p.nama_produk, j.nama_jasa, d.nama_file)"))
            ->selectRaw("COALESCE(p.nama_produk, j.nama_jasa, d.nama_file) as nama, SUM(d.qty) as qty, SUM(" . \App\Support\OmzetBersih::sql() . ") as omzet")
            ->orderByDesc('omzet')->limit($limit)->get();
    }

    /** Hal-hal yang butuh perhatian owner/admin. Tiap item: label, jumlah, tautan. */
    public static function peringatan(): array
    {
        $item = fn (string $label, int $jumlah, string $rute) => ['label' => $label, 'jumlah' => $jumlah, 'url' => route($rute)];

        return array_values(array_filter([
            $item('Pengajuan menunggu persetujuan', \App\Models\ApprovalRequest::where('status', 'Menunggu')->count(), 'approval.index'),
            $item('Bahan di bawah stok minimal', DB::table('bahan_baku')->where('status', 'aktif')->whereColumn('stok', '<', 'stok_minimal')->where('stok_minimal', '>', 0)->count(), 'laporan.stok-bahan'),
            $item('PO melewati estimasi kedatangan', DB::table('purchase_orders')->whereIn('status', ['Dipesan', 'Diterima Sebagian'])->where('tgl_pengiriman_estimasi', '<', now()->toDateString())->count(), 'purchase-order.index'),
            $item('Faktur hutang lewat jatuh tempo', DB::table('pembelian_bahan')->whereNull('deleted_at')->where('status_pembayaran', 'Hutang')->where('jatuh_tempo_tgl', '<', now()->toDateString())->count(), 'laporan.hutang'),
            $item('Nota belum lunas lebih dari 7 hari', DB::table('transaksi')->where('status_nota', 'Aktif')->where('status_pembayaran', '!=', 'Lunas')->where('tgl_order', '<', now()->subDays(7))->count(), 'laporan.piutang'),
            $item('Rework menunggu diproses', DB::table('rework')->where('status', 'Dibuka')->count(), 'qc.index'),
        ], fn ($p) => $p['jumlah'] > 0));
    }

    /**
     * Estimasi sederhana bulan depan = rata-rata 3 bulan penuh terakhir.
     * BUKAN prediksi statistik - hanya acuan kasar; ditandai jelas di layar.
     */
    public static function estimasiBulanDepan(): array
    {
        $awal = now()->startOfMonth()->subMonths(3);
        $akhir = now()->startOfMonth();

        $omzet = (float) DB::table('transaksi')->where('status_nota', '!=', 'Dibatalkan')->where('tgl_order', '>=', $awal)->where('tgl_order', '<', $akhir)->sum('total_bayar');
        $belanja = (float) DB::table('pembelian_bahan')->whereNull('deleted_at')->where('tgl_transaksi', '>=', $awal->toDateString())->where('tgl_transaksi', '<', $akhir->toDateString())->sum('grand_total');
        // Uang masuk ke kas usaha: pembayaran nota (bukan dari saldo deposit) + setoran deposit baru.
        $kasMasuk = (float) DB::table('pembayaran_riwayat')->where('metode', '!=', 'Deposit')->where('tgl_bayar', '>=', $awal)->where('tgl_bayar', '<', $akhir)->sum('nominal')
            + (float) DB::table('konsumen_deposit')->where('tipe', 'Setoran')->whereIn('metode_bayar', ['Tunai', 'Transfer'])
                ->where('tgl_deposit', '>=', $awal->toDateString())->where('tgl_deposit', '<', $akhir->toDateString())->sum('nominal');
        // Uang keluar dari kas usaha: biaya Kas Kecil. Pembayaran hutang supplier dan biaya "Dibayar Pemilik" memakai
        // uang pemilik (Dana Pemilik 3100/3200), bukan kas usaha. Hutang lama yang dibayar dari Kas Kecil sudah
        // tercatat sebagai baris Kas Kecil.
        $kasKeluar = (float) DB::table('kas_kecil')->whereNull('deleted_at')->where('jenis', 'Keluar')->where('metode', '!=', 'Dibayar Pemilik')
            ->where('tanggal', '>=', $awal->toDateString())->where('tanggal', '<', $akhir->toDateString())->sum('nominal');

        return [
            'omzet' => round($omzet / 3, 2),
            'belanja_bahan' => round($belanja / 3, 2),
            'kas_masuk' => round($kasMasuk / 3, 2),
            'kas_keluar' => round($kasKeluar / 3, 2),
            'arus_kas_bersih' => round(($kasMasuk - $kasKeluar) / 3, 2),
            'periode' => $awal->translatedFormat('M Y') . ' - ' . $akhir->copy()->subMonth()->translatedFormat('M Y'),
        ];
    }

    /** Profitabilitas per dimensi: produk | pelanggan | mesin. Baris: nama, qty/nota, omzet, hpp, laba, margin. */
    public static function profitabilitas(string $dimensi, Carbon $dari, Carbon $sampai)
    {
        $q = DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->leftJoin('produk as p', 'p.id_produk', '=', 'd.id_produk')
            ->leftJoin('jasa as j', 'j.id_jasa', '=', 'd.id_jasa')
            ->leftJoin('mesin as m', 'm.id_mesin', '=', 'p.id_mesin')
            ->leftJoin('konsumen as k', 'k.id_konsumen', '=', 't.id_konsumen')
            ->where('t.status_nota', '!=', 'Dibatalkan')
            ->whereTanggalAntara('t.tgl_order', [$dari->toDateString(), $sampai->toDateString()]);

        $ekspresi = match ($dimensi) {
            'pelanggan' => "COALESCE(k.nama_konsumen, 'Tanpa pelanggan')",
            'mesin' => "COALESCE(m.nama_mesin, 'Jasa / lainnya')",
            default => "COALESCE(p.nama_produk, j.nama_jasa, d.nama_file)",
        };

        return $q->groupBy(DB::raw($ekspresi))
            ->selectRaw("{$ekspresi} as nama, COUNT(DISTINCT t.id_transaksi) as jumlah_nota, SUM(d.qty) as qty, SUM(" . \App\Support\OmzetBersih::sql() . ") as omzet, SUM(d.hpp_snapshot) as hpp")
            ->orderByDesc('omzet')->get()
            ->map(function ($r) {
                $r->laba = round((float) $r->omzet - (float) $r->hpp, 2);
                $r->margin = (float) $r->omzet > 0 ? round($r->laba / (float) $r->omzet * 100, 1) : 0.0;

                return $r;
            });
    }

    /** Pemakaian bahan dari kartu stok (OUT), diklasifikasi menurut prefix referensi: RW- rework, WS- waste, sisanya pemakaian order/lain. */
    public static function pemakaianBahan(Carbon $dari, Carbon $sampai)
    {
        return DB::table('kartu_stok as ks')
            ->join('bahan_baku as b', 'b.id_bahan', '=', 'ks.id_bahan')
            ->where('ks.tipe', 'OUT')
            ->whereTanggalAntara('ks.tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->groupBy('b.id_bahan', 'b.nama_bahan', 'b.satuan_stok', 'b.harga_rata_rata')
            ->selectRaw("b.nama_bahan, b.satuan_stok, b.harga_rata_rata,
                SUM(CASE WHEN ks.referensi LIKE 'RW-%' THEN ks.qty ELSE 0 END) as qty_rework,
                SUM(CASE WHEN ks.referensi LIKE 'WS-%' THEN ks.qty ELSE 0 END) as qty_waste,
                SUM(CASE WHEN ks.referensi LIKE 'RW-%' OR ks.referensi LIKE 'WS-%' THEN 0 ELSE ks.qty END) as qty_pemakaian,
                SUM(ks.qty) as qty_total")
            ->orderByDesc('qty_total')->get()
            ->map(function ($r) {
                $r->persen_susut = (float) $r->qty_total > 0 ? round(((float) $r->qty_rework + (float) $r->qty_waste) / (float) $r->qty_total * 100, 1) : 0.0;
                $r->nilai_susut = round(((float) $r->qty_rework + (float) $r->qty_waste) * (float) $r->harga_rata_rata, 2);

                return $r;
            });
    }
}
