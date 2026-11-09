<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ganti pelanggan saat keranjang sudah berisi.
 *
 * Kenapa ini dijaga lewat test:
 *
 * Harga produk memakai tier per golongan konsumen, jadi mengganti pelanggan
 * berarti tarifnya berubah - tapi BARANG dan UKURAN item keranjang tidak
 * berubah. Versi lama membuang seluruh keranjang (cart = []) setiap kali
 * data pelanggan/golongan berubah, jadi salah pilih pelanggan menghapus
 * semua pekerjaan kasir.
 *
 * Perilaku yang harus bertahan:
 *   - Keranjang TIDAK dikosongkan.
 *   - Biaya cetak item yang harganya dari tarif ikut dihitung ulang.
 *   - Biaya cetak yang diketik manual kasir TIDAK ditimpa (harga yang
 *     disepakati bukan hasil tarif).
 *   - Jalur "modal Konsumen Baru" juga ikut, kalau tidak tarifnya diam-diam
 *     tetap milik golongan lama.
 *
 * Perilaku di sisi server (PenjualanController::store memakai biaya_cetak
 * dari client apa adanya) tidak berubah - test ini menjaga sisi kliennya
 * saja, dan alasannya dijelaskan di komentar fungsi hitungUlangHargaKeranjang().
 */
class PenjualanGantiPelangganTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();

        AppSetting::clearCache();

        $role = Role::factory()->create(['nama_role' => 'Kasir']);
        $this->kasir = User::factory()->create(['id_role' => $role->id_role]);

        DB::table('menus')->insertOrIgnore(['id_menu' => 41, 'nama_menu' => 'Input Order', 'url_link' => 'penjualan/kasir', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => 41]);
    }

    private function halamanKasir(): string
    {
        return $this->actingAs($this->kasir)
            ->get(route('penjualan.create'))
            ->assertOk()
            ->getContent();
    }

    public function test_keranjang_tidak_lagi_dikosongkan_saat_data_pelanggan_berubah(): void
    {
        $html = $this->halamanKasir();

        // handleIntegritasData() dipanggil dari handler perubahan
        // pelanggan DAN perubahan golongan. Isinya dulu `cart = []`.
        $this->assertStringContainsString('function handleIntegritasData()', $html);

        // Ekstrak isi fungsi dan pastikan TIDAK lagi mengosongkan keranjang.
        $awal = strpos($html, 'function handleIntegritasData()');
        $ini = substr($html, $awal, 400);

        $this->assertStringContainsString('jalankanHitungUlangHarga()', $ini);
        $this->assertStringNotContainsString(
            'cart = []',
            $ini,
            'Ganti pelanggan tidak boleh lagi membuang isi keranjang.'
        );

        // Pesan lama "keranjang dikosongkan karena..." harus hilang total.
        $this->assertStringNotContainsString(
            'Keranjang dikosongkan karena perubahan data pelanggan/harga',
            $html,
            'Notifikasi pengosongan keranjang masih ada - berarti regresi.'
        );
    }

    public function test_fungsi_hitung_ulang_harga_ada_dan_membaca_golongan(): void
    {
        $html = $this->halamanKasir();

        $this->assertStringContainsString('async function hitungUlangHargaKeranjang()', $html);

        $awal = strpos($html, 'async function hitungUlangHargaKeranjang()');
        $ini = substr($html, $awal, 2600);

        // Sumber tarif baru = golongan yang sedang aktif.
        $this->assertStringContainsString("document.getElementById('id_golongan').value", $ini);

        // Hanya biaya cetak yang dihitung ulang; desain dan finishing tidak,
        // karena keduanya tidak terikat golongan.
        $this->assertStringContainsString('item.biaya_cetak = Math.round(harga * item.qty_hitung);', $ini);
        $this->assertStringContainsString('(item.biaya_desain || 0)', $ini);
        $this->assertStringContainsString('(item.biaya_finishing || 0)', $ini);

        // Harga manual dikecualikan, item jasa dilewati.
        $this->assertStringContainsString('if (item.cetak_manual)', $ini);
        $this->assertStringContainsString('if (item.is_jasa) continue;', $ini);
    }

    public function test_harga_manual_per_item_ikut_disimpan_ke_keranjang(): void
    {
        $html = $this->halamanKasir();

        $this->assertStringContainsString(
            'cetak_manual: cetakManualEdit,',
            $html,
            'Status harga manual harus ikut tersimpan per item, bukan jadi flag global.'
        );
    }

    public function test_ada_penjaga_agar_tidak_hitung_ulang_berulang(): void
    {
        $html = $this->halamanKasir();

        // Satu pergantian pelanggan memicu tiga handler hampir bersamaan.
        // Tanpa penjaga, satu penggantian = 3x request per item.
        $this->assertStringContainsString('let hitungUlangBerjalan = false;', $html);
        $this->assertStringContainsString('hitungUlangMenunggu = true;', $html);
    }

    public function test_jalur_konsumen_baru_juga_menghitung_ulang_harga(): void
    {
        $html = $this->halamanKasir();

        // Jalur modal hanya memicu 'change', tidak 'select2:select'. Kalau
        // tidak dipanggil eksplisit, harga keranjang tetap milik golongan lama
        // lalu nota disimpan dengan angka itu.
        $awal = strpos($html, "append(newOption).trigger('change')");
        $this->assertNotFalse($awal, 'Blok Konsumen Baru tidak ditemukan.');

        $setelah = substr($html, $awal, 1400);
        $this->assertStringContainsString(
            'jalankanHitungUlangHarga()',
            $setelah,
            'Jalur Konsumen Baru harus memanggil hitung ulang harga secara eksplisit.'
        );
    }

    public function test_endpoint_harga_menerima_golongan_sehingga_rekalkulasi_bisa_berbeda(): void
    {
        // Dasar dari seluruh fitur ini: tarif memang bergantung golongan.
        $res = $this->actingAs($this->kasir)->get('/api/penjualan/get-harga/1/1/1');
        $res->assertOk()->assertJsonStructure(['harga_jual']);
    }
}
