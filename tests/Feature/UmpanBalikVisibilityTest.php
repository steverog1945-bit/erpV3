<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BEHAVIORAL AUDIT — Rantai visibilitas pesan error.
 *
 * Bukan hanya "server menolak" yang dibuktikan: pesan penolakan harus
 * TAMPak di HTML halaman tujuan redirect-back, bukan cuma di session.
 *
 * - Halaman form bersih (tanpa error di session) TIDAK merender popup.
 * - Halaman yang PUNYA ringkasan $errors sendiri merender pesan secara
 *   inline (dan script popup layout ikut ada untuk di-deduplikasi JS).
 * - Halaman TANPA ringkasan sendiri hanya punya popup layout.
 */
class UmpanBalikVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $idRole = (int) DB::table('roles')->insertGetId(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $idRole]);
    }

    public function test_halaman_bersih_tidak_merender_popup(): void
    {
        $html = $this->actingAs($this->admin)->get('/supplier')->getContent();

        $this->assertStringNotContainsString('Periksa kembali isian', $html);
    }

    public function test_halaman_bersih_kas_kecil_tidak_merender_popup(): void
    {
        $html = $this->actingAs($this->admin)->get('/kas-kecil')->getContent();

        $this->assertStringNotContainsString('Periksa kembali isian', $html);
    }

    public function test_popup_hanya_muncul_setelah_penolakan(): void
    {
        // Gagal POST -> redirect -> popup harus ada dengan pesan asli
        $r = $this->actingAs($this->admin)
            ->from('/supplier')
            ->followingRedirects()
            ->post('/supplier', ['nama_supplier' => '', 'kontak' => '']);

        $html = (string) $r->getContent();
        $this->assertStringContainsString('Periksa kembali isian', $html);
        $this->assertStringContainsString('Nama supplier wajib diisi.', $html);
    }

    public function test_halaman_tanpa_ringkasan_punya_pesan_diPopup_dan_di_field(): void
    {
        // finishing/index menampilkan pesan di level field (<p> text-rose-500)
        // dan popup layout tetap di-deduplikasi JS agar tidak tampil ganda.
        $r = $this->actingAs($this->admin)
            ->from('/finishing')
            ->followingRedirects()
            ->post('/finishing', ['nama_finishing' => '', 'harga_default' => '', 'metode_hitung' => '']);

        $html = (string) $r->getContent();
        $this->assertStringContainsString('Periksa kembali isian', $html);
        $this->assertSame(2, substr_count($html, 'Nama finishing wajib diisi.'),
            'pesan harus ada di elemen field DAN di script popup');
        // penjaga dedupe client-side: popup tidak muncul bila pesan sudah terlihat
        $this->assertStringContainsString('document.body.innerText.indexOf(pesan) !== -1', $html);
    }

    public function test_halaman_dengan_ringkasan_punya_pesan_inline_plus_popup(): void
    {
        // kas-kecil/index merender ringkasan $errors sendiri:
        // pesan harus muncul 2x (inline + script popup layout), JS dedupe
        // yang mencegah popup ganda di browser.
        $r = $this->actingAs($this->admin)
            ->from('/kas-kecil')
            ->followingRedirects()
            ->post('/kas-kecil', ['tanggal' => '']);

        $html = (string) $r->getContent();
        $this->assertStringContainsString('Periksa kembali isian', $html);
        $jumlah = substr_count($html, 'Tanggal wajib diisi.');
        $this->assertGreaterThanOrEqual(2, $jumlah, "pesan muncul {$jumlah}x, harusnya >=2x (inline + popup)");
    }
}
