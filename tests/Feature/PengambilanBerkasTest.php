<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PengambilanBerkasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idKonsumen;
    private int $idNota;
    private int $idDetail;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $role->id_role]);
        $this->actingAs($this->admin);

        $idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Bu Rina', 'telepon' => '0811', 'alamat' => '-', 'id_golongan' => $idGol]);
        [$this->idNota, $this->idDetail] = $this->buatNota('INV-PB-1', 'Selesai');
    }

    /** @return array{0:int,1:int} */
    private function buatNota(string $nomor, string $statusSpk): array
    {
        $idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'Banner ' . $nomor, 'metode_hitung' => 'quantity']);
        $idT = DB::table('transaksi')->insertGetId([
            'nomor_nota' => $nomor, 'tipe_order' => 'Reguler', 'id_konsumen' => $this->idKonsumen, 'id_user_kasir' => $this->admin->id_user,
            'tgl_order' => now(), 'total_bayar' => 100000, 'status_pembayaran' => 'Lunas', 'status_nota' => 'Aktif', 'status_collecting' => 'Belum Diambil',
        ]);
        $idD = DB::table('transaksi_detail')->insertGetId([
            'id_transaksi' => $idT, 'id_produk' => $idProduk, 'nama_file' => "{$nomor}.pdf", 'thumbnail' => "order_thumbnails/{$nomor}.jpg", 'p_ukuran' => 1, 'l_ukuran' => 1,
            'qty' => 1, 'status_urgent' => 0, 'status_urgensi' => 'Normal', 'status_finishing' => 'Standar', 'subtotal' => 100000, 'potongan' => 0,
            'hpp_snapshot' => 0, 'harga_jual_snapshot' => 100000, 'status_spk' => $statusSpk, 'biaya_cetak' => 100000, 'biaya_desain' => 0,
        ]);

        return [$idT, $idD];
    }

    private function foto(string $nama = 'bukti.jpg'): UploadedFile
    {
        return UploadedFile::fake()->create($nama, 50, 'image/jpeg');
    }

    public function test_proses_tanpa_field_baru_tetap_jalan_dan_tercatat_diambil_sendiri(): void
    {
        $this->patch("/collecting/{$this->idNota}/proses")->assertSessionHas('success');

        $this->assertEquals('Sudah Diambil', DB::table('transaksi')->where('id_transaksi', $this->idNota)->value('status_collecting'));
        $p = DB::table('pengambilan')->first();
        $this->assertEquals(['Diambil Sendiri', null, null], [$p->jenis, $p->nama_penerima, $p->bukti_path]);
    }

    public function test_perwakilan_wajib_nama_penerima_dan_bukti_tersimpan(): void
    {
        $this->patch("/collecting/{$this->idNota}/proses", ['jenis' => 'Diambil Perwakilan'])->assertSessionHasErrors('nama_penerima');
        $this->assertEquals('Belum Diambil', DB::table('transaksi')->where('id_transaksi', $this->idNota)->value('status_collecting'));
        $this->assertEquals(0, DB::table('pengambilan')->count());

        $this->patch("/collecting/{$this->idNota}/proses", [
            'jenis' => 'Diambil Perwakilan', 'nama_penerima' => 'Pak Joko', 'catatan_serah' => 'Bawa surat kuasa', 'bukti' => $this->foto(),
        ])->assertSessionHas('success');

        $p = DB::table('pengambilan')->first();
        $this->assertEquals(['Diambil Perwakilan', 'Pak Joko', 'Bawa surat kuasa'], [$p->jenis, $p->nama_penerima, $p->catatan]);
        Storage::disk('public')->assertExists($p->bukti_path);
    }

    public function test_bukan_gambar_ditolak_dan_nota_belum_selesai_atau_sudah_diambil_tidak_menambah_catatan(): void
    {
        $this->patch("/collecting/{$this->idNota}/proses", ['bukti' => UploadedFile::fake()->create('x.exe', 10)])->assertSessionHasErrors('bukti');

        [$idBelum] = $this->buatNota('INV-PB-2', 'Cetak');
        $this->patch("/collecting/{$idBelum}/proses")->assertSessionHas('error');
        $this->assertEquals(0, DB::table('pengambilan')->count());

        $this->patch("/collecting/{$this->idNota}/proses");
        $this->patch("/collecting/{$this->idNota}/proses")->assertSessionHas('error');
        $this->assertEquals(1, DB::table('pengambilan')->count());
    }

    public function test_berkas_order_mengelompokkan_semua_kategori(): void
    {
        $this->post("/berkas-order/{$this->idNota}/unggah", ['kategori' => 'Production File', 'file' => UploadedFile::fake()->create('cetak.pdf', 100, 'application/pdf'), 'id_detail' => $this->idDetail])->assertSessionHas('success');
        $this->post("/berkas-order/{$this->idNota}/unggah", ['kategori' => 'QC Photo', 'file' => $this->foto('qc.jpg')])->assertSessionHas('success');
        $this->patch("/collecting/{$this->idNota}/proses", ['jenis' => 'Dikirim', 'nama_penerima' => 'Kurir', 'bukti' => $this->foto('kirim.jpg')]);

        $grup = $this->get("/berkas-order/{$this->idNota}")->assertOk()->assertSee('Berkas Order INV-PB-1')->viewData('grup');

        $this->assertEquals(['Design', 'Production File', 'QC Photo', 'Delivery Proof'], $grup->keys()->all());
        $this->assertEquals('cetak.pdf', $grup['Production File'][0]['nama']);
        $this->assertStringContainsString('Kurir', $grup['Delivery Proof'][0]['info']);
    }

    public function test_unggah_berkas_validasi_ekstensi_dan_item_harus_milik_nota(): void
    {
        $this->post("/berkas-order/{$this->idNota}/unggah", ['kategori' => 'Lainnya', 'file' => UploadedFile::fake()->create('shell.php', 1)])->assertSessionHasErrors('file');
        $this->post("/berkas-order/{$this->idNota}/unggah", ['kategori' => 'Design', 'file' => $this->foto()])->assertSessionHasErrors('kategori');

        [, $detailLain] = $this->buatNota('INV-PB-3', 'Selesai');
        $this->post("/berkas-order/{$this->idNota}/unggah", ['kategori' => 'Lainnya', 'file' => $this->foto(), 'id_detail' => $detailLain])->assertSessionHas('error');

        $this->assertEquals(0, DB::table('order_berkas')->count());
        $this->get('/berkas-order/999999')->assertNotFound();
    }

    public function test_timeline_pelanggan_memuat_serah_terima_dan_akses_berkas_per_role(): void
    {
        $this->patch("/collecting/{$this->idNota}/proses", ['jenis' => 'Diambil Perwakilan', 'nama_penerima' => 'Pak Joko']);

        $this->get("/konsumen/{$this->idKonsumen}/timeline")->assertOk()->assertSee('Serah terima INV-PB-1')->assertSee('Pak Joko');
        $this->get("/penjualan/{$this->idNota}")->assertOk()->assertSee('Berkas Order');

        $roleOp = Role::factory()->create(['nama_role' => 'Operator Produksi']);
        $op = User::factory()->create(['id_role' => $roleOp->id_role]);
        $roleGudang = Role::factory()->create(['nama_role' => 'Gudang']);
        $gudang = User::factory()->create(['id_role' => $roleGudang->id_role]);
        DB::table('menus')->insertOrIgnore(['id_menu' => 23, 'nama_menu' => 'Produksi', 'url_link' => 'produksi', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $roleOp->id_role, 'id_menu' => 23]);

        $this->actingAs($op)->get("/berkas-order/{$this->idNota}")->assertOk();
        $this->actingAs($gudang)->get("/berkas-order/{$this->idNota}")->assertForbidden();
    }
}
