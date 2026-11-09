<?php

namespace App\Http\Controllers;

use App\Services\PembayaranHutangService;
use App\Services\TarifJahitJerseyService;
use App\Services\UpahJahitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * JASA JAHIT BORONGAN (pengeluaran)
 *
 * 1. Penjahit: orang yang mengerjakan dan menerima bayaran borongan.
 * 2. Katalog tarif: harga borongan per satuan yang disepakati dengan penjahit, diisi manual.
 *    - Tarif biasa boleh ditautkan ke finishing (mis. "Jahit Keliling"). Baris nota yang finishing-nya
 *      tertaut muncul di form Catat, jadi kasir tidak mencari nomor nota di antara ratusan order.
 *    - Tarif jersey boleh diikat ke tipe lengan, tipe kerah, atau keduanya. Ongkos satu order jersey
 *      dihitung otomatis per baju dari tarif ini (aturan pemilihan ada di TarifJahitJerseyService).
 * 3. Catatan: siapa menjahit apa, untuk order mana, berapa banyak, dengan harga berapa. Harga dibekukan
 *    saat dicatat. Catatan tidak bisa diubah: kalau salah, hapus lalu catat ulang, supaya angka yang
 *    pernah dibayar tidak berubah diam-diam.
 */
class JasaJahitController extends Controller
{
    /** Status SPK nota yang jahitannya masih mungkin dikerjakan. */
    private const STATUS_MASIH_DIBUTUHKAN = ['Antre', 'Desain', 'Cetak', 'Pending'];

