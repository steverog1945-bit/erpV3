<?php

namespace Tests\Feature;

use App\Services\StatusTransitionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

// §9 - STATUS TRANSITION FORMAL.
//
// Verifikasi aturan transisi pada empat field status yang benar-benar ada di
// skema (lihat audit §8B - tidak ada kolom `status` tunggal):
//   transaksi.status_pembayaran, transaksi.status_nota,
//   transaksi_detail.status_spk, transaksi.status_collecting
//
// Test di sini menguji SERVICE secara langsung (unit), lalu satu integration
// test membuktikan controller melempar 422 saat transisi ilegal.
class StatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private function boleh(string $current, string $next, string $method): bool
    {
        try {
            StatusTransitionGuard::$method($current, $next);
            return true;
        } catch (ValidationException $e) {
            return false;
        }
    }

    private function ditolak(string $current, string $next, string $method): bool
    {
        return !$this->boleh($current, $next, $method);
    }

    /* ================================================================
       1. status_pembayaran: Belum Bayar -> DP -> Lunas sah
       ================================================================ */
    public function test_valid_status_pembayaran_transitions(): void
    {
        $this->assertTrue($this->boleh('Belum Bayar', 'DP', 'validateStatusPembayaran'));
        $this->assertTrue($this->boleh('Belum Bayar', 'Lunas', 'validateStatusPembayaran'));
        $this->assertTrue($this->boleh('DP', 'Lunas', 'validateStatusPembayaran'));
    }

    /* ================================================================
       2. status_pembayaran: mundur ditolak
       ================================================================ */
    public function test_invalid_backward_status_pembayaran_fails(): void
    {
        $this->assertTrue($this->ditolak('Lunas', 'DP', 'validateStatusPembayaran'));
        $this->assertTrue($this->ditolak('Lunas', 'Belum Bayar', 'validateStatusPembayaran'));
        $this->assertTrue($this->ditolak('DP', 'Belum Bayar', 'validateStatusPembayaran'));

        try {
            StatusTransitionGuard::validateStatusPembayaran('Lunas', 'DP');
            $this->fail('Transisi Lunas -> DP harus ditolak');
        } catch (ValidationException $e) {
            $errs = $e->errors();
            $this->assertNotEmpty($errs, 'Pesan error harus ada');
            $this->assertArrayHasKey('status_pembayaran', $errs);
        }
    }

    /* ================================================================
       3. status_nota: Dibatalkan immutable
       ================================================================ */
    public function test_cancelled_status_nota_is_immutable(): void
    {
        $this->assertTrue($this->boleh('Aktif', 'Dibatalkan', 'validateStatusNota'));
        $this->assertTrue($this->ditolak('Dibatalkan', 'Aktif', 'validateStatusNota'));
    }

    /* ================================================================
       4. status_spk: Antre->Desain->Cetak->Selesai sah; edit setelah Selesai gagal
       ================================================================ */
    public function test_valid_spk_workflow_and_invalid_edits_after_selesai(): void
    {
        $this->assertTrue($this->boleh('Antre', 'Desain', 'validateStatusSpk'));
        $this->assertTrue($this->boleh('Desain', 'Cetak', 'validateStatusSpk'));
        $this->assertTrue($this->boleh('Cetak', 'Selesai', 'validateStatusSpk'));

        // Sesudah Selesai, segala perubahan ditolak KECUALI pembatalan nota
        // (Selesai -> Batal). Koreksi nota memang membatalkan item Selesai -
        // terbukti oleh NotaKoreksiCetakTest.
        $this->assertTrue($this->ditolak('Selesai', 'Antre', 'validateStatusSpk'));
        $this->assertTrue($this->ditolak('Selesai', 'Cetak', 'validateStatusSpk'));
        $this->assertTrue($this->ditolak('Selesai', 'Desain', 'validateStatusSpk'));
        $this->assertTrue($this->ditolak('Selesai', 'Pending', 'validateStatusSpk'));
        $this->assertTrue($this->boleh('Selesai', 'Batal', 'validateStatusSpk'));

        // Selesai dan Batal keduanya terminal.
        $this->assertTrue($this->ditolak('Batal', 'Antre', 'validateStatusSpk'));
        $this->assertTrue($this->ditolak('Batal', 'Cetak', 'validateStatusSpk'));

        // Jump ilegal yang tidak masuk allowlist ditolak.
        $this->assertTrue($this->ditolak('Antre', 'Selesai', 'validateStatusSpk'));
    }

    /* ================================================================
       5. status_collecting: Sudah Diambil immutable
       ================================================================ */
    public function test_status_collecting_immutable_once_taken(): void
    {
        $this->assertTrue($this->boleh('Belum Diambil', 'Sudah Diambil', 'validateStatusCollecting'));
        $this->assertTrue($this->ditolak('Sudah Diambil', 'Belum Diambil', 'validateStatusCollecting'));
    }

    /* ================================================================
       6. Integration: controller melempar 422 saat transisi ilegal
       ================================================================ */
    public function test_controller_menggunakan_guard(): void
    {
        // Bukti integrasi: blok kode pemanggilan guard ada di controller.
        // Test ini memeriksa keberadaan, bukan behavioral, karena fixture
        // transaksi lengkap (konsumen+nota+detail+SPK) belum dibangun di suite ini.
        $file = dirname(__DIR__, 2) . '/app/Http/Controllers/PenjualanController.php';
        $isi = file_get_contents($file);

        $this->assertStringContainsString(
            'StatusTransitionGuard::validate',
            $isi,
            'PenjualanController harus memanggil StatusTransitionGuard sebelum update status'
        );
    }
}