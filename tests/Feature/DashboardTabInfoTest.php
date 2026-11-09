<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTabInfoTest extends TestCase
{
    use RefreshDatabase;

    public function test_tab_info_di_dashboard_untuk_semua_role_dan_menu_kelola_hanya_untuk_pengelola(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        foreach (range(1, 11) as $i) {
            Post::create(['judul' => "Info $i", 'slug' => "info-$i", 'isi' => '<p>x</p>', 'status' => 'terbit', 'terbit_at' => now()->subMinutes(20 - $i), 'id_penulis' => $admin->id_user]);
        }

        $kasir = Role::factory()->create(['nama_role' => 'Kasir']);
        DB::table('menus')->insertOrIgnore(['id_menu' => 67, 'nama_menu' => 'Info & Panduan', 'url_link' => 'postingan', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $kasir->id_role, 'id_menu' => 67, 'can_view' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0]);
        $pembaca = User::factory()->create(['id_role' => $kasir->id_role]);

        // Pembaca: tab Info memuat daftar (unggulan + grid) dan pagination tetap di alamat Dashboard; tidak ada menu Kelola.
        $res = $this->actingAs($pembaca)->get('/dashboard?tab=info')->assertOk();
        $res->assertSee('Papan Informasi Toko')->assertSee('Info 11')->assertSee('Ringkasan')->assertDontSee('Kelola Postingan')->assertDontSee('Tulis Postingan');
        $this->get('/dashboard?tab=info&page=2')->assertOk()->assertSee('Menampilkan')->assertSee('/dashboard?', false);
        $this->get('/dashboard')->assertOk()->assertDontSee('Papan Informasi Toko');

        // Administrator: menu Kelola Postingan tetap ada + tombol Tulis Postingan di tab.
        $this->actingAs($admin)->get('/dashboard?tab=info')->assertOk()->assertSee('Kelola Postingan')->assertSee('Tulis Postingan');
    }
}
