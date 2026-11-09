<?php

namespace Tests\Feature;

use Tests\TestCase;

// UI audit Kasir & Penjualan: komponen <x-badge> adalah satu-satunya sumber
// kebenaran warna indikator status. Test ini mengunci pemetaan agar status
// baru tidak jatuh ke warna default (info) secara diam-diam.
class StatusBadgeTest extends TestCase
{
    /** @dataProvider statusMap */
    public function test_badge_memetakan_status_ke_varian_yang_benar(string $status, string $varian): void
    {
        $html = (string) $this->blade('<x-badge :status="$s" />', ['s' => $status]);

        $this->assertStringContainsString(
            'chip-' . $varian,
            $html,
            "Status '{$status}' harus memakai varian '{$varian}'"
        );
        $this->assertStringContainsString($status, $html, 'Teks status harus tampil');
    }

    public static function statusMap(): array
    {
        return [
            // status_pembayaran
            'Lunas' => ['Lunas', 'success'],
            'DP' => ['DP', 'warning'],
            'Belum Bayar' => ['Belum Bayar', 'danger'],
            // status_spk
            'Selesai' => ['Selesai', 'success'],
            'Desain' => ['Desain', 'info'],
            'Cetak' => ['Cetak', 'info'],
            'Antre' => ['Antre', 'secondary'],
            'Batal' => ['Batal', 'danger'],
            'Pending' => ['Pending', 'warning'],
            // status_nota
            'Aktif' => ['Aktif', 'success'],
            'Dibatalkan' => ['Dibatalkan', 'danger'],
            // status_collecting
            'Sudah Diambil' => ['Sudah Diambil', 'success'],
            'Belum Diambil' => ['Belum Diambil', 'secondary'],
        ];
    }

    public function test_variant_manual_tetap_dihormati(): void
    {
        $html = (string) $this->blade('<x-badge status="Dibatalkan" variant="danger" />');

        $this->assertStringContainsString('chip-danger', $html);
    }
}