<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PembelianBahan;
use App\Models\PembelianBahanDetail;
use App\Models\KedatanganBahan;
use App\Models\BahanBaku;
use Illuminate\Support\Facades\DB;
use App\Services\AccountingService;

class PembelianBahanController extends Controller
{

    public function index(Request $request)
    {
        // Filter search/from/to sebelumnya ditampilkan di form tapi diabaikan
        // total oleh controller (index() tidak menerima Request sama sekali) -
        // apapun yang diisi user, hasil yang tampil selalu daftar penuh tanpa
        // filter.
        $query = PembelianBahan::with('kedatangan.supplier');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('no_faktur', 'like', "%{$search}%")
                  ->orWhereHas('kedatangan.supplier', function ($qs) use ($search) {
                      $qs->where('nama_supplier', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('from')) {
            $query->where('tgl_transaksi', '>=', $request->from); // DATE: sargable
        }

        if ($request->filled('to')) {
            $query->where('tgl_transaksi', '<=', $request->to);
        }

        // Filter jatuh tempo: telat / jatuh tempo <= 7 hari (hanya faktur yang masih berhutang).
        if (in_array($request->jt, ['telat', '7hari', 'aman'], true)) {
            $query->where('status_pembayaran', 'Hutang');
            $batas = $request->jt === 'telat' ? now()->startOfDay() : now()->startOfDay()->addDays(7);
            // Sama dengan hitungan ringkasan: faktur tanpa tanggal jatuh tempo memakai tanggal transaksinya.
            $tglJt = 'DATE(COALESCE(jatuh_tempo_tgl, tgl_transaksi))';
            if ($request->jt === 'telat') {
                $query->whereRaw("{$tglJt} < ?", [$batas->toDateString()]);
            } elseif ($request->jt === '7hari') {
                $query->whereRaw("{$tglJt} >= ?", [now()->startOfDay()->toDateString()])->whereRaw("{$tglJt} <= ?", [$batas->toDateString()]);
            } else { // aman: jatuh tempo lebih dari 7 hari lagi
                $query->whereRaw("{$tglJt} > ?", [now()->startOfDay()->addDays(7)->toDateString()]);
            }
        }

        $data = $query->orderBy('id_pembelian', 'desc')->paginate(20)->withQueryString();

        // Sisa hutang per faktur (grand_total - pembayaran aktif) untuk
        // ditampilkan di daftar & dipakai dialog pembayaran.
        $dibayar = DB::table('pembayaran_hutang')
            ->whereIn('id_pembelian', $data->pluck('id_pembelian'))
            ->whereNull('voided_at')
            ->groupBy('id_pembelian')
            ->selectRaw('id_pembelian, SUM(nominal) as total')
            ->pluck('total', 'id_pembelian');

        $data->getCollection()->each(function ($item) use ($dibayar) {
            $item->sisa_hutang = max(0, round((float) $item->grand_total - (float) ($dibayar[$item->id_pembelian] ?? 0), 2));
        });

        // Ringkasan jatuh tempo seluruh faktur berhutang (bukan hanya halaman ini).
        $ringkasJt = ['telat' => ['n' => 0, 'nilai' => 0.0], 'minggu' => ['n' => 0, 'nilai' => 0.0], 'aman' => ['n' => 0, 'nilai' => 0.0]];
        $hariIni = now()->startOfDay();
        // Satu query untuk semua pembayaran (sebelumnya satu SUM per faktur berhutang: tumbuh tanpa batas).
        $fakturHutang = DB::table('pembelian_bahan')->whereNull('deleted_at')->where('status_pembayaran', 'Hutang')->get();
        $dibayarHutang = DB::table('pembayaran_hutang')->whereIn('id_pembelian', $fakturHutang->pluck('id_pembelian'))
            ->whereNull('voided_at')->groupBy('id_pembelian')->selectRaw('id_pembelian, SUM(nominal) as total')->pluck('total', 'id_pembelian');
        foreach ($fakturHutang as $p) {
            $sisa = max(0, round((float) $p->grand_total - (float) ($dibayarHutang[$p->id_pembelian] ?? 0), 2));
            if ($sisa <= 0) {
                continue;
            }
            $jt = \Carbon\Carbon::parse($p->jatuh_tempo_tgl ?: $p->tgl_transaksi)->startOfDay();
            $kunci = $jt->lt($hariIni) ? 'telat' : ($jt->lte($hariIni->copy()->addDays(7)) ? 'minggu' : 'aman');
            $ringkasJt[$kunci]['n']++;
            $ringkasJt[$kunci]['nilai'] += $sisa;
        }

        return view('pembelian-bahan.index', compact('data', 'ringkasJt'));

    }

public function create()
{
    // Mengambil kedatangan yang statusnya Selesai dan belum diproses menjadi pembelian
    $kedatangans = \App\Models\KedatanganBahan::with('supplier')
        ->where('status', 'Selesai')
        ->whereDoesntHave('pembelian')
        ->get();

    return view('pembelian-bahan.create', compact('kedatangans'));
}
public function kedatanganDetail(Request $request)
{
    $id = $request->query('id_kedatangan');
    $kedatangan = \App\Models\KedatanganBahan::with(['supplier', 'detail.bahan'])->find($id);

    if (!$kedatangan) return response()->json(['detail' => []]);

    // Usulan tempo: durasi faktur tempo terakhir dari supplier yang sama (bila ada), selain default 30 hari.
    $tempoTerakhir = $kedatangan->id_supplier
        ? DB::table('pembelian_bahan as pb')->join('kedatangan_bahan as k', 'k.id_kedatangan', '=', 'pb.id_kedatangan')
            ->where('k.id_supplier', $kedatangan->id_supplier)->where('pb.tipe_pembelian', 'Tempo')->whereNotNull('pb.jatuh_tempo_hari')->whereNull('pb.deleted_at')
            ->orderByDesc('pb.id_pembelian')->value('pb.jatuh_tempo_hari')
        : null;

    return response()->json([
        'tempo_terakhir' => $tempoTerakhir ? (int) $tempoTerakhir : null,
        'supplier' => $kedatangan->supplier->nama_supplier ?? 'Supplier Umum',
        'online' => $kedatangan->id_platform ? [
            'platform' => optional($kedatangan->platform)->nama,
            'toko' => $kedatangan->nama_toko_online,
            'no_pesanan' => $kedatangan->no_pesanan_online,
            'resi' => $kedatangan->no_resi,
        ] : null,
        'detail' => $kedatangan->detail->map(function($item) {
            return [
                'id_bahan' => $item->id_bahan,
                'nama_bahan' => $item->bahan->nama_bahan,
                'kode_bahan' => $item->bahan->kode_bahan,
                'qty' => $item->qty,
                'satuan' => $item->bahan->satuan_stok,
            ];
        })
    ]);
}
    public function store(Request $request)
    {

        $request->validate([
            'id_kedatangan'=>'required',
            // Unik termasuk faktur yang sudah dibatalkan (soft delete): pembalikan jurnal faktur memakai nomor ini,
            // jadi nomor kembar ikut membalik jurnal faktur lain. Dulu hanya tertahan indeks unik sebagai galat sistem.
            'no_faktur'=>'required|max:255|unique:pembelian_bahan,no_faktur',
            'tgl_transaksi'=>'required',
            'tipe_pembelian'=>'required|in:Cash,Tempo',
            'id_bahan'=>'required|array|min:1',
            'qty'=>'required|array|min:1',
            'harga'=>'required|array|min:1',
            'qty.*'=>'required|numeric|min:0.01',
            'harga.*'=>'required|numeric|min:0',
            'ongkir'=>'nullable|numeric|min:0',
            'biaya_layanan'=>'nullable|numeric|min:0',
            'voucher'=>'nullable|numeric|min:0',
        ], [
            'no_faktur.unique' => 'Nomor faktur ini sudah pernah dipakai (termasuk faktur yang sudah dibatalkan). Pakai nomor lain, misalnya dengan akhiran -2.',
        ]);

        DB::beginTransaction();

        try{

            // Audit Fase 2: penjaga SERVER-SIDE (dulu hanya filter dropdown di
            // create()). Gembok baris kedatangan supaya dua submit bersamaan /
            // klik ganda antre; yang kedua melihat faktur sudah ada dan ditolak
            // - bukan faktur ganda + jurnal ganda + harga rata-rata dihitung dua kali.
            $kedatangan = DB::table('kedatangan_bahan')->where('id_kedatangan', $request->id_kedatangan)->lockForUpdate()->first();
            if (!$kedatangan) {
                throw new \RuntimeException('Kedatangan bahan tidak ditemukan.');
            }
            if ($kedatangan->status !== 'Selesai') {
                throw new \RuntimeException('Faktur hanya bisa dibuat dari kedatangan yang sudah berstatus Selesai.');
            }
            if (DB::table('pembelian_bahan')->where('id_kedatangan', $kedatangan->id_kedatangan)->whereNull('deleted_at')->exists()) {
                throw new \RuntimeException('Kedatangan ini sudah punya faktur pembelian.');
            }
            $bahanKedatangan = DB::table('kedatangan_bahan_detail')->where('id_kedatangan', $kedatangan->id_kedatangan)->pluck('id_bahan')->map(fn ($b) => (int) $b)->all();
            foreach ($request->id_bahan as $idBahanInput) {
                if (!in_array((int) $idBahanInput, $bahanKedatangan, true)) {
                    throw new \RuntimeException('Ada bahan pada faktur yang tidak termasuk dalam kedatangan ini.');
                }
            }

            $includePajak = $request->boolean('include_pajak');

            $pembelian = PembelianBahan::create([
                'id_kedatangan'=>$request->id_kedatangan,
                'no_faktur'=>$request->no_faktur,
                'tgl_transaksi'=>$request->tgl_transaksi,
                'tipe_pembelian'=>$request->tipe_pembelian,
                'include_pajak'=>$includePajak,
                'jatuh_tempo_hari'=>$request->tipe_pembelian == 'Tempo' ? max(1, (int) ($request->tempo_hari ?: 30)) : null,
                'ongkir'=>(float) ($request->ongkir ?? 0),
                'biaya_layanan'=>(float) ($request->biaya_layanan ?? 0),
                'voucher'=>(float) ($request->voucher ?? 0),
                'grand_total'=>0
            ]);

            // Subtotal, diskon, potongan, dan PPN 11% dihitung ULANG di sini
            // memakai rumus yang SAMA dengan calculateGrandTotal() di form
            // (qty*harga - diskon - potongan, lalu *1.11 kalau PPN dicentang).
            // Sebelumnya field diskon/potongan/include_pajak yang diisi di form
            // tidak pernah dibaca sama sekali oleh server - grand_total yang
            // tersimpan bisa berbeda dari yang dilihat operator saat entry.
            $grandTotal = 0;
            $barisAvg = [];

            foreach($request->id_bahan as $i=>$idBahan){

                $qty      = (float) $request->qty[$i];
                $harga    = (float) $request->harga[$i];
                $diskon   = (float) ($request->diskon[$i] ?? 0);
                $potongan = (float) ($request->potongan[$i] ?? 0);

                $subtotal = ($qty * $harga) - $diskon - $potongan;

                PembelianBahanDetail::create([
                    'id_pembelian'=>$pembelian->id_pembelian,
                    'id_bahan'=>$idBahan,
                    'qty'=>$qty,
                    'harga'=>$harga,
                    'diskon'=>$diskon,
                    'potongan'=>$potongan,
                    'subtotal'=>$subtotal
                ]);

                $grandTotal += $subtotal;

                // CATATAN: stok TIDAK ditambah di sini. Barang secara fisik sudah
                // masuk dan tercatat di kartu_stok saat Kedatangan Bahan divalidasi
                // (KedatanganBahanController::validateStatus). Pembelian Bahan hanya
                // dibuat dari kedatangan yang sudah berstatus 'Selesai' (lihat create()
                // di atas), jadi ini murni faktur/tagihan atas barang yang sama —
                // menambah stok lagi di sini akan menghitung barang dua kali.

                // Harga rata-rata diperbarui SETELAH total faktur akhir diketahui (lihat di bawah).
                $barisAvg[] = ['id_bahan' => (int) $idBahan, 'qty' => $qty, 'subtotal' => $subtotal];
            }

            if ($includePajak) {
                $grandTotal *= 1.11;
            }

            // Biaya belanja online: ongkir & biaya layanan menambah tagihan, voucher/diskon platform menguranginya
            // (setelah PPN - dihitung sama persis dengan form). Total tidak boleh negatif.
            $grandTotal = max(0, $grandTotal + (float) ($request->ongkir ?? 0) + (float) ($request->biaya_layanan ?? 0) - (float) ($request->voucher ?? 0));

            // Harga rata-rata bergerak (moving average) per bahan.
            // - Harga satuan EFEKTIF = bagian baris ini dari total faktur akhir (setelah diskon, potongan, PPN, ongkir,
            //   biaya layanan, voucher) dibagi qty: dasar yang sama dengan jurnal Persediaan (grand total).
            // - Stok acuan = stok tepat sebelum barang kedatangan ini masuk (baris IN di kartu stok saat validasi),
            //   bukan stok saat faktur dibuat: faktur sering datang belakangan setelah sebagian barang terpakai.
            $totalBaris = array_sum(array_column($barisAvg, 'subtotal'));
            $faktor = $totalBaris > 0 ? $grandTotal / $totalBaris : 1;
            foreach ($barisAvg as $b) {
                if ($b['qty'] <= 0) {
                    continue;
                }
                $bahanUntukAvg = DB::table('bahan_baku')->where('id_bahan', $b['id_bahan'])->lockForUpdate()->first();
                if (!$bahanUntukAvg) {
                    continue;
                }
                $hargaEfektif = ($b['subtotal'] * $faktor) / $b['qty'];
                $stokSaatMasuk = DB::table('kartu_stok')->where('id_bahan', $b['id_bahan'])->where('tipe', 'IN')
                    ->where('referensi', $kedatangan->no_surat_jalan)->orderBy('id')->value('stok_sebelum');
                $stokSebelumBatch = $stokSaatMasuk !== null ? (float) $stokSaatMasuk : (float) $bahanUntukAvg->stok - $b['qty'];
                $avgLama = (float) $bahanUntukAvg->harga_rata_rata;

                $avgBaru = ($stokSebelumBatch > 0 && $avgLama > 0)
                    ? (($avgLama * $stokSebelumBatch) + ($b['qty'] * $hargaEfektif)) / ($stokSebelumBatch + $b['qty'])
                    // Belum ada histori biaya atau stok sebelumnya kosong/minus: harga batch ini apa adanya.
                    : $hargaEfektif;

                DB::table('bahan_baku')->where('id_bahan', $b['id_bahan'])->update(['harga_rata_rata' => round($avgBaru, 2)]);
            }

            if ($request->tipe_pembelian == 'Cash') {
                $pembelian->status_pembayaran = 'Lunas';
                $pembelian->tgl_pelunasan = $request->tgl_transaksi;
            } else {
                $pembelian->status_pembayaran = 'Hutang';
                // Tempo TIDAK PERNAH tanpa jatuh tempo: kosong -> default 30 hari setelah tanggal transaksi.
                $pembelian->jatuh_tempo_tgl = $request->jatuh_tempo_tgl
                    ?: \Carbon\Carbon::parse($request->tgl_transaksi)->addDays(max(1, (int) ($request->tempo_hari ?: 30)))->toDateString();
            }

            $pembelian->save();
            $pembelian->update([
                'grand_total'=>$grandTotal
            ]);

            AccountingService::postPembelianBahan($request->no_faktur, $grandTotal, $request->tipe_pembelian);

            if ($request->tipe_pembelian == 'Cash') {
                \App\Services\PembayaranHutangService::catatPembayaranAwal($pembelian->id_pembelian, round($grandTotal, 2), $request->tgl_transaksi);
            }

            DB::commit();

            if ($request->tipe_pembelian == 'Tempo') {
                \App\Support\JalurLanjut::set('Faktur tempo tercatat - pantau jatuh tempo & bayar hutangnya', 'pembelian-bahan.index', 'Buka Pembelian Bahan');
            } else {
                \App\Support\JalurLanjut::set('Faktur tunai sudah lunas - cetak atau lihat rinciannya', 'pembelian-bahan.show', 'Lihat Faktur', [$pembelian->id_pembelian]);
            }

            return redirect()->route('pembelian-bahan.index')
                ->with('success','Pembelian berhasil disimpan');

        }catch(\RuntimeException $e){

            // Pesan bisnis yang memang ditujukan ke operator (periode ditutup,
            // kedatangan sudah difaktur, dst).
            DB::rollBack();
            return back()->withInput()->with('error', \App\Support\PesanError::untuk($e));

        }catch(\Exception $e){

            // Selain itu (SQL, bug): detail hanya ke log, bukan ke layar.
            DB::rollBack();
            report($e);
            return back()->withInput()->with('error','Pembelian gagal disimpan karena kesalahan sistem. Data tidak tersimpan; coba lagi atau hubungi Administrator.');

        }


    }

    /**
     * Hapus massal = MENGAJUKAN pembatalan tiap faktur lewat Approval
     * Workflow (bukan menghapus langsung) - pembatalan faktur harus
     * membalik jurnal & dikontrol (bagian 28 dokumen audit).
     */
    public function bulkDelete(Request $request)
    {
        $ids = $request->ids;

        if (!$ids) {
            return back()->with('error', 'Tidak ada data dipilih');
        }

        $request->validate(['alasan' => 'nullable|string|max:500']);

        $diajukan = 0;
        $dilewati = [];

        foreach ($ids as $id) {
            $hasil = $this->ajukanPembatalan((int) $id, $request->input('alasan') ?: 'Pembatalan massal');
            $hasil === true ? $diajukan++ : $dilewati[] = $hasil;
        }

        $pesan = "{$diajukan} pengajuan pembatalan faktur dikirim untuk persetujuan.";
        if ($dilewati) {
            $pesan .= ' Dilewati: ' . implode('; ', array_slice($dilewati, 0, 3));
        }

        return back()->with($diajukan > 0 ? 'success' : 'error', $pesan);
    }

    public function bahan()
{
    return $this->belongsTo(BahanBaku::class, 'id_bahan');
}
public function show($id)
{
    // Mengambil data pembelian beserta relasi kedatangan, supplier, dan detail bahan
    $pembelian = PembelianBahan::with([
        'kedatangan.supplier', 
        'detail.bahan'
    ])->findOrFail($id);

    $pembayaran = DB::table('pembayaran_hutang')->where('id_pembelian', $id)->orderBy('id')->get();
    $sisa = \App\Services\PembayaranHutangService::sisa((object) $pembelian->getAttributes());

    return view('pembelian-bahan.show', compact('pembelian', 'pembayaran', 'sisa'));
}

    /**
     * Cetak faktur pembelian mengikuti aturan cetak aplikasi: pilihan A4 atau Continuous Form (9,5 x 11 inci).
     * Tanpa parameter format = ikut format global Pengaturan Aplikasi (thermal dianggap A4, karena faktur bukan struk).
     */
    public function cetak($id, $format = null)
    {
        $pembelian = PembelianBahan::with(['kedatangan.supplier', 'detail.bahan'])->findOrFail($id);
        $sisa = \App\Services\PembayaranHutangService::sisa((object) $pembelian->getAttributes());

        $global = \App\Models\AppSetting::current()->format_nota ?? 'a4';
        $format = in_array($format, ['a4', 'continuous'], true) ? $format : ($global === 'continuous' ? 'continuous' : 'a4');

        return view('pembelian-bahan.print', compact('pembelian', 'sisa', 'format'));
    }

    /**
     * Mengajukan pembatalan faktur ke Approval Workflow (tipe_aksi
     * 'batalkan_pembelian'). Faktur baru benar-benar dibatalkan (jurnal
     * dibalik, soft-delete) saat disetujui - lihat PembayaranHutangService.
     *
     * @return true|string  true kalau diajukan, atau alasan singkat kenapa dilewati
     */
    private function ajukanPembatalan(int $id, string $alasan)
    {
        $p = PembelianBahan::find($id);

        if (!$p) {
            return "faktur #{$id} tidak ditemukan";
        }

        $sudahAda = \App\Models\ApprovalRequest::where('tipe_aksi', 'batalkan_pembelian')
            ->where('referensi_type', 'pembelian_bahan')->where('referensi_id', $id)->where('status', 'Menunggu')->exists();

        if ($sudahAda) {
            return "{$p->no_faktur} sudah punya pengajuan pembatalan yang menunggu";
        }

        \App\Services\ApprovalService::ajukan(
            'batalkan_pembelian',
            'pembelian_bahan',
            $id,
            ['alasan' => $alasan],
            "Batalkan faktur pembelian {$p->no_faktur} (Rp " . number_format($p->grand_total, 0, ',', '.') . ')',
            $alasan
        );

        return true;
    }

    public function destroy(Request $request, $id)
    {
        $request->validate(['alasan' => 'nullable|string|max:500']);

        $hasil = $this->ajukanPembatalan((int) $id, $request->input('alasan') ?: 'Pembatalan faktur');

        return $hasil === true
            ? back()->with('success', 'Pengajuan pembatalan faktur dikirim untuk persetujuan Administrator.')
            : back()->with('error', ucfirst($hasil) . '.');
    }

    /** Mengajukan void satu pembayaran hutang (tipe_aksi 'void_pembayaran_hutang'). */
    public function ajukanVoidPembayaran(Request $request, $idPembayaran)
    {
        $request->validate(['alasan' => 'required|string|max:255']);

        $bayar = DB::table('pembayaran_hutang')->where('id', $idPembayaran)->first();

        if (!$bayar || $bayar->voided_at || $bayar->bagian_faktur) {
            return back()->with('error', 'Pembayaran ini tidak bisa di-void (sudah di-void atau merupakan pembayaran awal faktur).');
        }

        $sudahAda = \App\Models\ApprovalRequest::where('tipe_aksi', 'void_pembayaran_hutang')
            ->where('referensi_id', $idPembayaran)->where('status', 'Menunggu')->exists();

        if ($sudahAda) {
            return back()->with('error', 'Pembayaran ini sudah punya pengajuan void yang menunggu.');
        }

        $faktur = DB::table('pembelian_bahan')->where('id_pembelian', $bayar->id_pembelian)->value('no_faktur');

        \App\Services\ApprovalService::ajukan(
            'void_pembayaran_hutang',
            'pembayaran_hutang',
            (int) $idPembayaran,
            ['alasan' => $request->alasan],
            "Void pembayaran hutang {$faktur} (Rp " . number_format($bayar->nominal, 0, ',', '.') . ')',
            $request->alasan
        );

        return back()->with('success', 'Pengajuan void pembayaran dikirim untuk persetujuan Administrator.');
    }

// Menampilkan form edit (Terbatas pada data non-stok seperti No Faktur/Tanggal)
public function edit($id)
{
    $pembelian = PembelianBahan::with('kedatangan.supplier')->findOrFail($id);
    return view('pembelian-bahan.edit', compact('pembelian'));
}

// Update data dasar. no_faktur SENGAJA tidak bisa diubah: seluruh jurnal
// akuntansi merujuk nomor ini (no_referensi) - mengganti nomor setelah
// diposting memutus hubungan faktur dengan jurnalnya dan membuat
// pembatalan/pembalikan tidak menemukan baris jurnal aslinya lagi.
public function update(Request $request, $id)
{
    $request->validate([
        'tgl_transaksi' => ['required', 'date', new \App\Rules\TanggalTransaksi]
    ]);

    $pembelian = PembelianBahan::findOrFail($id);
    if (\App\Support\VersiBaris::bentrok($request, $pembelian)) {
        return back()->withInput()->with('error', \App\Support\VersiBaris::pesan($pembelian));
    }

    $tglLama = $pembelian->tgl_transaksi;
    $tglBaru = $request->tgl_transaksi;

    // Cek periode akuntansi tertutup untuk tanggal LAMA dan BARU.
    if (\App\Services\AccountingPeriodService::isClosed($tglLama)) {
        throw new \App\Exceptions\PeriodeAkuntansiTertutupException('Periode ' . \Carbon\Carbon::parse($tglLama)->locale('id')->translatedFormat('F Y') . ' sudah ditutup. Tanggal lama tidak bisa diubah.');
    }
    if (\App\Services\AccountingPeriodService::isClosed($tglBaru)) {
        throw new \App\Exceptions\PeriodeAkuntansiTertutupException('Periode ' . \Carbon\Carbon::parse($tglBaru)->locale('id')->translatedFormat('F Y') . ' sudah ditutup. Pakai tanggal di periode yang masih terbuka.');
    }

    // Tanggal sama = tidak ada perubahan, tidak ada audit.
    if ($tglLama === $tglBaru) {
        return redirect()->route('pembelian-bahan.index')->with('success', 'Faktur berhasil diperbarui');
    }

    $jtLama = $pembelian->jatuh_tempo_tgl;

    // Hitung ulang jatuh_tempo_tgl hanya jika jatuh_tempo_hari terisi (Tempo).
    $dataUpdate = ['tgl_transaksi' => $tglBaru];
    if ($pembelian->jatuh_tempo_hari) {
        $dataUpdate['jatuh_tempo_tgl'] = \Carbon\Carbon::parse($tglBaru)->addDays((int) $pembelian->jatuh_tempo_hari)->toDateString();
    }

    $pembelian->update($dataUpdate);

    $jtBaru = $pembelian->fresh()->jatuh_tempo_tgl;

    // Audit trail: tanggal lama -> baru, jatuh tempo lama -> baru, user.
    \App\Services\AuditLogger::log(
        'updated',
        'pembelian_bahan',
        (int) $id,
        ['tgl_transaksi' => $tglLama, 'jatuh_tempo_tgl' => $jtLama],
        ['tgl_transaksi' => $tglBaru, 'jatuh_tempo_tgl' => $jtBaru],
        'Tanggal transaksi pembelian #' . $id . ' diubah: ' . $tglLama . ' -> ' . $tglBaru
    );

    return redirect()->route('pembelian-bahan.index')->with('success', 'Faktur berhasil diperbarui');
}

/**
 * Membayar hutang faktur (cicilan atau pelunasan). Nominal default = sisa
 * hutang (pelunasan penuh, perilaku tombol lama). Route tetap
 * pembelian.pelunasan supaya tautan lama tidak putus.
 */
public function pelunasan(Request $request, $id)
{
    $request->validate([
        'nominal' => 'nullable|numeric|min:0.01',
        'metode' => 'nullable|in:Tunai,Transfer,Kas Kecil',
        'tanggal' => ['nullable', 'date', new \App\Rules\TanggalTransaksi],
        'keterangan' => 'nullable|string|max:255',
    ]);

    try {
        $pembelian = DB::table('pembelian_bahan')->where('id_pembelian', $id)->whereNull('deleted_at')->first();

        if (!$pembelian) {
            throw new \RuntimeException('Data pembelian dengan ID ' . $id . ' tidak ditemukan.');
        }

        $nominal = $request->filled('nominal')
            ? (float) $request->nominal
            : \App\Services\PembayaranHutangService::sisa($pembelian);

        \App\Services\PembayaranHutangService::bayar(
            (int) $id,
            $nominal,
            $request->input('metode', 'Tunai'),
            $request->input('tanggal'),
            $request->input('keterangan')
        );

        return redirect()->back()->with('success', 'Pembayaran hutang Rp ' . \App\Support\Angka::format($nominal, 2) . ' berhasil dicatat.');
    } catch (\Exception $e) {
        return redirect()->back()->with('error', \App\Support\PesanError::untuk($e, 'Gagal membayar'));
    }
}
/** Data untuk layar pelunasan: ?faktur=ID (satu faktur) atau ?supplier=ID (semua hutang supplier). */
public function pelunasanInfo(Request $request)
{
    $service = \App\Services\PembayaranHutangService::class;
    $saldoKasKecil = max(0, $service::saldoKasKecil());

    if ($request->filled('faktur')) {
        $p = DB::table('pembelian_bahan')->where('id_pembelian', (int) $request->faktur)->whereNull('deleted_at')->first();
        abort_if(!$p, 404);
        $supplier = DB::table('kedatangan_bahan as k')->leftJoin('supplier as s', 's.id_supplier', '=', 'k.id_supplier')->where('k.id_kedatangan', $p->id_kedatangan)->value('s.nama_supplier');
        $r = $service::ringkasan($p);

        return response()->json([
            'mode' => 'faktur', 'judul' => $p->no_faktur, 'supplier' => $supplier ?? 'Umum', 'total' => $r['total'], 'dibayar' => $r['dibayar'], 'sisa' => $r['sisa'],
            'fakturs' => [$r], 'saldo_kas_kecil' => $saldoKasKecil, 'action' => route('pembelian.pelunasan', $p->id_pembelian),
            'riwayat' => DB::table('pembayaran_hutang')->where('id_pembelian', $p->id_pembelian)->whereNull('voided_at')->orderBy('tanggal')->orderBy('id')
                ->get(['tanggal', 'nominal', 'metode', 'keterangan'])->map(fn ($b) => [
                    'tanggal' => \Carbon\Carbon::parse($b->tanggal)->translatedFormat('d M Y'), 'nominal' => (float) $b->nominal, 'metode' => $b->metode, 'keterangan' => $b->keterangan,
                ]),
        ]);
    }

    $idSupplier = (int) $request->supplier;
    $sup = DB::table('supplier')->where('id_supplier', $idSupplier)->first();
    abort_if(!$sup, 404);
    $fakturs = $service::fakturBerhutang($idSupplier)->map(fn ($p) => $service::ringkasan($p))->filter(fn ($r) => $r['sisa'] > 0)->values();

    return response()->json([
        'mode' => 'supplier', 'judul' => $sup->nama_supplier, 'supplier' => $sup->nama_supplier, 'total' => $fakturs->sum('total'), 'dibayar' => $fakturs->sum('dibayar'),
        'sisa' => round($fakturs->sum('sisa'), 2), 'fakturs' => $fakturs, 'saldo_kas_kecil' => $saldoKasKecil, 'riwayat' => [],
        'action' => route('pembelian.bayar-supplier', $idSupplier),
    ]);
}

/** Bayar hutang semua faktur satu supplier sekaligus (dibagi ke faktur paling lama dulu). */
public function bayarSupplier(Request $request, $idSupplier)
{
    $data = $request->validate([
        'nominal' => 'required|numeric|min:0.01', 'metode' => 'required|in:Tunai,Transfer,Kas Kecil',
        'tanggal' => ['nullable', 'date', new \App\Rules\TanggalTransaksi], 'keterangan' => 'nullable|string|max:255',
    ]);

    try {
        $hasil = \App\Services\PembayaranHutangService::bayarSupplier((int) $idSupplier, (float) $data['nominal'], $data['metode'], $data['tanggal'] ?? null, $data['keterangan'] ?? null);
    } catch (\Exception $e) {
        return back()->with('error', \App\Support\PesanError::untuk($e, 'Gagal membayar'));
    }

    return back()->with('success', 'Pembayaran hutang Rp ' . \App\Support\Angka::format((float) $data['nominal'], 2) . ' dibagi ke ' . count($hasil) . ' faktur: ' . collect($hasil)->map(fn ($h) => "{$h['no_faktur']} (Rp " . \App\Support\Angka::format((float) $h['nominal'], 2) . ')')->implode(', ') . '.');
}

public function getKedatanganDetail(Request $request)
{
    $id = $request->id_kedatangan;
    $data = KedatanganBahan::with(['supplier', 'detail.bahan'])->findOrFail($id);

    return response()->json([
        'supplier' => $data->supplier,
        'detail' => $data->detail
    ]);
}

}
