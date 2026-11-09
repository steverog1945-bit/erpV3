<?php

namespace Tests\Feature;

use App\Models\BackupLog;
use App\Models\Role;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolasi dari storage/app/backups asli supaya test tidak
        // menumpuk/menghapus file backup sungguhan.
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'percetakan_test_backups_' . uniqid();
        Config::set('backup.disk_path', $this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->hapusDirekori($this->tempDir);
        }

        parent::tearDown();
    }

    private function hapusDirekori(string $dir): void
    {
        foreach (glob($dir . '/*/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($dir . '/*') ?: [] as $d) {
            if (is_dir($d)) @rmdir($d);
        }
        @rmdir($dir);
    }

    private function admin(): User
    {
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        return User::factory()->create(['id_role' => $role->id_role]);
    }

    public function test_run_menghasilkan_file_dump_dan_log_sukses(): void
    {
        $log = BackupService::run('manual');

        $this->assertEquals('Sukses', $log->status);
        $this->assertNotNull($log->file_path);
        $this->assertFileExists($log->file_path);
        $this->assertGreaterThan(0, $log->ukuran_bytes);
        $this->assertStringContainsString('-- MariaDB dump', file_get_contents($log->file_path));
    }

    public function test_retensi_menghapus_file_lama_melebihi_batas(): void
    {
        Config::set('backup.retention.manual', 2);

        BackupService::run('manual');
        BackupService::run('manual');
        BackupService::run('manual');

        $files = glob($this->tempDir . '/manual/*.sql');
        $this->assertCount(2, $files);
    }

    public function test_command_backup_run_tipe_tidak_valid_gagal(): void
    {
        $exitCode = Artisan::call('backup:run', ['tipe' => 'ngawur']);
        $this->assertEquals(1, $exitCode);
    }

    public function test_command_backup_run_sukses(): void
    {
        $exitCode = Artisan::call('backup:run', ['tipe' => 'manual']);
        $this->assertEquals(0, $exitCode);
        $this->assertDatabaseHas('backup_logs', ['tipe' => 'manual', 'status' => 'Sukses']);
    }

    public function test_administrator_bisa_akses_halaman_backup(): void
    {
        $response = $this->actingAs($this->admin())->get('/backup');
        $response->assertOk();
        $response->assertSee('Backup');
    }

    public function test_bukan_administrator_tidak_bisa_akses_halaman_backup(): void
    {
        $roleKasir = Role::factory()->create(['nama_role' => 'Kasir']);
        $kasir = User::factory()->create(['id_role' => $roleKasir->id_role]);

        $response = $this->actingAs($kasir)->get('/backup');
        $response->assertForbidden();
    }

    public function test_jalankan_backup_manual_lewat_halaman(): void
    {
        $response = $this->actingAs($this->admin())->post('/backup/jalankan');

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('backup_logs', ['tipe' => 'manual', 'status' => 'Sukses']);
    }
}
