<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\BackupService;
use App\Services\NotifikasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * P2-2 (audit hardening): backup sebelumnya hanya tersimpan di disk yang
 * sama dengan aplikasi - tidak ada jalur ke lokasi kedua di luar server,
 * dan kegagalan backup tidak pernah memicu apa pun selain baris
 * backup_logs yang harus dilihat manual. Tes ini mengunci: (1) salinan
 * kedua terkirim ke disk yang dikonfigurasi tanpa mengganggu backup lokal
 * yang sudah berhasil, (2) TIDAK dikonfigurasi = tidak ada perubahan
 * perilaku sama sekali (backward compatible), (3) kegagalan salinan kedua
 * tidak ikut menggagalkan backup lokal, dan (4) badge notifikasi memicu
 * saat ada backup/salinan kedua yang gagal.
 */
class BackupOffsiteTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'percetakan_test_backups_' . uniqid();
        Config::set('backup.disk_path', $this->tempDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*/*') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($this->tempDir . '/*') ?: [] as $d) {
            if (is_dir($d)) {
                @rmdir($d);
            }
        }
        @rmdir($this->tempDir);
        parent::tearDown();
    }

    public function test_tanpa_secondary_disk_dikonfigurasi_perilaku_sama_seperti_sebelumnya(): void
    {
        Config::set('backup.secondary_disk', null);

        $log = BackupService::run('manual');

        $this->assertSame('Sukses', $log->status);
        $this->assertNull($log->offsite_status);
        $this->assertNull($log->offsite_error);
    }

    public function test_secondary_disk_terkonfigurasi_dump_ikut_tersalin(): void
    {
        Storage::fake('backup_kedua_uji');
        Config::set('backup.secondary_disk', 'backup_kedua_uji');

        $log = BackupService::run('manual');

        $this->assertSame('Sukses', $log->status);
        $this->assertSame('Sukses', $log->offsite_status);
        $namaFile = basename($log->file_path);
        Storage::disk('backup_kedua_uji')->assertExists('manual/' . $namaFile);
    }

    public function test_kegagalan_salinan_kedua_tidak_menggagalkan_backup_lokal(): void
    {
        // Nama disk yang sengaja TIDAK ADA di config/filesystems.php - Storage::disk() melempar exception saat dipakai.
        Config::set('backup.secondary_disk', 'disk_tidak_pernah_dikonfigurasi_xyz');

        $log = BackupService::run('manual');

        $this->assertSame('Sukses', $log->status, 'backup LOKAL tetap harus sukses walau salinan kedua gagal');
        $this->assertFileExists($log->file_path);
        $this->assertSame('Gagal', $log->offsite_status);
        $this->assertNotNull($log->offsite_error);
    }

    public function test_notifikasi_memicu_saat_ada_backup_atau_salinan_kedua_yang_gagal(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);

        $notif = NotifikasiService::untuk($admin);
        $this->assertFalse((bool) collect($notif['items'])->firstWhere('key', 'backup-gagal'), 'belum ada backup gagal - belum boleh muncul');

        DB::table('backup_logs')->insert([
            'tipe' => 'manual', 'status' => 'Sukses', 'offsite_status' => 'Gagal', 'offsite_error' => 'uji',
            'started_at' => now(), 'finished_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\Cache::forget('notif_user_' . $admin->id_user);
        \Illuminate\Support\Facades\Cache::forget('notif:backup-gagal');

        $notif = NotifikasiService::untuk($admin);
        $item = collect($notif['items'])->firstWhere('key', 'backup-gagal');
        $this->assertNotNull($item);
        $this->assertSame(1, $item['count']);
    }
}
