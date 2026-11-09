<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchasingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        return User::factory()->create(['id_role' => $role->id_role]);
    }

    private function buatBahan(string $nama = 'Flexi Korea'): int
    {
        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin ' . uniqid(), 'tipe_mesin' => 'Digital']);
        $idKategori = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kategori ' . uniqid()]);

        return DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'BHN-' . uniqid(), 'nama_bahan' => $nama, 'stok' => 15,
            'id_mesin' => $idMesin, 'id_kategori' => $idKategori, 'satuan_stok' => 'm2',
            'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function buatSupplier(): int
    {
        return DB::table('supplier')->insertGetId(['nama_supplier' => 'PT Supplier', 'alamat' => '-', 'kontak' => '0800']);
    }

    private function buatPrDisetujui(User $admin, int $idBahan): PurchaseRequest
    {
        $this->actingAs($admin)->post('/purchase-request', [
            'tanggal' => now()->toDateString(),
            'items' => [['id_bahan' => $idBahan, 'qty' => 50]],
        ]);
        $pr = PurchaseRequest::firstOrFail();
        $this->actingAs($admin)->patch(route('purchase-request.ajukan', $pr->id));
        $req = ApprovalRequest::where('tipe_aksi', 'purchase_request')->firstOrFail();
        $this->actingAs($admin)->patch("/approval/{$req->id}/approve", ['catatan' => 'OK']);

        return $pr->fresh();
    }

    public function test_store_pr_membuat_draft_dengan_nomor_dan_item(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan();

        $response = $this->actingAs($admin)->post('/purchase-request', [
            'tanggal' => now()->toDateString(), 'catatan' => 'Flexi tinggal 15 m2',
            'items' => [['id_bahan' => $idBahan, 'qty' => 50, 'catatan' => 'urgent']],
        ]);

        $pr = PurchaseRequest::first();
        $response->assertRedirect(route('purchase-request.edit', $pr->id));
        $this->assertEquals('Draft', $pr->status);
        $this->assertStringStartsWith('PR-', $pr->nomor);
        $this->assertEquals(1, $pr->details()->count());
    }

    public function test_pr_tanpa_item_ditolak_validasi(): void
    {
        $response = $this->actingAs($this->admin())->post('/purchase-request', ['tanggal' => now()->toDateString()]);
        $response->assertSessionHasErrors('items');
        $this->assertEquals(0, PurchaseRequest::count());
    }

    public function test_ajukan_pr_lalu_setujui_menjadi_disetujui(): void
    {
        $admin = $this->admin();
        $pr = $this->buatPrDisetujui($admin, $this->buatBahan());

        $this->assertEquals('Disetujui', $pr->status);
    }

    public function test_tolak_pr_berstatus_ditolak(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/purchase-request', [
            'tanggal' => now()->toDateString(), 'items' => [['id_bahan' => $this->buatBahan(), 'qty' => 5]],
        ]);
        $pr = PurchaseRequest::first();
        $this->actingAs($admin)->patch(route('purchase-request.ajukan', $pr->id));
        $req = ApprovalRequest::where('tipe_aksi', 'purchase_request')->first();

        $this->actingAs($admin)->patch("/approval/{$req->id}/reject", ['catatan' => 'Belum perlu']);

        $this->assertEquals('Ditolak', $pr->fresh()->status);
    }

    public function test_pr_non_draft_tidak_bisa_diubah_atau_dihapus(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan();
        $pr = $this->buatPrDisetujui($admin, $idBahan);

        $this->actingAs($admin)->patch(route('purchase-request.update', $pr->id), [
            'tanggal' => now()->toDateString(), 'items' => [['id_bahan' => $idBahan, 'qty' => 999]],
        ])->assertSessionHas('error');
        $this->actingAs($admin)->delete(route('purchase-request.destroy', $pr->id))->assertSessionHas('error');

        $this->assertEquals(50, $pr->details()->first()->qty);
    }

    public function test_po_dari_pr_disetujui_menghitung_total_di_server_dan_menandai_pr(): void
    {
        $admin = $this->admin();
        $idBahan = $this->buatBahan();
        $idSupplier = $this->buatSupplier();
        $pr = $this->buatPrDisetujui($admin, $idBahan);

        $this->actingAs($admin)->get(route('purchase-order.create', ['from_pr' => $pr->id]))->assertOk()->assertSee($pr->nomor);

        $response = $this->actingAs($admin)->post('/purchase-order', [
            'id_purchase_request' => $pr->id, 'id_supplier' => $idSupplier, 'tanggal' => now()->toDateString(),
            'diskon' => 100000, 'pajak_persen' => 11, 'ongkir' => 50000,
            'items' => [['id_bahan' => $idBahan, 'qty' => 50, 'harga' => 20000]],
            'total' => 1, // angka dari client tidak boleh dipercaya
        ]);

        $po = PurchaseOrder::first();
        $response->assertRedirect(route('purchase-order.edit', $po->id));
        // subtotal 1.000.000 - diskon 100.000 = 900.000; pajak 11% = 99.000; + ongkir 50.000 = 1.049.000
        $this->assertEquals(1000000, (float) $po->subtotal);
        $this->assertEquals(1049000, (float) $po->total);
        $this->assertEquals('Draft', $po->status);
        $this->assertStringStartsWith('PO-', $po->nomor);
        $this->assertEquals('Diproses PO', $pr->fresh()->status);
    }

    public function test_po_dari_pr_yang_belum_disetujui_ditolak(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/purchase-request', [
            'tanggal' => now()->toDateString(), 'items' => [['id_bahan' => $this->buatBahan(), 'qty' => 5]],
        ]);
        $pr = PurchaseRequest::first();

        $this->actingAs($admin)->get(route('purchase-order.create', ['from_pr' => $pr->id]))->assertRedirect(route('purchase-request.index'));
    }

    public function test_diskon_melebihi_subtotal_ditolak(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin)->post('/purchase-order', [
            'id_supplier' => $this->buatSupplier(), 'tanggal' => now()->toDateString(), 'diskon' => 999999,
            'items' => [['id_bahan' => $this->buatBahan(), 'qty' => 1, 'harga' => 1000]],
        ]);

        $response->assertSessionHasErrors('diskon');
        $this->assertEquals(0, PurchaseOrder::count());
    }

    public function test_siklus_status_po_terbitkan_dan_batalkan(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/purchase-order', [
            'id_supplier' => $this->buatSupplier(), 'tanggal' => now()->toDateString(),
            'items' => [['id_bahan' => $this->buatBahan(), 'qty' => 2, 'harga' => 5000]],
        ]);
        $po = PurchaseOrder::first();

        $this->actingAs($admin)->patch(route('purchase-order.terbitkan', $po->id));
        $this->assertEquals('Dipesan', $po->fresh()->status);

        // Dipesan tidak bisa diubah/dihapus, tapi bisa dibatalkan selama belum ada barang diterima.
        $this->actingAs($admin)->patch(route('purchase-order.update', $po->id), [
            'id_supplier' => $po->id_supplier, 'tanggal' => now()->toDateString(),
            'items' => [['id_bahan' => $po->details()->first()->id_bahan, 'qty' => 99, 'harga' => 1]],
        ])->assertSessionHas('error');
        $this->actingAs($admin)->patch(route('purchase-order.batalkan', $po->id));
        $this->assertEquals('Dibatalkan', $po->fresh()->status);
    }

    public function test_po_yang_sudah_ada_barang_diterima_tidak_bisa_dibatalkan(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/purchase-order', [
            'id_supplier' => $this->buatSupplier(), 'tanggal' => now()->toDateString(),
            'items' => [['id_bahan' => $this->buatBahan(), 'qty' => 10, 'harga' => 5000]],
        ]);
        $po = PurchaseOrder::first();
        $this->actingAs($admin)->patch(route('purchase-order.terbitkan', $po->id));
        $po->details()->update(['qty_diterima' => 4]);

        $this->actingAs($admin)->patch(route('purchase-order.batalkan', $po->id))->assertSessionHas('error');
        $this->assertEquals('Dipesan', $po->fresh()->status);
    }

    public function test_staf_gudang_bisa_membuat_pr_tapi_tidak_bisa_mengakses_po(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Gudang']);
        $gudang = User::factory()->create(['id_role' => $role->id_role]);
        DB::table('menus')->insertOrIgnore(['id_menu' => 1, 'nama_menu' => 'Master Bahan', 'url_link' => 'bahan-baku', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => 1]);

        $this->actingAs($gudang)->get('/purchase-request')->assertOk();
        $this->actingAs($gudang)->post('/purchase-request', [
            'tanggal' => now()->toDateString(), 'items' => [['id_bahan' => $this->buatBahan(), 'qty' => 10]],
        ])->assertRedirect();
        $this->assertEquals(1, PurchaseRequest::count());

        // Persetujuan tetap khusus Administrator, dan PO khusus pembelian.
        $this->actingAs($gudang)->get('/approval')->assertForbidden();
        $this->actingAs($gudang)->get('/purchase-order')->assertForbidden();
    }

    public function test_akses_menu_purchase_sesuai_role(): void
    {
        $roleGudang = Role::factory()->create(['nama_role' => 'Gudang']);
        $gudang = User::factory()->create(['id_role' => $roleGudang->id_role]);
        $roleKasir = Role::factory()->create(['nama_role' => 'Kasir']);
        $kasir = User::factory()->create(['id_role' => $roleKasir->id_role]);
        DB::table('menus')->insertOrIgnore(['id_menu' => 20, 'nama_menu' => 'Pembelian Bahan', 'url_link' => 'pembelian-bahan', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $roleGudang->id_role, 'id_menu' => 20]);

        foreach (['/purchase-request', '/purchase-order'] as $url) {
            $this->actingAs($gudang)->get($url)->assertOk();
            $this->actingAs($kasir)->get($url)->assertForbidden();
        }
    }
}
