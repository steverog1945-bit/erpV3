<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P1-2 (audit hardening): alur Edit Nota (requestEditNota, approveEditNota,
 * rejectEditNota, applyApprovedEdit) sebelumnya cek-status-lalu-tindak TANPA
 * lockForUpdate()/transaction - klik ganda atau retry jaringan bisa memicu
 * StokReversalService::kembalikan() & AccountingService::reversePenjualan()
 * DUA KALI untuk nota yang sama (stok & jurnal ganda). Tes ini membuktikan
 * panggilan kedua (setelah yang pertama commit) selalu ditolak dan TIDAK
 * mengulang efek sampingnya - pola pembuktian yang sama seperti
 * ApprovalWorkflowTest::test_approvalservice_lockforupdate_mencegah_keputusan_ganda().
 */
class EditNotaConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idKonsumen;
    private int $idGol;
    private int $idBahan;
    private int $idTransaksi;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00:00')); // Senin - lihat catatan sama di TintaStokTest/StokReversalSnapshotTest

        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M1', 'tipe_mesin' => 'Cetak']);
        $idKat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $this->idBahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'B1', 'nama_bahan' => 'Bahan', 'id_mesin' => $idMesin, 'id_kategori' => $idKat,
            'stok' => 100, 'nilai_konversi' => 1, 'harga_rata_rata' => 1000, 'satuan_stok' => 'lbr', 'status' => 'aktif',
        ]);
        $idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'P1', 'metode_hitung' => 'quantity', 'status' => 'aktif']);
        DB::table('bom_detail')->insert(['id_produk' => $idProduk, 'id_bahan' => $this->idBahan, 'cara_hitung_pemakaian' => 'Q', 'created_at' => now(), 'updated_at' => now()]);

        $this->idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $this->idGol]);
        DB::table('setting_harga')->insert(['id_produk' => $idProduk, 'id_golongan' => $this->idGol, 'range_min' => 0, 'harga_jual' => 10000]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open']);

        $this->actingAs($this->admin)->postJson('/penjualan/store', [
            'id_konsumen' => $this->idKonsumen, 'id_golongan' => $this->idGol,
            'items' => [['id_produk' => $idProduk, 'nama_file' => 'a', 'p' => 0, 'l' => 0, 'qty_input' => 5, 'biaya_cetak' => 10000]],
        ])->assertOk();
        $this->idTransaksi = DB::table('transaksi')->value('id_transaksi');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_apply_approved_edit_dipanggil_dua_kali_tidak_membalikkan_stok_dan_jurnal_dua_kali(): void
    {
        $this->assertEquals(95, (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('stok'));

        $this->actingAs($this->admin)->post("/penjualan/{$this->idTransaksi}/request-edit", ['alasan' => 'salah harga'])->assertRedirect();
        $idRequest = DB::table('nota_edit_request')->where('id_transaksi', $this->idTransaksi)->value('id_request');
        $this->actingAs($this->admin)->patch("/penjualan/edit-nota/{$idRequest}/approve", ['catatan' => 'ok'])->assertRedirect();

        // Panggilan PERTAMA: harus benar-benar membalikkan stok & menandai selesai.
        $this->actingAs($this->admin)->post("/penjualan/edit-nota/{$idRequest}/apply")->assertRedirect();
        $this->assertEquals(100, (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('stok'), 'stok harus kembali penuh setelah 1x apply');
        $this->assertSame('Selesai', DB::table('nota_edit_request')->where('id_request', $idRequest)->value('status'));
        $jumlahJurnalSetelahPertama = DB::table('jurnal_umum')->count();
        $jumlahKartuStokSetelahPertama = DB::table('kartu_stok')->where('referensi', 'EDIT-NOTA-' . $this->idTransaksi)->count();

        // Panggilan KEDUA (simulasi klik ganda / retry) - HARUS ditolak, TIDAK boleh membalikkan stok/jurnal lagi.
        $this->actingAs($this->admin)->post("/penjualan/edit-nota/{$idRequest}/apply")->assertRedirect();
        $this->assertEquals(100, (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('stok'), 'stok TIDAK BOLEH bertambah lagi di panggilan kedua');
        $this->assertEquals($jumlahJurnalSetelahPertama, DB::table('jurnal_umum')->count(), 'jurnal TIDAK BOLEH terbalik dua kali');
        $this->assertEquals($jumlahKartuStokSetelahPertama, DB::table('kartu_stok')->where('referensi', 'EDIT-NOTA-' . $this->idTransaksi)->count(), 'kartu_stok TIDAK BOLEH bertambah baris lagi');
    }

    public function test_permintaan_edit_kedua_untuk_nota_yang_sama_ditolak_selama_yang_pertama_masih_berjalan(): void
    {
        $this->actingAs($this->admin)->post("/penjualan/{$this->idTransaksi}/request-edit", ['alasan' => 'a'])->assertRedirect();
        $this->assertEquals(1, DB::table('nota_edit_request')->where('id_transaksi', $this->idTransaksi)->count());

        $this->actingAs($this->admin)->post("/penjualan/{$this->idTransaksi}/request-edit", ['alasan' => 'b'])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertEquals(1, DB::table('nota_edit_request')->where('id_transaksi', $this->idTransaksi)->count(), 'tidak boleh ada permintaan kedua selama yang pertama masih Menunggu');
    }

    public function test_approve_dua_kali_untuk_permintaan_yang_sama_hanya_berlaku_sekali(): void
    {
        $this->actingAs($this->admin)->post("/penjualan/{$this->idTransaksi}/request-edit", ['alasan' => 'a'])->assertRedirect();
        $idRequest = DB::table('nota_edit_request')->where('id_transaksi', $this->idTransaksi)->value('id_request');

        $this->actingAs($this->admin)->patch("/penjualan/edit-nota/{$idRequest}/approve", ['catatan' => 'pertama'])->assertRedirect()->assertSessionHas('success');
        $this->actingAs($this->admin)->patch("/penjualan/edit-nota/{$idRequest}/approve", ['catatan' => 'kedua'])->assertRedirect()->assertSessionHas('error');

        $this->assertSame('pertama', DB::table('nota_edit_request')->where('id_request', $idRequest)->value('catatan_approver'));
    }
}
