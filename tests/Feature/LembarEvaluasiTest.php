<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\LembarEvaluasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LembarEvaluasiTest extends TestCase
{
    use RefreshDatabase;

    private int $idProdukUkuran;
    private int $idProdukQty;
    private int $idGolRetail;
    private int $idGolReseller;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->actingAs(User::factory()->create(['id_role' => $role->id_role]));

        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin Eval', 'tipe_mesin' => 'Solvent']);
        $kategori = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kat Eval']);
        $roll = DB::table('satuan')->insertGetId(['nama_satuan' => 'roll', 'nilai_konversi' => 1]);
        $m2 = DB::table('satuan')->insertGetId(['nama_satuan' => 'm2', 'nilai_konversi' => 1]);
        $pcs = DB::table('satuan')->insertGetId(['nama_satuan' => 'pcs', 'nilai_konversi' => 1]);

        // Roll 50 m2, Rp 500.000 per roll -> Rp 10.000 per m2.
        $roll50 = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'B-ROLL', 'nama_bahan' => 'Vinyl Uji', 'id_mesin' => $mesin, 'id_kategori' => $kategori,
            'id_satuan_stok' => $roll, 'id_satuan_hitung' => $m2, 'nilai_konversi' => 50, 'stok' => 2, 'stok_minimal' => 3,
            'satuan_stok' => 'roll', 'harga_rata_rata' => 500000, 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $kosong = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'B-KOSONG', 'nama_bahan' => 'Tinta Tanpa Harga', 'id_mesin' => $mesin, 'id_kategori' => $kategori,
            'id_satuan_stok' => $pcs, 'id_satuan_hitung' => $pcs, 'nilai_konversi' => 1, 'stok' => 10, 'stok_minimal' => 1,
            'satuan_stok' => 'pcs', 'harga_rata_rata' => 0, 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->idProdukUkuran = DB::table('produk')->insertGetId(['nama_produk' => 'Banner Uji', 'id_mesin' => $mesin, 'metode_hitung' => 'ukuran', 'status' => 'aktif']);
        $this->idProdukQty = DB::table('produk')->insertGetId(['nama_produk' => 'Stiker Uji', 'id_mesin' => $mesin, 'metode_hitung' => 'quantity', 'status' => 'aktif']);

        DB::table('bom_detail')->insert([
            ['id_produk' => $this->idProdukUkuran, 'id_bahan' => $roll50, 'cara_hitung_pemakaian' => '(P*L)/10000'],
            ['id_produk' => $this->idProdukQty, 'id_bahan' => $kosong, 'cara_hitung_pemakaian' => 'Q'],
        ]);

        DB::table('golongan_konsumen')->delete();
        $this->idGolRetail = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $this->idGolReseller = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Reseller']);

        foreach ([[$this->idGolRetail, 1, 9.99, 25000], [$this->idGolRetail, 10, null, 20000], [$this->idGolReseller, 1, 9.99, 22000], [$this->idGolReseller, 10, null, 18000]] as [$gol, $min, $max, $harga]) {
            DB::table('setting_harga')->insert(['id_produk' => $this->idProdukUkuran, 'id_golongan' => $gol, 'range_min' => $min, 'range_max' => $max, 'harga_jual' => $harga]);
        }
    }

    public function test_hpp_bom_dihitung_per_satuan_jual_dengan_konversi_satuan(): void
    {
        $bom = LembarEvaluasiService::bom()->keyBy('nama_produk');

        // 1 m2 = 1/50 roll x Rp 500.000 = Rp 10.000
        $this->assertEquals(10000.0, $bom['Banner Uji']->hpp);
        $this->assertSame('m²', $bom['Banner Uji']->satuan_jual);
        $this->assertEquals(1.0, $bom['Banner Uji']->baris[0]->pakai);
        $this->assertFalse($bom['Banner Uji']->ada_harga_kosong);

        $this->assertSame('pcs', $bom['Stiker Uji']->satuan_jual);
        $this->assertTrue($bom['Stiker Uji']->ada_harga_kosong);
    }

    public function test_harga_dikelompokkan_per_tier_dan_golongan_dengan_margin(): void
    {
        $h = LembarEvaluasiService::harga();
        $banner = $h['produk']->firstWhere('nama_produk', 'Banner Uji');

        $this->assertCount(2, $banner->tier);
        $this->assertEquals(1.0, $banner->tier[0]->min);
        $this->assertEquals(25000.0, $banner->tier[0]->per_golongan[$this->idGolRetail]);
        $this->assertEquals(18000.0, $banner->tier[1]->per_golongan[$this->idGolReseller]);
        $this->assertNull($banner->tier[1]->max);
        $this->assertEquals(60.0, LembarEvaluasiService::margin(25000, $banner->hpp));
        $this->assertNull(LembarEvaluasiService::margin(25000, 0));
    }

    public function test_bahan_baku_menghitung_nilai_persediaan_dan_stok_menipis(): void
    {
        $bahan = LembarEvaluasiService::bahanBaku()->keyBy('kode_bahan');

        $this->assertEquals(1000000.0, $bahan['B-ROLL']->nilai);
        $this->assertTrue($bahan['B-ROLL']->menipis);
        $this->assertEquals(0.0, $bahan['B-KOSONG']->nilai);
    }

    public function test_halaman_menampilkan_semua_bagian_dan_filter_jenis(): void
    {
        $this->get('/laporan/evaluasi-master')->assertOk()
            ->assertSee('Lembar Evaluasi Harian')->assertSee('A. Bahan Baku')->assertSee('B. BOM per Produk')->assertSee('C. Harga Produk')
            ->assertSee('Vinyl Uji')->assertSee('Banner Uji')->assertSee('Stok Fisik');

        $this->get('/laporan/evaluasi-master?jenis=harga&periode=bulanan&cek_fisik=0')->assertOk()
            ->assertSee('Lembar Evaluasi Bulanan')->assertSee('C. Harga Produk')->assertDontSee('A. Bahan Baku')->assertDontSee('Stok Fisik');
    }

    public function test_unduh_csv_untuk_setiap_jenis(): void
    {
        foreach (['bahan' => 'Vinyl Uji', 'bom' => 'Banner Uji', 'harga' => 'Reseller'] as $jenis => $isi) {
            $res = $this->get("/laporan/evaluasi-master?jenis={$jenis}&format=csv");
            $res->assertOk();
            $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
            $this->assertStringContainsString($isi, $res->streamedContent());
        }
    }

    public function test_akses_hanya_untuk_menu_hpp(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Gudang']);
        $gudang = User::factory()->create(['id_role' => $role->id_role]);

        $this->actingAs($gudang)->get('/laporan/evaluasi-master')->assertForbidden();
    }
}
