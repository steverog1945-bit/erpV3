<?php

namespace App\Http\Controllers;

use App\Models\BahanBaku;
use App\Models\Kategori;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaporanController extends Controller
{
    /**
     * Laporan Rekap Transaksi Order - baca langsung dari transaksi, tidak ada
     * tabel rekap terpisah supaya tidak mungkin selisih dengan data aslinya.
     */
    public function order(Request $request)
    {
        $query = DB::table('transaksi')
            ->join('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
            ->join('golongan_konsumen', 'konsumen.id_golongan', '=', 'golongan_konsumen.id_golongan')
            ->leftJoin('users', 'transaksi.id_user_kasir', '=', 'users.id_user')
            ->leftJoin('karyawan', 'users.id_karyawan', '=', 'karyawan.id_karyawan')
            ->select(
                'transaksi.id_transaksi',
                'transaksi.nomor_nota',
                'transaksi.no_po',
                'transaksi.tipe_order',
                'transaksi.tgl_order',
                'transaksi.created_at',
                'transaksi.total_bayar',
                'transaksi.diskon',
                'transaksi.status_pembayaran',
                'transaksi.status_nota',
                'transaksi.metode_pembayaran',
                'konsumen.nama_konsumen',
                'golongan_konsumen.nama_golongan',
                DB::raw('COALESCE(karyawan.nama_karyawan, users.username) as nama_operator')
            )
            // Piutang RIIL per nota = total_bayar dikurangi yang sudah
            // dibayar (pembayaran_riwayat) - sebelumnya resume "Total
            // Piutang" memakai total_bayar PENUH untuk nota berstatus
            // bukan Lunas, jadi overstate untuk nota yang sudah DP
            // sebagian. Kolom ini dipakai baik di tabel maupun resume.
            ->selectSub(function ($q) {
                $q->from('pembayaran_riwayat')
                    ->selectRaw('COALESCE(SUM(nominal), 0)')
                    ->whereColumn('pembayaran_riwayat.id_transaksi', 'transaksi.id_transaksi');
            }, 'total_dibayar');

        // Default rekap HARI INI SAJA - berlaku sama di semua laporan,
        // supaya begitu dibuka langsung menampilkan pergerakan hari itu
        // tanpa perlu isi filter dulu.
                // §8I: tolak format tanggal yang rusak, bukan melempar 500.
                $request->validate(['tgl_dari' => 'nullable|date', 'tgl_sampai' => 'nullable|date']);
        $tglDari = $request->filled('tgl_dari') ? $request->tgl_dari : now()->format('Y-m-d');
        $tglSampai = $request->filled('tgl_sampai') ? $request->tgl_sampai : now()->format('Y-m-d');
        // Batas atas hari-terakhir (eksklusif) - dipakai lagi oleh query resume
        // di bawah, supaya filter resume & daftar memakai batas yang sama persis.
        $batasAtas = \Carbon\Carbon::parse($tglSampai)->addDay()->format('Y-m-d') . ' 00:00:00';
        $query->where('transaksi.tgl_order', '>=', $tglDari . ' 00:00:00')
              ->where('transaksi.tgl_order', '<', $batasAtas);

        if ($request->filled('status_pembayaran')) {
            $query->where('transaksi.status_pembayaran', $request->status_pembayaran);
        }
        if ($request->filled('id_golongan')) {
            $query->where('konsumen.id_golongan', $request->id_golongan);
        }
        // Sebelumnya text-input dicocokkan LIKE ke nama_konsumen (rawan
        // typo/salah pilih kalau nama mirip) - diganti pilih pasti lewat
        // id_konsumen dari dropdown Select2 (live search) di baris ini.
        if ($request->filled('id_konsumen')) {
            $query->where('transaksi.id_konsumen', $request->id_konsumen);
        }

        // Resume dihitung dari filter YANG SAMA dengan listing (sebelum
        // pagination) supaya konsisten dengan filter. Nota yang sudah
        // dibatalkan tetap ditampilkan di listing (untuk jejak audit, ditandai
        // badge Dibatalkan) tapi dikeluarkan dari resume angka supaya total
        // transaksi/piutang tidak ikut menghitung order yang sudah batal.
        //
        // Perf dataset besar (uji beban 756 ribu baris sintetis, 2026-11-13):
        // resume HANYA butuh kolom transaksi, jadi TIDAK ikut join master
        // konsumen/golongan/users/karyawan untuk COUNT/SUM. Join 1:1 ke PK
        // itu tidak menambah/mengurangi baris (FK menjamin konsumen/golongan
        // selalu ada), tapi menggandakan biaya scan - COUNT yang seharusnya
        // 1ms lewat indeks transaksi_tgl_order_status_nota_index menjadi
        // 193ms per query. Filter id_golongan tetap dihormati lewat
        // whereExists ke konsumen; angka yang dihasilkan identik dengan
        // versi join.
        $saringResume = function ($q) use ($request, $tglDari, $batasAtas) {
            $q->where('transaksi.tgl_order', '>=', $tglDari . ' 00:00:00')
              ->where('transaksi.tgl_order', '<', $batasAtas)
              ->where('transaksi.status_nota', '!=', 'Dibatalkan');
            if ($request->filled('status_pembayaran')) {
                $q->where('transaksi.status_pembayaran', $request->status_pembayaran);
            }
            if ($request->filled('id_golongan')) {
                $q->whereExists(fn ($w) => $w->selectRaw(1)->from('konsumen')
                    ->whereColumn('konsumen.id_konsumen', 'transaksi.id_konsumen')
                    ->where('konsumen.id_golongan', $request->id_golongan));
            }
            if ($request->filled('id_konsumen')) {
                $q->where('transaksi.id_konsumen', $request->id_konsumen);
            }
        };

        $resumeBase = DB::table('transaksi')->where($saringResume);
        $jumlahTransaksi = (clone $resumeBase)->count();
        $totalTransaksi = (clone $resumeBase)->sum('transaksi.total_bayar');

        // Piutang resume = jumlah GREATEST(0, total - yang sudah dibayar)
        // dihitung DI SQL. Sebelumnya dihitung di PHP lewat ->get()->sum(),
        // yang memuat SELURUH baris hasil filter (dilengkapi subquery total
        // terbayar korelasi per baris) ke memori dulu - rumus & filter sama
        // persis, angka identik.
        $totalPiutang = (float) (clone $resumeBase)
            ->where('transaksi.status_pembayaran', '!=', 'Lunas')
            ->selectRaw('COALESCE(SUM(GREATEST(0, transaksi.total_bayar - COALESCE((SELECT SUM(pr.nominal) FROM pembayaran_riwayat pr WHERE pr.id_transaksi = transaksi.id_transaksi), 0))), 0) as sisa_tagihan')
            ->first()
            ->sisa_tagihan;

        // Batasan nota untuk kedua breakdown: whereExists ke transaksi dengan
        // filter YANG SAMA dengan resume, bukan pluck seluruh id lalu whereIn -
        // pada rentang lebar (36 bulan) pluck bisa memuat ratusan ribu id ke
        // memori sia-sia.
        $batasiNota = fn ($w) => $w->selectRaw(1)->from('transaksi')
            ->whereColumn('transaksi.id_transaksi', 'transaksi_detail.id_transaksi')
            ->where($saringResume);

        // Breakdown per Divisi/Mesin (proxy kategori - lihat catatan di
        // laporan.order.blade.php, tabel produk tidak punya kolom
        // kategori) - biaya cetak/design/finishing dijumlah dari
        // transaksi_detail, DIBATASI ke nota yang sama persis dengan hasil
        // filter resume di atas supaya kedua bagian laporan selalu sinkron.
        // Batasan ke nota yang sama persis dengan hasil filter resume di atas
        // supaya kedua bagian laporan selalu sinkron (lihat $batasiNota).
        $breakdownDivisi = DB::table('transaksi_detail')
            ->leftJoin('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
            ->leftJoin('mesin', 'produk.id_mesin', '=', 'mesin.id_mesin')
            ->whereExists($batasiNota)
            ->select(
                DB::raw("COALESCE(mesin.nama_mesin, 'Lainnya') as divisi"),
                DB::raw('SUM(transaksi_detail.biaya_cetak) as total_biaya_cetak'),
                DB::raw('SUM(transaksi_detail.biaya_desain) as total_biaya_desain'),
                DB::raw('SUM(transaksi_detail.biaya_finishing) as total_biaya_finishing')
            )
            ->groupBy(DB::raw("COALESCE(mesin.nama_mesin, 'Lainnya')"))
            ->orderByDesc(DB::raw('SUM(transaksi_detail.biaya_cetak)'))
            ->get();

        // Rincian per PRODUK di dalam tiap divisi (bukan cuma total per
        // divisi) - dipakai laporan cetak supaya rinciannya selengkap
        // referensi (tiap divisi jadi tabel produk tersendiri, sama
        // seperti pola "Ringkasan per Divisi" di Laporan Produksi).
        // DIBATASI ke nota yang sama persis dengan filter resume, sama
        // seperti breakdownDivisi.
        $breakdownProdukMentah = DB::table('transaksi_detail')
            ->leftJoin('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
            ->leftJoin('jasa', 'transaksi_detail.id_jasa', '=', 'jasa.id_jasa')
            ->leftJoin('mesin', 'produk.id_mesin', '=', 'mesin.id_mesin')
            ->whereExists($batasiNota)
            ->select(
                DB::raw("COALESCE(mesin.nama_mesin, 'Lainnya') as divisi"),
                DB::raw("COALESCE(produk.nama_produk, jasa.nama_jasa, '-') as produk"),
                DB::raw('COUNT(DISTINCT transaksi_detail.id_transaksi) as jml_nota'),
                DB::raw('SUM(transaksi_detail.qty) as total_qty'),
                DB::raw('SUM(transaksi_detail.subtotal) as total_subtotal')
            )
            ->groupBy(DB::raw("COALESCE(mesin.nama_mesin, 'Lainnya')"), DB::raw("COALESCE(produk.nama_produk, jasa.nama_jasa, '-')"))
            ->orderBy('produk')
            ->get();
        $breakdownProduk = $breakdownProdukMentah
            ->groupBy('divisi')
            ->sortKeys();

        // Paginasi lebih kecil dari laporan lain (10, bukan 20) - tiap nota
        // sekarang tampil sebagai blok PENUH (header + tabel item + total),
        // bukan satu baris ringkas, supaya satu halaman tidak kepanjangan.
        $data = $query->orderByDesc('transaksi.tgl_order')->paginate(20)->withQueryString();

        // Detail item per nota - HANYA untuk nota di halaman yang sedang
        // tampil (bukan seluruh hasil filter) supaya query tetap ringan.
        // SELALU ditampilkan penuh (bukan lagi expand/collapse) sesuai
        // permintaan user - rincian konsumen & item pesanan harus langsung
        // terlihat semua tanpa perlu klik.
        $idNotaHalamanIni = collect($data->items())->pluck('id_transaksi');
        $itemPerNota = DB::table('transaksi_detail')
            ->leftJoin('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
            ->leftJoin('jasa', 'transaksi_detail.id_jasa', '=', 'jasa.id_jasa')
            ->leftJoin('mesin', 'produk.id_mesin', '=', 'mesin.id_mesin')
            ->whereIn('transaksi_detail.id_transaksi', $idNotaHalamanIni)
            ->select('transaksi_detail.*', 'produk.nama_produk', 'jasa.nama_jasa', 'mesin.nama_mesin as divisi')
            ->orderBy('transaksi_detail.id_detail')
            ->get()
            ->groupBy('id_transaksi');

        $golongan = DB::table('golongan_konsumen')->orderBy('nama_golongan')->get();
        $konsumenList = DB::table('konsumen')->orderBy('nama_konsumen')->get(['id_konsumen', 'nama_konsumen', 'telepon']);

        return view('laporan.order', compact(
            'data', 'golongan', 'konsumenList', 'jumlahTransaksi', 'totalTransaksi', 'totalPiutang', 'breakdownDivisi', 'breakdownProduk', 'itemPerNota',
            'tglDari', 'tglSampai'
        ));
    }

    /**
     * Laporan Produksi - gabungan ringkasan per divisi/produk (sesuai kebutuhan
     * rekap produk) dan detail per SPK (sesuai kebutuhan laporan produksi),
     * karena keduanya membaca baris transaksi_detail yang persis sama, hanya
     * beda cara mengelompokkannya.
     */
    public function produksi(Request $request)
    {
        $base = DB::table('transaksi_detail')
            ->join('transaksi', 'transaksi_detail.id_transaksi', '=', 'transaksi.id_transaksi')
            ->join('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
            ->join('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
            ->leftJoin('mesin', 'produk.id_mesin', '=', 'mesin.id_mesin')
            ->leftJoin('users', 'transaksi.id_user_kasir', '=', 'users.id_user')
            ->leftJoin('karyawan', 'users.id_karyawan', '=', 'karyawan.id_karyawan');

        // Default rekap HARI INI SAJA (bukan lagi seluruh riwayat) - berlaku
        // sama di semua laporan, supaya begitu dibuka langsung menampilkan
        // pergerakan hari itu tanpa perlu isi filter dulu.
                // §8I: tolak format tanggal yang rusak, bukan melempar 500.
                $request->validate(['tgl_dari' => 'nullable|date', 'tgl_sampai' => 'nullable|date']);
        $tglDari = $request->filled('tgl_dari') ? $request->tgl_dari : now()->format('Y-m-d');
        $tglSampai = $request->filled('tgl_sampai') ? $request->tgl_sampai : now()->format('Y-m-d');
        $base->where('transaksi.tgl_order', '>=', $tglDari . ' 00:00:00')
             ->where('transaksi.tgl_order', '<', \Carbon\Carbon::parse($tglSampai)->addDay()->format('Y-m-d') . ' 00:00:00');

        if ($request->filled('id_mesin')) {
            $base->where('produk.id_mesin', $request->id_mesin);
        }
        if ($request->filled('status_spk')) {
            // Diterapkan ke $base (sebelum di-clone) supaya ringkasan dan
            // detail SELALU menghitung dari himpunan baris yang sama persis.
            // Sebelumnya filter ini hanya ditempel ke $detailQuery di bawah,
            // jadi kartu "Ringkasan per Divisi & Produk" (termasu total
            // keseluruhannya) tetap menghitung SEMUA status walau user sudah
            // memfilter status di halaman yang sama - laporan yang dicetak
            // jadi tidak sinkron antara ringkasan dan detailnya sendiri.
            $base->where('transaksi_detail.status_spk', $request->status_spk);
        }

        // --- RINGKASAN PER DIVISI & PRODUK ---
        // m2 HANYA dihitung untuk produk metode_hitung='ukuran' (outdoor/
        // area, harga dari panjang x lebar) - produk metode 'quantity'
        // (harga per pcs) tidak punya makna luas, dibiarkan 0.
        //
        // PENTING: m2 SENGAJA TIDAK dijumlahkan lintas divisi jadi satu
        // angka gabungan (permintaan eksplisit user, mengacu ke referensi -
        // tiap divisi/kategori di sana selalu tampil sebagai bagian/blok
        // SENDIRI dengan totalnya masing-masing, tidak pernah digabung jadi
        // satu total besar lintas divisi yang unitnya beda-beda). Baris
        // mentah dikelompokkan PER DIVISI di PHP (bukan cuma di-flatten)
        // supaya tiap divisi punya subtotalnya sendiri di view.
        // Ringkasan hanya pekerjaan yang berlaku: nota dibatalkan dan SPK Batal tidak dihitung (kecuali yang
        // difilter memang status Batal). Detail di bawah tetap menampilkannya sebagai jejak.
        $ringkasanQuery = (clone $base)->where('transaksi.status_nota', '!=', 'Dibatalkan');
        if ($request->status_spk !== 'Batal') {
            $ringkasanQuery->where('transaksi_detail.status_spk', '!=', 'Batal');
        }
        $ringkasanMentah = $ringkasanQuery
            ->select(
                'mesin.nama_mesin as divisi',
                'produk.nama_produk',
                'produk.metode_hitung',
                DB::raw('COUNT(DISTINCT transaksi_detail.id_transaksi) as total_transaksi'),
                DB::raw('SUM(transaksi_detail.qty) as total_order'),
                DB::raw('SUM(COALESCE(transaksi_detail.qty_cetak, 0)) as total_cetak'),
                DB::raw("SUM(CASE WHEN produk.metode_hitung = 'ukuran' THEN (transaksi_detail.p_ukuran * transaksi_detail.l_ukuran / 10000) * transaksi_detail.qty ELSE 0 END) as total_m2_order"),
                DB::raw("SUM(CASE WHEN produk.metode_hitung = 'ukuran' THEN (transaksi_detail.p_ukuran * transaksi_detail.l_ukuran / 10000) * COALESCE(transaksi_detail.qty_cetak, 0) ELSE 0 END) as total_m2_cetak")
            )
            ->groupBy('mesin.nama_mesin', 'produk.nama_produk', 'produk.metode_hitung')
            ->orderBy('produk.nama_produk')
            ->get();

        $ringkasanPerDivisi = $ringkasanMentah
            ->groupBy(fn ($r) => $r->divisi ?? 'Lainnya')
            ->sortKeys()
            ->map(fn ($baris, $namaDivisi) => (object) [
                'divisi' => $namaDivisi,
                'baris' => $baris,
                // Subtotal divisi ini SAJA - qty pcs dan m2 dipisah (bukan
                // dijumlah jadi satu angka) karena satuannya beda.
                'subtotal_qty_order_pcs' => $baris->where('metode_hitung', '!=', 'ukuran')->sum('total_order'),
                'subtotal_qty_cetak_pcs' => $baris->where('metode_hitung', '!=', 'ukuran')->sum('total_cetak'),
                'subtotal_m2_order' => $baris->where('metode_hitung', 'ukuran')->sum('total_m2_order'),
                'subtotal_m2_cetak' => $baris->where('metode_hitung', 'ukuran')->sum('total_m2_cetak'),
            ]);

        // Total keseluruhan LAPORAN (jumlah nota &amp; qty pesanan) tetap
        // ditampilkan di resume atas - ini beda dari m2, memang lazim
        // dijumlahkan lintas divisi (sama seperti "Jumlah Transaksi" di
        // Laporan Order), karena satuannya konsisten (pcs/nota).
        $totalOrderKeseluruhan = $ringkasanMentah->sum('total_order');
        $totalCetakKeseluruhan = $ringkasanMentah->sum('total_cetak');

        // --- DETAIL PER SPK (status_spk sudah diterapkan di $base di atas) ---
        $detail = (clone $base)
            ->select(
                'transaksi_detail.id_detail',
                'transaksi_detail.nama_file',
                'transaksi_detail.qty',
                'transaksi_detail.qty_cetak',
                'transaksi_detail.p_ukuran',
                'transaksi_detail.l_ukuran',
                'transaksi_detail.status_spk',
                'produk.metode_hitung',
                'transaksi.nomor_nota',
                'transaksi.tgl_order',
                'konsumen.nama_konsumen',
                'mesin.nama_mesin as divisi',
                DB::raw('COALESCE(karyawan.nama_karyawan, users.username) as desainer')
            )
            ->orderByDesc('transaksi_detail.id_detail')
            ->paginate(20)
            ->withQueryString();

        $divisis = DB::table('mesin')->orderBy('nama_mesin')->get();

        return view('laporan.produksi', compact(
            'ringkasanPerDivisi', 'detail', 'divisis', 'totalOrderKeseluruhan', 'totalCetakKeseluruhan',
            'tglDari', 'tglSampai'
        ));
    }

    /**
     * Laporan Kas - rekap sesi kas_harian lintas tanggal. Sebelumnya kas
     * hanya bisa dilihat satu sesi per satu sesi (cetak closing per sesi
     * atau halaman Kelola Kas yang cuma menampilkan hari ini) - tidak ada
     * cara melihat tren/rekap kas lintas periode. Murni baca dari
     * kas_harian yang sudah lengkap, tidak ada tabel/kolom baru.
     */
    public function kas(Request $request)
    {
        $query = DB::table('kas_harian')
            ->leftJoin('users', 'kas_harian.id_user', '=', 'users.id_user')
            ->leftJoin('karyawan', 'users.id_karyawan', '=', 'karyawan.id_karyawan')
            ->select(
                'kas_harian.*',
                DB::raw('COALESCE(karyawan.nama_karyawan, users.username) as nama_kasir')
            );

                // §8I: tolak format tanggal yang rusak, bukan melempar 500.
                $request->validate(['tgl_dari' => 'nullable|date', 'tgl_sampai' => 'nullable|date']);
        $tglDari = $request->filled('tgl_dari') ? $request->tgl_dari : now()->format('Y-m-d');
        $tglSampai = $request->filled('tgl_sampai') ? $request->tgl_sampai : now()->format('Y-m-d');

        // Perf dataset besar: rentang sargable (DATE() mematikan index).
        $query->where('kas_harian.tgl_kas', '>=', $tglDari . ' 00:00:00')
              ->where('kas_harian.tgl_kas', '<', \Carbon\Carbon::parse($tglSampai)->addDay()->format('Y-m-d') . ' 00:00:00');

        if ($request->filled('status_lock')) {
            $query->where('kas_harian.status_lock', $request->status_lock);
        }

        // Ringkasan dihitung dari query YANG SAMA (sebelum pagination) supaya
        // konsisten dengan filter - pola yang sama dipakai laporan Order.
        $ringkasanQuery = clone $query;
        $totalTunaiMasuk = (clone $ringkasanQuery)->sum('kas_harian.total_tunai_masuk');
        $totalTransferMasuk = (clone $ringkasanQuery)->sum('kas_harian.total_transfer_masuk');
        $totalDebitMasuk = (clone $ringkasanQuery)->sum('kas_harian.total_debit_masuk');
        $totalKeluar = (clone $ringkasanQuery)->sum('kas_harian.total_tunai_keluar');
        $totalSelisih = (clone $ringkasanQuery)->sum('kas_harian.selisih');
        $jumlahSesi = (clone $ringkasanQuery)->count();

        $data = $query->orderByDesc('kas_harian.tgl_kas')->paginate(20)->withQueryString();

        // Rincian transaksi manual (pemasukan/pengeluaran non-penjualan) per
        // sesi kas, supaya langsung terlihat semua tanpa perlu klik - pola
        // sama dengan itemPerNota di laporan Order.
        $idSesiHalamanIni = collect($data->items())->pluck('id_kas');
        $manualPerSesi = DB::table('kas_manual')
            ->leftJoin('users', 'kas_manual.id_user', '=', 'users.id_user')
            ->whereIn('kas_manual.id_kas', $idSesiHalamanIni)
            ->select('kas_manual.*', 'users.username as nama_user')
            ->orderBy('kas_manual.waktu')
            ->get()
            ->groupBy('id_kas');

        return view('laporan.kas', compact(
            'data', 'tglDari', 'tglSampai',
            'totalTunaiMasuk', 'totalTransferMasuk', 'totalDebitMasuk', 'totalKeluar', 'totalSelisih', 'jumlahSesi',
            'manualPerSesi'
        ));
    }

    /**
     * Laporan Belanja Bahan - pembukuan pembelian bahan baku TERPISAH dari
     * Laporan Kas (kas fisik laci kasir) dan Keuangan Jersey. Pembelian
     * bahan sebelumnya cuma terlihat lewat jurnal akuntansi (akun 1200/
     * 2000) tanpa daftar operasional per faktur - laporan ini murni baca
     * dari pembelian_bahan, tidak ada tabel/kolom baru.
     */
    public function belanjaBahan(Request $request)
    {
        $query = DB::table('pembelian_bahan')
            ->leftJoin('kedatangan_bahan', 'pembelian_bahan.id_kedatangan', '=', 'kedatangan_bahan.id_kedatangan')
            ->leftJoin('supplier', 'kedatangan_bahan.id_supplier', '=', 'supplier.id_supplier')
            ->select('pembelian_bahan.*', 'supplier.nama_supplier')
            // Faktur yang dibatalkan (soft delete, jurnalnya sudah dibalik) bukan belanja.
            ->whereNull('pembelian_bahan.deleted_at');

                // §8I: tolak format tanggal yang rusak, bukan melempar 500.
                $request->validate(['tgl_dari' => 'nullable|date', 'tgl_sampai' => 'nullable|date']);
        $tglDari = $request->filled('tgl_dari') ? $request->tgl_dari : now()->format('Y-m-d');
        $tglSampai = $request->filled('tgl_sampai') ? $request->tgl_sampai : now()->format('Y-m-d');
        $query->where('pembelian_bahan.tgl_transaksi', '>=', $tglDari . ' 00:00:00')->where('pembelian_bahan.tgl_transaksi', '<', \Carbon\Carbon::parse($tglSampai)->addDay()->format('Y-m-d') . ' 00:00:00');

        if ($request->filled('status_pembayaran')) {
            $query->where('pembelian_bahan.status_pembayaran', $request->status_pembayaran);
        }
        if ($request->filled('tipe_pembelian')) {
            $query->where('pembelian_bahan.tipe_pembelian', $request->tipe_pembelian);
        }

        $ringkasanQuery = clone $query;
        $totalBelanja = (clone $ringkasanQuery)->sum('pembelian_bahan.grand_total');
        // Hutang = SISA faktur (total dikurangi pembayaran yang tidak di-void), sama dengan Laporan Hutang Supplier dan
        // akun Hutang Usaha; dulu memakai total faktur penuh sehingga cicilan tidak mengurangi angkanya.
        $totalHutang = (float) (clone $ringkasanQuery)->where('pembelian_bahan.status_pembayaran', '!=', 'Lunas')
            ->select(DB::raw('COALESCE(SUM(pembelian_bahan.grand_total - COALESCE((SELECT SUM(ph.nominal) FROM pembayaran_hutang ph WHERE ph.id_pembelian = pembelian_bahan.id_pembelian AND ph.voided_at IS NULL), 0)), 0) AS sisa'))
            ->value('sisa');
        $jumlahFaktur = (clone $ringkasanQuery)->count();

        $data = $query->orderByDesc('pembelian_bahan.tgl_transaksi')->paginate(20)->withQueryString();

        // Rincian bahan yang dibeli per faktur, supaya langsung terlihat
        // semua tanpa perlu klik - pola sama dengan itemPerNota di laporan
        // Order.
        $idFakturHalamanIni = collect($data->items())->pluck('id_pembelian');
        $itemPerFaktur = DB::table('pembelian_bahan_detail')
            ->leftJoin('bahan_baku', 'pembelian_bahan_detail.id_bahan', '=', 'bahan_baku.id_bahan')
            ->whereIn('pembelian_bahan_detail.id_pembelian', $idFakturHalamanIni)
            ->select('pembelian_bahan_detail.*', 'bahan_baku.nama_bahan', 'bahan_baku.satuan_stok')
            ->orderBy('pembelian_bahan_detail.id_detail')
            ->get()
            ->groupBy('id_pembelian');

        return view('laporan.belanja-bahan', compact(
            'data', 'tglDari', 'tglSampai', 'totalBelanja', 'totalHutang', 'jumlahFaktur', 'itemPerFaktur'
        ));
    }

    /**
     * Laporan Order Jersey - pola sama dengan laporan Order percetakan
     * biasa (resume Jumlah Order/Total Transaksi/Total Piutang + breakdown
     * + detail per order), tapi TERPISAH karena modul jersey punya tabel
     * &amp; alur sendiri (jersey_orders, bukan transaksi). Piutang pakai
     * accessor sisa_tagihan (total - deposit, deposit = ledger riil dari
     * jersey_order_payments) - sudah akurat sejak awal, beda dengan
     * laporan Order biasa yang baru diperbaiki di method order() di atas.
     */
    public function orderJersey(Request $request)
    {
        $query = \App\Models\JerseyOrder::with(['konsumen', 'designer']);

        // Default rekap HARI INI SAJA - berlaku sama di semua laporan.
                // §8I: tolak format tanggal yang rusak, bukan melempar 500.
                $request->validate(['tgl_dari' => 'nullable|date', 'tgl_sampai' => 'nullable|date']);
        $tglDari = $request->filled('tgl_dari') ? $request->tgl_dari : now()->format('Y-m-d');
        $tglSampai = $request->filled('tgl_sampai') ? $request->tgl_sampai : now()->format('Y-m-d');
        $query->where('order_date', '>=', $tglDari . ' 00:00:00')
              ->where('order_date', '<', \Carbon\Carbon::parse($tglSampai)->addDay()->format('Y-m-d') . ' 00:00:00');

        if ($request->filled('status_pembayaran')) {
            $query->where('status_pembayaran', $request->status_pembayaran);
        }
        if ($request->filled('jersey_designer_id')) {
            $query->where('jersey_designer_id', $request->jersey_designer_id);
        }
        if ($request->filled('id_konsumen')) {
            $query->where('id_konsumen', $request->id_konsumen);
        }

        // Resume &amp; breakdown dihitung dari SELURUH hasil filter (bukan cuma
        // halaman yang tampil) - pola sama dengan laporan Order/Kas/Belanja
        // Bahan, supaya angkanya benar-benar total, bukan per-halaman.
        $semuaSesuaiFilter = (clone $query)->get();
        $jumlahOrder = $semuaSesuaiFilter->count();
        $totalTransaksi = $semuaSesuaiFilter->sum('total');
        $totalPiutang = $semuaSesuaiFilter->sum(fn ($o) => $o->sisa_tagihan);

        $breakdownDesainer = $semuaSesuaiFilter
            ->groupBy(fn ($o) => $o->designer->name ?? ($o->designer_name ?: 'Belum Ditentukan'))
            ->map(fn ($grup, $nama) => (object) [
                'nama' => $nama,
                'jumlah_order' => $grup->count(),
                'total' => $grup->sum('total'),
            ])
            ->sortByDesc('total')
            ->values();

        $idOrderSesuaiFilter = $semuaSesuaiFilter->pluck('id');
        $breakdownKategori = DB::table('jersey_order_lines')
            ->leftJoin('jersey_categories', 'jersey_order_lines.jersey_category_id', '=', 'jersey_categories.id')
            ->whereIn('jersey_order_lines.jersey_order_id', $idOrderSesuaiFilter)
            ->select(
                DB::raw("COALESCE(jersey_categories.name, 'Belum Ditentukan') as kategori"),
                DB::raw('SUM(jersey_order_lines.qty) as total_qty'),
                DB::raw('SUM(jersey_order_lines.subtotal) as total_subtotal')
            )
            ->groupBy(DB::raw("COALESCE(jersey_categories.name, 'Belum Ditentukan')"))
            ->orderByDesc(DB::raw('SUM(jersey_order_lines.subtotal)'))
            ->get();

        $data = $query->orderByDesc('order_date')->paginate(20)->withQueryString();

        // Detail barang per order - hanya untuk order di halaman yang
        // sedang tampil, sama seperti pola detail-per-nota di laporan
        // Order percetakan biasa.
        $idOrderHalamanIni = collect($data->items())->pluck('id');
        $linePerOrder = \App\Models\JerseyOrderLine::whereIn('jersey_order_id', $idOrderHalamanIni)
            ->orderBy('id')
            ->get()
            ->groupBy('jersey_order_id');

        // Finishing per order untuk nota cetak laporan - satu query agregat
        // (bukan satu query per order - pola yang sama dengan itemPerNota).
        $finishingPerOrder = DB::table('jersey_line_finishing')
            ->join('jersey_order_lines', 'jersey_order_lines.id', '=', 'jersey_line_finishing.jersey_order_line_id')
            ->whereIn('jersey_order_lines.jersey_order_id', $idOrderHalamanIni)
            ->select('jersey_order_lines.jersey_order_id', DB::raw('SUM(jersey_line_finishing.biaya) as finishing'))
            ->groupBy('jersey_order_lines.jersey_order_id')
            ->pluck('finishing', 'jersey_order_id');

        $designers = \App\Models\JerseyDesigner::orderBy('name')->get();
        $konsumenList = DB::table('konsumen')->orderBy('nama_konsumen')->get(['id_konsumen', 'nama_konsumen', 'telepon']);

        return view('laporan.order-jersey', compact(
            'data', 'designers', 'konsumenList', 'jumlahOrder', 'totalTransaksi', 'totalPiutang',
            'breakdownDesainer', 'breakdownKategori', 'linePerOrder', 'tglDari', 'tglSampai', 'finishingPerOrder'
        ));
    }

    /**
     * Laporan Daftar Piutang - nota yang belum Lunas dengan sisa tagihan
     * riil (total_bayar dikurangi yang sudah dibayar lewat
     * pembayaran_riwayat) masih > 0. Piutang RIIL cuma bisa dihitung
     * setelah baris diambil (hasil selisih dua kolom), jadi filter
     * "piutang > 0" dan sortir dilakukan di koleksi PHP, bukan di SQL -
     * makanya paginasi juga dibangun manual dari koleksi yang sudah
     * difilter, bukan ->paginate() langsung dari query builder.
     */
    /**
     * Laporan Hutang Supplier (Accounts Payable aging): sisa hutang tiap
     * faktur (grand_total - pembayaran aktif) dikelompokkan per supplier dan
     * per umur keterlambatan terhadap jatuh tempo.
     */
    public function hutang(Request $request)
    {
        $hariIni = now()->startOfDay();

        $faktur = DB::table('pembelian_bahan as p')
            ->leftJoin('kedatangan_bahan as k', 'k.id_kedatangan', '=', 'p.id_kedatangan')
            ->leftJoin('supplier as s', 's.id_supplier', '=', 'k.id_supplier')
            ->whereNull('p.deleted_at')
            ->selectRaw('p.id_pembelian, p.no_faktur, p.tgl_transaksi, p.jatuh_tempo_tgl, p.grand_total, s.id_supplier, s.nama_supplier,
                (SELECT COALESCE(SUM(h.nominal), 0) FROM pembayaran_hutang h WHERE h.id_pembelian = p.id_pembelian AND h.voided_at IS NULL) as dibayar')
            ->orderBy('p.tgl_transaksi')
            ->get()
            ->map(function ($f) use ($hariIni) {
                $f->sisa = round((float) $f->grand_total - (float) $f->dibayar, 2);
                $acuan = \Carbon\Carbon::parse($f->jatuh_tempo_tgl ?: $f->tgl_transaksi)->startOfDay();
                $f->hari_telat = $acuan->lt($hariIni) ? $acuan->diffInDays($hariIni) : 0;
                $f->bucket = match (true) {
                    $f->hari_telat === 0 => 'belum',
                    $f->hari_telat <= 30 => 'b30',
                    $f->hari_telat <= 60 => 'b60',
                    default => 'b60plus',
                };

                return $f;
            })
            ->filter(fn ($f) => $f->sisa > 0.01)
            ->values();

        $perSupplier = $faktur->groupBy(fn ($f) => $f->nama_supplier ?? 'Supplier Umum')->map(function ($rows, $nama) {
            $row = ['nama' => $nama, 'id_supplier' => $rows->first()->id_supplier, 'total' => round($rows->sum('sisa'), 2), 'faktur' => $rows];
            foreach (['belum', 'b30', 'b60', 'b60plus'] as $b) {
                $row[$b] = round($rows->where('bucket', $b)->sum('sisa'), 2);
            }

            return $row;
        })->sortByDesc('total')->values();

        $total = [
            'total' => round($faktur->sum('sisa'), 2),
            'belum' => round($faktur->where('bucket', 'belum')->sum('sisa'), 2),
            'b30' => round($faktur->where('bucket', 'b30')->sum('sisa'), 2),
            'b60' => round($faktur->where('bucket', 'b60')->sum('sisa'), 2),
            'b60plus' => round($faktur->where('bucket', 'b60plus')->sum('sisa'), 2),
        ];

        return view('laporan.hutang', compact('perSupplier', 'total'));
    }

    public function piutang(Request $request)
    {
        $query = DB::table('transaksi')
            ->join('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
            ->where('transaksi.status_nota', '!=', 'Dibatalkan')
            ->where('transaksi.status_pembayaran', '!=', 'Lunas')
            ->select(
                'transaksi.id_transaksi',
                'transaksi.nomor_nota',
                'transaksi.tgl_order',
                'transaksi.total_bayar',
                'konsumen.nama_konsumen',
                'konsumen.id_konsumen'
            )
            ->selectSub(function ($q) {
                $q->from('pembayaran_riwayat')
                    ->selectRaw('COALESCE(SUM(nominal), 0)')
                    ->whereColumn('pembayaran_riwayat.id_transaksi', 'transaksi.id_transaksi');
            }, 'total_dibayar');

        // Default rekap HARI INI SAJA - sama seperti laporan lain.
                // §8I: tolak format tanggal yang rusak, bukan melempar 500.
                $request->validate(['tgl_dari' => 'nullable|date', 'tgl_sampai' => 'nullable|date']);
        $tglDari = $request->filled('tgl_dari') ? $request->tgl_dari : now()->format('Y-m-d');
        $tglSampai = $request->filled('tgl_sampai') ? $request->tgl_sampai : now()->format('Y-m-d');
        $query->where('transaksi.tgl_order', '>=', $tglDari . ' 00:00:00')
              ->where('transaksi.tgl_order', '<', \Carbon\Carbon::parse($tglSampai)->addDay()->format('Y-m-d') . ' 00:00:00');

        if ($request->filled('id_konsumen')) {
            $query->where('transaksi.id_konsumen', $request->id_konsumen);
        }

        $semua = $query->get()
            ->map(function ($t) {
                $t->piutang = max(0, $t->total_bayar - $t->total_dibayar);
                return $t;
            })
            ->filter(fn ($t) => $t->piutang > 0)
            ->values();

        $sortBy = $request->get('sort_by', 'tanggal_terbaru');
        $semua = match ($sortBy) {
            'piutang_terbesar' => $semua->sortByDesc('piutang')->values(),
            'piutang_terkecil' => $semua->sortBy('piutang')->values(),
            'nama_az' => $semua->sortBy('nama_konsumen')->values(),
            'tanggal_terlama' => $semua->sortBy('tgl_order')->values(),
            default => $semua->sortByDesc('tgl_order')->values(),
        };

        $jumlahNota = $semua->count();
        $totalPiutang = $semua->sum('piutang');

        $perPage = 15;
        $page = (int) $request->get('page', 1);
        $data = new \Illuminate\Pagination\LengthAwarePaginator(
            $semua->forPage($page, $perPage)->values(),
            $semua->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $konsumenList = DB::table('konsumen')->orderBy('nama_konsumen')->get(['id_konsumen', 'nama_konsumen', 'telepon']);

        return view('laporan.piutang', compact(
            'data', 'konsumenList', 'jumlahNota', 'totalPiutang', 'sortBy', 'tglDari', 'tglSampai'
        ));
    }

    /**
     * Laporan Stok Bahan Baku - evaluasi pengadaan/pembelanjaan bahan.
     * BEDA dari 6 laporan lain di atas: ini snapshot KONDISI SAAT INI
     * (stok masih ada berapa), bukan rekap transaksi periode tertentu -
     * jadi TIDAK pakai filter tanggal seperti laporan lain.
     *
     * Status per bahan dihitung dari stok vs stok_minimal (kolom yang
     * sudah ada di bahan_baku, dipakai juga di master/bahan untuk
     * menandai teks merah, tapi belum pernah ada laporan konsolidasinya):
     * - Habis          : stok <= 0
     * - Menipis        : stok > 0 dan stok <= stok_minimal (stok_minimal harus > 0 dulu)
     * - Belum Ada Batas: stok > 0 tapi stok_minimal belum diisi (<= 0) -
     *   dari audit data riil, 25 dari 26 bahan justru dalam kondisi ini,
     *   jadi ditandai eksplisit (bukan dianggap "Aman" begitu saja)
     *   supaya kelihatan mana yang batas minimalnya perlu diisi dulu.
     * - Aman           : stok > stok_minimal, stok_minimal > 0
     */
    public function stokBahan(Request $request)
    {
        $query = BahanBaku::with(['kategori', 'satuanStok', 'merk'])
            ->where('status', 'aktif');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_bahan', 'like', '%' . $request->search . '%')
                  ->orWhere('kode_bahan', 'like', '%' . $request->search . '%');
            });
        }
        if ($request->filled('id_kategori')) {
            $query->where('id_kategori', $request->id_kategori);
        }

        $semua = $query->get()->map(function ($b) {
            $b->status_stok = match (true) {
                $b->stok <= 0 => 'Habis',
                $b->stok_minimal > 0 && $b->stok <= $b->stok_minimal => 'Menipis',
                $b->stok_minimal <= 0 => 'Belum Ada Batas',
                default => 'Aman',
            };
            return $b;
        });

        if ($request->filled('status_stok')) {
            $semua = $semua->where('status_stok', $request->status_stok)->values();
        }

        // Urutan default: yang paling butuh perhatian (Habis) tampil
        // duluan, supaya evaluasi pengadaan langsung fokus ke situ.
        $urutanStatus = ['Habis' => 0, 'Menipis' => 1, 'Belum Ada Batas' => 2, 'Aman' => 3];
        $semua = $semua->sortBy(fn ($b) => [$urutanStatus[$b->status_stok], $b->nama_bahan])->values();

        $jumlahHabis = $semua->where('status_stok', 'Habis')->count();
        $jumlahMenipis = $semua->where('status_stok', 'Menipis')->count();
        $jumlahBelumAdaBatas = $semua->where('status_stok', 'Belum Ada Batas')->count();
        $jumlahBahan = $semua->count();

        $perPage = 20;
        $page = (int) $request->get('page', 1);
        $data = new \Illuminate\Pagination\LengthAwarePaginator(
            $semua->forPage($page, $perPage)->values(),
            $semua->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $kategoriList = Kategori::orderBy('nama_kategori')->get();

        return view('laporan.stok-bahan', compact(
            'data', 'kategoriList', 'jumlahBahan', 'jumlahHabis', 'jumlahMenipis', 'jumlahBelumAdaBatas'
        ));
    }

    /**
     * Laporan HPP & Margin - membaca transaksi_detail.hpp_snapshot yang
     * SUDAH dihitung otomatis sejak PenjualanController::store() (rumus:
     * qty_pakai per bahan di BOM x bahan_baku.harga_rata_rata, dijumlah
     * seluruh baris BOM produk itu), tapi sebelum ini datanya tidak pernah
     * ditampilkan di mana pun - kasir/admin tidak punya cara melihat margin
     * keuntungan sama sekali walau datanya sudah tersimpan tiap transaksi.
     *
     * KETERBATASAN yang perlu disadari (bukan bug, batas data yang memang
     * tersedia): hpp_snapshot HANYA mencakup biaya BAHAN BAKU sesuai BOM -
     * belum termasuk ongkos finishing/jasa/tenaga kerja/overhead (aplikasi
     * ini tidak punya pencatatan biaya untuk komponen-komponen itu). Baris
     * jasa juga selalu hpp_snapshot=0 (tidak berbasis BOM sama sekali).
     * Margin yang ditampilkan karena itu adalah "margin kotor atas bahan",
     * bukan margin bersih sesungguhnya - cukup jelas dari label & catatan
     * di halaman laporannya sendiri supaya tidak disalahpahami sebagai
     * laba bersih riil.
     */
    /**
     * Job Costing (LEVEL 2, bagian 24 dokumen audit): biaya aktual per nota =
     * HPP material saat order (hpp_snapshot) + biaya rework yang sudah
     * diproses + biaya waste terkait item nota itu. Belum mencakup tenaga
     * kerja, mesin, dan overhead karena aplikasi belum mencatatnya.
     */
    public function jobCosting(Request $request)
    {
                // §8I: tolak format tanggal yang rusak, bukan melempar 500.
                $request->validate(['tgl_dari' => 'nullable|date', 'tgl_sampai' => 'nullable|date']);
        $tglDari = $request->filled('tgl_dari') ? $request->tgl_dari : now()->startOfMonth()->format('Y-m-d');
        $tglSampai = $request->filled('tgl_sampai') ? $request->tgl_sampai : now()->format('Y-m-d');

        $data = DB::table('transaksi as t')
            ->join('transaksi_detail as d', 'd.id_transaksi', '=', 't.id_transaksi')
            ->leftJoin('konsumen as k', 'k.id_konsumen', '=', 't.id_konsumen')
            ->where('t.status_nota', '!=', 'Dibatalkan')
            ->where('t.tgl_order', '>=', $tglDari . ' 00:00:00')
            ->where('t.tgl_order', '<', \Carbon\Carbon::parse($tglSampai)->addDay()->format('Y-m-d') . ' 00:00:00')
            ->groupBy('t.id_transaksi', 't.nomor_nota', 't.tgl_order', 'k.nama_konsumen')
            ->selectRaw("t.id_transaksi, t.nomor_nota, t.tgl_order, k.nama_konsumen,
                SUM(" . \App\Support\OmzetBersih::sql() . ") as omzet, SUM(d.hpp_snapshot) as hpp,
                (SELECT COALESCE(SUM(r.biaya_material), 0) FROM rework r JOIN transaksi_detail d2 ON d2.id_detail = r.id_detail
                    WHERE d2.id_transaksi = t.id_transaksi AND r.status = 'Selesai') as biaya_rework,
                (SELECT COALESCE(SUM(w.biaya), 0) FROM waste_log w JOIN transaksi_detail d3 ON d3.id_detail = w.id_detail
                    WHERE d3.id_transaksi = t.id_transaksi AND w.status = 'Diterapkan') as biaya_waste")
            ->orderByDesc('t.tgl_order')
            ->get()
            ->map(function ($r) {
                $r->biaya_aktual = round((float) $r->hpp + (float) $r->biaya_rework + (float) $r->biaya_waste, 2);
                $r->laba = round((float) $r->omzet - $r->biaya_aktual, 2);
                $r->margin = (float) $r->omzet > 0 ? round($r->laba / (float) $r->omzet * 100, 1) : 0;
                $r->margin_estimasi = (float) $r->omzet > 0 ? round(((float) $r->omzet - (float) $r->hpp) / (float) $r->omzet * 100, 1) : 0;

                return $r;
            });

        $total = [
            'omzet' => $data->sum('omzet'), 'hpp' => $data->sum('hpp'),
            'rework' => $data->sum('biaya_rework'), 'waste' => $data->sum('biaya_waste'),
            'aktual' => $data->sum('biaya_aktual'), 'laba' => $data->sum('laba'),
        ];
        $total['margin'] = $total['omzet'] > 0 ? round($total['laba'] / $total['omzet'] * 100, 1) : 0;

        return view('laporan.job-costing', compact('data', 'total', 'tglDari', 'tglSampai'));
    }

    public function hpp(Request $request)
    {
                // §8I: tolak format tanggal yang rusak, bukan melempar 500.
                $request->validate(['tgl_dari' => 'nullable|date', 'tgl_sampai' => 'nullable|date']);
        $tglDari = $request->filled('tgl_dari') ? $request->tgl_dari : now()->format('Y-m-d');
        $tglSampai = $request->filled('tgl_sampai') ? $request->tgl_sampai : now()->format('Y-m-d');

        $query = DB::table('transaksi_detail')
            ->join('transaksi', 'transaksi_detail.id_transaksi', '=', 'transaksi.id_transaksi')
            ->leftJoin('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
            ->leftJoin('jasa', 'transaksi_detail.id_jasa', '=', 'jasa.id_jasa')
            ->leftJoin('mesin', 'produk.id_mesin', '=', 'mesin.id_mesin')
            ->where('transaksi.status_nota', '!=', 'Dibatalkan')
            ->where('transaksi.tgl_order', '>=', $tglDari . ' 00:00:00')
            ->where('transaksi.tgl_order', '<', \Carbon\Carbon::parse($tglSampai)->addDay()->format('Y-m-d') . ' 00:00:00');

        if ($request->filled('id_mesin')) {
            $query->where('produk.id_mesin', $request->id_mesin);
        }

        $resumeQuery = clone $query;
        $jumlahItem = $resumeQuery->count();
        $omzetBersih = \App\Support\OmzetBersih::sql('transaksi_detail', 'transaksi');
        $totalOmzet = (float) (clone $query)->sum(DB::raw($omzetBersih));
        $totalHpp = (clone $query)->sum('transaksi_detail.hpp_snapshot');
        $totalMargin = $totalOmzet - $totalHpp;
        $marginPersen = $totalOmzet > 0 ? ($totalMargin / $totalOmzet) * 100 : 0;

        $breakdownDivisi = (clone $query)
            ->select(
                DB::raw("COALESCE(mesin.nama_mesin, 'Lainnya') as divisi"),
                DB::raw("SUM({$omzetBersih}) as total_omzet"),
                DB::raw('SUM(transaksi_detail.hpp_snapshot) as total_hpp')
            )
            ->groupBy(DB::raw("COALESCE(mesin.nama_mesin, 'Lainnya')"))
            ->orderByDesc('total_omzet')
            ->get()
            ->map(function ($d) {
                $d->margin = $d->total_omzet - $d->total_hpp;
                $d->margin_persen = $d->total_omzet > 0 ? ($d->margin / $d->total_omzet) * 100 : 0;
                return $d;
            });

        $data = $query
            ->select(
                'transaksi_detail.id_detail',
                'transaksi.nomor_nota',
                'transaksi.tgl_order',
                DB::raw("COALESCE(produk.nama_produk, jasa.nama_jasa, transaksi_detail.nama_file) as nama_item"),
                DB::raw("COALESCE(mesin.nama_mesin, 'Lainnya') as divisi"),
                'transaksi_detail.qty',
                DB::raw("{$omzetBersih} as subtotal"),
                'transaksi_detail.hpp_snapshot'
            )
            ->orderByDesc('transaksi.tgl_order')
            ->paginate(20)
            ->withQueryString();

        $mesinList = DB::table('mesin')->orderBy('nama_mesin')->get();

        return view('laporan.hpp', compact(
            'data', 'totalOmzet', 'totalHpp', 'totalMargin', 'marginPersen', 'jumlahItem',
            'breakdownDivisi', 'mesinList', 'tglDari', 'tglSampai'
        ));
    }
}
