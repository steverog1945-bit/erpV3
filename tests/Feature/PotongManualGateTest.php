<?php

namespace Tests\Feature;

use App\Services\HargaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Gate Potong Manual (LINEAR_GRID).
 *
 * Kasus acuan yang dipakai pemilik: media 150 x 40 cm, potong 10 x 10 cm,
 * jarak (gap) 0, tarif Rp450/meter, minimum charge Rp5.000.
 *
 *   QTY 1 = 10,10 m  = Rp5.000   (minimum charge yang berlaku, bukan 4.545)
 *   QTY 2 = 20,20 m  = Rp9.090
 *   QTY 3 = 30,30 m  = Rp13.635
 *   QTY 5 = 50,50 m  = Rp22.725
 *
 * Ini menguji SATU-SATUNYA rumus server. Sisi peramban diuji terpisah oleh
 * tests/Feature/PotongManualFrontendGateTest.php, yang benar-benar mengeksekusi
 * JavaScript aslinya lewat Node - bukan menyalin logikanya ke PHP, karena
 * salinan seperti itu bisa hijau sementara kodenya sendiri salah.
 */
class PotongManualGateTest extends TestCase
{
    use RefreshDatabase;

    private const LEBAR_MEDIA = 150.0;
    private const TINGGI_MEDIA = 40.0;
    private const LEBAR_POTONG = 10.0;
    private const TINGGI_POTONG = 10.0;
    private const TARIF = 450.0;
    private const MINIMUM_CHARGE = 5000.0;

    private function finishingPotongManual(): int
    {
        return DB::table('finishing')->insertGetId([
            'nama_finishing' => 'Potong Manual Uji',
            'metode_hitung' => 'LINEAR_GRID',

            'harga_default' => self::TARIF,
            // Minimum charge sekarang datanya master (kolom finishing), bukan
            // angka mati di dalam kode.
            'minimum_charge' => self::MINIMUM_CHARGE,
        ]);
    }

    public static function kasusAcuan(): array
    {
        return [
            'qty 1minimum charge berlaku' => [1, 5000.0],
            'qty 2' => [2, 9090.0],
            'qty 3' => [3, 13635.0],
            'qty 5' => [5, 22725.0],
        ];
    }

    /** @dataProvider kasusAcuan */
    public function test_biaya_potong_manual_sesuai_acuan(int $qty, float $harapan): void
    {
        $id = $this->finishingPotongManual();

        $biaya = HargaService::hitungBiayaFinishingLinearGrid(
            $id,
            self::LEBAR_MEDIA,
            self::TINGGI_MEDIA,
            self::LEBAR_POTONG,
            self::TINGGI_POTONG,
            $qty
        );

        $this->assertEqualsWithDelta($harapan, $biaya, 0.001, "QTY {$qty} harus Rp" . number_format($harapan, 0, ',', '.'));
    }

    /** @dataProvider kasusAcuan */
    public function test_meter_potong_bukan_meter_media_atau_pcs_hasil_potong(int $qty, float $harapan): void
    {
        $id = $this->finishingPotongManual();

        // Sisa rumus supaya kegagalan bisa dibaca langsung tanpamenebak.
        // kolom = floor(150/10) = 15, baris = floor(40/10) = 4
        // garis vertikal = 14 -> 14 x 40 = 560 cm
        // garis horizontal = 3 -> 3 x 150 = 450 cm
        // total 1010 cm = 10,10 m per media, dikali qty.
        $meterYangHarusDipakai = 10.10 * $qty;
        $meterMediaSalah = (self::LEBAR_MEDIA * self::TINGGI_MEDIA / 10000) * $qty;
        $jumlahKepingSalah = (15 * 4) * $qty;

        $biaya = HargaService::hitungBiayaFinishingLinearGrid(
            $id,
            self::LEBAR_MEDIA,
            self::TINGGI_MEDIA,
            self::LEBAR_POTONG,
            self::TINGGI_POTONG,
            $qty
        );

        $biayaKalauPakaiMeterMedia = max(5000.0, $meterMediaSalah * self::TARIF);
        $biayaKalauPakaiJumlahKeping = max(5000.0, $jumlahKepingSalah * self::TARIF);

        // QTY 1 kebetulan minimum charge menutupi bedanya, jadi hanya
        //	qty >= 2 yang bisa membedakan ketiga rumus.
        if ($qty >= 2) {
            $this->assertNotEquals($biayaKalauPakaiMeterMedia, $biaya, 'Harus memakai meter GARIS POTONG, bukan m² media.');
            $this->assertNotEquals($biayaKalauPakaiJumlahKeping, $biaya, 'Harus memakai meter GARIS POTONG, bukan jumlah keping hasil potong.');
        }

        $this->assertEqualsWithDelta(
            max(5000.0, $meterYangHarusDipakai * self::TARIF),
            $biaya,
            0.001
        );
    }

