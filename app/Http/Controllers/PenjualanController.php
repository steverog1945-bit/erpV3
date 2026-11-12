<?php

namespace App\Http\Controllers;

use App\Services\KasSesiService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\HargaService;
use App\Services\AccountingService;
use App\Services\StokReversalService;
use App\Services\DocumentNumberService;
use Carbon\Carbon;

class PenjualanController extends Controller
{
    public function create()
{
    $kategori = DB::table('kategori')->get();
    
    // Ambil data produk lengkap dengan id_kategori dari bahan bakunya - hanya
    // yang berstatus 'aktif' (produk dinonaktifkan sengaja disembunyikan dari
    // pilihan order baru, sama seperti bahan_baku nonaktif tidak muncul di
    // BOM/Kedatangan Bahan, lihat ProdukController::toggleStatus).
    $produk = DB::table('produk')
    ->leftJoin('bom_detail', 'produk.id_produk', '=', 'bom_detail.id_produk')
    ->leftJoin('bahan_baku', 'bom_detail.id_bahan', '=', 'bahan_baku.id_bahan')
    ->where('produk.status', 'aktif')
    ->select('produk.*', DB::raw('COALESCE(bahan_baku.id_kategori, 0) as id_kategori'))
    ->get()
    ->unique('id_produk');

    $golongan = DB::table('golongan_konsumen')->get();
    // Perf dataset besar: dropdown pelanggan POS sekarang AJAX (select2 AJAX
    // ke KonsumenController::searchAjax) - server TIDAK lagi menyeret seluruh
    // tabel konsumen (bisa puluhan ribu baris) ke HTML tiap buka POS. Server
    // render cuma untuk SATU konsumen pre-selected dari redirect ?id_konsumen=
    // (mis. alur nota pengganti), supaya select2 langsung menampilkan namanya
    // tanpa perlu fetch tambahan.
    $konsumenAwal = collect();
    if (request()->filled('id_konsumen')) {
        $konsumenAwal = DB::table('konsumen')
            ->where('id_konsumen', (int) request('id_konsumen'))
            ->get(['id_konsumen', 'nama_konsumen', 'telepon', 'id_golongan']);
    }
    $finishing = DB::table('finishing')->orderBy('nama_finishing')->get();
    $jasa = DB::table('jasa')
        ->leftJoin('satuan', 'jasa.id_satuan', '=', 'satuan.id_satuan')
        ->select('jasa.*', 'satuan.nama_satuan')
        ->orderBy('jasa.nama_jasa')
        ->get();

    // Generate No. Nota - prefix bisa diatur di Pengaturan Aplikasi (dulu
    // hardcode "INV").
    $date = date('Ymd');
    // Perf dataset besar: rentang sargable (DATE() mematikan index tgl_order).
    $lastNota = DB::table('transaksi')
        ->where('tgl_order', '>=', $date . ' 00:00:00')
        ->where('tgl_order', '<', date('Y-m-d', strtotime('+1 day')) . ' 00:00:00')
        ->count();
    $prefixNota = \App\Models\AppSetting::current()->nomor_nota_prefix ?: 'INV';
    $no_nota = $prefixNota . "-" . $date . "-" . str_pad($lastNota + 1, 3, '0', STR_PAD_LEFT);

    // Order dari penawaran: tampilkan rincian penawaran sebagai acuan di
    // layar Input Order (item tetap diinput operator seperti biasa).
    $quotationRef = null;
    if (request()->filled('quotation') && \App\Support\AksesHarga::bolehLihat(auth()->user())) { // rincian penawaran memuat harga
        $q = \App\Models\Quotation::with('items')->find(request('quotation'));
        if ($q && in_array($q->status, ['Terkirim', 'Disetujui'], true)) {
            $quotationRef = $q;
        }
    }

    $desainerList = \App\Services\DesainKomisiService::daftarDesainer();
    $desainerDefault = \App\Services\DesainKomisiService::tentukanDesainer(null);
    // Desainer terkunci: Operator Desain yang sedang membuat nota TIDAK boleh
    // memilih nama desainer lain - ia adalah desainer yang mengerjakan item
    // itu. Role lain (Kasir/Administrator/Owner) tetap bebas memilih desainer
    // yang benar-benar bekerja pada item tersebut.
    $desainerTerkunci = \App\Services\DesainKomisiService::desainerTerkunci();
    $desainerTerkunciNama = $desainerTerkunci
        ? optional($desainerList->firstWhere('id_user', $desainerTerkunci))->nama
        : null;

    $produkSiapCetak = \App\Services\HargaService::produkDenganHargaSiapCetak();

    // MODE EDIT (koreksi selisih): layar Input Pesanan yang sama, terisi item
    // nota yang mau dikoreksi. Item yang tidak berubah tidak menyentuh stok
    // sama sekali - hanya selisihnya yang efek.
    $notaEdit = null;
    $cartAwal = [];
    if (request()->filled('edit')) {
        $notaEdit = DB::table('transaksi')
            ->join('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
            ->where('transaksi.id_transaksi', (int) request('edit'))
            ->select(
                'transaksi.id_transaksi',
                'transaksi.nomor_nota',
                'transaksi.id_konsumen',
                'transaksi.id_user_kasir',
                'transaksi.tgl_order',
                'transaksi.tipe_order',
                'transaksi.no_po',
                'transaksi.total_bayar',
                'transaksi.status_nota',
                'transaksi.status_order',
                'konsumen.nama_konsumen',
                'konsumen.telepon',
                'konsumen.id_golongan'
            )
            ->first();

        if (!$notaEdit) {
            abort(404);
        }
        $bolehEdit = \App\Services\NotaEditService::bolehEdit($notaEdit);
        if (!$bolehEdit['boleh']) {
            return back()->with('error', $bolehEdit['alasan']);
        }

        $cartAwal = $this->keranjangAwalEdit($notaEdit);
    }

    // MODE SUSULAN: layar Input Pesanan yang sama dipakai untuk menambah item
    // ke nota yang sudah jalan (?susulan={id_transaksi}). Data nota asal
    // dikunci di sini - operator menambah item, bukan membuat nota baru.
    $notaSusulan = null;
    // Item lama di nota asal + finishing-nya. Kosong di luar mode susulan.
    $notaSusulanItems = collect();
    $notaSusulanFinishing = collect();
    if (request()->filled('susulan')) {
        $notaSusulan = DB::table('transaksi')
            ->join('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
            ->where('transaksi.id_transaksi', (int) request('susulan'))
            ->select(
                'transaksi.id_transaksi',
                'transaksi.nomor_nota',
                'transaksi.id_konsumen',
                'transaksi.id_user_kasir',
                'transaksi.tgl_order',
                'transaksi.tipe_order',
                'transaksi.no_po',
                'transaksi.total_bayar',
                'transaksi.status_nota',
                'transaksi.status_order',
                'konsumen.nama_konsumen',
                'konsumen.telepon',
                'konsumen.id_golongan'
            )
            ->first();

        // Syarat addition sama persis dengan endpoint tambah-item, tapi di
        // sini dicegat lebih awal supaya operator tidak sampai isi form
        // panjang untuk nota yang memang tidak boleh ditambah.
        if (!$notaSusulan) {
            abort(404);
        }
        if ((int) $notaSusulan->id_user_kasir !== (int) Auth::id()) {
            abort(403, 'Hanya operator yang membuat nota ini yang boleh menambah item.');
        }
        if ($notaSusulan->status_nota !== 'Aktif' || !in_array($notaSusulan->status_order, ['Menunggu Kasir', 'Diterima Kasir'], true)) {
            abort(422, 'Nota ini belum bisa ditambah item (harus Aktif dan belum dibatalkan).');
        }
        if (Carbon::parse($notaSusulan->tgl_order)->toDateString() !== now()->toDateString()) {
            abort(422, 'Penambahan hanya boleh dilakukan di hari yang sama dengan nota.');
        }

        // Konsumen, golongan, tanggal, dan nomor nota dikunci ke nota asal.
        $konsumenAwal = collect([(object) [
            'id_konsumen' => $notaSusulan->id_konsumen,
            'nama_konsumen' => $notaSusulan->nama_konsumen,
            'telepon' => $notaSusulan->telepon,
            'id_golongan' => $notaSusulan->id_golongan,
        ]]);
        $no_nota = $notaSusulan->nomor_nota;
        $quotationRef = null;

        // Item yang SUDAH ada di nota asal. Ditampilkan di keranjang layar
        // susulan supaya operator tahu persis apa yang sudah tercatat -
        // tidak ada item yang terlewat atau sengaja diduplikasi.
        //
        // Sifatnya BACA SAJA. Mengubah isi nota yang sudah jalan punya jalur
        // sendiri ("Edit Nota" = batal + buat ulang) yang membalik stok,
        // jurnal, dan pembayaran secara berurutan, dan ikut tunduk pada
        // persetujuan edit di Pengaturan Aplikasi. Memuat item lama ke
        // keranjang susulan sebagai item yang bisa diedit akan
        // menghasilkan baris dobel - bukan koreksi.
        $notaSusulanItems = DB::table('transaksi_detail as d')
            ->leftJoin('produk as pr', 'd.id_produk', '=', 'pr.id_produk')
            ->leftJoin('jasa as js', 'd.id_jasa', '=', 'js.id_jasa')
            ->where('d.id_transaksi', $notaSusulan->id_transaksi)
            ->orderBy('d.id_detail')
            ->get([
                'd.id_detail', 'd.id_produk', 'd.id_jasa', 'd.nama_file', 'd.p_ukuran', 'd.l_ukuran',
                'd.qty', 'd.qty_cetak', 'd.subtotal', 'd.status_spk', 'd.is_susulan',
                'pr.nama_produk', 'js.nama_jasa',
            ]);

        // Nama finishing per baris item - supaya daftar item lama di layar
        // susulan menampilkan finishing yang sama seperti di detail nota.
        $notaSusulanFinishing = collect();
        if ($notaSusulanItems->isNotEmpty()) {
            $notaSusulanFinishing = DB::table('transaksi_detail_finishing as f')
                ->join('finishing as fi', 'f.id_finishing', '=', 'fi.id_finishing')
                ->whereIn('f.id_detail', $notaSusulanItems->pluck('id_detail'))
                ->orderBy('f.id')
                ->get(['f.id_detail', 'fi.nama_finishing'])
                ->groupBy('id_detail');
        }
    }

    // Kepala nota (pelanggan, golongan harga, tipe order, tanggal) dikunci di
    // mode susulan maupun mode edit - di kedua mode operator bekerja pada nota
    // yang sudah ada, bukan membuat nota baru.
    $notaKunci = $notaSusulan ?: $notaEdit;

    return view('penjualan.create', compact('produk', 'golongan', 'konsumenAwal', 'no_nota', 'kategori', 'finishing', 'jasa', 'quotationRef', 'desainerList', 'desainerDefault', 'desainerTerkunci', 'desainerTerkunciNama', 'produkSiapCetak', 'notaSusulan', 'notaSusulanItems', 'notaSusulanFinishing', 'notaEdit', 'notaKunci', 'cartAwal'));
}

    public function store(Request $request)
    {
        return app(\App\Services\PenjualanCheckoutService::class)->store($request);
    }

/**
 * Upload thumbnail gambar order (dipanggil terpisah dari /penjualan/store
 * begitu operator memilih file - payload store tetap JSON seperti semula,
 * bukan multipart, jadi cuma path hasil upload ini yang ikut terbawa di
 * item keranjang). Disimpan di storage/app/public/order_thumbnails, publik
 * lewat symlink public/storage.
 */
    /**
     * Tulis satu item produk ke nota: baris transaksi_detail, rincian
     * finishing, pemakaian bahan (potong stok + kartu_stok + snapshot
     * bahan_pakai), lalu HPP item ini.
     *
     * Dipakai oleh store() (nota baru & tambah item susulan) DAN oleh
     * updateItemNota() (edit selisih) - jadi aturan pemakaian bahan &
     * pencatatan stok hanya ada SATU implementasi, tidak mungkin ada dua
     * versi yang berbeda.
     *
     * @param  string  $referensiKartu  Referensi yang ditulis di kartu_stok.
     * @param  string  $keteranganKartu Keterangan yang ditulis di kartu_stok.
     * @param  bool    $isSusulan       Baris ini item susulan (bukan item asli).
     * @param  int     $idDetailLama    0 = baris baru. >0 = baris itu yang
     *                                    diperbarui (mode EDIT SELISIH) -
     *                                    id_detail sengaja tidak berubah
     *                                    supaya link SPK & Berkas Order
     *                                    yang sudah dicetak tetap berlaku.
     * @return array{0: int, 1: float} [id_detail, HPP item ini]
     */
    private function tulisItemProduk(array $item, int $idTransaksi, string $referensiKartu, string $keteranganKartu, bool $isSusulan, int $idDetailLama = 0): array
    {
        return app(\App\Services\PenjualanCheckoutService::class)->tulisItemProduk(
            $item, $idTransaksi, $referensiKartu, $keteranganKartu, $isSusulan, $idDetailLama
        );
    }

    /**
     * Perbarui baris item produk yang sudah ada (mode EDIT SELISIH).
     *
     * Baris diperbarui DI TEMPAT - id_detail tidak berubah, jadi link SPK &
     * Berkas Order yang sudah dicetak untuk item ini tetap berlaku.
     *
     * status_spk & is_susulan SENGAJA tidak disentuh: status produksi item
     * tidak boleh berubah diam-diam karena operator mengoreksi nominalnya.
     */
    private function perbaruiBarisItemProduk(array $item, int $idDetail, array $komisiDesain, $jumlah_order): int
    {
        return app(\App\Services\PenjualanCheckoutService::class)->perbaruiBarisItemProduk(
            $item, $idDetail, $komisiDesain, $jumlah_order
        );
    }

    /**
     * Tulis rincian finishing milik satu item nota.
     *
     * Rincian lama (kalau ada) dibuang lebih dulu - dipakai mode EDIT
     * SELISIH, di mana daftar finishing bisa berubah.
     */
    private function tulisFinishingItem(int $idDetail, array $daftarFinishing): void
    {
        app(\App\Services\PenjualanCheckoutService::class)->tulisFinishingItem($idDetail, $daftarFinishing);
    }

    /**
     * Potong stok bahan untuk satu item: catat kartu_stok (tipe OUT), tulis
     * snapshot pemakaian bahan per baris item, lalu hitung HPP item ini.
     *
     * Dipisah dari tulisItemProduk() supaya mode EDIT SELISIH bisa memakai
     * bagian yang sama - hanya bedanya, mode edit memanggilnya SESUDAH
     * pemakaian bahan yang lama dikembalikan, sehingga efeknya hanya
     * sebesar selisihnya.
     */
    private function pakaiBahanItem(array $item, int $idDetail, string $referensiKartu, string $keteranganKartu, $jumlah_order): float
    {
        return app(\App\Services\PenjualanCheckoutService::class)->pakaiBahanItem(
            $item, $idDetail, $referensiKartu, $keteranganKartu, $jumlah_order
        );
    }

    /**
     * Apakah isi item nota BERUBAH dibanding isi yang tersimpan.
     *
     * Yang dibandingkan adalah hal yang benar-benar mengubah pemakaian
     * bahan & isi pekerjaan: produk/jasa, ukuran, qty, nama file, catatan
     * produksi, urgensi, dan daftar finishing.
     *
     * NOMINAL SENGAJA TIDAK dibandingkan. Harga selalu dihitung ulang dari
     * master, jadi item yang tidak disentuh operator pun boleh ikut harga
     * terbaru - dan itu tidak boleh dianggap "berubah" (kalau iya, stok
     * akan dikembalikan lalu dipotong lagi tanpa ada gunanya).
     */
    private function itemBerubah(object $lama, array $item): bool
    {
        $tandaFinishingLama = DB::table('transaksi_detail_finishing')
            ->where('id_detail', $lama->id_detail)
            ->orderBy('id_finishing')
            ->get(['id_finishing', 'status_finishing', 'qty_pakai'])
            ->map(fn ($f) => [
                (int) $f->id_finishing,
                (string) $f->status_finishing,
                (float) ($f->qty_pakai ?? 0),
            ])->all();

        $tandaFinishingBaru = collect($item['finishing_list'] ?? [])
            ->sortBy(fn ($f) => (int) $f['id_finishing'])
            ->map(fn ($f) => [
                (int) $f['id_finishing'],
                (string) $f['status_finishing'],
                (float) ($f['qty_pakai'] ?? 0),
            ])->all();

        if ($tandaFinishingLama != $tandaFinishingBaru) {
            return true;
        }

        return (int) ($lama->id_produk ?? 0) !== (int) ($item['id_produk'] ?? 0)
            || (int) ($lama->id_jasa ?? 0) !== (int) ($item['id_jasa'] ?? 0)
            || (float) $lama->p_ukuran !== (float) ($item['p'] ?? 0)
            || (float) $lama->l_ukuran !== (float) ($item['l'] ?? 0)
            || (int) $lama->qty !== (int) ($item['qty_input'] ?? 0)
            || (string) $lama->nama_file !== (string) ($item['nama_file'] ?? '')
            || (string) ($lama->catatan_produksi ?? '') !== (string) ($item['catatan_produksi'] ?? '')
            || (string) $lama->status_urgensi !== (string) ($item['status_urgensi'] ?? '')
            || (int) $lama->file_siap_cetak !== (int) ($item['file_siap_cetak'] ? 1 : 0);
    }

    /**
     * Perbarui item produk yang TIDAK berubah isinya: nominal disegarkan
     * dari master, tapi stok & pemakaian bahan tidak disentuh sama sekali.
     *
     * Dipakai mode EDIT SELISIH untuk item yang operatornya tidak sentuh -
     * item seperti ini harus benar-benar tidakLeaving jejak di kartu_stok.
     */
    private function perbaruiItemProdukTanpaStok(array $item, int $idDetail): void
    {
        $komisiDesain = \App\Services\DesainKomisiService::hitung($item['id_desainer'], (float) $item['biaya_desain']);
        $this->perbaruiBarisItemProduk($item, $idDetail, $komisiDesain, $item['qty_input']);
        $this->tulisFinishingItem($idDetail, $item['finishing_list'] ?? []);
    }

    /**
     * Simpan koreksi nota (EDIT SELISIH).
     *
     * Berbeda dengan alur lama (batal + buat ulang), nota di sini TIDAK
     * dibatalkan dan pembayarannya tidak diubah. Yang efeknya hanya selisih:
     *
     *   - item baru     : stok dipotong, baris baru dibuat
     *   - item diubah   : pemakaian bahan lama dikembalikan, yang baru dipotong
     *   - item dihapus  : pemakaian bahan dikembalikan, baris dihapus
     *   - item tetap    : hanya nominal yang disegarkan dari master, stok
     *                     tidak disentuh sama sekali
     *
     * Jurnal pendapatan direkonsiliasi sebesar total yang baru
     * (AccountingService::sesuaikanTotalNota) dan jurnal HPP disesuaikan
     * sebesar selisih HPP - bukan dibalik lalu ditulis ulang, supaya
     * riwayat buku besar tetap menjelaskan apa yang terjadi.
     */
    public function updateItemNota($id, Request $request)
    {
        if (!$request->items || count($request->items) == 0) {
            return response()->json(['status' => 'error', 'msg' => 'Keranjang kosong!'], 400);
        }

        $id = (int) $id;
        $transaksi = DB::table('transaksi')->where('id_transaksi', $id)->first();
        if (!$transaksi) {
            abort(404);
        }

        $boleh = \App\Services\NotaEditService::bolehEdit($transaksi);
        if (!$boleh['boleh']) {
            return response()->json(['status' => 'error', 'msg' => $boleh['alasan']], 422);
        }

        // Kepala nota terkunci: pelanggan & golongan harga diambil dari nota,
        // bukan dari kiriman browser.
        $idKonsumen = (int) $transaksi->id_konsumen;
        $idGolongan = (int) DB::table('konsumen')->where('id_konsumen', $idKonsumen)->value('id_golongan');

        $hargaTerkunci = !\App\Support\AksesHarga::bolehLihat(Auth::user());
        $bolehInputBiayaDesain = \App\Support\AksesHarga::bolehInputBiayaDesain(Auth::user());

        $itemsLama = DB::table('transaksi_detail')->where('id_transaksi', $id)->get()->keyBy('id_detail');

        try {
            $itemsTervalidasi = $this->validasiItems(
                $request,
                $idGolongan,
                $hargaTerkunci,
                $bolehInputBiayaDesain,
                $itemsLama,
                fn ($lama, $item) => $this->itemBerubah($lama, $item)
            );
        } catch (\App\Exceptions\ValidasiNotaException $e) {
            return response()->json(['status' => 'error', 'msg' => $e->getMessage()], 422);
        }

        $noNota = $transaksi->nomor_nota;

        DB::beginTransaction();
        try {
            // Kunci nota: dua simpan bersamaan untuk nota yang sama tidak
            // boleh sama-sama menulis.
            $transaksi = DB::table('transaksi')->where('id_transaksi', $id)->lockForUpdate()->first();
            $boleh = \App\Services\NotaEditService::bolehEdit($transaksi);
            if (!$boleh['boleh']) {
                throw new \RuntimeException($boleh['alasan']);
            }

            $itemLama = DB::table('transaksi_detail')->where('id_transaksi', $id)->get()->keyBy('id_detail');
            $hppLama = (float) $itemLama->sum('hpp_snapshot');

            $totalBaru = 0.0;
            $hppBaru = 0.0;
            $idDetailDipakai = [];
            $jumlahPerubahan = ['baru' => 0, 'diubah' => 0, 'dihapus' => 0, 'tetap' => 0];

            foreach ($itemsTervalidasi as $index => $item) {
                $idDetailLama = isset($item['id_detail']) ? (int) $item['id_detail'] : 0;
                $lama = $idDetailLama ? $itemLama->get($idDetailLama) : null;

                if ($lama) {
                    // id_detail harus milik nota ini - kalau tidak, operator
                    // bisa menimpa baris nota orang lain.
                    if ((int) $lama->id_transaksi !== $id) {
                        throw new \RuntimeException('Item pada baris ' . ($index + 1) . ' bukan milik nota ini.');
                    }
                    $idDetailDipakai[] = $idDetailLama;
                }

                if ($lama && !$this->itemBerubah($lama, $item)) {
                    // TIDAK BERUBAH: hanya nominal yang disegarkan dari
                    // master. Stok & pemakaian bahan tidak disentuh.
                    $this->perbaruiItemProdukTanpaStok($item, $idDetailLama);
                    $totalBaru += (float) $item['subtotal'];
                    $hppBaru += (float) $lama->hpp_snapshot;
                    $jumlahPerubahan['tetap']++;

                    continue;
                }

                if ($lama) {
                    // BERUBAH: pemakaian bahan lama dikembalikan dulu, lalu
                    // yang baru dipotong - efek bersihnya cuma sebesar
                    // selisihnya.
                    StokReversalService::kembalikan(
                        $lama,
                        'EDIT-NOTA-' . $id,
                        'Koreksi nota #' . $noNota . ' - pemakaian bahan dikembalikan'
                    );
                    $jumlahPerubahan['diubah']++;
                } else {
                    $jumlahPerubahan['baru']++;
                }

                [$idDetailBaru, $hppItem] = $this->tulisItemProduk(
                    $item,
                    $id,
                    $noNota . ' (edit)',
                    'Pemakaian produksi order ' . $noNota . ' (koreksi)',
                    false,
                    $idDetailLama
                );
                $totalBaru += (float) $item['subtotal'];
                $hppBaru += $hppItem;
            }

            // Item yang ada di nota tapi TIDAK ada di kiriman -> dihapus:
            // pemakaian bahan dikembalikan, baris & rinciannya dihapus.
            foreach ($itemLama as $idDetail => $lama) {
                if (in_array((int) $idDetail, $idDetailDipakai, true)) {
                    continue;
                }
                StokReversalService::kembalikan(
                    $lama,
                    'EDIT-NOTA-' . $id,
                    'Koreksi nota #' . $noNota . ' - item dihapus, bahan dikembalikan'
                );
                DB::table('transaksi_detail_finishing')->where('id_detail', $idDetail)->delete();
                DB::table('transaksi_detail')->where('id_detail', $idDetail)->delete();
                $jumlahPerubahan['dihapus']++;
            }

            if ($totalBaru <= 0) {
                throw new \RuntimeException('Total nota tidak boleh Rp 0 - tidak ada data yang tersimpan; coba lagi atau hubungi Administrator.');
            }

            $totalTerbayar = (float) DB::table('pembayaran_riwayat')->where('id_transaksi', $id)->sum('nominal');
            if ($totalTerbayar > 0 && $totalBaru + 0.004 < $totalTerbayar) {
                throw new \App\Exceptions\ValidasiNotaException('Total baru (' . number_format($totalBaru, 0, ',', '.') . ') lebih kecil dari pembayaran yang sudah masuk (' . number_format($totalTerbayar, 0, ',', '.') . '). Gunakan retur/refund.');
            }

            // Jurnal pendapatan direkonsiliasi sebesar total yang baru.
            AccountingService::sesuaikanTotalNota($noNota, $totalBaru);

            // Jurnal HPP disesuaikan sebesar selisih HPP.
            $selisihHpp = round($hppBaru - $hppLama, 2);
            if ($selisihHpp > 0) {
                AccountingService::postHppBahanTerpakai(
                    $noNota,
                    $selisihHpp,
                    'Penyesuaian HPP koreksi nota ' . $noNota . ' (pemakaian bahan bertambah)'
                );
            } elseif ($selisihHpp < 0) {
                AccountingService::postPembalikanHppItem($noNota, 'Penyesuaian HPP koreksi nota ' . $noNota . ' (pemakaian bahan berkurang)', abs($selisihHpp));
            }

            // Status pembayaran dihitung ulang dari total yang baru.
            $totalTerbayar = (float) DB::table('pembayaran_riwayat')->where('id_transaksi', $id)->sum('nominal');
            $statusBaru = \App\Services\StatusPembayaran::dari($totalBaru, $totalTerbayar);

            DB::table('transaksi')->where('id_transaksi', $id)->update([
                'total_bayar' => $totalBaru,
                'status_pembayaran' => $statusBaru,
                'updated_at' => now(),
            ]);

            \App\Services\AuditLogger::log(
                'updated',
                'transaksi',
                $id,
                ['total' => (float) $transaksi->total_bayar, 'item' => $itemLama->count()],
                ['total' => $totalBaru, 'item' => $jumlahPerubahan],
                'Koreksi nota ' . $noNota . ' (selisih): baru ' . $jumlahPerubahan['baru']
                    . ', diubah ' . $jumlahPerubahan['diubah']
                    . ', dihapus ' . $jumlahPerubahan['dihapus']
                    . ', tetap ' . $jumlahPerubahan['tetap']
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            $status = $e instanceof \App\Exceptions\ValidasiNotaException ? 422 : 500;
            return response()->json([
                'status' => 'error',
                'msg' => \App\Support\PesanError::untuk($e),
            ], $status);
        }

        session()->flash('success', "Nota {$noNota} berhasil dikoreksi.");

        return response()->json([
            'status' => 'success',
            'msg' => 'Nota ' . $noNota . ' berhasil dikoreksi.',
            'id_transaksi' => $id,
            'nomor_nota' => $noNota,
            'total_baru' => $totalBaru,
            'perubahan' => $jumlahPerubahan,
        ]);
    }

    /**
     * Susun keranjang awal untuk mode EDIT: item-item nota yang dikoreksi,
     * dengan BENTUK YANG SAMA PERSIS dengan item yang dibuat di layar Input
     * Pesanan (lihat tambahKeKeranjang) supaya operator bisa langsung
     * mengubahnya - dan supaya server bisa mencocokkannya kembali ke baris
     * nota lewat id_detail.
     *
     * id_detail ikut dibawa supaya saat disimpan server tahu baris mana
     * yang diperbarui (diperbarui di tempat, bukan dibuat ulang) dan
     * barang mana yang memang baru.
     */
    private function keranjangAwalEdit(object $nota): array
    {
        $itemNota = DB::table('transaksi_detail')
            ->leftJoin('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
            ->leftJoin('jasa', 'transaksi_detail.id_jasa', '=', 'jasa.id_jasa')
            // Kategori produk diambil dari bahan bakunya (produk tidak punya
            // kolom kategori sendiri) - sama seperti query produk di create().
            ->leftJoin('bom_detail', 'transaksi_detail.id_produk', '=', 'bom_detail.id_produk')
            ->leftJoin('bahan_baku', 'bom_detail.id_bahan', '=', 'bahan_baku.id_bahan')
            ->where('transaksi_detail.id_transaksi', (int) $nota->id_transaksi)
            ->orderBy('transaksi_detail.id_detail')
            ->get([
                'transaksi_detail.id_detail',
                'transaksi_detail.id_produk',
                'transaksi_detail.id_jasa',
                'transaksi_detail.nama_file',
                'transaksi_detail.thumbnail',
                'transaksi_detail.p_ukuran',
                'transaksi_detail.l_ukuran',
                'transaksi_detail.qty',
                'transaksi_detail.qty_cetak',
                'transaksi_detail.biaya_cetak',
                'transaksi_detail.biaya_desain',
                'transaksi_detail.biaya_finishing',
                'transaksi_detail.id_finishing',
                'transaksi_detail.id_desainer',
                'transaksi_detail.file_siap_cetak',
                'transaksi_detail.status_urgensi',
                'transaksi_detail.status_finishing',
                'transaksi_detail.catatan_produksi',
                'transaksi_detail.subtotal',
                'produk.nama_produk',
                'produk.metode_hitung',
                'jasa.nama_jasa',
                DB::raw('COALESCE(bahan_baku.id_kategori, 0) as id_kategori'),
            ])->unique('id_detail')->values();

        $finishingNota = DB::table('transaksi_detail_finishing as f')
            ->join('finishing as fi', 'f.id_finishing', '=', 'fi.id_finishing')
            ->whereIn('f.id_detail', $itemNota->pluck('id_detail'))
            ->orderBy('f.id')
            ->get([
                'f.id_detail', 'f.id_finishing', 'f.status_finishing', 'f.qty_pakai', 'f.biaya', 'f.catatan',
                'f.lebar_potong', 'f.tinggi_potong', 'f.lebar_media', 'f.tinggi_media', 'f.keping',
                'fi.nama_finishing', 'fi.metode_hitung',
            ])
            ->groupBy('id_detail');

        $keranjang = [];
        foreach ($itemNota as $baris) {
            $daftarFinishing = $finishingNota->get($baris->id_detail, collect())
                ->map(fn ($f) => [
                    'id_finishing' => (int) $f->id_finishing,
                    'nama' => $f->nama_finishing,
                    'metode' => $f->metode_hitung,
                    'status' => $f->status_finishing,
                    'qty_pakai' => $f->qty_pakai,
                    'biaya' => (float) $f->biaya,
                    'catatan' => $f->catatan,
                    'lebar_potong' => $f->lebar_potong,
                    'tinggi_potong' => $f->tinggi_potong,
                    'keping' => $f->keping,
                    'lebar_media' => $f->lebar_media,
                    'tinggi_media' => $f->tinggi_media,
                ])->values()->all();

            $qty = (float) $baris->qty;
            $keranjang[] = [
                // Penanda baris nota asal - server memakainya untuk tahu
                // item mana yang diperbarui & item mana yang baru.
                'id_detail' => (int) $baris->id_detail,
                'id_produk' => $baris->id_produk,
                'id_jasa' => $baris->id_jasa,
                'nama_produk' => $baris->nama_produk ?: ($baris->nama_jasa ?: $baris->nama_file),
                'nama_file' => $baris->nama_file,
                'thumbnail' => $baris->thumbnail,
                'p' => (float) $baris->p_ukuran,
                'l' => (float) $baris->l_ukuran,
                'qty_input' => $qty,
                'qty_hitung' => (float) ($baris->qty_cetak ?? $qty),
                'harga_satuan' => $qty > 0 ? round((float) $baris->subtotal / $qty, 2) : (float) $baris->subtotal,
                'biaya_desain' => (float) $baris->biaya_desain,
                'id_desainer' => $baris->id_desainer,
                'siap_cetak' => (bool) $baris->file_siap_cetak,
                'biaya_cetak' => (float) $baris->biaya_cetak,
                'cetak_manual' => false,
                'id_finishing' => $daftarFinishing[0]['id_finishing'] ?? null,
                'nama_finishing' => $daftarFinishing ? implode(' + ', array_column($daftarFinishing, 'nama')) : null,
                'finishing' => $daftarFinishing,
                'biaya_finishing' => (float) $baris->biaya_finishing,
                'status_urgensi' => $baris->status_urgensi,
                'status_finishing' => $baris->status_finishing,
                'catatan_produksi' => $baris->catatan_produksi,
                'subtotal' => (float) $baris->subtotal,
                'id_kategori' => $baris->id_kategori,
            ];
        }

        return $keranjang;
    }

    /**
     * Validasi item kiriman: harga, finishing, dan catatan produksi dihitung
     * ULANG dari
     * master (produk + setting_harga + finishing) memakai hanya input mentah
     * yang tidak bisa dipalsukan (p, l, qty) - nilai dari browser untuk
     * harga SELALU diabaikan.
     *
     * Dipakai oleh store() (nota baru & tambah item susulan) dan oleh
     * updateItemNota() (edit selisih) - jadi kedua alur memakai ATURAN HARGA
     * YANG SAMA PERSIS, tidak ada dua implementasi yang bisa berbeda.
     *
     * @return array<int, array> Baris item yang sudah tervalidasi.
     *
     * @throws \App\Exceptions\ValidasiNotaException Bila ada baris yang tidak valid.
     */
    private function validasiItems(Request $request, int $idGolongan, bool $hargaTerkunci, bool $bolehInputBiayaDesain): array
    {
        return app(\App\Services\PenjualanCheckoutService::class)->validasiItems(
            $request, $idGolongan, $hargaTerkunci, $bolehInputBiayaDesain
        );
    }

/**
 * Upload gambar acuan desain (thumbnail item order).
 *
 * Format yang diterima LEBAR: semua gambar umum (jpg, jpeg, png, webp, gif,
 * bmp, svg, tif, tiff, heic, avif) plus dokumen desain (pdf, cdr, ai, eps,
 * psd). Gambar dikompres di peramban sampai 200 KB supaya tetap jernih saat
 * dicetak di SPK; dokumen desain tidak bisa dikompres (browser tidak bisa
 * membukanya sebagai gambar) jadi dikirim apa adanya.
 *
 * Ekstensi dicek dari nama file asal, bukan dari tebakan MIME - format
 * desain seperti CDR punya MIME generik (application/octet-stream) yang
 * akan ditolak aturan mimes padahal berkasnya benar. Daftar formatnya satu
 * sumber kebenaran di App\Support\BerkasDesain.
 */
public function uploadThumbnail(Request $request)
{
    $file = $request->file('thumbnail');

    // Format & ukuran dicek sendiri (bukan aturan 'mimes'), karena aturan itu
    // menebak ekstensi dari MIME - dan format desain seperti CDR punya MIME
    // generik (application/octet-stream) yang akan ditolak padahal berkasnya
    // benar. Daftar formatnya satu sumber kebenaran di App\Support\BerkasDesain.
    //
    // Error-nya tetap dikembalikan sebagai error validasi Laravel (422 +
    // errors.thumbnail) supaya sama dengan jalur validasi lain di endpoint ini.
    $validator = \Illuminate\Support\Facades\Validator::make(
        ['thumbnail' => $file],
        [
            'thumbnail' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    if (!$value instanceof \Illuminate\Http\UploadedFile) {
                        return;
                    }

                    $ekstensi = \App\Support\BerkasDesain::ekstensi($value->getClientOriginalExtension());
                    if (!\App\Support\BerkasDesain::diizinkan($ekstensi)) {
                        $fail('Format file tidak didukung. Yang boleh: ' . \App\Support\BerkasDesain::daftarFormat() . '.');

                        return;
                    }

                    $maksKb = \App\Support\BerkasDesain::maksKilobyte($ekstensi);
                    if ($value->getSize() > $maksKb * 1024) {
                        $fail('Ukuran file terlalu besar (maksimal ' . number_format($maksKb / 1024, 0, ',', '.') . ' MB).');
                    }
                },
            ],
        ]
    );

    if ($validator->fails()) {
        throw new \Illuminate\Validation\ValidationException($validator);
    }

    $ekstensi = \App\Support\BerkasDesain::ekstensi($file->getClientOriginalExtension());

    // Nama berkas di disk diacak, tapi EKSTENSI ASLI dipertahankan. Kalau
    // ekstensinya hilang (mis. jadi .bin), peramban tidak bisa menampilkan
    // gambarnya dan file desainnya tidak bisa dibuka tim produksi.
    //
    // Catatan format: PDF sudah dirender jadi gambar di peramban (pdf.js),
    // jadi berkas yang tiba di sini untuk PDF hanya kalau peramban gagal
    // merendernya. Berkas desain lain (CDR, AI, EPS, PSD) tidak bisa
    // dirender di peramban maupun di server (tidak ada ImageMagick/
    // Ghostscript/LibreOffice di sini, dan thumbnail Windows hanya
    // menghasilkan ikon file generik - bukan isi desainnya). Berkas seperti
    // itu disimpan apa adanya sebagai sumber desain, dan SPK menampilkan
    // keterangan formatnya, bukan gambar rusak.
    $path = $file->storeAs(
        'order_thumbnails',
        \Illuminate\Support\Str::random(40) . '.' . $ekstensi,
        'public'
    );

    return response()->json([
        'status' => 'success',
        'path' => $path,
        'url' => url('storage/' . $path),
        'bisa_ditampilkan' => \App\Support\BerkasDesain::bisaDitampilkan($ekstensi),
        'nama_asli' => $file->getClientOriginalName(),
    ]);
}

/**
 * Riwayat Nota - daftar transaksi penjualan dengan filter range tanggal.
 * Default: hanya menampilkan transaksi HARI INI jika tidak ada filter
 * tanggal yang dikirim. Murni query baca, tidak mengubah data apa pun.
 */
public function riwayat(Request $request)
{
    // P8I: tolak format tanggal yang rusak sebelum dipakai di
    // Carbon::parse() di bawah - tanpa ini input rusak melempar 500.
    $request->validate([
        'from' => 'nullable|date',
        'to' => 'nullable|date',
        // Pencarian konsumen & nomor nota (permintaan owner 2026-11-02).
        'cari' => 'nullable|string|max:100',
    ]);

    // Pencarian teks: cocokkan nomor nota ATAU nama konsumen. %/_ pengguna
    // di-escape supaya tidak berubah jadi wildcard jika tak diinginkan.
    $cari = trim(strip_tags((string) $request->input('cari', '')));
    $cariAktif = $cari !== '';

    // Default HARI INI hanya masuk akal untuk lihat transaksi umum tanpa
    // filter - begitu ada id_konsumen (dipakai link "Riwayat Transaksi"
    // dari Master Konsumen), default HARI INI justru menyembunyikan
    // hampir seluruh histori pelanggan itu. Default melebar ke semua
    // tanggal kalau id_konsumen diisi TANPA rentang tanggal eksplisit.
    // Pencarian "cari" diperlakukan sama: nomor lama tetap harus ketemu.
    $filterKonsumen = $request->filled('id_konsumen');
    $from = $request->filled('from') ? $request->from : (($filterKonsumen || $cariAktif) ? null : date('Y-m-d'));
    $to   = $request->filled('to') ? $request->to : (($filterKonsumen || $cariAktif) ? null : date('Y-m-d'));

    $konsumenTerpilih = null;
    if ($filterKonsumen) {
        $konsumenTerpilih = DB::table('konsumen')->where('id_konsumen', $request->id_konsumen)->first();
    }

    // Permintaan edit TERBARU per nota (kalau ada) - dipakai riwayat.blade.php
    // untuk menampilkan tombol yang sesuai (Ajukan Edit / Menunggu / Disetujui-
    // proses sekarang / Ditolak). MAX(id_request) per id_transaksi lalu di-join
    // balik ke tabelnya - pola "ambil baris terbaru per grup" standar untuk
    // Query Builder biasa (tanpa window function).
    $latestEditReq = DB::table('nota_edit_request')
        ->select('id_transaksi', DB::raw('MAX(id_request) as max_id'))
        ->groupBy('id_transaksi');

    // Total terbayar per nota - dihitung SATU query untuk seluruh halaman
    // (dulu SUM per baris di bolehEdit -> N+1 pada daftar Riwayat).
    $totalTerbayarSum = DB::table('pembayaran_riwayat')
        ->select('id_transaksi', DB::raw('COALESCE(SUM(nominal), 0) as total_terbayar'))
        ->groupBy('id_transaksi');

    $transaksis = DB::table('transaksi')
        ->join('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
        ->leftJoin('golongan_konsumen', 'konsumen.id_golongan', '=', 'golongan_konsumen.id_golongan')
        ->leftJoin('users', 'transaksi.id_user_kasir', '=', 'users.id_user')
        ->leftJoin('karyawan', 'users.id_karyawan', '=', 'karyawan.id_karyawan')
        ->leftJoinSub($latestEditReq, 'latest_req', fn ($join) => $join->on('transaksi.id_transaksi', '=', 'latest_req.id_transaksi'))
        ->leftJoin('nota_edit_request', 'nota_edit_request.id_request', '=', 'latest_req.max_id')
        ->leftJoinSub($totalTerbayarSum, 'terbayar', fn ($join) => $join->on('transaksi.id_transaksi', '=', 'terbayar.id_transaksi'))
        // Perf dataset besar: rentang sargable untuk memakai index
        // (tgl_order,status_nota) - whereDate()/DATE() mematikan index.
        ->when($from, fn ($q) => $q->where('tgl_order', '>=', $from . ' 00:00:00'))
        ->when($to, fn ($q) => $q->where('tgl_order', '<', \Carbon\Carbon::parse($to)->addDay()->format('Y-m-d') . ' 00:00:00'))
        ->when($filterKonsumen, fn ($q) => $q->where('transaksi.id_konsumen', $request->id_konsumen))
        ->when($cariAktif, function ($q) use ($cari) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $cari);
            $q->where(function ($sub) use ($escaped) {
                $sub->where('transaksi.nomor_nota', 'like', '%' . $escaped . '%')
                    ->orWhere('konsumen.nama_konsumen', 'like', '%' . $escaped . '%');
            });
        })
        ->select(
            'transaksi.*',
            'konsumen.nama_konsumen',
            'golongan_konsumen.nama_golongan',
            'karyawan.nama_karyawan as nama_kasir',
            'nota_edit_request.id_request as edit_request_id',
            'nota_edit_request.status as edit_request_status',
            'nota_edit_request.catatan_approver as edit_request_catatan',
            // Total terbayar (dari LEFT JOIN subquery di atas) - dipakai
            // penandaRiwayat tanpa query tambahan per baris.
            DB::raw('COALESCE(terbayar.total_terbayar, 0) as total_terbayar'),
            // Penanda untuk syarat edit selisih, diambil sebagai subquery
            // supaya daftar riwayat tidak jadi N+1 query (satu nota satu
            // query, bisa ratusan baris).
            DB::raw("EXISTS (SELECT 1 FROM transaksi_detail d WHERE d.id_transaksi = transaksi.id_transaksi AND d.status_spk IN ('Selesai', 'Cetak')) as ada_item_selesai"),
            DB::raw("EXISTS (SELECT 1 FROM transaksi_detail d WHERE d.id_transaksi = transaksi.id_transaksi AND d.id_produk IS NOT NULL) as ada_item_produksi")
        )
        ->orderByDesc('tgl_order')
        ->paginate(20)
        ->withQueryString();

    $isAdmin = optional(optional($request->user())->role)->nama_role === 'Administrator';
    $pendingEditCount = $isAdmin ? DB::table('nota_edit_request')->where('status', 'Menunggu')->count() : 0;

    // Penanda "boleh diedit" per baris - sumbernya sama dengan yang dipakai
    // server saat menyimpan (NotaEditService), jadi tombol Edit di daftar dan
    // penolakan saat disimpan tidak bisa berbeda pendapat. Dihitung SEKALI
    // per baris (dulu 2x, dan pembayaran_riwayat di-SUM per baris).
    $transaksis->getCollection()->each(function ($t) {
        $penanda = \App\Services\NotaEditService::penandaRiwayat($t);
        $t->boleh_edit = $penanda['boleh'];
        $t->alasan_edit = $penanda['alasan'];
    });

    return view('penjualan.riwayat', compact('transaksis', 'from', 'to', 'konsumenTerpilih', 'isAdmin', 'pendingEditCount', 'cari'));
}

/**
 * Lihat Data - detail satu transaksi (read-only). Transaksi yang sudah
 * tersimpan SENGAJA tidak punya alur edit: stok bahan baku sudah dikurangi
 * dan jurnal akuntansi sudah diposting saat store(), jadi "edit" yang benar
 * butuh alur pembalikan stok + jurnal dulu (retur/void) - bukan sekadar
 * update baris seperti form biasa. Belum ada alur retur di aplikasi ini,
 * jadi daripada membangun edit yang setengah jadi dan berisiko merusak
 * integritas stok/jurnal, halaman ini murni tampilan.
 */
public function show($id)
{
    $transaksi = DB::table('transaksi')
        ->join('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
        ->leftJoin('golongan_konsumen', 'konsumen.id_golongan', '=', 'golongan_konsumen.id_golongan')
        ->leftJoin('users', 'transaksi.id_user_kasir', '=', 'users.id_user')
        ->leftJoin('karyawan', 'users.id_karyawan', '=', 'karyawan.id_karyawan')
        ->leftJoin('users as pembatal', 'transaksi.id_user_pembatal_nota', '=', 'pembatal.id_user')
        ->leftJoin('karyawan as karyawan_pembatal', 'pembatal.id_karyawan', '=', 'karyawan_pembatal.id_karyawan')
        ->where('transaksi.id_transaksi', $id)
        ->select(
            'transaksi.*',
            'konsumen.nama_konsumen',
            'konsumen.telepon',
            'golongan_konsumen.nama_golongan',
            'karyawan.nama_karyawan as nama_kasir',
            DB::raw('COALESCE(karyawan_pembatal.nama_karyawan, pembatal.username) as nama_pembatal_nota')
        )
        ->first();

    if (!$transaksi) {
        abort(404);
    }

    $items = DB::table('transaksi_detail')
        ->leftJoin('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
        ->leftJoin('jasa', 'transaksi_detail.id_jasa', '=', 'jasa.id_jasa')
        ->leftJoin('finishing', 'transaksi_detail.id_finishing', '=', 'finishing.id_finishing')
        ->where('id_transaksi', $id)
        ->select(
            'transaksi_detail.*',
            'produk.nama_produk',
            'produk.spk_lengkap',
            'jasa.nama_jasa',
            'finishing.nama_finishing'
        )
        ->get();

    $finishingItems = \App\Services\FinishingItemService::untukDetail($items->pluck('id_detail'));

    return view('penjualan.show', compact('transaksi', 'items', 'finishingItems'));
}

/**
 * Tambah item susulan ke nota yang sudah berjalan.
 *
 * Aturan (sesuai permintaan bisnis):
 *   - hanya operator yang membuat nota itu (transaksi.id_user_kasir)
 *   - hanya di hari yang sama (DATE(tgl_order) = hari ini)
 *   - hanya nota yang transaksinya sudah dibuka kasir (Diterima Kasir)
 *     dan masih Aktif
 *   - item baru masuk ke SPK sebagai 'Antre' (status_spk) dan ikut
 *     tercatat di kartu_stok + jurnal seperti nota awal
 *
 * Jika nota lama sudah Lunas, total_bayar naik sementara pembayaran lama
 * tetap. Status pembayaran otomatis turun jadi 'DP'/'Belum Bayar' karena
 * StatusPembayaran::sqlCase() membandingkan total_bayar vs terbayar, dan
 * rincian "Terbayar/Sisa" di Monitoring Kasir sudah saya perbaiki agar
 * memakai status turunan, jadi kasir melihat sisa = hanya tambahan.
 *
 * Dipisah dari store() supaya alur Input Order yang ada tidak disentuh.
 */
public function tambahItemSusulan($id, Request $request)
{
    // Alur "Tambah Order Susulan" memakai LAYAR Input Pesanan yang sama
    // (penjualan/create.blade.php mode susulan), jadi kasir merakit item
    // dengan form yang sama persis dengan order baru.
    //
    // Endpoint ini lalu meneruskan request ke store() dengan penanda
    // susulan_id. store() memakai jalur validasi item, penulisan baris
    // detail, pemotongan stok, dan posting jurnal yang SAMA dengan nota
    // baru - jadi tidak ada dua implementasi aturan harga yang bisa
    // berbeda. Yang membedakan cuma sumber data nota (konsumen, golongan,
    // tanggal, tipe order, nomor nota) dan penandaan baris sebagai susulan.
    //
    // Syarat addition (dikunci di store() dan diuji TambahItemSusulanTest):
    //   - hanya operator yang membuat nota itu (transaksi.id_user_kasir)
    //   - hanya di hari yang sama (DATE(tgl_order) = hari ini)
    //   - hanya nota yang transaksinya sudah dibuka kasir (Diterima Kasir)
    //     dan masih Aktif
    //   - item baru masuk ke SPK sebagai 'Antre' (status_spk) dan ikut
    //     tercatat di kartu_stok + jurnal seperti nota awal
    //
    // Jika nota lama sudah Lunas, total_bayar naik sementara pembayaran lama
    // tetap. Status pembayaran otomatis turun jadi 'DP'/'Belum Bayar' karena
    // StatusPembayaran::sqlCase() membandingkan total_bayar vs terbayar, dan
    // rincian "Terbayar/Sisa" di Monitoring Kasir sudah diperbaiki agar
    // memakai status turunan, jadi kasir melihat sisa = hanya tambahan.
    $request->merge(['susulan_id' => (int) $id]);

    // Kunci nota Lunas (keputusan bisnis final C): tambah susulan ke nota
    // yang sudah Lunas DITOLAK - pesan mengarahkan buat nota baru. Cek di
    // sini (sebelum store()) agar tidak masuk jalur penulisan sama sekali.
    $transaksiSusulan = DB::table('transaksi')->where('id_transaksi', (int) $id)->first();
    if ($transaksiSusulan) {
        $totalTerbayarSusulan = (float) DB::table('pembayaran_riwayat')->where('id_transaksi', (int) $id)->sum('nominal');
        if ((float) $transaksiSusulan->total_bayar > 0
            && \App\Services\StatusPembayaran::dari((float) $transaksiSusulan->total_bayar, $totalTerbayarSusulan) === \App\Services\StatusPembayaran::LUNAS) {
            return response()->json([
                'status'  => 'error',
                'msg'     => 'Nota sudah Lunas — tidak bisa ditambah item susulan. Buat nota baru untuk tambahan.',
                'message' => 'Nota sudah Lunas — tidak bisa ditambah item susulan. Buat nota baru untuk tambahan.',
            ], 422);
        }
    }

    return $this->store($request);
}

/**
 * Item hasil produksi (punya id_produk). Bahan, jasa, dan item custom dari Penjualan dan Jasa langsung berstatus
 * Selesai sejak nota dibuat karena tidak lewat produksi, jadi status itu tidak boleh mengunci koreksi/pembatalan.
 */
/**
 * HPP item yang SPK-nya sudah dibatalkan TANPA pengembalian bahan (baris snapshot pemakaian masih ada, karena
 * snapshot dihapus saat bahan dikembalikan). Bahan itu sudah terpakai, jadi HPP-nya tetap biaya walau notanya batal.
 */
private static function hppItemBatalTanpaKembali($items): float
{
    $nilai = 0.0;
    foreach ($items as $i) {
        if ($i->status_spk === 'Batal' && !empty($i->id_produk)
            && DB::table('transaksi_detail_bahan_pakai')->where('id_detail', $i->id_detail)->exists()) {
            $nilai += (float) $i->hpp_snapshot;
        }
    }

    return round($nilai, 2);
}

private static function adaItemProduksi($items): bool
{
    return $items->contains(fn ($i) => !empty($i->id_produk));
}

private static function itemProduksiSelesai($items): ?object
{
    return $items->first(fn ($i) => !empty($i->id_produk) && $i->status_spk === 'Selesai');
}

/**
 * Membatalkan SELURUH nota (bukan per-item SPK seperti
 * ProduksiController::cancel) - untuk kasus nota salah input (salah
 * konsumen, duplikat, dsb). Ini SOFT-CANCEL: nota TIDAK dihapus dari
 * database (nomor_nota, riwayat pembayaran, kartu stok yang sudah
 * tertaut tetap utuh untuk audit) - hanya ditandai status_nota =
 * 'Dibatalkan' dan seluruh item SPK di dalamnya ikut dibatalkan supaya
 * tidak ada yang lanjut mengerjakan nota yang sudah tidak valid.
 *
 * Diblokir kalau ada item yang sudah 'Selesai' atau nota sudah
 * 'Sudah Diambil' (collecting) - barang fisik kemungkinan sudah di
 * tangan konsumen, pembatalan di titik itu harus lewat proses retur
 * manual, bukan tombol ini.
 */
/**
 * Sesuai roadmap Approval Workflow ("Cancel Invoice" - jangan pakai
 * delete/perubahan langsung untuk transaksi keuangan): method ini
 * SEKARANG cuma MENGAJUKAN pembatalan, tidak lagi mengeksekusinya
 * langsung. Validasi cepat (nota valid, belum dibatalkan, dst) tetap di
 * sini supaya pengajuan yang jelas tidak valid ditolak seketika - tidak
 * perlu membebani approver dengan pengajuan yang pasti gagal. Eksekusi
 * SUNGGUHAN (lihat eksekusiPembatalanNota() di bawah) baru terjadi
 * setelah Administrator menyetujui lewat ApprovalController.
 */
public function batalkanNota(Request $request, $id)
{
    $request->validate([
        'alasan' => 'required|string|max:1000',
    ]);

    $transaksi = DB::table('transaksi')->where('id_transaksi', $id)->first();

    if (!$transaksi) {
        return back()->with('error', 'Nota tidak ditemukan.');
    }

    if ($transaksi->status_nota === 'Dibatalkan') {
        return back()->with('error', 'Nota ini sudah dibatalkan sebelumnya.');
    }

    $items = DB::table('transaksi_detail')->where('id_transaksi', $id)->get();

    if (self::adaItemProduksi($items) && $transaksi->status_collecting === 'Sudah Diambil') {
        return back()->with('error', 'Nota ini tidak bisa dibatalkan - barang sudah diambil konsumen (Collecting). Gunakan proses retur manual kalau memang diperlukan.');
    }

    $sudahSelesai = self::itemProduksiSelesai($items);
    if ($sudahSelesai) {
        return back()->with('error', 'Nota ini tidak bisa dibatalkan - ada item ("' . $sudahSelesai->nama_file . '") yang sudah Selesai diproduksi. Gunakan proses retur manual kalau memang diperlukan.');
    }

    // Cegah pengajuan dobel - kalau sudah ada pengajuan Menunggu untuk
    // nota yang sama, tidak perlu diajukan ulang.
    $sudahAda = \App\Models\ApprovalRequest::where('tipe_aksi', 'batalkan_nota')
        ->where('referensi_type', 'transaksi')
        ->where('referensi_id', $id)
        ->where('status', 'Menunggu')
        ->exists();
    if ($sudahAda) {
        return back()->with('error', 'Sudah ada pengajuan pembatalan untuk nota ini yang masih menunggu persetujuan.');
    }

    \App\Services\ApprovalService::ajukan(
        tipeAksi: 'batalkan_nota',
        referensiType: 'transaksi',
        referensiId: (int) $id,
        payload: [
            'alasan' => $request->input('alasan'),
            'kembalikan_stok' => $request->boolean('kembalikan_stok'),
        ],
        ringkasan: "Batalkan nota {$transaksi->nomor_nota}",
        alasanPengajuan: $request->input('alasan'),
    );

    return redirect()->route('penjualan.show', $id)
        ->with('success', 'Pengajuan pembatalan nota terkirim, menunggu persetujuan Administrator.');
}

/**
 * Eksekusi SUNGGUHAN pembatalan nota - dipanggil ApprovalController
 * setelah Administrator menyetujui pengajuan dari batalkanNota() di
 * atas. Isinya PERSIS logic yang dulu langsung jalan di batalkanNota().
 *
 * @return array<int, string> Ringkasan stok yang dikembalikan (kalau ada)
 */
public function eksekusiPembatalanNota(int $id, array $payload): array
{
    // P0-2: jalan persis SEKALI per nota - sebelumnya tidak ada guard, jadi
    // pembatalan notanya sendiri tidak idempoten (stok/jurnal bisa dobel
    // kalau eksekusi terpicu dua kali).
    $transaksi = DB::table('transaksi')->where('id_transaksi', $id)->lockForUpdate()->first();
    if (!$transaksi) {
        throw new \RuntimeException("Nota #{$id} tidak ditemukan - pembatalan tidak bisa dieksekusi.");
    }
    if ($transaksi->status_nota === 'Dibatalkan') {
        return []; // sudah dibatalkan sebelumnya - tidak ada yang perlu dilakukan
    }

    $items = DB::table('transaksi_detail')->where('id_transaksi', $id)->get();

    // Keadaan bisa berubah selama pengajuan menunggu: item produk selesai diproduksi atau barang sudah diambil.
    // Pemeriksaan saat pengajuan diulang di sini (setelah nota dikunci); bila tidak lolos, pengajuan tetap Menunggu
    // untuk ditolak, bukan membatalkan nota yang barangnya sudah di tangan pelanggan.
    if (self::adaItemProduksi($items) && $transaksi->status_collecting === 'Sudah Diambil') {
        throw new \RuntimeException("Nota {$transaksi->nomor_nota} tidak bisa dibatalkan: barangnya sudah diambil pelanggan sejak pengajuan dibuat. Tolak pengajuan ini; gunakan retur manual bila perlu.");
    }
    if ($selesai = self::itemProduksiSelesai($items)) {
        throw new \RuntimeException("Nota {$transaksi->nomor_nota} tidak bisa dibatalkan: item \"{$selesai->nama_file}\" sudah selesai diproduksi sejak pengajuan dibuat. Tolak pengajuan ini.");
    }

    $kembalikanStok = (bool) ($payload['kembalikan_stok'] ?? false);
    $hppTerpakai = self::hppItemBatalTanpaKembali($items);
    $ringkasanStok = [];

    foreach ($items as $item) {
        if ($item->status_spk === 'Batal') {
            continue;
        }

        // P9: tolak transisi SPK yang tidak sah ke Batal.
        \App\Services\StatusTransitionGuard::validateStatusSpk((string) $item->status_spk, 'Batal');

        DB::table('transaksi_detail')->where('id_detail', $item->id_detail)->update([
            'status_spk' => 'Batal',
        ]);

        if ($kembalikanStok) {
            $ringkasanStok = array_merge($ringkasanStok, StokReversalService::kembalikan(
                $item,
                'BATAL-NOTA-' . $id,
                'Pembatalan nota #' . $id . ' - stok dikembalikan'
            ));
        }
    }

    // P0-2: jurnal pendapatan/HPP nota DIBALIK saat pembatalan dieksekusi
    // (sesuai docs/PANDUAN_PENGGUNAAN.md: "jurnal dibalik"). Baris jurnal
    // PEMBAYARAN (referensi <nota>-BYR-*) sengaja TIDAK disentuh oleh
    // reversePenjualan() - uang yang sudah benar-benar diterima tetap
    // sah di buku besar; nota batal berarti pendapatannya hangus, BUKAN
    // uangnya hilang. Konsekuensinya piutang nota batal yang sudah
    // dibayar penuh berubah jadi saldo kas/bank atas nota yang batal -
    // ditindaklanjuti (retur uang / deposit) lewat proses manual &
    // terlihat di Laporan Piutang yang sudah mengecualikan nota batal.
    // Bahan tidak dikembalikan = sudah terpakai: HPP-nya tetap biaya (lihat reversePenjualan).
    AccountingService::reversePenjualan($transaksi->nomor_nota, $kembalikanStok);
    if ($kembalikanStok) {
        AccountingService::postHppBahanTerpakai($transaksi->nomor_nota, $hppTerpakai);
    }
    $this->kreditkanPembayaranNotaBatal($transaksi);

    // P9: guard status nota - Dibatalkan terminal, tidak boleh kembali ke Aktif.
    \App\Services\StatusTransitionGuard::validateStatusNota(
        (string) DB::table('transaksi')->where('id_transaksi', $id)->value('status_nota'),
        'Dibatalkan'
    );

    DB::table('transaksi')->where('id_transaksi', $id)->update([
        'status_nota' => 'Dibatalkan',
        // status_order mengikuti agar nota batal keluar dari antrean kasir/SPK.
        'status_order' => 'Dibatalkan',
        'alasan_batal_nota' => $payload['alasan'] ?? null,
        'tgl_dibatalkan_nota' => now(),
        'id_user_pembatal_nota' => Auth::id(),
    ]);

    return $ringkasanStok;
}

/**
 * Uang yang sudah masuk untuk nota yang dibatalkan tidak boleh menggantung: menjadi saldo deposit
 * pelanggan (bisa dipakai di nota pengganti), dengan jurnal Piutang -> Hutang Deposit dan jejak audit.
 * Dipanggil di dalam transaksi pembatalan, tepat sekali per nota (pembatalan dijaga status_nota).
 */
private function kreditkanPembayaranNotaBatal(object $transaksi): void
{
    $terbayar = round((float) DB::table('pembayaran_riwayat')->where('id_transaksi', $transaksi->id_transaksi)->sum('nominal'), 2);
    if ($terbayar <= 0) {
        return;
    }

    $data = [
        'id_konsumen' => $transaksi->id_konsumen,
        'tipe' => 'Setoran',
        'nominal' => $terbayar,
        'metode_bayar' => 'Kredit Nota Batal',
        'keterangan' => "Uang nota {$transaksi->nomor_nota} yang dibatalkan, menjadi saldo pelanggan",
        'tgl_deposit' => now()->toDateString(),
        'id_transaksi' => $transaksi->id_transaksi,
        'id_user' => Auth::id(),
        'created_at' => now(),
        'updated_at' => now(),
    ];
    $idDeposit = DB::table('konsumen_deposit')->insertGetId($data);
    AccountingService::postKreditNotaBatal($transaksi->nomor_nota, $terbayar);
    \App\Services\AuditLogger::log('created', 'konsumen_deposit', $idDeposit, null, $data, "Kredit pelanggan Rp " . number_format($terbayar, 0, ',', '.') . " dari nota batal {$transaksi->nomor_nota}");
}

/**
 * ALUR EDIT NOTA (lihat catatan di show() kenapa tidak ada edit langsung).
 * Kasir mengajukan permintaan lebih dulu - TIDAK langsung membuka akses
 * edit. Satu nota hanya boleh punya satu permintaan yang masih berjalan
 * (Menunggu/Disetujui-belum-dipakai) supaya tidak ada spam permintaan
 * ganda untuk nota yang sama.
 */
public function requestEditNota(Request $request, $id)
{
    // Mode langsung: bila fitur approval edit dinonaktifkan di
    // Pengaturan Aplikasi, operator yang membuat nota boleh langsung
    // mengoreksi tanpa persetujuan - eksekusi yang sama dengan
    // applyApprovedEdit, cuma tidak lewat antrian approval.
    if (!\App\Models\AppSetting::current()->approval_edit_nota) {
        $transaksi = DB::table('transaksi')->where('id_transaksi', $id)->first();
        if (!$transaksi) {
            abort(404);
        }
        if ((int) $transaksi->id_user_kasir !== (int) Auth::id()) {
            return back()->with('error', 'Hanya operator yang membuat nota ini yang boleh mengedit.');
        }
        if ($transaksi->status_nota !== 'Aktif' || $transaksi->status_order !== 'Diterima Kasir') {
            return back()->with('error', 'Nota ini tidak bisa diedit (status tidak memenuhi syarat).');
        }
        if (Carbon::parse($transaksi->tgl_order)->toDateString() !== now()->toDateString()) {
            return back()->with('error', 'Edit hanya boleh dilakukan di hari yang sama dengan nota.');
        }

        return $this->editLangsung($id);
    }

    $request->validate(['alasan' => 'required|string|max:1000']);

    // Hardening P1-2: cek "sudah ada permintaan berjalan?" lalu insert SEBELUMNYA
    // tidak dikunci sama sekali - dua klik/tab bersamaan bisa sama-sama lolos
    // cek (belum ada yang commit) dan menghasilkan DUA permintaan Menunggu
    // untuk nota yang sama. transaksi.id_transaksi PASTI ada untuk nota yang
    // valid, jadi mengunci baris itu (bukan baris nota_edit_request yang belum
    // tentu ada) cukup untuk membuat cek+insert ini serial per-nota.
    $pesanError = null;
    DB::transaction(function () use ($id, $request, &$pesanError) {
        $transaksi = DB::table('transaksi')->where('id_transaksi', $id)->lockForUpdate()->first();
        if (!$transaksi) {
            $pesanError = 'Nota tidak ditemukan.';

            return;
        }
        if ($transaksi->status_nota === 'Dibatalkan') {
            $pesanError = 'Nota ini sudah dibatalkan, tidak perlu diedit.';

            return;
        }

        $adaPermintaanBerjalan = DB::table('nota_edit_request')
            ->where('id_transaksi', $id)
            ->whereIn('status', ['Menunggu', 'Disetujui'])
            ->exists();
        if ($adaPermintaanBerjalan) {
            $pesanError = 'Nota ini sudah punya permintaan edit yang masih berjalan (menunggu/sudah disetujui).';

            return;
        }

        DB::table('nota_edit_request')->insert([
            'id_transaksi' => $id,
            'id_user_requester' => Auth::id(),
            'alasan' => $request->input('alasan'),
            'status' => 'Menunggu',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    if ($pesanError) {
        return back()->with('error', $pesanError);
    }

    return back()->with('success', 'Permintaan edit nota dikirim, menunggu persetujuan Administrator.');
}

/**
 * Semua route /penjualan/* (termasuk tiga method approval di bawah ini)
 * berbagi satu aturan menu_access ('penjualan' => [3]) yang sama dengan
 * create/store/riwayat - jadi kasir biasa pun lolos middleware CheckMenuAccess
 * untuk URL ini. Approval edit nota HARUS Administrator-only (itu intinya
 * fitur ini - anti "orang sembarangan tangan" mengedit nota), jadi dicek
 * eksplisit di sini, sama seperti SpkController::index() mendeteksi admin.
 */
private function pastikanAdministrator(Request $request): ?\Illuminate\Http\RedirectResponse
{
    $isAdmin = optional(optional($request->user())->role)->nama_role === 'Administrator';
    if (!$isAdmin) {
        return redirect()->route('penjualan.riwayat')->with('error', 'Halaman ini khusus Administrator.');
    }
    return null;
}

/**
 * Daftar permintaan edit nota - HANYA untuk Administrator.
 */
public function editApprovalIndex(Request $request)
{
    if ($redirect = $this->pastikanAdministrator($request)) {
        return $redirect;
    }

    $query = DB::table('nota_edit_request')
        ->join('transaksi', 'nota_edit_request.id_transaksi', '=', 'transaksi.id_transaksi')
        ->join('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
        ->join('users as requester', 'nota_edit_request.id_user_requester', '=', 'requester.id_user')
        ->leftJoin('karyawan as karyawan_requester', 'requester.id_karyawan', '=', 'karyawan_requester.id_karyawan')
        ->leftJoin('users as approver', 'nota_edit_request.id_user_approver', '=', 'approver.id_user')
        ->leftJoin('karyawan as karyawan_approver', 'approver.id_karyawan', '=', 'karyawan_approver.id_karyawan')
        ->select(
            'nota_edit_request.*',
            'transaksi.nomor_nota',
            'transaksi.total_bayar',
            'konsumen.nama_konsumen',
            DB::raw('COALESCE(karyawan_requester.nama_karyawan, requester.username) as nama_requester'),
            DB::raw('COALESCE(karyawan_approver.nama_karyawan, approver.username) as nama_approver')
        );

    if ($request->filled('status')) {
        $query->where('nota_edit_request.status', $request->status);
    } else {
        $query->where('nota_edit_request.status', 'Menunggu');
    }

    $requests = $query->orderByDesc('nota_edit_request.created_at')->paginate(20)->withQueryString();

    return view('penjualan.edit-approval', compact('requests'));
}

public function approveEditNota(Request $request, $id)
{
    if ($redirect = $this->pastikanAdministrator($request)) {
        return $redirect;
    }

    // Hardening P1-2: lock + cek ulang status di dalam transaction supaya
    // klik ganda tidak bisa menimpa keputusan (approve/reject) yang sudah
    // tercatat lebih dulu tanpa terdeteksi.
    $pesanError = null;
    DB::transaction(function () use ($id, $request, &$pesanError) {
        $req = DB::table('nota_edit_request')->where('id_request', $id)->lockForUpdate()->first();
        if (!$req) {
            $pesanError = 'Permintaan tidak ditemukan.';

            return;
        }
        if ($req->status !== 'Menunggu') {
            $pesanError = 'Permintaan ini sudah diproses sebelumnya (status: ' . $req->status . ').';

            return;
        }

        DB::table('nota_edit_request')->where('id_request', $id)->update([
            'status' => 'Disetujui',
            'id_user_approver' => Auth::id(),
            'catatan_approver' => $request->input('catatan'),
            'tgl_keputusan' => now(),
            'updated_at' => now(),
        ]);
    });

    if ($pesanError) {
        return back()->with('error', $pesanError);
    }

    return back()->with('success', 'Permintaan edit disetujui. Kasir sekarang bisa memproses koreksi nota ini.');
}

public function rejectEditNota(Request $request, $id)
{
    if ($redirect = $this->pastikanAdministrator($request)) {
        return $redirect;
    }

    $request->validate(['catatan' => 'required|string|max:1000']);

    // Hardening P1-2: lihat catatan sama di approveEditNota().
    $pesanError = null;
    DB::transaction(function () use ($id, $request, &$pesanError) {
        $req = DB::table('nota_edit_request')->where('id_request', $id)->lockForUpdate()->first();
        if (!$req) {
            $pesanError = 'Permintaan tidak ditemukan.';

            return;
        }
        if ($req->status !== 'Menunggu') {
            $pesanError = 'Permintaan ini sudah diproses sebelumnya (status: ' . $req->status . ').';

            return;
        }

        DB::table('nota_edit_request')->where('id_request', $id)->update([
            'status' => 'Ditolak',
            'id_user_approver' => Auth::id(),
            'catatan_approver' => $request->input('catatan'),
            'tgl_keputusan' => now(),
            'updated_at' => now(),
        ]);
    });

    if ($pesanError) {
        return back()->with('error', $pesanError);
    }

    return back()->with('success', 'Permintaan edit ditolak.');
}

/**
 * Eksekusi koreksi setelah permintaan Disetujui - dipanggil kasir dari
 * Riwayat Nota. TIDAK menulis ulang logic pembalikan stok/jurnal dari nol:
 * memakai ulang persis StokReversalService::kembalikan() (sama seperti
 * batalkanNota()) + AccountingService::reversePenjualan() (entri kontra,
 * tidak pernah menghapus/mengubah baris jurnal asli). Nota lama ditandai
 * Dibatalkan (riwayat & jejak audit tetap utuh, bisa dilihat lewat
 * alasan_batal_nota yang mengarah ke permintaan edit ini) - nota
 * PENGGANTI dibuat manual oleh kasir lewat Input Order seperti biasa
 * (bukan auto-generate) supaya harga/stok nota baru selalu dihitung ulang
 * dari data TERKINI (Price List, stok bahan saat ini), bukan menyalin
 * mentah angka lama yang mungkin sudah usang atau memang salah.
 */
    public function editLangsung($id)
    {
        // Hardening: jalur "langsung" ini HANYA sah saat approval edit nota
        // DINONAKTIFKAN di Pengaturan Aplikasi. Sebelumnya flag itu hanya
        // dicek di requestEditNota - POST manual ke URL ini tetap dieksekusi
        // self-approve (batal nota + stok/jurnal balik) walau approval aktif,
        // melewati persetujuan Administrator.
        if (\App\Models\AppSetting::current()->approval_edit_nota) {
            return back()->with('error', 'Approval edit nota sedang aktif. Gunakan tombol "Ajukan Edit" agar koreksi disetujui Administrator dulu.');
        }

        $transaksi = DB::table('transaksi')->where('id_transaksi', $id)->first();
        if (!$transaksi) {
            abort(404);
        }

        $boleh = \App\Services\NotaEditService::bolehEdit($transaksi);
        if (!$boleh['boleh']) {
            return back()->with('error', $boleh['alasan']);
        }

        $idRequest = DB::table('nota_edit_request')->insertGetId([
            'id_transaksi' => $id,
            'id_user_requester' => Auth::id(),
            'alasan' => 'Edit langsung (approval dinonaktifkan di Pengaturan Aplikasi)',
            'status' => 'Disetujui',
            'id_user_approver' => Auth::id(),
            'catatan_approver' => 'Auto-disetujui (fitur approval edit nonaktif)',
            'tgl_keputusan' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->applyApprovedEdit($idRequest);
    }

public function applyApprovedEdit($id)
{
    // Hardening P1-2: SEBELUMNYA seluruh pengecekan status (nota_edit_request,
    // transaksi, item sudah Selesai, sudah Diambil) dilakukan DI LUAR
    // transaction dan TANPA lockForUpdate() - klik ganda tombol "Terapkan
    // Koreksi" (atau retry jaringan) bisa membuat DUA request sama-sama lolos
    // pengecekan sebelum salah satunya sempat menulis, sehingga
    // StokReversalService::kembalikan() dan AccountingService::reversePenjualan()
    // terpanggil DUA KALI untuk nota yang sama (stok & jurnal ganda). Sekarang
    // baris nota_edit_request DAN transaksi dikunci lebih dulu, seluruh
    // pengecekan diulang SETELAH lock didapat - pola yang sama persis dengan
    // ProduksiController::cancel().
    $pesanError = null;
    $sudahDibatalkanSebelumnya = false;
    $transaksiHasil = null;

    DB::transaction(function () use ($id, &$pesanError, &$sudahDibatalkanSebelumnya, &$transaksiHasil) {
        $req = DB::table('nota_edit_request')->where('id_request', $id)->lockForUpdate()->first();
        if (!$req) {
            $pesanError = 'Permintaan edit tidak ditemukan.';

            return;
        }
        if ($req->status !== 'Disetujui') {
            $pesanError = 'Permintaan ini belum disetujui Administrator (status: ' . $req->status . ').';

            return;
        }

        $transaksi = DB::table('transaksi')->where('id_transaksi', $req->id_transaksi)->lockForUpdate()->first();
        if (!$transaksi) {
            $pesanError = 'Nota tidak ditemukan.';

            return;
        }
        if ($transaksi->status_nota === 'Dibatalkan') {
            DB::table('nota_edit_request')->where('id_request', $id)->update(['status' => 'Selesai', 'tgl_selesai' => now()]);
            $sudahDibatalkanSebelumnya = true;

            return;
        }

        $bolehApply = \App\Services\NotaEditService::bolehEdit($transaksi);
        if (!$bolehApply['boleh']) {
            $pesanError = $bolehApply['alasan'];

            return;
        }

        $items = DB::table('transaksi_detail')->where('id_transaksi', $transaksi->id_transaksi)->get();
        $sudahSelesai = self::itemProduksiSelesai($items);
        if ($sudahSelesai) {
            $pesanError = 'Nota ini tidak bisa dikoreksi - ada item ("' . $sudahSelesai->nama_file . '") yang sudah Selesai diproduksi.';

            return;
        }
        if (self::adaItemProduksi($items) && $transaksi->status_collecting === 'Sudah Diambil') {
            $pesanError = 'Nota ini tidak bisa dikoreksi - barang sudah diambil konsumen.';

            return;
        }

        $hppTerpakai = self::hppItemBatalTanpaKembali($items);
        foreach ($items as $item) {
            if ($item->status_spk === 'Batal') {
                continue;
            }

            // P9: tolak transisi SPK yang tidak sah dari ke Batal.
            \App\Services\StatusTransitionGuard::validateStatusSpk((string) $item->status_spk, 'Batal');

            DB::table('transaksi_detail')->where('id_detail', $item->id_detail)->update(['status_spk' => 'Batal']);
            StokReversalService::kembalikan(
                $item,
                'EDIT-NOTA-' . $transaksi->id_transaksi,
                'Koreksi nota #' . $transaksi->nomor_nota . ' (permintaan edit #' . $req->id_request . ') - stok dikembalikan'
            );
        }

        AccountingService::reversePenjualan($transaksi->nomor_nota);
        AccountingService::postHppBahanTerpakai($transaksi->nomor_nota, $hppTerpakai);
        $this->kreditkanPembayaranNotaBatal($transaksi);

        // P9: guard status nota.
        \App\Services\StatusTransitionGuard::validateStatusNota(
            (string) $transaksi->status_nota,
            'Dibatalkan'
        );

        DB::table('transaksi')->where('id_transaksi', $transaksi->id_transaksi)->update([
            'status_nota' => 'Dibatalkan',
            'alasan_batal_nota' => 'Dikoreksi via alur Edit Nota (disetujui Administrator) - alasan kasir: ' . $req->alasan,
            'tgl_dibatalkan_nota' => now(),
            'id_user_pembatal_nota' => Auth::id(),
        ]);

        DB::table('nota_edit_request')->where('id_request', $req->id_request)->update([
            'status' => 'Selesai',
            'tgl_selesai' => now(),
            'updated_at' => now(),
        ]);

        $transaksiHasil = $transaksi;
        $transaksiHasil->penjualanLangsung = !self::adaItemProduksi($items);
    });

    if ($sudahDibatalkanSebelumnya) {
        return redirect()->route('penjualan.riwayat')->with('error', 'Nota ini sudah dibatalkan/dikoreksi sebelumnya.');
    }
    if ($pesanError) {
        return back()->with('error', $pesanError);
    }

    // Nota Penjualan dan Jasa (tanpa item produk) diganti lewat Penjualan dan Jasa juga, bukan Input Order.
    return redirect()->route($transaksiHasil->penjualanLangsung ? 'penjualan-barang.create' : 'penjualan.create', ['id_konsumen' => $transaksiHasil->id_konsumen])
        ->with('success', 'Nota lama sudah dibatalkan, stok & jurnal sudah dikembalikan. Silakan buat nota pengganti dengan data yang benar (nota lama tetap bisa dilihat di Riwayat untuk referensi).');
}
}