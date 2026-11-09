<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Sumber tunggal untuk menentukan harga jual efektif sebuah produk, supaya
 * harga yang DITAMPILKAN ke operator (live saat mengetik qty di POS) dan
 * harga yang benar-benar DISIMPAN saat checkout (PenjualanController::store)
 * selalu dihitung dengan cara yang identik. Sebelumnya kedua tempat itu
 * masing-masing punya query sendiri yang berpotensi ngedrift.
 */
class HargaService
{
    /**
     * Harga promo (kalau sedang aktif) selalu menang di atas price list biasa,
     * dan berlaku untuk SEMUA golongan konsumen (tabel harga_promo memang
     * tidak punya kolom id_golongan - flat price, bukan per range/golongan).
     * Begitu promo berakhir (lewat tgl_selesai atau status diubah manual),
     * harga otomatis kembali ke setting_harga tanpa perlu ubah apa pun di
     * sana - harga normal tidak pernah disentuh oleh promo.
     */
    public static function getHargaEfektif(int $idProduk, int $idGolongan, float $qty, bool $siapCetak = false): array
    {
        // File Siap Cetak: harga tersendiri per produk (tier & golongan sendiri), tanpa promo.
        if ($siapCetak) {
            $tier = DB::table('harga_siap_cetak')
                ->where('id_produk', $idProduk)->where('id_golongan', $idGolongan)
                ->where('range_min', '<=', $qty)
                ->where(fn ($q) => $q->whereNull('range_max')->orWhere('range_max', '>=', $qty))
                ->orderBy('range_min', 'desc')->first();

            return ['harga_jual' => $tier ? (float) $tier->harga_jual : 0, 'is_promo' => false, 'promo_selesai' => null, 'siap_cetak' => true];
        }

        $promo = self::promoAktif($idProduk);

        if ($promo) {
            return [
                'harga_jual' => (float) $promo->harga_promo,
                'is_promo' => true,
                'promo_selesai' => $promo->tgl_selesai,
            ];
        }

        $harga = DB::table('setting_harga')
            ->where('id_produk', $idProduk)
            ->where('id_golongan', $idGolongan)
            ->where('range_min', '<=', $qty)
            ->where(function ($q) use ($qty) {
                $q->whereNull('range_max')->orWhere('range_max', '>=', $qty);
            })
            ->orderBy('range_min', 'desc')
            ->first();

        return [
            'harga_jual' => $harga ? (float) $harga->harga_jual : 0,
            'is_promo' => false,
            'promo_selesai' => null,
        ];
    }

