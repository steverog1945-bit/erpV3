<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Upload berkas desain (gambar acuan order).
 *
 * Dua hal yang dikunci test ini:
 *
 * 1. FORMAT BERKAS LEBAR. Dulu upload hanya menerima gambar raster
 *    (jpg/jpeg/png/webp) - berkas desain seperti PDF & CDR ditolak, padahal
 *    justru format yang dipakai desainer untuk kirim file sumber. Sekarang
 *    semua format gambar umum plus dokumen desain (pdf, cdr, ai, eps, psd)
 *    diterima. Berkas berbahaya (php, exe) tetap ditolak.
 *
 * 2. UKURAN GAMBAR DI CETAK SPK. Dulu gambar dikompres di browser sampai
 *    10 KB dengan sisi terpanjang 480 px - terlalu kecil, surat & detail di
 *    dalam desain jadi pecah saat dicetak di SPK. Sekarang targetnya 200 KB
 *    dengan resolusi jauh lebih besar.
 */
class UploadBerkasDesainTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);

        Storage::fake('public');
    }

    private function upload(string $nama, int $kb = 50): \Illuminate\Testing\TestResponse
    {
        // Header Accept: application/json - sama seperti fetch() di layar,
        // supaya balasannya JSON (tanpa header ini Laravel mengalihkan).
        return $this->actingAs($this->admin)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/penjualan/upload-thumbnail', [
                'thumbnail' => UploadedFile::fake()->create($nama, $kb),
            ]);
    }

    public function test_pdf_boleh_diunggah_dan_ekstensinya_dipakai(): void
    {
        $r = $this->upload('desain-akhir.pdf')->assertOk();

        $r->assertJson(['status' => 'success', 'bisa_ditampilkan' => false]);
        $this->assertStringEndsWith('.pdf', $r->json('path'), 'ekstensi asli harus dipertahankan');
        Storage::disk('public')->assertExists($r->json('path'));
    }

    public function test_cdr_boleh_diunggah_dan_ekstensinya_dipakai(): void
    {
        $r = $this->upload('desain-akhir.cdr')->assertOk();

        $r->assertJson(['status' => 'success', 'bisa_ditampilkan' => false]);
        $this->assertStringEndsWith('.cdr', $r->json('path'), 'ekstensi asli harus dipertahankan');
        Storage::disk('public')->assertExists($r->json('path'));
    }

    public function test_tif_boleh_diunggah_dan_bisa_ditampilkan_sebagai_gambar(): void
    {
        $r = $this->upload('desain-akhir.tif')->assertOk();

        $r->assertJson(['status' => 'success', 'bisa_ditampilkan' => true]);
        $this->assertStringEndsWith('.tif', $r->json('path'), 'ekstensi asli harus dipertahankan');
        Storage::disk('public')->assertExists($r->json('path'));
    }

    public function test_tiff_boleh_diunggah_dan_bisa_ditampilkan_sebagai_gambar(): void
    {
        $r = $this->upload('desain-akhir.tiff')->assertOk();

        $r->assertJson(['status' => 'success', 'bisa_ditampilkan' => true]);
        $this->assertStringEndsWith('.tiff', $r->json('path'), 'ekstensi asli harus dipertahankan');
        Storage::disk('public')->assertExists($r->json('path'));
    }

    public function test_psd_boleh_diunggah_tapi_tidak_bisa_ditampilkan(): void
    {
        $r = $this->upload('desain-akhir.psd')->assertOk();

        $r->assertJson(['status' => 'success', 'bisa_ditampilkan' => false]);
        $this->assertStringEndsWith('.psd', $r->json('path'), 'ekstensi asli harus dipertahankan');
        Storage::disk('public')->assertExists($r->json('path'));
    }

    public function test_semua_format_gambar_umum_boleh_diunggah(): void
    {
        foreach (['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tif', 'tiff', 'heic', 'ai', 'eps', 'psd'] as $ext) {
            $r = $this->upload('desain.' . $ext)->assertOk();
            $this->assertStringEndsWith('.' . $ext, $r->json('path'));
        }
    }

    public function test_svg_ditolak_karena_bisa_membawa_skrip(): void
    {
        $this->upload('desain.svg')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['thumbnail']);
    }

    public function test_berkas_berbahaya_ditolak(): void
    {
        foreach (['jahat.php', 'virus.exe', 'skrip.sh'] as $nama) {
            $this->upload($nama)
                ->assertStatus(422)
                ->assertJsonValidationErrors(['thumbnail']);
        }
    }

    public function test_berkas_kosong_masih_berfungsi_sebagai_error_validasi(): void
    {
        // Kontrak lama harus tetap sama: 422 + errors.thumbnail (dipakai
        // test UmpanBalikAjaxTest).
        $this->actingAs($this->admin)
            ->postJson('/penjualan/upload-thumbnail', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['thumbnail']);
    }

    public function test_target_kompresi_gambar_naik_ke_200_kb_dengan_resolusi_lebih_besar(): void
    {
        $isi = file_get_contents(resource_path('views/partials/image-compress-script.blade.php'));

        $this->assertStringContainsString('TARGET_BYTES_GAMBAR = 200 * 1024', $isi, 'target kompresi harus 200 KB');
        $this->assertStringNotContainsString('targetBytes = 10 * 1024', $isi, 'target lama 10 KB harus hilang');
        $this->assertStringContainsString('DIMENSI_GAMBAR = [1600,', $isi, 'resolusi harus jauh lebih besar dari 480 px');
    }

    /**
     * PDF harus diubah jadi GAMBAR sebelum diunggah.
     *
     * PDF tidak bisa dibuka peramban sebagai gambar, dan tidak ada konverter
     * PDF di server ini - jadi PDF dirender sendiri di peramban memakai pdf.js
     * (halaman pertama), lalu dikirim sebagai JPEG. Berkas yang tersimpan di
     * server dengan demikian berupa gambar, dan bisa langsung ditampilkan di
     * SPK.
     */
    public function test_pdf_dirender_jadi_gambar_di_peramban(): void
    {
        $isi = file_get_contents(resource_path('views/partials/image-compress-script.blade.php'));

        $this->assertStringContainsString('function rasterisasiPdf(', $isi, 'fungsi rasterisasi PDF harus ada');
        $this->assertStringContainsString('pdf.min.js', $isi, 'pdf.js harus dimuat dari vendor');
        $this->assertStringContainsString('pdf.worker.min.js', $isi, 'worker pdf.js harus dipasang');
        $this->assertStringContainsString("endsWith('.pdf')", $isi, 'PDF harus dikenali dan dirender');

        // Berkas vendor-nya harus benar-benar ada di public/vendor.
        $this->assertFileExists(public_path('vendor/pdfjs/pdf.min.js'));
        $this->assertFileExists(public_path('vendor/pdfjs/pdf.worker.min.js'));
    }

    /**
     * Format berkas desain yang TIDAK bisa jadi gambar harus tetap disimpan
     * apa adanya (sebagai sumber desain), bukan ditulis dengan ekstensi lain.
     *
     * Untuk CDR tidak ada konverter di server ini: tidak ada ImageMagick,
     * Ghostscript, maupun LibreOffice - dan thumbnail Windows hanya
     * menghasilkan ikon file generik (bukan isi desainnya), sehingga dipakai
     * akan menampilkan lembar kosong di SPK. Jadi CDR disimpan sebagai CDR.
     */
    public function test_cdr_tetap_disimpan_sebagai_berkas_desain(): void
    {
        $r = $this->upload('desain-akhir.cdr')->assertOk();

        $r->assertJson(['status' => 'success', 'bisa_ditampilkan' => false]);
        $this->assertStringEndsWith('.cdr', $r->json('path'), 'CDR harus tetap berformat CDR');
    }

    public function test_halaman_input_pesanan_menerima_semua_format_berkasa(): void
    {
        $html = $this->actingAs($this->admin)->get('/penjualan/kasir')->assertOk()->getContent();

        // Input file harus menerima dokumen desain, bukan cuma gambar raster.
        $this->assertStringContainsString('.pdf', $html);
        $this->assertStringContainsString('.cdr', $html);
        $this->assertStringNotContainsString('accept="image/png,image/jpeg,image/webp"', $html, 'pembatasan format lama harus dilepas');
    }

    public function test_spk_berkas_desain_non_gambar_tidak_menampilkan_gambar_rusak(): void
    {
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'MESIN UJI', 'tipe_mesin' => 'Direct Print']);
        $produk = DB::table('produk')->insertGetId(['nama_produk' => 'Produk Uji', 'metode_hitung' => 'ukuran', 'status' => 'Aktif', 'id_mesin' => $mesin, 'spk_lengkap' => 1]);
        $g = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $k = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Konsumen Uji', 'telepon' => '0811', 'alamat' => '-', 'id_golongan' => $g]);
        $t = DB::table('transaksi')->insertGetId(['nomor_nota' => 'N-UPL-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $k, 'id_user_kasir' => $this->admin->id_user, 'tgl_order' => now(), 'total_bayar' => 1, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir']);
        $idDetail = DB::table('transaksi_detail')->insertGetId([
            'id_transaksi' => $t, 'id_produk' => $produk, 'nama_file' => 'desain akhir',
            'thumbnail' => 'order_thumbnails/abc123.pdf', 'p_ukuran' => 100, 'l_ukuran' => 100, 'qty' => 1,
            'subtotal' => 1, 'status_spk' => 'Antre',
        ]);

        $html = $this->actingAs($this->admin)->get("/spk/{$idDetail}/print/lengkap")->assertOk()->getContent();

        // Peramban tidak bisa menampilkan PDF sebagai <img> - jadi SPK harus
        // menampilkan keterangan formatnya, bukan gambar rusak.
        $this->assertStringNotContainsString('<img src="' . asset('storage/order_thumbnails/abc123.pdf') . '"', $html);
        $this->assertStringContainsString('Berkas desain', $html);
        $this->assertStringContainsString('PDF', $html);
    }
}