    public function index(Request $request)
    {
        $jenis = DB::table('jasa_jahit')
            ->leftJoin('satuan', 'jasa_jahit.id_satuan', '=', 'satuan.id_satuan')
            ->leftJoin('finishing', 'jasa_jahit.id_finishing', '=', 'finishing.id_finishing')
            ->leftJoin('jersey_sleeve_types as ls', 'ls.id', '=', 'jasa_jahit.jersey_sleeve_type_id')
            ->leftJoin('jersey_collar_types as kr', 'kr.id', '=', 'jasa_jahit.jersey_collar_type_id')
            ->leftJoinSub(
                DB::table('jasa_jahit_catat')->select('id_jasa_jahit', DB::raw('COUNT(*) as jumlah_catatan'))->groupBy('id_jasa_jahit'),
                'c', 'c.id_jasa_jahit', '=', 'jasa_jahit.id_jasa_jahit'
            )
            ->select(
                'jasa_jahit.*', 'satuan.nama_satuan', 'finishing.nama_finishing',
                'ls.name as nama_lengan', 'kr.name as nama_kerah',
                DB::raw('COALESCE(c.jumlah_catatan, 0) as jumlah_catatan')
            )
            ->orderByDesc('jasa_jahit.untuk_jersey')
            ->orderBy('jasa_jahit.nama_jahit')
            ->get();

        $penjahit = DB::table('penjahit as p')
            ->leftJoinSub(
                DB::table('jasa_jahit_catat')
                    ->whereNotNull('id_penjahit')
                    ->groupBy('id_penjahit')
                    ->selectRaw('id_penjahit, COUNT(*) jumlah_catatan, SUM(CASE WHEN tgl_catat >= ? THEN total ELSE 0 END) total_bulan_ini, SUM(CASE WHEN tgl_catat >= ? THEN qty ELSE 0 END) qty_bulan_ini, SUM(CASE WHEN id_kas_kecil IS NULL THEN total ELSE 0 END) belum_dibayar', [now()->startOfMonth()->toDateString(), now()->startOfMonth()->toDateString()]),
                'c', 'c.id_penjahit', '=', 'p.id_penjahit'
            )
            ->select('p.*', DB::raw('COALESCE(c.jumlah_catatan, 0) jumlah_catatan'), DB::raw('COALESCE(c.total_bulan_ini, 0) total_bulan_ini'), DB::raw('COALESCE(c.qty_bulan_ini, 0) qty_bulan_ini'), DB::raw('COALESCE(c.belum_dibayar, 0) belum_dibayar'))
            ->orderByDesc('p.aktif')
            ->orderBy('p.nama')
            ->get();

        $orderan = $this->orderanPerluJahit($jenis->pluck('id_finishing')->filter());
        $orderJersey = $this->orderJerseyTerbuka();

        // 100 catatan terbaru. Untuk rekap panjang ada menu Laporan.
        $riwayat = DB::table('jasa_jahit_catat as c')
            ->join('jasa_jahit as j', 'j.id_jasa_jahit', '=', 'c.id_jasa_jahit')
            ->leftJoin('penjahit as pj', 'pj.id_penjahit', '=', 'c.id_penjahit')
            ->leftJoin('transaksi_detail as d', 'd.id_detail', '=', 'c.id_transaksi_detail')
            ->leftJoin('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->leftJoin('jersey_orders as jo', 'jo.id', '=', 'c.id_jersey_order')
            ->leftJoin('users as u', 'u.id_user', '=', 'c.id_user')
            ->select('c.*', 'j.nama_jahit', 'pj.nama as nama_penjahit', 't.nomor_nota', 'd.nama_file',
                'jo.order_number', 'jo.customer_name', 'jo.team_name', 'u.username')
            ->orderBy('c.tgl_catat', 'desc')
            ->orderBy('c.id_jasa_jahit_catat', 'desc')
            ->limit(100)
            ->get();

        // Catatan yang belum dibayar, dikelompokkan per penjahit untuk modal Bayar Penjahit.
        $belumDibayar = DB::table('jasa_jahit_catat as c')
            ->join('jasa_jahit as j', 'j.id_jasa_jahit', '=', 'c.id_jasa_jahit')
            ->leftJoin('transaksi_detail as d', 'd.id_detail', '=', 'c.id_transaksi_detail')
            ->leftJoin('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->leftJoin('jersey_orders as jo', 'jo.id', '=', 'c.id_jersey_order')
            ->whereNotNull('c.id_penjahit')
            ->whereNull('c.id_kas_kecil')
            ->orderBy('c.tgl_catat')
            ->get(['c.id_jasa_jahit_catat', 'c.id_penjahit', 'c.tgl_catat', 'c.qty', 'c.total', 'j.nama_jahit', 'jo.order_number', 't.nomor_nota'])
            ->groupBy('id_penjahit');
        $totalBelumDibayar = (float) DB::table('jasa_jahit_catat')->whereNull('id_kas_kecil')->sum('total');
        $saldoKasKecil = PembayaranHutangService::saldoKasKecil();
        $bolehBayar = \App\Support\Izin::bolehKeluarkanKasKecil($request->user());

        $totalBulanIni = (float) DB::table('jasa_jahit_catat')->where('tgl_catat', '>=', now()->startOfMonth()->toDateString())->sum('total');

        $satuan = DB::table('satuan')->orderBy('nama_satuan')->get();
        $finishing = DB::table('finishing')->orderBy('nama_finishing')->get();
        $lengan = DB::table('jersey_sleeve_types')->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $kerah = DB::table('jersey_collar_types')->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        return view('jasa-jahit.index', compact(
            'jenis', 'penjahit', 'belumDibayar', 'totalBelumDibayar', 'saldoKasKecil', 'bolehBayar', 'orderan', 'orderJersey', 'riwayat', 'totalBulanIni', 'satuan', 'finishing', 'lengan', 'kerah'
        ));
    }

    /** Baris nota yang finishing-nya tertaut ke katalog dan belum selesai dikerjakan. */
    private function orderanPerluJahit($idsFinishing)
    {
        if ($idsFinishing->isEmpty()) {
            return collect();
        }

        return DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->leftJoin('finishing as f', 'f.id_finishing', '=', 'd.id_finishing')
            ->leftJoin('produk as p', 'p.id_produk', '=', 'd.id_produk')
            ->whereIn('d.id_finishing', $idsFinishing)
            ->whereIn('d.status_spk', self::STATUS_MASIH_DIBUTUHKAN)
            ->where('t.status_nota', '!=', 'Dibatalkan')
            ->orderBy('t.tgl_order', 'desc')
            ->select('d.id_detail', 'd.id_finishing', 'd.nama_file', 'd.qty', 'd.status_spk', 't.nomor_nota', 't.tgl_order',
                'f.nama_finishing', DB::raw('COALESCE(p.nama_produk, "-") as nama_produk'))
            ->get();
    }

    /**
     * Order jersey yang jahitannya mungkin masih perlu dibayar: belum diambil, atau diambil dalam 60 hari
     * terakhir (penjahit kadang dibayar setelah barang keluar).
     */
    private function orderJerseyTerbuka()
    {
        return DB::table('jersey_orders')
            ->where(function ($q) {
                $q->where('status', '!=', 'diambil')->orWhere('updated_at', '>=', now()->subDays(60));
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->limit(300)
            ->get(['id', 'order_number', 'customer_name', 'team_name', 'status', 'order_date']);
    }

    // ---------------------------------------------------------------- penjahit

    public function storePenjahit(Request $request)
    {
        $data = $request->validate([
            'nama'       => 'required|max:100|unique:penjahit,nama',
            'telepon'    => 'nullable|max:30',
            'keterangan' => 'nullable|max:1000',
        ], ['nama.unique' => 'Nama penjahit ini sudah terdaftar.']);

        DB::table('penjahit')->insert($data + ['aktif' => true, 'created_at' => now(), 'updated_at' => now()]);

        return redirect()->back()->with('success', "Penjahit {$data['nama']} ditambahkan.");
    }

    public function updatePenjahit(Request $request, $id)
    {
        $data = $request->validate([
            'nama'       => 'required|max:100|unique:penjahit,nama,' . (int) $id . ',id_penjahit',
            'telepon'    => 'nullable|max:30',
            'keterangan' => 'nullable|max:1000',
        ], ['nama.unique' => 'Nama penjahit ini sudah terdaftar.']);

        if (! DB::table('penjahit')->where('id_penjahit', $id)->exists()) {
            return redirect()->back()->with('error', 'Penjahit tidak ditemukan.');
        }
        DB::table('penjahit')->where('id_penjahit', $id)->update($data + [
            'aktif' => $request->boolean('aktif'), 'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Data penjahit diperbarui.');
    }

    public function destroyPenjahit($id)
    {
        $p = DB::table('penjahit')->where('id_penjahit', $id)->first();
        if (! $p) {
            return redirect()->back()->with('error', 'Penjahit tidak ditemukan.');
        }
        // Penjahit yang sudah pernah dibayar tetap disimpan supaya riwayat pengeluarannya tidak kehilangan nama.
        if (DB::table('jasa_jahit_catat')->where('id_penjahit', $id)->exists()) {
            DB::table('penjahit')->where('id_penjahit', $id)->update(['aktif' => false, 'updated_at' => now()]);

            return redirect()->back()->with('warning', "{$p->nama} sudah punya catatan jahit, jadi tidak dihapus. Statusnya diubah menjadi nonaktif dan tidak muncul lagi di pilihan.");
        }
        DB::table('penjahit')->where('id_penjahit', $id)->delete();

        return redirect()->back()->with('success', "Penjahit {$p->nama} dihapus.");
    }

    // ---------------------------------------------------------------- katalog tarif

    private function aturanTarif(?int $kecuali = null): array
    {
        return [
            'nama_jahit'            => 'required|max:100|unique:jasa_jahit,nama_jahit' . ($kecuali ? ",{$kecuali},id_jasa_jahit" : ''),
            'id_satuan'             => 'nullable|exists:satuan,id_satuan',
            'id_finishing'          => 'nullable|exists:finishing,id_finishing',
            'harga_borongan'        => 'required|numeric|min:0',
            'keterangan'            => 'nullable|max:1000',
            'untuk_jersey'          => 'nullable|boolean',
            'jersey_sleeve_type_id' => 'nullable|exists:jersey_sleeve_types,id',
            'jersey_collar_type_id' => 'nullable|exists:jersey_collar_types,id',
        ];
    }

    private function dataTarif(Request $request): array
    {
        $jersey = $request->boolean('untuk_jersey');

        return [
            'nama_jahit'            => $request->nama_jahit,
            'id_satuan'             => $request->id_satuan ?: null,
            // Tarif jersey dihitung dari isi order jersey, bukan dari finishing nota.
            'id_finishing'          => $jersey ? null : ($request->id_finishing ?: null),
            'harga_borongan'        => $request->harga_borongan,
            'keterangan'            => $request->keterangan,
            'untuk_jersey'          => $jersey,
            'jersey_sleeve_type_id' => $jersey ? ($request->jersey_sleeve_type_id ?: null) : null,
            'jersey_collar_type_id' => $jersey ? ($request->jersey_collar_type_id ?: null) : null,
        ];
    }

    /** Dua tarif jersey dengan lengan dan kerah yang persis sama membuat hasil hitung tidak pasti. */
    private function tarifJerseyKembar(array $data, ?int $kecuali = null): ?string
    {
        if (! $data['untuk_jersey']) {
            return null;
        }

        return DB::table('jasa_jahit')
            ->where('untuk_jersey', true)
            ->when($kecuali, fn ($q) => $q->where('id_jasa_jahit', '!=', $kecuali))
            ->where(fn ($q) => $data['jersey_sleeve_type_id'] ? $q->where('jersey_sleeve_type_id', $data['jersey_sleeve_type_id']) : $q->whereNull('jersey_sleeve_type_id'))
            ->where(fn ($q) => $data['jersey_collar_type_id'] ? $q->where('jersey_collar_type_id', $data['jersey_collar_type_id']) : $q->whereNull('jersey_collar_type_id'))
            ->value('nama_jahit');
    }

    public function store(Request $request)
    {
        $request->validate($this->aturanTarif(), ['nama_jahit.unique' => 'Nama jenis jahit ini sudah dipakai.']);
        $data = $this->dataTarif($request);
        if ($kembar = $this->tarifJerseyKembar($data)) {
            return redirect()->back()->withInput()->with('error', "Lengan dan kerah ini sudah diatur di tarif \"{$kembar}\". Ubah tarif itu saja.");
        }

        DB::table('jasa_jahit')->insert($data + ['created_at' => now(), 'updated_at' => now()]);

        return redirect()->back()->with('success', 'Jenis jahit ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $request->validate($this->aturanTarif((int) $id), ['nama_jahit.unique' => 'Nama jenis jahit ini sudah dipakai.']);

        if (! DB::table('jasa_jahit')->where('id_jasa_jahit', $id)->exists()) {
            return redirect()->back()->with('error', 'Jenis jahit tidak ditemukan.');
        }
        $data = $this->dataTarif($request);
        if ($kembar = $this->tarifJerseyKembar($data, (int) $id)) {
            return redirect()->back()->with('error', "Lengan dan kerah ini sudah diatur di tarif \"{$kembar}\".");
        }

        // Harga baru berlaku untuk catatan berikutnya. Catatan lama tetap memakai harga yang dibekukan.
        DB::table('jasa_jahit')->where('id_jasa_jahit', $id)->update($data + ['updated_at' => now()]);

        return redirect()->back()->with('success', 'Jenis jahit diperbarui. Catatan lama tetap memakai harga saat dicatat.');
    }

    public function destroy($id)
    {
        $nama = DB::table('jasa_jahit')->where('id_jasa_jahit', $id)->value('nama_jahit');
        if (! $nama) {
            return redirect()->back()->with('error', 'Jenis jahit tidak ditemukan.');
        }
        $jumlah = DB::table('jasa_jahit_catat')->where('id_jasa_jahit', $id)->count();
        if ($jumlah > 0) {
            return redirect()->back()->with('error', "\"{$nama}\" sudah dipakai di {$jumlah} catatan pengeluaran, jadi tidak bisa dihapus. Kalau tarifnya berubah, cukup ubah harganya.");
        }

        DB::table('jasa_jahit')->where('id_jasa_jahit', $id)->delete();

        return redirect()->back()->with('success', "Jenis jahit \"{$nama}\" dihapus.");
    }

    // ---------------------------------------------------------------- catatan

    private function penjahitAktif(): array
    {
        return ['required', \Illuminate\Validation\Rule::exists('penjahit', 'id_penjahit')->where('aktif', true)];
    }

    public function storeCatat(Request $request)
    {
        $request->validate([
            'id_penjahit'         => $this->penjahitAktif(),
            'id_jasa_jahit'       => 'required|exists:jasa_jahit,id_jasa_jahit',
            'id_transaksi_detail' => 'nullable|exists:transaksi_detail,id_detail',
            'qty'                 => 'required|numeric|min:0.0001',
            'harga'               => 'nullable|numeric|min:0',
            'tgl_catat'           => ['required', 'date', new \App\Rules\TanggalTransaksi],
            'catatan'             => 'nullable|max:1000',
        ], ['id_penjahit.required' => 'Pilih penjahitnya dulu.', 'id_penjahit.exists' => 'Penjahit tidak ditemukan atau sudah nonaktif.']);

        $jenis = DB::table('jasa_jahit')->where('id_jasa_jahit', $request->id_jasa_jahit)->first();
        // Upah jahit jersey dicatat dari order jerseynya: di sana dibatasi sisa baju per tarif, jadi tidak bisa terbayar dua kali.
        if (!empty($jenis->untuk_jersey)) {
            return redirect()->back()->withInput()->with('error', "Jenis \"{$jenis->nama_jahit}\" adalah upah jahit jersey. Catat dari halaman order jerseynya supaya jumlahnya tidak melebihi baju yang dipesan.");
        }

        // Harga katalog dipakai kecuali kasir mengisi harga yang benar-benar disepakati kali ini.
        $harga = $request->filled('harga') ? (float) $request->harga : (float) $jenis->harga_borongan;
        $qty = (float) $request->qty;

        DB::table('jasa_jahit_catat')->insert([
            'id_jasa_jahit'       => $jenis->id_jasa_jahit,
            'id_penjahit'         => $request->id_penjahit,
            'id_transaksi_detail' => $request->id_transaksi_detail ?: null,
            'qty'                 => $qty,
            'harga_snapshot'      => $harga,
            'total'               => round($qty * $harga, 2),
            'tgl_catat'           => $request->tgl_catat,
            'catatan'             => $request->catatan,
            'id_user'             => Auth::id(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        return redirect()->back()->with('success', 'Pengeluaran jahit dicatat.');
    }

    /** Rincian ongkos jahit satu order jersey, untuk pratinjau di form Catat Jahit Jersey. */
    public function rincianJersey($idOrder)
    {
        $order = DB::table('jersey_orders')->where('id', $idOrder)->first(['id', 'order_number', 'customer_name', 'team_name']);
        abort_unless($order, 404);

        return response()->json(['order' => $order] + TarifJahitJerseyService::rincian((int) $idOrder));
    }

    public function storeCatatJersey(Request $request)
    {
        $request->validate([
            'id_penjahit'     => $this->penjahitAktif(),
            'id_jersey_order' => 'required|exists:jersey_orders,id',
            'tgl_catat'       => ['required', 'date', new \App\Rules\TanggalTransaksi],
            'qty'             => 'required|array',
            'qty.*'           => 'nullable|integer|min:0',
            'catatan'         => 'nullable|max:1000',
        ], ['id_penjahit.required' => 'Pilih penjahitnya dulu.', 'id_penjahit.exists' => 'Penjahit tidak ditemukan atau sudah nonaktif.']);

        $nomor = DB::table('jersey_orders')->where('id', $request->id_jersey_order)->value('order_number');

        // Pemeriksaan sisa dan penyimpanan dalam satu transaksi, dengan order dikunci, supaya dua pencatatan
        // bersamaan untuk order yang sama tidak sama-sama lolos.
        $hasil = DB::transaction(function () use ($request, $nomor) {
            DB::table('jersey_orders')->where('id', $request->id_jersey_order)->lockForUpdate()->first();
            $rincian = collect(TarifJahitJerseyService::rincian((int) $request->id_jersey_order)['baris'])->keyBy('id_jasa_jahit');

            $baris = [];
            foreach ($request->input('qty', []) as $idTarif => $qty) {
                $qty = (int) $qty;
                if ($qty <= 0) {
                    continue;
                }
                $r = $rincian->get((int) $idTarif);
                if (! $r) {
                    return 'Tarif yang dikirim tidak cocok dengan isi order. Muat ulang halaman lalu coba lagi.';
                }
                // Mencegah bayar dua kali: tidak boleh melebihi baju yang belum dicatat.
                $sisa = (int) max(0, $r['qty'] - $r['sudah']);
                if ($qty > $sisa) {
                    return "{$r['nama_jahit']}: {$nomor} berisi {$r['qty']} baju dan " . (int) $r['sudah'] . " sudah dicatat. Paling banyak {$sisa} lagi.";
                }
                $baris[] = [
                    'id_jasa_jahit'   => $r['id_jasa_jahit'],
                    'id_penjahit'     => $request->id_penjahit,
                    'id_jersey_order' => $request->id_jersey_order,
                    'qty'             => $qty,
                    'harga_snapshot'  => $r['harga'],
                    'total'           => round($qty * $r['harga'], 2),
                    'tgl_catat'       => $request->tgl_catat,
                    'catatan'         => $request->catatan,
                    'id_user'         => Auth::id(),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            }
            if (! $baris) {
                return 'Isi jumlah baju yang dijahit, minimal satu.';
            }
            DB::table('jasa_jahit_catat')->insert($baris);

            return $baris;
        });

        if (is_string($hasil)) {
            return redirect()->back()->withInput()->with('error', $hasil);
        }
        $baris = $hasil;

        $total = array_sum(array_column($baris, 'total'));

        return redirect()->back()->with('success', "Jahit jersey {$nomor} dicatat: " . array_sum(array_column($baris, 'qty')) . ' baju, Rp ' . number_format($total, 0, ',', '.') . '.');
    }

    /** Bayar upah penjahit dari Kas Kecil untuk catatan yang dipilih. */
    public function bayar(Request $request)
    {
        if (! \App\Support\Izin::bolehKeluarkanKasKecil($request->user())) {
            abort(403, 'Membayar penjahit mengeluarkan uang Kas Kecil, jadi butuh hak tambah di menu Kas Kecil.');
        }

        $data = $request->validate([
            'id_penjahit' => 'required|exists:penjahit,id_penjahit',
            'catat'       => 'required|array|min:1',
            'catat.*'     => 'integer',
        ], ['catat.required' => 'Pilih minimal satu catatan yang dibayar.']);

        try {
            $hasil = UpahJahitService::bayar((int) $data['id_penjahit'], array_map('intval', $data['catat']));
        } catch (\RuntimeException $e) {
            return redirect()->back()->with('error', \App\Support\PesanError::untuk($e));
        }

        return redirect()->back()->with('success', "Upah {$hasil['nama']} Rp " . number_format($hasil['total'], 0, ',', '.') . " ({$hasil['jumlah']} catatan) dibayar dari Kas Kecil, nomor KK-{$hasil['id_kas_kecil']}.");
    }

    public function destroyCatat($id)
    {
        // Tidak ada ubah catatan. Kalau salah, hapus lalu catat ulang yang benar.
        $catat = DB::table('jasa_jahit_catat as c')
            ->join('jasa_jahit as j', 'j.id_jasa_jahit', '=', 'c.id_jasa_jahit')
            ->where('c.id_jasa_jahit_catat', $id)
            ->first(['j.nama_jahit', 'c.id_kas_kecil']);

        if (! $catat) {
            return redirect()->back()->with('error', 'Catatan tidak ditemukan.');
        }
        // Uangnya sudah keluar: koreksi lewat pembatalan pengeluaran Kas Kecil, supaya kas dan jurnal ikut kembali.
        if ($catat->id_kas_kecil) {
            return redirect()->back()->with('error', "Catatan ini sudah dibayar lewat KK-{$catat->id_kas_kecil}. Ajukan pembatalan pengeluaran itu di Kas Kecil dulu. Setelah disetujui, catatannya kembali belum dibayar dan bisa dihapus.");
        }
        $nama = $catat->nama_jahit;

        // Hanya baris yang masih belum dibayar: pembayaran yang masuk di sela pengecekan tidak ikut terhapus.
        $terhapus = DB::table('jasa_jahit_catat')->where('id_jasa_jahit_catat', $id)->whereNull('id_kas_kecil')->delete();
        if ($terhapus === 0) {
            return redirect()->back()->with('error', 'Catatan ini baru saja dibayar, jadi tidak dihapus. Muat ulang halaman.');
        }

        return redirect()->back()->with('success', "Catatan jahit \"{$nama}\" dihapus.");
    }
}
