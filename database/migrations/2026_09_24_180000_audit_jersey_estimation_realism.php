<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit kewajaran estimasi kain Jersey (meter & gram per pcs). Temuan:
 * 1. Gram tidak sebanding dengan meter: berat per meter melebar dari ~200
 *    sampai ~300 g/m (Body 5XL 320 g untuk 1,04 m). Kain jersey dryfit
 *    +/- 150 gsm x lebar 1,5 m = +/- 225 g per meter lari, jadi gram
 *    dihitung ulang = meter x 225 (dibulatkan 5 g).
 * 2. Kurva ukuran Body tidak halus (S-L hampir datar lalu XL melompat 15%);
 *    dirapikan naik +/- 5,5% per ukuran (sesuai selisih lingkar dada
 *    +2 cm & panjang +1,5 cm per ukuran).
 * 3. Lengan tumbuh 9%/ukuran (0,18 -> 0,34) padahal body cuma 5,5%;
 *    disamakan +/- 5,5%. Lengan panjang serupa.
 * 4. Celana pendek 0,84-1,25 m tidak wajar (celana pendek dewasa L
 *    +/- 0,65 m pada lebar kain 1,5 m; 1 m kain lebih mahal dari harga
 *    jual celana Rp 30.000). Diturunkan ke 0,55-0,94 m.
 * Tetap ACUAN - verifikasi dengan pengukuran/timbang riil. Hanya mengubah
 * baris yang masih persis sama dengan angka lama, jadi tidak menimpa edit
 * Anda. Kerah & Rib tidak diubah (sudah masuk akal: 5-30 g per kerah).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $ukuran = DB::table('jersey_sizes')->orderBy('sort_order')->orderBy('id')->pluck('id')->values();
        if ($ukuran->count() !== 8) {
            return;
        }

        // [tabel, kolom FK, id FK, meter lama, gram lama, meter baru, gram baru]
        $daftar = [
            ['jersey_size_component_usage', 'jersey_component_id', $this->idKomponen('Body'),
                [0.712, 0.73, 0.75, 0.86, 0.92, 0.96, 1.0, 1.04], [140, 160, 180, 200, 230, 260, 290, 320],
                [0.71, 0.74, 0.78, 0.83, 0.88, 0.93, 0.99, 1.05], [160, 165, 175, 185, 200, 210, 225, 235]],
            ['jersey_size_component_usage', 'jersey_component_id', $this->idKomponen('Celana'),
                [0.84, 0.89, 1.0, 1.05, 1.1, 1.15, 1.2, 1.25], [110, 120, 135, 150, 170, 190, 210, 230],
                [0.55, 0.60, 0.65, 0.70, 0.76, 0.82, 0.88, 0.94], [125, 135, 145, 160, 170, 185, 200, 210]],
            ['jersey_size_sleeve_usage', 'jersey_sleeve_type_id', $this->idTipeLengan('Pendek'),
                [0.18, 0.2, 0.22, 0.24, 0.26, 0.28, 0.31, 0.34], [55, 60, 65, 70, 80, 90, 100, 110],
                [0.18, 0.19, 0.20, 0.21, 0.22, 0.24, 0.25, 0.26], [40, 45, 45, 45, 50, 55, 55, 60]],
            ['jersey_size_sleeve_usage', 'jersey_sleeve_type_id', $this->idTipeLengan('Panjang'),
                [0.42, 0.46, 0.5, 0.54, 0.6, 0.65, 0.71, 0.77], [130, 145, 160, 175, 195, 215, 235, 255],
                [0.42, 0.44, 0.47, 0.49, 0.52, 0.55, 0.58, 0.61], [95, 100, 105, 110, 115, 125, 130, 135]],
        ];

        foreach ($daftar as [$tabel, $kolom, $idFk, $mLama, $gLama, $mBaru, $gBaru]) {
            if (!$idFk) {
                continue;
            }
            foreach ($ukuran as $i => $idUkuran) {
                DB::table($tabel)
                    ->where($kolom, $idFk)->where('jersey_size_id', $idUkuran)
                    ->whereRaw('ABS(usage_meter - ?) < 0.0005', [$mLama[$i]])
                    ->whereRaw('ABS(usage_weight_gram - ?) < 0.005', [$gLama[$i]])
                    ->update(['usage_meter' => $mBaru[$i], 'usage_weight_gram' => $gBaru[$i], 'updated_at' => now()]);
            }
        }
    }

    private function idKomponen(string $nama): ?int
    {
        return DB::table('jersey_components')->where('name', $nama)->value('id');
    }

    private function idTipeLengan(string $nama): ?int
    {
        return DB::table('jersey_sleeve_types')->where('name', $nama)->value('id');
    }

    public function down(): void
    {
        // Data master - tidak dikembalikan otomatis (ada backup sebelum audit).
    }
};
