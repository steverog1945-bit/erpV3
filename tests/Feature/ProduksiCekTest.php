<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** Audit produksi (LAN): perintah pemeriksaan kesiapan harus jalan, melaporkan terstruktur, dan tidak mengubah apa pun. */
class ProduksiCekTest extends TestCase
{
    use RefreshDatabase;

    public function test_perintah_menghasilkan_temuan_terstruktur_untuk_semua_area_penting(): void
    {
        Artisan::call('produksi:cek', ['--json' => true]);
        $temuan = json_decode(Artisan::output(), true);

        $this->assertIsArray($temuan);
        $this->assertNotEmpty($temuan);
        foreach ($temuan as $t) {
            $this->assertContains($t['status'], ['OK', 'WASPADA', 'GAGAL']);
            $this->assertArrayHasKey('area', $t);
            $this->assertArrayHasKey('perbaikan', $t);
        }
        $area = array_unique(array_column($temuan, 'area'));
        foreach (['Aplikasi', 'Backup', 'Database', 'Server'] as $wajib) {
            $this->assertContains($wajib, $area, "Area {$wajib} harus diperiksa");
        }
    }

    public function test_tanpa_backup_sama_sekali_dilaporkan_gagal_dengan_kode_keluar_1(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup_kosong_' . uniqid();
        mkdir($dir);
        config(['backup.disk_path' => $dir]);

        $kode = Artisan::call('produksi:cek', ['--json' => true]);
        $temuan = collect(json_decode(Artisan::output(), true));

        $this->assertSame(1, $kode);
        $this->assertTrue($temuan->contains(fn ($t) => $t['area'] === 'Backup' && $t['status'] === 'GAGAL' && str_contains($t['temuan'], 'Belum ada berkas backup')));
        rmdir($dir);
    }

    public function test_backup_lama_lebih_dari_26_jam_dilaporkan_gagal(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'backup_lama_' . uniqid();
        mkdir($dir . DIRECTORY_SEPARATOR . 'daily', 0777, true);
        $f = $dir . DIRECTORY_SEPARATOR . 'daily' . DIRECTORY_SEPARATOR . 'backup_daily_lama.sql';
        file_put_contents($f, '-- x');
        touch($f, time() - 3 * 86400);
        config(['backup.disk_path' => $dir]);

        Artisan::call('produksi:cek', ['--json' => true]);
        $temuan = collect(json_decode(Artisan::output(), true));

        $this->assertTrue($temuan->contains(fn ($t) => $t['area'] === 'Backup' && $t['status'] === 'GAGAL' && str_contains($t['temuan'], 'penjadwal tidak berjalan')));
        unlink($f);
        rmdir($dir . DIRECTORY_SEPARATOR . 'daily');
        rmdir($dir);
    }
}
