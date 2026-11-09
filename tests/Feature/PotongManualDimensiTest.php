<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\HargaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression lock: dimensi potong LINEAR_GRID wajib sampai ke database.
 *
 * Bug yang dikunci di sini: PenjualanController menghitung lebar_potong dan
 * tinggi_potong, memakainya untuk menghitung biaya, lalu menaruhnya di array
 * $daftarFinishing dengan komentar "Dimensi potong disimpan supaya nota/SPK
 * bisa menampilkan dan menghitung ulang Potong Manual apa adanya". Tapi INSERT
 * ke transaksi_detail_finishing tidak menulis kedua kolom itu, dan tabelnya
 * sendiri tidak punya kolom tersebut. Akibatnya hanya `biaya` yang bertahan.
 *
 * Tes-tes di bawah mengunci dua hal yang berbeda:
 *   1. Persistensi - dimensinya benar-benar tersimpan.
 *   2. Auditabilitas - biaya yang tersimpan bisa dihitung ULANG dari angka di
 *      database dan hasilnya sama persis. Inilah yang mustahil sebelum perbaikan
 *      ini, karena satu sisi rumus tidak ada sama sekali.
 */
class PotongManualDimensiTest extends TestCase
{
    use RefreshDatabase;

    private int $idProduk;

    private int $idKonsumen;

    private int $idGol;

    private int $idPotong;

    // 1000 x 1000 cm, potong 100 x 100 cm, tarif 450/meter.
    // kolom 10, baris 10 -> (9 x 1000 + 9 x 1000) / 100 = 180 meter per media.
    private const MEDIA = 1000.0;

    private const POTONG = 100.0;

    private const TARIFF_PER_METER = 450.0;

