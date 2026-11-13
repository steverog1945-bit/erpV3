<?php

namespace App\Http\Controllers;

use App\Services\KasSesiService;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Dashboard dulunya SATU tampilan sama untuk semua role, termasuk angka
     * uang (nilai order, kas masuk, piutang) yang seharusnya cuma boleh
     * dibaca Administrator & Kasir. Sekarang dipecah per role:
     *
     * - Administrator, Kasir       -> dashboard penuh (semua nominal & aksi).
     * - Operator Desain            -> dashboard khusus rute /dashboard/desain
     *   (role.only, hanya Operator Desain + Administrator) - mirip dashboard
     *   utama TAPI TIDAK ADA satu pun angka Rupiah (nilai order, kas,
     *   piutang): role ini cuma input order & lihat status, tidak pernah
     *   menyentuh transaksi/nominal.
     * - Operator Produksi ("operator cetak") -> dashboard khusus rute
     *   /dashboard/produksi (role.only) - SPK-sentris: semua hitungan &
     *   daftar kerja dibatasi ke mesin/divisi tanggung jawab karyawan yang
     *   login (pola sama dengan SpkController::index()), tanpa nominal.
     * - Gudang                     -> ringkasan stok bahan baku (habis/
     *   menipis/belum ada batas minimal) & kedatangan bahan terbaru, tanpa
     *   nominal (harga rata-rata TIDAK ditampilkan).
     *
     * Semua tetap murni baca data yang sudah ada - tidak ada tabel/kolom
     * baru, tidak ada angka statis/dummy.
     */
    public function index()
    {
        $user = auth()->user();
        $namaRole = optional($user->role)->nama_role;

        if ($namaRole === 'Owner') {
            return redirect()->route('audit-owner.index');
        }

        return match ($namaRole) {
            // Halaman role punya rute & gemboknya sendiri (role.only) - /dashboard
            // mengalihkan ke sana supaya link "Dashboard" di sidebar tetap satu pintu.
            'Operator Desain' => redirect()->route('dashboard.desain'),
            'Operator Produksi' => redirect()->route('dashboard.produksi'),
            'Gudang' => $this->dashboardGudang(),
            default => $this->dashboardFull(), // Administrator, Kasir, & role lain yang belum diklasifikasi eksplisit
        };
    }

    /**
     * API JSON untuk KPI dashboard real-time (polling dari blade via fetch).
     * Hanya mengembalikan angka kunci - tanpa HTML - supaya ringan dan cepat.
     */
    /**
     * Piutang global (dipakai dashboard & API KPI) - SUM + COUNT digabung
     * SATU scan (dulu dua query identik). Sisa tagihan (total - yang sudah
     * dibayar), bukan total nota: nota DP sudah sebagian lunas.
     *
     * Cache 60 detik: angka ini global (tidak per-user/filter) dan query-nya
     * O(N) atas nota bukan-Lunas - di skala 756 ribu baris 112-160ms, dan
     * tumbuh linear. Cold cache = query yang sama persis seperti sebelumnya
     * (hasil identik); warm cache tidak menyentuh tabel sama sekali. Angka
     * piutang boleh tertinggal maksimal 60 detik di layar.
     */
    private static function piutangRingkas(): array
    {
        return Cache::remember('ringkasan.piutang', 60, function () {
            $row = DB::table('transaksi')
                ->where('status_pembayaran', '!=', 'Lunas')
                ->where('status_nota', '!=', 'Dibatalkan')
                ->selectRaw('COALESCE(SUM(GREATEST(0, total_bayar - COALESCE((SELECT SUM(pr.nominal) FROM pembayaran_riwayat pr WHERE pr.id_transaksi = transaksi.id_transaksi), 0))), 0) as total, COUNT(*) as jumlah')
                ->first();

            return ['total' => (float) ($row->total ?? 0), 'jumlah' => (int) ($row->jumlah ?? 0)];
        });
    }

    public function kpiApi()
    {
        $today = now()->format('Y-m-d');
        [$mulaiHariIni, $akhirHariIni] = self::rentangHari($today);

        $orderHariIni = DB::table('transaksi')->whereBetween('tgl_order', [$mulaiHariIni, $akhirHariIni])->where('status_nota', '!=', 'Dibatalkan')->count();

        $statusSpk = DB::table('transaksi_detail')
            ->select('status_spk', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('status_spk')
            ->pluck('jumlah', 'status_spk');
        $spkAntre = (int) ($statusSpk['Antre'] ?? 0);
        $spkProses = (int) (($statusSpk['Desain'] ?? 0) + ($statusSpk['Cetak'] ?? 0));
        $spkSelesai = (int) ($statusSpk['Selesai'] ?? 0);

        $urgentAktif = DB::table('transaksi_detail')
            ->where('status_urgent', 1)
            ->whereNotIn('status_spk', ['Selesai', 'Batal'])
            ->count();

        $kasAktifSemua = KasSesiService::aktif();
        $kasAktif = $kasAktifSemua ? (bool) $kasAktifSemua : false;

        // Angka Rupiah HANYA untuk role yang berhak lihat nominal
        // (Administrator & Kasir). Role lain menerima hitungan/count saja -
        // dulu endpoint ini membocorkan piutang, nilai order & kas masuk ke
        // semua yang login (ada di always_allow).
        $namaRole = optional(auth()->user()->role)->nama_role;
        $nominalBoleh = in_array($namaRole, ['Administrator', 'Kasir'], true);

        $payload = [
            'order_hari_ini' => (int) $orderHariIni,
            'spk_antre' => $spkAntre,
            'spk_proses' => $spkProses,
            'spk_selesai' => $spkSelesai,
            'urgent_aktif' => (int) $urgentAktif,
            'kas_aktif' => $kasAktif,
            'updated_at' => now()->toDateTimeString(),
        ];

        if ($nominalBoleh) {
            $piutangRingkas = self::piutangRingkas();
            $nilaiOrderHariIni = (float) DB::table('transaksi')->whereBetween('tgl_order', [$mulaiHariIni, $akhirHariIni])->where('status_nota', '!=', 'Dibatalkan')->sum('total_bayar');

            $payload['piutang'] = $piutangRingkas['total'];
            $payload['piutang_count'] = $piutangRingkas['jumlah'];
            $payload['nilai_order_hari_ini'] = $nilaiOrderHariIni;
            $payload['kas_masuk_hari_ini'] = (float) self::uangMasuk($mulaiHariIni, $akhirHariIni);
        } else {
            $payload['piutang_count'] = self::piutangRingkas()['jumlah'];
        }

        return response()->json($payload);
    }

    /**
     * Dashboard penuh - Administrator & Kasir (satu-satunya role yang boleh
     * lihat nominal, sesuai instruksi eksplisit). Logika sama persis dengan
     * versi sebelum dashboard dipecah per role.
     */
    private function dashboardFull()
    {
        $today = now()->format('Y-m-d');

        // Perf dataset besar: whereDate() (DATE(kolom)=...) mematikan index
        // (tgl_order,status_nota) karena fungsi di atas kolom - diganti
        // rentang sargable [hari, besok). Rentang lebar tanpa batas atas
        // sengaja dibatasi sampai "besok" karena tgl_order tidak mungkin
        // di masa depan untuk penghitungan ini.
        [$mulaiHariIni, $akhirHariIni] = self::rentangHari($today);
        [$mulaiKemarin, $akhirKemarin] = self::rentangHari($kemarin = now()->subDay()->format('Y-m-d'));

        // Piutang: lihat piutangRingkas() - di-cache 60 detik, angka global.
        // $piutang TETAP float (dipakai langsung oleh format angka di blade).
        $piutangRingkas = self::piutangRingkas();
        $piutang = $piutangRingkas['total'];

        $orderHariIni = DB::table('transaksi')->whereBetween('tgl_order', [$mulaiHariIni, $akhirHariIni])->where('status_nota', '!=', 'Dibatalkan')->count();
        $nilaiOrderHariIni = DB::table('transaksi')->whereBetween('tgl_order', [$mulaiHariIni, $akhirHariIni])->where('status_nota', '!=', 'Dibatalkan')->sum('total_bayar');
        $orderKemarin = DB::table('transaksi')->whereBetween('tgl_order', [$mulaiKemarin, $akhirKemarin])->where('status_nota', '!=', 'Dibatalkan')->count();

        $statusSpk = DB::table('transaksi_detail')
            ->select('status_spk', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('status_spk')
            ->pluck('jumlah', 'status_spk');

        $spkAntre = (int) ($statusSpk['Antre'] ?? 0);
        $spkProses = (int) (($statusSpk['Desain'] ?? 0) + ($statusSpk['Cetak'] ?? 0));
        // Dipecah lagi untuk alur 5 tahap di dashboard (Antre -> Desain -> Produksi -> Collecting -> Selesai)
        $spkDesain = (int) ($statusSpk['Desain'] ?? 0);
        $spkCetak = (int) ($statusSpk['Cetak'] ?? 0);
        $spkSelesai = (int) ($statusSpk['Selesai'] ?? 0);

        $urgentAktif = DB::table('transaksi_detail')
            ->where('status_urgent', 1)
            ->whereNotIn('status_spk', ['Selesai', 'Batal'])
            ->count();

        [$notaLengkap, $notaBelumLengkap] = $this->ringkasanCollecting();

        $piutangCount = (int) $piutangRingkas['jumlah'];

        // Total nota aktif (non-dibatalkan) — dipakai sebagai angka penyebut
        // untuk menampilkan persentase nota lunas di kartu Kinerja Pembayaran.
        // Murni query baca tambahan, tidak mengubah query/perhitungan lain.
        $totalNotaAll = (int) DB::table('transaksi')->where('status_nota', '!=', 'Dibatalkan')->count();

        // Beban SPK aktif per mesin (antre/desain/cetak). Dipakai panel
        // "Beban Produksi per Mesin" di dashboard — angka nyata, bukan contoh.
        $bebanMesin = DB::table('transaksi_detail')
            ->join('transaksi', 'transaksi_detail.id_transaksi', '=', 'transaksi.id_transaksi')
            ->join('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
            ->leftJoin('mesin', 'produk.id_mesin', '=', 'mesin.id_mesin')
            ->where('transaksi.status_nota', '!=', 'Dibatalkan')
            ->whereIn('transaksi_detail.status_spk', ['Antre', 'Desain', 'Cetak'])
            ->select(DB::raw("COALESCE(mesin.nama_mesin, 'Lainnya') as nama_mesin"), DB::raw('COUNT(*) as jumlah'))
            ->groupBy('produk.id_mesin', 'mesin.nama_mesin')
            ->orderByDesc('jumlah')
            ->limit(4)
            ->get();
        $bebanMesinMaks = max(1, (int) $bebanMesin->max('jumlah'));
        $kasMasukHariIni = self::uangMasuk($mulaiHariIni, $akhirHariIni);

        $kasAktifSemua = KasSesiService::aktif();
        $kasAktif = ($kasAktifSemua && (int) $kasAktifSemua->id_user === (int) auth()->id()) ? $kasAktifSemua : null;

        $urgentList = DB::table('transaksi_detail')
            ->join('transaksi', 'transaksi_detail.id_transaksi', '=', 'transaksi.id_transaksi')
            ->where('transaksi_detail.status_urgent', 1)
            ->whereNotIn('transaksi_detail.status_spk', ['Selesai', 'Batal'])
            ->select('transaksi_detail.id_detail', 'transaksi_detail.nama_file', 'transaksi_detail.status_spk')
            // Nama pelanggan lewat subquery: dengan JOIN, MySQL mengurutkan seluruh nota dulu (filesort).
            ->selectRaw('(select k.nama_konsumen from konsumen k where k.id_konsumen = transaksi.id_konsumen) as nama_konsumen')
            ->orderByDesc('transaksi_detail.id_detail')
            ->limit(5)
            ->get();

        $orderTerbaru = DB::table('transaksi')
            ->select('transaksi.id_transaksi', 'transaksi.nomor_nota', 'transaksi.tgl_order', 'transaksi.total_bayar', 'transaksi.status_pembayaran', 'transaksi.status_nota')
            // Nama pelanggan lewat subquery: dengan JOIN, MySQL mengurutkan seluruh nota dulu (filesort).
            ->selectRaw('(select k.nama_konsumen from konsumen k where k.id_konsumen = transaksi.id_konsumen) as nama_konsumen')
            ->orderByDesc('transaksi.id_transaksi')
            ->limit(5)
            ->get();

        // Pembanding hari kemarin + tren 7 hari terakhir (jumlah order & kas masuk per hari).
        $kasMasukKemarin = self::uangMasuk($mulaiKemarin, $akhirKemarin);
        $awal7 = now()->subDays(6)->startOfDay();
        $orderPerHari = DB::table('transaksi')->where('tgl_order', '>=', $awal7)->where('tgl_order', '<', $akhirHariIni)->where('status_nota', '!=', 'Dibatalkan')
            ->selectRaw('DATE(tgl_order) as tgl, COUNT(*) as n')->groupBy('tgl')->pluck('n', 'tgl');
        $kasPerHari = DB::table('pembayaran_riwayat')->where('tgl_bayar', '>=', $awal7)
            ->where('tgl_bayar', '<', $akhirHariIni)->where('metode', '!=', 'Deposit')
            ->selectRaw('DATE(tgl_bayar) as tgl, SUM(nominal) as total')->groupBy('tgl')->pluck('total', 'tgl');
        $setoranPerHari = self::setoranDeposit()->where('tgl_deposit', '>=', $awal7->toDateString())->where('tgl_deposit', '<', $akhirHariIni)
            ->selectRaw('tgl_deposit as tgl, SUM(nominal) as total')->groupBy('tgl_deposit')->pluck('total', 'tgl');
        foreach ($setoranPerHari as $tgl => $nominal) {
            $kasPerHari[$tgl] = (float) ($kasPerHari[$tgl] ?? 0) + (float) $nominal;
        }
        $tren = collect(range(6, 0))->map(function ($i) use ($orderPerHari, $kasPerHari) {
            $d = now()->subDays($i);

            return ['label' => $d->translatedFormat('D'), 'tgl' => $d->translatedFormat('d M'), 'order' => (int) ($orderPerHari[$d->format('Y-m-d')] ?? 0), 'kas' => (float) ($kasPerHari[$d->format('Y-m-d')] ?? 0)];
        });

        // Pekerjaan yang menunggu tindakan (sama dengan lonceng notifikasi, khusus role ini).
        $perluTindakan = \App\Services\NotifikasiService::untuk(auth()->user())['items'];

        // Strip "Perhatian Hari Ini" - HANYA muncul bila ada yang perlu dicek;
        // angka baca saja (nota batal, rework, waste abnormal, selisih kas).
        $anomBatal = DB::table('transaksi')->whereBetween('tgl_order', [$mulaiHariIni, $akhirHariIni])->where('status_nota', 'Dibatalkan')->count();
        $anomRework = DB::table('rework')->whereDate('created_at', $today)->where('status', '!=', 'Selesai')->count();
        $anomWaste = DB::table('waste_log')->where('tanggal', $today)->where('jenis', 'Abnormal')->count();
        $anomSelisihCount = DB::table('kas_harian')->where('tgl_kas', $today)->whereRaw('ABS(COALESCE(selisih,0)) > 0')->count();
        $anomSelisihNilai = (float) DB::table('kas_harian')->where('tgl_kas', $today)->sum(DB::raw('ABS(COALESCE(selisih,0))'));
        $anomali = collect([
            $anomBatal > 0 ? ['label' => $anomBatal . ' nota dibatalkan hari ini', 'route' => 'penjualan.riwayat', 'param' => [], 'tone' => 'rose'] : null,
            $anomRework > 0 ? ['label' => $anomRework . ' rework belum selesai', 'route' => 'qc.index', 'param' => [], 'tone' => 'amber'] : null,
            $anomWaste > 0 ? ['label' => $anomWaste . ' waste abnormal hari ini', 'route' => 'waste.index', 'param' => [], 'tone' => 'amber'] : null,
            $anomSelisihCount > 0 ? ['label' => 'selisih kas ' . $anomSelisihCount . ' sesi (Rp ' . number_format($anomSelisihNilai, 0, ',', '.') . ')', 'route' => 'kasir.kelola', 'param' => [], 'tone' => 'rose'] : null,
        ])->filter()->values();

        return view('dashboard', compact(
            'orderKemarin', 'kasMasukKemarin', 'tren', 'perluTindakan', 'anomali',
            'orderHariIni', 'nilaiOrderHariIni',
            'spkAntre', 'spkProses', 'spkDesain', 'spkCetak', 'spkSelesai', 'urgentAktif',
            'notaLengkap', 'notaBelumLengkap', 'totalNotaAll', 'bebanMesin', 'bebanMesinMaks',
            'piutang', 'piutangCount', 'kasMasukHariIni', 'kasAktif',
            'urgentList', 'orderTerbaru'
        ));
    }

    /**
     * Builder data bersama untuk dashboard peran operasional (Desain &
     * Produksi). Cermin dari dashboard penuh - gauge KPI, siklus alur 5
     * tahap, beban produksi per mesin, panel perlu perhatian - TAPI tanpa
     * satu pun angka Rupiah: nilai order, kas, piutang & total_bayar tidak
     * pernah di-select/dikirim ke view. Query hanya membaca tabel yang
     * sudah ada (tidak ada perubahan skema/data).
     *
     * @param \Illuminate\Support\Collection|null $mesinDiizinkan  Bila diisi
     *   (penugasan mesin karyawan), semua hitungan SPK & daftar urgent dibatasi
     *   ke mesin tsb - pola sama dengan SpkController::index(). Administrator/
     *   karyawan tanpa penugasan mesin: null (lihat semua).
     * @param bool $hanyaAktif  true (dashboard Produksi) = hanya item dari nota
     *   berstatus 'Aktif'; false (dashboard Desain) = semua nota yang tidak
     *   dibatalkan (pola dashboard utama).
     */
    private function ringkasanOperasional($mesinDiizinkan = null, bool $hanyaAktif = false): array
    {
        $today = now()->format('Y-m-d');
        [$mulaiHariIni, $akhirHariIni] = self::rentangHari($today);
        [$mulaiKemarin, $akhirKemarin] = self::rentangHari(now()->subDay()->format('Y-m-d'));

        $orderHariIni = DB::table('transaksi')->whereBetween('tgl_order', [$mulaiHariIni, $akhirHariIni])->where('status_nota', '!=', 'Dibatalkan')->count();
        $orderKemarin = DB::table('transaksi')->whereBetween('tgl_order', [$mulaiKemarin, $akhirKemarin])->where('status_nota', '!=', 'Dibatalkan')->count();

        $baseSpk = DB::table('transaksi_detail')
            ->join('transaksi', 'transaksi_detail.id_transaksi', '=', 'transaksi.id_transaksi')
            ->join('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk');
        if ($hanyaAktif) {
            $baseSpk->where('transaksi.status_nota', 'Aktif');
        } else {
            $baseSpk->where('transaksi.status_nota', '!=', 'Dibatalkan');
        }
        if ($mesinDiizinkan) {
            $baseSpk->whereIn('produk.id_mesin', $mesinDiizinkan);
        }

        $statusSpk = (clone $baseSpk)
            ->select('transaksi_detail.status_spk', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('transaksi_detail.status_spk')
            ->pluck('jumlah', 'status_spk');

        $spkAntre = (int) ($statusSpk['Antre'] ?? 0);
        $spkDesain = (int) ($statusSpk['Desain'] ?? 0);
        $spkCetak = (int) ($statusSpk['Cetak'] ?? 0);
        $spkProses = $spkDesain + $spkCetak;
        $spkSelesai = (int) ($statusSpk['Selesai'] ?? 0);

        $urgentAktif = (clone $baseSpk)
            ->where('transaksi_detail.status_urgent', 1)
            ->whereNotIn('transaksi_detail.status_spk', ['Selesai', 'Batal'])
            ->count();

        [$notaLengkap, $notaBelumLengkap] = $this->ringkasanCollecting();

        // Penyebut persentase tahap Collecting & penyebut gauge selesai - murni
        // hitungan nota (bukan nominal). Sama seperti dashboard utama.
        $totalNotaAll = (int) DB::table('transaksi')->where('status_nota', '!=', 'Dibatalkan')->count();

        // Beban SPK aktif per mesin (antre/desain/cetak) - angka nyata, bukan
        // contoh. Dibatasi ke mesin tanggung jawab bila ada penugasan.
        $bebanMesin = DB::table('transaksi_detail')
            ->join('transaksi', 'transaksi_detail.id_transaksi', '=', 'transaksi.id_transaksi')
            ->join('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
            ->leftJoin('mesin', 'produk.id_mesin', '=', 'mesin.id_mesin')
            ->when($hanyaAktif, fn ($q) => $q->where('transaksi.status_nota', '=', 'Aktif'), fn ($q) => $q->where('transaksi.status_nota', '!=', 'Dibatalkan'))
            ->when($mesinDiizinkan, fn ($q) => $q->whereIn('produk.id_mesin', $mesinDiizinkan))
            ->whereIn('transaksi_detail.status_spk', ['Antre', 'Desain', 'Cetak'])
            ->select(DB::raw("COALESCE(mesin.nama_mesin, 'Lainnya') as nama_mesin"), DB::raw('COUNT(*) as jumlah'))
            ->groupBy('produk.id_mesin', 'mesin.nama_mesin')
            ->orderByDesc('jumlah')
            ->limit(4)
            ->get();
        $bebanMesinMaks = max(1, (int) $bebanMesin->max('jumlah'));

        $urgentList = (clone $baseSpk)
            ->where('transaksi_detail.status_urgent', 1)
            ->whereNotIn('transaksi_detail.status_spk', ['Selesai', 'Batal'])
            ->select('transaksi_detail.id_detail', 'transaksi_detail.nama_file', 'transaksi_detail.status_spk')
            // Nama pelanggan lewat subquery: dengan JOIN, MySQL mengurutkan seluruh nota dulu (filesort).
            ->selectRaw('(select k.nama_konsumen from konsumen k where k.id_konsumen = transaksi.id_konsumen) as nama_konsumen')
            ->orderByDesc('transaksi_detail.id_detail')
            ->limit(5)
            ->get();

        // Pekerjaan yang menunggu tindakan (sama dengan lonceng notifikasi,
        // sudah difilter per izin role oleh NotifikasiService - aman dipakai).
        $perluTindakan = \App\Services\NotifikasiService::untuk(auth()->user())['items'];

        // Strip "Perhatian Hari Ini" - TANPA nominal: selisih kas (memuat Rupiah)
        // sengaja tidak disertakan untuk role ini. Setiap butir hanya tampil bila
        // role memang boleh membuka halaman tujuannya.
        $anomBatal = DB::table('transaksi')->whereBetween('tgl_order', [$mulaiHariIni, $akhirHariIni])->where('status_nota', 'Dibatalkan')->count();
        $anomRework = DB::table('rework')->whereDate('created_at', $today)->where('status', '!=', 'Selesai')->count();
        $anomWaste = DB::table('waste_log')->where('tanggal', $today)->where('jenis', 'Abnormal')->count();
        $anomali = collect([
            $anomBatal > 0 ? ['label' => $anomBatal . ' nota dibatalkan hari ini', 'route' => 'penjualan.riwayat', 'param' => [], 'tone' => 'rose'] : null,
            $anomRework > 0 ? ['label' => $anomRework . ' rework belum selesai', 'route' => 'qc.index', 'param' => [], 'tone' => 'amber'] : null,
            $anomWaste > 0 ? ['label' => $anomWaste . ' waste abnormal hari ini', 'route' => 'waste.index', 'param' => [], 'tone' => 'amber'] : null,
        ])->filter()->values()
            ->filter(function ($a) {
                try {
                    return \App\Support\JalurLanjut::boleh(route($a['route'], $a['param']));
                } catch (\Throwable $e) {
                    return false;
                }
            })->values();

        return compact(
            'orderHariIni', 'orderKemarin',
            'spkAntre', 'spkDesain', 'spkCetak', 'spkProses', 'spkSelesai', 'urgentAktif',
            'notaLengkap', 'notaBelumLengkap', 'totalNotaAll', 'bebanMesin', 'bebanMesinMaks',
            'urgentList', 'perluTindakan', 'anomali'
        );
    }

    /**
     * Halaman dashboard Operator Desain - rute /dashboard/desain, dijaga
     * role.only:Operator Desain (Administrator lolos untuk inspeksi).
     * TANPA satu pun angka Rupiah; status pembayaran hanya badge Lunas/DP/
     * Belum Bayar. $orderTerbaru SENGAJA tidak meng-select total_bayar sama
     * sekali (bukan cuma disembunyikan di view) supaya tidak ada jalan
     * nominal itu ikut kebawa ke view ini.
     */
    public function desain()
    {
        $data = $this->ringkasanOperasional(null, false);

        $orderTerbaru = DB::table('transaksi')
            ->select('transaksi.id_transaksi', 'transaksi.nomor_nota', 'transaksi.tgl_order', 'transaksi.status_pembayaran', 'transaksi.status_nota')
            // Nama pelanggan lewat subquery: dengan JOIN, MySQL mengurutkan seluruh nota dulu (filesort).
            ->selectRaw('(select k.nama_konsumen from konsumen k where k.id_konsumen = transaksi.id_konsumen) as nama_konsumen')
            ->orderByDesc('transaksi.id_transaksi')
            ->limit(6)
            ->get();

        return view('dashboard-desain', array_merge($data, compact('orderTerbaru')));
    }

    /**
     * Halaman dashboard Operator Produksi ("operator cetak") - rute
     * /dashboard/produksi, dijaga role.only:Operator Produksi (Administrator
     * lolos untuk inspeksi). SPK-sentris: semua hitungan dibatasi ke
     * mesin/divisi tanggung jawab karyawan yang login (pola sama persis
     * dengan SpkController::index()). Tanpa nominal sama sekali.
     */
    public function produksi()
    {
        $user = auth()->user();
        $karyawan = optional($user)->karyawan;
        $mesinDiizinkan = null;
        $namaMesinDiizinkan = null;
        if ($karyawan) {
            $mesinKaryawan = $karyawan->mesin()->get();
            if ($mesinKaryawan->isNotEmpty()) {
                $mesinDiizinkan = $mesinKaryawan->pluck('id_mesin');
                $namaMesinDiizinkan = $mesinKaryawan->pluck('nama_mesin');
            }
        }

        $data = $this->ringkasanOperasional($mesinDiizinkan, true);

        // Daftar SPK yang PERLU DIKERJAKAN, langsung tersaji (bukan cuma
        // angka) - Antre dulu (siap diambil), baru Desain/Cetak yang
        // urgent (sedang dikerjakan tapi butuh perhatian).
        $spkPerluDikerjakan = DB::table('transaksi_detail')
            ->join('transaksi', 'transaksi_detail.id_transaksi', '=', 'transaksi.id_transaksi')
            ->join('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
            ->leftJoin('mesin', 'produk.id_mesin', '=', 'mesin.id_mesin')
            ->where('transaksi.status_nota', 'Aktif')
            ->whereIn('transaksi_detail.status_spk', ['Antre', 'Desain', 'Cetak'])
            ->when($mesinDiizinkan, fn ($q) => $q->whereIn('produk.id_mesin', $mesinDiizinkan))
            ->select(
                'transaksi_detail.id_detail', 'transaksi_detail.nama_file', 'transaksi_detail.qty',
                'transaksi_detail.status_spk', 'transaksi_detail.status_urgent',
                'transaksi.nomor_nota',
                DB::raw('(select k.nama_konsumen from konsumen k where k.id_konsumen = transaksi.id_konsumen) as nama_konsumen'),
                DB::raw("COALESCE(mesin.nama_mesin, 'Lainnya') as divisi")
            )
            ->orderByRaw("FIELD(transaksi_detail.status_spk, 'Antre', 'Cetak', 'Desain')")
            ->orderByDesc('transaksi_detail.status_urgent')
            ->orderBy('transaksi_detail.id_detail')
            ->limit(10)
            ->get();

        return view('dashboard-produksi', array_merge($data, compact('spkPerluDikerjakan', 'namaMesinDiizinkan')));
    }

    /**
     * Gudang - ringkasan stok bahan baku (status sama seperti Laporan Stok
     * Bahan Baku) & kedatangan bahan terbaru. Harga rata-rata SENGAJA
     * tidak diambil/ditampilkan (nominal).
     */
    private function dashboardGudang()
    {
        $bahan = DB::table('bahan_baku')
            ->where('status', 'aktif')
            ->select('id_bahan', 'nama_bahan', 'kode_bahan', 'stok', 'stok_minimal', 'satuan_stok')
            ->get()
            ->map(function ($b) {
                $b->status_stok = match (true) {
                    $b->stok <= 0 => 'Habis',
                    $b->stok_minimal > 0 && $b->stok <= $b->stok_minimal => 'Menipis',
                    $b->stok_minimal <= 0 => 'Belum Ada Batas',
                    default => 'Aman',
                };
                return $b;
            });

        $jumlahBahan = $bahan->count();
        $jumlahHabis = $bahan->where('status_stok', 'Habis')->count();
        $jumlahMenipis = $bahan->where('status_stok', 'Menipis')->count();
        $jumlahBelumAdaBatas = $bahan->where('status_stok', 'Belum Ada Batas')->count();

        // Yang paling butuh perhatian (Habis dulu, baru Menipis) - dipakai
        // untuk daftar langsung di dashboard.
        $urutanStatus = ['Habis' => 0, 'Menipis' => 1];
        $bahanPerluPerhatian = $bahan
            ->whereIn('status_stok', ['Habis', 'Menipis'])
            ->sortBy(fn ($b) => [$urutanStatus[$b->status_stok], $b->nama_bahan])
            ->take(8)
            ->values();

        $kedatanganTerbaru = DB::table('kedatangan_bahan')
            ->leftJoin('supplier', 'kedatangan_bahan.id_supplier', '=', 'supplier.id_supplier')
            ->select('kedatangan_bahan.id_kedatangan', 'kedatangan_bahan.no_surat_jalan', 'kedatangan_bahan.tgl_datang', 'kedatangan_bahan.status', 'supplier.nama_supplier')
            ->orderByDesc('kedatangan_bahan.id_kedatangan')
            ->limit(5)
            ->get();

        return view('dashboard-gudang', compact(
            'jumlahBahan', 'jumlahHabis', 'jumlahMenipis', 'jumlahBelumAdaBatas',
            'bahanPerluPerhatian', 'kedatanganTerbaru'
        ));
    }

    /**
     * Hardening P0-5: dulunya query ini JOIN transaksi+transaksi_detail lalu
     * GROUP BY id_transaksi TANPA batas tanggal/status sama sekali, hasilnya
     * di-fetch PENUH (satu baris per nota SEJAK HARI PERTAMA aplikasi
     * dipakai) ke Collection PHP hanya untuk dihitung jadi 2 angka - padahal
     * ini query yang paling sering dijalankan di seluruh aplikasi (Dashboard,
     * dibuka setiap login/refresh oleh semua role). Waktu muatnya memburuk
     * terus seiring bertambahnya total nota sepanjang umur bisnis. Diganti
     * agregat SQL 2-tingkat (subquery group-by lalu dijumlah) supaya yang
     * dikirim dari database ke PHP cuma 2 angka, bukan N baris - angka yang
     * dihasilkan PERSIS sama seperti rumus lama:
     * notaLengkap = jumlah nota yang item_kelar-nya == total_item;
     * notaBelumLengkap = sisanya. Tidak ada JOIN ke tabel transaksi sama
     * sekali karena id_transaksi sudah ada langsung di transaksi_detail -
     * join lama ke transaksi tidak pernah dipakai kolomnya, murni overhead.
     *
     * @return array{0: int, 1: int} [notaLengkap, notaBelumLengkap]
     */
    private function ringkasanCollecting(): array
    {
        // Cache 60 detik terhadap dua DISTINCT-count atas transaksi_detail
        // (tumbuh linear, 45-70ms di skala stress): angka GLOBAL, tidak
        // per-user/filter. Cold cache = dua query yang sama seperti sebelum,
        // hasil identik; boleh tertinggal maksimal 60 detik.
        return Cache::remember('ringkasan.collecting', 60, function () {
            // Dua hitungan berindeks, bukan GROUP BY seluruh item lalu dihitung lagi (tumbuh terus seiring nota).
            // Nota lengkap = nota yang punya item dan tidak satu pun itemnya masih berjalan.
            $totalNota = DB::table('transaksi_detail')->distinct()->count('id_transaksi');
            // Status berjalan ditulis positif (IN) supaya indeks status_spk terpakai; NOT IN memaksa pemindaian penuh.
            $notaBelum = DB::table('transaksi_detail')->whereIn('status_spk', ['Antre', 'Desain', 'Cetak', 'Pending'])->distinct()->count('id_transaksi');
            $notaLengkap = $totalNota - $notaBelum;

            return [$notaLengkap, $totalNota - $notaLengkap];
        });
    }

    /**
     * Perf (audit dataset besar): [awal hari, awal hari berikutnya) sebagai
     * pasangan batas sargable untuk menggantikan whereDate() (DATE(kolom)=...)
     * yang mematikan index pada kolom datetime. Bentuk [inklusif, eksklusif)
     * aman untuk tipe timestamp/dateTime dan tetap benar walau ada nilai
     * detik. Boundary pakai string 'Y-m-d H:i:s' - format yang MySQL pahami
     * sebagai datetime literal, tanpa menebak timezone aplikasi.
     */
    /** Setoran deposit uang baru (Tunai/Transfer). Kredit Nota Batal hanya memindah uang nota lama ke saldo pelanggan. */
    private static function setoranDeposit()
    {
        return DB::table('konsumen_deposit')->where('tipe', 'Setoran')->whereIn('metode_bayar', ['Tunai', 'Transfer']);
    }

    /**
     * Uang masuk dalam rentang [mulai, akhir): pembayaran nota selain dari saldo deposit (uang itu sudah masuk saat
     * setoran) + setoran deposit baru.
     */
    private static function uangMasuk(string $mulai, string $akhir): float
    {
        return (float) DB::table('pembayaran_riwayat')->whereBetween('tgl_bayar', [$mulai, $akhir])->where('metode', '!=', 'Deposit')->sum('nominal')
            + (float) self::setoranDeposit()->where('tgl_deposit', '>=', substr($mulai, 0, 10))->where('tgl_deposit', '<', substr($akhir, 0, 10))->sum('nominal');
    }

    private static function rentangHari(string $tanggal): array
    {
        return [
            $tanggal . ' 00:00:00',
            \Carbon\Carbon::parse($tanggal)->addDay()->format('Y-m-d') . ' 00:00:00',
        ];
    }
}
