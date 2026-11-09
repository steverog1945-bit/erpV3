<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// §8I - Malformed date harus jadi validation error, bukan 500.
class DateValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
    }

    // Tanggal valid harus tetap bekerja (regresi: tidak memecah yang valid).
    public function test_tanggal_valid_tetap_diterima(): void
    {
        $r = $this->actingAs($this->admin)
            ->get('/collecting?tgl_dari=2026-01-01&tgl_sampai=2026-01-31');

        $this->assertSame(200, $r->status(), 'Tanggal valid harus menghasilkan 200');
    }

    // Tanggal yang rusak harus validation error, bukan 500.
    public function test_tanggal_rusak_mendapat_validation_error(): void
    {
        $r = $this->actingAs($this->admin)
            ->get('/collecting?tgl_sampai=' . urlencode('bukan-tanggal'));

        $this->assertNotSame(500, $r->status(), 'Malformed date tidak boleh 500');
        // Web request: redirect balik dengan error bag. JSON: 422.
        $this->assertTrue(
            $r->isRedirect() || $r->status() === 422,
            'Malformed date harus redirect-balik (web) atau 422 (JSON), dapat ' . $r->status()
        );
        $this->assertStringNotContainsString('Carbon', (string) $r->getContent(),
            'Error tidak boleh memamerkan nama exception Carbon');
    }

    // Input yang mencurigakan (injeksi-style) untuk tanggal juga harus ditolak
    // sebagai validation error, bukan dilempar ke Carbon::parse.
    public function test_payload_injeksi_di_tanggal_mendapat_validation_error(): void
    {
        $r = $this->actingAs($this->admin)
            ->get('/collecting?tgl_sampai=' . urlencode("2026-01-01' OR '1'='1"));

        $this->assertNotSame(500, $r->status(), 'Payload injeksi tidak boleh 500');
        $this->assertStringNotContainsString('SQLSTATE', (string) $r->getContent());
    }

    // Tanpa tanggal sama sekali: parameter opsional, harus tetap 200.
    public function test_tanpa_tanggal_tetap_berjalan(): void
    {
        $r = $this->actingAs($this->admin)->get('/collecting');

        $this->assertSame(200, $r->status(), 'Tanpa filter tanggal harus tetap 200');
    }

    /* ================================================================
       Kasir monitoring (§8I catch-up): tgl_mulai / tgl_selesai
       ================================================================ */

    public function test_kasir_tanggal_valid_tetap_diterima(): void
    {
        $r = $this->actingAs($this->admin)
            ->get('/kasir/monitoring?tgl_mulai=2026-01-01&tgl_selesai=2026-01-31');

        $this->assertSame(200, $r->status(), 'Tanggal valid harus menghasilkan 200');
    }

    public function test_kasir_tanggal_rusak_mendapat_validation_error(): void
    {
        $r = $this->actingAs($this->admin)
            ->get('/kasir/monitoring?tgl_selesai=' . urlencode('bukan-tanggal'));

        $this->assertNotSame(500, $r->status(), 'Malformed date tidak boleh 500');
        $this->assertTrue(
            $r->isRedirect() || $r->status() === 422,
            'Malformed date harus redirect-balik (web) atau 422 (JSON), dapat ' . $r->status()
        );
        $this->assertStringNotContainsString('Carbon', (string) $r->getContent());
        $this->assertStringNotContainsString('SQLSTATE', (string) $r->getContent());
    }

    public function test_kasir_tanpa_tanggal_tetap_berjalan(): void
    {
        $r = $this->actingAs($this->admin)->get('/kasir/monitoring');

        $this->assertSame(200, $r->status(), 'Tanpa filter tanggal harus tetap 200');
    }

    /* ================================================================
       Riwayat penjualan: from / to
       ================================================================ */

    public function test_riwayat_tanggal_valid_tetap_diterima(): void
    {
        $r = $this->actingAs($this->admin)
            ->get('/penjualan/riwayat?from=2026-01-01&to=2026-01-31');

        $this->assertSame(200, $r->status(), 'Tanggal valid harus menghasilkan 200');
    }

    public function test_riwayat_tanggal_rusak_mendapat_validation_error(): void
    {
        $r = $this->actingAs($this->admin)
            ->get('/penjualan/riwayat?to=' . urlencode('bukan-tanggal'));

        $this->assertNotSame(500, $r->status(), 'Malformed date tidak boleh 500');
        $this->assertTrue(
            $r->isRedirect() || $r->status() === 422,
            'Malformed date harus redirect-balik (web) atau 422 (JSON), dapat ' . $r->status()
        );
        $this->assertStringNotContainsString('Carbon', (string) $r->getContent());
        $this->assertStringNotContainsString('SQLSTATE', (string) $r->getContent());
    }

    /* ================================================================
       Produksi: tgl_dari / tgl_sampai
       ================================================================ */

    public function test_produksi_tanggal_valid_tetap_diterima(): void
    {
        $r = $this->actingAs($this->admin)
            ->get('/produksi?tgl_dari=2026-01-01&tgl_sampai=2026-01-31');

        $this->assertSame(200, $r->status(), 'Tanggal valid harus menghasilkan 200');
    }

    public function test_produksi_tanggal_rusak_mendapat_validation_error(): void
    {
        $r = $this->actingAs($this->admin)
            ->get('/produksi?tgl_sampai=' . urlencode('bukan-tanggal'));

        $this->assertNotSame(500, $r->status(), 'Malformed date tidak boleh 500');
        $this->assertTrue(
            $r->isRedirect() || $r->status() === 422,
            'Malformed date harus redirect-balik (web) atau 422 (JSON), dapat ' . $r->status()
        );
        $this->assertStringNotContainsString('Carbon', (string) $r->getContent());
        $this->assertStringNotContainsString('SQLSTATE', (string) $r->getContent());
    }
}