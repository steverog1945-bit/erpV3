<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Potong Manual untuk mesin KONICA MINOLTA (kategori A3).
 *
 * Yang diuji:
 *  - Master "Potong Manual A3" terdaftar, metode LINEAR_GRID, tarif sama
 *    dengan Potong Manual yang sudah ada (Rp 450/meter).
 *  - Produk A3 (dijual per lembar, metode_hitung 'quantity') TIDAK punya
 *    Panjang/Lebar di form, jadi ukuran media dikirim terpisah lewat
 *    lebar_media/tinggi_media - tanpa itu rumus potong tidak punya sisi
 *    media dan biaya tidak bisa dihitung.
 *  - Potongan BERDIMENSI KECIL tetap ikut rumus (tidak ada ambang minimum
 *    ukuran yang memaksa biaya jadi minimum charge).
 *  - Spek "Custom" pada role yang berhak mengubah harga = harga potong
 *    diisi manual, dan angkanya dipakai apa adanya.
 *  - Role tanpa hak ubah harga TIDAK bisa memaksa harga manual.
 *  - Ukuran media ikut tersimpan supaya biaya bisa ditelusuri ulang.
 */
class PotongManualA3Test extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $idProduk;

    private int $idKonsumen;

    private int $idGolongan;

    private int $idFinishing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
        DB::table('roles')->where('nama_role', 'Administrator')->update(['boleh_lihat_harga' => true]);

        $this->idGolongan = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Konsumen A3', 'telepon' => '0812', 'alamat' => '-', 'id_golongan' => $this->idGolongan,
        ]);

        // Produk A3: dijual per lembar (metode_hitung quantity) - TIDAK punya
        // Panjang/Lebar di form Input Pesanan.
        $this->idProduk = DB::table('produk')->insertGetId([
            'nama_produk' => 'HVS A3 Uji', 'metode_hitung' => 'quantity', 'status' => 'Aktif',
        ]);
        DB::table('setting_harga')->insert([
            'id_produk' => $this->idProduk, 'id_golongan' => $this->idGolongan,
            'range_min' => 0, 'range_max' => null, 'harga_jual' => 5000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Order baru hanya boleh dibuat saat kasir sudah "buka toko" hari ini.
        DB::table('kas_harian')->insert([
            'tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user,
            'saldo_awal' => 0, 'status_lock' => 'open',
        ]);

        // Baris master dibuat migration 2026_11_06_090000_potong_manual_a3.php.
        $this->idFinishing = (int) DB::table('finishing')->where('nama_finishing', 'Potong Manual A3')->value('id_finishing');

        // Tier harga wajib untuk Potong Manual A3 (validasi server-side).
        // Tambahkan tier default agar finishing bisa dipakai di test.
        if (!DB::table('finishing_harga')->where('id_finishing', $this->idFinishing)->exists()) {
            DB::table('finishing_harga')->insert([
                'id_finishing' => $this->idFinishing,
                'range_min' => 1,
                'range_max' => null,
                'harga' => 450,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function payload(array $finishing): array
    {
        return [
            'id_konsumen' => $this->idKonsumen,
            'id_golongan' => $this->idGolongan,
            'items' => [[
                'id_produk' => $this->idProduk,
                'nama_file' => 'a3.pdf',
                'p' => 0, 'l' => 0, 'qty_input' => 1,
                'biaya_cetak' => 5000,
                'biaya_desain' => 0,
                'finishing' => [$finishing],
            ]],
        ];
    }

    public function test_master_potong_manual_a3_terdaftar_dengan_metode_dan_tarif_yang_benar(): void
    {
        $f = DB::table('finishing')->where('id_finishing', $this->idFinishing)->first();

        $this->assertNotNull($f, 'Potong Manual A3 harus ada di master finishing');
        $this->assertSame('LINEAR_GRID', $f->metode_hitung);
        $this->assertEquals(450.0, (float) $f->harga_default);
    }

    public function test_biaya_potong_a3_dihitung_dari_ukuran_media_yang_dikirim(): void
    {
        // Media 100 x 100 cm, potong 10 x 10 cm, qty 1.
        //   kolom = floor(100/10) = 10, baris = floor(100/10) = 10
        //   garis vertikal = 9 (panjang 100 cm), garis horizontal = 9 (panjang 100 cm)
        //   meter = (9*100 + 9*100)/100 = 18 meter
        //   biaya = 18 * 450 = 8.100
        $r = $this->actingAs($this->admin)->postJson('/penjualan/store', $this->payload([
            'id_finishing' => $this->idFinishing,
            'status_finishing' => 'Standar',
            'biaya' => 999999999,
            'lebar_potong' => 10, 'tinggi_potong' => 10,
            'lebar_media' => 100, 'tinggi_media' => 100,
        ]));

        $r->assertOk();

        $detail = DB::table('transaksi_detail')->first();
        $this->assertEquals(8100.0, (float) $detail->biaya_finishing);

        $fin = DB::table('transaksi_detail_finishing')->where('id_detail', $detail->id_detail)->first();
        $this->assertEquals(10.0, (float) $fin->lebar_potong);
        $this->assertEquals(10.0, (float) $fin->tinggi_potong);
        $this->assertEquals(100.0, (float) $fin->lebar_media);
        $this->assertEquals(100.0, (float) $fin->tinggi_media);
    }

    public function test_potong_dimensi_kecil_tetap_ikut_rumus_bukan_minimum_charge(): void
    {
        // Media 100 x 100 cm, potong 2 x 2 cm, qty 1.
        //   kolom 50, baris 50 -> garis 49 + 49
        //   meter = (49*100 + 49*100)/100 = 98 meter
        //   biaya = 98 * 450 = 44.100 (jauh di atas minimum charge 5.000,
        //   jadi angka ini BUKAN hasil minimum charge)
        $this->actingAs($this->admin)->postJson('/penjualan/store', $this->payload([
            'id_finishing' => $this->idFinishing,
            'status_finishing' => 'Standar',
            'lebar_potong' => 2, 'tinggi_potong' => 2,
            'lebar_media' => 100, 'tinggi_media' => 100,
        ]))->assertOk();

        $detail = DB::table('transaksi_detail')->first();
        $this->assertEquals(44100.0, (float) $detail->biaya_finishing);
    }

    /** Minimum charge diambil dari master finishing, dan bisa diubah operator. */
    public function test_minimum_charge_bersumber_dari_master_dan_bisa_diubah(): void
    {
        // Master default-nya 5.000 (dari migration), media 100 x 100 potong
        // 10 x 10 -> 18 m x 450 = 8.100 (di atas minimum, jadi minimum
        // tidak terlihat). Turunkan minimum jadi 0 supaya bedanya jelas.
        DB::table('finishing')->where('id_finishing', $this->idFinishing)->update(['minimum_charge' => 0]);

        $this->actingAs($this->admin)->postJson('/penjualan/store', $this->payload([
            'id_finishing' => $this->idFinishing,
            'status_finishing' => 'Standar',
            'lebar_potong' => 10, 'tinggi_potong' => 10,
            'lebar_media' => 100, 'tinggi_media' => 100,
        ]))->assertOk();

        $detail = DB::table('transaksi_detail')->first();
        $this->assertEquals(8100.0, (float) $detail->biaya_finishing);
    }

    /** Minimum charge 5.000 masih berlaku saat potongannya kecil. */
    public function test_minimum_charge_master_berlaku_saat_potongannya_kecil(): void
    {
        // Media 100 x 100, potong 30 x 30 -> kolom 3, baris 3 -> 2x100+2x100
        // = 4 m x 450 = 1.800, jadi minimum charge 5.000 yang dipakai.
        $this->actingAs($this->admin)->postJson('/penjualan/store', $this->payload([
            'id_finishing' => $this->idFinishing,
            'status_finishing' => 'Standar',
            'lebar_potong' => 30, 'tinggi_potong' => 30,
            'lebar_media' => 100, 'tinggi_media' => 100,
        ]))->assertOk();

        $detail = DB::table('transaksi_detail')->first();
        $this->assertEquals(2000.0, (float) $detail->biaya_finishing, 'minimum charge dari master harus dipakai');
    }

    public function test_spek_custom_mengizinkan_harga_potong_manual(): void    {
        $this->actingAs($this->admin)->postJson('/penjualan/store', $this->payload([
            'id_finishing' => $this->idFinishing,
            'status_finishing' => 'Custom',
            'biaya' => 7777,
            'lebar_potong' => 10, 'tinggi_potong' => 10,
            'lebar_media' => 100, 'tinggi_media' => 100,
        ]))->assertOk();

        $detail = DB::table('transaksi_detail')->first();
        $this->assertEquals(7777.0, (float) $detail->biaya_finishing, 'harga manual (Custom) harus dipakai apa adanya');

        $fin = DB::table('transaksi_detail_finishing')->where('id_detail', $detail->id_detail)->first();
        $this->assertSame('Custom', $fin->status_finishing);
    }

    public function test_role_tanpa_hak_ubah_harga_tidak_bisa_memaksa_harga_potong_manual(): void
    {
        $rDesain = Role::factory()->create(['nama_role' => 'Operator Desain']);
        DB::table('roles')->where('id_role', $rDesain->id_role)->update(['boleh_lihat_harga' => false, 'boleh_input_biaya_desain' => true]);
        $desain = User::factory()->create(['id_role' => $rDesain->id_role]);
        // Izin menu "Input Order" (41) supaya role ini boleh membuat order.
        DB::table('menus')->insertOrIgnore(['id_menu' => 41, 'nama_menu' => 'Input Order', 'url_link' => 'm41', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $rDesain->id_role, 'id_menu' => 41, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 0, 'can_delete' => 0]);

        // Role ini tidak boleh melihat harga => harga potong WAJIB dihitung
        // server dari rumus, angka dari browser diabaikan.
        $this->actingAs($desain)->postJson('/penjualan/store', $this->payload([
            'id_finishing' => $this->idFinishing,
            'status_finishing' => 'Custom',
            'biaya' => 7777,
            'lebar_potong' => 10, 'tinggi_potong' => 10,
            'lebar_media' => 100, 'tinggi_media' => 100,
        ]))->assertOk();

        $detail = DB::table('transaksi_detail')->first();
        $this->assertEquals(8100.0, (float) $detail->biaya_finishing, 'role tanpa hak ubah harga harus dapat hitungan rumus');
    }

    public function test_media_tanpa_ukuran_ditolak(): void
    {
        $r = $this->actingAs($this->admin)->postJson('/penjualan/store', $this->payload([
            'id_finishing' => $this->idFinishing,
            'status_finishing' => 'Standar',
            'lebar_potong' => 10, 'tinggi_potong' => 10,
            'lebar_media' => 0, 'tinggi_media' => 0,
        ]));

        $r->assertStatus(422);
        $this->assertStringContainsString('Potong Manual', $r->json('msg'));
    }

    public function test_potong_pada_produk_ukuran_mengambil_media_dari_panjang_lebar_item(): void
    {
        $produkRoll = DB::table('produk')->insertGetId([
            'nama_produk' => 'Roll Uji', 'metode_hitung' => 'ukuran', 'status' => 'Aktif',
        ]);
        DB::table('setting_harga')->insert([
            'id_produk' => $produkRoll, 'id_golongan' => $this->idGolongan,
            'range_min' => 0, 'range_max' => null, 'harga_jual' => 50000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Media 100 x 100 cm (dari p & l), potong 10 x 10 cm.
        //   meter = 18 -> 8.100
        $r = $this->actingAs($this->admin)->postJson('/penjualan/store', [
            'id_konsumen' => $this->idKonsumen,
            'id_golongan' => $this->idGolongan,
            'items' => [[
                'id_produk' => $produkRoll,
                'nama_file' => 'roll.pdf',
                'p' => 100, 'l' => 100, 'qty_input' => 1,
                'biaya_cetak' => 50000,
                'biaya_desain' => 0,
                'finishing' => [[
                    'id_finishing' => $this->idFinishing,
                    'status_finishing' => 'Standar',
                    'lebar_potong' => 10, 'tinggi_potong' => 10,
                ]],
            ]],
        ]);

        $r->assertOk();

        $detail = DB::table('transaksi_detail')->first();
        $this->assertEquals(8100.0, (float) $detail->biaya_finishing);
    }
}
