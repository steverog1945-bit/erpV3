<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Lapisan laporan/read-only di atas jurnal_umum yang ditulis AccountingService.
 * Tidak ada satupun method di sini yang menulis ke jurnal_umum - satu-satunya
 * jalur tulis tetap AccountingService::postJournal() (lihat catatan di sana).
 * Semua total di sini murni derivasi dari data yang sudah ada, bukan sumber
 * kebenaran baru.
 */
class AkuntansiController extends Controller
{
    public function jurnal(Request $request)
    {
        $awal = $request->get('awal', now()->startOfMonth()->toDateString());
        $akhir = $request->get('akhir', now()->toDateString());

        $rows = DB::table('jurnal_umum')
            ->join('bag_akun', 'bag_akun.kode_akun', '=', 'jurnal_umum.kode_akun')
            ->whereBetween('tgl_jurnal', [$awal, $akhir])
            ->orderBy('tgl_jurnal')
            ->orderBy('id_jurnal')
            ->select('jurnal_umum.*', 'bag_akun.nama_akun')
            ->get();

        $totalDebet = $rows->sum('debet');
        $totalKredit = $rows->sum('kredit');

        return view('akuntansi.jurnal', compact('rows', 'awal', 'akhir', 'totalDebet', 'totalKredit'));
    }

    public function bukuBesar(Request $request)
    {
        $daftarAkun = DB::table('bag_akun')->orderBy('kode_akun')->get();

        $kodeAkun = $request->get('kode_akun', optional($daftarAkun->first())->kode_akun);
        $awal = $request->get('awal', now()->startOfMonth()->toDateString());
        $akhir = $request->get('akhir', now()->toDateString());

        $akun = $daftarAkun->firstWhere('kode_akun', $kodeAkun);
        $rows = collect();
        $saldoAwal = 0.0;
        $saldoAkhir = 0.0;

        if ($akun) {
            $saldoSebelum = DB::table('jurnal_umum')
                ->where('kode_akun', $kodeAkun)
                ->where('tgl_jurnal', '<', $awal)
                ->selectRaw('COALESCE(SUM(debet),0) as total_debet, COALESCE(SUM(kredit),0) as total_kredit')
                ->first();

            $arahNormal = $akun->saldo_normal === 'Debet' ? 1 : -1;
            $saldoAwal = $arahNormal * (($saldoSebelum->total_debet ?? 0) - ($saldoSebelum->total_kredit ?? 0));

            $transaksi = DB::table('jurnal_umum')
                ->where('kode_akun', $kodeAkun)
                ->whereBetween('tgl_jurnal', [$awal, $akhir])
                ->orderBy('tgl_jurnal')
                ->orderBy('id_jurnal')
                ->get();

            $berjalan = $saldoAwal;
            $rows = $transaksi->map(function ($t) use (&$berjalan, $arahNormal) {
                $berjalan += $arahNormal * ($t->debet - $t->kredit);
                $t->saldo_berjalan = $berjalan;
                return $t;
            });

            $saldoAkhir = $berjalan;
        }

        return view('akuntansi.buku-besar', compact('daftarAkun', 'akun', 'kodeAkun', 'awal', 'akhir', 'rows', 'saldoAwal', 'saldoAkhir'));
    }

    public function neracaSaldo(Request $request)
    {
        $akhir = $request->get('akhir', now()->toDateString());

        $akunList = DB::table('bag_akun')->orderBy('kode_akun')->get();

        $saldoPerAkun = DB::table('jurnal_umum')
            ->where('tgl_jurnal', '<=', $akhir)
            ->selectRaw('kode_akun, SUM(debet) as total_debet, SUM(kredit) as total_kredit')
            ->groupBy('kode_akun')
            ->get()
            ->keyBy('kode_akun');

        $rows = $akunList->map(function ($akun) use ($saldoPerAkun) {
            $s = $saldoPerAkun->get($akun->kode_akun);
            $totalDebet = $s->total_debet ?? 0;
            $totalKredit = $s->total_kredit ?? 0;
            $net = $totalDebet - $totalKredit;

            $akun->kolom_debet = $net > 0 ? $net : 0;
            $akun->kolom_kredit = $net < 0 ? abs($net) : 0;

            // Bendera diagnostik: saldo akun ini terbalik dari saldo_normal
            // yang didefinisikan (mis. akun Aset malah bersaldo Kredit) -
            // bukan error, tapi layak diperiksa (piutang lunas lebih besar
            // dari nilainya, dsb).
            $akun->saldo_tidak_normal = $net != 0 && (
                ($akun->saldo_normal === 'Debet' && $net < 0) ||
                ($akun->saldo_normal === 'Kredit' && $net > 0)
            );

            return $akun;
        })->filter(fn($a) => $a->kolom_debet != 0 || $a->kolom_kredit != 0 || $a->is_active)->values();

        $totalDebet = $rows->sum('kolom_debet');
        $totalKredit = $rows->sum('kolom_kredit');

        return view('akuntansi.neraca-saldo', compact('rows', 'akhir', 'totalDebet', 'totalKredit'));
    }

