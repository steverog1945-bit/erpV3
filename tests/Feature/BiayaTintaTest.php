<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\BiayaTintaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BiayaTintaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idMesin;
    private int $idBahan;
    private int $idProduk;
    private int $idKonsumen;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $role->id_role]);
        $this->actingAs($this->admin);

        $this->idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin Tinta', 'tipe_mesin' => 'Solvent']);
        $idKategori = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kat Tinta']);
        $liter = DB::table('satuan')->insertGetId(['nama_satuan' => 'liter', 'nilai_konversi' => 1]);
        $ml = DB::table('satuan')->insertGetId(['nama_satuan' => 'ml', 'nilai_konversi' => 1]);

        // Tinta: dibeli per liter (Rp 300.000), dipakai per ml (1 liter = 1000 ml).
        $this->idBahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'TNT-UJI', 'nama_bahan' => 'Tinta Uji', 'id_mesin' => $this->idMesin, 'id_kategori' => $idKategori,
            'id_satuan_stok' => $liter, 'id_satuan_hitung' => $ml, 'nilai_konversi' => 1000, 'stok' => 5, 'stok_minimal' => 1,
            'satuan_stok' => 'liter', 'harga_rata_rata' => 300000, 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'Banner Uji', 'id_mesin' => $this->idMesin, 'metode_hitung' => 'ukuran']);
        $idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Umum']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Uji', 'telepon' => '0811', 'alamat' => '-', 'id_golongan' => $idGol]);

        DB::table('pemakaian_tinta')->insert([
            'id_mesin' => $this->idMesin, 'id_bahan' => $this->idBahan, 'dasar' => 'm2', 'pemakaian' => 10, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function nota(string $nomor, string $status, float $p, float $l, int $qty, float $subtotal, ?string $tanggal = null): void
    {
        $idT = DB::table('transaksi')->insertGetId([
            'nomor_nota' => $nomor, 'tipe_order' => 'Reguler', 'id_konsumen' => $this->idKonsumen, 'id_user_kasir' => $this->admin->id_user,
            'tgl_order' => $tanggal ?? now(), 'total_bayar' => $subtotal, 'status_pembayaran' => 'Lunas', 'status_nota' => $status, 'status_collecting' => 'Belum Diambil',
        ]);
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $idT, 'id_produk' => $this->idProduk, 'nama_file' => "{$nomor}.pdf", 'p_ukuran' => $p, 'l_ukuran' => $l, 'qty' => $qty,
            'status_urgent' => 0, 'status_urgensi' => 'Normal', 'status_finishing' => 'Standar', 'subtotal' => $subtotal, 'potongan' => 0,
            'hpp_snapshot' => 0, 'harga_jual_snapshot' => $subtotal, 'status_spk' => 'Antre', 'biaya_cetak' => $subtotal, 'biaya_desain' => 0,
        ]);
    }

    public function test_biaya_tinta_dihitung_dari_luas_pakai_per_m2_konversi_satuan_dan_harga(): void
    {
        // 200 x 100 cm x 2 = 4 m2 -> 40 ml -> 0,04 liter x Rp 300.000 = Rp 12.000
        $this->nota('INV-T1', 'Aktif', 200, 100, 2, 400000);
        // Dibatalkan tidak dihitung.
        $this->nota('INV-T2', 'Dibatalkan', 100, 100, 5, 500000);

        $h = BiayaTintaService::hitung(now()->startOfMonth(), now());

        $this->assertEquals(4.0, $h['total_m2']);
        $this->assertEquals(12000.0, $h['total_biaya']);
        $this->assertEqualsWithDelta(40.0, $h['per_bahan'][0]->pakai, 0.0001);
        $this->assertEquals(400000.0, $h['total_omzet']);
        $this->assertEquals(12000.0, $h['per_produk'][0]->biaya_tinta);
        $this->assertEquals(3000.0, $h['per_produk'][0]->biaya_per_m2);
        $this->assertEquals(3.0, $h['per_produk'][0]->persen_omzet);
        $this->assertSame([], $h['bahan_tanpa_harga']);
    }

    public function test_periode_di_luar_rentang_tidak_dihitung_dan_bahan_tanpa_harga_diberi_peringatan(): void
    {
        $this->nota('INV-T3', 'Aktif', 100, 100, 1, 100000, now()->subMonths(2)->toDateString());
        DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->update(['harga_rata_rata' => 0]);
        $this->nota('INV-T4', 'Aktif', 100, 100, 1, 100000);

        $h = BiayaTintaService::hitung(now()->startOfMonth(), now());

        $this->assertEquals(1.0, $h['total_m2']);
        $this->assertEquals(0.0, $h['total_biaya']);
        $this->assertSame(['Tinta Uji'], $h['bahan_tanpa_harga']);
    }

    public function test_halaman_laporan_simpan_dan_hapus_pengaturan(): void
    {
        $this->nota('INV-T5', 'Aktif', 200, 100, 2, 400000);

        $this->get('/laporan/biaya-tinta')->assertOk()->assertSee('Biaya Tinta')->assertSee('Tinta Uji')->assertSee('Rp 12.000');

        $this->post('/laporan/biaya-tinta/pengaturan', [
            'id_mesin' => $this->idMesin, 'id_bahan' => $this->idBahan, 'dasar' => 'm2', 'pemakaian' => 20, 'keterangan' => 'ukur riil',
        ])->assertSessionHas('success');
        $this->assertEquals(1, DB::table('pemakaian_tinta')->count());
        $this->assertEquals(20, (float) DB::table('pemakaian_tinta')->value('pemakaian'));

        $this->post('/laporan/biaya-tinta/pengaturan', ['id_mesin' => $this->idMesin, 'id_bahan' => $this->idBahan, 'dasar' => 'm2', 'pemakaian' => -1])->assertSessionHasErrors('pemakaian');

        $id = DB::table('pemakaian_tinta')->value('id');
        $this->delete("/laporan/biaya-tinta/pengaturan/{$id}")->assertSessionHas('success');
        $this->assertEquals(0, DB::table('pemakaian_tinta')->count());
    }

    public function test_akses_mengikuti_menu_hpp_dan_role_lain_ditolak(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Gudang']);
        $gudang = User::factory()->create(['id_role' => $role->id_role]);

        $this->actingAs($gudang)->get('/laporan/biaya-tinta')->assertForbidden();

        DB::table('menus')->insertOrIgnore(['id_menu' => 47, 'nama_menu' => 'Laporan HPP', 'url_link' => 'laporan/hpp', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => 47]);

        $this->actingAs($gudang)->get('/laporan/biaya-tinta')->assertOk();
    }
}
