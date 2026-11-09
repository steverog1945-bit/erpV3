<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Desain pelanggan dan bukti bayar hanya untuk pengguna yang sudah login; logo tetap publik.
 * (Apache meneruskan /storage/<folder privat>/ ke rute ini; lihat public/.htaccess.)
 */
class BerkasPrivatTest extends TestCase
{
    use RefreshDatabase;

    /** Pengguna dengan menu tertentu (Kasir: menu Penjualan 3, yang memang menangani berkas order). */
    private function user(array $menu = [3], string $nama = 'Kasir'): User
    {
        $role = Role::factory()->create(['nama_role' => $nama]);
        foreach ($menu as $m) {
            DB::table('menus')->insertOrIgnore(['id_menu' => $m, 'nama_menu' => 'Menu ' . $m, 'url_link' => 'menu-' . $m, 'parent_id' => 0]);
            DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => $m, 'can_view' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0]);
        }

        return User::factory()->create(['id_role' => $role->id_role]);
    }

    protected function tearDown(): void
    {
        Storage::disk('public')->deleteDirectory('order_thumbnails/uji-privat');
        Storage::disk('public')->deleteDirectory('kas_kecil_bukti/uji-privat');
        parent::tearDown();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        Storage::disk('public')->put('order_thumbnails/uji-privat/a.jpg', 'x');

        $this->get('/storage/order_thumbnails/uji-privat/a.jpg')->assertRedirect();
    }

    public function test_pengguna_login_menerima_berkas_dengan_nosniff(): void
    {
        Storage::disk('public')->put('order_thumbnails/uji-privat/a.jpg', 'x');

        $r = $this->actingAs($this->user())->get('/storage/order_thumbnails/uji-privat/a.jpg');

        $r->assertOk();
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_berkas_html_dipaksa_diunduh_tanpa_skrip(): void
    {
        Storage::disk('public')->put('order_thumbnails/uji-privat/b.html', '<script>1</script>');

        $r = $this->actingAs($this->user())->get('/storage/order_thumbnails/uji-privat/b.html');

        $r->assertOk();
        $this->assertStringContainsString("script-src 'none'", $r->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));
    }

    public function test_folder_tak_dikenal_dan_penelusuran_direktori_ditolak(): void
    {
        $u = $this->user();

        $this->actingAs($u)->get('/storage/company_logo/x.png')->assertNotFound();
        $this->actingAs($u)->get('/storage/order_thumbnails/..%2F..%2F.env')->assertNotFound();
        $this->actingAs($u)->get('/storage/order_thumbnails/tidak-ada.jpg')->assertNotFound();
    }

    public function test_berkas_hanya_untuk_menu_yang_menanganinya(): void
    {
        Storage::disk('public')->put('order_thumbnails/uji-privat/a.jpg', 'x');
        Storage::disk('public')->put('kas_kecil_bukti/uji-privat/k.jpg', 'x');
        // Staf yang hanya memegang Kas Kecil: boleh melihat bukti Kas Kecil, tidak boleh membuka berkas desain pelanggan.
        $staf = $this->user([39], 'Staf Kas');
        $this->actingAs($staf)->get('/storage/kas_kecil_bukti/uji-privat/k.jpg')->assertOk();
        $this->actingAs($staf)->get('/storage/order_thumbnails/uji-privat/a.jpg')->assertForbidden();
        // Sebaliknya, kasir tanpa menu Kas Kecil tidak boleh membuka bukti Kas Kecil.
        $this->actingAs($this->user())->get('/storage/kas_kecil_bukti/uji-privat/k.jpg')->assertForbidden();
    }
}