    /**
     * Direstrukturisasi supaya memisahkan Laba Kotor (Penjualan - HPP)
     * SEBELUM Beban Usaha - sebelumnya HPP (kode 5000) tercampur rata
     * dalam satu daftar "Beban" bersama Beban Usaha lainnya, jadi laporan
     * tidak pernah menampilkan Laba Kotor sama sekali (langsung loncat ke
     * Laba Bersih). Format sekarang: Penjualan -> HPP -> Laba Kotor ->
     * rincian Beban Usaha per akun -> Total Beban -> Laba Bersih ->
     * Persentase Laba, mengikuti format laporan laba rugi standar.
     */
    public function labaRugi(Request $request)
    {
        $awal = $request->get('awal', now()->startOfMonth()->toDateString());
        $akhir = $request->get('akhir', now()->toDateString());

        $totals = DB::table('jurnal_umum')
            ->join('bag_akun', 'bag_akun.kode_akun', '=', 'jurnal_umum.kode_akun')
            ->whereBetween('tgl_jurnal', [$awal, $akhir])
            ->whereIn('tipe_akun', ['Pendapatan', 'Beban'])
            ->selectRaw('bag_akun.kode_akun, bag_akun.nama_akun, bag_akun.tipe_akun, SUM(debet) as total_debet, SUM(kredit) as total_kredit')
            ->groupBy('bag_akun.kode_akun', 'bag_akun.nama_akun', 'bag_akun.tipe_akun')
            ->orderBy('bag_akun.kode_akun')
            ->get();

        $pendapatan = $totals->where('tipe_akun', 'Pendapatan')->map(function ($a) {
            $a->jumlah = $a->total_kredit - $a->total_debet;
            return $a;
        })->values();

        // Akun 5000 (Harga Pokok Penjualan) SENGAJA dipisah dari sisa akun
        // Beban - HPP dikurangkan lebih dulu untuk dapat Laba Kotor,
        // Beban Usaha (5100 dst) baru dikurangkan setelahnya. Angka HPP di
        // sini datang dari jurnal_umum yang diposting AccountingService::
        // postPenjualan() = qty BOM x bahan_baku.harga_rata_rata (harga
        // pembelian riil, rata-rata tertimbang) - bukan estimasi manual.
        $totalHpp = (float) $totals->where('kode_akun', '5000')->sum(fn ($a) => $a->total_debet - $a->total_kredit);

        $bebanUsaha = $totals->where('tipe_akun', 'Beban')->where('kode_akun', '!=', '5000')->map(function ($a) {
            $a->jumlah = $a->total_debet - $a->total_kredit;
            return $a;
        })->values();

        $totalPendapatan = (float) $pendapatan->sum('jumlah');
        $totalBebanUsaha = (float) $bebanUsaha->sum('jumlah');
        $labaKotor = $totalPendapatan - $totalHpp;
        $labaBersih = $labaKotor - $totalBebanUsaha;
        $persentaseLaba = $totalPendapatan > 0 ? ($labaBersih / $totalPendapatan) * 100 : 0;

        return view('akuntansi.laba-rugi', compact(
            'awal', 'akhir', 'pendapatan', 'totalPendapatan',
            'totalHpp', 'labaKotor', 'bebanUsaha', 'totalBebanUsaha',
            'labaBersih', 'persentaseLaba'
        ));
    }

