<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\JamKerjaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JamKerjaMenuEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_hari_libur_memblokir_dan_hari_buka_tidak(): void
    {
        DB::table('jam_kerja')->where('hari', 1)->update(['buka' => false]);
        DB::table('jam_kerja')->where('hari', 2)->update(['buka' => true]);

        $this->assertStringContainsString('Senin', (string) JamKerjaService::alasanTutup(Carbon::parse('2026-09-28 10:00'))); // Senin
        $this->assertNull(JamKerjaService::alasanTutup(Carbon::parse('2026-09-29 10:00')));                                     // Selasa
    }

    public function test_batasi_jam_kerja_memblokir_di_luar_jam(): void
    {
        DB::table('jam_kerja')->where('hari', 2)->update(['buka' => true, 'jam_mulai' => '08:00:00', 'jam_selesai' => '17:00:00']);
        $this->assertNull(JamKerjaService::alasanTutup(Carbon::parse('2026-09-29 20:00'))); // belum dibatasi

        \App\Models\AppSetting::current()->update(['batasi_jam_kerja' => true]);
        $this->assertStringContainsString('Di luar jam kerja', (string) JamKerjaService::alasanTutup(Carbon::parse('2026-09-29 20:00')));
        $this->assertNull(JamKerjaService::alasanTutup(Carbon::parse('2026-09-29 10:00')));
    }

    public function test_halaman_dan_simpan_jam_kerja(): void
    {
        $this->actingAs($this->admin)->get('/pengaturan-jam-kerja')->assertOk()->assertSee('Senin')->assertSee('Minggu');

        $jam = [];
        foreach (range(1, 7) as $h) {
            $jam[$h] = ['buka' => 1, 'mulai' => '09:00', 'selesai' => '18:00'];
        }
        $this->actingAs($this->admin)->post('/pengaturan-jam-kerja', ['jam' => $jam, 'batasi_jam_kerja' => 1])->assertRedirect(route('jam-kerja.index'));

        $this->assertEquals('09:00:00', DB::table('jam_kerja')->where('hari', 7)->value('jam_mulai'));
        $this->assertEquals(1, DB::table('jam_kerja')->where('hari', 7)->value('buka'));
    }

    public function test_menu_kustom_ditambah_tampil_di_sidebar_dan_bisa_dihapus(): void
    {
        $this->actingAs($this->admin)->post('/menu-editor/tambah', ['label' => 'Panduan XYZ', 'url' => 'https://contoh.test/panduan'])->assertRedirect();
        $id = DB::table('menus')->where('jenis', 'kustom')->value('id_menu');
        $this->assertNotNull($id);

        $this->actingAs($this->admin)->get('/dashboard')->assertOk()->assertSee('Panduan XYZ')->assertSee('Menu Tambahan');

        $this->actingAs($this->admin)->delete("/menu-editor/{$id}")->assertRedirect();
        $this->assertEquals(0, DB::table('menus')->where('jenis', 'kustom')->count());
    }

    public function test_menu_nonaktif_hilang_dari_sidebar_tapi_modul_penting_tidak_bisa_disembunyikan(): void
    {
        DB::table('menus')->insertOrIgnore([['id_menu' => 20, 'nama_menu' => 'Pembelian Bahan', 'url_link' => 'pembelian-bahan', 'parent_id' => 0], ['id_menu' => 4, 'nama_menu' => 'Pengaturan Akses', 'url_link' => 'setting-akses', 'parent_id' => 0]]);

        // Kirim form tanpa satu pun "aktif": menu 20 disembunyikan, menu 4 (wajib) tetap aktif.
        $this->actingAs($this->admin)->post('/menu-editor', ['menu' => [20 => ['urutan' => 1], 4 => ['urutan' => 1]]])->assertRedirect();

        $this->assertEquals(0, DB::table('menus')->where('id_menu', 20)->value('aktif'));
        $this->assertEquals(1, DB::table('menus')->where('id_menu', 4)->value('aktif'));
    }

    public function test_url_menu_kustom_berbahaya_ditolak(): void
    {
        $this->actingAs($this->admin)->post('/menu-editor/tambah', ['label' => 'X', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
    }
}
