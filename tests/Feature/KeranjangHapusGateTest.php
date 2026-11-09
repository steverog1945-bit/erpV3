<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Menjalankan gate keranjang Input Pesanan sisi peramban
 * (tests/js/keranjang-hapus-gate.mjs) dari dalam PHPUnit.
 *
 * Gate ini mengunci aturan index item yang sedang diedit saat ada baris
 * keranjang yang dihapus. Aturan itu tidak terlihat dari kode server -
 * salah di sini berarti "UPDATE ITEM" menimpa baris yang salah atau
 * menambah baris dobel, dan tidak ada test lain yang menangkapnya.
 *
 * Kalau Node tidak tersedia, test ini DILEWATI dengan alasan yang jelas -
 * bukan diam-diam dianggap lulus.
 */
class KeranjangHapusGateTest extends TestCase
{
    public function test_gate_keranjang_sisi_peramban(): void
    {
        $node = $this->cariNode();

        if ($node === null) {
            $this->markTestSkipped('Node tidak ditemukan di PATH - gate keranjang sisi peramban tidak dijalankan.');
        }

        $script = base_path('tests/js/keranjang-hapus-gate.mjs');
        $this->assertFileExists($script, 'tests/js/keranjang-hapus-gate.mjs tidak ada.');

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

        $this->assertSame(
            0,
            $kode,
            "Gate keranjang (peramban) GAGAL:\n" . $teks
        );

        $this->assertStringContainsString('HASIL: PASS', $teks, $teks);
    }

    private function cariNode(): ?string
    {
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
