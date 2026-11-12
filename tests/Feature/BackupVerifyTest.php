<?php

namespace Tests\Feature;

use App\Models\BackupLog;
use App\Models\Role;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupVerifyTest extends TestCase
{
    use RefreshDatabase;

    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup_verify_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . DIRECTORY_SEPARATOR . '*.sql') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    private function dumpUtuh(): string
    {
        $file = $this->tmpDir . DIRECTORY_SEPARATOR . 'backup_manual_20260101_000000.sql';
        $isi = "-- MySQL dump\nCREATE TABLE `transaksi` (`id_transaksi` int NOT NULL, PRIMARY KEY (`id_transaksi`)) ENGINE=InnoDB;\n"
            . "CREATE TABLE `bahan_baku` (`id_bahan` int NOT NULL, PRIMARY KEY (`id_bahan`)) ENGINE=InnoDB;\n"
            . "CREATE TABLE `users` (`id_user` int NOT NULL, PRIMARY KEY (`id_user`)) ENGINE=InnoDB;\n"
            . str_repeat("INSERT INTO `transaksi` VALUES (1);\n", 50);
        file_put_contents($file, $isi);

        return $file;
    }

    public function test_dump_utuh_lulus_verifikasi(): void
    {
        $file = $this->dumpUtuh();
        BackupLog::create(['tipe' => 'manual', 'file_path' => $file, 'ukuran_bytes' => filesize($file), 'status' => 'Sukses', 'started_at' => now(), 'finished_at' => now()]);

        $hasil = BackupService::verifikasiTerbaru('manual');

        $this->assertTrue($hasil[0]['ok'], 'dump utuh harus lolos: ' . json_encode($hasil));
    }

    public function test_dump_rusak_gagal_verifikasi(): void
    {
        $file = $this->tmpDir . DIRECTORY_SEPARATOR . 'backup_manual_rusak.sql';
        file_put_contents($file, "teks acak bukan dump mysql\n" . str_repeat("x", 2048));
        BackupLog::create(['tipe' => 'manual', 'file_path' => $file, 'ukuran_bytes' => filesize($file), 'status' => 'Sukses', 'started_at' => now(), 'finished_at' => now()]);

        $hasil = BackupService::verifikasiTerbaru('manual');

        $this->assertFalse($hasil[0]['ok']);
    }

    public function test_file_backup_hilang_gagal(): void
    {
        BackupLog::create(['tipe' => 'manual', 'file_path' => $this->tmpDir . '/tidak-ada.sql', 'ukuran_bytes' => 99999, 'status' => 'Sukses', 'started_at' => now(), 'finished_at' => now()]);

        $hasil = BackupService::verifikasiTerbaru('manual');

        $this->assertFalse($hasil[0]['ok']);
    }

    public function test_halaman_cadangan_menampilkan_tombol_verifikasi(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);

        $this->actingAs($admin)->get('/backup')->assertOk()->assertSee('Verifikasi Backup Terbaru');
    }
}