    /** @dataProvider kasusAcuan */
    public function test_qty_naik_menambah_biaya_monoton(int $qty, float $harapan): void
    {
        $id = $this->finishingPotongManual();

        $sebelumnya = 0.0;
        foreach ([1, 2, 3, 4, 5] as $q) {
            $biaya = HargaService::hitungBiayaFinishingLinearGrid(
                $id,
                self::LEBAR_MEDIA,
                self::TINGGI_MEDIA,
                self::LEBAR_POTONG,
                self::TINGGI_POTONG,
                $q
            );
            $this->assertGreaterThanOrEqual($sebelumnya, $biaya, "QTY {$q} tidak boleh lebih murah dari QTY sebelumnya.");
            $sebelumnya = $biaya;
        }
    }

    public function test_finishing_bukan_potong_manual_tidak_ikut_dihitung(): void
    {
        $id = DB::table('finishing')->insertGetId([
            'nama_finishing' => 'Laminasi Uji',
            'metode_hitung' => 'luas',
            
            'harga_default' => 450.0,
        ]);

        $this->assertSame(0.0, HargaService::hitungBiayaFinishingLinearGrid($id, 150, 40, 10, 10, 5));
    }

    public function test_master_finishing_lain_tidak_berubah(): void
    {
        // Minimum charge Rp5.000 milik Potong Manual. Finishing lain yang tidak
        // memakai rumus grid harus tetap dihargai sesuai metodenya sendiri.
        $laminasi = DB::table('finishing')->insertGetId([
            'nama_finishing' => 'Laminasi Glossy',
            'metode_hitung' => 'luas',
            
            'harga_default' => 8000.0,
        ]);

        // 150 x 40 cm = 0,6 m2.
        $luas = (150 * 40 / 10000);
        $hargaLaminasi = round(HargaService::getHargaFinishingEfektif($laminasi, $luas) * $luas);

        $this->assertSame(0.0, HargaService::hitungBiayaFinishingLinearGrid($laminasi, 150, 40, 10, 10, 3));
        $this->assertGreaterThan(0.0, $hargaLaminasi);
        $this->assertNotEquals(5000.0, $hargaLaminasi, 'Laminasi per m2 tidak boleh dipaksa ke minimum charge grid.');
    }

    public function test_potong_lebih_besar_dari_media_nol_bukan_minimum_charge(): void
    {
        $id = $this->finishingPotongManual();

        $biaya = HargaService::hitungBiayaFinishingLinearGrid($id, 150, 40, 200, 200, 3);

        $this->assertSame(0.0, $biaya, 'Potong lebih besar dari media berarti tidak ada keping - minimal charge tidak berlaku.');
    }

    public function test_minimum_charge_hanya_dipakai_satu_kali_per_item(): void
    {
        $id = $this->finishingPotongManual();

        // Qty 1 = 10,10 m x 450 = 4.545 -> minimum 5.000
        // Qty 2 = 20,20 m x 450 = 9.090, TIDAK 5.000 per meter.
        $satu = HargaService::hitungBiayaFinishingLinearGrid($id, 150, 40, 10, 10, 1);
        $dua = HargaService::hitungBiayaFinishingLinearGrid($id, 150, 40, 10, 10, 2);

        $this->assertSame(5000.0, $satu);
        $this->assertSame(9090.0, $dua);
        $this->assertNotEquals($satu * 2, $dua, 'Minimum charge tidak boleh dikalikan qty.');
    }

    /** Minimum charge datanya master, bukan angka mati di dalam kode. */
    public function test_minimum_charge_diambil_dari_master_dan_bisa_diubah(): void
    {
        $id = $this->finishingPotongManual();

        // Default master = 5.000 -> biaya qty 1 (4.545) dikejar ke 5.000.
        $this->assertSame(5000.0, HargaService::hitungBiayaFinishingLinearGrid($id, 150, 40, 10, 10, 1));

        // Minimum dinaikkan jadi 8.000 -> biaya qty 1 ikut jadi 8.000.
        DB::table('finishing')->where('id_finishing', $id)->update(['minimum_charge' => 8000]);
        $this->assertSame(8000.0, HargaService::hitungBiayaFinishingLinearGrid($id, 150, 40, 10, 10, 1));

        // Minimum diturunkan jadi 2.000 -> biaya qty 1 (4.545) TIDAK lagi dikejar,
        // jadi biaya persis hasil rumus.
        DB::table('finishing')->where('id_finishing', $id)->update(['minimum_charge' => 2000]);
        $this->assertSame(4545.0, HargaService::hitungBiayaFinishingLinearGrid($id, 150, 40, 10, 10, 1));
    }

    /** Minimum charge 0 = tidak ada lantai biaya sama sekali. */
    public function test_minimum_charge_nol_berarti_tanpa_lantai_biaya(): void
    {
        $id = $this->finishingPotongManual();
        DB::table('finishing')->where('id_finishing', $id)->update(['minimum_charge' => 0]);

        // 10,10 m x 450 = 4.545, tanpa minimum charge.
        $this->assertSame(4545.0, HargaService::hitungBiayaFinishingLinearGrid($id, 150, 40, 10, 10, 1));
    }
}