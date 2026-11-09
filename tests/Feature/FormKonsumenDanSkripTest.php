<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * - Modal Tambah dan Ubah Konsumen berisi isian yang sama (dulu Tipe Konsumen hanya ada di Ubah).
 * - Skrip di view tidak boleh memanggil classList dengan nama kelas kosong: browser melempar error dan
 *   sisa fungsi berhenti (penyebab produk Input Order tetap terkunci setelah kategori dipilih).
 */
class FormKonsumenDanSkripTest extends TestCase
{
    use RefreshDatabase;

    public function test_modal_tambah_dan_ubah_konsumen_punya_isian_yang_sama(): void
    {
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
        $html = $this->get('/konsumen')->assertOk()->getContent();

        $isian = function (string $idModal) use ($html) {
            $this->assertMatchesRegularExpression('/id="' . $idModal . '"/', $html);
            $mulai = strpos($html, 'id="' . $idModal . '"');
            $blok = substr($html, $mulai, strpos($html, '</form>', $mulai) - $mulai);
            preg_match_all('/<(?:input|select|textarea)\b[^>]*\bname="([^"_][^"]*)"/', $blok, $m);

            return collect($m[1])->reject(fn ($n) => $n === '_method')->sort()->values()->all();
        };

        $tambah = $isian('modal-tambah-konsumen');
        $this->assertContains('tipe_konsumen', $tambah);
        $this->assertSame($tambah, $isian('modal-edit-konsumen'));
    }

    public function test_tambah_konsumen_menyimpan_tipe(): void
    {
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
        $gol = DB::table('golongan_konsumen')->value('id_golongan') ?? DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Umum']);

        $this->post('/konsumen', ['nama_konsumen' => 'Toko Maju', 'telepon' => '0812345', 'id_golongan' => $gol, 'tipe_konsumen' => 'permanent'])
            ->assertSessionHas('success');
        $this->assertSame('permanent', DB::table('konsumen')->where('nama_konsumen', 'Toko Maju')->value('tipe_konsumen'));

        $this->post('/konsumen', ['nama_konsumen' => 'Salah', 'telepon' => '0812', 'id_golongan' => $gol, 'tipe_konsumen' => 'vip'])
            ->assertSessionHasErrors('tipe_konsumen');
    }

    public function test_tidak_ada_classlist_dengan_nama_kelas_kosong(): void
    {
        $temuan = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $f) {
            if (! str_ends_with($f->getFilename(), '.blade.php')) {
                continue;
            }
            foreach (file($f->getPathname()) as $i => $baris) {
                if (preg_match('/classList\.\w+\([^)]*(\'\'|"")/', $baris) && ! preg_match('/classList\.toggle\([^,]+,[^)]*(\'\'|"")/', $baris)) {
                    $temuan[] = str_replace(resource_path('views'), '', $f->getPathname()) . ':' . ($i + 1);
                }
            }
        }
        $this->assertSame([], $temuan, 'classList dengan nama kelas kosong melempar error di browser');
    }

    public function test_isian_yang_dipulihkan_browser_disinkronkan_dan_konsumen_transaksi_tidak_bisa_dikosongkan_sekali_klik(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringContainsString('window.sinkronkanIsianPulih', $layout);
        $this->assertStringContainsString("nav.type !== 'reload'", $layout);

        foreach (['penjualan/create.blade.php', 'penjualan-barang/create.blade.php', 'jersey/orders/create.blade.php'] as $f) {
            $isi = file_get_contents(resource_path('views/' . $f));
            $this->assertStringNotContainsString('allowClear', $isi, $f . ': konsumen transaksi tidak boleh punya tombol hapus');
        }
    }

    /** Tombol yang memanggil fungsi tak terdefinisi tidak bereaksi sama sekali (dulu: ubah/hapus Pemasok). */
    public function test_fungsi_di_atribut_onclick_terdefinisi(): void
    {
        $views = resource_path('views');
        $umum = file_get_contents(resource_path('js/app.js'));
        foreach (['layouts', 'components', 'partials'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($views . '/' . $dir)) as $f) {
                if (str_ends_with($f->getFilename(), '.blade.php')) {
                    $umum .= file_get_contents($f->getPathname());
                }
            }
        }
        $bawaan = ['alert', 'confirm', 'setTimeout', 'parseInt', 'parseFloat', 'history', 'location', 'window', 'document', 'event', 'this', 'return', 'if', 'Swal', 'fetch', 'print', 'open', 'close', 'Number'];

        $muat = function (string $berkas, array &$sudah) use (&$muat, $views): string {
            if (isset($sudah[$berkas]) || ! is_file($berkas)) {
                return '';
            }
            $sudah[$berkas] = true;
            $isi = file_get_contents($berkas);
            preg_match_all("/@include(?:If)?\(\s*'([^']+)'/", $isi, $m);
            foreach ($m[1] as $nama) {
                $isi .= $muat($views . '/' . str_replace('.', '/', $nama) . '.blade.php', $sudah);
            }

            return $isi;
        };

        $temuan = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($views)) as $f) {
            $jalur = str_replace(DIRECTORY_SEPARATOR, '/', $f->getPathname());
            if (! str_ends_with($jalur, '.blade.php') || preg_match('#/(layouts|components|partials)/#', $jalur) || str_starts_with($f->getFilename(), '_')) {
                continue;
            }
            $sudah = [];
            $gabung = $muat($f->getPathname(), $sudah) . $umum;
            preg_match_all('/\bon(?:click|change|input|submit)\s*=\s*["\']\s*(?:return\s+)?([A-Za-z_$][\w$]*)\s*\(/', file_get_contents($f->getPathname()), $m);
            foreach (array_unique($m[1]) as $fn) {
                if (in_array($fn, $bawaan, true)) {
                    continue;
                }
                $q = preg_quote($fn, '/');
                if (! preg_match('/function\s+' . $q . '\b|\b' . $q . '\s*=\s*(async\s*)?(function|\(|[A-Za-z_$][\w$]*\s*=>)|window\.' . $q . '\s*=/', $gabung)) {
                    $temuan[] = str_replace(str_replace(DIRECTORY_SEPARATOR, '/', $views), '', $jalur) . ': ' . $fn . '()';
                }
            }
        }
        $this->assertSame([], $temuan, 'Fungsi dipanggil dari atribut tetapi tidak didefinisikan');
    }

    /** Kolom isian setinggi 38px; tombol Filter/reset 46px membuat baris penyaring tidak sejajar. */
    public function test_tombol_baris_penyaring_setinggi_kolom_isian(): void
    {
        $temuan = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $f) {
            if (str_ends_with($f->getFilename(), '.blade.php') && str_contains(file_get_contents($f->getPathname()), 'class="h-[46px] px-')) {
                $temuan[] = $f->getFilename();
            }
        }
        $this->assertSame([], $temuan);
    }
}