    /**
     * Perbandingan pemasukan Input Order dengan pengeluaran pada periode yang sama, dibaca dari data yang sudah ada:
     * pendapatan nota (jurnal 4000/4100, nota batal sudah terbalik), uang yang benar-benar diterima, pengeluaran dari
     * Kas Kecil, dan biaya yang dibayar langsung pemilik (per kategori). Pembelian bahan yang dibayar pemilik
     * ditampilkan sebagai keterangan, tidak ikut selisih (bahan masuk persediaan, bukan biaya).
     */
    public function perbandingan(Request $request)
    {
        $awal = $request->get('awal', now()->startOfMonth()->toDateString());
        $akhir = $request->get('akhir', now()->toDateString());

        $pendapatan = DB::table('jurnal_umum')->whereBetween('tgl_jurnal', [$awal, $akhir])->whereIn('kode_akun', ['4000', '4100'])
            ->selectRaw('kode_akun, SUM(kredit - debet) AS nilai')->groupBy('kode_akun')->pluck('nilai', 'kode_akun');
        $pendapatanPenjualan = round((float) ($pendapatan['4000'] ?? 0), 2);
        $pendapatanJasa = round((float) ($pendapatan['4100'] ?? 0), 2);
        $totalPemasukan = round($pendapatanPenjualan + $pendapatanJasa, 2);

        // Uang yang benar-benar masuk dari pembayaran nota. Pembayaran memakai saldo Deposit bukan uang baru: uangnya
        // sudah masuk saat setoran deposit, yang ditampilkan terpisah.
        $uangDiterima = round((float) DB::table('pembayaran_riwayat')
            ->where('tgl_bayar', '>=', $awal . ' 00:00:00')->where('tgl_bayar', '<=', $akhir . ' 23:59:59')
            ->where('metode', '!=', 'Deposit')->sum('nominal'), 2);
        // Hanya uang baru (Tunai/Transfer); "Kredit Nota Batal" adalah uang nota lama yang dipindah ke saldo pelanggan.
        $setoranDeposit = round((float) DB::table('konsumen_deposit')->where('tipe', 'Setoran')->whereIn('metode_bayar', ['Tunai', 'Transfer'])
            ->where('tgl_deposit', '>=', $awal)->where('tgl_deposit', '<=', $akhir)->sum('nominal'), 2);

        $pengeluaran = DB::table('kas_kecil as k')
            ->leftJoin('kategori_kas_kecil as c', 'c.id_kategori_kas_kecil', '=', 'k.id_kategori')
            ->whereNull('k.deleted_at')->where('k.jenis', 'Keluar')
            ->where('k.tanggal', '>=', $awal)->where('k.tanggal', '<=', $akhir)
            ->selectRaw("COALESCE(c.nama_kategori, 'Tanpa kategori') AS kategori, k.metode = 'Dibayar Pemilik' AS pemilik, SUM(k.nominal) AS total")
            ->groupBy('kategori', 'pemilik')->orderBy('kategori')->get();
        $kasKecil = $pengeluaran->where('pemilik', 0)->values();
        $pemilik = $pengeluaran->where('pemilik', 1)->values();
        $totalKasKecil = round((float) $kasKecil->sum('total'), 2);
        $totalPemilik = round((float) $pemilik->sum('total'), 2);
        $totalPengeluaran = round($totalKasKecil + $totalPemilik, 2);
        $selisih = round($totalPemasukan - $totalPengeluaran, 2);

        $bahanDariPemilik = round((float) DB::table('jurnal_umum')->whereBetween('tgl_jurnal', [$awal, $akhir])
            ->where('kode_akun', '3100')->sum(DB::raw('kredit - debet')), 2);

        return view('akuntansi.perbandingan', compact(
            'awal', 'akhir', 'pendapatanPenjualan', 'pendapatanJasa', 'totalPemasukan', 'uangDiterima', 'setoranDeposit',
            'kasKecil', 'pemilik', 'totalKasKecil', 'totalPemilik', 'totalPengeluaran', 'selisih', 'bahanDariPemilik'
        ));
    }

