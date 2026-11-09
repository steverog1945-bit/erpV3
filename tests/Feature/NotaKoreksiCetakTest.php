<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Nota Penjualan dan Jasa (item langsung Selesai, tanpa produksi) tetap bisa dikoreksi, dibatalkan, dan dicetak:
 *   koreksi/batal -> stok bahan kembali (kartu IN), jurnal penjualan & HPP dibalik, uang masuk menjadi deposit.
 * Item PRODUK yang sudah Selesai diproduksi tetap mengunci nota (tidak berubah).
 */
class NotaKoreksiCetakTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $kons;
    private int $bahan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($this->admin);
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $this->kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Toko Maju', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'x']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K', 'kode_kategori' => 'K']);
        $this->bahan = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'B', 'nama_bahan' => 'Flexi Korea', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 50, 'nilai_konversi' => 1, 'harga_rata_rata' => 10000, 'satuan_stok' => 'm2', 'status' => 'aktif']);
        AccountingService::postSaldoAwalPersediaan('SA-B', 'Saldo awal Flexi', 500000);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()->subMinute()]);
    }

    private function saldo(string $kode): float
    {
        return round((float) DB::table('jurnal_umum')->where('kode_akun', $kode)->sum(DB::raw('debet - kredit')), 2);
    }

    private function stok(): float
    {
        return (float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('stok');
    }

    /** Nota Penjualan dan Jasa: 3 m2 bahan @15.000 + jasa 20.000, dibayar 25.000. */
    private function notaPenjualanLangsung(): object
    {
        $jasa = DB::table('jasa')->insertGetId(['nama_jasa' => 'Press', 'harga_default' => 20000]);
        $this->post('/penjualan-barang/store', ['id_konsumen' => $this->kons, 'items' => [
            ['tipe' => 'bahan', 'id_bahan' => $this->bahan, 'qty' => 3, 'harga' => 15000],
            ['tipe' => 'jasa', 'id_jasa' => $jasa, 'qty' => 1, 'harga' => 20000],
        ]])->assertSessionHasNoErrors();
        $t = DB::table('transaksi')->latest('id_transaksi')->first();
        $this->post('/kasir/proses-bayar', ['id_transaksi' => $t->id_transaksi, 'jumlah_bayar' => '25.000', 'metode_pembayaran' => 'Transfer', 'submit_id' => 'nk-' . uniqid()])
            ->assertSessionHas('success');

        return $t;
    }

    private function buktikanNotaDibalik(object $t): void
    {
        $this->assertSame('Dibatalkan', DB::table('transaksi')->where('id_transaksi', $t->id_transaksi)->value('status_nota'));
        $this->assertEquals(50, $this->stok(), 'stok bahan yang dijual harus kembali');
        $this->assertSame(1, DB::table('kartu_stok')->where('id_bahan', $this->bahan)->where('tipe', 'IN')->count(), 'pengembalian stok harus tercatat sekali di kartu stok');
        $this->assertEquals(0, $this->saldo('1100'), 'piutang nota dibalik');
        $this->assertEquals(0, $this->saldo('4000') + $this->saldo('4100'), 'pendapatan dibalik');
        $this->assertEquals(0, $this->saldo('5000'), 'HPP dibalik');
        $this->assertEquals(500000, $this->saldo('1200'), 'persediaan kembali utuh');
        $this->assertEquals(-25000, $this->saldo('2100'), 'uang yang sudah dibayar menjadi deposit pelanggan');
        $this->assertEquals((float) DB::table('jurnal_umum')->sum('debet'), (float) DB::table('jurnal_umum')->sum('kredit'));
    }

    public function test_koreksi_nota_penjualan_langsung_membalik_stok_jurnal_dan_mengarahkan_ke_penjualan_dan_jasa(): void
    {
        $t = $this->notaPenjualanLangsung();

        $this->post("/penjualan/{$t->id_transaksi}/request-edit", ['alasan' => 'Salah jumlah bahan'])->assertSessionHasNoErrors();
        $req = DB::table('nota_edit_request')->where('id_transaksi', $t->id_transaksi)->first();
        $this->assertNotNull($req, 'permintaan koreksi tidak tercatat');
        $this->patch("/penjualan/edit-nota/{$req->id_request}/approve", ['catatan' => 'ok']);
        $this->assertSame('Disetujui', DB::table('nota_edit_request')->where('id_request', $req->id_request)->value('status'));

        $this->post("/penjualan/edit-nota/{$req->id_request}/apply")->assertRedirect(route('penjualan-barang.create', ['id_konsumen' => $this->kons]));

        $this->buktikanNotaDibalik($t);

        // Proses koreksi diulang tidak membalik dua kali.
        $this->post("/penjualan/edit-nota/{$req->id_request}/apply");
        $this->assertEquals(50, $this->stok());
        $this->assertEquals(-25000, $this->saldo('2100'));
    }

    public function test_batal_nota_penjualan_langsung_dengan_pengembalian_stok(): void
    {
        $t = $this->notaPenjualanLangsung();

        $this->patch("/penjualan/{$t->id_transaksi}/batalkan-nota", ['alasan' => 'Pembeli batal', 'kembalikan_stok' => 1])->assertSessionHasNoErrors();
        $req = ApprovalRequest::where('tipe_aksi', 'batalkan_nota')->first();
        $this->assertNotNull($req, 'pengajuan pembatalan tidak tercatat');
        $this->patch("/approval/{$req->id}/approve", ['catatan' => 'ok']);

        $this->buktikanNotaDibalik($t);
    }

    public function test_nota_produk_yang_sudah_selesai_diproduksi_tetap_terkunci(): void
    {
        $produk = DB::table('produk')->insertGetId(['nama_produk' => 'Banner', 'metode_hitung' => 'quantity', 'status' => 'aktif']);
        $id = DB::table('transaksi')->insertGetId(['nomor_nota' => 'INV-PRD', 'tipe_order' => 'Reguler', 'id_konsumen' => $this->kons, 'id_user_kasir' => $this->admin->id_user,
            'tgl_order' => now(), 'total_bayar' => 50000, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_collecting' => 'Belum Diambil']);
        DB::table('transaksi_detail')->insert(['id_transaksi' => $id, 'id_produk' => $produk, 'nama_file' => 'banner.pdf', 'p_ukuran' => 1, 'l_ukuran' => 1, 'qty' => 1,
            'status_urgensi' => 'Normal', 'status_finishing' => 'Standar', 'subtotal' => 50000, 'hpp_snapshot' => 0, 'harga_jual_snapshot' => 50000, 'status_spk' => 'Selesai', 'biaya_cetak' => 50000, 'biaya_desain' => 0]);

        $this->patch("/penjualan/{$id}/batalkan-nota", ['alasan' => 'x'])->assertSessionHas('error');
        $this->assertSame(0, ApprovalRequest::where('tipe_aksi', 'batalkan_nota')->count());
    }

    public function test_nota_bisa_dicetak_dari_detail_dan_riwayat(): void
    {
        $t = $this->notaPenjualanLangsung();
        $cetak = route('kasir.print-opsi', $t->id_transaksi);

        $this->get(route('penjualan.show', $t->id_transaksi))->assertOk()->assertSee($cetak, false);
        $this->get(route('penjualan.riwayat'))->assertOk()->assertSee($cetak, false);
        $this->get($cetak)->assertOk()->assertSee($t->nomor_nota)->assertSee('Flexi Korea');
    }

    public function test_kasir_tanpa_menu_monitoring_tetap_bisa_mencetak_nota(): void
    {
        $t = $this->notaPenjualanLangsung();
        $role = Role::factory()->create(['nama_role' => 'Kasir Depan']);
        DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => 3, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 0]);
        $this->actingAs(User::factory()->create(['id_role' => $role->id_role]));

        $cetak = route('kasir.print-opsi', $t->id_transaksi);
        $this->get(route('penjualan.riwayat'))->assertOk()->assertSee($cetak, false);
        $this->get($cetak)->assertOk()->assertSee($t->nomor_nota);
        $this->get(route('kasir.index'))->assertForbidden();
    }

    public function test_batal_tanpa_pengembalian_stok_tidak_membalik_hpp(): void
    {
        $t = $this->notaPenjualanLangsung();

        $this->patch("/penjualan/{$t->id_transaksi}/batalkan-nota", ['alasan' => 'Bahan sudah terpotong rusak'])->assertSessionHasNoErrors();
        $req = ApprovalRequest::where('tipe_aksi', 'batalkan_nota')->first();
        $this->patch("/approval/{$req->id}/approve", ['catatan' => 'ok']);

        $this->assertSame('Dibatalkan', DB::table('transaksi')->where('id_transaksi', $t->id_transaksi)->value('status_nota'));
        $this->assertEquals(47, $this->stok(), 'bahan tidak dikembalikan');
        // Bahan sudah keluar dan tidak kembali: biayanya tetap HPP, persediaan tetap berkurang - sama dengan stok fisik.
        $this->assertEquals(30000, $this->saldo('5000'));
        $this->assertEquals(470000, $this->saldo('1200'));
        $this->assertEquals(0, $this->saldo('4000') + $this->saldo('4100'), 'pendapatan tetap dibalik');
        $this->assertEquals(0, $this->saldo('1100'));
        $this->assertEquals((float) DB::table('jurnal_umum')->sum('debet'), (float) DB::table('jurnal_umum')->sum('kredit'));
    }

    public function test_pengembalian_stok_penjualan_langsung_tetap_sekali_walau_log_audit_diarsipkan(): void
    {
        $t = $this->notaPenjualanLangsung();
        $item = DB::table('transaksi_detail')->where('id_transaksi', $t->id_transaksi)->whereNotNull('id_bahan')->first();

        \App\Services\StokReversalService::kembalikan($item, 'BATAL-UJI', 'uji pengembalian');
        $this->assertEquals(50, $this->stok());

        // Log audit lebih tua dari 24 bulan dipindah ke arsip oleh data:rapikan: penanda di log itu hilang.
        DB::table('audit_logs')->where('event', 'stok_reversal')->delete();

        \App\Services\StokReversalService::kembalikan($item, 'BATAL-UJI', 'uji pengembalian');
        $this->assertEquals(50, $this->stok(), 'stok bahan dikembalikan dua kali');
        $this->assertSame(1, DB::table('kartu_stok')->where('id_bahan', $this->bahan)->where('tipe', 'IN')->count());
    }
}
