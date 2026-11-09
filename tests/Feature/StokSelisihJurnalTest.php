<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\PenyesuaianStokService;
use App\Services\StockOpnameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Stok yang berubah lewat stock opname atau penyesuaian manual ikut mengubah nilai Persediaan (1200) di jurnal,
 * dengan lawan akun 5230 Selisih Persediaan, dinilai qty selisih x harga rata-rata. Tanpa ini nilai persediaan di
 * neraca terus menyimpang dari stok fisik.
 */
class StokSelisihJurnalTest extends TestCase
{
    use RefreshDatabase;

    private int $bahan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'x']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K', 'kode_kategori' => 'K']);
        $this->bahan = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'B', 'nama_bahan' => 'Flexi', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 100, 'nilai_konversi' => 1, 'harga_rata_rata' => 1000, 'satuan_stok' => 'm2', 'status' => 'aktif']);
        AccountingService::postSaldoAwalPersediaan('SA-B', 'Saldo awal Flexi', 100000);
    }

    private function saldo(string $kode): float
    {
        return round((float) DB::table('jurnal_umum')->where('kode_akun', $kode)->sum(DB::raw('debet - kredit')), 2);
    }

    private function seimbang(): void
    {
        $this->assertEquals((float) DB::table('jurnal_umum')->sum('debet'), (float) DB::table('jurnal_umum')->sum('kredit'));
    }

    private function opname(float $fisik): void
    {
        $id = DB::table('stock_opnames')->insertGetId(['nomor' => 'SO-1', 'tanggal' => now()->toDateString(), 'status' => 'Diajukan', 'id_user_pembuat' => auth()->id()]);
        DB::table('stock_opname_details')->insert(['id_stock_opname' => $id, 'id_bahan' => $this->bahan, 'stok_sistem' => 100, 'stok_fisik' => $fisik, 'alasan' => 'hitung ulang']);
        StockOpnameService::eksekusiAdjustment($id);
    }

    public function test_opname_kurang_mengurangi_persediaan_dan_menjadi_beban_selisih(): void
    {
        $this->opname(92);

        $this->assertEquals(92, (float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('stok'));
        $this->assertEquals(92000, $this->saldo('1200'), 'nilai persediaan harus mengikuti stok fisik');
        $this->assertEquals(8000, $this->saldo('5230'));
        $this->seimbang();
    }

    public function test_opname_lebih_menambah_persediaan(): void
    {
        $this->opname(105);

        $this->assertEquals(105000, $this->saldo('1200'));
        $this->assertEquals(-5000, $this->saldo('5230'));
        $this->seimbang();
    }

    public function test_penyesuaian_manual_keluar_dan_set_stok_ikut_dijurnal(): void
    {
        PenyesuaianStokService::eksekusi(['id_bahan' => $this->bahan, 'tipe' => 'OUT', 'qty' => 10, 'keterangan' => 'rusak kena air']);
        $this->assertEquals(90000, $this->saldo('1200'));
        $this->assertEquals(10000, $this->saldo('5230'));

        PenyesuaianStokService::eksekusi(['id_bahan' => $this->bahan, 'tipe' => 'ADJUSTMENT', 'stok_baru' => 95, 'keterangan' => 'salah hitung']);
        $this->assertEquals(95000, $this->saldo('1200'));
        $this->assertEquals(5000, $this->saldo('5230'));
        $this->seimbang();
    }

    public function test_bahan_tanpa_harga_rata_rata_tidak_membuat_jurnal_nol(): void
    {
        DB::table('bahan_baku')->where('id_bahan', $this->bahan)->update(['harga_rata_rata' => 0]);

        $this->opname(90);

        $this->assertEquals(90, (float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('stok'));
        $this->assertSame(0, DB::table('jurnal_umum')->where('kode_akun', '5230')->count());
    }
}
