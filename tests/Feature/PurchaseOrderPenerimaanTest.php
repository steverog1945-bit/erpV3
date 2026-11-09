<?php

namespace Tests\Feature;

use App\Models\KedatanganBahan;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseOrderPenerimaanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idBahan;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $role->id_role]);
        DB::table('gudang')->insert(['nama_gudang' => 'Gudang Utama']);

        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin', 'tipe_mesin' => 'Digital']);
        $idKategori = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kategori']);
        $this->idBahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'BHN-1', 'nama_bahan' => 'Flexi Korea', 'stok' => 15, 'id_mesin' => $idMesin,
            'id_kategori' => $idKategori, 'satuan_stok' => 'm2', 'status' => 'aktif',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function poDipesan(float $qty = 10): PurchaseOrder
    {
        $idSupplier = DB::table('supplier')->insertGetId(['nama_supplier' => 'PT Supplier', 'alamat' => '-', 'kontak' => '0800']);
        $this->actingAs($this->admin)->post('/purchase-order', [
            'id_supplier' => $idSupplier, 'tanggal' => now()->toDateString(),
            'items' => [['id_bahan' => $this->idBahan, 'qty' => $qty, 'harga' => 5000]],
        ]);
        $po = PurchaseOrder::firstOrFail();
        $this->actingAs($this->admin)->patch(route('purchase-order.terbitkan', $po->id));

        return $po->fresh();
    }

    private function catat(PurchaseOrder $po, string $sj): KedatanganBahan
    {
        $this->actingAs($this->admin)->post(route('purchase-order.kedatangan', $po->id), [
            'no_surat_jalan' => $sj, 'tgl_datang' => now()->toDateString(),
        ]);

        return KedatanganBahan::where('no_surat_jalan', $sj)->firstOrFail();
    }

    private function validasi(KedatanganBahan $k): void
    {
        $this->actingAs($this->admin)->patch(route('kedatangan-bahan.validate', $k->id_kedatangan));
    }

    public function test_catat_kedatangan_membuat_draft_dari_sisa_qty_dan_belum_mengubah_stok(): void
    {
        $po = $this->poDipesan(10);

        $k = $this->catat($po, 'SJ-001');

        $this->assertEquals('Draft', $k->status);
        $this->assertEquals($po->id, $k->id_purchase_order);
        $this->assertEquals(10, (float) $k->detail()->first()->qty);
        $this->assertEquals(15, (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('stok'));
        $this->assertEquals('Dipesan', $po->fresh()->status);
        $this->actingAs($this->admin)->get(route('purchase-order.edit', $po->id))->assertOk()->assertSee('Penerimaan Barang')->assertSee('SJ-001');
    }

    public function test_validasi_penuh_membuat_po_selesai_dan_stok_bertambah(): void
    {
        $po = $this->poDipesan(10);
        $k = $this->catat($po, 'SJ-001');

        $this->validasi($k);

        $this->assertEquals('Selesai', $po->fresh()->status);
        $this->assertEquals(10, (float) $po->details()->first()->qty_diterima);
        $this->assertEquals(25, (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('stok'));
    }

    public function test_penerimaan_sebagian_lalu_sisanya_sampai_selesai(): void
    {
        $po = $this->poDipesan(10);
        $k1 = $this->catat($po, 'SJ-001');
        $k1->detail()->update(['qty' => 4]);
        $this->validasi($k1);

        $this->assertEquals('Diterima Sebagian', $po->fresh()->status);
        $this->assertEquals(4, (float) $po->details()->first()->qty_diterima);

        // Kedatangan kedua otomatis berisi sisa 6.
        $k2 = $this->catat($po, 'SJ-002');
        $this->assertEquals(6, (float) $k2->detail()->first()->qty);
        $this->validasi($k2);

        $this->assertEquals('Selesai', $po->fresh()->status);
    }

    public function test_hapus_kedatangan_selesai_mengembalikan_qty_diterima_dan_status_po(): void
    {
        $po = $this->poDipesan(10);
        $k = $this->catat($po, 'SJ-001');
        $this->validasi($k);
        $this->assertEquals('Selesai', $po->fresh()->status);

        $this->actingAs($this->admin)->delete(route('kedatangan-bahan.destroy', $k->id_kedatangan));

        $this->assertEquals('Dipesan', $po->fresh()->status);
        $this->assertEquals(0, (float) $po->details()->first()->qty_diterima);
        $this->assertEquals(15, (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('stok'));
    }

    public function test_po_yang_sudah_menerima_barang_tidak_bisa_dibatalkan(): void
    {
        $po = $this->poDipesan(10);
        $k = $this->catat($po, 'SJ-001');
        $k->detail()->update(['qty' => 3]);
        $this->validasi($k);

        $this->actingAs($this->admin)->patch(route('purchase-order.batalkan', $po->id))->assertSessionHas('error');
        $this->assertEquals('Diterima Sebagian', $po->fresh()->status);
    }

    public function test_po_draft_tidak_bisa_mencatat_kedatangan(): void
    {
        $idSupplier = DB::table('supplier')->insertGetId(['nama_supplier' => 'PT S', 'alamat' => '-', 'kontak' => '1']);
        $this->actingAs($this->admin)->post('/purchase-order', [
            'id_supplier' => $idSupplier, 'tanggal' => now()->toDateString(),
            'items' => [['id_bahan' => $this->idBahan, 'qty' => 5, 'harga' => 1000]],
        ]);
        $po = PurchaseOrder::firstOrFail();

        $this->actingAs($this->admin)->post(route('purchase-order.kedatangan', $po->id), [
            'no_surat_jalan' => 'SJ-X', 'tgl_datang' => now()->toDateString(),
        ])->assertSessionHas('error');
        $this->assertEquals(0, KedatanganBahan::count());
    }
}
