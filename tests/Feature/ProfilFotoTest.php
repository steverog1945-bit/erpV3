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
 * Reproduksi masalah "profil belum muncul":
 * upload foto profil lewat endpoint asli, lalu GET /storage/profil/<nama>
 * lewat rute berkas privat yang sama dengan yang dipakai <img> di layout.
 */
class ProfilFotoTest extends TestCase
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

    public function test_upload_foto_lalu_get_lewat_rute_privat(): void
    {
        Storage::fake('public');

        $r = $this->actingAs($this->admin)->post('/profil/foto', [
            'foto' => UploadedFile::fake()->image('me.png', 400, 300)->size(80),
        ]);
        $r->assertOk()->assertJson(['ok' => true]);
        $nama = $r->json('nama');
        $this->assertNotEmpty($nama);

        // <img> di layout memuat URL ini - lewat rute /storage/{folder}/{path} (auth).
        $r2 = $this->actingAs($this->admin)->get('/storage/profil/' . $nama);
        $r2->assertOk();
        // BinaryFileResponse adalah streamed - getContent() selalu false. Cek Content-Length saja.
        $this->assertGreaterThan(100, (int) $r2->headers->get('Content-Length'));
    }
}