    /** Id produk yang punya harga File Siap Cetak (opsi ini hanya ditawarkan untuk produk tersebut). */
    public static function produkDenganHargaSiapCetak(): array
    {
        return DB::table('harga_siap_cetak')->distinct()->pluck('id_produk')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Harga Finishing bertingkat berdasarkan qty (finishing_harga) - TANPA
     * dimensi golongan (finishing dianggap ongkos kerja, satu rentang
     * berlaku untuk semua pelanggan). Kalau tidak ada tier yang cocok atau
     * belum diatur tiernya sama sekali, jatuh balik ke finishing.harga_default.
     * Ini murni ANGKA ACUAN/starting point untuk POS - operator tetap bisa
     * menimpa manual, sama seperti perilaku harga_default sebelumnya.
     */
    public static function getHargaFinishingEfektif(int $idFinishing, float $qty): float
    {
        $tier = DB::table('finishing_harga')
            ->where('id_finishing', $idFinishing)
            ->where('range_min', '<=', $qty)
            ->where(function ($q) use ($qty) {
                $q->whereNull('range_max')->orWhere('range_max', '>=', $qty);
            })
            ->orderBy('range_min', 'desc')
            ->first();

        if ($tier) {
            return (float) $tier->harga;
        }

        $default = DB::table('finishing')->where('id_finishing', $idFinishing)->value('harga_default');

        return (float) ($default ?? 0);
    }

    /**
     * Sama seperti getHargaFinishingEfektif tapi untuk Jasa (jasa_harga +
     * jasa.harga_default sebagai fallback). Jasa sebelumnya tidak punya
     * harga tersimpan sama sekali - ini angka acuan baru, kasir tetap bisa
     * override manual di POS.
     */
    public static function getHargaJasaEfektif(int $idJasa, float $qty): float
    {
        $tier = DB::table('jasa_harga')
            ->where('id_jasa', $idJasa)
            ->where('range_min', '<=', $qty)
            ->where(function ($q) use ($qty) {
                $q->whereNull('range_max')->orWhere('range_max', '>=', $qty);
            })
            ->orderBy('range_min', 'desc')
            ->first();

        if ($tier) {
            return (float) $tier->harga;
        }

        $default = DB::table('jasa')->where('id_jasa', $idJasa)->value('harga_default');

        return (float) ($default ?? 0);
    }

    public static function promoAktif(int $idProduk): ?object
    {
        $today = now()->format('Y-m-d');

        return DB::table('harga_promo')
            ->where('id_produk', $idProduk)
            ->where('status_promo', 'aktif')
            ->where('tgl_mulai', '<=', $today)   // kolom DATE: sargable
            ->where('tgl_selesai', '>=', $today)
            ->orderByDesc('id_promo')
            ->first();
    }

    /**
     * Kalkulasi biaya finishing mode LINEAR_GRID (Potong Manual).
     *
     * SATUAN INPUT: CENTIMETER, sama dengan field Panjang/Lebar di form pemesanan.
     * Konversi ke meter dilakukan DI DALAM fungsi ini (/100), jadi pemanggil
     * cukup mengirim angka cm apa adanya.
     *
     * Formula:
     *   - Kolom = FLOOR(lebar_media / lebar_potong)      (rasio cm:cm)
     *   - Baris = FLOOR(tinggi_media / tinggi_potong)   (rasio cm:cm)
     *   - Garis vertikal   = MAX(0, kolom - 1)   -> memotong tegak, panjang = tinggi_media
     *   - Garis horizontal = MAX(0, baris - 1)   -> memotong mendatar, panjang = lebar_media
     *   - Meter potong per media = [(garis_vertikal x tinggi_media)
     *                               + (garis_horizontal x lebar_media)] / 100
     *   - Meter total = meter per media x qty cetak
     *   - Harga dasar = meter total x tarif_per_meter
     *   - Biaya finishing = MAX(minimum_charge, harga_dasar)
     *
     * Tarif per meter dan minimum charge KEDUA-DUANYA diambil dari master
     * finishing (kolom harga_default dan minimum_charge), jadi bisa diubah
     * operator dari Master Finishing tanpa menyentuh kode. Minimum charge 0
     * berarti tidak ada lantai biaya.
     *
     * Contoh terverifikasi (tarif 450/meter, minimum charge 5.000):
     *   media 1000cm x 1000cm, potong 500cm x 500cm, qty 1
     *     kolom 2, baris 2 -> (1x1000 + 1x1000)/100 = 20 meter -> 20 x 450 = 9.000
     *   media 1000cm x 1000cm, potong 1000cm x 1000cm, qty 1
     *     kolom 1, baris 1 -> 0 meter -> maximum dengan minimum charge = 5.000
     *   media 1000cm x 1000cm, potong 2000cm x 2000cm, qty 1
     *     potong lebih besar dari media -> 0
     *
     * @param  float  $qtyCetak  jumlah cetak; meter potong dikalikan nilai ini
     * @return float biaya finishing yang sudah dihitung (0 jika finishing bukan LINEAR_GRID)
     */
    public static function hitungBiayaFinishingLinearGrid(int $idFinishing, float $lebarMedia, float $tinggiMedia, float $lebarPotong, float $tinggiPotong, float $qtyCetak = 1): float
    {
        // Satu query untuk tiga hal yang dibutuhkan: mode hitung, tarif per
        // meter, dan minimum charge.
        $finishing = DB::table('finishing')
            ->where('id_finishing', $idFinishing)
            ->first(['metode_hitung', 'harga_default', 'minimum_charge']);

        // Hanya proses jika finishing memiliki mode LINEAR_GRID
        if (!$finishing || $finishing->metode_hitung !== 'LINEAR_GRID') {
            return 0;
        }

        // Tarif per meter dari master finishing (harga_default).
        $tarifPerMeter = (float) ($finishing->harga_default ?? 0);

        // Minimum charge = LANTAI biaya finishing per item, diambil dari
        // master finishing (kolom minimum_charge). Nilai 0 = tidak ada
        // minimum charge, biaya persis hasil rumus.
        //
        // Dulu angka ini ditulis mati di sini (Rp 5.000), padahal layar
        // Input Pesanan punya salinan angka yang sama di sisi peramban -
        // dua tempat, satu angka, tanpa tempat mengubahnya. Sekarang
        // keduanya membaca baris master yang sama, jadi tidak mungkin lagi
        // layar dan server berbeda.
        $minimumCharge = (float) ($finishing->minimum_charge ?? 0);

        // Validasi dimensi potong. Tanpa dimensi valid tidak ada garis potong
        // yang bisa dihitung, jadi biaya 0 (bukan minimum charge).
        if ($lebarPotong <= 0 || $tinggiPotong <= 0) {
            return 0;
        }
        if ($lebarMedia <= 0 || $tinggiMedia <= 0) {
            return 0;
        }

        // Kalkulasi grid. orientarasi deliberate: lebar media dibagi lebar potong
        // (kiri-kanan = kolom), tinggi dibagi tinggi potong (atas-bawah = baris).
        $kolom = (int) floor($lebarMedia / $lebarPotong);
        $baris = (int) floor($tinggiMedia / $tinggiPotong);

        if ($kolom < 1 || $baris < 1) {
            // Potong lebih besar dari media: tidak ada satu pun keping yang bisa dipotong.
            return 0;
        }

        $garisVertikal = max(0, $kolom - 1);
        $garisHorizontal = max(0, $baris - 1);

        // Panjang garis masih cm; /100 -> meter.
        $meterPerMedia = (($garisVertikal * $tinggiMedia) + ($garisHorizontal * $lebarMedia)) / 100;

        $qtyCetak = $qtyCetak > 0 ? $qtyCetak : 1;
        $meterTotal = $meterPerMedia * $qtyCetak;

        // Formula harga
        $hargaDasar = $meterTotal * $tarifPerMeter;
        $biayaFinishing = max($minimumCharge, $hargaDasar);

        return (float) $biayaFinishing;
    }
}
