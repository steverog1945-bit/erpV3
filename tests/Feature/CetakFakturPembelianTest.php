<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CetakFakturPembelianTest extends TestCase
{
    use RefreshDatabase;

    private function data(): int
    {
        $idSup = DB::table('supplier')->insertGetId(['nama_supplier' => 'PT Uji', 'kontak' => '-']);
        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin Uji', 'tipe_mesin' => 'Digital']);
        $idKat = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kategori Uji']);
        $idGudang = DB::table('gudang')->insertGetId(['nama_gudang' => 'Utama']);
        $idBahan = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'UJI1', 'nama_bahan' => 'Kertas Uji', 'satuan_stok' => 'rim', 'id_mesin' => $idMesin, 'id_kategori' => $idKat, 'nilai_konversi' => 500, 'stok' => 0, 'harga_rata_rata' => 0, 'status' => 'aktif']);
        $idKed = DB::table('kedatangan_bahan')->insertGetId([
            'no_surat_jalan' => 'SJ-UJI', 'id_supplier' => $idSup, 'id_gudang' => $idGudang, 'tgl_datang' => now()->toDateString(), 'status' => 'Selesai',
            'keterangan' => "PENERIMAAN BARANG FISIK:\n1. Kertas Uji (3 rim)\n\nNB : Barang Lengkap!",
        ]);
        $idPb = DB::table('pembelian_bahan')->insertGetId([
            'id_kedatangan' => $idKed, 'no_faktur' => 'INV/UJI/1', 'tgl_transaksi' => now()->toDateString(), 'tipe_pembelian' => 'Cash', 'grand_total' => 1350000,
        ]);
        DB::table('pembelian_bahan_detail')->insert(['id_pembelian' => $idPb, 'id_bahan' => $idBahan, 'qty' => 3, 'harga' => 460000, 'diskon' => 30000, 'potongan' => 0, 'subtotal' => 1350000]);

        return $idPb;
    }

    public function test_cetak_faktur_punya_pilihan_a4_dan_continuous_form_dengan_ukuran_kertas_masing_masing(): void
    {
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
        $id = $this->data();

        $a4 = $this->get("/pembelian-bahan/{$id}/cetak/a4")->assertOk();
        $a4->assertSee('size: A4', false)->assertSee('Faktur Pembelian Bahan')->assertSee('INV/UJI/1')->assertSee('Continuous Form')->assertSee('1.350.000');

        $cf = $this->get("/pembelian-bahan/{$id}/cetak/continuous")->assertOk();
        $cf->assertSee('size: 241mm 279mm', false)->assertSee('Courier New', false);

        // Format tak dikenal -> jatuh ke A4 / format global; halaman detail menampilkan pilihannya.
        $this->get("/pembelian-bahan/{$id}/cetak/thermal")->assertOk()->assertSee('size: A4', false);
        $this->get("/pembelian-bahan/{$id}")->assertOk()->assertSee('Kertas A4')->assertSee('Continuous Form');
    }

    public function test_daftar_kedatangan_menampilkan_keterangan_utuh(): void
    {
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
        $this->data();

        $this->get('/kedatangan-bahan')->assertOk()->assertSee('1. Kertas Uji (3 rim)')->assertSee('NB : Barang Lengkap!');
    }
}
