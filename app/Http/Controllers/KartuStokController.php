<?php

namespace App\Http\Controllers;

use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KartuStokController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status', 'aktif');          // aktif | nonaktif | semua
        $statusStok = $request->get('status_stok');          // habis | menipis | aman | tanpa_batas
        $urut = $request->get('urut', 'nama');               // nama | kode | stok_asc | stok_desc | mutasi
        $dari = $request->get('dari');
        $sampai = $request->get('sampai');
        $tipe = $request->get('tipe');                       // IN | OUT | ADJUSTMENT (mutasi pada rentang tanggal)

        $q = DB::table('bahan_baku')
            ->leftJoin('kategori', 'kategori.id_kategori', '=', 'bahan_baku.id_kategori')
            ->leftJoin('mesin', 'mesin.id_mesin', '=', 'bahan_baku.id_mesin')
            ->leftJoin('satuan as satuan_stok_ref', 'satuan_stok_ref.id_satuan', '=', 'bahan_baku.id_satuan_stok')
            ->select('bahan_baku.*', 'kategori.nama_kategori', 'mesin.nama_mesin', 'satuan_stok_ref.nama_satuan as nama_satuan_stok')
            // Subquery berkorelasi per bahan memakai indeks (id_bahan, tanggal). Sebelumnya seluruh kartu stok
            // dikelompokkan dulu (GROUP BY id_bahan) setiap kali halaman dibuka: 24 detik pada 650 ribu mutasi.
            ->selectRaw('(select max(ks2.tanggal) from kartu_stok ks2 where ks2.id_bahan = bahan_baku.id_bahan) as mutasi_terakhir')
            ->selectRaw('(select count(*) from kartu_stok ks3 where ks3.id_bahan = bahan_baku.id_bahan) as jumlah_mutasi')
            ->when($search, function ($qq) use ($search) {
                $qq->where(function ($w) use ($search) {
                    $w->where('bahan_baku.nama_bahan', 'like', "%{$search}%")->orWhere('bahan_baku.kode_bahan', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('kategori'), fn ($qq) => $qq->where('bahan_baku.id_kategori', (int) $request->kategori))
            ->when($request->filled('mesin'), fn ($qq) => $qq->where('bahan_baku.id_mesin', (int) $request->mesin))
            ->when($request->filled('satuan'), fn ($qq) => $qq->where('bahan_baku.id_satuan_stok', (int) $request->satuan))
            ->when(in_array($status, ['aktif', 'nonaktif'], true), fn ($qq) => $qq->where('bahan_baku.status', $status));

        // Status stok (definisi sama dengan dashboard Gudang & Laporan Stok Bahan).
        if ($statusStok === 'habis') {
            $q->where('bahan_baku.stok', '<=', 0);
        } elseif ($statusStok === 'menipis') {
            $q->where('bahan_baku.stok', '>', 0)->where('bahan_baku.stok_minimal', '>', 0)->whereColumn('bahan_baku.stok', '<=', 'bahan_baku.stok_minimal');
        } elseif ($statusStok === 'aman') {
            $q->where('bahan_baku.stok', '>', 0)->where(fn ($w) => $w->where('bahan_baku.stok_minimal', '<=', 0)->orWhereColumn('bahan_baku.stok', '>', 'bahan_baku.stok_minimal'));
        } elseif ($statusStok === 'tanpa_batas') {
            $q->where('bahan_baku.stok_minimal', '<=', 0);
        }

        // Hanya bahan yang bergerak pada rentang tanggal / tipe mutasi tertentu.
        if ($dari || $sampai || $tipe) {
            // Perf dataset besar: kartu_stok.tanggal bertipe TIMESTAMP -
            // whereDate()/DATE() mematikan index (id_bahan,tanggal); pakai
            // rentang sargable [dari 00:00, sampai+1 00:00).
            $q->whereExists(function ($e) use ($dari, $sampai, $tipe) {
                $e->select(DB::raw(1))->from('kartu_stok as ks')->whereColumn('ks.id_bahan', 'bahan_baku.id_bahan')
                    ->when($dari, fn ($x) => $x->where('ks.tanggal', '>=', $dari . ' 00:00:00'))
                    ->when($sampai, fn ($x) => $x->where('ks.tanggal', '<', \Carbon\Carbon::parse($sampai)->addDay()->format('Y-m-d') . ' 00:00:00'))
                    ->when(in_array($tipe, ['IN', 'OUT', 'ADJUSTMENT'], true), fn ($x) => $x->where('ks.tipe', $tipe));
            });
        }

        match ($urut) {
            'kode' => $q->orderBy('bahan_baku.kode_bahan'),
            'stok_asc' => $q->orderBy('bahan_baku.stok')->orderBy('bahan_baku.nama_bahan'),
            'stok_desc' => $q->orderByDesc('bahan_baku.stok')->orderBy('bahan_baku.nama_bahan'),
            'mutasi' => $q->orderByDesc('mutasi_terakhir')->orderBy('bahan_baku.nama_bahan'),
            default => $q->orderBy('bahan_baku.nama_bahan'),
        };

        $bahan = $q->get()->map(function ($b) {
            $b->status_stok = match (true) {
                (float) $b->stok <= 0 => 'Habis',
                (float) $b->stok_minimal > 0 && (float) $b->stok <= (float) $b->stok_minimal => 'Menipis',
                (float) $b->stok_minimal <= 0 => 'Tanpa Batas',
                default => 'Aman',
            };

            return $b;
        });

        $ringkas = [
            'total' => $bahan->count(), 'habis' => $bahan->where('status_stok', 'Habis')->count(), 'menipis' => $bahan->where('status_stok', 'Menipis')->count(),
        ];

        $kategoriList = DB::table('kategori')->orderBy('nama_kategori')->get(['id_kategori', 'nama_kategori']);
        $mesinList = DB::table('mesin')->orderBy('nama_mesin')->get(['id_mesin', 'nama_mesin']);
        $satuanList = DB::table('satuan')->orderBy('nama_satuan')->get(['id_satuan', 'nama_satuan']);

        return view('kartu_stok.index', compact('bahan', 'search', 'status', 'statusStok', 'urut', 'dari', 'sampai', 'tipe', 'ringkas', 'kategoriList', 'mesinList', 'satuanList'));
    }

    public function detail(Request $request, $id)
    {
        $bahan = DB::table('bahan_baku')
            ->leftJoin('satuan as satuan_stok_ref', 'satuan_stok_ref.id_satuan', '=', 'bahan_baku.id_satuan_stok')
            ->leftJoin('satuan as satuan_hitung_ref', 'satuan_hitung_ref.id_satuan', '=', 'bahan_baku.id_satuan_hitung')
            ->where('bahan_baku.id_bahan', $id)
            ->select('bahan_baku.*', 'satuan_stok_ref.nama_satuan as nama_satuan_stok', 'satuan_hitung_ref.nama_satuan as nama_satuan_hitung')
            ->firstOrFail();

        $awal = $request->get('awal', now()->startOfMonth()->toDateString());
        $akhir = $request->get('akhir', now()->toDateString());
        $tipe = $request->get('tipe');
        $cari = trim((string) $request->get('cari', ''));   // referensi / keterangan
        $oleh = $request->get('oleh');                       // id_user pencatat
        $urutDetail = $request->get('urut', 'lama');         // lama | baru

        // Saldo sebelum periode - dipakai sebagai baris "Saldo Awal" supaya
        // baris pertama yang tampil tidak muncul begitu saja tanpa konteks
        // saat histori difilter ke sub-rentang tanggal (pola sama seperti
        // Buku Besar akuntansi: AkuntansiController::bukuBesar()).
        $saldoAwal = DB::table('kartu_stok')
            ->where('id_bahan', $id)
            ->where('tanggal', '<', $awal)
            ->orderBy('tanggal', 'desc')
            ->orderBy('id', 'desc')
            ->value('stok_sesudah');
        $saldoAwal = $saldoAwal !== null ? (float) $saldoAwal : 0.0;

        $kartu = DB::table('kartu_stok')
            ->leftJoin('users', 'users.id_user', '=', 'kartu_stok.created_by')
            ->leftJoin('karyawan', 'karyawan.id_karyawan', '=', 'users.id_karyawan')
            ->where('kartu_stok.id_bahan', $id)
            // Perf dataset besar: rentang sargable (tanggal = TIMESTAMP).
            ->where('kartu_stok.tanggal', '>=', $awal . ' 00:00:00')
            ->where('kartu_stok.tanggal', '<', \Carbon\Carbon::parse($akhir)->addDay()->format('Y-m-d') . ' 00:00:00')
            ->when($tipe, fn ($q) => $q->where('kartu_stok.tipe', $tipe))
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w->where('kartu_stok.referensi', 'like', "%{$cari}%")->orWhere('kartu_stok.keterangan', 'like', "%{$cari}%")))
            ->when($request->filled('oleh'), fn ($q) => $q->where('kartu_stok.created_by', (int) $oleh))
            ->orderBy('kartu_stok.tanggal', $urutDetail === 'baru' ? 'desc' : 'asc')
            ->orderBy('kartu_stok.id', $urutDetail === 'baru' ? 'desc' : 'asc')
            ->select('kartu_stok.*', DB::raw('COALESCE(karyawan.nama_karyawan, users.username) as nama_pencatat'))
            ->get();

        // Ringkasan periode (setelah semua filter): total masuk, keluar, dan selisih penyesuaian.
        $totalMasuk = (float) $kartu->where('tipe', 'IN')->sum('qty');
        $totalKeluar = (float) $kartu->where('tipe', 'OUT')->sum('qty');
        $totalAdj = (float) $kartu->where('tipe', 'ADJUSTMENT')->sum(fn ($k) => (float) $k->stok_sesudah - (float) $k->stok_sebelum);

        $pencatatList = DB::table('kartu_stok')->leftJoin('users', 'users.id_user', '=', 'kartu_stok.created_by')->leftJoin('karyawan', 'karyawan.id_karyawan', '=', 'users.id_karyawan')
            ->where('kartu_stok.id_bahan', $id)->whereNotNull('kartu_stok.created_by')->distinct()
            ->get(['kartu_stok.created_by as id_user', DB::raw('COALESCE(karyawan.nama_karyawan, users.username) as nama')]);

        return view('kartu_stok.detail', compact('bahan', 'kartu', 'awal', 'akhir', 'tipe', 'saldoAwal', 'cari', 'oleh', 'urutDetail', 'totalMasuk', 'totalKeluar', 'totalAdj', 'pencatatList'));
    }

    /**
     * Penyesuaian stok manual satu-bahan (jalur cepat). Stok TIDAK diubah di
     * sini - sama seperti Stock Opname, cuma DIAJUKAN lewat ApprovalService
     * (tipe_aksi 'penyesuaian_stok_manual'); stok baru berubah saat approver
     * menyetujui (lihat PenyesuaianStokService::eksekusi). Diajukan Administrator = langsung disetujui otomatis.
     */
    public function storeAdjustment(Request $request)
    {
        $validated = $request->validate([
            'id_bahan'    => 'required|exists:bahan_baku,id_bahan',
            'tipe'        => 'required|in:OUT,ADJUSTMENT',
            'qty'         => 'required_if:tipe,OUT|nullable|numeric|min:0.01',
            'stok_baru'   => 'required_if:tipe,ADJUSTMENT|nullable|numeric|min:0',
            'keterangan'  => 'required|string|max:255',
            'stok_dibuka' => 'nullable|numeric',
        ]);

        $bahan = DB::table('bahan_baku')->where('id_bahan', $validated['id_bahan'])->first();
        $stok = (float) $bahan->stok;

        // Optimistic locking: stok saat formulir dibuka harus masih sama dengan stok sekarang - kalau sudah berubah
        // (nota, kedatangan, penyesuaian pengguna lain), angka yang Anda lihat sudah usang dan ditolak.
        if (isset($validated['stok_dibuka']) && abs($stok - (float) $validated['stok_dibuka']) > 0.0001) {
            return back()->withInput()->with('error',
                "Stok {$bahan->nama_bahan} sudah berubah sejak formulir dibuka (dibuka: " . angka((float) $validated['stok_dibuka'], 2) .
                ', sekarang: ' . angka($stok, 2) . '). Periksa angka terbaru lalu ulangi penyesuaian.');
        }

        // Cek awal supaya pengaju langsung tahu kalau mustahil; dicek ulang
        // saat eksekusi karena stok bisa berubah selama menunggu approval.
        if ($validated['tipe'] === 'OUT' && (float) $validated['qty'] > $stok) {
            return back()->withInput()->with('error',
                "Gagal: stok {$bahan->nama_bahan} tersisa " . angka($stok, 2) .
                ", tidak bisa dikeluarkan " . angka((float) $validated['qty'], 2) . '.');
        }

        $ringkasan = $validated['tipe'] === 'OUT'
            ? "Keluar {$bahan->nama_bahan}: " . angka((float) $validated['qty'], 2)
            : "Revisi stok {$bahan->nama_bahan}: " . angka($stok, 2) . ' -> ' . angka((float) $validated['stok_baru'], 2);

        $pengajuan = ApprovalService::ajukan(
            'penyesuaian_stok_manual',
            'bahan_baku',
            (int) $bahan->id_bahan,
            [
                'id_bahan'   => (int) $bahan->id_bahan,
                'tipe'       => $validated['tipe'],
                'qty'        => $validated['qty'] ?? null,
                'stok_baru'  => $validated['stok_baru'] ?? null,
                'keterangan' => $validated['keterangan'],
            ],
            $ringkasan,
            $validated['keterangan']
        );

        // Administrator = satu-satunya penyetuju, jadi pengajuannya langsung disetujui & dijalankan (tetap tercatat di
        // Approval Workflow + Kartu Stok sebagai jejak audit). Role lain tetap menunggu persetujuan Administrator.
        if (optional(Auth::user()->role)->nama_role === 'Administrator') {
            // Pemisahan tugas: bila ada Administrator lain, pengajuan menunggu persetujuannya (ApprovalService menolak
            // menyetujui pengajuan sendiri). Dulu jalur ini memanggil tolak sehingga penyesuaian admin selalu gagal.
            $adaAdminLain = DB::table('users')->join('roles', 'roles.id_role', '=', 'users.id_role')
                ->where('roles.nama_role', 'Administrator')->where('users.id_user', '!=', Auth::id())->exists();
            if ($adaAdminLain) {
                return back()->with('success', 'Penyesuaian stok diajukan. Karena ada Administrator lain, stok berubah setelah dia menyetujuinya di menu Approval.');
            }

            try {
                ApprovalService::setujui($pengajuan, fn (array $payload) => \App\Services\PenyesuaianStokService::eksekusi($payload), 'Disetujui otomatis (diajukan oleh Administrator)');
            } catch (\Throwable $e) {
                ApprovalService::tolak($pengajuan, \App\Support\PesanError::untuk($e, 'Gagal dijalankan otomatis'));

                return back()->withInput()->with('error', \App\Support\PesanError::untuk($e, 'Penyesuaian gagal'));
            }
            session()->flash('lanjut', ['teks' => 'Stok sudah diperbarui - lihat riwayatnya di kartu stok bahan', 'url' => url('/kartu-stok/' . $bahan->id_bahan), 'tombol' => 'Buka Kartu Stok']);

            return back()->with('success', 'Penyesuaian stok berhasil - stok bahan sudah diperbarui.');
        }

        return back()->with('success', 'Penyesuaian stok diajukan untuk persetujuan Administrator - stok berubah setelah disetujui.');
    }
}
