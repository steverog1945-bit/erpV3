<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PembandingHargaBahanTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_bahan_menampilkan_harga_beli_terakhir_dan_selisih_terhadap_rata_rata(): void
    {
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
        $idSup = DB::table('supplier')->insertGetId(['nama_supplier' => 'PT Uji', 'kontak' => '-']);
        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'D']);
        $idKat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $idGudang = DB::table('gudang')->insertGetId(['nama_gudang' => 'Utama']);
        $idBahan = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'PB1', 'nama_bahan' => 'Kertas Pembanding', 'id_mesin' => $idMesin, 'id_kategori' => $idKat, 'nilai_konversi' => 1, 'stok' => 5, 'harga_rata_rata' => 476000, 'satuan_stok' => 'rim', 'status' => 'aktif']);
        DB::table('bahan_baku')->insert(['kode_bahan' => 'PB2', 'nama_bahan' => 'Bahan Belum Dibeli', 'id_mesin' => $idMesin, 'id_kategori' => $idKat, 'nilai_konversi' => 1, 'stok' => 1, 'harga_rata_rata' => 1000, 'satuan_stok' => 'pcs', 'status' => 'aktif']);

        foreach ([['F-LAMA', '2026-08-01', 450000], ['F-BARU', '2026-09-01', 500000]] as [$no, $tgl, $harga]) {
            $idKed = DB::table('kedatangan_bahan')->insertGetId(['no_surat_jalan' => 'SJ-' . $no, 'id_supplier' => $idSup, 'id_gudang' => $idGudang, 'tgl_datang' => $tgl, 'status' => 'Selesai']);
            $idPb = DB::table('pembelian_bahan')->insertGetId(['id_kedatangan' => $idKed, 'no_faktur' => $no, 'tgl_transaksi' => $tgl, 'tipe_pembelian' => 'Cash', 'grand_total' => $harga]);
            DB::table('pembelian_bahan_detail')->insert(['id_pembelian' => $idPb, 'id_bahan' => $idBahan, 'qty' => 1, 'harga' => $harga, 'diskon' => 0, 'potongan' => 0, 'subtotal' => $harga]);
        }

        $res = $this->get('/bahan-baku')->assertOk();
        $res->assertSee('Harga Beli Terakhir')->assertSee('Rp 500.000')->assertSee('▲')->assertSee('5%')->assertSee('+Rp 24.000')->assertSee('Belum pernah dibeli');
    }
}
