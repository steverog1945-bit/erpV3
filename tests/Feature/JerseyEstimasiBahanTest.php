<?php

namespace Tests\Feature;

use App\Services\JerseyMaterialEstimationService as Estimasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JerseyEstimasiBahanTest extends TestCase
{
    use RefreshDatabase;

    private int $ukuranM;
    private int $tipeJersey;
    private int $tipeCelana;
    private int $pendek;
    private int $panjang;

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();
        $this->ukuranM = $this->ambil('jersey_sizes', 'M', ['sort_order' => 1]);
        $this->tipeJersey = $this->ambil('jersey_product_types', 'Jersey', ['has_sleeve_collar' => 1]);
        $this->tipeCelana = $this->ambil('jersey_product_types', 'Celana Pendek', ['has_sleeve_collar' => 0]);
        $this->pendek = $this->ambil('jersey_sleeve_types', 'Pendek', ['sort_order' => 1]);
        $this->panjang = $this->ambil('jersey_sleeve_types', 'Panjang', ['sort_order' => 2]);

        // Skema tes sudah membawa Body/Lengan/Rib/Celana - hanya yang dipakai tes ini yang aktif.
        DB::table('jersey_components')->update(['is_active' => 0]);
        $body = $this->komponen('Body', null, 1);
        $celana = $this->komponen('Celana', $this->tipeCelana, 2);
        DB::table('jersey_size_component_usage')->insert([
            ['jersey_size_id' => $this->ukuranM, 'jersey_component_id' => $body, 'usage_meter' => 0.7, 'usage_weight_gram' => 160, 'created_at' => $now, 'updated_at' => $now],
            ['jersey_size_id' => $this->ukuranM, 'jersey_component_id' => $celana, 'usage_meter' => 0.9, 'usage_weight_gram' => 120, 'created_at' => $now, 'updated_at' => $now],
        ]);
        foreach ([[$this->pendek, 0.2, 60], [$this->panjang, 0.33, 105]] as [$idTipe, $m, $g]) {
            DB::table('jersey_size_sleeve_usage')->updateOrInsert(['jersey_size_id' => $this->ukuranM, 'jersey_sleeve_type_id' => $idTipe], ['usage_meter' => $m, 'usage_weight_gram' => $g, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    /** Ambil baris master (dibuat bila belum ada) - skema tes mungkin sudah membawa data awal. */
    private function ambil(string $tabel, string $nama, array $atribut): int
    {
        DB::table($tabel)->updateOrInsert(['name' => $nama], $atribut + ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return (int) DB::table($tabel)->where('name', $nama)->value('id');
    }

    private function komponen(string $nama, ?int $idTipe, int $urut): int
    {
        DB::table('jersey_components')->updateOrInsert(['name' => $nama], [
            'jersey_product_type_id' => $idTipe, 'jersey_category_id' => null, 'sort_order' => $urut, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('jersey_components')->where('name', $nama)->value('id');
    }

    private function namaKomponen(array $hasil): array
    {
        return array_map(fn ($e) => $e['component']->name, $hasil);
    }

    public function test_jersey_lengan_pendek_hanya_memuat_body_dan_lengan_pendek_tanpa_celana_atau_lengan_panjang(): void
    {
        $komponen = Estimasi::hitungEstimasi([$this->ukuranM => 10], $this->tipeJersey, null);
        $lengan = Estimasi::hitungEstimasiLengan([$this->ukuranM => [$this->pendek => 10]]);

        $this->assertSame(['Body'], $this->namaKomponen($komponen));
        $this->assertSame(['Pendek'], $this->namaKomponen($lengan));
        $this->assertEqualsWithDelta(7.0, $komponen[0]['total_meter'], 0.001);
        $this->assertEqualsWithDelta(2.0, $lengan[0]['total_meter'], 0.001);
    }

    public function test_campuran_lengan_hanya_memuat_tipe_yang_benar_benar_dipesan(): void
    {
        $lengan = Estimasi::hitungEstimasiLengan([$this->ukuranM => [$this->pendek => 6, $this->panjang => 4]]);

        $this->assertSame(['Pendek', 'Panjang'], $this->namaKomponen($lengan));
        $this->assertEqualsWithDelta(6 * 0.2, $lengan[0]['total_meter'], 0.001);
        $this->assertEqualsWithDelta(4 * 0.33, $lengan[1]['total_meter'], 0.001);
    }

    public function test_celana_hanya_memuat_komponen_celana_bukan_body_dan_tidak_punya_lengan(): void
    {
        $komponen = Estimasi::hitungEstimasi([$this->ukuranM => 10], $this->tipeCelana, null);

        $this->assertSame(['Celana'], $this->namaKomponen($komponen));
        $this->assertFalse(Estimasi::tipeMemilikiLenganKerah($this->tipeCelana));
        $this->assertTrue(Estimasi::tipeMemilikiLenganKerah($this->tipeJersey));
        $this->assertTrue(Estimasi::tipeMemilikiLenganKerah(null));
    }
}
