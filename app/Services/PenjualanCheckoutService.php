<?php

namespace App\Services;

use App\Http\Controllers\KonsumenDepositController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * Tahap API-3: satu implementasi checkout untuk kanal web dan, setelah
 * adapter API + idempotensi aman, integrasi eksternal.
 *
 * Kode dipindahkan secara mekanis dari PenjualanController agar perilaku
 * web (stok/kartu_stok, BOM/HPP, jurnal, deposit, nota susulan) tetap sama.
 * Endpoint POST API tetap 503 sampai kontrak actor/request/idempotensi
 * selesai dan lolos uji paritas.
 */
class PenjualanCheckoutService
{
    public function store(Request $request, ?array $konteksApi = null)
{
    if (!$request->items || count($request->items) == 0) {
        return response()->json(['status' => 'error', 'msg' => 'Keranjang kosong!'], 400);
    }

    // =====================================================================
    // MODE SUSULAN - tambahan item ke nota yang SUDAH jalan.
    //
    // Layarnya sama persis dengan Input Pesanan (penjualan/create.blade.php
    // mode 'susulan'), jadi validasi harga, finishing, penulisan baris detail,
    // pemotongan stok, dan posting jurnal memakai KODE YANG SAMA dengan nota
    // baru - bukan implementasi terpisah. Yang berbeda hanya sumber data
    // nota (konsumen, golongan, tanggal, tipe order, nomor nota) dan
    // penandaan barisnya sebagai susulan supaya bisa dibedakan dari item
    // asli di nota, SPK, dan produksi.
    //
    // Syarat addition (dipastikan di sini, sebelum transaksi DB dimulai):
    //   - hanya operator yang membuat nota itu (transaksi.id_user_kasir)
    //   - nota masih Aktif, dan ordernya sudah ada (Menunggu Kasir atau
    //     Diterima Kasir) - syaratnya PERSIS sama dengan yang dicegat di
    //     create() supaya layar dan endpoint tidak berbeda
    //   - hanya di hari yang sama dengan tanggal order nota
    // =====================================================================
    $notaSusulan = null;
    if ($request->filled('susulan_id')) {
        $notaSusulan = DB::table('transaksi')->where('id_transaksi', (int) $request->susulan_id)->first();
        if (!$notaSusulan) {
            abort(404);
        }
        if ((int) $notaSusulan->id_user_kasir !== (int) Auth::id()) {
            return response()->json([
                'status'  => 'error',
                'msg'     => 'Hanya operator yang membuat nota ini yang boleh menambah item.',
                'message' => 'Hanya operator yang membuat nota ini yang boleh menambah item.',
            ], 403);
        }
        if ($notaSusulan->status_nota !== 'Aktif') {
            return response()->json([
                'status'  => 'error',
                'msg'     => 'Nota sudah tidak aktif.',
                'message' => 'Nota sudah tidak aktif.',
            ], 422);
        }
        // Status order HARUS sama persis dengan yang diperiksa create() (layar
        // Input Pesanan mode susulan). Dulu keduanya berbeda: layar membolehkan
        // nota 'Menunggu Kasir' (operator baru saja membuat nota, belum sempat
        // membuka kasir) tapi endpoint menolaknya dengan "Nota belum dibuka
        // kasir" - jadi operator boleh sampai_Isian full lalu baru ditolak
        // saat menekan "Tambahkan ke Nota".
        //
        // 'Menunggu Kasir' aman: itemnya tetap masuk SPK begitu kasir membuka
        // nota (SPK membaca transaksi dengan status_order = 'Diterima Kasir'),
        // sama seperti item nota biasa.
        if (!in_array($notaSusulan->status_order, ['Menunggu Kasir', 'Diterima Kasir'], true)) {
            return response()->json([
                'status'  => 'error',
                'msg'     => 'Nota belum dibuka kasir.',
                'message' => 'Nota belum dibuka kasir.',
            ], 422);
        }
        // Kunci nota Lunas (keputusan bisnis final C): tambah susulan ke nota
        // yang sudah Lunas DITOLAK. Status pembayaran dihitung dari
        // StatusPembayaran::dari() (pembayaran_riwayat), bukan kolom status.
        $totalTerbayarSusulan = (float) DB::table('pembayaran_riwayat')
            ->where('id_transaksi', (int) $notaSusulan->id_transaksi)
            ->sum('nominal');
        if ((float) $notaSusulan->total_bayar > 0
            && \App\Services\StatusPembayaran::dari((float) $notaSusulan->total_bayar, $totalTerbayarSusulan) === \App\Services\StatusPembayaran::LUNAS) {
            return response()->json([
                'status'  => 'error',
                'msg'     => 'Nota sudah Lunas — tidak bisa ditambah item susulan. Buat nota baru untuk tambahan.',
                'message' => 'Nota sudah Lunas — tidak bisa ditambah item susulan. Buat nota baru untuk tambahan.',
            ], 422);
        }

        if (Carbon::parse($notaSusulan->tgl_order)->toDateString() !== now()->toDateString()) {
            return response()->json([
                'status'  => 'error',
                'msg'     => 'Penambahan hanya boleh dilakukan di hari yang sama dengan nota.',
                'message' => 'Penambahan hanya boleh dilakukan di hari yang sama dengan nota.',
            ], 422);
        }
    }

    // Konsumen & golongan pada mode susulan SELALU diambil dari nota, bukan
    // dari browser - item susulan tidak boleh pernah ditagihkan ke konsumen
    // lain atau dihitung dengan golongan harga yang lain.
    $idKonsumen = $notaSusulan
        ? (int) $notaSusulan->id_konsumen
        : $request->id_konsumen;
    $idGolongan = $notaSusulan
        ? (int) DB::table('konsumen')->where('id_konsumen', $notaSusulan->id_konsumen)->value('id_golongan')
        : $request->id_golongan;

    if (!$idKonsumen) {
        return response()->json([
            'status' => 'error',
            'msg' => 'Konsumen wajib dipilih'
        ], 400);
    }

    if (!$idGolongan) {
        return response()->json([
            'status' => 'error',
            'msg' => 'Golongan konsumen wajib diisi'
        ], 400);
    }

    // Order baru (dan otomatis SPK yang ikut terbentuk dari transaksi_detail-
    // nya - lihat SpkController::index, SPK dibaca langsung dari sini tanpa
    // tabel terpisah) HANYA boleh dibuat saat kasir sudah resmi "buka toko"
    // hari ini (kas_harian berstatus open). Sebelum ini tidak ada guard sama
    // sekali di sini - order/SPK bisa terbentuk kapan pun tanpa sesi kas yang
    // menaunginya. Guard yang sama persis dengan KasirController::prosesBayar().
    //
    // Mode susulan TIDAK ikut guard ini: nota lamanya sudah lewat proses
    // terima kasir, jadi syarat "kasir buka toko" sudah terpenuhi saat nota
    // itu dibuat. Menambah item susulan tidak membuka order baru.
    $kasHariIni = $notaSusulan ? null : KasSesiService::aktifUntukTransaksi();

    if (!$notaSusulan && !$kasHariIni) {
        return response()->json(['status' => 'error', 'msg' => 'Kasir belum dibuka! Buka kas terlebih dahulu sebelum membuat order baru.'], 400);
    }

    // Tipe order & tanggal order pada mode susulan mengikuti nota asal -
    // tambahan item tidak boleh mengubah jenis order yang sudah disepakati.
    $tipeOrder = $notaSusulan
        ? ($notaSusulan->tipe_order ?: 'Reguler')
        : (in_array($request->tipe_order, ['Reguler', 'PO']) ? $request->tipe_order : 'Reguler');
    $noPo = $notaSusulan
        ? $notaSusulan->no_po
        : ($tipeOrder === 'PO' ? trim((string) $request->no_po) : null);
    if (!$notaSusulan && $tipeOrder === 'PO' && !$noPo) {
        return response()->json(['status' => 'error', 'msg' => 'No. PO wajib diisi untuk tipe order PO.'], 400);
    }

    // Tanggal order boleh diubah kasir (mis. mencatat PO yang sebenarnya
    // masuk kemarin) tapi tidak boleh di masa depan - itu bukan tanggal
    // order, itu penjadwalan, yang bukan konsep yang didukung di sini.
    $tglOrder = now();
    if ($notaSusulan) {
        $tglOrder = Carbon::parse($notaSusulan->tgl_order);
    } elseif ($request->filled('tgl_order')) {
        try {
            $tglInput = Carbon::parse($request->tgl_order);
            if (!$tglInput->isFuture()) {
                $tglOrder = $tglInput->setTimeFrom(now());
            }
        } catch (\Exception $e) {
            // format tanggal tidak valid - abaikan, pakai now() seperti biasa
        }
    }

    // Harga & subtotal TIDAK PERNAH dipercaya dari client (lihat catatan audit:
    // harga sebelumnya dikirim mentah-mentah dari browser tanpa verifikasi ulang,
    // sehingga siapa pun yang mengubah request bisa menetapkan harga sembarang).
    // Di sini setiap item dihitung ULANG dari data master (produk + setting_harga)
    // menggunakan hanya input mentah yang tidak bisa "dipalsukan" jadi lebih murah
    // (p, l, qty) - harga per unit dan subtotal akhir murni hasil hitungan server.
    //
    // API-3: adapter checkout API memanggil store() dengan konteks
    // ['jalur' => 'api'] - HARGA SELALU TERKUNCI server tanpa kecuali (token API
    // tidak punya sesi layar kasir; memercayakan "biaya_cetak" dari kanal API =
    // celah memangkas harga langsung) dan Biaya Desain selalu 0. Kanal web tidak
    // berubah: default konteksnya 'web', perilaku persis sebelum refactor ini.
    $jalurApi = (is_array($konteksApi) && ($konteksApi['jalur'] ?? 'web') === 'api');
    $hargaTerkunci = $jalurApi ? true : !\App\Support\AksesHarga::bolehLihat(Auth::user());
    // Hak menginput Biaya Desain dipisah dari hak melihat harga (lihat catatan
    // pemisahan di dalam validasiItems()). Dihitung SEKALI di sini, bukan
    // tiap baris item, dan TIDAK bergantung pada $hargaTerkunci: role boleh
    // salah satu atau keduanya. Jalur API: selalu tidak (lihat di atas).
    $bolehInputBiayaDesain = $jalurApi ? false : \App\Support\AksesHarga::bolehInputBiayaDesain(Auth::user());

    try {
        $itemsTervalidasi = $this->validasiItems($request, (int) $idGolongan, $hargaTerkunci, $bolehInputBiayaDesain);
    } catch (\App\Exceptions\ValidasiNotaException $e) {
        return response()->json(['status' => 'error', 'msg' => $e->getMessage()], 422);
    }

    DB::beginTransaction();

    try {

        // Nomor nota di-generate ULANG di sini (atomik lewat
        // DocumentNumberService), bukan dipercaya dari $request->no_nota.
        // Sebelumnya nomor cuma dihitung sekali saat form GET dimuat
        // (create(), pola COUNT(*)+1) lalu dikirim balik client saat
        // submit - dua order dibuat nyaris bersamaan bisa melihat "nomor
        // berikutnya" yang sama sebelum salah satu commit (race
        // condition nyata, bukan teoretis - beda sesi kasir/tab yang
        // buka form di waktu berdekatan). $no_nota di create() sekarang
        // MURNI preview UX, angka final selalu ditentukan di sini.
        $prefixNota = \App\Models\AppSetting::current()->nomor_nota_prefix ?: 'INV';
        // Mode susulan memakai nomor nota yang sudah ada - bukan nomor baru.
        $noNota = $notaSusulan ? $notaSusulan->nomor_nota : DocumentNumberService::generate($prefixNota);

        $grand_total = collect($itemsTervalidasi)->sum('subtotal');

        $dataTransaksi = [
            'nomor_nota'        => $noNota,
            'tipe_order'        => $tipeOrder,
            'no_po'             => $noPo,
            'id_konsumen'       => $idKonsumen,
            // P0-6: fallback "?? 1" dihapus - sebelumnya request tanpa user
            // login (mis. sesi hangus) diam-diam dicatat atas nama user #1
            // (biasanya Administrator), merusak akuntabilitas. Sekarang
            // gagal jelas sebagai 401 kalau sesi tidak valid.
            'id_user_kasir'     => Auth::id() ?? abort(401, 'Sesi login tidak valid - login ulang sebelum membuat order.'),
            'tgl_order'         => $tglOrder,
            'total_bayar'       => $grand_total,
            'status_pembayaran' => 'Belum Bayar',
            'status_nota'       => 'Aktif',
            // Order lifecycle: nota baru = Menunggu Kasir. Baris
            // transaksi_detail status_spk='Antre' di bawah DORMAN sampai
            // Kasir menerima via POST terimaTransaksi (SPK/Produksi
            // memfilter status_order='Diterima Kasir').
            'status_order'      => 'Menunggu Kasir',
            'created_at'        => now(),
            'updated_at'        => now(),
        ];

        // Mode susulan: nota sudah ada, item nempel ke nota itu. Total nota
        // dan status pembayarannya ikut diperbarui setelah loop (lihat
        // blok "TOTAL NOTA" di bawah), bukan di sini.
        $id_transaksi = $notaSusulan
            ? (int) $notaSusulan->id_transaksi
            : DB::table('transaksi')->insertGetId($dataTransaksi);

        $totalHppMaterialSemua = 0;
        $totalJasaSemua = 0;
        $totalProdukSemua = 0;

        foreach ($itemsTervalidasi as $item) {

            // Baris Jasa: tidak ada BOM/stok/SPK produksi untuk dilacak -
            // langsung selesai (bukan 'Antre', supaya tidak nyasar ke
            // antrian SPK/Produksi yang isinya seharusnya cuma pekerjaan
            // cetak fisik).
            if (!empty($item['is_jasa'])) {
                DB::table('transaksi_detail')->insert([
                    'id_transaksi'        => $id_transaksi,
                    'id_produk'           => null,
                    'id_jasa'             => $item['id_jasa'],
                    'nama_file'           => $item['nama_file'],
                    'p_ukuran'            => 0,
                    'l_ukuran'            => 0,
                    'qty'                 => $item['qty_input'],
                    'subtotal'            => $item['subtotal'],
                    'status_spk'          => 'Selesai',
                    'harga_jual_snapshot' => $item['subtotal'],
                    'hpp_snapshot'        => 0,
                    // Penanda baris susulan supaya bisa dibedakan dari item
                    // asli nota (dipakai di detail nota & SPK).
                    'is_susulan'          => $notaSusulan ? 1 : 0,
                ]);

                $totalJasaSemua += $item['subtotal'];
                continue;
            }

            $totalProdukSemua += $item['subtotal'];

            // Catatan mode HYBRID minimum luas: TIDAK ADA baris selisih
            // terpisah lagi. Selisih minimum sudah di-roll ke subtotal baris
            // item terbesar kelompoknya di validasiItems() dan tercatat di
            // kolom is_minimum_order baris itu (ditulis di tulisItemProduk),
            // jadi baris minimum khusus tidak ada di sini lagi.

            // Penulisan item produk dipindah ke method tulisItemProduk()
            // supaya mode EDIT SELISIH memakai kode yang sama persis.
            [$id_detail, $hppItemIni] = $this->tulisItemProduk(
                $item,
                $id_transaksi,
                $notaSusulan ? $noNota . ' (susulan)' : $noNota,
                $notaSusulan
                    ? 'Pemakaian produksi order susulan ' . $noNota
                    : 'Pemakaian produksi order ' . $noNota,
                (bool) $notaSusulan
            );
            $totalHppMaterialSemua += $hppItemIni;
        }

        // Jejak penimpaan harga: bila biaya cetak yang dipakai berbeda dari
        // price list (potongan atau markup manual oleh kasir/admin), catat di
        // audit siapa, nota mana, baris mana, dari berapa ke berapa. Tanpa ini
        // potongan harga tidak bisa ditelusuri setelah nota tersimpan.
        $penyimpangan = [];
        foreach ($itemsTervalidasi as $urut => $item) {
            if (!empty($item['is_jasa']) || !isset($item['harga_standar'])) {
                continue;
            }
            $selisih = round((float) $item['biaya_cetak'] - (float) $item['harga_standar'], 2);
            if (abs($selisih) > 1) {
                $penyimpangan[] = ['baris' => $urut + 1, 'nama_file' => $item['nama_file'], 'price_list' => (float) $item['harga_standar'], 'dipakai' => (float) $item['biaya_cetak'], 'selisih' => $selisih];
            }
        }
        if ($penyimpangan !== []) {
            $totalSelisih = array_sum(array_column($penyimpangan, 'selisih'));
            \App\Services\AuditLogger::log(
                'harga_manual',
                'transaksi',
                (int) $id_transaksi,
                ['price_list' => array_sum(array_column($penyimpangan, 'price_list'))],
                ['dipakai' => array_sum(array_column($penyimpangan, 'dipakai')), 'baris' => $penyimpangan],
                'Nota ' . $noNota . ': biaya cetak diubah dari price list pada ' . count($penyimpangan) . ' baris (' . ($totalSelisih < 0 ? 'potongan Rp ' : 'tambahan Rp ') . number_format(abs($totalSelisih), 0, ',', '.') . ')'
            );
        }

        // Dipisah jadi 2 posting independen (bukan 1 baris gabungan) supaya
        // postPenjualan() yang sudah teruji untuk order produk-only tidak
        // perlu diubah sama sekali - order campuran produk+jasa cukup
        // menjalankan keduanya dalam transaction yang sama.
        // Nota bertotal Rp 0 tidak pernah bisa lunas (selalu di antrean Belum Bayar) dan jurnal HPP-nya terlewat padahal
        // stok bahan sudah terpotong. Ditolak di sini (di dalam transaksi, jadi potongan stok ikut batal).
        if ($totalProdukSemua + $totalJasaSemua <= 0) {
            throw new \RuntimeException('Total nota Rp 0: isi biaya cetak/harga barangnya dulu. Nota tanpa nilai tidak bisa dilunasi dan bahannya tidak tercatat sebagai biaya.');
        }
        // Nomor acuan jurnal. Mode susulan memakai sufiks "-susulan" supaya
        // tambahan ke nota yang sama bisa dibedakan dari posting nota awal
        // saat memuat jurnal umum.
        $noNotaJurnal = $notaSusulan ? $noNota . '-susulan' : $noNota;

        if ($totalProdukSemua > 0) {
            AccountingService::postPenjualan($noNotaJurnal, $totalProdukSemua, $totalHppMaterialSemua);
        }
        if ($totalJasaSemua > 0) {
            AccountingService::postPenjualanJasa($noNotaJurnal, $totalJasaSemua);
        }

        // TOTAL NOTA pada mode susulan: total nota lama naik sebesar tambahan,
        // dan status pembayaran dihitung ULANG dari total vs yang sudah
        // dibayar (kolom status_pembayaran cuma salinan denormalisasi - kalau
        // tidak diupdate di sini, badge dan kolom itu akan bertentangan).
        // Nota yang tadinya Lunas otomatis turun jadi DP/Belum Bayar karena
        // tambahan belum dibayar - kasir melihat sisa = hanya tambahan.
        if ($notaSusulan) {
            // $grand_total di sini = total BARU BARU ini (tambahan), bukan
            // total nota. Total nota adalah total lama + tambahan.
            $totalLama = (float) $notaSusulan->total_bayar;
            $totalNotaBaru = $totalLama + $grand_total;

            $totalTerbayar = (float) DB::table('pembayaran_riwayat')
                ->where('id_transaksi', $id_transaksi)
                ->sum('nominal');
            $statusBaru = \App\Services\StatusPembayaran::dari($totalNotaBaru, $totalTerbayar);

            DB::table('transaksi')
                ->where('id_transaksi', $id_transaksi)
                ->update([
                    'total_bayar'       => $totalNotaBaru,
                    'status_pembayaran' => $statusBaru,
                    'updated_at'        => now(),
                ]);
        }

        // Pakai saldo deposit konsumen (kalau ada) LANGSUNG saat nota ini
        // dibuat - sebelumnya deposit cuma bisa dipakai lewat pilihan manual
        // di halaman Bayar Kasir (kasir harus pilih "Deposit" di dropdown +
        // ketik nominal sendiri), jadi konsumen dengan saldo tetap muncul
        // "Belum Bayar" sampai ada yang memprosesnya secara terpisah.
        // Rumus & ledger PERSIS sama dengan KasirController::prosesBayar()
        // untuk metode Deposit (insert konsumen_deposit tipe Pemakaian +
        // pembayaran_riwayat + posting akuntansi yang sama) supaya tidak ada
        // dua cara berbeda mencatat pemakaian deposit.
        //
        // SENGAJA hanya berlaku untuk nota BARU ini - tidak menyapu mundur
        // ke nota lain milik konsumen yang sama yang mungkin masih Belum
        // Bayar/DP (itu tetap harus diproses manual seperti biasa di Kasir).
        // Deposit yang otomatis dipakai di sini TETAP "pembayaran" (insert
        // pembayaran_riwayat + posting akuntansi) - sesi kas aktif bersifat
        // eksklusif milik SATU orang (lihat catatan di
        // KasirController::storeKas()/prosesBayar()), jadi user yang BUKAN
        // pemilik sesi kas yang sedang aktif tidak boleh diam-diam mencatat
        // pembayaran lewat jalur ini juga - order tetap boleh dibuat
        // (Belum Bayar), cuma auto-pakai depositnya yang dilewati.
        $bolehBayarDenganKasIni = $kasHariIni && (int) $kasHariIni->id_user === (int) Auth::id();

        $depositTerpakai = null;
        if ($grand_total > 0 && $bolehBayarDenganKasIni) {
            // Lock baris konsumen sebagai mutex sebelum hitung saldo - sama
            // seperti catatan di KasirController::prosesBayar() metode Deposit,
            // supaya order lain untuk konsumen yang sama yang dibuat nyaris
            // bersamaan tidak bisa sama-sama membaca saldo lama yang sama dan
            // menyebabkan deposit terpakai melebihi yang disetor.
            DB::table('konsumen')->where('id_konsumen', $request->id_konsumen)->lockForUpdate()->first();

            $saldoDeposit = KonsumenDepositController::saldoUntuk((int) $request->id_konsumen, true);

            if ($saldoDeposit > 0) {
                $jumlahPakai = min($saldoDeposit, $grand_total);

                DB::table('konsumen_deposit')->insert([
                    'id_konsumen'  => $request->id_konsumen,
                    'tipe'         => 'Pemakaian',
                    'nominal'      => $jumlahPakai,
                    'metode_bayar' => 'Deposit',
                    'keterangan'   => "Pembayaran otomatis nota {$noNota} saat order dibuat",
                    'tgl_deposit'  => now()->toDateString(),
                    'id_transaksi' => $id_transaksi,
                    'id_user'      => Auth::id(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                $idBayarDeposit = DB::table('pembayaran_riwayat')->insertGetId([
                    'id_transaksi' => $id_transaksi,
                    'nominal'      => $jumlahPakai,
                    'metode'       => 'Deposit',
                    'id_bank'      => null,
                    'tgl_bayar'    => now(),
                    'keterangan'   => 'Otomatis dipotong dari saldo deposit saat order dibuat',
                ]);

                \App\Services\AuditLogger::log(
                    'created',
                    'pembayaran_riwayat',
                    (int) $idBayarDeposit,
                    null,
                    ['nomor_nota' => $noNota, 'nominal' => (float) $jumlahPakai, 'metode' => 'Deposit'],
                    "Deposit dipakai otomatis untuk nota {$noNota}: Rp " . number_format((float) $jumlahPakai, 0, ',', '.')
                );

                AccountingService::postPembayaranDeposit($noNota, $jumlahPakai);

                $statusBaru = $jumlahPakai >= $grand_total ? 'Lunas' : 'DP';

                \App\Services\StatusTransitionGuard::validateStatusPembayaran(
                    (string) DB::table('transaksi')->where('id_transaksi', $id_transaksi)->value('status_pembayaran'),
                    $statusBaru
                );

                DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update([
                    'status_pembayaran' => $statusBaru,
                    'metode_pembayaran' => 'Deposit',
                ]);

                $depositTerpakai = [
                    'dipakai'    => $jumlahPakai,
                    'sisa_saldo' => $saldoDeposit - $jumlahPakai,
                    'status'     => $statusBaru,
                ];
            }
        }

        DB::commit();

        // Order dibuat dari penawaran (Quotation): tandai penawaran jadi
        // "Menjadi Order". Setelah commit & dibungkus try/catch - order sudah
        // sah dan tidak boleh gagal hanya karena penandaan penawaran.
        if ($request->filled('quotation_id')) {
            try {
                \App\Services\QuotationService::tandaiMenjadiOrder((int) $request->quotation_id, (int) $id_transaksi);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        if ($notaSusulan) {
            // Mode susulan: nota asal tetap yang berlaku, jadi pesan sukses
            // menyuruh operator kembali ke detail nota - bukan ke Monitoring
            // Kasir (nota ini sudah di sana sejak awal).
            session()->flash('success', "Item susulan berhasil ditambahkan ke nota {$noNota}.");

            return response()->json([
                'status'       => 'success',
                'msg'          => 'Item susulan berhasil ditambahkan ke nota ' . $noNota . '.',
                'message'      => 'Item susulan berhasil ditambahkan ke nota ' . $noNota . '.',
                'id_transaksi' => $id_transaksi,
                'nomor_nota'   => $noNota,
                'total_baru'   => (float) $notaSusulan->total_bayar + $grand_total,
                'total_tambah' => $grand_total,
            ]);
        }

        session()->flash('success', "Nota {$noNota} berhasil dibuat.");
        \App\Support\JalurLanjut::set('Langkah berikut: pembayaran/validasi di Kasir', 'kasir.index', 'Buka Monitoring Kasir');

        return response()->json([
            'status' => 'success',
            'msg' => 'Transaksi Berhasil',
            'id_transaksi' => $id_transaksi,
            'nomor_nota' => $noNota,
            'deposit_terpakai' => $depositTerpakai,
        ]);

    } catch (\Exception $e) {

        DB::rollBack();

        $status = $e instanceof \App\Exceptions\ValidasiNotaException ? 422 : 500;
        return response()->json([
            'status' => 'error',
            'msg' => \App\Support\PesanError::untuk($e)
        ], $status);
    }
}

    public function tulisItemProduk(array $item, int $idTransaksi, string $referensiKartu, string $keteranganKartu, bool $isSusulan, int $idDetailLama = 0): array
    {
        $jumlah_order = $item['qty_input'];
        $komisiDesain = \App\Services\DesainKomisiService::hitung($item['id_desainer'], (float) $item['biaya_desain']);

        $id_detail = $idDetailLama > 0
            ? $this->perbaruiBarisItemProduk($item, $idDetailLama, $komisiDesain, $jumlah_order)
            : DB::table('transaksi_detail')->insertGetId([
                'id_transaksi'     => $idTransaksi,
                'id_produk'        => $item['id_produk'],
                'nama_file'        => $item['nama_file'],
                'thumbnail'        => $item['thumbnail'],
                'catatan_produksi' => $item['catatan_produksi'],
                'p_ukuran'         => $item['p'],
                'l_ukuran'         => $item['l'],
                'qty'              => $jumlah_order,
                'id_finishing'     => $item['id_finishing'],
                'biaya_finishing'  => $item['biaya_finishing'],
                'biaya_cetak'      => $item['biaya_cetak'],
                'biaya_desain'     => $item['biaya_desain'],
                'file_siap_cetak'  => $item['file_siap_cetak'] ? 1 : 0,
                'id_desainer'      => $item['id_desainer'],
                'komisi_desain_persen' => $komisiDesain['persen'],
                'komisi_desain'    => $komisiDesain['komisi'],
                'komisi_status'    => $komisiDesain['komisi'] > 0 ? 'Belum' : null,
                'status_urgensi'   => $item['status_urgensi'],
                // status_urgent (kolom lama, tinyint) tetap diisi supaya
                // layar SPK yang sudah ada (toggleUrgent/bulkUrgent, sorting
                // urgent di spk/index) tetap jalan tanpa perubahan - Urgent
                // & Very Urgent dari kasir keduanya dianggap "urgent" di
                // sana, bedanya cuma tampil di status_urgensi.
                'status_urgent'    => $item['status_urgensi'] !== 'Normal' ? 1 : 0,
                'status_finishing' => $item['status_finishing'],
                'subtotal'         => $item['subtotal'],
                'status_spk'       => 'Antre',
                // Penanda baris susulan supaya bisa dibedakan dari item asli
                // nota (dipakai di detail nota & SPK).
                'is_susulan'       => $isSusulan ? 1 : 0,
                // Mode HYBRID minimum luas: penanda selisih minimum yang
                // digulung ke baris item ini (tidak ada lagi baris minimum
                // terpisah), dipakai nota print/monitoring kasir.
                'is_minimum_order' => !empty($item['is_minimum_order']) ? 1 : 0,
                // harga_jual_snapshot dulu diisi = subtotal (harga jual final
                // baris ini). hpp_snapshot diisi setelah loop BOM di bawah,
                // karena baru diketahui setelah menjumlah biaya tiap bahan.
                'harga_jual_snapshot' => $item['subtotal'],
            ]);

        // Rincian finishing per item (boleh lebih dari satu).
        $this->tulisFinishingItem($id_detail, $item['finishing_list'] ?? []);

        // BOM + tinta/toner mesin produk (lihat BomCalculationService::komponen).
        $hppItemIni = $this->pakaiBahanItem($item, $id_detail, $referensiKartu, $keteranganKartu, $jumlah_order);

        return [$id_detail, $hppItemIni];
    }

    public function perbaruiBarisItemProduk(array $item, int $idDetail, array $komisiDesain, $jumlah_order): int
    {
        DB::table('transaksi_detail')->where('id_detail', $idDetail)->update([
            'id_produk'        => $item['id_produk'],
            'nama_file'        => $item['nama_file'],
            'thumbnail'        => $item['thumbnail'],
            'catatan_produksi' => $item['catatan_produksi'],
            'p_ukuran'         => $item['p'],
            'l_ukuran'         => $item['l'],
            'qty'              => $jumlah_order,
            'id_finishing'     => $item['id_finishing'],
            'biaya_finishing'  => $item['biaya_finishing'],
            'biaya_cetak'      => $item['biaya_cetak'],
            'biaya_desain'     => $item['biaya_desain'],
            'file_siap_cetak'  => $item['file_siap_cetak'] ? 1 : 0,
            'id_desainer'      => $item['id_desainer'],
            'komisi_desain_persen' => $komisiDesain['persen'],
            'komisi_desain'    => $komisiDesain['komisi'],
            'komisi_status'    => $komisiDesain['komisi'] > 0 ? 'Belum' : null,
            'status_urgensi'   => $item['status_urgensi'],
            'status_urgent'    => $item['status_urgensi'] !== 'Normal' ? 1 : 0,
            'status_finishing' => $item['status_finishing'],
            'subtotal'         => $item['subtotal'],
            'harga_jual_snapshot' => $item['subtotal'],
            // Penanda baris minimum ikut diperbarui saat EDIT SELISIH, supaya
            // baris yang dulu ditandai minimum tidak tetap berflag 1 setelah
            // ukurannya diubah jadi cukup (dan sebaliknya).
            'is_minimum_order' => !empty($item['is_minimum_order']) ? 1 : 0,
            'updated_at'       => now(),
        ]);

        // Rincian finishing lama dibuang; yang baru ditulis ulang oleh
        // pemanggil dengan isi validasi terbaru.
        DB::table('transaksi_detail_finishing')->where('id_detail', $idDetail)->delete();

        return $idDetail;
    }

    public function tulisFinishingItem(int $idDetail, array $daftarFinishing): void
    {
        foreach ($daftarFinishing as $fin) {
            $finishing = DB::table('finishing')->where('id_finishing', $fin['id_finishing'])->first();
            if ($finishing && $finishing->nama_finishing === 'Potong Manual A3') {
                $hasTier = DB::table('finishing_harga')->where('id_finishing', $fin['id_finishing'])->exists();
                if (!$hasTier) {
                    throw new \App\Exceptions\ValidasiNotaException('Tier harga Potong Manual A3 belum diisi, hubungi admin.');
                }
            }
            DB::table('transaksi_detail_finishing')->insert([
                'id_detail' => $idDetail, 'id_finishing' => $fin['id_finishing'], 'status_finishing' => $fin['status_finishing'],
                'qty_pakai' => $fin['qty_pakai'], 'biaya' => $fin['biaya'], 'catatan' => $fin['catatan'],
                // Dimensi potong ikut ditulis. Sebelumnya kedua nilai ini
                // sudah dihitung dan dimasukkan ke $daftarFinishing (lihat
                // baris di atas blok komentar "Dimensi potong disimpan")
                // tapi TIDAK ikut ke INSERT, jadi tidak pernah sampai ke
                // database. Akibatnya biaya Potong Manual yang tersimpan
                // tidak bisa ditelusuri ulang: sisi media ada di
                // transaksi_detail.p_ukuran/l_ukuran/qty_cetak, sisi keping
                // yang dipotong hilang. Kolomnya dibuat migration
                // 2026_11_02_090000_simpan_dimensi_potong.php.
                // NULL (bukan 0) untuk finishing yang bukan LINEAR_GRID.
                'lebar_potong' => $fin['lebar_potong'] ?? null,
                'tinggi_potong' => $fin['tinggi_potong'] ?? null,
                // Ukuran media yang dipotong. Untuk produk 'ukuran' ini
                // sama dengan Panjang/Lebar item; untuk produk per lembar
                // (A3) inilah satu-satunya sumber ukuran media, jadi
                // tanpa kolom ini biaya potong A3 tidak bisa dihitung
                // ulang. Kolomnya dibuat migration
                // 2026_11_06_090000_potong_manual_a3.php.
                'lebar_media' => $fin['lebar_media'] ?? null,
                'tinggi_media' => $fin['tinggi_media'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function pakaiBahanItem(array $item, int $idDetail, string $referensiKartu, string $keteranganKartu, $jumlah_order): float
    {
        $boms = \App\Services\BomCalculationService::komponen(
            (int) $item['id_produk'], (float) $item['p'], (float) $item['l'], (float) $jumlah_order
        );

        $hppItemIni = 0;

        foreach ($boms as $bom) {

            $qty_pakai = $bom->qty_pakai;

            if ($qty_pakai > 0) {
                // Baca stok_sebelum dengan lock supaya aman dari order
                // bersamaan, lalu catat ke kartu_stok (tipe OUT) - tanpa
                // ini, konsumsi bahan baku per order tidak pernah
                // tercatat di kartu_stok sama sekali (hanya kedatangan
                // barang yang tercatat sebagai IN), sehingga riwayat
                // stok tidak pernah bisa dipakai untuk menjelaskan
                // kenapa stok berkurang atau sampai bisa negatif.
                $bahan = DB::table('bahan_baku')
                    ->where('id_bahan', $bom->id_bahan)
                    ->lockForUpdate()
                    ->first();

                // $qty_pakai dihitung dalam satuan HITUNG/pakai (mis. m2
                // dari (P*L)/10000, atau lembar untuk metode 'Q'), sedangkan
                // bahan_baku.stok & harga_rata_rata dilacak dalam satuan
                // STOK/beli (mis. roll, rim) - dua satuan yang berbeda.
                // nilai_konversi ("1 satuan stok = berapa satuan hitung",
                // mis. 1 rim = 500 lembar) adalah faktor konversi di antara
                // keduanya yang SUDAH ADA di skema tapi sebelumnya tidak
                // pernah dipakai di sini - qty_pakai (lembar/m2) langsung
                // dikurangkan dari stok (rim/roll) tanpa konversi, membuat
                // stok terkuras ratusan kali lebih cepat dari seharusnya
                // untuk bahan dengan nilai_konversi besar (mis. AC 210gr:
                // order 1 lembar salah-salah mengurangi stok 1 RIM penuh),
                // dan HPP snapshot ikut salah kali nilai_konversi juga.
                $nilaiKonversi = (float) ($bahan->nilai_konversi ?? 1);
                if ($nilaiKonversi <= 0) {
                    $nilaiKonversi = 1;
                }
                $qtyPakaiSatuanStok = $qty_pakai / $nilaiKonversi;

                $stokSebelum = $bahan->stok ?? 0;
                $stokSesudah = $stokSebelum - $qtyPakaiSatuanStok;

                DB::table('bahan_baku')
                    ->where('id_bahan', $bom->id_bahan)
                    ->update(['stok' => $stokSesudah]);

                DB::table('kartu_stok')->insert([
                    'id_bahan'      => $bom->id_bahan,
                    'tanggal'       => now(),
                    'tipe'          => 'OUT',
                    'qty'           => $qtyPakaiSatuanStok,
                    'stok_sebelum'  => $stokSebelum,
                    'stok_sesudah'  => $stokSesudah,
                    'referensi'     => $referensiKartu,
                    'keterangan'    => $keteranganKartu,
                    'created_by'    => Auth::id(),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                // Hardening P0-1: snapshot qty_pakai PER BARIS ITEM (bukan
                // dihitung ulang dari BOM live saat reversal - lihat
                // catatan lengkap di migrasi transaksi_detail_bahan_pakai
                // dan StokReversalService).
                DB::table('transaksi_detail_bahan_pakai')->insert([
                    'id_detail'  => $idDetail,
                    'id_bahan'   => $bom->id_bahan,
                    'qty_pakai'  => $qtyPakaiSatuanStok,
                    'is_tinta'   => $bom->is_tinta,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $hppItemIni += $qtyPakaiSatuanStok * (float) ($bahan->harga_rata_rata ?? 0);
            }
        }

        if ($hppItemIni > 0) {
            DB::table('transaksi_detail')->where('id_detail', $idDetail)->update([
                'hpp_snapshot' => $hppItemIni,
            ]);
        }

        return $hppItemIni;
    }

    public function validasiItems(Request $request, int $idGolongan, bool $hargaTerkunci, bool $bolehInputBiayaDesain, ?\Illuminate\Support\Collection $itemsLama = null, ?callable $itemBerubah = null): array
    {
        $itemsTervalidasi = [];

    // Perf (N+1): dulu tiap baris keranjang menjalankan query master-nya
    // sendiri (produk/jasa/finishing) di dalam loop - keranjang 20 item
    // berarti puluhan query identik per checkout. Sekarang semua master
    // yang dirujuk items di-prefetch SEKALI di atas, loop tinggal baca
    // dari collection yang sudah di-keyBy id. Semantik cek "tidak
    // ditemukan" tetap sama persis (get() mengembalikan null).
    $idJasaReq = collect($request->items)->filter(fn ($i) => !empty($i['id_jasa']))->map(fn ($i) => (int) $i['id_jasa'])->unique()->values()->all();
    $idProdukReq = collect($request->items)->filter(fn ($i) => empty($i['id_jasa']) && !empty($i['id_produk']))->map(fn ($i) => (int) $i['id_produk'])->unique()->values()->all();
    $idFinishingReq = collect($request->items)
        ->flatMap(function ($i) {
            $daftar = is_array($i['finishing'] ?? null) ? $i['finishing'] : [];
            if (!$daftar && !empty($i['id_finishing'])) $daftar = [['id_finishing' => $i['id_finishing']]];
            return collect($daftar)->pluck('id_finishing');
        })->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();

    $jasaRows = $idJasaReq ? DB::table('jasa')->whereIn('id_jasa', $idJasaReq)->get()->keyBy('id_jasa') : collect();
    $produkRows = $idProdukReq ? DB::table('produk')->whereIn('id_produk', $idProdukReq)->get()->keyBy('id_produk') : collect();
    $finishingRows = $idFinishingReq ? DB::table('finishing')->whereIn('id_finishing', $idFinishingReq)->get()->keyBy('id_finishing') : collect();

    // Pengelompokan minimum luas outdoor (per kelompok_minimum) dikumpulkan
    // di sini selama loop, lalu diproses setelah semua item tervalidasi
    // agar total aktual per kelompok diketahui. Struktur:
    //   kelompokLabel => [ 'min' => float, 'items' => [ ['id_produk'=>.., 'area'=>.., 'p'=>.., 'l'=>.., 'qty'=>..] ] ]
    $minLuasGroups = [];

    foreach ($request->items as $index => $item) {

        // Baris Jasa (bukan produk fisik) - tidak punya BOM/stok, harga
        // ditentukan manual oleh kasir (sama seperti biaya_desain), bukan
        // dari price list. Divalidasi terpisah lalu lanjut ke baris berikutnya.
        if (!empty($item['id_jasa'])) {
            $jasa = $jasaRows->get((int) $item['id_jasa']);
            if (!$jasa) {
                throw new \App\Exceptions\ValidasiNotaException('Jasa pada baris ' . ($index + 1) . ' tidak ditemukan.');
            }

            $qtyJasa = (float) ($item['qty_input'] ?? 0);
            // Role tanpa hak ubah harga (Operator Desain/Produksi): harga jasa SELALU dari master, kiriman client diabaikan.
            $hargaJasa = $hargaTerkunci
                ? (float) HargaService::getHargaJasaEfektif((int) $jasa->id_jasa, $qtyJasa)
                : max(0, (float) ($item['harga_satuan'] ?? 0));

            if ($qtyJasa <= 0) {
                throw new \App\Exceptions\ValidasiNotaException('Qty jasa pada baris ' . ($index + 1) . ' tidak valid.');
            }
            if ($hargaJasa <= 0) {
                throw new \App\Exceptions\ValidasiNotaException('Harga jasa pada baris ' . ($index + 1) . ' harus diisi.');
            }

            $itemsTervalidasi[] = [
                'is_jasa'    => true,
                'id_jasa'    => $jasa->id_jasa,
                // Penanda baris nota asal (mode EDIT SELISIH). Tanpa ini
                // server tidak tahu baris mana yang diperbarui dan barang
                // mana yang baru - setiap penyimpanan edit akan menambah
                // baris dobel.
                'id_detail' => isset($item['id_detail']) ? (int) $item['id_detail'] : null,
                'nama_file'  => $item['nama_file'] ?? $jasa->nama_jasa,
                'qty_input'  => $qtyJasa,
                'subtotal'   => $hargaJasa * $qtyJasa,
            ];
            continue;
        }

        $produk = $produkRows->get((int) ($item['id_produk'] ?? 0));

        if (!$produk) {
            throw new \App\Exceptions\ValidasiNotaException('Produk pada baris ' . ($index + 1) . ' tidak ditemukan.');
        }

        $p = (float) ($item['p'] ?? 0);
        $l = (float) ($item['l'] ?? 0);
        $qtyInput = (float) ($item['qty_input'] ?? 0);

        // T4: validasi minimum (min_panjang/min_lebar untuk ukuran, min_order
        // untuk quantity). Hanya baris baru/berubah (id_detail dikenali +
        // itemBerubah); baris lama tak berubah dibebaskan. Berlaku juga jalur
        // API (validasiItems dipanggil dengan konteks yang sama).
        $idDetailLamaT4 = isset($item['id_detail']) ? (int) $item['id_detail'] : 0;
        $lamaT4 = ($itemsLama && $idDetailLamaT4) ? $itemsLama->get($idDetailLamaT4) : null;
        $tidakBerubahT4 = $lamaT4 && $itemBerubah ? !$itemBerubah($lamaT4, $item) : false;
        if (!$tidakBerubahT4) {
            if ($produk->metode_hitung === 'ukuran') {
                $minP = (float) ($produk->min_panjang ?? 0);
                $minL = (float) ($produk->min_lebar ?? 0);
                if ($minP > 0 && $p > 0 && $p < $minP) {
                    throw new \App\Exceptions\ValidasiNotaException('Baris ' . ($index + 1) . ': panjang ' . $p . ' cm di bawah minimum (' . $minP . ' cm).');
                }
                if ($minL > 0 && $l > 0 && $l < $minL) {
                    throw new \App\Exceptions\ValidasiNotaException('Baris ' . ($index + 1) . ': lebar ' . $l . ' cm di bawah minimum (' . $minL . ' cm).');
                }
            } else {
                $minQty = (float) ($produk->min_order ?? 0);
                if ($minQty > 0 && $qtyInput > 0 && $qtyInput < $minQty) {
                    throw new \App\Exceptions\ValidasiNotaException('Baris ' . ($index + 1) . ': qty ' . $qtyInput . ' di bawah minimum (' . $minQty . ').');
                }
            }
        }

        if ($qtyInput <= 0) {
            throw new \App\Exceptions\ValidasiNotaException('Qty pada baris ' . ($index + 1) . ' tidak valid.');
        }

        // Rumus qty-untuk-harga HARUS sama persis dengan hitungSubtotal() di
        // penjualan/create.blade.php, supaya harga yang ditagih konsisten
        // dengan yang ditampilkan ke operator sebelum disimpan.
        $qtyHitung = $produk->metode_hitung === 'ukuran'
            ? ($p * $l / 10000) * $qtyInput
            : $qtyInput;

        // Aturan minimum 1 m² untuk produk cetak luas (metode 'ukuran'): jika
        // luas item < 1 m² dan tidak ada item lain dalam nota yang >= 1 m²,
        // ditagih 1 m². Jika ada item >= 1 m², semua ditagih luas aktual.
        // Minimum luas outdoor (min_luas_total > 0): kumpulkan per kelompok
        // untuk diproses setelah loop (total per kelompok + baris selisih).
        if ($produk->metode_hitung === 'ukuran' && (float) ($produk->min_luas_total ?? 0) > 0) {
            $kelompok = \App\Support\MinimumLuas::kelompokLabel($produk->kelompok_minimum ?? null, (int) $produk->id_produk);
            $areaIni = ($p * $l / 10000) * $qtyInput;
            if (!isset($minLuasGroups[$kelompok])) {
                $minLuasGroups[$kelompok] = ['min' => (float) $produk->min_luas_total, 'items' => [], 'produkTerbesar' => null, 'areaTerbesar' => 0.0, 'idxTerbesar' => null];
            }
            $minLuasGroups[$kelompok]['items'][] = ['id_produk' => (int) $produk->id_produk, 'area' => $areaIni, 'p' => $p, 'l' => $l, 'qty' => $qtyInput];
            if ($areaIni > $minLuasGroups[$kelompok]['areaTerbesar']) {
                $minLuasGroups[$kelompok]['areaTerbesar'] = $areaIni;
                $minLuasGroups[$kelompok]['produkTerbesar'] = (int) $produk->id_produk;
                // Mode hybrid: selisih diGULUNG ke baris item terbesar, jadi
                // catat indexnya di $itemsTervalidasi. Baris produk selalu
                // di-push tepat satu kali per request di akhir iterasi, jadi
                // jumlah elemen SAAT INI = index push row ini nanti.
                $minLuasGroups[$kelompok]['idxTerbesar'] = count($itemsTervalidasi);
            }
        }

        if ($qtyHitung <= 0) {
            throw new \App\Exceptions\ValidasiNotaException('Ukuran/qty pada baris ' . ($index + 1) . ' tidak valid.');
        }

        // Harga promo (kalau sedang aktif) atau price list biasa - HargaService
        // adalah sumber yang sama persis dipakai layar POS untuk menampilkan
        // harga saat operator mengetik qty. Price List di sini TETAP wajib
        // ada (guard rail supaya tidak ada produk terjual tanpa harga pernah
        // diatur sama sekali) - tapi angkanya sendiri TIDAK LAGI otomatis
        // ditambahkan ke subtotal (lihat catatan biaya_cetak di bawah),
        // murni jadi SARAN nilai default untuk Biaya Cetak yang dikirim
        // client dari hitungSubtotal() di penjualan/create.blade.php.
        $siapCetak = filter_var($item['siap_cetak'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($qtyHitung > 0) {
            $hargaSatuan = HargaService::getHargaEfektif($produk->id_produk, (int) $idGolongan, max($qtyHitung, 1), $siapCetak)['harga_jual'];
        } else {
            $hargaSatuan = 0;
        }

        if ($hargaSatuan <= 0 && $qtyHitung > 0) {
            throw new \App\Exceptions\ValidasiNotaException($siapCetak
                    ? 'Harga File Siap Cetak untuk "' . $produk->nama_produk . '" belum diatur (menu Harga File Siap Cetak) untuk golongan konsumen ini.'
                    : 'Harga untuk "' . $produk->nama_produk . '" belum diatur di Price List untuk golongan konsumen ini.');
        }

        // Biaya Cetak SEKARANG jadi komponen UTAMA subtotal (menggantikan
        // hargaSatuan*qtyHitung, bukan lagi ditambahkan di atasnya) - selaras
        // dengan biaya_desain/biaya_finishing yang memang sudah lebih dulu
        // murni input manual operator (tidak ada tabel acuan yang mengikat,
        // biaya cetak riil tergantung mesin/ongkos produksi saat itu).
        // Defaultnya dikirim client = hargaSatuan*qtyHitung (Price List),
        // tapi operator bebas menimpa - sama persis tingkat kepercayaan yang
        // sudah berlaku untuk desain/finishing, cuma dipastikan tidak
        // negatif supaya tidak bisa dipakai mengurangi total.
        // File Siap Cetak = tanpa campur tangan tim desain: tidak ada biaya desain & tidak ada desainer/komisi.
        $biayaDesain = $siapCetak ? 0.0 : max(0, (float) ($item['biaya_desain'] ?? 0));
        $biayaCetak = max(0, (float) ($item['biaya_cetak'] ?? 0));

        // id_finishing opsional (produk boleh tanpa finishing). Divalidasi ke
        // tabel finishing supaya id yang dipalsukan dari client tidak lolos
        // ke foreign key transaksi_detail.id_finishing. biaya_finishing input
        // manual operator sama seperti biaya_desain (harga_default di master
        // Finishing cuma starting point, bukan harga final).
        // Finishing: DAFTAR (boleh lebih dari satu, mis. Laminasi + Kisscut). Bentuk lama (id_finishing tunggal)
        // tetap diterima dan diubah menjadi daftar satu elemen.
        $daftarFinishingMentah = is_array($item['finishing'] ?? null) ? $item['finishing'] : [];
        if (!$daftarFinishingMentah && !empty($item['id_finishing'])) {
            $daftarFinishingMentah = [[
                'id_finishing' => $item['id_finishing'], 'status_finishing' => $item['status_finishing'] ?? 'Standar',
                'biaya' => $item['biaya_finishing'] ?? 0, 'qty_pakai' => null, 'catatan' => null,
            ]];
        }
        if (count($daftarFinishingMentah) > 5) {
            throw new \App\Exceptions\ValidasiNotaException('Maksimal 5 finishing per item (baris ' . ($index + 1) . ').');
        }
        $daftarFinishing = [];
        $biayaFinishing = 0.0;
        foreach ($daftarFinishingMentah as $fin) {
            $idF = !empty($fin['id_finishing']) ? (int) $fin['id_finishing'] : null;
            $rowF = $idF ? $finishingRows->get($idF) : null;
            if (!$rowF) {
                throw new \App\Exceptions\ValidasiNotaException('Finishing pada baris ' . ($index + 1) . ' tidak ditemukan.');
            }
            $statusF = ($fin['status_finishing'] ?? $fin['status'] ?? 'Standar') === 'Custom' ? 'Custom' : 'Standar';
            $qtyPakai = isset($fin['qty_pakai']) && (float) $fin['qty_pakai'] > 0 ? (float) $fin['qty_pakai'] : null;

            // Qty acuan tarif finishing menurut metode_hitung finishing itu sendiri (sama dengan hitungan di form).
            $qtyF = match ($rowF->metode_hitung) {
                'luas' => $qtyHitung,
                'keliling' => $produk->metode_hitung === 'ukuran' ? (2 * ($p + $l) / 100) * $qtyInput : $qtyInput,
                // Finishing jahit keliling per keping: meter jahit =
                // JUMLAH KEPING di panel x keliling ukuran BARANG jahit.
                // Ukuran media cetak/nota (mis. 120 x 500) TIDAK ikut jahit -
                // contoh owner: barang 100 x 500 di roll 120 -> nota 120 x 500
                // tapi jahit keliling 100 x 500. Pengganda qty nota/barang
                // SADAR TIDAK pakai lagi (keputusan owner 2026-11-09): jumlah
                // keping di panel SUDAH menyatakan total barang, kalau
                // dikalikan qty lagi angkanya ganda. Barang kosong -> fallback
                // keliling ukuran nota (rumus keliling lama).
                'KEPING_ROLL' => self::meterJahitKepingRoll(
                    (float) ($fin['lebar_potong'] ?? 0),
                    (float) ($fin['tinggi_potong'] ?? 0),
                    $fin['keping'] ?? null, $p, $l
                ),
                // Potong Manual (LINEAR_GRID): tarif dihitung dari meter potong, BUKAN
                // dari qty pcs. lihat HargaService::hitungBiayaFinishingLinearGrid().
                'LINEAR_GRID' => $qtyHitung,
                default => $qtyPakai ?? $qtyInput,
            };

            // Ukuran media yang dipotong (dipakai mode LINEAR_GRID saja).
            $lebarMedia = 0.0;
            $tinggiMedia = 0.0;
            if ($rowF->metode_hitung === 'LINEAR_GRID') {
                // Biaya dihitung server dari dimensi potong yang dikirim form.
                // Kasir TIDAK boleh menentukan nominal sendiri untuk mode ini, karena
                // nominalnya turunan rumus (meter potong x tarif + minimum charge).
                $lebarPotong = (float) ($fin['lebar_potong'] ?? 0);
                $tinggiPotong = (float) ($fin['tinggi_potong'] ?? 0);

                // Ukuran MEDIA yang dipotong.
                //
                // Dua sumber, sesuai cara operator mengorder:
                //  - Produk ber-metode_hitung 'ukuran' (roll/banner): media =
                //    Panjang x Lebar yang diketik operator di form.
                //  - Produk ber-metode_hitung lain (mis. kertas/foto A3, dijual
                //    per lembar): form TIDAK punya field Panjang/Lebar, jadi
                //    operator mengisi ukuran lembar yang dipotong di panel
                //    Potong Manual dan mengirimnya lewat lebar_media/tinggi_media.
                // Tanpa sumber kedua, mode ini tidak mungkin dipakai untuk A3.
                $lebarMedia = (float) $p;
                $tinggiMedia = (float) $l;
                if ($lebarMedia <= 0 || $tinggiMedia <= 0) {
                    $lebarMedia = (float) ($fin['lebar_media'] ?? 0);
                    $tinggiMedia = (float) ($fin['tinggi_media'] ?? 0);
                }

                // Harga potong MANUAL: dipakai kalau finishing ini ber-spek
                // "Custom" DAN role pemakai punya hak mengubah harga. Rumus
                // LINEAR_GRID tetap jadi harga default, tapi ada kalanya
                // nominal potong tidak follow rumus (mis. potongan kecil sekali
                // dan operator tarifkan manual). Role tanpa hak ubah harga
                // tidak pernah bisa masuk jalur ini - angka dari browser
                // diabaikan, sama seperti finishing lain.
                $biayaManual = ($statusF === 'Custom' && !$hargaTerkunci)
                    ? max(0, (float) ($fin['biaya'] ?? 0))
                    : null;

                if ($biayaManual !== null && $biayaManual > 0) {
                    $biayaF = $biayaManual;
                } else {
                    $biayaF = HargaService::hitungBiayaFinishingLinearGrid(
                        $idF,
                        $lebarMedia,
                        $tinggiMedia,
                        $lebarPotong,
                        $tinggiPotong,
                        (float) $qtyHitung
                    );

                    if ($biayaF <= 0) {
                        throw new \App\Exceptions\ValidasiNotaException('Dimensi potong pada baris ' . ($index + 1) . ' tidak valid untuk finishing Potong Manual.');
                    }
                }
            } else {
                $standar = $qtyF > 0 ? round(HargaService::getHargaFinishingEfektif($idF, $qtyF) * $qtyF) : 0.0;

                // Standar (atau role tanpa hak ubah harga) = SELALU dihitung server dari master; hanya Custom oleh role berhak
                // yang boleh menentukan biaya sendiri.
                $biayaF = ($statusF === 'Standar' || $hargaTerkunci) ? $standar : max(0, (float) ($fin['biaya'] ?? 0));
            }

            $daftarFinishing[] = [
                'id_finishing' => $idF, 'status_finishing' => $statusF, 'qty_pakai' => $rowF->metode_hitung === 'quantity' ? ($qtyPakai ?? $qtyInput) : null,
                'biaya' => $biayaF, 'catatan' => isset($fin['catatan']) && trim((string) $fin['catatan']) !== '' ? mb_substr(trim((string) $fin['catatan']), 0, 255) : null,
                // Dimensi potong dibawa ke bawah supaya ikut tersimpan bersama
                // baris finishing-nya. Tanpa ini hanya `biaya` yang bertahan dan
                // angka Potong Manual tidak bisa ditelusuri ulang. Dipakai juga
                // oleh hitungUlangPotongManualDiDaftar() di layar kasir, jadi
                // jangan dibuang di sini.
                // Dimensi barang jahit (KEPING_ROLL) dipakai ulang pola Potong
                // Manual: ikut tersimpan supaya meter jahit bisa ditelusuri.
                'lebar_potong' => in_array($rowF->metode_hitung, ['LINEAR_GRID', 'KEPING_ROLL'], true)
                    ? (float) ($fin['lebar_potong'] ?? 0) : null,
                'tinggi_potong' => in_array($rowF->metode_hitung, ['LINEAR_GRID', 'KEPING_ROLL'], true)
                    ? (float) ($fin['tinggi_potong'] ?? 0) : null,
                // Jumlah keping barang jahit (metode KEPING_ROLL saja).
                'keping' => $rowF->metode_hitung === 'KEPING_ROLL' ? max(1, (int) ($fin['keping'] ?? 1)) : null,
                // Ukuran media yang dipotong. Untuk produk 'ukuran' sama
                // dengan Panjang/Lebar item; untuk produk per lembar (A3) ini
                // satu-satunya sumber ukuran media, jadi WAJIB ikut disimpan
                // - tanpa ini biaya potong tidak bisa ditelusuri ulang.
                'lebar_media' => $rowF->metode_hitung === 'LINEAR_GRID' ? ($lebarMedia > 0 ? $lebarMedia : null) : null,
                'tinggi_media' => $rowF->metode_hitung === 'LINEAR_GRID' ? ($tinggiMedia > 0 ? $tinggiMedia : null) : null,
            ];
            $biayaFinishing += $biayaF;
        }
        $idFinishing = $daftarFinishing[0]['id_finishing'] ?? null;

        // Role tanpa hak ubah harga: biaya cetak & desain juga dihitung server (finishing sudah dihitung di atas).
        // CATATAN PENTING - dua kunci yang TIDAK boleh digabung lagi:
        //
        // 1. $biayaCetak TETAP dikunci server untuk role tanpa akses harga. Harga produk
        //    adalah angka milik toko; mengizinkan client menimpanya sama dengan memberi
        //    harga gratis. Tidak ada exception apa pun di sini - nilai dari browser
        //    diabaikan total dan dihitung ulang dari master.
        //
        // 2. $biayaDesain TIDAK lagi ikut terkunci hanya karena role tidak bisa melihat
        //    harga. Biaya desain bukan harga barang: itu nilai jasa yang diinput orang
        //    yang mengerjakan desain (role Operator Desain), dan tidak pernah tampil di
        //    halaman manapun milik role itu. Pemisahan izinnya lewat
        //    roles.boleh_input_biaya_desain (lihat AksesHarga::bolehInputBiayaDesain),
        //    jadi Operator Desain boleh menginput biaya yang ia kerjakan tanpa ikut
        //    membuka seluruh harga produk/cetak/finishing.
        //
        // Kolomnya default 0 (gagal-terutup): role yang tidak dicentang di Pengaturan
        // Hak Akses tetap dipaksa 0 di sini, sama seperti sebelumnya.
        if ($hargaTerkunci) {
            $biayaCetak = round($hargaSatuan * $qtyHitung);
        }

        if (!$bolehInputBiayaDesain) {
            $biayaDesain = 0;
        }

        $statusUrgensi = in_array($item['status_urgensi'] ?? null, ['Normal', 'Urgent', 'Very Urgent'])
            ? $item['status_urgensi'] : 'Normal';
        $statusFinishing = in_array($item['status_finishing'] ?? null, ['Standar', 'Custom'])
            ? $item['status_finishing'] : 'Standar';

        // thumbnail sudah diunggah sebelumnya lewat endpoint upload-thumbnail
        // terpisah (lihat uploadThumbnail()) - baris ini cuma membawa path
        // string yang dikembalikan endpoint itu, bukan file binary (payload
        // /penjualan/store tetap JSON seperti sebelumnya, tidak diubah jadi
        // multipart). Path divalidasi berada di dalam folder upload yang
        // benar supaya client tidak bisa menyelipkan path sembarang.
        $thumbnail = null;
        if (!empty($item['thumbnail']) && str_starts_with($item['thumbnail'], 'order_thumbnails/')) {
            $thumbnail = $item['thumbnail'];
        }

        $catatanProduksi = trim((string) ($item['catatan_produksi'] ?? ''));
        if ($siapCetak) {
            // Penanda ikut tercetak di SPK & tampil di daftar produksi tanpa mengubah layar-layar itu.
            $catatanProduksi = trim('[FILE SIAP CETAK - dari pelanggan, tanpa desain tim] ' . $catatanProduksi);
        }

        $itemsTervalidasi[] = [
            'is_jasa' => false,
            'id_produk' => $produk->id_produk,
            // Penanda baris nota asal (mode EDIT SELISIH) - lihat catatan di
            // cabang jasa di atas.
            'id_detail' => isset($item['id_detail']) ? (int) $item['id_detail'] : null,
            'nama_file' => $item['nama_file'] ?? '-',
            'thumbnail' => $thumbnail,
            'catatan_produksi' => $catatanProduksi !== '' ? $catatanProduksi : null,
            'p' => $p,
            'l' => $l,
            'qty_input' => $qtyInput,
            'qty_hitung' => $qtyHitung,
            'id_finishing' => $idFinishing,
            'finishing_list' => $daftarFinishing,
            'biaya_finishing' => $biayaFinishing,
            'biaya_cetak' => $biayaCetak,
            // Acuan price list (harga per unit x qty hitung) - dipakai HANYA untuk mencatat selisih bila kasir menimpa biaya cetak.
            'harga_standar' => round($hargaSatuan * $qtyHitung),
            'biaya_desain' => $biayaDesain,
            'file_siap_cetak' => $siapCetak,
            // Desainer yang menerapkan biaya desain + komisi menurut kelasnya (snapshot saat nota dibuat).
            // kunciDesainer(): kalau yang membuat nota adalah Operator Desain,
            // desainernya WAJIB dirinya sendiri - nilai id_desainer dari browser
            // diabaikan supaya komisi tidak bisa dialihkan ke nama lain.
            'id_desainer' => $biayaDesain > 0 ? \App\Services\DesainKomisiService::kunciDesainer(isset($item['id_desainer']) ? (int) $item['id_desainer'] : null) : null,
            'status_urgensi' => $statusUrgensi,
            'status_finishing' => collect($daftarFinishing)->contains('status_finishing', 'Custom') ? 'Custom' : $statusFinishing,
            'subtotal' => $biayaCetak + $biayaDesain + $biayaFinishing,
        ];
    }

        // Minimum luas outdoor, mode HYBRID (keputusan owner, notasi SETUJU T4-A):
    // selisih tidak lagi jadi baris terpisah (bersih dari nota, SPK, dan
    // produksi), tetapi DIGULUNG ke subtotal baris item TERBESAR di kelompok
    // itu. Grup total = tarif + selisihHarga sehingga, dengan tarif seragam
    // satu kelompok, hasilnya tarif x min_luas_total kelompok - sama dengan
    // aturan final A tapi tanpa baris minimum yang dulu memenuhi nota.
    // Server tetap satu-satunya sumber hitungan.
    foreach ($minLuasGroups as $kelompok => $g) {
        $totalAktual = 0.0;
        foreach ($g['items'] as $it) {
            $totalAktual += (float) $it['area'];
        }
        $totalAktual = round($totalAktual, 4);
        $minLuas = round((float) $g['min'], 4);
        $selisih = \App\Support\MinimumLuas::selisih($totalAktual, $minLuas);
        if ($selisih <= 0) {
            continue;
        }
        // Tarif diambil dari produk dengan luas aktual terbesar di kelompok, di tier qty = selisih.
        $idProdukHarga = $g['produkTerbesar'];
        $hargaSatuan = 0.0;
        if ($idProdukHarga) {
            $resHarga = \App\Services\HargaService::getHargaEfektif($idProdukHarga, (int) $idGolongan, $selisih, false);
            $hargaSatuan = (float) ($resHarga['harga_jual'] ?? 0);
        }
        if ($hargaSatuan <= 0) {
            throw new \App\Exceptions\ValidasiNotaException('Harga Minimum Order untuk kelompok "' . $kelompok . '" belum diatur di Price List untuk golongan konsumen ini.');
        }
        $selisihHarga = round($hargaSatuan * $selisih, 2);
        $idxTerbesar = $g['idxTerbesar'] ?? null;
        if ($idxTerbesar === null || !isset($itemsTervalidasi[$idxTerbesar]) || !empty($itemsTervalidasi[$idxTerbesar]['is_jasa'])) {
            // Tidak pernah seharusnya terjadi (idx dicatat saat item produk
            // terbesar terdaftar); gagal jelas daripada menagih tanpa baris.
            throw new \App\Exceptions\ValidasiNotaException('Baris Minimum Order untuk kelompok "' . $kelompok . '" tidak ditemukan di keranjang.');
        }
        $baris = $itemsTervalidasi[$idxTerbesar];
        $baris['biaya_cetak'] += $selisihHarga;
        $baris['subtotal'] += $selisihHarga;
        // Penanda baris minimum pindah ke baris ITEM (bukan baris terpisah).
        $baris['is_minimum_order'] = 1;
        // Catatan ikut tersimpan supaya nota/SPK bisa menjelaskan dari mana
        // tambahan subtotal itu (baris keranjang/frontend print menampilkan catatan ini).
        $catatanMin = 'Termasuk Minimum Order ' . $kelompok . ': selisih ' . rtrim(rtrim(number_format($selisih, 2, '.', ''), '0'), '.') . ' m²';
        $baris['catatan_produksi'] = trim(($baris['catatan_produksi'] ?? '') . ' ' . $catatanMin);
        // Selisih ditambahkan ke harga_standar agar log selisih harga_manual
        // tidak salah memperlakukan biaya minimum sebagai penimpaan manual
        // kasir (harga_standar tetap referensi price list + biaya minimum).
        $baris['harga_standar'] = round((float) $baris['harga_standar'] + $selisihHarga, 2);
        $itemsTervalidasi[$idxTerbesar] = $baris;
    }

    return $itemsTervalidasi;
    }

    /**
     * Meter jahit per metode KEPING_ROLL (rumus owner 2026-11-09): jumlah
     * keping x keliling ukuran barang jahit per keping. TANPA pengganda qty
     * (jumlah keping di panel sudah total; dikali qty lagi angkanya ganda).
     * Ukuran barang kosong -> fallback keliling ukuran nota (rumus keliling
     * lama) supaya kekurangan isian tidak membuat biaya jahit jadi nol.
     */
    public static function meterJahitKepingRoll(float $lebarBarang, float $panjangBarang, $keping, float $pNota = 0.0, float $lNota = 0.0): float
    {
        if ($lebarBarang > 0 && $panjangBarang > 0 && (float) $keping >= 1) {
            return (float) $keping * 2 * ($lebarBarang + $panjangBarang) / 100;
        }

        return 2 * ($pNota + $lNota) / 100;
    }
}
