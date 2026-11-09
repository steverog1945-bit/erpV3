<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Menjalankan gate Potong Manual sisi peramban (tests/js/potong-manual-gate.mjs)
 * dari dalam PHPUnit.
 *
 * Kenapa dibungkus PHP dan tidak berdiri sendiri:
 *  - Gate ini bagian dari gerbang rilis, jadi harus ikut hijau/b merah bersama
 *    suite - kalau berdiri sendiri, mudah terlupa dijalankan.
 *  - Isinya mengeksekusi JavaScript ASLI yang dipakai halaman Input Pesanan
 *    (dicek ulang dari berkas blade), sehingga tidak bisa hijau sementara
 *    aplikasinya masih salah. Gate ini sudah dibuktikan menangkap perilaku lama
 *    lewat negative control.
 *
 * Kalau Node tidak tersedia, test ini DILEWATI dengan alasan yang jelas - bukan
 * diam-diam dianggap lulus.
 */
class PotongManualFrontendGateTest extends TestCase
{
    public function test_gate_potong_manual_sisi_peramban(): void
    {
        $node = $this->cariNode();

        if ($node === null) {
            $this->markTestSkipped('Node tidak ditemukan di PATH - gate Potong Manual sisi peramban tidak dijalankan.');
        }

        $script = base_path('tests/js/potong-manual-gate.mjs');
        $this->assertFileExists($script, 'tests/js/potong-manual-gate.mjs tidak ada.');

        $blade = base_path('resources/views/penjualan/create.blade.php');
        $this->assertFileExists($blade, 'Berkas view Input Pesanan tidak ada.');

        $perintah = sprintf(
            '%s %s 2>&1',
            escapeshellarg($node),
            escapeshellarg($script)
        );

        $keluaran = [];
        $kode = 0;
        exec('cd ' . escapeshellarg(base_path()) . ' && ' . $perintah, $keluaran, $kode);

        $teks = implode(PHP_EOL, $keluaran);

        // Cetak keluaran apa adanya. Kegagalan gate harus bisa dibaca langsung
        // dari pesan test, bukan harus diburu di log terpisah.
        $this->assertSame(
            0,
            $kode,
            "Gate Potong Manual (peramban) GAGAL:\n" . $teks
        );

        $this->assertStringContainsString('HASIL: PASS', $teks, $teks);
    }

    private function cariNode(): ?string
    {
        // 'node' saja pertama supaya PATH yang aktif dipakai (dari nvm, scoop,
        // atau instalasi lain) - bukan asumsi lokasi tertentu.
        $output = [];
        $kode = 0;
        @exec('command -v node 2>/dev/null || where node 2>nul', $output, $kode);

        foreach ($output as $baris) {
            $kandidat = trim($baris);
            if ($kandidat !== '' && file_exists($kandidat)) {
                return $kandidat;
            }
        }

        foreach (['C:/Program Files/nodejs/node.exe'] as $tetap) {
            if (file_exists($tetap)) {
                return $tetap;
            }
        }

        return null;
    }
}