    private function siapkan(): User
    {
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        DB::table('roles')->where('id_role', $role->id_role)->update(['boleh_lihat_harga' => 1]);

        foreach ([3, 41] as $id) {
            DB::table('menus')->insertOrIgnore([
                'id_menu' => $id, 'nama_menu' => "M{$id}", 'url_link' => "m{$id}", 'parent_id' => 0,
            ]);
            DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => $id]);
        }

        $user = User::factory()->create(['id_role' => $role->id_role]);

        $this->idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail Uji', 'keterangan' => '-']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Konsumen Uji', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $this->idGol,
        ]);

        // metode_hitung 'quantity' supaya qtyHitung = qtyInput, bukan luas m2.
        $this->idProduk = DB::table('produk')->insertGetId([
            'nama_produk' => 'Media Uji Potong', 'metode_hitung' => 'quantity', 'status' => 'Aktif',
        ]);
        DB::table('setting_harga')->insert([
            'id_produk' => $this->idProduk, 'id_golongan' => $this->idGol, 'range_min' => 0.01, 'harga_jual' => 1000,
        ]);

        $this->idPotong = DB::table('finishing')->insertGetId([
            'nama_finishing' => 'Potong Manual Uji',
            'harga_default' => self::TARIFF_PER_METER,
            'metode_hitung' => 'LINEAR_GRID',
        ]);

        DB::table('kas_harian')->insert([
            'tgl_kas' => now()->toDateString(), 'id_user' => $user->id_user, 'saldo_awal' => 0, 'status_lock' => 'open',
        ]);

        return $user;
    }

    private function payload(array $finishing, int $qty = 2): array
    {
        return [
            'id_konsumen' => $this->idKonsumen,
            'id_golongan' => $this->idGol,
            'items' => [[
                'id_produk' => $this->idProduk,
                'nama_file' => 'uji.jpg',
                'p' => self::MEDIA,
                'l' => self::MEDIA,
                'qty_input' => $qty,
                'biaya_cetak' => 1000,
                'finishing' => $finishing,
            ]],
        ];
    }

    private function finishingLinearGrid(float $lebar, float $tinggi, float $biayaPalsu = 1): array
    {
        return [[
            'id_finishing' => $this->idPotong,
            'status_finishing' => 'Standar',
            'lebar_potong' => $lebar,
            'tinggi_potong' => $tinggi,
            'biaya' => $biayaPalsu,
        ]];
    }

    public function test_dimensi_potong_linear_grid_tersimpan_persis_seperti_dikirim(): void
    {
        $admin = $this->siapkan();

        $this->actingAs($admin)
            ->postJson('/penjualan/store', $this->payload($this->finishingLinearGrid(37.5, 62.25)))
            ->assertOk();

        $row = DB::table('transaksi_detail_finishing')->first();

        $this->assertNotNull($row, 'baris finishing harus tersimpan');

        // Inilah assertion yang gagal sebelum perbaikan: kedua kolom ini tidak
        // ada di tabel, jadi nilainya selalu null.
        $this->assertSame(37.5, (float) $row->lebar_potong, 'lebar_potong harus tersimpan apa adanya');
        $this->assertSame(62.25, (float) $row->tinggi_potong, 'tinggi_potong harus tersimpan apa adanya');
    }

    public function test_media_bukan_linear_grid_tetap_null_bukan_nol(): void
    {
        $admin = $this->siapkan();

        $idLaminasi = DB::table('finishing')->insertGetId([
            'nama_finishing' => 'Laminasi Uji', 'harga_default' => 15000, 'metode_hitung' => 'luas',
        ]);

        $this->actingAs($admin)->postJson('/penjualan/store', $this->payload([
            ['id_finishing' => $idLaminasi, 'status_finishing' => 'Standar', 'biaya' => 1],
        ]))->assertOk();

        $row = DB::table('transaksi_detail_finishing')->first();

        // NULL dan 0 dibedakan dengan sengaja: 0 berarti "Potong Manual dengan
        // dimensi tidak sah" (server menolak dengan 422), NULL berarti "mode ini
        // tidak berlaku untuk finishing tersebut".
        $this->assertNull($row->lebar_potong);
        $this->assertNull($row->tinggi_potong);
    }

    public function test_biaya_tersimpan_bisa_dihitung_ulang_purel_dari_data_di_database(): void
    {
        $admin = $this->siapkan();
        $qty = 3;

        $this->actingAs($admin)
            ->postJson('/penjualan/store', $this->payload($this->finishingLinearGrid(100, 100), $qty))
            ->assertOk();

        $fin = DB::table('transaksi_detail_finishing')->first();
        $det = DB::table('transaksi_detail')->first();

        // Sisipkan ULANG angka yang tersimpan ke service aslinya. Kalau
        // LebarPotong/TinggiPotong tidak ikut tersimpan, pemanggilan di bawah
        // akan menerima 0 dan menghasilkan 0 - bukan angka yang sama.
        $ulang = HargaService::hitungBiayaFinishingLinearGrid(
            $fin->id_finishing,
            (float) $det->p_ukuran,
            (float) $det->l_ukuran,
            (float) $fin->lebar_potong,
            (float) $fin->tinggi_potong,
            $qty
        );

        $this->assertGreaterThan(
            0.0,
            $ulang,
            'dimensi potong tidak tersimpan, jadi biaya tidak bisa ditelusuri ulang'
        );
        $this->assertEqualsWithDelta(
            (float) $fin->biaya,
            $ulang,
            0.01,
            'biaya tersimpan harus sama dengan hasil hitung ulang dari angka di database'
        );
    }

    public function test_biaya_linear_grid_masih_dihitung_server_bukan_dari_nominal_kiriman(): void
    {
        $admin = $this->siapkan();

        // Kirim nominal Rp1. Mode ini nominalnya turunan rumus, jadi nilai
        // kiriman harus diabaikan.
        $this->actingAs($admin)
            ->postJson('/penjualan/store', $this->payload($this->finishingLinearGrid(100, 100, 1), 2))
            ->assertOk();

        $row = DB::table('transaksi_detail_finishing')->first();

        // kolom 10, baris 10 -> 180 m/media; qty 2 -> 360 m x 450 = 162.000
        $this->assertSame(162000.0, (float) $row->biaya);
        $this->assertSame(162000.0, (float) DB::table('transaksi_detail')->value('biaya_finishing'));
    }

    public function test_dimensi_potong_tidak_valid_ditolak_dan_tidak_ada_baris_yang_tertinggal(): void
    {
        $admin = $this->siapkan();

        $respons = $this->actingAs($admin)
            ->postJson('/penjualan/store', $this->payload($this->finishingLinearGrid(0, 0)))
            ->assertStatus(422);

        $this->assertStringContainsString('tidak valid', (string) $respons->json('msg'));

        // Penolakan harus happened SEBELUM ada penulisan apa pun, supaya tidak
        // ada finishing tanpa dimensi yang menggantung di tabel.
        $this->assertSame(
            0,
            DB::table('transaksi_detail_finishing')->count(),
            'penolakan dimensi tidak valid tidak boleh meninggalkan baris finishing'
        );
    }
}