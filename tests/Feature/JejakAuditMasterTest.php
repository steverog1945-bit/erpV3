<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit Fase 4-5: perubahan master yang memengaruhi uang (rekening bank,
 * golongan/price list, promo, supplier, konsumen) dan pembayaran POS harus
 * meninggalkan jejak (siapa, kapan, sebelum, sesudah), dan master yang sudah
 * dipakai riwayat tidak boleh dihapus.
 */
class JejakAuditMasterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00:00'));
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function log(string $tabel, ?int $id = null)
    {
        return DB::table('audit_logs')->where('auditable_type', $tabel)->when($id, fn ($q) => $q->where('auditable_id', $id))->orderBy('id')->get();
    }

    public function test_rekening_bank_dibuat_diubah_dan_dihapus_dengan_jejak_lengkap(): void
    {
        $this->post('/bank', ['nama_bank' => 'BCA', 'nomor_rekening' => '111', 'atas_nama' => 'PT Uji'])->assertSessionHas('success');
        $id = (int) DB::table('bank_perusahaan')->value('id_bank');

        $this->put("/bank/{$id}", ['nama_bank' => 'BCA', 'nomor_rekening' => '999', 'atas_nama' => 'PT Uji'])->assertSessionHas('success');
        $this->delete("/bank/{$id}")->assertSessionHas('success');

        $log = $this->log('bank_perusahaan', $id);
        $this->assertSame(['created', 'updated', 'deleted'], $log->pluck('event')->all());
        $ubah = $log[1];
        $this->assertSame('111', json_decode($ubah->old_values, true)['nomor_rekening']);
        $this->assertSame('999', json_decode($ubah->new_values, true)['nomor_rekening']);
        $this->assertSame($this->admin->username, $ubah->user_label);
    }

    public function test_rekening_yang_sudah_dipakai_pembayaran_tidak_bisa_dihapus(): void
    {
        $idBank = DB::table('bank_perusahaan')->insertGetId(['nama_bank' => 'BRI', 'nomor_rekening' => '222', 'atas_nama' => 'PT Uji']);
        $idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $idKons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $idGol]);
        $idTrx = DB::table('transaksi')->insertGetId(['nomor_nota' => 'N-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $idKons, 'id_user_kasir' => $this->admin->id_user, 'tgl_order' => now(), 'total_bayar' => 1, 'status_pembayaran' => 'Lunas', 'status_nota' => 'Aktif']);
        DB::table('pembayaran_riwayat')->insert(['id_transaksi' => $idTrx, 'nominal' => 1, 'metode' => 'Transfer', 'id_bank' => $idBank, 'tgl_bayar' => now()]);

        $this->delete("/bank/{$idBank}")->assertSessionHas('error');

        $this->assertDatabaseHas('bank_perusahaan', ['id_bank' => $idBank]);
    }

    public function test_golongan_yang_dipakai_price_list_tidak_bisa_dihapus_dan_perubahannya_tercatat(): void
    {
        $this->post('/golongan', ['nama_golongan' => 'Reseller'])->assertSessionHas('success');
        $id = (int) DB::table('golongan_konsumen')->where('nama_golongan', 'Reseller')->value('id_golongan');
        $idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'P', 'metode_hitung' => 'quantity', 'status' => 'aktif']);
        DB::table('setting_harga')->insert(['id_produk' => $idProduk, 'id_golongan' => $id, 'range_min' => 0, 'harga_jual' => 5000]);

        $this->put("/golongan/{$id}", ['nama_golongan' => 'Reseller Besar'])->assertSessionHas('success');
        $this->delete("/golongan/{$id}")->assertSessionHas('error');

        $this->assertDatabaseHas('golongan_konsumen', ['id_golongan' => $id]);
        $this->assertSame(['created', 'updated'], $this->log('golongan_konsumen', $id)->pluck('event')->all());
    }

    public function test_harga_promo_tercatat_setiap_perubahan_termasuk_nonaktif_dan_hapus(): void
    {
        $idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'P', 'metode_hitung' => 'quantity', 'status' => 'aktif']);

        $this->post('/harga-promo', ['id_produk' => $idProduk, 'harga_promo' => 4000, 'tgl_mulai' => '2026-09-01', 'tgl_selesai' => '2026-09-30'])->assertSessionHas('success');
        $id = (int) DB::table('harga_promo')->value('id_promo');
        $this->put("/harga-promo/{$id}", ['id_produk' => $idProduk, 'harga_promo' => 100, 'tgl_mulai' => '2026-09-01', 'tgl_selesai' => '2026-09-30']);
        $this->patch("/harga-promo/{$id}/toggle");
        $this->delete("/harga-promo/{$id}");

        $log = $this->log('harga_promo', $id);
        $this->assertSame(['created', 'updated', 'updated', 'deleted'], $log->pluck('event')->all());
        $this->assertEquals(4000, json_decode($log[1]->old_values, true)['harga_promo']);
        $this->assertEquals(100, json_decode($log[1]->new_values, true)['harga_promo']);
    }

    public function test_supplier_dan_konsumen_tercatat(): void
    {
        $this->post('/supplier', ['nama_supplier' => 'PT A', 'kontak' => '1', 'alamat' => 'x'])->assertSessionHas('success');
        $idS = (int) DB::table('supplier')->value('id_supplier');
        $this->put("/supplier/{$idS}", ['nama_supplier' => 'PT A2', 'kontak' => '2'])->assertSessionHas('success');
        $this->assertSame(['created', 'updated'], $this->log('supplier', $idS)->pluck('event')->all());

        $idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'G', 'keterangan' => '-']);
        $this->post('/konsumen', ['nama_konsumen' => 'Budi', 'id_golongan' => $idGol, 'telepon' => '081'])->assertSessionHas('success');
        $idK = (int) DB::table('konsumen')->where('nama_konsumen', 'Budi')->value('id_konsumen');
        $this->put("/konsumen/{$idK}", ['nama_konsumen' => 'Budi S', 'telepon' => '082', 'id_golongan' => $idGol]);
        $this->assertSame(['created', 'updated'], $this->log('konsumen', $idK)->pluck('event')->all());
    }

    public function test_master_eloquent_ikut_tercatat_otomatis_lewat_trait(): void
    {
        $this->post('/finishing', ['nama_finishing' => 'Laminasi Uji', 'metode_hitung' => 'luas', 'harga_default' => 1000])->assertSessionHas('success');

        $this->assertGreaterThan(0, DB::table('audit_logs')->where('auditable_type', 'like', '%Finishing')->where('event', 'created')->count());
    }

    /** Pembayaran POS: siapa yang menerima uang hanya bisa dijawab lewat audit (tabel pembayaran tidak punya kolom pengguna). */
    public function test_pembayaran_kasir_tercatat_dan_klik_ganda_dengan_submit_id_sama_tidak_menggandakan(): void
    {
        $idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $idKons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $idGol]);
        $idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'Spanduk', 'metode_hitung' => 'quantity', 'status' => 'aktif']);
        DB::table('setting_harga')->insert(['id_produk' => $idProduk, 'id_golongan' => $idGol, 'range_min' => 0, 'harga_jual' => 10000]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open']);

        $this->postJson('/penjualan/store', [
            'id_konsumen' => $idKons, 'id_golongan' => $idGol,
            'items' => [['id_produk' => $idProduk, 'nama_file' => 'a.pdf', 'p' => 0, 'l' => 0, 'qty_input' => 5, 'biaya_cetak' => 50000]],
        ])->assertOk();
        $idTrx = (int) DB::table('transaksi')->value('id_transaksi');
        // Flow baru: kasir terima dulu sebelum bisa membayar.
        $this->post(route('kasir.terimaTransaksi', $idTrx), ['submit_id' => 'uji-terima-1'])->assertSessionHas('success');

        $payload = ['id_transaksi' => $idTrx, 'jumlah_bayar' => '20.000', 'metode_pembayaran' => 'Tunai', 'submit_id' => 'uji-123'];
        $this->post('/kasir/proses-bayar', $payload);
        $this->post('/kasir/proses-bayar', $payload);   // klik ganda: submit_id sama

        $this->assertSame(1, DB::table('pembayaran_riwayat')->where('id_transaksi', $idTrx)->count());
        $log = $this->log('pembayaran_riwayat')->first();
        $this->assertNotNull($log, 'Pembayaran harus tercatat di audit');
        $this->assertSame($this->admin->username, $log->user_label);
        $baru = json_decode($log->new_values, true);
        $this->assertEquals(20000, $baru['nominal']);
        $this->assertSame('Tunai', $baru['metode']);
        $this->assertSame(1, $this->log('pembayaran_riwayat')->count(), 'Klik ganda tidak boleh menghasilkan dua catatan');

        // Tunai lebih dari sisa (sisa 30.000, uang 50.000): dicatat 30.000, kembalian 20.000 diberitahukan.
        $r = $this->post('/kasir/proses-bayar', ['id_transaksi' => $idTrx, 'jumlah_bayar' => '50.000', 'metode_pembayaran' => 'Tunai', 'submit_id' => 'uji-124']);
        $this->assertStringContainsString('Kembalian untuk pelanggan: Rp 20.000', (string) session('success'));
        $this->assertEquals(50000, (float) DB::table('pembayaran_riwayat')->where('id_transaksi', $idTrx)->sum('nominal'));
        // Sudah lunas: uang berikutnya ditolak, bukan dianggap kembalian.
        $this->post('/kasir/proses-bayar', ['id_transaksi' => $idTrx, 'jumlah_bayar' => '10.000', 'metode_pembayaran' => 'Tunai', 'submit_id' => 'uji-125']);
        $this->assertEquals(50000, (float) DB::table('pembayaran_riwayat')->where('id_transaksi', $idTrx)->sum('nominal'));
    }
}
