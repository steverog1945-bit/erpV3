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

/**
 * PO yang dibatalkan/dihapus melepas PR-nya kembali ke Disetujui (bisa dibuatkan PO pengganti tanpa persetujuan ulang),
 * dan PO tidak bisa dibatalkan selama masih ada kedatangan Draft darinya (kedatangan itu bisa divalidasi belakangan
 * dan menambah stok dari PO yang sudah batal).
 */
class PoBatalPrTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $bahan;
    private int $sup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($this->admin);
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'x']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K', 'kode_kategori' => 'K']);
        $this->bahan = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'B', 'nama_bahan' => 'Flexi', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 0, 'nilai_konversi' => 1, 'harga_rata_rata' => 0, 'satuan_stok' => 'm2', 'status' => 'aktif']);
        $this->sup = DB::table('supplier')->insertGetId(['nama_supplier' => 'PT A', 'alamat' => '-', 'kontak' => '0']);
        DB::table('gudang')->insert(['nama_gudang' => 'Utama']);
    }

    private function prDisetujui(): PurchaseRequest
    {
        $this->post('/purchase-request', ['tanggal' => now()->toDateString(), 'items' => [['id_bahan' => $this->bahan, 'qty' => 50]]]);
        $pr = PurchaseRequest::latest('id')->firstOrFail();
        $this->patch(route('purchase-request.ajukan', $pr->id));
        $req = ApprovalRequest::where('tipe_aksi', 'purchase_request')->latest('id')->firstOrFail();
        $this->patch("/approval/{$req->id}/approve", ['catatan' => 'OK']);
        $this->assertSame('Disetujui', $pr->fresh()->status);

        return $pr->fresh();
    }

    private function poDari(PurchaseRequest $pr): PurchaseOrder
    {
        $this->post('/purchase-order', ['id_purchase_request' => $pr->id, 'id_supplier' => $this->sup, 'tanggal' => now()->toDateString(),
            'items' => [['id_bahan' => $this->bahan, 'qty' => 50, 'harga' => 20000]]])->assertSessionHasNoErrors();
        $this->assertSame('Diproses PO', $pr->fresh()->status);

        return PurchaseOrder::latest('id')->firstOrFail();
    }

    public function test_po_dibatalkan_mengembalikan_pr_ke_disetujui_dan_bisa_dibuat_po_pengganti(): void
    {
        $pr = $this->prDisetujui();
        $po = $this->poDari($pr);
        $this->patch(route('purchase-order.terbitkan', $po->id));

        $this->patch(route('purchase-order.batalkan', $po->id))->assertSessionHas('success');

        $this->assertSame('Dibatalkan', $po->fresh()->status);
        $this->assertSame('Disetujui', $pr->fresh()->status, 'PR tertahan di Diproses PO padahal PO-nya batal');
        $this->get(route('purchase-order.create', ['from_pr' => $pr->id]))->assertOk()->assertSee($pr->nomor);
        $this->poDari($pr);
    }

    public function test_po_draft_dihapus_mengembalikan_pr_ke_disetujui(): void
    {
        $pr = $this->prDisetujui();
        $po = $this->poDari($pr);

        $this->delete(route('purchase-order.destroy', $po->id))->assertSessionHas('success');

        $this->assertSame('Disetujui', $pr->fresh()->status);
    }

    public function test_pr_tetap_diproses_bila_masih_ada_po_lain_yang_aktif(): void
    {
        $pr = $this->prDisetujui();
        $po1 = $this->poDari($pr);
        $this->patch(route('purchase-order.batalkan', $po1->id));
        $this->poDari($pr);
        // PO kedua aktif: membatalkan ulang PO pertama (sudah batal) ditolak dan tidak mengubah PR.
        $this->patch(route('purchase-order.batalkan', $po1->id))->assertSessionHas('error');
        $this->assertSame('Diproses PO', $pr->fresh()->status);
    }

    public function test_po_dengan_kedatangan_draft_tidak_bisa_dibatalkan(): void
    {
        $po = $this->poDari($this->prDisetujui());
        $this->patch(route('purchase-order.terbitkan', $po->id));
        $this->post(route('purchase-order.kedatangan', $po->id), ['no_surat_jalan' => 'SJ-PO', 'tgl_datang' => now()->toDateString()]);
        $this->assertSame(1, DB::table('kedatangan_bahan')->where('id_purchase_order', $po->id)->count());

        $this->patch(route('purchase-order.batalkan', $po->id))->assertSessionHas('error');

        $this->assertSame('Dipesan', $po->fresh()->status);
    }

    public function test_kedatangan_draft_kedua_dari_sisa_po_yang_sama_ditolak(): void
    {
        $po = $this->poDari($this->prDisetujui());
        $this->patch(route('purchase-order.terbitkan', $po->id));
        $this->post(route('purchase-order.kedatangan', $po->id), ['no_surat_jalan' => 'SJ-1', 'tgl_datang' => now()->toDateString()]);

        // Sisa yang sama sudah menunggu di Draft SJ-1: Draft kedua akan membuat stok masuk dua kali bila keduanya divalidasi.
        $this->post(route('purchase-order.kedatangan', $po->id), ['no_surat_jalan' => 'SJ-2', 'tgl_datang' => now()->toDateString()])->assertSessionHas('error');

        $this->assertSame(1, DB::table('kedatangan_bahan')->where('id_purchase_order', $po->id)->count());
    }
}
