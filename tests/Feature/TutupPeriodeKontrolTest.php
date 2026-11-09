<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Rules\TanggalTransaksi;
use App\Services\AccountingPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Audit tutup buku: periode hanya bisa ditutup bila bulannya sudah lewat, kasnya sudah di-closing, dan
 * jurnalnya seimbang; dokumen tidak bisa dimundurkan ke periode tertutup atau diberi tanggal masa depan.
 */
class TutupPeriodeKontrolTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00'));
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tutup(int $bulan)
    {
        return $this->actingAs($this->admin)->patch('/accounting-period/tutup', ['tahun' => 2026, 'bulan' => $bulan]);
    }

    public function test_bulan_berjalan_dan_bulan_depan_tidak_bisa_ditutup(): void
    {
        $this->tutup(9)->assertSessionHas('error');
        $this->tutup(10)->assertSessionHas('error');

        $this->assertSame(0, DB::table('accounting_periods')->where('status', 'Closed')->count());
    }

    public function test_periode_dengan_sesi_kas_masih_terbuka_tidak_bisa_ditutup(): void
    {
        DB::table('kas_harian')->insert(['tgl_kas' => '2026-08-30', 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open']);

        $this->tutup(8)->assertSessionHas('error');
        $this->assertFalse(AccountingPeriodService::isClosed('2026-08-10'));
    }

    public function test_periode_dengan_jurnal_timpang_tidak_bisa_ditutup(): void
    {
        DB::table('jurnal_umum')->insert(['tgl_jurnal' => '2026-08-05', 'no_referensi' => 'X', 'keterangan' => 'timpang', 'kode_akun' => '1000', 'debet' => 1000, 'kredit' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $this->tutup(8)->assertSessionHas('error');
        $this->assertFalse(AccountingPeriodService::isClosed('2026-08-10'));
    }

    public function test_periode_lampau_yang_bersih_bisa_ditutup(): void
    {
        $this->tutup(8)->assertSessionHas('success');

        $this->assertTrue(AccountingPeriodService::isClosed('2026-08-10'));
    }

    public function test_tanggal_dokumen_ditolak_bila_masa_depan_atau_di_periode_tertutup(): void
    {
        AccountingPeriodService::tutup(2026, 8, $this->admin->id_user);
        $lolos = fn (string $tgl) => Validator::make(['t' => $tgl], ['t' => ['date', new TanggalTransaksi]])->passes();

        $this->assertTrue($lolos('2026-09-15'));
        $this->assertTrue($lolos('2026-09-01'));
        $this->assertFalse($lolos('2026-09-16'), 'besok');
        $this->assertFalse($lolos('2026-08-31'), 'periode tertutup');
    }

    public function test_setoran_deposit_tidak_bisa_dimundurkan_ke_periode_tertutup(): void
    {
        AccountingPeriodService::tutup(2026, 8, $this->admin->id_user);
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);

        $this->actingAs($this->admin)->post('/konsumen-deposit', ['id_konsumen' => $kons, 'nominal' => 50000, 'metode_bayar' => 'Tunai', 'tgl_deposit' => '2026-08-20'])
            ->assertSessionHasErrors('tgl_deposit');

        $this->assertSame(0, DB::table('konsumen_deposit')->count());
        $this->assertSame(0, DB::table('jurnal_umum')->count());
    }
}
