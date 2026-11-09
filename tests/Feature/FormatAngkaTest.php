<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Angka;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Penjaga konsistensi angka di seluruh aplikasi:
 * - tampilan selalu format Indonesia (titik ribuan, koma desimal), nol hanya dipotong dari pecahan;
 * - input uang memakai pemisah ribuan hanya saat mengetik (class format-ribuan / format-ribuan-desimal).
 */
class FormatAngkaTest extends TestCase
{
    use RefreshDatabase;

    public function test_format_tampilan_tidak_memakan_nol_bilangan_bulat(): void
    {
        $this->assertSame('200.000', Angka::format(200000, 0));
        $this->assertSame('200.000', Angka::format(200000.0, 2));
        $this->assertSame('100', Angka::format(100, 0));
        $this->assertSame('10', Angka::format(10, 2));
        $this->assertSame('1.300.000', Angka::format(1300000, 4));
        $this->assertSame('12,5', Angka::format(12.5, 2));
        $this->assertSame('0,98', Angka::format(0.98, 2));
        $this->assertSame('0', Angka::format(0, 2));
        $this->assertSame('1.234,57', Angka::format(1234.5678, 2));
        $this->assertSame('Rp 1.300.000', Angka::rupiah(1300000));
        $this->assertSame('226', Angka::polos('226.000', 4));
        $this->assertSame('12.5', Angka::polos(12.50, 4));
        $this->assertSame('100', Angka::polos(100, 4));
        $this->assertSame('281.057,64', Angka::input(281057.64, 2));
    }

    public function test_parse_menerima_format_indonesia_dan_polos(): void
    {
        $this->assertEquals(1300000.5, Angka::parse('1.300.000,5'));
        $this->assertEquals(12.5, Angka::parse('12.5'));
        $this->assertEquals(500.0, Angka::parse('500'));
        $this->assertEquals(1500.0, Angka::parse('1.500'));
        $this->assertEquals(0.0, Angka::parse(''));
    }

    public function test_volume_stok_400_rim_konversi_500_tampil_200_000_lembar(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->actingAs(User::factory()->create(['id_role' => $role->id_role]));

        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin Uji', 'tipe_mesin' => 'Digital']);
        $kategori = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kat Uji']);
        $rim = DB::table('satuan')->insertGetId(['nama_satuan' => 'rim', 'nilai_konversi' => 1]);
        $lembar = DB::table('satuan')->insertGetId(['nama_satuan' => 'lembar', 'nilai_konversi' => 1]);
        DB::table('bahan_baku')->insert([
            'kode_bahan' => 'KRT-UJI', 'nama_bahan' => 'Kertas Uji', 'id_mesin' => $mesin, 'id_kategori' => $kategori, 'id_satuan_stok' => $rim,
            'id_satuan_hitung' => $lembar, 'nilai_konversi' => 500, 'stok' => 400, 'stok_minimal' => 10, 'satuan_stok' => 'rim',
            'harga_rata_rata' => 1300000, 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $html = $this->get('/bahan-baku')->assertOk()->getContent();

        $this->assertStringContainsString('≈ 200.000 lembar', $html);
        $this->assertStringNotContainsString('≈ 200. lembar', $html);
        $this->assertStringContainsString('Rp 1.300.000', $html);
        $this->assertStringNotContainsString('1,300,000', $html);
    }

    public function test_tidak_ada_number_format_bergaya_amerika_atau_pemotong_nol_yang_salah(): void
    {
        $temuan = [];
        foreach (['app', 'resources'] as $dir) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir))) as $file) {
                if (!str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }
                $path = str_replace('\\', '/', $file->getPathname());
                if (str_ends_with($path, 'app/Support/Angka.php') || str_ends_with($path, 'app/helpers.php')) {
                    continue;
                }
                $src = file_get_contents($file->getPathname());
                if (preg_match('/rtrim\(\s*rtrim\(\s*number_format/', $src)) {
                    $temuan[] = "{$path}: pola rtrim(rtrim(number_format(...))) - pakai angka()";
                }
                // number_format dengan 1-2 argumen = format Amerika ("1,300,000").
                preg_match_all('/number_format\(((?:[^()]|\((?:[^()]|\([^()]*\))*\))*)\)/', $src, $m);
                foreach ($m[1] as $args) {
                    $jumlah = 1;
                    $depth = 0;
                    foreach (str_split($args) as $c) {
                        if ($c === '(') $depth++;
                        elseif ($c === ')') $depth--;
                        elseif ($c === ',' && $depth === 0) $jumlah++;
                    }
                    if ($jumlah < 3) {
                        $temuan[] = "{$path}: number_format({$args}) tanpa pemisah Indonesia - pakai angka()/rupiah()";
                    }
                }
            }
        }

        $this->assertSame([], $temuan, implode("\n", $temuan));
    }

    public function test_input_uang_wajib_berpemisah_ribuan_saat_mengetik(): void
    {
        $uang = '/(harga|nominal|saldo|biaya|diskon|discount|potongan|deposit|\bdp\b|tarif|price|charge|uang|bayar|tunai|kembali|modal|ongkir|ongkos|surcharge|gaji|upah|honor|pembulatan)/i';
        // Bukan uang walau namanya mengandung kata di atas.
        $kecuali = ['edit-harga-qty-min', 'edit-harga-qty-max', 'termin_pembayaran'];
        $temuan = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if (!str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            $off = 0;
            while (preg_match('/<(x-form\.input|input)\b/', $src, $m, PREG_OFFSET_CAPTURE, $off)) {
                [$tag, $akhir] = $this->ambilTag($src, $m[0][1]);
                $off = $akhir + 1;
                if (preg_match('/type=["\'](hidden|checkbox|radio|file|date|datetime-local|time|search|email|password|submit|color)["\']/i', $tag)) {
                    continue;
                }
                if (!preg_match('/(?:name|id)=["\']([^"\']*)["\']/', $tag, $n) || !preg_match($uang, $n[1]) || in_array($n[1], $kecuali, true)) {
                    continue;
                }
                if (!str_contains($tag, 'format-ribuan')) {
                    $temuan[] = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $file->getPathname()) . ': input "' . $n[1] . '" tanpa class format-ribuan/format-ribuan-desimal';
                }
            }
        }

        $this->assertSame([], $temuan, implode("\n", $temuan));
    }

    /** Ambil satu tag input (sadar kutip dan {{ }} Blade). */
    private function ambilTag(string $s, int $i): array
    {
        $n = strlen($s);
        $q = null;
        $blade = 0;
        for ($k = $i; $k < $n; $k++) {
            $c = $s[$k];
            if ($q) {
                if ($c === $q) {
                    $q = null;
                }
                continue;
            }
            if ($c === '{' && ($s[$k + 1] ?? '') === '{') {
                $blade++;
                $k++;
                continue;
            }
            if ($c === '}' && ($s[$k + 1] ?? '') === '}' && $blade) {
                $blade--;
                $k++;
                continue;
            }
            if ($blade) {
                continue;
            }
            if ($c === '"' || $c === "'") {
                $q = $c;
                continue;
            }
            if ($c === '>') {
                return [substr($s, $i, $k - $i + 1), $k];
            }
        }

        return [substr($s, $i), $n];
    }
}
