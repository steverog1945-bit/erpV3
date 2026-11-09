<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sisa hutang berpecahan (mis. PPN 11% menghasilkan 4103998.60): modal
 * pelunasan wajib menampilkan & mengisi nominal PECAHAN yang persis, bukan
 * hasil Math.round. Backend (otoritas final) sudah exact - dikunci di sini.
 */
class PelunasanHutangPecahanTest extends TestCase
{
    use RefreshDatabase;

    private int $idSup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
        $this->idSup = DB::table('supplier')->insertGetId(['nama_supplier' => 'WUJUD UNGGUL', 'kontak' => '-']);
    }

    /** Faktur 4103998.60 (display bulat "Rp 4.103.999") dengan cicilan 4102999. */
    private function fakturPecahan(): int
    {
        $gudang = DB::table('gudang')->first()->id_gudang ?? DB::table('gudang')->insertGetId(['nama_gudang' => 'Utama']);
        $ked = DB::table('kedatangan_bahan')->insertGetId(['no_surat_jalan' => 'SJ-PEC', 'id_supplier' => $this->idSup, 'id_gudang' => $gudang, 'tgl_datang' => now()->toDateString(), 'status' => 'Selesai']);
        $id = DB::table('pembelian_bahan')->insertGetId([
            'id_kedatangan' => $ked, 'no_faktur' => 'SO-WU/0926/00049', 'tgl_transaksi' => now()->toDateString(),
            'tipe_pembelian' => 'Tempo', 'status_pembayaran' => 'Hutang', 'jatuh_tempo_tgl' => now()->addDays(30)->toDateString(),
            'grand_total' => 4103998.60,
        ]);
        AccountingService::postPembelianBahan('SO-WU/0926/00049', 4103998.60, 'Tempo');
        AccountingService::postPembayaranHutang('SO-WU/0926/00049/BYR-0', 'SO-WU/0926/00049', 4102999.00, 'Transfer');
        DB::table('pembayaran_hutang')->insert([
            'id_pembelian' => $id, 'tanggal' => now()->toDateString(), 'nominal' => 4102999.00,
            'metode' => 'Transfer', 'no_referensi_jurnal' => 'SO-WU/0926/00049/BYR-0', 'id_user' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function sisa(int $id): float
    {
        return round((float) DB::table('pembelian_bahan')->where('id_pembelian', $id)->value('grand_total')
            - (float) DB::table('pembayaran_hutang')->where('id_pembelian', $id)->whereNull('voided_at')->sum('nominal'), 2);
    }

    public function test_info_mengembalikan_sisa_pecahan_exact_bukan_bulat(): void
    {
        $f = $this->fakturPecahan();
        $this->getJson("/pembelian-bahan/pelunasan-info?faktur={$f}")->assertOk()->assertJsonPath('sisa', 999.6);
    }

    public function test_bayar_kurang_dari_sisa_diterima_sebagai_cicilan(): void
    {
        $f = $this->fakturPecahan();
        $this->patch("/pembelian-bahan/pelunasan/{$f}", ['nominal' => 999, 'metode' => 'Transfer', 'tanggal' => now()->toDateString()])
            ->assertSessionHas('success');
        $this->assertEquals(0.6, $this->sisa($f));
        $this->assertSame('Hutang', DB::table('pembelian_bahan')->where('id_pembelian', $f)->value('status_pembayaran'));
    }

    public function test_bayar_persis_sama_dengan_sisa_diterima_dan_lunas(): void
    {
        $f = $this->fakturPecahan();
        $this->patch("/pembelian-bahan/pelunasan/{$f}", ['nominal' => 999.6, 'metode' => 'Transfer', 'tanggal' => now()->toDateString()])
            ->assertSessionHas('success');
        $this->assertEquals(0.0, $this->sisa($f));
        $this->assertSame('Lunas', DB::table('pembelian_bahan')->where('id_pembelian', $f)->value('status_pembayaran'));
    }

    public function test_bayar_lebih_dari_sisa_ditolak_tanpa_menyimpan(): void
    {
        $f = $this->fakturPecahan();
        // Nilai bulat dari display lama (1000) memang melebihi sisa exact (999.6).
        $this->patch("/pembelian-bahan/pelunasan/{$f}", ['nominal' => 1000, 'metode' => 'Transfer', 'tanggal' => now()->toDateString()])
            ->assertSessionHas('error');
        $this->assertSame(1, DB::table('pembayaran_hutang')->where('id_pembelian', $f)->count());
        $this->assertEquals(999.6, $this->sisa($f));
    }

    public function test_bayar_nol_dan_negatif_ditolak(): void
    {
        $f = $this->fakturPecahan();
        $this->patch("/pembelian-bahan/pelunasan/{$f}", ['nominal' => 0, 'metode' => 'Transfer'])->assertSessionHasErrors('nominal');
        $this->patch("/pembelian-bahan/pelunasan/{$f}", ['nominal' => -5, 'metode' => 'Transfer'])->assertSessionHasErrors('nominal');
        $this->assertSame(1, DB::table('pembayaran_hutang')->where('id_pembelian', $f)->count());
    }

    public function test_pesan_sukses_menampilkan_nominal_exact(): void
    {
        $f = $this->fakturPecahan();
        $this->patch("/pembelian-bahan/pelunasan/{$f}", ['nominal' => 999.6, 'metode' => 'Transfer', 'tanggal' => now()->toDateString()])
            ->assertSessionHas('success', fn ($pesan) => str_contains($pesan, '999,60') || str_contains($pesan, '999,6'));
    }

    public function test_submit_pelunasan_ganda_tidak_mencatat_dua_kali(): void
    {
        $f = $this->fakturPecahan();
        $payload = ['nominal' => 999.6, 'metode' => 'Transfer', 'tanggal' => now()->toDateString()];
        $this->patch("/pembelian-bahan/pelunasan/{$f}", $payload)->assertSessionHas('success');
        $this->patch("/pembelian-bahan/pelunasan/{$f}", $payload)->assertSessionHas('error');
        $this->assertSame(2, DB::table('pembayaran_hutang')->where('id_pembelian', $f)->count());
        $this->assertEquals(0.0, $this->sisa($f));
    }

    public function test_tabel_menampilkan_sisa_pecahan_exact_bukan_bulat(): void
    {
        $this->fakturPecahan();
        // Sisa exact 999,6 wajib tampil apa adanya di daftar & laporan.
        $this->get('/pembelian-bahan')->assertOk()->assertSee('999,6', false);
        $this->get('/laporan/hutang')->assertOk()->assertSee('999,6', false);
    }

    public function test_pelunasan_pecahan_konsisten_hutang_nol_dan_jurnal_seimbang(): void
    {
        $f = $this->fakturPecahan();
        $this->patch("/pembelian-bahan/pelunasan/{$f}", ['nominal' => 999.6, 'metode' => 'Transfer', 'tanggal' => now()->toDateString()])
            ->assertSessionHas('success');
        $this->assertEquals(0, (float) DB::table('jurnal_umum')->where('kode_akun', '2000')->selectRaw('COALESCE(SUM(kredit) - SUM(debet), 0) s')->value('s'));
        $this->assertEquals((float) DB::table('jurnal_umum')->sum('debet'), (float) DB::table('jurnal_umum')->sum('kredit'));
    }
}
