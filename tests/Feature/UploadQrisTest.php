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
 * Regresi: upload berkas pada form yang membawa submit_id TIDAK boleh 500.
 * Akar masalah: middleware SubmitSekaliJalan dulu men-serialize input yang
 * membawa objek UploadedFile (via $request->except()) -> "Serialization of
 * 'Illuminate\Http\UploadedFile' is not allowed". Ketiga tes di bawah
 * men-cover (1) upload pertama sukses, (2) kiriman identik dijawab sebagai
 * kiriman ulang tanpa menyimpan dua kali, (3) teks sama tapi BERKAS BERBEDA
 * tetap diproses sebagai kiriman baru.
 */
class UploadQrisTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
        DB::table('roles')->where('nama_role', 'Administrator')->update(['boleh_lihat_harga' => true]);
    }

    public function test_upload_qris_pertama_sukses_tanpa_500(): void
    {
        Storage::fake('public');
        $r = $this->actingAs($this->admin)->post('/bank/qris', [
            'submit_id' => 'submit-qris-1',
            'qris' => UploadedFile::fake()->image('qris.png', 400, 300),
        ]);

        // Sebelumnya: error 500 dari middleware SubmitSekaliJalan.
        $r->assertStatus(302);
        Storage::disk('public')->assertExists('qris/qris.png');
    }

    public function test_kiriman_identik_dijawab_ulang_bukan_diproses_dua_kali(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('qris.png', 400, 300)->size(128);

        $pertama = $this->actingAs($this->admin)->post('/bank/qris', ['submit_id' => 'submit-qris-2', 'qris' => $file]);
        $pertama->assertStatus(302);

        $kedua = $this->actingAs($this->admin)->post('/bank/qris', ['submit_id' => 'submit-qris-2', 'qris' => $file]);

        $kedua->assertStatus(302);
        $this->assertStringContainsString('Kiriman ulang tidak dicatat dua kali.', (string) session('success'));
    }

    public function test_teks_sama_berkas_beda_tetap_diproses_sebagai_kiriman_baru(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin)->post('/bank/qris', [
            'submit_id' => 'submit-qris-3',
            'qris' => UploadedFile::fake()->image('qris.png', 400, 300)->size(128),
        ]);

        $r = $this->actingAs($this->admin)->post('/bank/qris', [
            'submit_id' => 'submit-qris-3',
            'qris' => UploadedFile::fake()->image('qris.png', 320, 240)->size(222),
        ]);

        $r->assertStatus(302);
        // Kiriman baru diproses -> pesan sukses ASLI (bukan "Kiriman ulang ...")
        $this->assertSame('Gambar QRIS berhasil diunggah.', (string) session('success'));
    }
}
