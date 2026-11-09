<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DesainHanyaLihatProduksiTest extends TestCase
{
    use RefreshDatabase;

    private User $desain;
    private int $idDetail;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::factory()->create(['nama_role' => 'Operator Desain']);
        // Sengaja diberi SEMUA aksi di matriks - proteksi tambahan harus tetap menolak.
        foreach ([22, 23, 24, 56, 57, 41] as $id) {
            DB::table('menus')->insertOrIgnore(['id_menu' => $id, 'nama_menu' => "M{$id}", 'url_link' => "m{$id}", 'parent_id' => 0]);
            DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => $id, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1]);
        }
        $this->desain = User::factory()->create(['id_role' => $role->id_role]);

        $g = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $k = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'P', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $g]);
        $t = DB::table('transaksi')->insertGetId(['nomor_nota' => 'N-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $k, 'id_user_kasir' => $this->desain->id_user, 'tgl_order' => now(), 'total_bayar' => 1, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir']);
        $p = DB::table('produk')->insertGetId(['nama_produk' => 'P1', 'metode_hitung' => 'quantity', 'status' => 'Aktif']);
        $this->idDetail = DB::table('transaksi_detail')->insertGetId(['id_transaksi' => $t, 'id_produk' => $p, 'nama_file' => 'F', 'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 1, 'subtotal' => 1, 'status_spk' => 'Cetak']);
    }

    public function test_desain_bisa_melihat_halaman_produksi_tapi_tidak_ada_tombol_aksi(): void
    {
        $this->actingAs($this->desain)->get('/spk')->assertOk()->assertSee('Hanya lihat')->assertDontSee('ambilPekerjaan(' . $this->idDetail . ')', false);
        $html = $this->actingAs($this->desain)->get('/produksi')->assertOk()->getContent();
        $this->assertStringNotContainsString('selesaiProduksi(' . $this->idDetail, $html);
        $this->assertStringNotContainsString('cancelSpk(' . $this->idDetail, $html);
        $this->assertStringNotContainsString('produksi.simpan-qty', $html);
        $this->actingAs($this->desain)->get('/collecting')->assertOk();
        $this->actingAs($this->desain)->get('/qc')->assertOk()->assertDontSee('QC Baru');
        $this->actingAs($this->desain)->get('/waste')->assertOk()->assertDontSee('Catat Waste');
    }

    public function test_desain_tidak_bisa_mengubah_apapun_di_modul_produksi(): void
    {
        $this->actingAs($this->desain)->patch("/produksi/{$this->idDetail}/selesai")->assertForbidden();
        $this->actingAs($this->desain)->patch("/produksi/{$this->idDetail}/simpan-qty", ['qty_cetak' => 5])->assertForbidden();
        $this->actingAs($this->desain)->patch("/produksi/{$this->idDetail}/cancel")->assertForbidden();
        $this->actingAs($this->desain)->patch("/spk/{$this->idDetail}/ambil")->assertForbidden();
        $this->actingAs($this->desain)->post('/spk/bulk-urgent', ['ids' => [$this->idDetail]])->assertForbidden();
        $this->actingAs($this->desain)->post('/waste', ['id_bahan' => 1, 'qty' => 1, 'jenis' => 'Normal', 'alasan' => 'x'])->assertForbidden();
        $this->actingAs($this->desain)->post('/qc', [])->assertForbidden();
        $this->actingAs($this->desain)->get('/qc/create')->assertForbidden();
        $this->actingAs($this->desain)->get('/waste/create')->assertForbidden();

        $this->assertEquals('Cetak', DB::table('transaksi_detail')->where('id_detail', $this->idDetail)->value('status_spk'));
    }

    public function test_kerja_input_order_desain_tidak_terganggu(): void
    {
        $this->actingAs($this->desain)->get('/penjualan/kasir')->assertOk();
    }
}
