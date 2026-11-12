<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Services\AuditLogger;

/**
 * Satu-satunya jalur penulisan ke jurnal_umum (double-entry ledger).
 * postJournal() adalah primitif dasarnya - setiap baris debet/kredit yang
 * masuk WAJIB balance (total debet == total kredit) atau dilempar exception,
 * supaya tidak mungkin ada entri jurnal yang timpang tersimpan. Method
 * lainnya (postPenjualan, dst) cuma menyusun $lines lalu memanggil
 * postJournal() - tidak ada logika akuntansi yang menulis ke tabel manapun
 * di luar method ini.
 *
 * Dipanggil dari DALAM DB::transaction() yang sudah ada di controller
 * masing-masing (PenjualanController::store, KasirController::prosesBayar,
 * PembelianBahanController::store/pelunasan). postJournal() SENDIRI juga
 * membungkus loop insert baris debet/kreditnya dengan DB::transaction()
 * (pakai SAVEPOINT kalau dipanggil dari dalam transaction controller yang
 * sudah aktif - tidak mengubah perilaku atomicity gabungan dengan data
 * pemicunya, savepoint tetap ikut roll back kalau transaction luar gagal)
 * supaya jurnal tetap balance (tidak ada baris debet tanpa pasangan
 * kreditnya) bahkan kalau suatu saat method ini dipanggil tanpa transaction
 * pembungkus dari luar.
 */
class AccountingService
{
    private const AKUN_KAS = '1000';
    /** Kas Kecil = uang di LUAR laci kasir; dibukukan terpisah dari Kas (1000) milik laci. */
    private const AKUN_KAS_KECIL = '1005';
    private const AKUN_BANK = '1010';
    private const AKUN_PIUTANG_USAHA = '1100';
    private const AKUN_PERSEDIAAN_BAHAN = '1200';
    private const AKUN_HUTANG_USAHA = '2000';
    private const AKUN_HUTANG_DEPOSIT = '2100';
    private const AKUN_MODAL = '3000';
    // Pembelian bahan & pelunasan hutangnya dibayar pemilik di luar semua kas: dicatat sebagai dana pemilik.
    private const AKUN_DANA_PEMILIK_PEMBELIAN = '3100';
    private const AKUN_PENDAPATAN_PENJUALAN = '4000';
    private const AKUN_PENDAPATAN_JASA = '4100';
    private const AKUN_PENDAPATAN_LAIN_LAIN = '4200';
    private const AKUN_HPP = '5000';
    private const AKUN_BIAYA_LAIN_LAIN = '5100';

