<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingPeriodService;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingPeriodTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        return User::factory()->create(['id_role' => $role->id_role]);
    }

    public function test_periode_tanpa_baris_dianggap_open(): void
    {
        $this->assertFalse(AccountingPeriodService::isClosed('2026-09-15'));
    }

    public function test_postjournal_ditolak_untuk_periode_yang_ditutup(): void
    {
        $admin = $this->admin();
        AccountingPeriodService::tutup(2026, 8, $admin->id_user);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('sudah ditutup');

        AccountingService::postJournal('2026-08-15', 'TEST-001', 'Test posting ke periode tertutup', [
            ['kode_akun' => '1000', 'debet' => 50000],
            ['kode_akun' => '4000', 'kredit' => 50000],
        ]);
    }

    public function test_postjournal_tetap_boleh_untuk_periode_lain_yang_masih_open(): void
    {
        $admin = $this->admin();
        AccountingPeriodService::tutup(2026, 8, $admin->id_user);

        // Bulan September masih Open walau Agustus sudah ditutup - harus
        // tetap berhasil, TIDAK ikut ditolak.
        AccountingService::postJournal('2026-09-15', 'TEST-002', 'Test posting ke periode terbuka', [
            ['kode_akun' => '1000', 'debet' => 50000],
            ['kode_akun' => '4000', 'kredit' => 50000],
        ]);

        $this->assertDatabaseHas('jurnal_umum', ['no_referensi' => 'TEST-002', 'kode_akun' => '1000', 'debet' => 50000]);
    }

    public function test_buka_kembali_periode_mengizinkan_posting_lagi(): void
    {
        $admin = $this->admin();
        AccountingPeriodService::tutup(2026, 8, $admin->id_user);
        AccountingPeriodService::buka(2026, 8);

        AccountingService::postJournal('2026-08-15', 'TEST-003', 'Test posting setelah dibuka lagi', [
            ['kode_akun' => '1000', 'debet' => 25000],
            ['kode_akun' => '4000', 'kredit' => 25000],
        ]);

        $this->assertDatabaseHas('jurnal_umum', ['no_referensi' => 'TEST-003']);
    }

    public function test_administrator_bisa_menutup_periode_lewat_halaman(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->patch('/accounting-period/tutup', [
            'tahun' => 2026, 'bulan' => 7, 'catatan' => 'Laporan sudah final',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('accounting_periods', [
            'tahun' => 2026, 'bulan' => 7, 'status' => 'Closed', 'closed_by' => $admin->id_user,
        ]);
    }

    public function test_bukan_administrator_tidak_bisa_akses_halaman_accounting_period(): void
    {
        $roleKasir = Role::factory()->create(['nama_role' => 'Kasir']);
        $kasir = User::factory()->create(['id_role' => $roleKasir->id_role]);

        $response = $this->actingAs($kasir)->get('/accounting-period');
        $response->assertForbidden();
    }

    public function test_periode_tertutup_yang_lolos_ke_handler_global_tampil_ramah_bukan_500(): void
    {
        $admin = $this->admin();
        AccountingPeriodService::tutup(2026, 8, $admin->id_user);

        \Illuminate\Support\Facades\Route::middleware('web')->match(['get'], '/_uji-periode', function () {
            AccountingService::postJournal('2026-08-15', 'UJI-1', 'Uji', [
                ['kode_akun' => '1000', 'debet' => 1000],
                ['kode_akun' => '4000', 'kredit' => 1000],
            ]);
        });

        $this->actingAs($admin)->from('/asal')->get('/_uji-periode')
            ->assertRedirect('/asal')->assertSessionHas('error');
        $this->actingAs($admin)->getJson('/_uji-periode')
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'sudah ditutup'));
    }

    public function test_administrator_bisa_akses_halaman_accounting_period(): void
    {
        $response = $this->actingAs($this->admin())->get('/accounting-period');
        $response->assertOk();
        $response->assertSee('Periode Akuntansi');
    }
}
