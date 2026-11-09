<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// §8E - ERROR DISCLOSURE.
//
// Bukti bahwa response error di environment produksi (APP_DEBUG=false) tidak
// membocorkan: path server, SQL, stack trace, credential, APP_KEY, atau nama
// exception. Ini diuji terhadap 404, 403, 405, 401, dan 500 (dari malformed
// date). Rutin ujienv mempakai .env lokal yang sets the same values.
//
// Fix dikirim lewat§8I (malformed date -> 422) terpisah. §8E hanya verify
// behavior bocor, tidak mengubah logic.
class ErrorDisclosureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    // Token yang tidak boleh muncul di respons error apa pun.
    private const FORBIDDEN = [
        'SQLSTATE',
        'syntax error',
        'mysql_',
        '/C:/xampp',
        '/xampp/',
        'vendor/',
        'Stack trace',
        'Exception trace',
        '#0 ',
        '.php:',
        'Ignition',
        'APP_KEY',
        'base64:ey',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
    }

    private function assertTidakBocor(string $isi, string $konteks): void
    {
        foreach (self::FORBIDDEN as $token) {
            $this->assertStringNotContainsString(
                $token,
                $isi,
                "{$konteks}: tidak boleh memuat \"{$token}\""
            );
        }
    }

    public function test_404_tidak_membocorkan_detail(): void
    {
        $r = $this->actingAs($this->admin)->get('/route-yang-pasti-tidak-ada-xyz');

        $this->assertSame(404, $r->status());
        $this->assertTidakBocor((string) $r->getContent(), '404');
    }

    public function test_403_tidak_membocorkan_detail(): void
    {
        $u = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Tanpa Izin'])->id_role,
        ]);

        $r = $this->actingAs($u)->get('/bahan-baku');

        $this->assertSame(403, $r->status());
        $this->assertTidakBocor((string) $r->getContent(), '403');
    }

    public function test_405_tidak_membocorkan_detail(): void
    {
        // POST ke route GET-only.
        $r = $this->actingAs($this->admin)->post('/spk', []);

        $this->assertContains($r->status(), [405, 302]);
        $this->assertTidakBocor((string) $r->getContent(), '405');
    }

    public function test_tanpa_login_tidak_membocorkan_detail(): void
    {
        $r = $this->get('/bahan-baku');

        $this->assertContains($r->status(), [302, 401, 403]);
        $this->assertTidakBocor((string) $r->getContent(), 'tanpa-login');
    }

    // Malformed date sebelumnya menghasilkan 500 dari Carbon::parse. Setelah
    // §8I, validate() menolaknya dulu: redirect-balik dengan error (web) atau
    // 422 (JSON). §8E hanya peduli bahwa tidak ada secret yang bocor.
    public function test_500_tanggal_rusak_tidak_membocorkan_detail(): void
    {
        $r = $this->actingAs($this->admin)
            ->get('/collecting?tgl_sampai=' . urlencode('bukan-tanggal-sama-sekali'));

        $this->assertNotSame(500, $r->status(), 'Setelah §8I, malformed date tidak boleh 500');

        $isi = (string) $r->getContent();
        $this->assertTidakBocor($isi, 'malformed-date');
        $this->assertStringNotContainsString('Carbon', $isi);
    }

    // Format yang berubah → 422, bukan 500. Ini mengunci fix §8I.
    public function test_tanggal_rusak_bukan_500_setelah_perbaikan(): void
    {
        $r = $this->actingAs($this->admin)
            ->get('/collecting?tgl_sampai=' . urlencode('bukan-tanggal-sama-sekali'));

        $this->assertNotSame(500, $r->status());
        $this->assertTrue($r->isRedirect() || $r->status() === 422);
    }

    public function test_app_debug_harus_false(): void
    {
        $this->assertFalse(
            (bool) config('app.debug'),
            'APP_DEBUG harus false'
        );
    }
}