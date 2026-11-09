<?php

namespace Tests\Feature;

use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Daftar Permintaan Pembelian menampilkan isi (bahan dan qty) dan supplier tanpa harus membuka dokumennya,
 * dan supplier yang direncanakan terbawa saat PR dijadikan Pesanan Pembelian.
 */
class PermintaanPembelianDaftarTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $bahan;
    private int $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'Digital']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $this->bahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'B1', 'nama_bahan' => 'Flexi Korea', 'stok' => 5, 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'satuan_stok' => 'roll', 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->supplier = DB::table('supplier')->insertGetId(['nama_supplier' => 'PT Sumber Kertas', 'alamat' => '-', 'kontak' => '08']);
    }

    private function simpan(array $tambahan = []): PurchaseRequest
    {
        $this->actingAs($this->admin)->post('/purchase-request', $tambahan + [
            'tanggal' => now()->toDateString(), 'items' => [['id_bahan' => $this->bahan, 'qty' => 4]],
        ])->assertSessionHasNoErrors();

        return PurchaseRequest::latest('id')->firstOrFail();
    }

    public function test_daftar_menampilkan_isi_dan_supplier_rencana(): void
    {
        $this->simpan(['id_supplier_rencana' => $this->supplier]);

        $this->actingAs($this->admin)->get('/purchase-request')
            ->assertOk()->assertSee('Flexi Korea')->assertSee('roll')->assertSee('PT Sumber Kertas')->assertSee('Rencana');
    }

    public function test_tanpa_rencana_supplier_tetap_tersimpan_dan_daftar_menyebut_belum_ditentukan(): void
    {
        $pr = $this->simpan();

        $this->assertNull($pr->id_supplier_rencana);
        $this->actingAs($this->admin)->get('/purchase-request')->assertOk()->assertSee('Belum ditentukan');
    }

    public function test_supplier_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->admin)->post('/purchase-request', [
            'tanggal' => now()->toDateString(), 'id_supplier_rencana' => 999999, 'items' => [['id_bahan' => $this->bahan, 'qty' => 1]],
        ])->assertSessionHasErrors('id_supplier_rencana');
    }

    public function test_supplier_rencana_menjadi_isian_awal_saat_membuat_pesanan_pembelian(): void
    {
        $pr = $this->simpan(['id_supplier_rencana' => $this->supplier]);
        $pr->update(['status' => 'Disetujui']);

        $this->actingAs($this->admin)->get('/purchase-order/create?from_pr=' . $pr->id)
            ->assertOk()->assertSee('value="' . $this->supplier . '" selected', false);
    }

    public function test_daftar_tidak_menjalankan_query_per_baris(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->simpan(['id_supplier_rencana' => $this->supplier]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get('/purchase-request')->assertOk();

        $pola = collect(DB::getQueryLog())->countBy(fn ($q) => $q['query'])->sortDesc();
        $this->assertLessThanOrEqual(3, $pola->first(), 'Query berulang per baris: ' . $pola->keys()->first());
    }
}
