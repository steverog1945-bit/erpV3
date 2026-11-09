<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\KedatanganBahan;
use App\Models\PembelianBahan;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingPeriodService;
use App\Services\AccountingService;
use App\Services\PembayaranHutangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HutangSupplierTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $role->id_role]);
        $this->actingAs($this->admin);
    }

    private function fakturTempo(float $total = 1000000, ?string $jatuhTempo = null): PembelianBahan
    {
        $idSupplier = DB::table('supplier')->insertGetId(['nama_supplier' => 'PT Kertas Jaya', 'alamat' => '-', 'kontak' => '0800']);
        $k = KedatanganBahan::create([
            'no_surat_jalan' => 'SJ-' . uniqid(), 'id_supplier' => $idSupplier, 'tgl_datang' => now()->toDateString(), 'status' => 'Selesai',
        ]);

        $p = PembelianBahan::create([
            'id_kedatangan' => $k->id_kedatangan, 'no_faktur' => 'FAK-' . uniqid(), 'tgl_transaksi' => now()->toDateString(),
            'tipe_pembelian' => 'Tempo', 'jatuh_tempo_tgl' => $jatuhTempo ?? now()->addDays(30)->toDateString(), 'grand_total' => $total,
        ]);
        $p->status_pembayaran = 'Hutang';
        $p->save();

        AccountingService::postPembelianBahan($p->no_faktur, $total, 'Tempo');

        return $p->fresh();
    }

    private function saldo(string $kodeAkun): float
    {
        return (float) DB::table('jurnal_umum')->where('kode_akun', $kodeAkun)->sum(DB::raw('debet - kredit'));
    }

    private function approve(string $tipe): void
    {
        $req = ApprovalRequest::where('tipe_aksi', $tipe)->where('status', 'Menunggu')->firstOrFail();
        $this->patch("/approval/{$req->id}/approve", ['catatan' => 'OK']);
    }

    public function test_cicilan_mengurangi_sisa_dan_lunas_di_pembayaran_terakhir(): void
    {
        $p = $this->fakturTempo(1000000);

        PembayaranHutangService::bayar($p->id_pembelian, 400000, 'Tunai');
        $this->assertEquals('Hutang', $p->fresh()->status_pembayaran);
        $this->assertEquals(600000, PembayaranHutangService::sisa((object) $p->fresh()->getAttributes()));

        PembayaranHutangService::bayar($p->id_pembelian, 600000, 'Transfer');
        $this->assertEquals('Lunas', $p->fresh()->status_pembayaran);
        // Hutang Usaha (2000) kembali nol. Pembayaran bahan dilakukan pemilik di luar semua kas: Kas, Kas Kecil
        // dan Bank tidak berubah; seluruhnya tercatat sebagai Dana Pemilik untuk Pembelian (3100).
        $this->assertEquals(0.0, -$this->saldo('2000') + 0);
        $this->assertEquals(0.0, $this->saldo('1000') + $this->saldo('1005') + $this->saldo('1010'));
        $this->assertEquals(-1000000, $this->saldo('3100'));
    }

    public function test_nominal_melebihi_sisa_atau_faktur_lunas_ditolak(): void
    {
        $p = $this->fakturTempo(500000);

        try {
            PembayaranHutangService::bayar($p->id_pembelian, 500001, 'Tunai');
            $this->fail('Seharusnya ditolak');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('melebihi sisa', $e->getMessage());
        }

        PembayaranHutangService::bayar($p->id_pembelian, 500000, 'Tunai');
        $this->expectException(\RuntimeException::class);
        PembayaranHutangService::bayar($p->id_pembelian, 1, 'Tunai');
    }

    public function test_pelunasan_lewat_get_tidak_diizinkan(): void
    {
        $p = $this->fakturTempo();

        $this->get("/pembelian-bahan/pelunasan/{$p->id_pembelian}")->assertStatus(405);
        $this->assertEquals('Hutang', $p->fresh()->status_pembayaran);
    }

    public function test_route_pelunasan_tanpa_nominal_melunasi_sisa_penuh(): void
    {
        $p = $this->fakturTempo(750000);

        $this->patch("/pembelian-bahan/pelunasan/{$p->id_pembelian}", ['metode' => 'Tunai'])->assertSessionHas('success');

        $this->assertEquals('Lunas', $p->fresh()->status_pembayaran);
    }

    public function test_hapus_faktur_hanya_mengajukan_lalu_disetujui_membalik_jurnal(): void
    {
        $p = $this->fakturTempo(1000000);

        $this->delete("/pembelian-bahan/{$p->id_pembelian}", ['alasan' => 'Salah input'])->assertSessionHas('success');
        $this->assertNotNull(PembelianBahan::find($p->id_pembelian), 'faktur tidak boleh langsung terhapus');
        $this->assertEquals(-1000000, $this->saldo('2000'));

        $this->approve('batalkan_pembelian');

        $this->assertNull(PembelianBahan::find($p->id_pembelian));
        $this->assertEquals(0.0, $this->saldo('2000') + 0);
        $this->assertEquals(0.0, $this->saldo('1200') + 0);
    }

    public function test_faktur_dengan_pembayaran_aktif_tidak_bisa_dibatalkan_sebelum_void(): void
    {
        $p = $this->fakturTempo(1000000);
        PembayaranHutangService::bayar($p->id_pembelian, 300000, 'Tunai');

        $this->delete("/pembelian-bahan/{$p->id_pembelian}", ['alasan' => 'Batal']);
        $this->approve('batalkan_pembelian');

        $this->assertNotNull(PembelianBahan::find($p->id_pembelian));
        $this->assertEquals('Menunggu', ApprovalRequest::where('tipe_aksi', 'batalkan_pembelian')->first()->status);
    }

    public function test_void_pembayaran_lewat_approval_mengembalikan_hutang(): void
    {
        $p = $this->fakturTempo(1000000);
        PembayaranHutangService::bayar($p->id_pembelian, 1000000, 'Tunai');
        $this->assertEquals('Lunas', $p->fresh()->status_pembayaran);
        $idBayar = DB::table('pembayaran_hutang')->value('id');

        $this->post("/pembelian-bahan/pembayaran/{$idBayar}/void", ['alasan' => 'Salah nominal'])->assertSessionHas('success');
        $this->assertEquals('Lunas', $p->fresh()->status_pembayaran, 'belum berubah sebelum disetujui');

        $this->approve('void_pembayaran_hutang');

        $this->assertEquals('Hutang', $p->fresh()->status_pembayaran);
        $this->assertEquals(-1000000, $this->saldo('2000'));
        $this->assertEquals(0.0, $this->saldo('1000') + 0);
        $this->assertNotNull(DB::table('pembayaran_hutang')->where('id', $idBayar)->value('voided_at'));
    }

    public function test_pembayaran_awal_faktur_tidak_bisa_di_void_sendiri(): void
    {
        $p = $this->fakturTempo();
        PembayaranHutangService::catatPembayaranAwal($p->id_pembelian, 1000, now()->toDateString());
        $id = DB::table('pembayaran_hutang')->value('id');

        $this->post("/pembelian-bahan/pembayaran/{$id}/void", ['alasan' => 'x'])->assertSessionHas('error');
        $this->assertEquals(0, ApprovalRequest::count());
    }

    public function test_periode_tertutup_menolak_pembayaran_hutang(): void
    {
        $p = $this->fakturTempo(100000);
        AccountingPeriodService::tutup((int) now()->year, (int) now()->month, $this->admin->id_user);

        $this->patch("/pembelian-bahan/pelunasan/{$p->id_pembelian}", ['metode' => 'Tunai'])->assertSessionHas('error');

        $this->assertEquals('Hutang', $p->fresh()->status_pembayaran);
        $this->assertEquals(0, DB::table('pembayaran_hutang')->count());
    }

    public function test_no_faktur_tidak_berubah_saat_update(): void
    {
        $p = $this->fakturTempo();
        $noAsli = $p->no_faktur;

        $this->patch("/pembelian-bahan/{$p->id_pembelian}", ['no_faktur' => 'BEDA-999', 'tgl_transaksi' => now()->toDateString()]);

        $this->assertEquals($noAsli, $p->fresh()->no_faktur);
    }

    public function test_halaman_daftar_dan_detail_faktur_menampilkan_sisa_dan_riwayat_pembayaran(): void
    {
        $p = $this->fakturTempo(1000000);
        PembayaranHutangService::bayar($p->id_pembelian, 250000, 'Transfer', null, 'DP awal');

        $this->get('/pembelian-bahan')->assertOk()->assertSee($p->no_faktur)->assertSee('Sisa Rp 750.000');
        $this->get("/pembelian-bahan/{$p->id_pembelian}")->assertOk()
            ->assertSee('Riwayat Pembayaran Hutang')->assertSee('DP awal')->assertSee('Ajukan Void')->assertSee('Sisa hutang: Rp 750.000');
        $this->get("/pembelian-bahan/{$p->id_pembelian}/edit")->assertOk()->assertSee($p->no_faktur);
    }

    public function test_laporan_hutang_menampilkan_sisa_dan_umur(): void
    {
        $this->fakturTempo(900000, now()->subDays(45)->toDateString()); // telat 45 hari -> bucket 31-60
        $lunas = $this->fakturTempo(500000);
        PembayaranHutangService::bayar($lunas->id_pembelian, 500000, 'Tunai');

        $response = $this->get('/laporan/hutang');

        $response->assertOk()->assertSee('PT Kertas Jaya')->assertSee('telat 45 hari');
        $this->assertEquals(900000, (float) $response->viewData('total')['total']);
        $this->assertEquals(900000, (float) $response->viewData('total')['b60']);
    }

    public function test_pembelian_lunas_dibayar_pemilik_tidak_mengurangi_kas(): void
    {
        \App\Services\AccountingService::postPembelianBahan('F-LUNAS', 750000, 'Cash');

        $this->assertEquals(750000, $this->saldo('1200'), 'persediaan bertambah');
        $this->assertEquals(-750000, $this->saldo('3100'), 'dana pemilik untuk pembelian');
        $this->assertEquals(0.0, $this->saldo('1000') + $this->saldo('1005') + $this->saldo('1010'));
    }
}
