<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WasteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idBahan;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $role->id_role]);
        $this->actingAs($this->admin);

        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin', 'tipe_mesin' => 'Digital']);
        $idKategori = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kategori']);
        $this->idBahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'BHN-1', 'nama_bahan' => 'Flexi Korea', 'stok' => 50, 'harga_rata_rata' => 20000, 'nilai_konversi' => 1,
            'id_mesin' => $idMesin, 'id_kategori' => $idKategori, 'satuan_stok' => 'm2', 'status' => 'aktif',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function stok(): float
    {
        return (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('stok');
    }

    private function saldo(string $kodeAkun): float
    {
        return (float) DB::table('jurnal_umum')->where('kode_akun', $kodeAkun)->sum(DB::raw('debet - kredit'));
    }

    public function test_waste_normal_langsung_mengurangi_stok_dan_menjurnal(): void
    {
        $this->post('/waste', ['id_bahan' => $this->idBahan, 'qty' => 3, 'jenis' => 'Normal', 'alasan' => 'Sisa potongan'])
            ->assertRedirect(route('waste.index'));

        $this->assertEquals(47, $this->stok());
        $w = DB::table('waste_log')->first();
        $this->assertEquals(['Diterapkan', 60000.0], [$w->status, (float) $w->biaya]); // 3 x 20.000
        $this->assertStringStartsWith('WS-', $w->nomor);
        $this->assertDatabaseHas('kartu_stok', ['id_bahan' => $this->idBahan, 'tipe' => 'OUT', 'qty' => 3, 'referensi' => $w->nomor]);
        $this->assertEquals(60000, $this->saldo('5240'));
        $this->assertEquals(0, $this->saldo('5200'), 'waste tidak boleh tercampur dengan Biaya Operasional Kantor');
        $this->assertEquals(-60000, $this->saldo('1200'));
        $this->assertEquals(0, ApprovalRequest::count());
    }

    public function test_waste_abnormal_hanya_diajukan_dan_stok_berubah_setelah_disetujui(): void
    {
        $this->post('/waste', ['id_bahan' => $this->idBahan, 'qty' => 5, 'jenis' => 'Abnormal', 'alasan' => 'Basah kena air']);

        $this->assertEquals(50, $this->stok());
        $this->assertEquals('Menunggu', DB::table('waste_log')->value('status'));

        $req = ApprovalRequest::where('tipe_aksi', 'waste_abnormal')->firstOrFail();
        $this->patch("/approval/{$req->id}/approve", ['catatan' => 'OK']);

        $this->assertEquals(45, $this->stok());
        $this->assertEquals('Diterapkan', DB::table('waste_log')->value('status'));
        $this->assertEquals(100000, $this->saldo('5240'));
    }

    public function test_waste_abnormal_ditolak_tidak_mengubah_stok(): void
    {
        $this->post('/waste', ['id_bahan' => $this->idBahan, 'qty' => 5, 'jenis' => 'Abnormal', 'alasan' => 'Hilang']);
        $req = ApprovalRequest::where('tipe_aksi', 'waste_abnormal')->firstOrFail();

        $this->patch("/approval/{$req->id}/reject", ['catatan' => 'Cari dulu']);

        $this->assertEquals('Ditolak', DB::table('waste_log')->value('status'));
        $this->assertEquals(50, $this->stok());
        $this->assertEquals(0, DB::table('jurnal_umum')->count());
    }

    public function test_stok_tidak_cukup_menggagalkan_waste_normal_tanpa_baris_tersisa(): void
    {
        $this->post('/waste', ['id_bahan' => $this->idBahan, 'qty' => 51, 'jenis' => 'Normal', 'alasan' => 'X'])->assertSessionHas('error');

        $this->assertEquals(50, $this->stok());
        $this->assertEquals(0, DB::table('waste_log')->count());
    }

    public function test_persetujuan_abnormal_gagal_kalau_stok_sudah_tidak_cukup(): void
    {
        $this->post('/waste', ['id_bahan' => $this->idBahan, 'qty' => 40, 'jenis' => 'Abnormal', 'alasan' => 'Rusak']);
        DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->update(['stok' => 10]);
        $req = ApprovalRequest::where('tipe_aksi', 'waste_abnormal')->firstOrFail();

        $this->patch("/approval/{$req->id}/approve", ['catatan' => 'OK'])->assertSessionHas('error');

        $this->assertEquals('Menunggu', $req->fresh()->status);
        $this->assertEquals('Menunggu', DB::table('waste_log')->value('status'));
        $this->assertEquals(10, $this->stok());
    }

    public function test_periode_tertutup_menolak_waste_normal(): void
    {
        AccountingPeriodService::tutup((int) now()->year, (int) now()->month, $this->admin->id_user);

        $this->post('/waste', ['id_bahan' => $this->idBahan, 'qty' => 1, 'jenis' => 'Normal', 'alasan' => 'X'])->assertSessionHas('error');

        $this->assertEquals(50, $this->stok());
        $this->assertEquals(0, DB::table('waste_log')->count());
    }

    public function test_halaman_waste_render_dan_akses_per_role(): void
    {
        $this->get('/waste')->assertOk()->assertSee('Pengelolaan Limbah');
        $this->get('/waste/create')->assertOk()->assertSee('Flexi Korea');

        $roleGudang = Role::factory()->create(['nama_role' => 'Gudang']);
        $gudang = User::factory()->create(['id_role' => $roleGudang->id_role]);
        $roleKasir = Role::factory()->create(['nama_role' => 'Kasir']);
        $kasir = User::factory()->create(['id_role' => $roleKasir->id_role]);
        DB::table('menus')->insertOrIgnore(['id_menu' => 1, 'nama_menu' => 'Master Bahan', 'url_link' => 'bahan-baku', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $roleGudang->id_role, 'id_menu' => 1]);

        $this->actingAs($gudang)->get('/waste')->assertOk();
        $this->actingAs($kasir)->get('/waste')->assertForbidden();
    }

    private function bahanBerSatuanHitung(string $namaHitung, float $konversi): void
    {
        $idRoll = DB::table('satuan')->insertGetId(['nama_satuan' => 'roll-uji', 'nilai_konversi' => 1]);
        $idHitung = DB::table('satuan')->insertGetId(['nama_satuan' => $namaHitung, 'nilai_konversi' => 1]);
        DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->update([
            'satuan_stok' => 'roll', 'stok' => 2, 'id_satuan_stok' => $idRoll, 'id_satuan_hitung' => $idHitung, 'nilai_konversi' => $konversi,
        ]);
    }

    public function test_waste_satuan_luas_diisi_panjang_lebar_dan_hanya_mengurangi_sebesar_luasnya(): void
    {
        // Roll = 45 m2. Rusak 30 x 90 cm = 0,27 m2 -> 0,006 roll (bukan 1 roll).
        $this->bahanBerSatuanHitung('m2', 45);

        $this->post('/waste', ['id_bahan' => $this->idBahan, 'panjang' => 30, 'lebar' => 90, 'jumlah' => 1, 'jenis' => 'Normal', 'alasan' => 'Salah cetak'])
            ->assertRedirect(route('waste.index'));

        $this->assertEqualsWithDelta(1.994, $this->stok(), 0.00001);
    }

    public function test_waste_satuan_pcs_diisi_jumlah_pcs(): void
    {
        // 1 dus = 100 pcs; waste 5 pcs = 0,05 dus.
        $this->bahanBerSatuanHitung('pcs-uji', 100);

        $this->post('/waste', ['id_bahan' => $this->idBahan, 'qty' => 5, 'jenis' => 'Normal', 'alasan' => 'Rusak'])->assertRedirect(route('waste.index'));

        $this->assertEqualsWithDelta(1.95, $this->stok(), 0.00001);
    }

    public function test_waste_satuan_luas_tanpa_panjang_lebar_ditolak_dan_stok_tetap(): void
    {
        $this->bahanBerSatuanHitung('m2', 45);

        $this->post('/waste', ['id_bahan' => $this->idBahan, 'jenis' => 'Normal', 'alasan' => 'x'])->assertSessionHas('error');
        $this->assertEqualsWithDelta(2.0, $this->stok(), 0.00001);
    }

    public function test_operator_hanya_melihat_bahan_dan_item_divisinya_dan_tidak_bisa_waste_divisi_lain(): void
    {
        $mesinA = DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('id_mesin');
        $mesinB = DB::table('mesin')->insertGetId(['nama_mesin' => 'MESIN-B', 'tipe_mesin' => 'x']);
        $kat = DB::table('kategori')->value('id_kategori');
        $bahanB = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'BHN-B', 'nama_bahan' => 'Bahan Divisi B', 'stok' => 10, 'harga_rata_rata' => 1000, 'nilai_konversi' => 1,
            'id_mesin' => $mesinB, 'id_kategori' => $kat, 'satuan_stok' => 'pcs', 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $kar = DB::table('karyawan')->insertGetId(['nama_karyawan' => 'Op A', 'bagian' => 'Produksi', 'telepon' => '1', 'status_aktif' => 1]);
        DB::table('karyawan_mesin')->insert(['id_karyawan' => $kar, 'id_mesin' => $mesinA]);
        $role = Role::factory()->create(['nama_role' => 'Operator Produksi']);
        $op = User::factory()->create(['id_role' => $role->id_role, 'id_karyawan' => $kar]);
        DB::table('menus')->insertOrIgnore(['id_menu' => 23, 'nama_menu' => 'Produksi', 'url_link' => 'produksi', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => 23]);

        $this->actingAs($op)->get('/waste/create')->assertOk()->assertSee('Flexi Korea')->assertDontSee('Bahan Divisi B');

        $this->actingAs($op)->post('/waste', ['id_bahan' => $bahanB, 'qty' => 1, 'jenis' => 'Normal', 'alasan' => 'x'])->assertSessionHas('error');
        $this->assertEquals(10.0, (float) DB::table('bahan_baku')->where('id_bahan', $bahanB)->value('stok'));

        // Administrator melihat semua dan bisa memfilter satu divisi.
        $this->actingAs($this->admin)->get('/waste/create')->assertSee('Flexi Korea')->assertSee('Bahan Divisi B');
        $this->actingAs($this->admin)->get("/waste/create?divisi={$mesinB}")->assertSee('Bahan Divisi B')->assertDontSee('Flexi Korea');
    }
}
