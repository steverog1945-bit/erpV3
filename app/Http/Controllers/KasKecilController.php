<?php

namespace App\Http\Controllers;

use App\Models\KasKecil;
use App\Models\KategoriKasKecil;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Kas Kecil: pembukuan terpisah yang TIDAK menyentuh laci kasir.
 * - Uang masuk: saldo awal (sekali), pembayaran order jersey (otomatis), setoran pemilik, pemasukan lain.
 * - Uang keluar: belanja kecil kebutuhan toko (ATK, konsumsi, ongkos kirim, dst), komisi desainer, upah jahit.
 *   Belanja bahan baku utama tetap lewat Pembelian Bahan.
 * Saldo yang berlaku adalah saldo akun 1005 di jurnal; pengeluaran ditolak bila saldonya tidak cukup.
 */
class KasKecilController extends Controller
{
    /**
     * Saldo awal Kas Kecil: uang yang sudah ada sebelum pembukuan dimulai. Hanya sekali (bisa dibatalkan lewat
     * pengajuan pembatalan biasa lalu ditetapkan ulang). Jurnal: Dr Kas Kecil (1005) / Cr Modal (3000).
     */
    public function saldoAwal(Request $request)
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date', new \App\Rules\TanggalTransaksi],
            'nominal' => 'required|numeric|min:1',
        ]);

        try {
            DB::transaction(function () use ($data) {
                DB::table('bag_akun')->where('kode_akun', '1005')->lockForUpdate()->first();
                if (KasKecil::where('sumber', 'saldo_awal')->exists()) {
                    throw new \RuntimeException('Saldo awal Kas Kecil sudah ditetapkan. Untuk mengubahnya, ajukan pembatalan catatan saldo awal lalu tetapkan ulang.');
                }
                $kk = KasKecil::create([
                    'tanggal' => $data['tanggal'], 'jenis' => 'Masuk', 'sumber' => 'saldo_awal', 'metode' => 'Tunai', 'id_kategori' => null,
                    'keterangan' => 'Saldo awal Kas Kecil', 'nominal' => $data['nominal'], 'id_user' => Auth::id(),
                ]);
                AccountingService::postJournal($data['tanggal'], 'KK-' . $kk->id, 'Saldo awal Kas Kecil', [
                    ['kode_akun' => '1005', 'debet' => (float) $data['nominal'], 'kredit' => 0],
                    ['kode_akun' => '3000', 'debet' => 0, 'kredit' => (float) $data['nominal']],
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', \App\Support\PesanError::untuk($e));
        }

        return back()->with('success', 'Saldo awal Kas Kecil Rp ' . number_format((float) $data['nominal'], 0, ',', '.') . ' dicatat.');
    }

    public function index(Request $request)
    {
        $query = KasKecil::with(['user', 'kategori'])->orderByDesc('tanggal')->orderByDesc('id');

        $tglDari = $request->filled('tgl_dari') ? $request->tgl_dari : now()->format('Y-m-d');
        $tglSampai = $request->filled('tgl_sampai') ? $request->tgl_sampai : now()->format('Y-m-d');
        // Kolom `tanggal` bertipe DATE: bandingkan langsung (sargable), bukan whereDate()/DATE(kolom) yang mematikan indeks.
        $query->where('tanggal', '>=', $tglDari)->where('tanggal', '<=', $tglSampai);

        if ($request->filled('id_kategori')) {
            $query->where('id_kategori', $request->id_kategori);
        }

        if ($request->filled('jenis')) {
            $query->where('jenis', $request->jenis);
        }

        // Ringkasan buku kas: pemasukan tunai (kas fisik), pemasukan non-tunai (mis. transfer jersey) & pengeluaran.
        // Pengeluaran yang benar-benar dari uang Kas Kecil; biaya yang dibayar langsung pemilik dihitung terpisah.
        $totalPengeluaran = (float) (clone $query)->where('jenis', 'Keluar')->where('metode', '!=', 'Dibayar Pemilik')->sum('nominal');
        $pengeluaranPemilik = (float) (clone $query)->where('jenis', 'Keluar')->where('metode', 'Dibayar Pemilik')->sum('nominal');
        $masukTunai = (float) (clone $query)->where('jenis', 'Masuk')->where('metode', 'Tunai')->sum('nominal');
        $masukNonTunai = (float) (clone $query)->where('jenis', 'Masuk')->where('metode', '!=', 'Tunai')->sum('nominal');
        $data = $query->paginate(20)->withQueryString();

        // Kategori sekarang tabel referensi tetap (dropdown), bukan lagi
        // isian bebas - lihat migrasi create_kategori_kas_kecil_table.
        $kategoriList = KategoriKasKecil::whereNotNull('kode_akun')->whereNotIn('kode_akun', ['1000', '1005'])->orderBy('nama_kategori')->get();
        // Saldo sebenarnya (seluruh waktu, menurut jurnal), bukan selisih dalam rentang filter.
        $saldoKasKecil = \App\Services\PembayaranHutangService::saldoKasKecil();
        $adaSaldoAwal = KasKecil::where('sumber', 'saldo_awal')->exists();
        $bolehSaldoAwal = optional(Auth::user()->role)->nama_role === 'Administrator';

        return view('kas-kecil.index', compact('data', 'tglDari', 'tglSampai', 'totalPengeluaran', 'pengeluaranPemilik', 'masukTunai', 'masukNonTunai', 'kategoriList', 'saldoKasKecil', 'adaSaldoAwal', 'bolehSaldoAwal'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date', new \App\Rules\TanggalTransaksi],
            'jenis' => 'required|in:Keluar,Masuk',
            'id_kategori' => 'required|integer|exists:kategori_kas_kecil,id_kategori_kas_kecil',
            'keterangan' => 'required|string|max:500',
            'nominal' => 'required|numeric|min:1',
            'bukti_path' => 'nullable|string|max:255',
        ]);
        $data['id_user'] = Auth::id();
        $data['sumber'] = 'manual';
        $data['metode'] = 'Tunai';

        // Setiap pengeluaran kas kecil SEKARANG ikut masuk jurnal akuntansi
        // (sebelumnya berdiri sendiri, tidak pernah muncul di Laba Rugi) -
        // akun Beban yang di-debit ditentukan oleh kategori yang dipilih
        // (kategori_kas_kecil.kode_akun). Kalau kategori itu kebetulan
        // belum dipetakan ke akun apa pun (kode_akun kosong), pencatatan
        // kas kecil TETAP disimpan (tidak memblokir operasional harian),
        // tapi jurnalnya dilewati dengan peringatan ke user supaya ketahuan
        // dan bisa dilengkapi lewat menu Kategori Kas Kecil.
        $kategori = KategoriKasKecil::findOrFail($data['id_kategori']);

        if ($kategori->jenis !== $data['jenis']) {
            return back()->withInput()->withErrors(['id_kategori' => "Kategori \"{$kategori->nama_kategori}\" adalah kategori " . ($kategori->jenis === 'Masuk' ? 'pemasukan' : 'pengeluaran') . ', tidak cocok dengan jenis yang dipilih.']);
        }

        // Tanpa akun tidak ada jurnal, padahal saldo Kas Kecil dihitung dari jurnal: pengeluarannya tidak mengurangi
        // saldo dan penjaga "tidak boleh minus" terlewati. Kategorinya harus dilengkapi dulu.
        if (!$kategori->kode_akun) {
            return back()->withInput()->with('error', "Kategori \"{$kategori->nama_kategori}\" belum dihubungkan ke akun, jadi belum bisa dipakai. Lengkapi akunnya lewat menu Kategori Kas Kecil, lalu catat lagi.");
        }

        // Kas Kecil tidak menyentuh laci kasir, dan tidak bisa "mengisi dirinya sendiri".
        if (in_array($kategori->kode_akun, ['1000', '1005'], true)) {
            return back()->withInput()->with('error', "Kategori \"{$kategori->nama_kategori}\" tidak bisa dipakai: Kas Kecil terpisah dari laci kasir. Uang tambahan dari pemilik dicatat dengan kategori setoran pemilik.");
        }

        // Biaya yang dibayar langsung pemilik: dicatat sebagai data dan beban, tetapi bukan uang Kas Kecil.
        $dibayarPemilik = $data['jenis'] === 'Keluar' && $kategori->dibayar_pemilik;
        if ($dibayarPemilik) {
            $data['metode'] = 'Dibayar Pemilik';
        }

        try {
        $kasKecil = DB::transaction(function () use ($data, $kategori, $dibayarPemilik) {
            // Uang fisik tidak bisa minus: pengeluaran dari Kas Kecil ditolak bila saldonya tidak cukup.
            if ($data['jenis'] === 'Keluar' && !$dibayarPemilik) {
                \App\Services\KasKecilService::pastikanSaldoCukup((float) $data['nominal']);
            }
            $kk = KasKecil::create($data);

            if ($dibayarPemilik) {
                AccountingService::postBiayaDibayarPemilik('KK-' . $kk->id, $kategori->kode_akun, (float) $kk->nominal);
            } elseif ($data['jenis'] === 'Masuk') {
                AccountingService::postKasKecilMasuk('KK-' . $kk->id, $kategori->kode_akun, (float) $kk->nominal);
            } else {
                AccountingService::postKasKecil('KK-' . $kk->id, $kategori->kode_akun, (float) $kk->nominal);
            }

            return $kk;
        });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', \App\Support\PesanError::untuk($e));
        }

        if ($dibayarPemilik) {
            return back()->with('success', "Biaya \"{$kategori->nama_kategori}\" dicatat sebagai dibayar langsung pemilik. Saldo Kas Kecil tidak berubah.");
        }

        return back()->with('success', ($data['jenis'] === 'Masuk' ? 'Pemasukan' : 'Pengeluaran') . ' kas kecil berhasil dicatat & masuk ke jurnal akuntansi.');
    }

    /**
     * Pembatalan catatan kas kecil = MENGAJUKAN ke Approval Workflow
     * (tipe_aksi 'batalkan_kas_kecil'); jurnal dibalik & catatan dihapus
     * saat disetujui (lihat KasKecilService::batalkan).
     */
    public function destroy(Request $request, KasKecil $kasKecil)
    {
        if ($kasKecil->sumber === 'jersey') {
            return back()->with('error', 'Pemasukan ini berasal dari pembayaran order jersey - batalkan lewat Keuangan Jersey (void pembayaran), bukan dari sini.');
        }
        if ($kasKecil->sumber === 'hutang') {
            return back()->with('error', 'Pengeluaran ini berasal dari pembayaran hutang supplier - batalkan lewat void pembayaran di Pembelian Bahan, bukan dari sini.');
        }

        $sudahAda = \App\Models\ApprovalRequest::where('tipe_aksi', 'batalkan_kas_kecil')
            ->where('referensi_id', $kasKecil->id)->where('status', 'Menunggu')->exists();

        if ($sudahAda) {
            return back()->with('error', 'Catatan ini sudah punya pengajuan pembatalan yang menunggu.');
        }

        $alasan = $request->input('alasan') ?: 'Pembatalan catatan kas kecil';

        \App\Services\ApprovalService::ajukan(
            'batalkan_kas_kecil',
            'kas_kecil',
            $kasKecil->id,
            ['alasan' => $alasan],
            "Batalkan kas kecil KK-{$kasKecil->id} (Rp " . number_format($kasKecil->nominal ?? 0, 0, ',', '.') . ')',
            $alasan
        );

        return back()->with('success', 'Pengajuan pembatalan kas kecil dikirim untuk persetujuan Administrator.');
    }

    public function uploadBukti(Request $request)
    {
        $request->validate([
            'bukti' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $path = $request->file('bukti')->store('kas_kecil_bukti', 'public');

        return response()->json([
            'status' => 'success',
            'path' => $path,
            'url' => asset('storage/' . $path),
        ]);
    }
}
