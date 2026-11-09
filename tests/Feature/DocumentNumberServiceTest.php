<?php

namespace Tests\Feature;

use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_nomor_urut_bertambah_sekuensial(): void
    {
        $this->assertSame('INV-' . now()->format('Ymd') . '-001', DocumentNumberService::generate('INV'));
        $this->assertSame('INV-' . now()->format('Ymd') . '-002', DocumentNumberService::generate('INV'));
        $this->assertSame('INV-' . now()->format('Ymd') . '-003', DocumentNumberService::generate('INV'));
    }

    public function test_kode_dokumen_berbeda_punya_counter_terpisah(): void
    {
        DocumentNumberService::generate('INV');
        DocumentNumberService::generate('INV');

        // 'JO' harus mulai dari 001 sendiri, tidak melanjutkan urutan 'INV'
        // - bug ini sempat nyata terjadi sebelum PK komposit diperbaiki
        // (lihat catatan di migration create_document_number_counters_table).
        $this->assertSame('JO-' . now()->format('Ymd') . '-001', DocumentNumberService::generate('JO'));
    }

    public function test_format_periode_custom(): void
    {
        $nomor = DocumentNumberService::generate('BLN', 'Ym', 4);
        $this->assertSame('BLN-' . now()->format('Ym') . '-0001', $nomor);
    }

    public function test_panjang_digit_urut_custom(): void
    {
        $nomor = DocumentNumberService::generate('X', 'Ymd', 5);
        $this->assertStringEndsWith('-00001', $nomor);
    }

    public function test_seratus_panggilan_beruntun_semua_unik_dan_sekuensial(): void
    {
        $nomors = [];
        for ($i = 1; $i <= 100; $i++) {
            $nomors[] = DocumentNumberService::generate('BULK');
        }

        $this->assertCount(100, array_unique($nomors), 'Semua nomor harus unik - tidak boleh ada tabrakan');
        $this->assertSame('BULK-' . now()->format('Ymd') . '-100', end($nomors));
    }
}
