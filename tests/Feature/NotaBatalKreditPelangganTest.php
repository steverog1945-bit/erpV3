<?php

namespace Tests\Feature;

use App\Http\Controllers\KonsumenDepositController;
use App\Models\ApprovalRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit alur ERP: nota yang sudah dibayar lalu dibatalkan tidak boleh meninggalkan uang menggantung.
 * Uang yang sudah masuk menjadi saldo deposit pelanggan, jurnalnya seimbang, dan tercatat di audit.
 */
class NotaBatalKreditPelangganTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idTrx;
    private int $idKons;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $this->idKons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        $this->idTrx = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'INV-KRD-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $this->idKons, 'id_user_kasir' => $this->admin->id_user,
            'tgl_order' => now(), 'total_bayar' => 100000, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_collecting' => 'Belum Diambil',
        ]);
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $this->idTrx, 'nama_file' => 'a.pdf', 'p_ukuran' => 1, 'l_ukuran' => 1, 'qty' => 1, 'status_urgent' => 0,
            'status_urgensi' => 'Normal', 'status_finishing' => 'Standar', 'subtotal' => 100000, 'potongan' => 0, 'hpp_snapshot' => 0,
            'harga_jual_snapshot' => 100000, 'status_spk' => 'Antre', 'biaya_cetak' => 100000, 'biaya_desain' => 0,
        ]);
        AccountingService::postPenjualan('INV-KRD-1', 100000, 0);
    }

    private function bayar(float $nominal): void
    {
        DB::table('pembayaran_riwayat')->insert(['id_transaksi' => $this->idTrx, 'nominal' => $nominal, 'metode' => 'Tunai', 'tgl_bayar' => now()]);
        AccountingService::postPembayaran('INV-KRD-1', $nominal, 'Tunai');
    }

    private function batalkan(): void
    {
        $this->actingAs($this->admin)->patch("/penjualan/{$this->idTrx}/batalkan-nota", ['alasan' => 'Salah input']);
        $req = ApprovalRequest::where('referensi_id', $this->idTrx)->first();
        $this->actingAs($this->admin)->patch("/approval/{$req->id}/approve", ['catatan' => 'ok']);
    }

    private function saldoAkun(string $kode): float
    {
        $r = DB::table('jurnal_umum')->where('kode_akun', $kode)->selectRaw('COALESCE(SUM(debet),0) d, COALESCE(SUM(kredit),0) k')->first();

        return round((float) $r->d - (float) $r->k, 2);
    }

    public function test_nota_dp_dibatalkan_uangnya_menjadi_saldo_deposit_dan_piutang_nol(): void
    {
        $this->bayar(40000);

        $this->batalkan();

        $this->assertDatabaseHas('transaksi', ['id_transaksi' => $this->idTrx, 'status_nota' => 'Dibatalkan']);
        $this->assertEquals(40000, KonsumenDepositController::saldoUntuk($this->idKons));
        $this->assertEquals(0, $this->saldoAkun('1100'), 'Piutang nota batal harus nol');
        $this->assertEquals(-40000, $this->saldoAkun('2100'), 'Hutang Deposit bertambah sebesar uang pelanggan');
        $this->assertEquals(
            (float) DB::table('jurnal_umum')->sum('debet'),
            (float) DB::table('jurnal_umum')->sum('kredit'),
            'Jurnal harus seimbang'
        );
        $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', 'konsumen_deposit')->count());
    }

    public function test_nota_belum_dibayar_dibatalkan_tidak_membuat_kredit(): void
    {
        $this->batalkan();

        $this->assertDatabaseHas('transaksi', ['id_transaksi' => $this->idTrx, 'status_nota' => 'Dibatalkan']);
        $this->assertSame(0, DB::table('konsumen_deposit')->count());
        $this->assertEquals(0, $this->saldoAkun('1100'));
    }

    public function test_pembatalan_diulang_tidak_menggandakan_kredit(): void
    {
        $this->bayar(100000);

        $this->batalkan();
        app(\App\Http\Controllers\PenjualanController::class)->eksekusiPembatalanNota($this->idTrx, ['alasan' => 'ulang']);

        $this->assertSame(1, DB::table('konsumen_deposit')->count());
        $this->assertEquals(100000, KonsumenDepositController::saldoUntuk($this->idKons));
    }
}
