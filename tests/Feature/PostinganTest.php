<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Support\HtmlBersih;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostinganTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
    }

    private function pembaca(): User
    {
        $r = Role::factory()->create(['nama_role' => 'Kasir']);
        DB::table('menus')->insertOrIgnore(['id_menu' => 67, 'nama_menu' => 'Info & Panduan', 'url_link' => 'postingan', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $r->id_role, 'id_menu' => 67, 'can_view' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0]);

        return User::factory()->create(['id_role' => $r->id_role]);
    }

    public function test_html_disaring_dari_skrip_dan_atribut_berbahaya(): void
    {
        $kotor = '<p onclick="x()">Halo <b>tebal</b></p><script>alert(1)</script><img src="javascript:alert(1)"><img src="/storage/posts/isi/a.jpg" onerror="x()"><a href="javascript:alert(1)">jahat</a><a href="https://contoh.id">ok</a><iframe src="x"></iframe>';
        $bersih = HtmlBersih::bersihkan($kotor);

        $this->assertStringNotContainsString('script', $bersih);
        $this->assertStringNotContainsString('onclick', $bersih);
        $this->assertStringNotContainsString('onerror', $bersih);
        $this->assertStringNotContainsString('javascript:', $bersih);
        $this->assertStringNotContainsString('iframe', $bersih);
        $this->assertStringContainsString('<b>tebal</b>', $bersih);
        $this->assertStringContainsString('src="/storage/posts/isi/a.jpg"', $bersih);
        $this->assertStringContainsString('href="https://contoh.id"', $bersih);
    }

    public function test_kelas_tata_letak_editor_lolos_hanya_dari_daftar_putih(): void
    {
        $bersih = HtmlBersih::bersihkan('<p class="ql-align-center ql-spasi-longgar evil-x">a</p><li class="ql-indent-2">b</li><h2 class="ql-align-justify" style="color:red">c</h2><p class="ql-indent-99 bebas">d</p>');

        $this->assertStringContainsString('class="ql-align-center ql-spasi-longgar"', $bersih);
        $this->assertStringContainsString('class="ql-indent-2"', $bersih);
        $this->assertStringContainsString('<h2 class="ql-align-justify">c</h2>', $bersih);
        $this->assertStringNotContainsString('evil-x', $bersih);
        $this->assertStringNotContainsString('bebas', $bersih);
        $this->assertStringNotContainsString('style=', $bersih);
    }

    public function test_format_font_ukuran_warna_lolos_dan_yang_lain_dibuang(): void
    {
        $bersih = HtmlBersih::bersihkan('<p><span class="ql-font-lora ql-size-large ql-color-red ql-bg-yellow ql-color-hack" style="color:red">a</span> H<sub>2</sub>O x<sup>2</sup></p>');

        $this->assertStringContainsString('class="ql-font-lora ql-size-large ql-color-red ql-bg-yellow"', $bersih);
        $this->assertStringNotContainsString('ql-color-hack', $bersih);
        $this->assertStringNotContainsString('style=', $bersih);
        $this->assertStringContainsString('<sub>2</sub>', $bersih);
        $this->assertStringContainsString('<sup>2</sup>', $bersih);
    }

    public function test_admin_membuat_mengedit_dan_menghapus_postingan_dengan_sampul_dan_gambar_isi(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $unggah = $this->post('/postingan/unggah-gambar', ['gambar' => UploadedFile::fake()->create('isi.jpg', 100, 'image/jpeg')])->assertOk()->json();
        $this->assertNotEmpty($unggah['url']);
        Storage::disk('public')->assertExists($unggah['path']);

        $this->post('/postingan', [
            'judul' => 'Aturan Baru Laminasi', 'isi' => '<p>Isi <b>penting</b></p><img src="' . $unggah['url'] . '"><script>x</script>', 'status' => 'terbit',
            'kategori_baru' => 'Info Supplier', 'tags' => 'laminasi, sop, laminasi', 'disematkan' => '1', 'sampul' => UploadedFile::fake()->create('sampul.jpg', 100, 'image/jpeg'),
        ])->assertRedirect('/postingan/aturan-baru-laminasi');

        $p = Post::first();
        $this->assertSame('terbit', $p->status);
        $this->assertTrue($p->disematkan);
        $this->assertSame('laminasi, sop', $p->tags);
        $this->assertStringNotContainsString('<script', $p->isi);
        $this->assertStringContainsString('@@STORAGE@@/posts/isi/', $p->isi);
        $this->assertNotNull($p->gambar_sampul);
        $this->assertDatabaseHas('post_kategori', ['slug' => 'info-supplier']);

        $this->get('/postingan/aturan-baru-laminasi')->assertOk()->assertSee('Aturan Baru Laminasi')->assertSee('penting')->assertSee('storage/posts/isi/', false);

        $this->put("/postingan/{$p->id}", ['judul' => 'Aturan Laminasi (Revisi)', 'isi' => '<p>Isi baru</p>', 'status' => 'terbit', 'hapus_sampul' => '1'])->assertRedirect('/postingan/aturan-baru-laminasi');
        $p->refresh();
        $this->assertSame('Aturan Laminasi (Revisi)', $p->judul);
        $this->assertNull($p->gambar_sampul);
        $this->assertSame('aturan-baru-laminasi', $p->slug, 'slug stabil agar tautan lama tidak putus');

        $this->delete("/postingan/{$p->id}")->assertRedirect('/postingan');
        $this->assertSoftDeleted('posts', ['id' => $p->id]);
    }

    public function test_daftar_memakai_pagination_bernomor_dan_pembaca_tidak_melihat_draft_atau_terjadwal(): void
    {
        $admin = $this->admin();
        foreach (range(1, 12) as $i) {
            Post::create(['judul' => "Postingan $i", 'slug' => "postingan-$i", 'isi' => '<p>x</p>', 'status' => 'terbit', 'terbit_at' => now()->subMinutes(30 - $i), 'id_penulis' => $admin->id_user]);
        }
        Post::create(['judul' => 'Rahasia Draft', 'slug' => 'rahasia-draft', 'isi' => '<p>x</p>', 'status' => 'draft', 'id_penulis' => $admin->id_user]);
        Post::create(['judul' => 'Belum Waktunya', 'slug' => 'belum-waktunya', 'isi' => '<p>x</p>', 'status' => 'terbit', 'terbit_at' => now()->addDay(), 'id_penulis' => $admin->id_user]);

        $pembaca = $this->pembaca();
        $hal1 = $this->actingAs($pembaca)->get('/postingan')->assertOk();
        $hal1->assertDontSee('Rahasia Draft')->assertDontSee('Belum Waktunya')->assertSee('Postingan 12');
        $this->get('/postingan?page=2')->assertOk()->assertSee('Menampilkan');
        $this->get('/postingan/rahasia-draft')->assertNotFound();
        $this->get('/postingan/belum-waktunya')->assertNotFound();
        $this->get('/postingan/create')->assertForbidden();
        $this->post('/postingan', ['judul' => 'x', 'isi' => '<p>x</p>', 'status' => 'draft'])->assertForbidden();

        // Pengelola melihat draft & terjadwal dan bisa menyaring per status.
        $this->actingAs($admin);
        $this->get('/postingan?status=draft')->assertOk()->assertSee('Rahasia Draft');
        $this->get('/postingan?status=terjadwal')->assertOk()->assertSee('Belum Waktunya');
        $this->get('/postingan/rahasia-draft')->assertOk();
    }

    public function test_pencarian_kategori_tag_dan_kartu_dashboard_info_terbaru(): void
    {
        $admin = $this->admin();
        $kat = DB::table('post_kategori')->where('slug', 'pengumuman')->value('id');
        Post::create(['judul' => 'Libur Nasional', 'slug' => 'libur', 'isi' => '<p>toko tutup</p>', 'status' => 'terbit', 'terbit_at' => now()->subHour(), 'id_kategori' => $kat, 'tags' => 'libur', 'disematkan' => true, 'id_penulis' => $admin->id_user]);
        Post::create(['judul' => 'Tips Doff', 'slug' => 'tips-doff', 'isi' => '<p>laminasi doff</p>', 'status' => 'terbit', 'terbit_at' => now()->subHour(), 'id_penulis' => $admin->id_user]);

        $this->actingAs($admin);
        $this->get('/postingan?cari=doff')->assertOk()->assertSee('Tips Doff')->assertDontSee('Libur Nasional');
        $this->get('/postingan?kategori=pengumuman')->assertOk()->assertSee('Libur Nasional')->assertDontSee('Tips Doff');
        $this->get('/postingan?tag=libur')->assertOk()->assertSee('Libur Nasional');
        $this->get('/dashboard')->assertOk()->assertSee('Info Terbaru')->assertSee('Libur Nasional');
    }
}
