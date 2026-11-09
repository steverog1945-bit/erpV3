<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mengembalikan stok bahan baku yang sudah dipotong BOM saat sebuah item
 * transaksi_detail dibatalkan - dipakai baik dari pembatalan SPK per-item
 * (ProduksiController::cancel) maupun pembatalan nota penuh
 * (PenjualanController::batalkanNota). Diekstrak jadi satu tempat supaya
 * rumus pengembalian stok tidak dobel-ditulis di dua controller dan
 * berisiko diperbaiki di satu tempat tapi lupa di tempat lain.
 *
 * Hardening P0-1: SEBELUMNYA method ini SELALU menghitung ulang qty yang
 * harus dikembalikan dari BomCalculationService::komponen() - yang membaca
 * bom_detail/pemakaian_tinta LIVE (tabel yang sama yang di-delete-lalu-
 * insert-ulang tiap kali BOM produk diedit, lihat ProdukController::update).
 * Kalau BOM produk berubah di antara order dibuat dan item itu dibatalkan,
 * reversal akan memakai formula BARU (bahan berbeda dan/atau qty berbeda),
 * BUKAN yang benar-benar dipotong dulu.
 *
 * Sekarang: kalau baris item ini punya snapshot di
 * transaksi_detail_bahan_pakai (dicatat persis saat qty dipotong, lihat
 * PenjualanController::store), snapshot itu yang dipakai - imun terhadap
 * BOM yang berubah belakangan.
 *
 * Hardening S1 (audit stok): snapshot berlaku CONSUME-ONCE - setelah
 * berhasil dipakai reversal, baris snapshot dihapus (dalam transaksi yang
 * sama) sehingga satu item tidak bisa direversal dua kali (batal SPK ->
 * antrekan ulang -> batal lagi tidak mengembalikan stok dobel). Snapshot
 * kosong pada transaksi era-snapshot (>= SNAPSHOT_MULAI, 2026-09-27 21:26:42) berarti "tidak pernah
 * memotong stok" atau "sudah pernah direversal" - keduanya TIDAK
 * mengembalikan apa pun (tidak ada recompute BOM live). Fallback recompute
 * hanya untuk transaksi ERA LAMA (pra snapshot) yang memang tidak punya
 * data lain, tetap dengan guard anti-dobel (cek kartu_stok IN
 * BATAL-SPK-{id_detail}). Setiap pemanggilan tercatat di audit_logs
 * (event stok_reversal, mode snapshot/fallback/dilewati+alasan).
 */
class StokReversalService
{
    /**
     * Saat snapshot transaksi_detail_bahan_pakai pertama kali tercatat di
     * produksi (MIN(created_at) tabel itu, dikonfirmasi pemilik 2026-09-30).
     * Audit H-2: sebelumnya di-hard-code 2026-10-14 - 17 hari lebih lambat
     * dari kenyataan, sehingga nota era snapshot tanpa baris snapshot salah
     * jatuh ke recompute BOM live (stok fiktif). JANGAN dimundurkan/dimajukan
     * tanpa memeriksa data: nilainya fakta historis, bukan pengaturan.
     */
    public const SNAPSHOT_MULAI = '2026-09-27 21:26:42';