    public function neraca(Request $request)
    {
        $tanggal = $request->get('tanggal', now()->toDateString());

        $totals = DB::table('jurnal_umum')
            ->join('bag_akun', 'bag_akun.kode_akun', '=', 'jurnal_umum.kode_akun')
            ->where('tgl_jurnal', '<=', $tanggal)
            ->selectRaw('bag_akun.kode_akun, bag_akun.nama_akun, bag_akun.tipe_akun, SUM(debet) as total_debet, SUM(kredit) as total_kredit')
            ->groupBy('bag_akun.kode_akun', 'bag_akun.nama_akun', 'bag_akun.tipe_akun')
            ->orderBy('bag_akun.kode_akun')
            ->get();

        $aset = $totals->where('tipe_akun', 'Aset')->map(function ($a) {
            $a->jumlah = $a->total_debet - $a->total_kredit;
            return $a;
        })->values();

        $kewajiban = $totals->where('tipe_akun', 'Kewajiban')->map(function ($a) {
            $a->jumlah = $a->total_kredit - $a->total_debet;
            return $a;
        })->values();

        $modal = $totals->where('tipe_akun', 'Modal')->map(function ($a) {
            $a->jumlah = $a->total_kredit - $a->total_debet;
            return $a;
        })->values();

        $totalPendapatan = $totals->where('tipe_akun', 'Pendapatan')->sum(fn($a) => $a->total_kredit - $a->total_debet);
        $totalBeban = $totals->where('tipe_akun', 'Beban')->sum(fn($a) => $a->total_debet - $a->total_kredit);

        // Laba berjalan: sistem ini belum punya proses tutup buku (closing
        // entry) yang memindahkan laba/rugi ke akun Modal di akhir periode,
        // jadi supaya Neraca tetap balance (Aset = Kewajiban + Modal), laba
        // kumulatif sampai tanggal ini ditampilkan sebagai baris ekuitas
        // tersendiri - bukan dihitung ulang secara berbeda dari Laba Rugi,
        // cuma window periodenya "sejak awal s/d tanggal ini" bukan bulan
        // berjalan saja.
        $labaBerjalan = $totalPendapatan - $totalBeban;

        $totalAset = $aset->sum('jumlah');
        $totalKewajiban = $kewajiban->sum('jumlah');
        $totalModal = $modal->sum('jumlah');
        $totalEkuitas = $totalModal + $labaBerjalan;
        $totalPasiva = $totalKewajiban + $totalEkuitas;
        $balance = abs($totalAset - $totalPasiva) < 0.01;

        $narasi = $this->buildNarasiNeraca($aset, $totalAset, $totalKewajiban, $totalEkuitas, $labaBerjalan, $balance, $totalPasiva);

        return view('akuntansi.neraca', compact(
            'tanggal', 'aset', 'kewajiban', 'modal', 'labaBerjalan',
            'totalAset', 'totalKewajiban', 'totalModal', 'totalEkuitas', 'totalPasiva', 'balance', 'narasi'
        ));
    }

