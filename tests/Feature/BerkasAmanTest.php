<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\BerkasAman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Audit Fase 5 (unggahan): isi berkas diperiksa, bukan hanya namanya. Berkas
 * HTML/skrip yang menyamar sebagai pdf/zip/jpg tidak boleh masuk ke storage
 * publik, dan ekstensi simpan selalu dari daftar putih.
 */
class BerkasAmanTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** Berkas nyata (bukan UploadedFile::fake, yang menebak tipe dari NAMA). */
    private function berkas(string $nama, string $isi): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ujiunggah');
        file_put_contents($tmp, $isi);

        return new UploadedFile($tmp, $nama, null, null, true);
    }

    public function test_html_yang_menyamar_sebagai_pdf_zip_dan_jpg_ditolak(): void
    {
        foreach (['desain.pdf', 'arsip.zip', 'foto.jpg'] as $nama) {
            $pesan = BerkasAman::periksa($this->berkas($nama, '<html><script>alert(1)</script></html>'));
            $this->assertNotNull($pesan, "$nama berisi HTML harus ditolak");
        }
    }

    public function test_php_dan_svg_yang_menyamar_ditolak(): void
    {
        $this->assertNotNull(BerkasAman::periksa($this->berkas('x.zip', '<?php system($_GET["c"]); ?>')));
        $this->assertNotNull(BerkasAman::periksa($this->berkas('logo.png', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>')));
    }

    public function test_gambar_dan_pdf_sungguhan_lolos_dan_ketidakcocokan_ekstensi_ditolak(): void
    {
        $this->assertNull(BerkasAman::periksa($this->berkas('foto.png', base64_decode(self::PNG))));
        $this->assertNull(BerkasAman::periksa($this->berkas('laporan.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF")));

        // PNG asli tetapi bernama .pdf: isi tidak sesuai ekstensi.
        $this->assertNotNull(BerkasAman::periksa($this->berkas('palsu.pdf', base64_decode(self::PNG))));
    }

    public function test_format_desain_biner_boleh_selama_bukan_tipe_terlarang(): void
    {
        // Isi biner tetap, bukan random_bytes: sesekali byte acak terbaca sebagai teks/skrip sehingga tes gagal acak.
        $biner = str_repeat("\x00\xFF\x10\x80\x8B\x03\xC4\x00", 32);
        foreach (['a.ai', 'b.psd', 'c.cdr', 'd.eps'] as $nama) {
            $this->assertNull(BerkasAman::periksa($this->berkas($nama, $biner)), "$nama biner umum harus boleh");
        }
        $this->assertNotNull(BerkasAman::periksa($this->berkas('e.psd', '<html>x</html>')));
    }

    public function test_ekstensi_di_luar_daftar_putih_ditolak(): void
    {
        foreach (['x.exe', 'y.php', 'z.html', 'w.svg', 'v.js', 'u.phtml'] as $nama) {
            $this->assertNotNull(BerkasAman::periksa($this->berkas($nama, 'apa saja')), "$nama harus ditolak");
        }
    }

    public function test_simpan_memakai_ekstensi_daftar_putih_bukan_tebakan_dari_isi(): void
    {
        Storage::fake('public');

        $path = BerkasAman::simpan($this->berkas('desain.pdf', base64_decode(self::PNG)), 'berkas_order/1');

        $this->assertMatchesRegularExpression('#^berkas_order/1/[A-Za-z0-9]{40}\.pdf$#', $path);
        Storage::disk('public')->assertExists($path);

        $this->expectException(\InvalidArgumentException::class);
        BerkasAman::simpan($this->berkas('x.html', '<html>'), 'berkas_order/1');
    }

    public function test_endpoint_berkas_order_menolak_html_berpura_pura_pdf_tanpa_menyimpan_apa_pun(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'G', 'keterangan' => '-']);
        $k = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        $t = DB::table('transaksi')->insertGetId(['nomor_nota' => 'N-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $k, 'id_user_kasir' => $admin->id_user, 'tgl_order' => now(), 'total_bayar' => 1, 'status_pembayaran' => 'Lunas', 'status_nota' => 'Aktif']);

        $this->actingAs($admin)->post("/berkas-order/{$t}/unggah", [
            'kategori' => 'Lainnya', 'file' => $this->berkas('desain.pdf', '<html><script>alert(1)</script></html>'),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, DB::table('order_berkas')->count());
        $this->assertSame([], Storage::disk('public')->allFiles());

        // Berkas sah tetap diterima dan disimpan dengan ekstensi dari daftar putih.
        $this->actingAs($admin)->post("/berkas-order/{$t}/unggah", [
            'kategori' => 'Production File', 'file' => $this->berkas('final.png', base64_decode(self::PNG)),
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('order_berkas')->count());
        $this->assertStringEndsWith('.png', DB::table('order_berkas')->value('file_path'));
    }
}
