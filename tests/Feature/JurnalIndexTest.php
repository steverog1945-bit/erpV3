<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Audit Fase 3 (performa): laporan buku besar mengandalkan dua indeks ini
 * (diukur pada 600.000 baris: 7,4 dtk -> 0,6 dtk; 3,9 dtk -> 0,23 dtk).
 * Tes ini mencegah indeks hilang diam-diam oleh migrasi berikutnya.
 */
class JurnalIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_indeks_laporan_jurnal_umum_ada_dengan_kolom_dan_urutan_yang_benar(): void
    {
        $kolom = collect(Schema::getIndexes('jurnal_umum'))->mapWithKeys(fn ($i) => [$i['name'] => $i['columns']]);

        $this->assertSame(['tgl_jurnal'], $kolom['jurnal_umum_tgl_jurnal_index'] ?? null);
        $this->assertSame(['kode_akun', 'tgl_jurnal', 'debet', 'kredit'], $kolom['jurnal_umum_akun_tgl_debet_kredit_index'] ?? null);
    }
}
