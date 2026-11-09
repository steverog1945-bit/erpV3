<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit alur ERP: pelepasan barang. Nota yang belum lunas boleh diserahkan hanya
 * dengan konfirmasi eksplisit dan alasan tertulis (ditegakkan di server) yang ikut
 * tercatat di audit; nota lunas tidak dipersulit.
 */
class CollectingBelumLunasTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idTrx;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00:00'));
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);

        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        $produk = DB::table('produk')->insertGetId(['nama_produk' => 'Spanduk', 'metode_hitung' => 'quantity', 'status' => 'aktif']);
        $this->idTrx = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'INV-UJI-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $kons, 'id_user_kasir' => $this->admin->id_user,
            'tgl_order' => now(), 'total_bayar' => 100000, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_collecting' => 'Belum Diambil',
        ]);
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $this->idTrx, 'id_produk' => $produk, 'nama_file' => 'a', 'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 1, 'subtotal' => 100000, 'status_spk' => 'Selesai',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function statusAmbil(): string
    {
        return (string) DB::table('transaksi')->where('id_transaksi', $this->idTrx)->value('status_collecting');
    }

    public function test_nota_belum_lunas_tanpa_konfirmasi_tidak_diserahkan(): void
    {
        $this->actingAs($this->admin)->patch("/collecting/{$this->idTrx}/proses", ['jenis' => 'Diambil Sendiri'])->assertSessionHas('error');

        $this->assertSame('Belum Diambil', $this->statusAmbil());
        $this->assertSame(0, DB::table('pengambilan')->count());
    }

    public function test_konfirmasi_tanpa_alasan_atau_alasan_terlalu_pendek_ditolak(): void
    {
        $this->actingAs($this->admin)->patch("/collecting/{$this->idTrx}/proses", ['konfirmasi_belum_lunas' => 1, 'alasan_belum_lunas' => ''])->assertSessionHas('error');
        $this->actingAs($this->admin)->patch("/collecting/{$this->idTrx}/proses", ['konfirmasi_belum_lunas' => 1, 'alasan_belum_lunas' => 'ok'])->assertSessionHas('error');

        $this->assertSame('Belum Diambil', $this->statusAmbil());
    }

    public function test_dengan_konfirmasi_dan_alasan_barang_diserahkan_dan_alasan_tercatat_di_audit(): void
    {
        DB::table('pembayaran_riwayat')->insert(['id_transaksi' => $this->idTrx, 'nominal' => 40000, 'metode' => 'Tunai', 'tgl_bayar' => now()]);
        DB::table('transaksi')->where('id_transaksi', $this->idTrx)->update(['status_pembayaran' => 'DP']);

        $this->actingAs($this->admin)->patch("/collecting/{$this->idTrx}/proses", [
            'jenis' => 'Diambil Sendiri', 'konfirmasi_belum_lunas' => 1, 'alasan_belum_lunas' => 'Pelanggan tetap, jatuh tempo Jumat',
        ])->assertSessionHas('success');

        $this->assertSame('Sudah Diambil', $this->statusAmbil());
        $log = DB::table('audit_logs')->where('event', 'pengambilan_barang')->first();
        $baru = json_decode($log->new_values, true);
        $this->assertTrue($baru['diserahkan_belum_lunas']);
        $this->assertEquals(60000, $baru['sisa_tagihan']);
        $this->assertSame('Pelanggan tetap, jatuh tempo Jumat', $baru['alasan']);
        $this->assertStringContainsString('TANPA LUNAS', $log->keterangan);
    }

    public function test_nota_lunas_diserahkan_tanpa_konfirmasi_tambahan(): void
    {
        DB::table('transaksi')->where('id_transaksi', $this->idTrx)->update(['status_pembayaran' => 'Lunas']);

        $this->actingAs($this->admin)->patch("/collecting/{$this->idTrx}/proses", ['jenis' => 'Diambil Sendiri'])->assertSessionHas('success');

        $this->assertSame('Sudah Diambil', $this->statusAmbil());
        $log = DB::table('audit_logs')->where('event', 'pengambilan_barang')->first();
        $this->assertArrayNotHasKey('diserahkan_belum_lunas', json_decode($log->new_values, true));
    }
}
