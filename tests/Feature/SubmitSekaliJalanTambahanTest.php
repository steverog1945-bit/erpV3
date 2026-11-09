<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Kasus tepi pencegah kirim ganda (SubmitSekaliJalan):
 *   - respons JSON berbentuk {success: true} juga dikenali sebagai sukses (master Jersey, harga);
 *   - pesan sukses dari permintaan SEBELUMNYA yang masih di sesi tidak dianggap sukses permintaan ini;
 *   - kunci "sedang diproses" yang tertinggal (proses mati mendadak) tidak mengunci operator lebih dari beberapa menit.
 */
class SubmitSekaliJalanTambahanTest extends TestCase
{
    use RefreshDatabase;

    public int $jalan = 0;
    public ?bool $masihTerkunci = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
        $uji = $this;
        Route::middleware('web')->group(function () use ($uji) {
            Route::post('/uji-kirim/json', function () use ($uji) {
                $uji->jalan++;

                return response()->json(['success' => true, 'id' => $uji->jalan]);
            })->name('uji.json');
            Route::post('/uji-kirim/sukses', function () use ($uji) {
                $uji->jalan++;

                return redirect('/uji-kirim/selesai')->with('success', 'Tersimpan.');
            })->name('uji.sukses');
            Route::post('/uji-kirim/tanpa-pesan', function () use ($uji) {
                $uji->jalan++;

                return redirect('/uji-kirim/selesai');
            })->name('uji.tanpa');
            // Proses yang berjalan sangat lama: selama ia berjalan, kunci "diproses" milik middleware dipegang.
            Route::post('/uji-kirim/lama', function () use ($uji) {
                $uji->travel(4)->minutes();
                $uji->masihTerkunci = Cache::has('submit:uji.lama:uji-kirim/lama:' . auth()->id() . ':l-1:' . md5(serialize([])));

                return redirect('/uji-kirim/selesai');
            })->name('uji.lama');
        });
    }

    public function test_json_success_true_tidak_diproses_dua_kali(): void
    {
        $this->postJson('/uji-kirim/json', ['submit_id' => 'j-1'])->assertOk()->assertJson(['success' => true]);
        $this->postJson('/uji-kirim/json', ['submit_id' => 'j-1'])->assertOk()->assertJson(['success' => true, 'ulang' => true]);

        $this->assertSame(1, $this->jalan);
    }

    public function test_pesan_sukses_lama_di_sesi_tidak_dianggap_sukses_permintaan_ini(): void
    {
        $this->post('/uji-kirim/sukses', ['submit_id' => 's-1'])->assertSessionHas('success');

        // Langsung kiriman berikutnya (tanpa membuka halaman di antaranya): tidak ada pesan sukses baru.
        $this->post('/uji-kirim/tanpa-pesan', ['submit_id' => 't-1']);
        $this->post('/uji-kirim/tanpa-pesan', ['submit_id' => 't-1']);

        $this->assertSame(3, $this->jalan, 'kiriman yang tidak sukses ikut dikunci karena pesan sukses lama');
    }

    public function test_kunci_diproses_yang_tertinggal_lepas_dalam_beberapa_menit(): void
    {
        // Bila proses mati mendadak (batas waktu PHP), kunci tidak pernah dilepas oleh middleware: masa berlakunya
        // sendiri yang harus pendek, supaya operator tidak terkunci 15 menit.
        $this->post('/uji-kirim/lama', ['submit_id' => 'l-1']);

        $this->assertFalse($this->masihTerkunci, 'kunci "diproses" masih berlaku setelah 4 menit');
    }

    public function test_submit_id_sama_dengan_isi_berbeda_tetap_diproses(): void
    {
        // Form yang submit_id-nya tidak berganti (mis. halaman tidak dimuat ulang) mengirim isi BARU: harus disimpan,
        // bukan dijawab "sudah tersimpan" dengan hasil kiriman sebelumnya.
        $this->post('/uji-kirim/sukses', ['submit_id' => 'h-1', 'nominal' => 1000])->assertSessionHas('success');
        $this->post('/uji-kirim/sukses', ['submit_id' => 'h-1', 'nominal' => 2000])->assertSessionHas('success');
        $this->assertSame(2, $this->jalan, 'isi baru dengan submit_id lama tidak disimpan');

        // Isi yang sama persis tetap dianggap kiriman ulang.
        $this->post('/uji-kirim/sukses', ['submit_id' => 'h-1', 'nominal' => 2000]);
        $this->assertSame(2, $this->jalan);
    }
}
