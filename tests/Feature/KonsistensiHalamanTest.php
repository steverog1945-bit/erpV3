<?php

namespace Tests\Feature;

use App\Models\JerseyOrder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Temuan konsistensi: pencarian Finishing, tanggal pesan jersey, lebar halaman Penjualan dan Jasa, gaya Kelola Kas. */
class KonsistensiHalamanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
    }

    public function test_finishing_bisa_dicari_dan_disaring(): void
    {
        foreach ([['Uji Lipat Tepi', 'keliling'], ['Uji Laminasi Kilap', 'luas'], ['Uji Pasang Ring', 'quantity']] as [$nama, $metode]) {
            DB::table('finishing')->insert(['nama_finishing' => $nama, 'harga_default' => 1000, 'metode_hitung' => $metode]);
        }

        $this->get('/finishing?search=kilap')->assertOk()->assertSee('Cari Finishing')->assertSee('Uji Laminasi Kilap')->assertDontSee('Uji Lipat Tepi');
        $this->get('/finishing?metode_hitung=quantity')->assertOk()->assertSee('Uji Pasang Ring')->assertDontSee('Uji Laminasi Kilap');
        $this->get('/finishing?search=tidakada')->assertOk()->assertSee('Tidak ada finishing yang cocok');
    }

    public function test_daftar_pesanan_dan_keuangan_jersey_menampilkan_tanggal_pesan(): void
    {
        JerseyOrder::create(['order_number' => 'JO-TGL-1', 'order_date' => '2026-09-14', 'customer_name' => 'Tim Garuda', 'total' => 100000]);

        $this->get('/jersey')->assertOk()->assertSee('Tgl Pesan')->assertSee('14 Sep 2026');
        $this->get('/jersey/keuangan')->assertOk()->assertSee('Tgl Pesan')->assertSee('14 Sep 2026');
    }

    public function test_penjualan_dan_jasa_memakai_lebar_halaman_standar(): void
    {
        $html = $this->get('/penjualan-barang')->assertOk()->getContent();

        $this->assertStringContainsString('max-w-[1600px]', $html);
        $this->assertStringNotContainsString('max-w-4xl mx-auto', $html);
    }

    public function test_kelola_kas_memakai_header_standar_tanpa_italic(): void
    {
        $html = $this->get('/kasir/kelola-kas')->assertOk()->getContent();

        $this->assertStringContainsString('Kelola Kas Harian', $html);
        $this->assertStringNotContainsString('italic', $html);
        $this->assertStringNotContainsString('rounded-[2rem]', $html);
    }
}