    /**
     * @param  object  $detail  Baris transaksi_detail (butuh id_detail, id_produk, p_ukuran, l_ukuran, qty)
     * @param  string  $referensi  Kode referensi untuk kartu_stok, mis. "BATAL-SPK-12" atau "BATAL-NOTA-5"
     * @param  string  $keterangan  Teks keterangan untuk kartu_stok
     * @return array<int, string>  Ringkasan "Nama Bahan +qty" per bahan yang dikembalikan
     */
    public static function kembalikan(object $detail, string $referensi, string $keterangan): array
    {
        if (!$detail->id_produk) {
            return !empty($detail->id_bahan) ? self::kembalikanBahanLangsung($detail, $referensi, $keterangan) : [];
        }

        $produk = DB::table('produk')->where('id_produk', $detail->id_produk)->first();
        if (!$produk) {
            return [];
        }

        // Dibungkus DB::transaction() sendiri (bukan cuma mengandalkan
        // transaction milik pemanggil) - dua alasan: (1) lockForUpdate() di
        // bawah TIDAK berarti apa-apa di MySQL kalau dipanggil di luar
        // transaksi aktif (row lock cuma berlaku selama transaksi
        // berjalan), dan (2) method ini loop per-bahan BOM melakukan UPDATE
        // bahan_baku + INSERT kartu_stok berpasangan - tanpa transaction,
        // pemanggil baru di masa depan yang lupa membungkusnya sendiri bisa
        // meninggalkan stok berubah tanpa jejak kartu_stok kalau gagal di
        // tengah loop (multi-bahan). DB::transaction() Laravel aman
        // di-nest (pakai SAVEPOINT) kalau pemanggil sudah py transaction
        // sendiri, jadi ini tidak mengubah perilaku 3 pemanggil yang ada
        // sekarang.
        // S1 (audit stok): id_detail unik per baris item - dipakai sebagai
        // penanda "sudah pernah direversal" untuk jalur fallback di bawah
        // (batal SPK per-item menulis referensi BATAL-SPK-{id_detail}).
        $idDetail = (int) ($detail->id_detail ?? 0);

        return DB::transaction(function () use ($detail, $idDetail, $referensi, $keterangan) {
            // S1 consume-once: baris snapshot dibaca DENGAN lock - dua
            // reversal bersamaan untuk item yang sama tidak bisa sama-sama
            // memakai snapshot yang sama.
            $snapshot = DB::table('transaksi_detail_bahan_pakai')
                ->where('id_detail', $idDetail)
                ->lockForUpdate()
                ->get();

            if ($snapshot->isNotEmpty()) {
                $ringkasan = self::kembalikanDariSnapshot($snapshot, $referensi, $keterangan);

                // S1 consume-once: snapshot yang sudah berhasil dipakai
                // reversal DIHAPUS di transaksi yang sama - gagal di tengah
                // jalan berarti rollback mengembalikan stok DAN snapshot
                // bersamaan. Tanpa ini, batal SPK -> antrekan ulang -> batal
                // lagi akan mengembalikan stok DOBEL (snapshot tidak pernah
                // "habis" dipakai). Re-antre TIDAK memotong stok ulang (stok
                // cuma dipotong saat order dibuat), jadi menolak reversal
                // kedua untuk item yang sama adalah perilaku yang benar.
                if ($ringkasan !== []) {
                    DB::table('transaksi_detail_bahan_pakai')
                        ->where('id_detail', $idDetail)
                        ->delete();
                }

                self::logReversal($idDetail, 'snapshot', $ringkasan, $referensi, $keterangan);

                return $ringkasan;
            }

            // S1: snapshot kosong - maknanya bergantung ERA transaksi.
            //
            // Era snapshot (order dibuat >= SNAPSHOT_MULAI): snapshot & baris OUT
            // kartu_stok selalu ditulis berpasangan dalam SATU transaksi saat
            // order dibuat. Snapshot kosong berarti SALAH SATU: (a) item ini
            // memang tidak pernah memotong stok (produk tanpa komponen BOM
            // berqty > 0 - nota juga tidak punya baris OUT), atau (b) snapshot
            // sudah pernah dipakai reversal lalu dihapus (consume-once di
            // atas; nota ini PASTI punya baris OUT). Kedua kasus: JANGAN
            // recompute dari BOM live - (a) akan mengembalikan stok yang
            // TIDAK PERNAH dipotong kalau BOM diedit belakangan (stok fiktif),
            // (b) pasti dobel-reversal.
            //
            // Era lama (pra SNAPSHOT_MULAI): pencatatan OUT saat order juga belum
            // ada - nota lama TIDAK punya baris OUT padahal stoknya benar
            // terpotong, dan tidak ada snapshot. Fallback recompute BOM adalah
            // satu-satunya cara; tetap dilindungi guard BATAL-SPK agar tidak
            // dobel.
            $transaksiRef = DB::table('transaksi')
                ->where('id_transaksi', $detail->id_transaksi ?? 0)
                ->first(['nomor_nota', 'tgl_order', 'created_at']);
            $noNota = $transaksiRef->nomor_nota ?? null;

            if (!$noNota) {
                self::logReversal($idDetail, 'dilewati-tanpa-out', [], $referensi, $keterangan);

                return [];
            }

            // Era snapshot HANYA bila created_at (jam tepat, tak boleh lebih
            // awal dari SNAPSHOT_MULAI) DAN tanggal order sama-sama sesudah
            // batas. Data impor lama yang created_at-nya baru tapi tgl_order
            // lampau tetap diperlakukan sebagai era lama (ada stok terpotong
            // tanpa snapshot -> fallback yang benar).
            $mulai = \Carbon\Carbon::parse(self::SNAPSHOT_MULAI);
            $tglOrder = $transaksiRef->tgl_order ?? null;
            $dibuat = $transaksiRef->created_at ?? null;
            $eraSnapshot = $dibuat !== null
                ? \Carbon\Carbon::parse($dibuat)->gte($mulai)
                    && ($tglOrder === null || \Carbon\Carbon::parse($tglOrder)->startOfDay()->gte($mulai->copy()->startOfDay()))
                : ($tglOrder !== null && \Carbon\Carbon::parse($tglOrder)->startOfDay()->gte($mulai->copy()->startOfDay()));
            $adaOut = DB::table('kartu_stok')->where('tipe', 'OUT')->where('referensi', $noNota)->exists();

            if ($eraSnapshot) {
                self::logReversal(
                    $idDetail,
                    $adaOut ? 'dilewati-sudah-direversal' : 'dilewati-tanpa-out',
                    [],
                    $referensi,
                    $keterangan
                );

                return [];
            }

            // Era lama: cegah double-reversal - kalau item ini pernah
            // direversal per-SPK sebelumnya (ada baris IN BATAL-SPK-{id_detail}
            // di kartu_stok), mengembalikan stok SEKALI LAGI pasti dobel.
            if ($idDetail > 0 && DB::table('kartu_stok')
                ->where('tipe', 'IN')
                ->where('referensi', 'BATAL-SPK-' . $idDetail)
                ->exists()) {
                self::logReversal($idDetail, 'dilewati-sudah-direversal', [], $referensi, $keterangan);

                return [];
            }

            // Fallback KHUSUS transaksi era lama pra-snapshot - PERSIS rumus
            // lama, kini dengan guard double-reversal di atas.
            $ringkasan = self::kembalikanRecomputeBomLama($detail, $noNota, $referensi, $keterangan);

            self::logReversal($idDetail, 'fallback-bom-lama', $ringkasan, $referensi, $keterangan);

            return $ringkasan;
        });
    }

