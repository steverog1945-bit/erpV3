<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Role;
use App\Models\StockOpname;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        return User::factory()->create(['id_role' => $role->id_role]);
    }

    private function buatBahan(string $nama = 'Flexi Korea', float $stok = 100, string $status = 'aktif'): int
    {
        $idMesin = DB::table('mesin')->insertGetId([
            'nama_mesin' => 'Mesin ' . uniqid(), 'tipe_mesin' => 'Digital',
        ]);
        $idKategori = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kategori ' . uniqid()]);

        return DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'BHN-' . uniqid(), 'nama_bahan' => $nama, 'stok' => $stok,
            'id_mesin' => $idMesin, 'id_kategori' => $idKategori, 'satuan_stok' => 'm2',
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_store_membuat_opname_draft_dengan_snapshot_semua_bahan_aktif(): void
    {
        $admin = $this->admin();
        $this->buatBahan('Flexi Korea', 100);
        $this->buatBahan('Albatros', 50);
        $this->buatBahan('Bahan Nonaktif', 10, 'nonaktif');

        $response = $this->actingAs($admin)->post('/stock-opname', ['tanggal' => now()->toDateString()]);

        $opname = StockOpname::first();
        $response->assertRedirect(route('stock-opname.edit', $opname->id));
        $this->assertEquals('Draft', $opname->status);
        $this->assertStringStartsWith('SO-', $opname->nomor);
        // Cuma 2 bahan aktif yang ikut - bahan nonaktif TIDAK dimasukkan.
        $this->assertEquals(2, $opname->details()->count());
    }

    public function test_ajukan_gagal_kalau_ada_selisih_tanpa_alasan(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan('Flexi Korea', 100);
        $this->actingAs($admin)->post('/stock-opname', ['tanggal' => now()->toDateString()]);
        $opname = StockOpname::first();
        $detail = $opname->details()->first();

        $this->actingAs($admin)->patch(route('stock-opname.update-details', $opname->id), [
            'detail' => [$detail->id => ['stok_fisik' => 98, 'alasan' => '']],
        ]);

        $response = $this->actingAs($admin)->patch(route('stock-opname.ajukan', $opname->id));

        $response->assertSessionHas('error');
        $this->assertEquals('Draft', $opname->fresh()->status);
    }

    public function test_ajukan_sukses_tidak_langsung_mengubah_stok(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan('Flexi Korea', 100);
        $this->actingAs($admin)->post('/stock-opname', ['tanggal' => now()->toDateString()]);
        $opname = StockOpname::first();
        $detail = $opname->details()->first();

        $this->actingAs($admin)->patch(route('stock-opname.update-details', $opname->id), [
            'detail' => [$detail->id => ['stok_fisik' => 98, 'alasan' => 'Rusak']],
        ]);

        $response = $this->actingAs($admin)->patch(route('stock-opname.ajukan', $opname->id));

        $response->assertRedirect(route('stock-opname.index'));
        $this->assertEquals('Diajukan', $opname->fresh()->status);
        // Stok TIDAK berubah sampai disetujui.
        $this->assertEquals(100, DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('stok'));
        $this->assertDatabaseHas('approval_requests', [
            'tipe_aksi' => 'stock_opname', 'referensi_type' => 'stock_opnames', 'referensi_id' => $opname->id, 'status' => 'Menunggu',
        ]);
    }

    public function test_setujui_pengajuan_menerapkan_selisih_ke_stok_dan_kartu_stok(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan('Flexi Korea', 100);
        $this->actingAs($admin)->post('/stock-opname', ['tanggal' => now()->toDateString()]);
        $opname = StockOpname::first();
        $detail = $opname->details()->first();

        $this->actingAs($admin)->patch(route('stock-opname.update-details', $opname->id), [
            'detail' => [$detail->id => ['stok_fisik' => 98, 'alasan' => 'Rusak']],
        ]);
        $this->actingAs($admin)->patch(route('stock-opname.ajukan', $opname->id));

        $req = ApprovalRequest::where('referensi_id', $opname->id)->where('tipe_aksi', 'stock_opname')->first();
        $response = $this->actingAs($admin)->patch("/approval/{$req->id}/approve", ['catatan' => 'OK']);

        $response->assertRedirect();
        $this->assertEquals('Disetujui', $opname->fresh()->status);
        $this->assertEquals(98, DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('stok'));
        $this->assertDatabaseHas('kartu_stok', [
            'id_bahan' => $idBahan, 'tipe' => 'ADJUSTMENT', 'referensi' => $opname->nomor,
            'stok_sebelum' => 100, 'stok_sesudah' => 98,
        ]);
    }

    public function test_tolak_pengajuan_stock_opname_berstatus_ditolak_dan_stok_tetap(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan('Flexi Korea', 100);
        $this->actingAs($admin)->post('/stock-opname', ['tanggal' => now()->toDateString()]);
        $opname = StockOpname::first();
        $detail = $opname->details()->first();

        $this->actingAs($admin)->patch(route('stock-opname.update-details', $opname->id), [
            'detail' => [$detail->id => ['stok_fisik' => 98, 'alasan' => 'Rusak']],
        ]);
        $this->actingAs($admin)->patch(route('stock-opname.ajukan', $opname->id));

        $req = ApprovalRequest::where('referensi_id', $opname->id)->where('tipe_aksi', 'stock_opname')->first();
        $response = $this->actingAs($admin)->patch("/approval/{$req->id}/reject", ['catatan' => 'Hitung ulang dulu']);

        $response->assertRedirect();
        $this->assertEquals('Ditolak', $opname->fresh()->status);
        $this->assertEquals(100, DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('stok'));
    }

    public function test_stok_yang_berubah_selama_menunggu_approval_tidak_tertimpa_sembarangan(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan('Flexi Korea', 100);
        $this->actingAs($admin)->post('/stock-opname', ['tanggal' => now()->toDateString()]);
        $opname = StockOpname::first();
        $detail = $opname->details()->first();

        $this->actingAs($admin)->patch(route('stock-opname.update-details', $opname->id), [
            'detail' => [$detail->id => ['stok_fisik' => 98, 'alasan' => 'Rusak']],
        ]);
        $this->actingAs($admin)->patch(route('stock-opname.ajukan', $opname->id));

        // Selama masih Diajukan, ada mutasi stok lain (mis. kedatangan bahan baru masuk).
        DB::table('bahan_baku')->where('id_bahan', $idBahan)->update(['stok' => 130]);

        $req = ApprovalRequest::where('referensi_id', $opname->id)->where('tipe_aksi', 'stock_opname')->first();
        $this->actingAs($admin)->patch("/approval/{$req->id}/approve", ['catatan' => 'OK']);

        // Hasil fisik (98) tetap diterapkan sebagai ground truth - bukan
        // dihitung ulang dari snapshot lama (100 -> selisih -2 -> 128).
        $this->assertEquals(98, DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('stok'));
        $this->assertDatabaseHas('kartu_stok', ['id_bahan' => $idBahan, 'stok_sebelum' => 130, 'stok_sesudah' => 98]);
    }

    public function test_hapus_hanya_boleh_untuk_draft(): void
    {
        $admin = $this->admin();
        $this->buatBahan();
        $this->actingAs($admin)->post('/stock-opname', ['tanggal' => now()->toDateString()]);
        $opname = StockOpname::first();

        $response = $this->actingAs($admin)->delete(route('stock-opname.destroy', $opname->id));
        $response->assertRedirect(route('stock-opname.index'));
        $this->assertDatabaseMissing('stock_opnames', ['id' => $opname->id]);
    }

    public function test_akses_halaman_stock_opname_sesuai_menu_access(): void
    {
        $roleGudang = Role::factory()->create(['nama_role' => 'Gudang']);
        $gudang = User::factory()->create(['id_role' => $roleGudang->id_role]);

        $roleKasir = Role::factory()->create(['nama_role' => 'Kasir']);
        $kasir = User::factory()->create(['id_role' => $roleKasir->id_role]);

        // menu_access.php memetakan 'stock-opname' ke id_menu [1] (sama
        // dengan 'bahan-baku'/'kartu-stok') - CheckMenuAccess cuma
        // mencocokkan angka ini ke role_permissions, tidak butuh baris di
        // tabel menus sama sekali (lihat app/Http/Middleware/CheckMenuAccess).
        DB::table('role_permissions')->insert(['id_role' => $roleGudang->id_role, 'id_menu' => 1]);

        $this->actingAs($gudang)->get('/stock-opname')->assertOk();
        $this->actingAs($kasir)->get('/stock-opname')->assertForbidden();
    }
}
