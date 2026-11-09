<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpkController extends Controller
{
    /**
     * SPK dibaca langsung dari transaksi_detail yang sudah dibuat oleh Kasir/Penjualan.
     * Tidak ada tabel/duplikasi data baru - hanya menambahkan tampilan & tracking
     * di atas data yang sudah ada. transaksi_detail hanya pernah terbentuk lewat
     * PenjualanController::store() (satu transaksi DB atomik bareng transaksi
     * induknya, dengan status_spk='Antre') - jadi tidak ada jalur lain yang bisa
     * memunculkan SPK tanpa nota resmi tersimpan dari Kasir.
     *
     * status_nota='Aktif' WAJIB difilter di sini - nota yang sudah Dibatalkan
     * (lihat PenjualanController::batalkanNota atau serupa) baris
     * transaksi_detail-nya TIDAK dihapus (riwayat harus tetap ada), tapi kalau
     * tidak difilter di sini item SPK dari nota yang sudah batal tetap muncul
     * di antrean produksi padahal ordernya sudah tidak berlaku.
     */
    public function index(Request $request)
    {
        $query = DB::table('transaksi_detail')
            ->join('transaksi', 'transaksi_detail.id_transaksi', '=', 'transaksi.id_transaksi')
            ->join('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
            ->join('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
            ->leftJoin('mesin', 'produk.id_mesin', '=', 'mesin.id_mesin')
            ->join('users', 'transaksi.id_user_kasir', '=', 'users.id_user')
            ->leftJoin('karyawan', 'users.id_karyawan', '=', 'karyawan.id_karyawan')
            ->where('transaksi.status_nota', 'Aktif')
            ->where('transaksi.status_order', 'Diterima Kasir')
            ->select(
                'transaksi_detail.id_detail',
                'transaksi_detail.nama_file',
                'transaksi_detail.qty',
                'transaksi_detail.status_urgent',
                'transaksi_detail.status_spk',
                'transaksi.nomor_nota',
                'transaksi.tgl_order',
                'transaksi.status_pembayaran',
                'konsumen.nama_konsumen',
                'produk.id_mesin',
                'produk.spk_lengkap',
                'mesin.nama_mesin as divisi',
                DB::raw('COALESCE(karyawan.nama_karyawan, users.username) as pembuat_nota')
            );

        // --- FILTER TANGGAL ---
        // Halaman ini adalah antrean kerja harian, jadi tanpa parameter dari
        // operator rentangnya HARI INI. Default itu disengaja, tapi WAJIB
        // ikut tertulis di form filter (lihat resources/views/spk/index.blade.php):
        // filter yang aktif tapi tidak kelihatan membuat operator mengira
        // tabelnya kosong, padahal order-nya cuma tersaring.
        $hariIni = now()->startOfDay();                  // 00:00:00 hari ini (Asia/Jakarta)
        $hariEsok = $hariIni->copy()->addDay();           // 00:00:00 esok

        $tglDari = $request->filled('tgl_dari')
            ? \Carbon\Carbon::parse($request->tgl_dari)->startOfDay()
            : $hariIni->copy();

        // $tglSampai selalu berarti "hari terakhir yang diperiksa", bukan batas
        // atas. Sebelumnya defaultnya $hariEsok sementara isian form memakai
        // endOfDay(), jadi dua jalur itu menghitung batas atas berbeda.
        $tglSampai = $request->filled('tgl_sampai')
            ? \Carbon\Carbon::parse($request->tgl_sampai)->endOfDay()
            : $hariIni->copy()->endOfDay();

        // Form bisa mengirim tgl_dari > tgl_sampai. Tukar supaya rentangnya
        // tetap sah, bukan kosong tanpa penjelasan.
        if ($tglDari->gt($tglSampai)) {
            $tmp = $tglDari;
            $tglDari = $tglSampai;
            $tglSampai = $tmp;
        }

        // Batas atas eksklusif = 00:00:00 hari setelah tglSampai.
        // Dihitung di Carbon lalu diformat, BUKAN menempelkan ' +1 day' ke
        // string hasil format(). MySQL membaca bagian depan saja lalu
        // diam-diam membuang sisanya (Warning 1292), sehingga batasnya turun
        // menjadi < 23:59:59 dan order pada detik terakhir hilang.
        $batasAtas = $tglSampai->copy()->addDay()->startOfDay()->format('Y-m-d H:i:s');

        $tunggakanAktif = $request->boolean('tunggakan_aktif')
            && !$request->filled('tgl_dari')
            && !$request->filled('tgl_sampai');

        if ($tunggakanAktif) {
            // "Tunggakan Aktif" menggantikan rentang tanggal, bukan ditumpuk
            // dengannya. Sebelumnya keduanya selalu dipasang bersamaan, jadi
            // syarat ">= hari ini" (rentang tanggal) dan "< hari ini" (tunggakan)
            // saling meniadakan dan checkbox ini tidak pernah bisa mengembalikan
            // satu baris pun. Sekarang operator menandainya untuk melihat order
            // LAMA yang belum selesai, bukan order hari ini.
            $query->where('transaksi.tgl_order', '<', $hariIni->format('Y-m-d H:i:s'));
            $query->whereIn('transaksi_detail.status_spk', ['Antre', 'Desain', 'Cetak', 'Pending']);
            $query->whereNotIn('transaksi_detail.status_spk', ['Batal', 'Selesai']);
        } else {
            $query->where('transaksi.tgl_order', '>=', $tglDari->format('Y-m-d H:i:s'));
            $query->where('transaksi.tgl_order', '<', $batasAtas);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaksi_detail.nama_file', 'like', "%{$search}%")
                  ->orWhere('konsumen.nama_konsumen', 'like', "%{$search}%")
                  ->orWhere('transaksi.nomor_nota', 'like', "%{$search}%");
            });
        }

        // Batasi ke mesin/divisi yang jadi tanggung jawab karyawan yang login
        // (lihat Karyawan::mesin(), migrasi karyawan_mesin) - Administrator
        // selalu bebas lihat semua, sama seperti CheckMenuAccess. Karyawan
        // tanpa penugasan mesin sama sekali (belum diisi, atau memang bukan
        // peran operator) juga tetap lihat semua - restriksi baru berlaku
        // begitu ada penugasan eksplisit, supaya rollout ini tidak
        // mendadak mempersempit akses siapa pun tanpa dikonfigurasi dulu.
        $karyawan = optional($request->user())->karyawan;
        $isAdmin = optional(optional($request->user())->role)->nama_role === 'Administrator';
        $mesinDiizinkan = null;

        if ($karyawan && !$isAdmin) {
            $idMesinDiizinkan = $karyawan->mesin()->pluck('mesin.id_mesin');
            if ($idMesinDiizinkan->isNotEmpty()) {
                $mesinDiizinkan = $idMesinDiizinkan;
                $query->whereIn('produk.id_mesin', $mesinDiizinkan);
            }
        }

        \App\Services\DivisiService::saring($query, $request->divisi);

        if ($request->filled('status_spk')) {
            $query->where('transaksi_detail.status_spk', $request->status_spk);
        }

        if ($request->boolean('urgent_only')) {
            $query->where('transaksi_detail.status_urgent', 1);
        }

        $spk = $query->orderByDesc('transaksi_detail.id_detail')->paginate(20)->withQueryString();

        // Divisi diambil dari data master mesin yang benar-benar dipakai produk,
        // bukan hard-code. Submenu akan otomatis bertambah kalau ada mesin/divisi baru.
        $divisis = \App\Services\DivisiService::daftar($mesinDiizinkan, ['Antre']);

        // Berapa pekerjaan yang MASIH antre tapi tidak terlihat, semata karena
        // tgl_order-nya di luar rentang tanggal yang sedang aktif.
        //
        // Kenapa perlu dihitung: halaman ini terbuka di rentang HARI INI,
        // sedangkan lonceng notifikasi sengaja menghitung seluruh pekerjaan
        // yang menunggu tanpa memfilter tanggal (lihat NotifikasiService).
        // Tanpa angka ini, operator melihat lonceng berbunyi lalu membuka
        // halaman dan mendapati tabel kosong, lalu menyimpulkan SPK-nya hilang
        // padahal masih menggantung. Diberi tahu lewat angka, operator bisa
        // sendiri memutuskan mau membuka rentang tanggalnya.
        //
        // Mode Tunggakan Aktif tidak memakai rentang tanggal sama sekali, jadi
        // tidak ada yang bisa tertinggal di sana.
        $tertinggal = 0;
        if (! $tunggakanAktif) {
            $tertinggal = DB::table('transaksi_detail')
                ->join('transaksi', 'transaksi_detail.id_transaksi', '=', 'transaksi.id_transaksi')
                ->join('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
                ->where('transaksi_detail.status_spk', 'Antre')
                ->where('transaksi.status_nota', 'Aktif')
                ->where('transaksi.status_order', 'Diterima Kasir')
                ->where(function ($q) use ($tglDari, $batasAtas) {
                    $q->where('transaksi.tgl_order', '<', $tglDari->format('Y-m-d H:i:s'))
                      ->orWhere('transaksi.tgl_order', '>=', $batasAtas);
                })
                ->when($mesinDiizinkan, fn ($q) => $q->whereIn('produk.id_mesin', $mesinDiizinkan))
                ->count();
        }

        // Jumlah SAAT halaman dimuat - pembanding untuk x-polling-notifikasi,
        // pakai query PERSIS sama dengan cekAntrianBaru() supaya banner
        // tidak salah muncul di awal (lihat catatan di komponennya).
        $jumlahAntreAwal = DB::table('transaksi_detail')
            ->join('transaksi', 'transaksi_detail.id_transaksi', '=', 'transaksi.id_transaksi')
            ->where('transaksi_detail.status_spk', 'Antre')
            ->where('transaksi.status_nota', 'Aktif')
            ->where('transaksi.status_order', 'Diterima Kasir')
            ->where('transaksi.tgl_order', '>=', $hariIni->format('Y-m-d H:i:s'))
            ->where('transaksi.tgl_order', '<', $hariEsok->format('Y-m-d H:i:s'))
            ->count();

        return view('spk.index', compact('spk', 'divisis', 'jumlahAntreAwal', 'tglDari', 'tglSampai', 'tertinggal', 'tunggakanAktif'));
    }

    /**
     * Gate order lifecycle untuk SEMUA mutasi SPK: induk transaksi wajib
     * status_order='Diterima Kasir'. Mencegah bypass filter UI via URL/request manual.
     */
    private static function pastikanOrderDiterima(int $idDetail): ?string
    {
        $order = DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->where('d.id_detail', $idDetail)
            ->select('t.status_nota', 't.status_order', 't.nomor_nota')
            ->first();

        if (!$order) {
            return 'Item SPK tidak ditemukan.';
        }
        if (($order->status_nota ?? null) === 'Dibatalkan' || ($order->status_order ?? null) === 'Dibatalkan') {
            return "Nota {$order->nomor_nota} sudah dibatalkan - SPK tidak bisa diproses.";
        }
        if (($order->status_order ?? 'Menunggu Kasir') !== 'Diterima Kasir') {
            return "Nota {$order->nomor_nota} belum diterima Kasir - SPK belum aktif.";
        }

        return null;
    }

    public function toggleUrgent($id)
    {
        $detail = DB::table('transaksi_detail')->where('id_detail', $id)->first();

        if (!$detail) {
            return back()->with('error', 'Item SPK tidak ditemukan.');
        }

        if ($pesan = self::pastikanOrderDiterima((int) $id)) {
            return back()->with('error', $pesan);
        }

        DB::table('transaksi_detail')->where('id_detail', $id)->update([
            'status_urgent' => !$detail->status_urgent,
        ]);

        return back()->with('success', 'Status urgent diperbarui.');
    }

    public function bulkUrgent(Request $request)
    {
        $ids = $request->ids;

        if (!$ids) {
            return back()->with('error', 'Tidak ada data dipilih.');
        }

        $boleh = DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->whereIn('d.id_detail', $ids)
            ->where('t.status_nota', 'Aktif')
            ->where('t.status_order', 'Diterima Kasir')
            ->pluck('d.id_detail')
            ->all();

        if ($boleh === []) {
            return back()->with('error', 'Item yang dipilih belum diterima Kasir - SPK belum aktif.');
        }

        DB::table('transaksi_detail')->whereIn('id_detail', $boleh)->update([
            'status_urgent' => true,
        ]);

        return back()->with('success', count($boleh) . ' item ditandai urgent.');
    }

    /**
     * "Ambil Pekerjaan": memindahkan SPK dari antrean (Antre) ke Produksi
     * (Desain). Sebelum ini SPK yang baru dibuat langsung ikut muncul di
     * halaman Produksi walau belum ada yang mengerjakan - sekarang Produksi
     * hanya menampilkan item yang sudah eksplisit diambil lewat aksi ini
     * (lihat filter default di ProduksiController@index).
     */
    public function ambilPekerjaan($id)
    {
        if ($pesan = self::pastikanOrderDiterima((int) $id)) {
            return back()->with('error', $pesan);
        }

        // UPDATE...WHERE satu query atomik (pola sama seperti bulkAmbil() di
        // atas) - tidak cek-lalu-update terpisah, supaya dua klik "Ambil
        // Pekerjaan" nyaris bersamaan pada item yang sama tidak bisa
        // sama-sama lolos cek status 'Antre' yang sudah basi.
        $diambil = DB::table('transaksi_detail')
            ->where('id_detail', $id)
            ->where('status_spk', 'Antre')
            ->update(['status_spk' => 'Desain']);

        if ($diambil === 0) {
            $detail = DB::table('transaksi_detail')->where('id_detail', $id)->first();
            if (!$detail) {
                return back()->with('error', 'Item SPK tidak ditemukan.');
            }
            return back()->with('error', 'Item ini sudah diambil/diproses sebelumnya (status: ' . $detail->status_spk . ').');
        }

        return back()->with('success', 'Pekerjaan diambil, item sekarang ada di halaman Produksi.');
    }

    /**
     * Menyelesaikan item berstatus 'Pending' (hasil pembatalan produksi yang
     * "diparkir" - lihat ProduksiController::cancel) jadi salah satu dari dua
     * keputusan akhir: kembali ke antrian SPK biasa (Antre) atau batal
     * permanen (Batal). Hanya berlaku untuk item yang memang sedang Pending -
     * mencegah tombol ini disalahgunakan untuk mengubah status item lain.
     */
    public function resolvePending(Request $request, $id)
    {
        $detail = DB::table('transaksi_detail')->where('id_detail', $id)->first();

        if (!$detail) {
            return back()->with('error', 'Item SPK tidak ditemukan.');
        }

        if ($detail->status_spk !== 'Pending') {
            return back()->with('error', 'Item ini bukan status Pending (status saat ini: ' . $detail->status_spk . ').');
        }

        if ($pesan = self::pastikanOrderDiterima((int) $id)) {
            return back()->with('error', $pesan);
        }

        $tindakan = $request->input('tindakan');
        if (!in_array($tindakan, ['antre', 'batal'])) {
            return back()->with('error', 'Tindakan tidak valid.');
        }

        $statusBaru = $tindakan === 'antre' ? 'Antre' : 'Batal';

        // §9: guard transisi SPK dari Pending ke Antre/Batal.
        \App\Services\StatusTransitionGuard::validateStatusSpk((string) $detail->status_spk, $statusBaru);

        DB::table('transaksi_detail')->where('id_detail', $id)->update([
            'status_spk' => $statusBaru,
        ]);

        $pesan = $tindakan === 'antre'
            ? 'Item dikembalikan ke antrian SPK.'
            : 'Item dibatalkan permanen.';

        return back()->with('success', $pesan);
    }

    /**
     * Cetak SPK (thermal/A4) - dipakai sebagai surat kerja saat produksi
     * berjalan maupun sebagai bukti selesai setelah status_spk = Selesai.
     * Satu view melayani kedua kebutuhan karena datanya sama, hanya status
     * yang berbeda.
     */
    public function print($id, $format = 'thermal')
    {
        // Daftar kolom dipilih eksplisit (bukan ->first() tanpa select) supaya
        // setiap kolom yang dipakai kedua view cetak dijamin ada di hasil
        // query. Sebelumnya view membaca properti yang TIDAK pernah di-select
        // (telepon_konsumen, role_pembuat, maker_nota, d.l, d.p, dst) sehingga
        // halaman cetak SPK lengkap berakhir HTTP 500 dengan
        // "Undefined property: stdClass::$telepon_konsumen".
        $detail = DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->join('konsumen as k', 't.id_konsumen', '=', 'k.id_konsumen')
            ->join('produk as pr', 'd.id_produk', '=', 'pr.id_produk')
            ->leftJoin('mesin as m', 'pr.id_mesin', '=', 'm.id_mesin')
            ->join('users as u', 't.id_user_kasir', '=', 'u.id_user')
            ->leftJoin('karyawan as kr', 'u.id_karyawan', '=', 'kr.id_karyawan')
            ->leftJoin('roles as r', 'u.id_role', '=', 'r.id_role')
            ->where('d.id_detail', $id)
            ->select([
                'd.*',
                't.nomor_nota',
                't.tgl_order',
                't.total_bayar',
                't.diskon',
                't.status_pembayaran',
                't.id_konsumen as id_konsumen',
                'k.nama_konsumen as nama_konsumen',
                'k.telepon as telepon_konsumen',
                'k.alamat as alamat_konsumen',
                'pr.nama_produk as nama_produk',
                'pr.metode_hitung as metode_hitung',
                'm.nama_mesin as divisi',
                'kr.nama_karyawan as pembuat_nota',
                'r.nama_role as role_pembuat',
            ])
            ->first();

        if (!$detail) {
            abort(404, 'Item SPK tidak ditemukan.');
        }

        $finishingItems = \App\Services\FinishingItemService::untukDetail([$id])[$id] ?? collect();

        if ($format === 'lengkap') {
            // Kebutuhan bahan dari BOM produk (tanpa tinta): pemakaian dalam satuan hitung bahan, plus perkiraan panjang bahan
            // gulungan bila lebar roll diketahui (mis. kain TC 120 cm).
            $bahan = collect(\App\Services\BomCalculationService::komponen((int) $detail->id_produk, (float) $detail->p_ukuran, (float) $detail->l_ukuran, (float) $detail->qty))
                ->reject(fn ($k) => $k->is_tinta || $k->qty_pakai <= 0)
                ->map(function ($k) {
                    $b = DB::table('bahan_baku as b')->leftJoin('satuan as sh', 'sh.id_satuan', '=', 'b.id_satuan_hitung')->where('b.id_bahan', $k->id_bahan)
                        ->first(['b.nama_bahan', 'b.lebar_roll', 'sh.nama_satuan as satuan_hitung']);
                    $satuan = $b->satuan_hitung ?? '';
                    $panjang = ($satuan === 'm2' && (float) $b->lebar_roll > 0) ? $k->qty_pakai / (float) $b->lebar_roll : null;

                    return (object) ['nama' => $b->nama_bahan ?? '-', 'qty' => $k->qty_pakai, 'satuan' => $satuan, 'panjang_m' => $panjang];
                })->values();

            return view('spk.print-lengkap', compact('detail', 'finishingItems', 'bahan'));
        }

        // $format WAJIB ikut dikirim: partials/print-style.blade.php membaca
        // $format di dozens tempat untuk menentukan ukuran font, padding, dan
        // lebar kertas. Tanpa ini halaman cetak SPK thermal berakhir
        // HTTP 500 ("Undefined variable $format").
        return view('spk.print', compact('detail', 'finishingItems', 'format'));
    }

    /**
     * SPK LENGKAP untuk tim desain (route 'spk.desain' -> /spk-desain/{id}).
     *
     * Alasan method ini ada: route tersebut sempat menunjuk ke
     * SpkController::printDesain yang TIDAK PERNAH ADA di kelas ini, sehingga
     * setiap klik "SPK Lengkap" berakhir HTTP 500
     * ("Call to undefined method SpkController::printDesain()").
     *
     * Isinya sengaja memanggil print($id, 'lengkap') supaya ada SATU sumber
     * data (gambar konsumen, bahan, finishing, perkiraan panjang roll)
     * dan tidak ada lagi jalur print yang terpisah yang bisa bazelino sendiri.
     */
    public function printDesain($id)
    {
        // Gate: SPK lengkap hanya untuk produk yang ditandai spk_lengkap di
        // master produk. Tanpa gate ini, produk yang TIDAK ditanda masih bisa
        // dibuka lewat URL dan memakai alur cetak lengkap yang mestinya
        // hanya untuk tim desain.
        $boleh = DB::table('transaksi_detail as d')
            ->join('produk as pr', 'd.id_produk', '=', 'pr.id_produk')
            ->where('d.id_detail', $id)
            ->value('pr.spk_lengkap');

        if (!$boleh) {
            abort(404, 'SPK lengkap tidak tersedia untuk produk ini.');
        }

        return $this->print($id, 'lengkap');
    }

    /**
     * Setujui/Pilih finishing untuk item SPK (modal finishinng di halaman SPK).
     */
    public function finishing($id)
    {
        $detail = DB::table('transaksi_detail')->where('id_detail', $id)->first();

        if (!$detail) {
            abort(404, 'Item SPK tidak ditemukan.');
        }

        $finishing = \App\Models\Finishing::orderBy('nama_finishing')->get();

        return view('spk.finishing', compact('detail', 'finishing'));
    }

    /**
     * Terima finishing pilihan dan simpan ke transaksi_detail_finishing.
     */
    public function simpanFinishing(Request $request, $id)
    {
        $request->validate([
            'finishing' => 'required|array',
            'finishing.*.id_finishing' => 'exists:finishing,id_finishing',
            'finishing.*.status_finishing' => 'required|in:Standar,Custom',
            'finishing.*.biaya' => 'nullable|numeric|min:0',
            'finishing.*.catatan' => 'nullable|string|max:255',
        ]);

        $detail = DB::table('transaksi_detail')->where('id_detail', $id)->first();

        if (!$detail) {
            return back()->with('error', 'Item SPK tidak ditemukan.');
        }

        // Hapus finishing lama untuk item ini
        DB::table('transaksi_detail_finishing')->where('id_detail', $id)->delete();

        // Simpan finishing baru
        foreach ($request->finishing as $fin) {
            $finishing = DB::table('finishing')->where('id_finishing', $fin['id_finishing'])->first();
            if ($finishing && $finishing->nama_finishing === 'Potong Manual A3') {
                $hasTier = DB::table('finishing_harga')->where('id_finishing', $fin['id_finishing'])->exists();
                if (!$hasTier) {
                    return back()->with('error', 'Tier harga Potong Manual A3 belum diisi, hubungi admin.');
                }
            }
            DB::table('transaksi_detail_finishing')->insert([
                'id_detail' => $id,
                'id_finishing' => $fin['id_finishing'],
                'status_finishing' => $fin['status_finishing'],
                'qty_pakai' => $fin['qty_pakai'] ?? null,
                'biaya' => $fin['biaya'] ?? 0,
                'catatan' => $fin['catatan'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Finishing berhasil disimpan.');
    }

    /**
     * Polling notifikasi "ada item baru masuk Produksi" - operator produksi
     * tahu ada pekerjaan baru (dipindah dari SPK lewat "Ambil Pekerjaan")
     * tanpa manual refresh.
     */
    public function cekAntrianBaru()
    {
        $hariIni = now()->startOfDay();
        $hariEsok = now()->startOfDay()->addDay();

        // Item 'Antre' = pekerjaan yang sudah DITERIMA Kasir tapi belum diambil
        // operator produksi. Itulah antrean SPK yang perlu dikejutkan lewat
        // polling. ('Desain'/'Cetak' dipakai endpoint Produksi, karena item
        // berpindah ke sana setelahoperator menekan "Ambil Pekerjaan".)
        $total = DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->where('t.status_nota', 'Aktif')
            ->where('t.status_order', 'Diterima Kasir')
            ->where('t.tgl_order', '>=', $hariIni->format('Y-m-d H:i:s'))
            ->where('t.tgl_order', '<', $hariEsok->format('Y-m-d H:i:s'))
            ->where('d.status_spk', 'Antre')
            ->count();
        return response()->json(['total' => $total]);
    }

    /**
     * Ambil Satu Pekerjaan: ambil SPK dari antrean (Antre) ke Produksi
     * (Desain). Selalu pakai metode POST ke URL ini.
     */
    public function ambilSatu($id)
    {
        if ($pesan = self::pastikanOrderDiterima((int) $id)) {
            return back()->with('error', $pesan);
        }

        $diambil = DB::table('transaksi_detail')
            ->where('id_detail', $id)
            ->where('status_spk', 'Antre')
            ->update(['status_spk' => 'Desain']);

        if ($diambil === 0) {
            $detail = DB::table('transaksi_detail')->where('id_detail', $id)->first();
            if (!$detail) {
                return back()->with('error', 'Item SPK tidak ditemukan.');
            }
            return back()->with('error', 'Item ini sudah diambil/diproses sebelumnya (status: ' . $detail->status_spk . ').');
        }

        return back()->with('success', 'Pekerjaan diambil, item sekarang ada di halaman Produksi.');
    }

    /**
     * Bulk ambil: pindahkan banyak item dari antrean ke produksi sekaligus.
     */
    public function bulkAmbil(Request $request)
    {
        $ids = $request->ids;

        if (!$ids) {
            return back()->with('error', 'Tidak ada data dipilih.');
        }

        // Gate: hanya item dari nota yang sudah diterima kasir.
        $boleh = DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->whereIn('d.id_detail', $ids)
            ->where('t.status_nota', 'Aktif')
            ->where('t.status_order', 'Diterima Kasir')
            ->pluck('d.id_detail')
            ->all();

        if ($boleh === []) {
            return back()->with('error', 'Item yang dipilih belum diterima Kasir - SPK belum aktif.');
        }

        $diambil = DB::table('transaksi_detail')
            ->whereIn('id_detail', $boleh)
            ->where('status_spk', 'Antre')
            ->update(['status_spk' => 'Desain']);

        $dilewati = count($ids) - $diambil;
        $pesan = "{$diambil} pekerjaan diambil, sekarang ada di halaman Produksi.";
        if ($dilewati > 0) {
            $pesan .= " {$dilewati} item dilewati karena sudah diambil/diproses sebelumnya.";
        }

        return back()->with($diambil > 0 ? 'success' : 'error', $pesan);
    }

    /**
     * Setujui/Pilih finishing untuk item SPK (modal finishinng di halaman SPK).
     */
    public function finishingPost(Request $request, $id)
    {
        $request->validate([
            'finishing' => 'required|array',
            'finishing.*.id_finishing' => 'exists:finishing,id_finishing',
            'finishing.*.status_finishing' => 'required|in:Standar,Custom',
            'finishing.*.biaya' => 'nullable|numeric|min:0',
            'finishing.*.catatan' => 'nullable|string|max:255',
        ]);

        $detail = DB::table('transaksi_detail')->where('id_detail', $id)->first();

        if (!$detail) {
            return back()->with('error', 'Item SPK tidak ditemukan.');
        }

        // Hapus finishing lama untuk item ini
        DB::table('transaksi_detail_finishing')->where('id_detail', $id)->delete();

        // Simpan finishing baru
        foreach ($request->finishing as $fin) {
            $finishing = DB::table('finishing')->where('id_finishing', $fin['id_finishing'])->first();
            if ($finishing && $finishing->nama_finishing === 'Potong Manual A3') {
                $hasTier = DB::table('finishing_harga')->where('id_finishing', $fin['id_finishing'])->exists();
                if (!$hasTier) {
                    return back()->with('error', 'Tier harga Potong Manual A3 belum diisi, hubungi admin.');
                }
            }
            DB::table('transaksi_detail_finishing')->insert([
                'id_detail' => $id,
                'id_finishing' => $fin['id_finishing'],
                'status_finishing' => $fin['status_finishing'],
                'qty_pakai' => $fin['qty_pakai'] ?? null,
                'biaya' => $fin['biaya'] ?? 0,
                'catatan' => $fin['catatan'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Finishing berhasil disimpan.');
    }
}