    /**
     * S1: satu entri audit per pemanggilan reversal - ditulis DI DALAM
     * transaksi pemanggil (rollback membatalkan log-nya juga, jadi Log
     * Aktivitas tidak pernah mencatat reversal yang tidak jadi terjadi).
     * Mode: snapshot (jalur akurat), fallback-bom-lama (transaksi pra-
     * snapshot), dilewati-tanpa-out / dilewati-sudah-direversal (tidak ada
     * yang dikembalikan beserta alasannya).
     */
    private static function logReversal(int $idDetail, string $mode, array $ringkasan, string $referensi, string $keterangan): void
    {
        if ($idDetail <= 0) {
            return;
        }

        AuditLogger::log(
            'stok_reversal',
            'transaksi_detail',
            $idDetail,
            null,
            [
                'mode'      => $mode,
                'referensi' => $referensi,
                'ringkasan' => $ringkasan,
            ],
            $keterangan
        );
    }

    /**
     * Item Penjualan dan Jasa (bahan dijual langsung, kolom id_bahan): stok dipotong persis qty item saat nota
     * dibuat (kartu OUT), jadi yang dikembalikan juga persis qty itu. Sekali per item: jejak audit mode
     * bahan-langsung untuk id_detail yang sama berarti stoknya sudah pernah kembali.
     */
    private static function kembalikanBahanLangsung(object $detail, string $referensi, string $keterangan): array
    {
        $idDetail = (int) ($detail->id_detail ?? 0);

        return DB::transaction(function () use ($detail, $idDetail, $referensi, $keterangan) {
            DB::table('transaksi_detail')->where('id_detail', $idDetail)->lockForUpdate()->first();
            $sudah = \App\Models\AuditLog::where('event', 'stok_reversal')
                ->where('auditable_type', 'transaksi_detail')
                ->where('auditable_id', $idDetail)
                ->where('new_values', 'like', '%"mode":"bahan-langsung"%')
                ->exists();
            // Penanda utama ada di kartu stok (tidak pernah diarsipkan): setiap pengembalian item ini membawa tanda
            // "(item #id)". Log audit di atas hanya lapisan kedua, karena data:rapikan memindahkannya ke arsip setelah 24 bulan.
            $penanda = "(item #{$idDetail})";
            $sudah = $sudah || DB::table('kartu_stok')->where('id_bahan', (int) $detail->id_bahan)->where('tipe', 'IN')
                ->where('keterangan', 'like', '%' . $penanda)->exists();
            if ($idDetail <= 0 || $sudah || (float) $detail->qty <= 0) {
                return [];
            }

            $hasil = self::tambahkanKembali((int) $detail->id_bahan, (float) $detail->qty, $referensi, $keterangan . ' ' . $penanda);
            $ringkasan = $hasil !== null ? [$hasil] : [];
            self::logReversal($idDetail, 'bahan-langsung', $ringkasan, $referensi, $keterangan);

            return $ringkasan;
        });
    }

