<?php

namespace App\Http\Controllers;

use App\Services\AccountingService;

use App\Services\KasSesiService;
use App\Services\DocumentNumberService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\AppSetting;

/**
 * Penjualan Barang - jual BAHAN BAKU langsung ke konsumen (mis. sisa roll
 * vinyl, bahan mentah lain) TANPA lewat proses cetak/produksi. Sengaja
 * TERINTEGRASI PENUH ke sistem transaksi/nota yang sama dengan Input Order
 * (bukan modul terpisah) - satu baris transaksi_detail baru cukup diisi
 * id_bahan (kolom baru, sejajar id_produk/id_jasa yang sudah ada, migrasi
 * 2026_09_18_100000), sisanya (nota, pembayaran, kas eksklusif, laporan)
 * otomatis ikut jalur yang sudah ada tanpa perlu ditulis ulang.
 *
 * Harga SENGAJA manual/hardcode per baris (bukan dari Price List) - harga
 * bahan baku berubah-ubah, tidak ada tabel harga jual bahan yang bisa
 * dijadikan acuan otomatis (beda dengan produk cetak yang punya
 * setting_harga).
 */
class PenjualanBarangController extends Controller
{
    public function create()
    {
        $konsumen = DB::table('konsumen')->orderBy('nama_konsumen')->get();
        $golongan = DB::table('golongan_konsumen')->get();
        $kategori = DB::table('kategori')->orderBy('nama_kategori')->get();

        // Bahan baku beserta info tampilan (kategori, satuan) - status aktif
        // saja, sama seperti aturan di seluruh modul bahan baku lainnya
        // (nonaktif = disembunyikan dari pilihan baru).
        $bahan = DB::table('bahan_baku')
            ->leftJoin('satuan', 'bahan_baku.id_satuan_stok', '=', 'satuan.id_satuan')
            ->where('bahan_baku.status', 'aktif')
            ->select(
                'bahan_baku.id_bahan',
                'bahan_baku.nama_bahan',
                'bahan_baku.id_kategori',
                'bahan_baku.stok',
                DB::raw("COALESCE(satuan.nama_satuan, '-') as nama_satuan")
            )
            ->orderBy('bahan_baku.nama_bahan')
            ->get();

        // Jasa untuk tab "Jasa" di halaman yang sama. Hanya yang punya
        // harga dasar - jasa tanpa harga tidak bisa dijual karena kolom
        // harga di form diisi dari master, dan kasir tidak boleh
        // menemukan daftar jasa yang isinya tidak bisa langsung dipakai.
        $jasa = DB::table('jasa')
            ->leftJoin('satuan', 'jasa.id_satuan', '=', 'satuan.id_satuan')
            ->whereNotNull('jasa.harga_default')
            ->where('jasa.harga_default', '>', 0)
            ->select(
                'jasa.id_jasa',
                'jasa.nama_jasa',
                'jasa.id_mesin',
                'jasa.harga_default',
                'jasa.biaya_operasional',
                'jasa.biaya_tinta',
                DB::raw('COALESCE(satuan.nama_satuan, "Pcs") as nama_satuan'),
                DB::raw('COALESCE(satuan.tipe_satuan, "Barang") as tipe_satuan')
            )
            ->orderBy('jasa.nama_jasa')
            ->get();

        // Divisi untuk filter. Sama seperti Master Jasa: hanya mesin yang
        // tampil sebagai divisi di produksi.
        $mesinJasa = DB::table('mesin')
            ->where('tampil_divisi', true)
            ->orderBy('nama_mesin')
            ->get(['id_mesin', 'nama_mesin']);

        $date = date('Ymd');
        // Perf dataset besar: rentang sargable (DATE() mematikan index tgl_order).
        $lastNota = DB::table('transaksi')
            ->where('tgl_order', '>=', $date . ' 00:00:00')
            ->where('tgl_order', '<', date('Y-m-d', strtotime('+1 day')) . ' 00:00:00')
            ->count();
        $prefixNota = AppSetting::current()->nomor_nota_prefix ?: 'INV';
        $no_nota = $prefixNota . '-' . $date . '-' . str_pad($lastNota + 1, 3, '0', STR_PAD_LEFT);

        return view('penjualan-barang.create', compact(
            'konsumen', 'golongan', 'kategori', 'bahan', 'no_nota', 'jasa', 'mesinJasa'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_konsumen' => 'required|exists:konsumen,id_konsumen',
            'items' => 'required|array|min:1',
            'items.*.tipe' => 'required|in:bahan,jasa,custom',
            'items.*.qty' => 'required|numeric|min:0.01',
            // Harga manual per transaksi (sesuai rancangan halaman ini), tetapi tidak boleh 0: layar sudah menolaknya,
            // server juga, supaya kiriman ulang/form lama tidak menyimpan baris gratis tanpa sengaja.
            'items.*.harga' => 'required|numeric|gt:0',
            // Bahan Baku: wajib pilih dari master (stok ikut berkurang).
            'items.*.id_bahan' => 'required_if:items.*.tipe,bahan|nullable|exists:bahan_baku,id_bahan',
            // Jasa: wajib pilih dari Master Jasa. Nama diambil ulang dari master; harga master hanya isian awal
            // di layar dan boleh ditimpa kasir, jadi harga yang dikirim yang dipakai.
            'items.*.id_jasa' => 'required_if:items.*.tipe,jasa|nullable|exists:jasa,id_jasa',
            // Custom/Lainnya: barang/jasa yang tidak ada di data bahan baku,
            // nama diisi bebas, TIDAK memotong stok apa pun.
            'items.*.nama' => 'required_if:items.*.tipe,custom|nullable|string|max:255',
            // Jasa bersatuan luas/panjang: ukuran dan jumlah keping. Qty dihitung ulang di server.
            'items.*.p' => 'nullable|numeric|min:0',
            'items.*.l' => 'nullable|numeric|min:0',
            'items.*.jumlah' => 'nullable|numeric|min:0.01',
        ]);

        // Order (belum tentu pembayaran) HANYA boleh dibuat selama toko
        // "buka" (ada kas aktif hari ini, siapa pun pemiliknya) - guard yang
        // SAMA PERSIS dengan PenjualanController::store(), supaya penjualan
        // barang juga tidak bisa terjadi di luar jam operasional kas.
        $kasHariIni = KasSesiService::aktifUntukTransaksi();

        if (!$kasHariIni) {
            return redirect()->back()->with('error', 'Kasir belum dibuka! Buka kas terlebih dahulu sebelum membuat order baru.')->withInput();
        }

        DB::beginTransaction();
        try {
            // P0-5: nomor nota di-generate ATOMIK lewat DocumentNumberService
            // (sama seperti PenjualanController::store) - pola COUNT(*)+1 di
            // bawah itu rawan race condition: dua order bersamaan melihat
            // "nomor berikutnya" yang sama sebelum salah satu commit, lalu
            // gagal UNIQUE constraint nomor_nota (500). Padahal service-nya
            // sudah ada dan sudah dipakai jalur Input Order.
            $prefixNota = AppSetting::current()->nomor_nota_prefix ?: 'INV';
            $no_nota = DocumentNumberService::generate($prefixNota);

            // Jaring pengaman untuk data lama: kalau nomor kebetulan sudah
            // pernah dipakai nota ber-pattern COUNT lama, naikkan urutannya
            // sampai bebas (maksimal beberapa percobaan - bukan loop tanpa batas).
            for ($coba = 0; $coba < 5 && DB::table('transaksi')->where('nomor_nota', $no_nota)->exists(); $coba++) {
                $no_nota = DocumentNumberService::generate($prefixNota);
            }
            if (DB::table('transaksi')->where('nomor_nota', $no_nota)->exists()) {
                throw new \Exception('Gagal menyiapkan nomor nota - nomor masih bentrok. Coba lagi.');
            }

            $idTransaksi = DB::table('transaksi')->insertGetId([
                'nomor_nota' => $no_nota,
                'tipe_order' => 'Reguler',
                'id_konsumen' => $request->id_konsumen,
                'id_user_kasir' => Auth::id(),
                'tgl_order' => now(),
                'total_bayar' => 0,
                'status_pembayaran' => 'Belum Bayar',
                'status_nota' => 'Aktif',
                // Penjualan barang langsung: tidak lewat antrean produksi/SPK
                // (semua detail 'Selesai'), kasir melayani saat itu juga -
                // dianggap sudah diterima agar bisa langsung dibayar.
                'status_order' => 'Diterima Kasir',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $grandTotal = 0;

            foreach ($request->items as $index => $item) {
                $qty = (float) $item['qty'];
                $ukuran = null;
                if (($item['tipe'] ?? '') === 'jasa') {
                    $ukuran = self::ukuranJasa((int) $item['id_jasa'], $item);
                    if ($ukuran) {
                        $qty = $ukuran['qty'];
                    }
                }
                $harga = (float) $item['harga'];
                $subtotal = $qty * $harga;
                $grandTotal += $subtotal;

                // JASA: jasa dan namanya diambil ulang dari master (bukan nama kiriman form). Harganya mengikuti
                // isian kasir: harga master hanya isian awal di layar dan boleh disesuaikan per transaksi.
                if (($item['tipe'] ?? '') === 'jasa') {
                    $jasa = DB::table('jasa')->where('id_jasa', $item['id_jasa'])->first();
                    if (! $jasa) {
                        throw new \Exception('Jasa pada baris ' . ($index + 1) . ' tidak ditemukan.');
                    }

                    // hpp_snapshot = biaya bahan sesuai BOM; jasa tidak ber-BOM sehingga 0, sama dengan
                    // jalur Input Pesanan (lihat LaporanController::hpp). Dulu diisi harga jual master.
                    $hppJasa = 0;

                    DB::table('transaksi_detail')->insert([
                        'id_transaksi'        => $idTransaksi,
                        'id_produk'           => null,
                        'id_jasa'             => $jasa->id_jasa,
                        'id_bahan'            => null,
                        'nama_file'           => $jasa->nama_jasa,
                        'p_ukuran'            => $ukuran['p'] ?? 0,
                        'l_ukuran'            => $ukuran['l'] ?? 0,
                        'qty'                 => $qty,
                        'biaya_cetak'         => $subtotal,
                        'biaya_desain'        => 0,
                        'biaya_finishing'     => 0,
                        'subtotal'            => $subtotal,
                        'harga_jual_snapshot' => $subtotal,
                        'hpp_snapshot'        => $hppJasa,
                        // Jasa tidak lewat produksi/SPK sama sekali -
                        // langsung "Selesai", sama seperti penjualan bahan.
                        'status_spk'          => 'Selesai',
                        'status_urgensi'      => 'Normal',
                        'status_finishing'    => 'Standar',
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);

                    // Jasa tidak menyentuh stok sama sekali - bukan
                    // item inventaris. status_spk-nya sudah 'Selesai'
                    // di atas supaya tidak nyangkut di antrean produksi.
                    continue;
                }

                if ($item['tipe'] === 'custom') {
                    // Barang/jasa CUSTOM: tidak ada di data master bahan baku
                    // (mis. barang titipan, jasa tambahan dadakan, barang
                    // bekas/rusak yang dijual sekali) - nama & harga murni
                    // manual, hpp_snapshot=0 (tidak ada acuan biaya, sama
                    // seperti baris Jasa di Input Order), dan TIDAK menyentuh
                    // stok/kartu_stok sama sekali karena bukan item inventaris.
                    DB::table('transaksi_detail')->insert([
                        'id_transaksi' => $idTransaksi,
                        'id_bahan' => null,
                        'nama_file' => $item['nama'],
                        'p_ukuran' => 0,
                        'l_ukuran' => 0,
                        'qty' => $qty,
                        'biaya_cetak' => $subtotal,
                        'biaya_desain' => 0,
                        'biaya_finishing' => 0,
                        'subtotal' => $subtotal,
                        'harga_jual_snapshot' => $subtotal,
                        'hpp_snapshot' => 0,
                        'status_spk' => 'Selesai',
                        'status_urgensi' => 'Normal',
                        'status_finishing' => 'Standar',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    continue;
                }

                $bahan = DB::table('bahan_baku')->where('id_bahan', $item['id_bahan'])->lockForUpdate()->first();
                if (!$bahan) {
                    throw new \Exception('Bahan baku tidak ditemukan.');
                }

                // HPP baris ini = qty x harga_rata_rata bahan (biaya bahan
                // sesungguhnya, BUKAN harga jual manual di atas) - konsisten
                // dengan hpp_snapshot di jalur Input Order, supaya Laporan
                // HPP & Margin ikut menghitung margin penjualan barang juga.
                $hppBaris = $qty * (float) ($bahan->harga_rata_rata ?? 0);

                DB::table('transaksi_detail')->insert([
                    'id_transaksi' => $idTransaksi,
                    'id_bahan' => $bahan->id_bahan,
                    'nama_file' => $bahan->nama_bahan,
                    'p_ukuran' => 0,
                    'l_ukuran' => 0,
                    'qty' => $qty,
                    'biaya_cetak' => $subtotal,
                    'biaya_desain' => 0,
                    'biaya_finishing' => 0,
                    'subtotal' => $subtotal,
                    'harga_jual_snapshot' => $subtotal,
                    'hpp_snapshot' => $hppBaris,
                    // Penjualan barang tidak lewat proses produksi/SPK sama
                    // sekali - langsung "Selesai" seperti baris Jasa, supaya
                    // tidak nyangkut di antrean SPK/Produksi.
                    'status_spk' => 'Selesai',
                    'status_urgensi' => 'Normal',
                    'status_finishing' => 'Standar',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Stok WAJIB berkurang (bahan sungguhan keluar dari gudang,
                // beda dari pemakaian produksi yang cuma "warning, tidak
                // memblokir" - di sini kasir memang sedang menyerahkan
                // barang fisik ke pembeli saat itu juga). Boleh sampai
                // negatif (jaring pengaman terakhir, bukan pemblokir keras)
                // supaya kasir yang sudah kadung menyerahkan barang tetap
                // bisa mencatat transaksinya - selisihnya akan kelihatan
                // jelas di Laporan Stok Bahan Baku untuk ditindaklanjuti.
                $stokSebelum = (float) $bahan->stok;
                $stokSesudah = $stokSebelum - $qty;

                DB::table('bahan_baku')->where('id_bahan', $bahan->id_bahan)->update(['stok' => $stokSesudah]);

                DB::table('kartu_stok')->insert([
                    'id_bahan' => $bahan->id_bahan,
                    'tanggal' => now(),
                    'tipe' => 'OUT',
                    'qty' => $qty,
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $stokSesudah,
                    'referensi' => $no_nota,
                    'keterangan' => 'Penjualan barang langsung - ' . $no_nota,
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('transaksi')->where('id_transaksi', $idTransaksi)->update(['total_bayar' => $grandTotal]);

            // Jurnal penjualan, pola sama dengan Input Pesanan (PenjualanController::store): barang (bahan + custom)
            // lewat postPenjualan beserta HPP bahan, jasa lewat postPenjualanJasa. Sebelumnya menu ini tidak menjurnal
            // sama sekali: piutang & pendapatan tidak tercatat, stok keluar tanpa HPP, dan pembayarannya membuat piutang
            // minus. Di dalam transaksi yang sama, jadi gagal jurnal = seluruh nota batal.
            $ringkas = DB::table('transaksi_detail')->where('id_transaksi', $idTransaksi)
                ->selectRaw('COALESCE(SUM(CASE WHEN id_jasa IS NULL THEN subtotal ELSE 0 END), 0) barang,
                             COALESCE(SUM(CASE WHEN id_jasa IS NOT NULL THEN subtotal ELSE 0 END), 0) jasa,
                             COALESCE(SUM(CASE WHEN id_bahan IS NOT NULL THEN hpp_snapshot ELSE 0 END), 0) hpp')
                ->first();
            if ((float) $ringkas->barang > 0) {
                AccountingService::postPenjualan($no_nota, round((float) $ringkas->barang, 2), round((float) $ringkas->hpp, 2));
            }
            if ((float) $ringkas->jasa > 0) {
                AccountingService::postPenjualanJasa($no_nota, round((float) $ringkas->jasa, 2));
            }

            DB::commit();

            return redirect()->route('kasir.bayarForm', $idTransaksi)->with('success', "Penjualan barang {$no_nota} berhasil dibuat. Lanjutkan ke pembayaran.");
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', \App\Support\PesanError::untuk($e, 'Gagal menyimpan'))->withInput();
        }
    }

    /**
     * Qty jasa menurut tipe satuannya di master: Luas = panjang x lebar x jumlah, Panjang = panjang x jumlah,
     * selain itu qty biasa (null). Tipe satuan diambil dari database, bukan dari form.
     *
     * @return array{qty: float, p: float, l: float}|null
     */
    private static function ukuranJasa(int $idJasa, array $item): ?array
    {
        $tipe = DB::table('jasa')->leftJoin('satuan', 'satuan.id_satuan', '=', 'jasa.id_satuan')
            ->where('jasa.id_jasa', $idJasa)->value('satuan.tipe_satuan');
        $p = (float) ($item['p'] ?? 0);
        $l = (float) ($item['l'] ?? 0);
        $jumlah = (float) ($item['jumlah'] ?? 1) ?: 1;

        if ($tipe === 'Luas') {
            if ($p <= 0 || $l <= 0) {
                throw new \RuntimeException('Jasa bersatuan luas perlu panjang dan lebar.');
            }

            return ['qty' => round($p * $l * $jumlah, 4), 'p' => $p, 'l' => $l];
        }
        if ($tipe === 'Panjang') {
            if ($p <= 0) {
                throw new \RuntimeException('Jasa bersatuan panjang perlu ukuran panjang.');
            }

            return ['qty' => round($p * $jumlah, 4), 'p' => $p, 'l' => 0];
        }

        return null;
    }
}