    /**
     * @param array<int, array{kode_akun: string, debet?: float, kredit?: float}> $lines
     */
    public static function postJournal(string $tglJurnal, string $noReferensi, string $keterangan, array $lines): void
    {
        if (count($lines) < 2) {
            throw new \InvalidArgumentException('Jurnal minimal butuh 2 baris (debet & kredit).');
        }

        $totalDebet = 0.0;
        $totalKredit = 0.0;

        foreach ($lines as $line) {
            $totalDebet += (float) ($line['debet'] ?? 0);
            $totalKredit += (float) ($line['kredit'] ?? 0);
        }

        // Toleransi pembulatan sangat kecil (rounding decimal(15,2)), bukan
        // celah untuk entri yang benar-benar timpang.
        if (abs($totalDebet - $totalKredit) > 0.01) {
            throw new \RuntimeException(
                "Jurnal tidak balance untuk referensi {$noReferensi}: debet {$totalDebet} != kredit {$totalKredit}"
            );
        }

        if ($totalDebet <= 0) {
            // Tidak ada nilai ekonomis untuk dicatat (mis. transaksi Rp 0) -
            // bukan error, cukup dilewati saja.
            return;
        }

        // Accounting Period (roadmap LEVEL 1) - postJournal() adalah
        // satu-satunya jalur tulis jurnal_umum, jadi ini satu-satunya titik
        // yang perlu menolak posting ke periode yang sudah ditutup.
        if (\App\Services\AccountingPeriodService::isClosed($tglJurnal)) {
            throw new \App\Exceptions\PeriodeAkuntansiTertutupException(
                "Periode akuntansi untuk tanggal {$tglJurnal} sudah ditutup (Closed) - tidak bisa memposting jurnal baru ke periode ini."
            );
        }

        $now = now();
        $baris = [];

        DB::transaction(function () use ($lines, $tglJurnal, $noReferensi, $keterangan, $now, &$baris) {
            // CATATAN PENTING - jangan tambahkan penolak "referensi sudah ada"
            // di sini.
            //
            // Descobaan pertama untuk menutup jurnal ganda memang ditambahkan
            // di titik ini, dan langsung merusak dua alur keuangan yang SAH:
            //
            //   1. Koreksi order jersey lewat selisih
            //      (JerseyAkuntansiTest::test_total_order_diakui_sebagai_pendapatan_jersey_dan_dikoreksi_lewat_selisih)
            //   2. Menghapus diskon lagi agar piutang kembali seperti semula
            //      (KasirUbahHargaJurnalTest::test_diskon_dihapus_lagi_mengembalikan_piutang_semula)
            //
            // Keduanya mem-posting jurnal dengan no_referensi dan keterangan
            // yang SAMA persis, bukan karena bug: di akuntansi, menyatakan
            // koreksi memakai referensi yang sama adalah cara yang benar untuk
            // mencatat koreksi. Menolaknya berarti operator tidak bisa
            // mengoreksi nominal yang sudah pernah tercatat.
            //
            // Jadi duplicate journal TIDAK dicegah di level service ini.
            // Pagar yang benar untuk double-submit ada di lapisan request:
            // SubmitSekaliJalan (satu submit_id = satu proses) plus
            // lockForUpdate pada baris yang diproses. Itu yang mencegah dua
            // request user menghasilkan satu jurnal ganda.
            //
            // Yang tetap dijaga di sini adalah integritas yang tidak bisa
            // ditawar: minimal 2 baris, debet = kredit, dan DB::transaction
            // supaya tidak ada jurnal separuh.
            foreach ($lines as $line) {
                $debet = (float) ($line['debet'] ?? 0);
                $kredit = (float) ($line['kredit'] ?? 0);

                if ($debet <= 0 && $kredit <= 0) {
                    continue;
                }

                DB::table('jurnal_umum')->insert([
                    'tgl_jurnal'    => $tglJurnal,
                    'no_referensi'  => $noReferensi,
                    'keterangan'    => $keterangan,
                    'kode_akun'     => $line['kode_akun'],
                    'debet'         => $debet,
                    'kredit'        => $kredit,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);

                $baris[] = ['kode_akun' => $line['kode_akun'], 'debet' => $debet, 'kredit' => $kredit];
            }
        });

        // Satu titik pencatatan Audit Trail untuk SEMUA posting akuntansi di
        // seluruh aplikasi (postJournal() adalah satu-satunya jalur tulis
        // jurnal_umum - lihat docblock class ini) - lebih efisien &
        // menjamin cakupan penuh dibanding memanggil AuditLogger di tiap
        // controller yang memicu postingan (PenjualanController,
        // KasirController, PembelianBahanController, dst).
        if (!empty($baris)) {
            AuditLogger::log('created', 'jurnal_umum', null, null, ['no_referensi' => $noReferensi, 'lines' => $baris], $keterangan);
        }
    }

    /**
     * Order penjualan dibuat (PenjualanController::store). Dua entri jurnal
     * terpisah tapi masing-masing balance sendiri:
     *  1. Pengakuan pendapatan: Dr Piutang Usaha / Cr Pendapatan Penjualan
     *  2. Pengakuan HPP (kalau ada biaya material yang bisa dihitung dari
     *     BOM x harga_rata_rata bahan): Dr HPP / Cr Persediaan Bahan Baku
     *
     * $totalHppMaterial sengaja HANYA komponen bahan baku (bukan
     * biaya_finishing) - lihat catatan di transaksi_detail migration:
     * biaya_finishing tidak punya dasar akun hutang/persediaan yang nyata
     * di skema saat ini, jadi tidak dipaksakan masuk jurnal supaya tidak
     * ada akun fiktif yang tidak pernah bisa dilunasi.
     */
    public static function postPenjualan(string $noNota, float $grandTotal, float $totalHppMaterial): void
    {
        $tgl = now()->toDateString();

        self::postJournal($tgl, $noNota, "Penjualan {$noNota}", [
            ['kode_akun' => self::AKUN_PIUTANG_USAHA, 'debet' => $grandTotal],
            ['kode_akun' => self::AKUN_PENDAPATAN_PENJUALAN, 'kredit' => $grandTotal],
        ]);

        if ($totalHppMaterial > 0) {
            self::postJournal($tgl, $noNota, "HPP bahan baku {$noNota}", [
                ['kode_akun' => self::AKUN_HPP, 'debet' => $totalHppMaterial],
                ['kode_akun' => self::AKUN_PERSEDIAAN_BAHAN, 'kredit' => $totalHppMaterial],
            ]);
        }
    }

    /**
     * Pembayaran diterima dari konsumen (KasirController::prosesBayar).
     * Mengurangi Piutang Usaha yang dibuat di postPenjualan().
     *
     * P0-3: no_referensi jurnal pembayaran DIPISAH dari nota (suffix
     * "-BYR-<urut>"). Sebelumnya referensinya = nomor nota saja, sama
     * dengan jurnal pendapatan/HPP nota - akibatnya reversePenjualan()
     * (yang membalik SEMUA baris per no_referensi saat koreksi nota)
     * ikut membalik jurnal pembayaran yang sudah menandai uang benar-benar
     * diterima, padahal pembayaran_riwayat & status nota TIDAK ikut
     * dibatalkan = uang "hilang" dari buku besar. Dengan referensi
     * terpisah, reverse nota hanya menyentuh jurnal pendapatan/HPP-nya.
     */
    public static function postPembayaran(string $noNota, float $nominal, string $metode): void
    {
        if ($nominal <= 0) {
            return;
        }

        $akunKasMasuk = $metode === 'Tunai' ? self::AKUN_KAS : self::AKUN_BANK;

        self::postJournal(now()->toDateString(), self::referensiPembayaran($noNota), "Pembayaran {$metode} {$noNota}", [
            ['kode_akun' => $akunKasMasuk, 'debet' => $nominal],
            ['kode_akun' => self::AKUN_PIUTANG_USAHA, 'kredit' => $nominal],
        ]);
    }

    /** Referensi jurnal pembayaran untuk satu nota: INV-...-001-BYR-1 (jangan pakai nomor nota polos - lihat postPembayaran). */
    public static function referensiPembayaran(string $noNota): string
    {
        $urut = (int) (DB::table('jurnal_umum')
            ->where('no_referensi', 'like', $noNota . '-BYR-%')
            ->count()) + 1;

        return $noNota . '-BYR-' . $urut;
    }

    /**
     * "Kas Tidak Terduga" (KasirController::storeKasManual) - mutasi kas
     * tunai yang TIDAK terikat nota penjualan (mis. pengeluaran operasional
     * dadakan, kas masuk di luar penjualan). $status: 'Masuk' -> Dr Kas /
     * Cr Pendapatan Lain-lain, 'Keluar' -> Dr Biaya Lain-lain / Cr Kas.
     */
    public static function postKasManual(string $noReferensi, float $nominal, string $status): void
    {
        if ($nominal <= 0) {
            return;
        }

        $lines = $status === 'Masuk'
            ? [
                ['kode_akun' => self::AKUN_KAS, 'debet' => $nominal],
                ['kode_akun' => self::AKUN_PENDAPATAN_LAIN_LAIN, 'kredit' => $nominal],
            ]
            : [
                ['kode_akun' => self::AKUN_BIAYA_LAIN_LAIN, 'debet' => $nominal],
                ['kode_akun' => self::AKUN_KAS, 'kredit' => $nominal],
            ];

        self::postJournal(now()->toDateString(), $noReferensi, "Kas Tidak Terduga ({$status}) {$noReferensi}", $lines);
    }

    /**
     * Faktur pembelian bahan baku dicatat (PembelianBahanController::store).
     * Cash -> Cr Dana Pemilik untuk Pembelian (dibayar pemilik di luar kas). Tempo -> Cr Hutang Usaha.
     */
    public static function postPembelianBahan(string $noFaktur, float $grandTotal, string $tipePembelian): void
    {
        $akunLawan = $tipePembelian === 'Cash' ? self::AKUN_DANA_PEMILIK_PEMBELIAN : self::AKUN_HUTANG_USAHA;

        self::postJournal(now()->toDateString(), $noFaktur, "Pembelian bahan baku {$noFaktur}", [
            ['kode_akun' => self::AKUN_PERSEDIAAN_BAHAN, 'debet' => $grandTotal],
            ['kode_akun' => $akunLawan, 'kredit' => $grandTotal],
        ]);
    }

    /**
     * Pembayaran (cicilan/pelunasan) hutang pembelian lewat
     * PembayaranHutangService. Referensi jurnal dibuat unik per pembayaran
     * (mis. "FAK-001/BYR-7") supaya bisa di-void sendiri tanpa ikut
     * membalik jurnal faktur atau pembayaran lain. Metode (Transfer/Tunai) hanya keterangan cara pemilik membayar:
     * uangnya di luar semua kas usaha, jadi lawannya selalu Dana Pemilik untuk Pembelian.
     */
    public static function postPembayaranHutang(string $noReferensi, string $noFaktur, float $nominal, string $metode): void
    {
        $akunKeluar = self::AKUN_DANA_PEMILIK_PEMBELIAN;

        self::postJournal(now()->toDateString(), $noReferensi, "Pembayaran hutang {$noFaktur} ({$metode})", [
            ['kode_akun' => self::AKUN_HUTANG_USAHA, 'debet' => $nominal],
            ['kode_akun' => $akunKeluar, 'kredit' => $nominal],
        ]);
    }

    /**
     * Beban rework & susut bahan (QcService, WasteService): material yang
     * keluar dari gudang tanpa menjadi barang terjual. Dr Beban Rework &
     * Susut Bahan (5200) / Cr Persediaan Bahan Baku.
     */
    /** HPP kain order jersey (Dr HPP / Cr Persediaan); nilai negatif = pembalikan (stok dikembalikan). */
    public static function postHppJersey(string $noOrder, float $biaya): void
    {
        if ($biaya == 0.0) {
            return;
        }
        $abs = abs($biaya);
        $lines = $biaya > 0
            ? [['kode_akun' => self::AKUN_HPP, 'debet' => $abs], ['kode_akun' => self::AKUN_PERSEDIAAN_BAHAN, 'kredit' => $abs]]
            : [['kode_akun' => self::AKUN_PERSEDIAAN_BAHAN, 'debet' => $abs], ['kode_akun' => self::AKUN_HPP, 'kredit' => $abs]];

        self::postJournal(now()->toDateString(), $noOrder, ($biaya > 0 ? 'HPP kain jersey ' : 'Pembalikan HPP kain jersey ') . $noOrder, $lines);
    }

    /**
     * Selisih stok hasil stock opname / penyesuaian manual: $nilai positif = stok bertambah (Dr Persediaan /
     * Cr Selisih Persediaan), negatif = berkurang (Dr Selisih Persediaan / Cr Persediaan). Nilai 0 tidak dijurnal
     * (bahan tanpa harga rata-rata).
     */
    public static function postSelisihPersediaan(string $noReferensi, string $keterangan, float $nilai): void
    {
        $abs = round(abs($nilai), 2);
        if ($abs <= 0) {
            return;
        }

        self::postJournal(now()->toDateString(), $noReferensi, $keterangan, $nilai > 0
            ? [['kode_akun' => self::AKUN_PERSEDIAAN_BAHAN, 'debet' => $abs], ['kode_akun' => '5230', 'kredit' => $abs]]
            : [['kode_akun' => '5230', 'debet' => $abs], ['kode_akun' => self::AKUN_PERSEDIAAN_BAHAN, 'kredit' => $abs]]);
    }

    /**
     * Total nota berubah setelah dijurnal (diskon/potongan/biaya item diubah di layar pembayaran kasir): pendapatan
     * nota di jurnal disamakan dengan total baru, selisihnya Dr/Cr Piutang lawan Pendapatan dengan porsi akun
     * pendapatan seperti jurnal yang ada. Rekonsiliasi, bukan tambahan: menyimpan ulang total yang sama tidak menjurnal
     * apa pun. Referensi nomor nota, jadi pembatalan nota tetap membalik semuanya. Nota tanpa jurnal penjualan (data
     * lama) dilewati.
     */
    public static function sesuaikanTotalNota(string $noNota, float $totalBaru): void
    {
        // Pendapatan satu nota bisa tersebar di beberapa baris jurnal: nota
        // yang mendapat item susulan dijurnal dengan sufiks "-susulan" (lihat
        // PenjualanController::store). Kalau hanya baris dengan referensi
        // PERSIS nota yang dihitung, tambahan item itu seolah belum pernah
        // terjurnal - rekonsiliasi lalu menganggap total nota masih kurang
        // sebesar tambahan itu, lalu menjurnal Piutang Usahanya lebih besar
        // dari tagihan yang sebenarnya (akibatnya Piutang Usaha 1100 tidak
        // sinkron dengan sisa tagihan nota).
        //
        // Semua referensi milik nota ini ikut dihitung. Nomor nota berformat
        // tetap (ORG-YYYYMMDD-NNN), jadi tidak ada nota lain yang bisa
        // tertangkap pola "NOMOR-%" ini.
        $baris = DB::table('jurnal_umum')
            ->where(function ($q) use ($noNota) {
                $q->where('no_referensi', $noNota)
                    ->orWhere('no_referensi', 'like', $noNota . '-%');
            })
            ->whereIn('kode_akun', [self::AKUN_PENDAPATAN_PENJUALAN, self::AKUN_PENDAPATAN_JASA])
            ->selectRaw('kode_akun, SUM(kredit - debet) AS nilai')->groupBy('kode_akun')->pluck('nilai', 'kode_akun');
        if ($baris->isEmpty()) {
            return;
        }

        $kini = round((float) $baris->sum(), 2);
        $selisih = round($totalBaru - $kini, 2);
        if (abs($selisih) < 0.01) {
            return;
        }

        $porsiJasa = $kini > 0 ? (float) ($baris[self::AKUN_PENDAPATAN_JASA] ?? 0) / $kini : 0.0;
        $jasa = round($selisih * $porsiJasa, 2);
        $barang = round($selisih - $jasa, 2);

        $lines = [$selisih > 0
            ? ['kode_akun' => self::AKUN_PIUTANG_USAHA, 'debet' => $selisih]
            : ['kode_akun' => self::AKUN_PIUTANG_USAHA, 'kredit' => -$selisih]];
        foreach ([self::AKUN_PENDAPATAN_PENJUALAN => $barang, self::AKUN_PENDAPATAN_JASA => $jasa] as $akun => $nilai) {
            if (abs($nilai) >= 0.01) {
                $lines[] = $nilai > 0 ? ['kode_akun' => $akun, 'kredit' => $nilai] : ['kode_akun' => $akun, 'debet' => -$nilai];
            }
        }

        self::postJournal(now()->toDateString(), $noNota, "Penyesuaian total nota {$noNota} di kasir (diskon/potongan/harga)", $lines);
    }

    /**
     * Biaya yang dibayar langsung oleh pemilik (bukan dari laci atau Kas Kecil): Dr Beban / Cr 3200 Dana Pemilik
     * untuk Biaya Operasional. Beban tetap masuk Laba Rugi, tanpa ada kas yang berkurang.
     */
    public static function postBiayaDibayarPemilik(string $noReferensi, string $kodeAkunBeban, float $nominal): void
    {
        self::postJournal(now()->toDateString(), $noReferensi, "Biaya dibayar langsung pemilik {$noReferensi}", [
            ['kode_akun' => $kodeAkunBeban, 'debet' => $nominal],
            ['kode_akun' => '3200', 'kredit' => $nominal],
        ]);
    }

    /**
     * Nota dibatalkan dengan pengembalian bahan, tetapi ada item yang SPK-nya sudah dibatalkan lebih dulu TANPA
     * pengembalian (bahannya terpakai/rusak). reversePenjualan membalik seluruh HPP nota; HPP item itu dicatat lagi
     * (Dr HPP / Cr Persediaan) supaya Persediaan tetap sama dengan stok fisik.
     *
     * $keterangan opsional: dipakai alur EDIT SELISIH, di mana HPP naik
     * karena pemakaian bahan bertambah - bukan karena bahan tidak kembali.
     */
    public static function postHppBahanTerpakai(string $noNota, float $nilai, ?string $keterangan = null): void
    {
        $nilai = round($nilai, 2);
        if ($nilai <= 0) {
            return;
        }

        self::postJournal(now()->toDateString(), $noNota, $keterangan ?: "HPP bahan terpakai pada item batal {$noNota} (bahan tidak kembali)", [
            ['kode_akun' => self::AKUN_HPP, 'debet' => $nilai],
            ['kode_akun' => self::AKUN_PERSEDIAAN_BAHAN, 'kredit' => $nilai],
        ]);
    }

    /** Bahan item yang dibatalkan kembali ke stok: Dr Persediaan / Cr HPP senilai bahan yang kembali. */
    public static function postPembalikanHppItem(string $noNota, string $keterangan, float $nilai): void
    {
        $nilai = round($nilai, 2);
        if ($nilai <= 0) {
            return;
        }

        self::postJournal(now()->toDateString(), $noNota, $keterangan, [
            ['kode_akun' => self::AKUN_PERSEDIAAN_BAHAN, 'debet' => $nilai],
            ['kode_akun' => self::AKUN_HPP, 'kredit' => $nilai],
        ]);
    }

    /** Bahan terbuang (waste) atau dipakai cetak ulang (rework): Dr 5240 Beban Waste & Rework / Cr Persediaan. */
    public static function postBebanRework(string $noReferensi, string $keterangan, float $nominal): void
    {
        if ($nominal <= 0) {
            return;
        }

        self::postJournal(now()->toDateString(), $noReferensi, $keterangan, [
            ['kode_akun' => '5240', 'debet' => $nominal],
            ['kode_akun' => self::AKUN_PERSEDIAAN_BAHAN, 'kredit' => $nominal],
        ]);
    }

    /**
     * Pengakuan/koreksi pendapatan Order Jersey (JerseyAccountingService).
     * $selisih positif = pendapatan bertambah (Dr Piutang / Cr Pendapatan
     * Jersey), negatif = berkurang (dibalik). Pendapatan Jersey memakai
     * akun 4300 terpisah dari Pendapatan Penjualan.
     */
    public static function postPendapatanJersey(string $noOrder, float $selisih): void
    {
        $nilai = abs($selisih);

        if ($nilai < 0.01) {
            return;
        }

        $lines = $selisih > 0
            ? [['kode_akun' => self::AKUN_PIUTANG_USAHA, 'debet' => $nilai], ['kode_akun' => '4300', 'kredit' => $nilai]]
            : [['kode_akun' => '4300', 'debet' => $nilai], ['kode_akun' => self::AKUN_PIUTANG_USAHA, 'kredit' => $nilai]];

        self::postJournal(now()->toDateString(), $noOrder, ($selisih > 0 ? 'Pendapatan' : 'Koreksi pendapatan') . " order jersey {$noOrder}", $lines);
    }

    /** Pembayaran Order Jersey: Tunai -> Kas, metode lain (Transfer/QRIS/Lainnya) -> Bank. */
    public static function postPembayaranJersey(string $noReferensi, string $noOrder, float $nominal, string $metode, ?string $tanggal = null): void
    {
        // Pelunasan jersey tunai diterima di luar laci -> masuk Kas Kecil (1005), bukan Kas laci.
        $akunMasuk = $metode === 'Tunai' ? self::AKUN_KAS_KECIL : self::AKUN_BANK;

        // Tanggal jurnal = tanggal bayar (sama dengan baris buku Kas Kecil), bukan tanggal input.
        self::postJournal($tanggal ?: now()->toDateString(), $noReferensi, "Pembayaran order jersey {$noOrder} ({$metode})", [
            ['kode_akun' => $akunMasuk, 'debet' => $nominal],
            ['kode_akun' => self::AKUN_PIUTANG_USAHA, 'kredit' => $nominal],
        ]);
    }

    /**
     * Saldo awal persediaan saat bahan baku baru didaftarkan dengan stok awal
     * DAN harga beli (BahanBakuController::store): Dr Persediaan Bahan Baku /
     * Cr Modal. Barang sudah ada secara fisik tanpa dibeli lewat faktur, jadi
     * lawannya modal pemilik, sama seperti saldo awal di akuntansi:saldo-awal.
     */
    public static function postSaldoAwalPersediaan(string $noReferensi, string $keterangan, float $nominal): void
    {
        self::postJournal(now()->toDateString(), $noReferensi, $keterangan, [
            ['kode_akun' => self::AKUN_PERSEDIAAN_BAHAN, 'debet' => $nominal],
            ['kode_akun' => self::AKUN_MODAL, 'kredit' => $nominal],
        ]);
    }

    /**
     * Setoran deposit konsumen diterima (KonsumenDepositController::store).
     * Uang benar-benar masuk (Kas/Bank), tapi belum jadi pendapatan - masih
     * kewajiban (Hutang Deposit Konsumen) sampai dipakai bayar nota.
     */
    public static function postSetoranDeposit(string $noReferensi, float $nominal, string $metode): void
    {
        $akunSumber = $metode === 'Tunai' ? self::AKUN_KAS : self::AKUN_BANK;

        self::postJournal(now()->toDateString(), $noReferensi, "Setoran deposit {$noReferensi}", [
            ['kode_akun' => $akunSumber, 'debet' => $nominal],
            ['kode_akun' => self::AKUN_HUTANG_DEPOSIT, 'kredit' => $nominal],
        ]);
    }

    /** Deposit dikembalikan ke pelanggan sebagai uang: Dr Hutang Deposit / Cr Kas atau Bank. */
    public static function postPengembalianDeposit(string $noReferensi, float $nominal, string $metode): void
    {
        if ($nominal <= 0) {
            return;
        }

        $akunKeluar = $metode === 'Tunai' ? self::AKUN_KAS : self::AKUN_BANK;

        self::postJournal(now()->toDateString(), $noReferensi, "Pengembalian deposit {$noReferensi}", [
            ['kode_akun' => self::AKUN_HUTANG_DEPOSIT, 'debet' => $nominal],
            ['kode_akun' => $akunKeluar, 'kredit' => $nominal],
        ]);
    }

    /**
     * Deposit dipakai sebagai pembayaran nota (KasirController::prosesBayar,
     * metode 'Deposit'; juga auto-pakai di PenjualanController::store).
     * Tidak ada Kas/Bank baru yang masuk di sini - uangnya
     * sudah masuk saat setoran - jadi ini murni memindahkan saldo dari
     * kewajiban deposit ke pelunasan piutang.
     * P0-3: referensi terpisah dari nota (lihat postPembayaran).
     */
    public static function postPembayaranDeposit(string $noNota, float $nominal): void
    {
        self::postJournal(now()->toDateString(), self::referensiPembayaran($noNota), "Pembayaran via deposit {$noNota}", [
            ['kode_akun' => self::AKUN_HUTANG_DEPOSIT, 'debet' => $nominal],
            ['kode_akun' => self::AKUN_PIUTANG_USAHA, 'kredit' => $nominal],
        ]);
    }

    /**
     * Penjualan Jasa (dipisah dari postPenjualan karena Jasa tidak punya
     * BOM/HPP bahan baku - langsung Dr Piutang / Cr Pendapatan Jasa, tanpa
     * baris HPP kedua seperti penjualan produk fisik).
     */
    public static function postPenjualanJasa(string $noNota, float $grandTotal): void
    {
        self::postJournal(now()->toDateString(), $noNota, "Penjualan jasa {$noNota}", [
            ['kode_akun' => self::AKUN_PIUTANG_USAHA, 'debet' => $grandTotal],
            ['kode_akun' => self::AKUN_PENDAPATAN_JASA, 'kredit' => $grandTotal],
        ]);
    }

    /**
     * Pengeluaran Kas Kecil dicatat (KasKecilController::store()) - akun
     * lawan (Beban) ditentukan oleh kategori yang dipilih user
     * (kategori_kas_kecil.kode_akun), BUKAN dihardcode seperti
     * postKasManual() - satu akun Beban generik tidak cukup lagi sekarang
     * ada rincian kategori (ATK, listrik, marketing, dst). Selalu Cr Kas
     * (1000) - Kas Kecil memang representasi laci kas fisik kecil, tidak
     * ada pilihan Bank di form-nya.
     */
    public static function postKasKecil(string $noReferensi, string $kodeAkunBeban, float $nominal): void
    {
        if ($nominal <= 0) {
            return;
        }

        self::postJournal(now()->toDateString(), $noReferensi, "Pengeluaran Kas Kecil {$noReferensi}", [
            ['kode_akun' => $kodeAkunBeban, 'debet' => $nominal],
            ['kode_akun' => self::AKUN_KAS_KECIL, 'kredit' => $nominal],
        ]);
    }

    /** Pemasukan manual di Kas Kecil: Dr Kas / Cr akun pendapatan (atau modal) sesuai kategorinya. */
    public static function postKasKecilMasuk(string $noReferensi, string $kodeAkunLawan, float $nominal): void
    {
        if ($nominal <= 0) {
            return;
        }

        self::postJournal(now()->toDateString(), $noReferensi, "Pemasukan Kas Kecil {$noReferensi}", [
            ['kode_akun' => self::AKUN_KAS_KECIL, 'debet' => $nominal],
            ['kode_akun' => $kodeAkunLawan, 'kredit' => $nominal],
        ]);
    }

    /**
     * Versi generik dari reversePenjualan() (nama lama dipertahankan apa
     * adanya supaya alur Edit Nota yang sudah ada tidak perlu disentuh) -
     * membalik SEMUA baris jurnal untuk satu no_referensi apa pun
     * sumbernya, dipakai KasKecilController::destroy() saat sebuah catatan
     * Kas Kecil dihapus (jurnal lama tidak dihapus/diubah, tetap jadi jejak
     * audit - pembalikan selalu berupa entri baru yang saling meniadakan).
     */
    public static function reverseByReferensi(string $noReferensi): void
    {
        $baris = DB::table('jurnal_umum')->where('no_referensi', $noReferensi)->get();

        if ($baris->isEmpty()) {
            return;
        }

        $lines = $baris->map(fn ($b) => [
            'kode_akun' => $b->kode_akun,
            'debet' => (float) $b->kredit,
            'kredit' => (float) $b->debet,
        ])->all();

        self::postJournal(now()->toDateString(), $noReferensi, "Koreksi/pembalikan {$noReferensi}", $lines);
    }

    /**
     * Nota yang sudah dibayar lalu dibatalkan: pendapatan dibalik (reversePenjualan) tetapi jurnal
     * pembayarannya sengaja dibiarkan, sehingga Piutang nota itu bersaldo KREDIT sebesar uang yang
     * sudah diterima. Uang itu kini utang usaha kepada pelanggan: dipindahkan ke Hutang Deposit
     * (Dr Piutang / Cr Hutang Deposit) dan di sisi operasional menjadi saldo deposit pelanggan.
     */
    public static function postKreditNotaBatal(string $noNota, float $nominal): void
    {
        if ($nominal <= 0) {
            return;
        }

        self::postJournal(now()->toDateString(), $noNota . '-KRD', "Kredit pelanggan dari nota batal {$noNota}", [
            ['kode_akun' => self::AKUN_PIUTANG_USAHA, 'debet' => $nominal],
            ['kode_akun' => self::AKUN_HUTANG_DEPOSIT, 'kredit' => $nominal],
        ]);
    }

    /**
     * Membalik SEMUA baris jurnal untuk satu no_referensi (nota) dengan
     * entri kontra (debet<->kredit ditukar) - dipakai saat sebuah nota
     * dikoreksi lewat alur Edit Nota (PenjualanController::applyApprovedEdit)
     * maupun saat nota DIBATALKAN lewat Approval Workflow
     * (PenjualanController::eksekusiPembatalanNota).
     * SENGAJA tidak pernah menghapus/mengubah baris jurnal asli (itu jejak
     * audit permanen) - pembalikan akuntansi yang benar selalu berupa entri
     * baru yang saling meniadakan, persis pola ini.
     *
     * P0-3: hanya baris jurnal NON-pembayaran yang dibalik. Baris dengan
     * referensi "<nota>-BYR-*" (postPembayaran/postPembayaranDeposit) adalah
     * jurnal uang yang benar-benar diterima - membaliknya tanpa membatalkan
     * pembayaran_riwayat-nya akan membuat uang tercatat masuk tapi hilang
     * dari buku besar. Koreksi pembayaran memakai jalur void tersendiri.
     *
     * $termasukHpp = false: nota batal yang bahannya TIDAK dikembalikan ke stok (sudah terpakai/rusak). Pasangan
     * HPP/Persediaan dibiarkan: bahannya memang sudah keluar, jadi nilai Persediaan tetap sama dengan stok fisik.
     */
    public static function reversePenjualan(string $noNota, bool $termasukHpp = true): void
    {
        $baris = DB::table('jurnal_umum')
            ->where('no_referensi', $noNota)
            ->where('no_referensi', 'not like', $noNota . '-BYR-%')
            ->when(!$termasukHpp, fn ($q) => $q->whereNotIn('kode_akun', [self::AKUN_HPP, self::AKUN_PERSEDIAAN_BAHAN]))
            ->get();

        if ($baris->isEmpty()) {
            return;
        }

        // Semua baris asli (bisa berasal dari beberapa panggilan postJournal
        // terpisah, mis. postPenjualan menulis 2 baris Piutang/Pendapatan +
        // 2 baris HPP/Persediaan kalau ada HPP) digabung jadi SATU baris
        // pembalikan sekaligus - tiap baris cuma diisi salah satu dari
        // debet/kredit (tidak pernah dua-duanya), jadi menukar posisi nilai
        // per baris otomatis tetap balance selama set aslinya balance.
        $lines = $baris->map(fn ($b) => [
            'kode_akun' => $b->kode_akun,
            'debet' => (float) $b->kredit,
            'kredit' => (float) $b->debet,
        ])->all();

        self::postJournal(now()->toDateString(), $noNota, "Koreksi/pembalikan nota {$noNota}", $lines);
    }
}