    /** Jalur akurat: qty persis seperti yang dipotong saat order dibuat, sudah dalam satuan stok - tanpa konversi/rumus BOM apa pun lagi. */
    private static function kembalikanDariSnapshot($snapshot, string $referensi, string $keterangan): array
    {
        $ringkasan = [];
        foreach ($snapshot as $baris) {
            $hasil = self::tambahkanKembali((int) $baris->id_bahan, (float) $baris->qty_pakai, $referensi, $keterangan);
            if ($hasil !== null) {
                $ringkasan[] = $hasil;
            }
        }

        return $ringkasan;
    }

    /** Fallback untuk transaksi sebelum snapshot ini ada - PERSIS rumus lama. */
    private static function kembalikanRecomputeBomLama(object $detail, ?string $noNota, string $referensi, string $keterangan): array
    {
        $ringkasan = [];
        $noNota ??= DB::table('transaksi')->where('id_transaksi', $detail->id_transaksi ?? 0)->value('nomor_nota');

        foreach (BomCalculationService::komponen((int) $detail->id_produk, (float) $detail->p_ukuran, (float) $detail->l_ukuran, (float) $detail->qty) as $bom) {
            $qtyPakai = $bom->qty_pakai;
            if ($qtyPakai <= 0) {
                continue;
            }

            // Tinta baru ikut dipotong sejak fitur potong-tinta aktif: nota lama (tanpa catatan OUT tinta) tidak dikembalikan.
            if ($bom->is_tinta && !DB::table('kartu_stok')->where('id_bahan', $bom->id_bahan)->where('tipe', 'OUT')->where('referensi', $noNota)->exists()) {
                continue;
            }

            $bahan = DB::table('bahan_baku')->where('id_bahan', $bom->id_bahan)->lockForUpdate()->first();
            if (!$bahan) {
                continue;
            }

            $nilaiKonversi = (float) ($bahan->nilai_konversi ?: 1);
            if ($nilaiKonversi <= 0) {
                $nilaiKonversi = 1;
            }
            $qtyKembali = $qtyPakai / $nilaiKonversi;

            $hasil = self::tambahkanKembali((int) $bom->id_bahan, $qtyKembali, $referensi, $keterangan, $bahan);
            if ($hasil !== null) {
                $ringkasan[] = $hasil;
            }
        }

        return $ringkasan;
    }

    /** Tambah balik ke bahan_baku.stok + catat kartu_stok IN - dipakai kedua jalur (snapshot & fallback). */
    private static function tambahkanKembali(int $idBahan, float $qtyKembali, string $referensi, string $keterangan, ?object $bahanSudahDikunci = null): ?string
    {
        $bahan = $bahanSudahDikunci ?? DB::table('bahan_baku')->where('id_bahan', $idBahan)->lockForUpdate()->first();
        if (!$bahan) {
            return null;
        }

        $stokSebelum = (float) $bahan->stok;
        $stokSesudah = $stokSebelum + $qtyKembali;

        DB::table('bahan_baku')->where('id_bahan', $idBahan)->update(['stok' => $stokSesudah]);

        DB::table('kartu_stok')->insert([
            'id_bahan'     => $idBahan,
            'tanggal'      => now(),
            'tipe'         => 'IN',
            'qty'          => $qtyKembali,
            'stok_sebelum' => $stokSebelum,
            'stok_sesudah' => $stokSesudah,
            'referensi'    => $referensi,
            'keterangan'   => $keterangan,
            'created_by'   => Auth::id(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return $bahan->nama_bahan . ' +' . angka($qtyKembali, 2);
    }
}