    /**
     * Narasi otomatis RULE-BASED (bukan AI/LLM - permintaan eksplisit user
     * supaya hasilnya deterministik/konsisten & tidak butuh API eksternal
     * berbayar untuk dokumen keuangan resmi) - kalimat dibentuk dari
     * kondisi atas angka yang SUDAH dihitung neraca(), bukan query baru.
     * Setiap butir punya 'tone' (info/positif/peringatan) supaya tampilan
     * bisa memberi aksen warna berbeda untuk hal yang perlu perhatian.
     */
    private function buildNarasiNeraca($aset, float $totalAset, float $totalKewajiban, float $totalEkuitas, float $labaBerjalan, bool $balance, float $totalPasiva): array
    {
        $butir = [];
        $fmt = fn (float $v) => 'Rp' . number_format(abs($v), 0, ',', '.');

        // 1. Status keseimbangan - selalu paling relevan, ditaruh pertama.
        if ($balance) {
            $butir[] = ['tone' => 'positif', 'teks' => 'Neraca dalam kondisi BALANCE - Total Aset (' . $fmt($totalAset) . ') sama dengan Total Kewajiban + Ekuitas (' . $fmt($totalPasiva) . ').'];
        } else {
            $butir[] = ['tone' => 'peringatan', 'teks' => 'Neraca TIDAK BALANCE - ada selisih ' . $fmt($totalAset - $totalPasiva) . ' antara Total Aset dan Total Kewajiban + Ekuitas yang perlu ditelusuri segera.'];
        }

        // 2. Rasio Lancar (Current Ratio) - seluruh akun Aset di CoA aplikasi
        // ini memang aset lancar (Kas/Bank/Piutang/Persediaan, belum ada
        // aset tetap), jadi Total Aset dipakai apa adanya sebagai aset
        // lancar tanpa perlu memfilter per kode akun.
        if ($totalKewajiban > 0.01) {
            $rasioLancar = $totalAset / $totalKewajiban;
            if ($rasioLancar >= 2) {
                $butir[] = ['tone' => 'positif', 'teks' => 'Rasio Lancar ' . angka($rasioLancar, 2) . 'x - sangat sehat, aset yang tersedia mampu menutupi kewajiban lebih dari dua kali lipat.'];
            } elseif ($rasioLancar >= 1) {
                $butir[] = ['tone' => 'info', 'teks' => 'Rasio Lancar ' . angka($rasioLancar, 2) . 'x - aset yang tersedia masih mencukupi untuk menutupi seluruh kewajiban.'];
            } else {
                $butir[] = ['tone' => 'peringatan', 'teks' => 'Rasio Lancar ' . angka($rasioLancar, 2) . 'x - aset yang tersedia BELUM cukup menutupi kewajiban, arus kas perlu diperhatikan.'];
            }
        } else {
            $butir[] = ['tone' => 'info', 'teks' => 'Tidak ada kewajiban tercatat per tanggal ini - seluruh aset murni dibiayai modal sendiri.'];
        }

        // 3. Struktur permodalan (Debt to Equity).
        if ($totalEkuitas > 0.01) {
            $der = $totalKewajiban / $totalEkuitas;
            if ($der < 0.5) {
                $butir[] = ['tone' => 'positif', 'teks' => 'Struktur permodalan konservatif (utang ' . number_format($der * 100, 0, ',', '.') . '% dari ekuitas) - mayoritas usaha dibiayai modal sendiri, bukan utang.'];
            } elseif ($der <= 1) {
                $butir[] = ['tone' => 'info', 'teks' => 'Struktur permodalan cukup seimbang antara utang (' . number_format($der * 100, 0, ',', '.') . '% dari ekuitas) dan modal sendiri.'];
            } else {
                $butir[] = ['tone' => 'peringatan', 'teks' => 'Kewajiban (' . number_format($der * 100, 0, ',', '.') . '% dari ekuitas) lebih besar dibanding modal sendiri - ketergantungan pada utang perlu diwaspadai.'];
            }
        } elseif ($totalEkuitas < -0.01) {
            $butir[] = ['tone' => 'peringatan', 'teks' => 'Ekuitas berada di posisi NEGATIF (' . $fmt($totalEkuitas) . ') - modal sudah tergerus rugi berjalan, kondisi ini perlu perhatian serius.'];
        }

        // 4. Akun Aset dengan saldo NEGATIF - Aset seharusnya bersaldo Debet
        // (>= 0), saldo minus (mis. Kas minus) menandakan pencatatan yang
        // patut ditelusuri (SELALU ditampilkan sebelum komposisi, karena
        // ini yang membuat persentase komposisi di bawah bisa terlihat
        // ganjil/lebih dari 100% kalau dibiarkan tanpa penjelasan).
        $asetNegatif = $aset->filter(fn ($a) => $a->jumlah < -0.01);
        foreach ($asetNegatif as $a) {
            $butir[] = ['tone' => 'peringatan', 'teks' => 'Akun "' . $a->nama_akun . '" bersaldo MINUS ' . $fmt($a->jumlah) . ' - tidak wajar untuk akun Aset, sebaiknya ditelusuri (kemungkinan ada jurnal Kredit yang melebihi Debet).'];
        }

        // 5. Komposisi aset terbesar.
        $asetTerbesar = $aset->sortByDesc('jumlah')->first();
        if ($asetTerbesar && $totalAset > 0.01) {
            $persenAset = ($asetTerbesar->jumlah / $totalAset) * 100;
            $butir[] = ['tone' => 'info', 'teks' => 'Komponen aset terbesar adalah ' . $asetTerbesar->nama_akun . ' sebesar ' . $fmt($asetTerbesar->jumlah) . ' (' . number_format($persenAset, 0, ',', '.') . '% dari total aset' . ($asetNegatif->isNotEmpty() ? ', persentase melebihi 100% karena ada akun aset lain yang bersaldo minus seperti disebutkan di atas' : '') . ').'];
        }

        // 6. Kondisi laba/rugi berjalan.
        if ($labaBerjalan > 0.01) {
            $persenEkuitas = $totalEkuitas > 0.01 ? ($labaBerjalan / $totalEkuitas) * 100 : null;
            $butir[] = ['tone' => 'positif', 'teks' => 'Mencatatkan LABA berjalan ' . $fmt($labaBerjalan) . ' sampai tanggal ini'
                . ($persenEkuitas !== null ? ', berkontribusi ' . number_format($persenEkuitas, 0, ',', '.') . '% terhadap total ekuitas.' : '.')];
        } elseif ($labaBerjalan < -0.01) {
            $butir[] = ['tone' => 'peringatan', 'teks' => 'Mencatatkan RUGI berjalan ' . $fmt($labaBerjalan) . ' sampai tanggal ini, mengurangi ekuitas perusahaan.'];
        }

        return $butir;
    }
}
