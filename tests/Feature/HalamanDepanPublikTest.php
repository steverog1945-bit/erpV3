<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\PostKategori;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman depan ala blog: tanpa login hanya tulisan yang terbit DAN ditandai publik yang tampil dan bisa dibuka.
 * Draft, terjadwal, dan tulisan internal tidak boleh bocor lewat daftar, pencarian, maupun alamat langsung.
 */
class HalamanDepanPublikTest extends TestCase
{
    use RefreshDatabase;

    private function tulis(string $judul, array $ubah = []): Post
    {
        return Post::create($ubah + [
            'judul' => $judul, 'slug' => Post::buatSlug($judul), 'ringkasan' => 'Ringkasan ' . $judul, 'isi' => '<p>Isi ' . $judul . '</p>',
            'status' => 'terbit', 'terbit_at' => now()->subDay(), 'tampil_publik' => true, 'menit_baca' => 1,
        ]);
    }

    public function test_halaman_depan_menampilkan_tulisan_publik_saja(): void
    {
        $this->tulis('Promo Spanduk');
        $this->tulis('Hasil Cetak Jersey');
        $this->tulis('SOP Internal Kasir', ['tampil_publik' => false]);
        $this->tulis('Masih Draft', ['status' => 'draft', 'terbit_at' => null]);
        $this->tulis('Terbit Besok', ['terbit_at' => now()->addDay()]);

        $r = $this->get('/');

        $r->assertOk()->assertSee('Promo Spanduk')->assertSee('Hasil Cetak Jersey')->assertSee('Masuk ke sistem');
        $r->assertDontSee('SOP Internal Kasir')->assertDontSee('Masih Draft')->assertDontSee('Terbit Besok');
    }

    public function test_tulisan_publik_bisa_dibuka_tanpa_login_dan_hitungan_baca_naik(): void
    {
        $p = $this->tulis('Promo Spanduk');

        $this->get('/baca/' . $p->slug)->assertOk()->assertSee('Promo Spanduk')->assertSee('Isi Promo Spanduk');

        $this->assertSame(1, (int) $p->fresh()->dibaca);
    }

    public function test_tulisan_internal_draft_dan_terjadwal_tidak_bisa_dibuka_tanpa_login(): void
    {
        $internal = $this->tulis('SOP Internal Kasir', ['tampil_publik' => false]);
        $draft = $this->tulis('Masih Draft', ['status' => 'draft', 'terbit_at' => null]);
        $nanti = $this->tulis('Terbit Besok', ['terbit_at' => now()->addDay()]);

        foreach ([$internal, $draft, $nanti] as $p) {
            $this->get('/baca/' . $p->slug)->assertNotFound();
        }
        // Halaman dalam tetap butuh login.
        $this->get('/postingan/' . $internal->slug)->assertRedirect();
    }

    public function test_saring_kategori_dan_cari_lewat_permintaan_ajax(): void
    {
        $kat = PostKategori::firstOrCreate(['slug' => 'hasil-karya'], ['nama' => 'Hasil Karya']);
        $this->tulis('Tulisan Utama');
        $this->tulis('Jersey Futsal', ['id_kategori' => $kat->id, 'terbit_at' => now()->subDays(2)]);
        $this->tulis('Spanduk Toko', ['terbit_at' => now()->subDays(3)]);
        $this->tulis('Jersey Rahasia', ['id_kategori' => $kat->id, 'tampil_publik' => false]);

        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        $k = $this->get('/?kategori=hasil-karya', $ajax)->assertOk()->json();
        $this->assertSame(1, $k['jumlah']);
        $this->assertStringContainsString('Jersey Futsal', $k['html']);
        $this->assertStringNotContainsString('Jersey Rahasia', $k['html']);

        $c = $this->get('/?cari=spanduk', $ajax)->json();
        $this->assertSame(1, $c['jumlah']);
        $this->assertStringContainsString('Spanduk Toko', $c['html']);

        $this->assertSame(0, $this->get('/?cari=tidakada', $ajax)->json('jumlah'));
    }

    public function test_muat_lagi_memberi_halaman_berikut(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->tulis("Tulisan {$i}", ['terbit_at' => now()->subDays($i)]);
        }

        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];
        $satu = $this->get('/', $ajax)->json();
        $dua = $this->get('/?page=2', $ajax)->json();

        $this->assertSame(2, $satu['berikut']);   // 1 utama + 9 di halaman pertama, sisa 2
        $this->assertNull($dua['berikut']);
        $this->assertStringContainsString('Tulisan 12', $dua['html']);
    }

    public function test_pengguna_yang_sudah_login_tetap_diarahkan_ke_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_ditolak_dari_halaman_dalam_mendapat_form_masuk(): void
    {
        // Checkpoint 4E.1: route('login') menunjuk /masuk, jadi tamu yang
        // ditolak mendarat di endpoint login (bukan homepage).
        $this->get('/dashboard')->assertRedirect(route('login'));

        // Form-nya benar-benar ada dan siap pakai, bukan dialog yang harus
        // dibuka lebih dulu.
        $r = $this->get(route('login'));
        $r->assertOk();
        $r->assertViewIs('masuk');
        $r->assertSee('name="_token"', false);
        $r->assertSee('name="username"', false);
        $r->assertSee('name="password"', false);
    }

    public function test_endpoint_login_menyediakan_form_di_setiap_permintaan(): void
    {
        // /masuk selalu menyediakan form pada setiap permintaan - berbeda dari
        // '/' yang memakai dialog "sekali" saat url.intended masih ada.
        $this->get(route('login'))->assertOk()->assertViewIs('masuk')
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false);

        // Permintaan kedua tetap sama - tidak ada state yang "terpakai".
        $this->get(route('login'))->assertOk()->assertViewIs('masuk')
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false);
    }

    public function test_homepage_buka_dialog_masuk_hanya_sekali_berikutnya(): void
    {
        // Perilaku lama homepage '/' dipertahankan: dialog terbuka sekali
        // setelah ditolak, lalu tidak lagi pada kunjungan berikutnya.
        $this->get('/dashboard')->assertRedirect(route('login'));

        $this->get('/')->assertSee('buka();', false);
        $this->get('/')->assertDontSee('buka();', false);
    }
}
