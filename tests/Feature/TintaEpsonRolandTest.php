<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** data:tinta-epson-roland boleh dijalankan ulang: pemakaian tinta yang sudah dikoreksi dengan data nyata tidak ditimpa estimasi awal. */
class TintaEpsonRolandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dijalankan_ulang_tidak_menimpa_pemakaian_nyata(): void
    {
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'EPSON S80670', 'tipe_mesin' => 'Printer']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'Tinta']);
        DB::table('bahan_baku')->insert(['kode_bahan' => 'TNT-S80670', 'nama_bahan' => 'TINTA S80670', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 1, 'nilai_konversi' => 1000, 'harga_rata_rata' => 500000, 'satuan_stok' => 'botol', 'status' => 'aktif']);

        $this->artisan('data:tinta-epson-roland', ['--tanpa-backup' => true])->assertSuccessful();
        $this->assertSame(9, DB::table('pemakaian_tinta')->where('id_mesin', $mesin)->count());

        $cyan = (int) DB::table('bahan_baku')->where('kode_bahan', 'TNT-S80670-CY')->value('id_bahan');
        DB::table('pemakaian_tinta')->where('id_bahan', $cyan)->update(['pemakaian' => 2.5, 'keterangan' => 'Hasil ukur nyata']);

        $this->artisan('data:tinta-epson-roland', ['--tanpa-backup' => true])->assertSuccessful();
        $baris = DB::table('pemakaian_tinta')->where('id_bahan', $cyan)->first();
        $this->assertEquals(2.5, (float) $baris->pemakaian);
        $this->assertSame('Hasil ukur nyata', $baris->keterangan);
        $this->assertSame(9, DB::table('pemakaian_tinta')->where('id_mesin', $mesin)->count());
    }
}
