<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogoSidebarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->actingAs(User::factory()->create(['id_role' => $role->id_role]));
        AppSetting::clearCache();
    }

    private function simpan(array $ekstra = [])
    {
        return $this->post(route("pengaturan-aplikasi.update"), $ekstra + ['nama_aplikasi' => 'Percetakan Uji']);
    }

    public function test_opsi_tampilan_logo_tersimpan_dan_dipakai_di_sidebar(): void
    {
        $this->simpan([
            'logo' => UploadedFile::fake()->create('logo.png', 50, 'image/png'),
            'logo_ukuran' => 'besar', 'logo_gaya' => 'kaca', 'logo_ringkas' => 'penuh',
        ])->assertSessionHas('success');

        $s = AppSetting::current();
        $this->assertSame(['besar', 'kaca', 'penuh'], [$s->logo_ukuran, $s->logo_gaya, $s->logo_ringkas]);

        // Command Center V3: sidebar memakai markup .sidebar-brandchip > img.brandchip-logo
        // (mode ringkas + kelas logo-ukuran/gaya dihapus), jadi yang diperiksa adalah
        // gambar logo yang benar-benar tampil di sidebar.
        $this->get('/dashboard')->assertOk()
            ->assertSee('brandchip-logo', false)
            ->assertSee('storage/company_logo/', false)
            ->assertDontSee('v1.0 Printing System');
    }

    public function test_nilai_tidak_valid_ditolak_dan_default_dipakai_tanpa_logo(): void
    {
        $this->simpan(['logo_gaya' => 'neon'])->assertSessionHasErrors('logo_gaya');

        // Tanpa logo: sidebar memakai ikon inline SVG bawaan .sidebar-brandchip.
        // (assertSee ke "brandchip-logo" akan cocok juga dengan selector di <style>,
        // jadi yang diperiksa adalah tag <img> spesifik dan SVG fallback-nya.)
        $this->get('/dashboard')->assertOk()
            ->assertSee('viewBox="0 0 36 36"', false)
            ->assertDontSee('<img src="' . asset('storage/'), false);
    }

    public function test_logo_ikon_diunggah_disimpan_dan_bisa_dihapus(): void
    {
        // Logo ikon hanya ditampilkan di halaman Pengaturan Aplikasi (logo
        // ringkas sidebar sudah tidak dipakai di Command Center V3), jadi
        // yang diperiksa: tersimpan + tampil di pengaturan + bisa dihapus.
        $this->simpan(['logo' => UploadedFile::fake()->create('logo.png', 50, 'image/png'), 'logo_ikon' => UploadedFile::fake()->create('ikon.png', 20, 'image/png')]);
        $path = AppSetting::current()->logo_ikon_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->get(route('pengaturan-aplikasi.edit'))->assertSee('storage/company_logo/', false);

        $this->simpan(['hapus_logo_ikon' => '1']);
        $this->assertNull(AppSetting::current()->logo_ikon_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_halaman_pengaturan_menampilkan_opsi_logo(): void
    {
        $this->get(route('pengaturan-aplikasi.edit'))->assertOk()->assertSee('Tampilan Logo di Sidebar')->assertSee('Gaya Latar Logo');
    }
}
