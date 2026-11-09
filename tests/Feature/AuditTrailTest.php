<?php

namespace Tests\Feature;

use App\Models\KasKecil;
use App\Models\KategoriKasKecil;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_trait_auditable_mencatat_event_created(): void
    {
        $kategori = KategoriKasKecil::create(['nama_kategori' => 'ATK']);

        $kk = KasKecil::create([
            'tanggal' => now()->toDateString(),
            'id_kategori' => $kategori->id_kategori_kas_kecil,
            'keterangan' => 'Beli kertas',
            'nominal' => 50000,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'created',
            'auditable_type' => KasKecil::class,
            'auditable_id' => $kk->id,
        ]);
    }

    public function test_trait_auditable_mencatat_event_updated_dengan_diff(): void
    {
        $kategori = KategoriKasKecil::create(['nama_kategori' => 'ATK']);
        $kk = KasKecil::create([
            'tanggal' => now()->toDateString(),
            'id_kategori' => $kategori->id_kategori_kas_kecil,
            'keterangan' => 'Beli kertas',
            'nominal' => 50000,
        ]);

        $kk->update(['nominal' => 75000]);

        $log = DB::table('audit_logs')
            ->where('auditable_type', KasKecil::class)
            ->where('auditable_id', $kk->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($log);
        $newValues = json_decode($log->new_values, true);
        $oldValues = json_decode($log->old_values, true);
        $this->assertEquals(75000, (float) $newValues['nominal']);
        $this->assertEquals(50000, (float) $oldValues['nominal']);
    }

    public function test_trait_auditable_mencatat_event_deleted(): void
    {
        $kategori = KategoriKasKecil::create(['nama_kategori' => 'ATK']);
        $kk = KasKecil::create([
            'tanggal' => now()->toDateString(),
            'id_kategori' => $kategori->id_kategori_kas_kecil,
            'keterangan' => 'Beli kertas',
            'nominal' => 50000,
        ]);

        $kk->forceDelete(); // SoftDeletes aktif - forceDelete supaya event 'deleted' Eloquent standar tetap terpicu untuk pengujian ini

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'deleted',
            'auditable_type' => KasKecil::class,
            'auditable_id' => $kk->id,
        ]);
    }

    public function test_password_tidak_pernah_masuk_audit_log(): void
    {
        $user = User::factory()->create();

        $log = DB::table('audit_logs')
            ->where('auditable_type', User::class)
            ->where('auditable_id', $user->id_user)
            ->where('event', 'created')
            ->first();

        $this->assertNotNull($log);
        $newValues = json_decode($log->new_values, true);
        $this->assertArrayNotHasKey('password', $newValues, 'Password TIDAK BOLEH pernah tersimpan di audit_logs');
    }

    public function test_auditlogger_manual_untuk_jalur_dbtable(): void
    {
        AuditLogger::log('created', 'konsumen_deposit', 99, null, ['nominal' => 100000], 'Test manual log');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'created',
            'auditable_type' => 'konsumen_deposit',
            'auditable_id' => 99,
            'keterangan' => 'Test manual log',
        ]);
    }

    public function test_accountingservice_postjournal_otomatis_tercatat_di_audit_log(): void
    {
        // Idempoten - migration bag_akun sudah menyeed akun standar 1000/4000.
        if (!DB::table('bag_akun')->where('kode_akun', '1000')->exists()) {
            DB::table('bag_akun')->insert([
                'kode_akun' => '1000', 'nama_akun' => 'Kas', 'tipe_akun' => 'Aset',
                'saldo_normal' => 'Debet', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        if (!DB::table('bag_akun')->where('kode_akun', '4000')->exists()) {
            DB::table('bag_akun')->insert([
                'kode_akun' => '4000', 'nama_akun' => 'Pendapatan', 'tipe_akun' => 'Pendapatan',
                'saldo_normal' => 'Kredit', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        AccountingService::postJournal(now()->toDateString(), 'TEST-AUDIT-001', 'Test audit trail akuntansi', [
            ['kode_akun' => '1000', 'debet' => 100000, 'kredit' => 0],
            ['kode_akun' => '4000', 'debet' => 0, 'kredit' => 100000],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'jurnal_umum',
            'keterangan' => 'Test audit trail akuntansi',
        ]);
    }

    public function test_halaman_log_aktivitas_bisa_diakses_administrator(): void
    {
        $adminRole = Role::factory()->create(['nama_role' => 'Administrator']);
        $user = User::factory()->create(['id_role' => $adminRole->id_role]);

        AuditLogger::log('created', 'test_table', 1, null, ['x' => 1]);

        $response = $this->actingAs($user)->get('/log-aktivitas');

        $response->assertOk();
        $response->assertSee('Log Aktivitas');
    }
}
