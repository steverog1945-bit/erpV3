<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AUDIT: "Simpan Order Jersey" - loading state, kirim ganda, dan integritas transaksi.
 *
 * Bug yang dilaporkan: tombol Simpan Order tidak punya loading state, sehingga
 * tidak jelas apakah transaksi sedang disimpan. Pertanyaan lanjutannya: apakah
 * tanpa loading itu mungkin terjadi
 *
 *     double click -> 2 request -> 2 order + 2 detail order
 *
 * Test ini tidak membuktikan apa yang "kelihatan benar".:Setiap test menjalankan
 * HTTP sungguhan ke route jersey.orders.store lalu menghitung baris di database.
 *
 * Yang diperiksa:
 *   1. Klik ganda dengan submit_id yang sama -> TEPAT SATU order (SubmitSekaliJalan).
 *   2. Dua tab: isi sama, submit_id sama -> tetap satu order.
 *   3. submit_id sama tapi isi BERBEDA -> dua-duanya diproses (sidik isi), bukan
 *      dijawab hasil lama tanpa menyimpan.
 *   4. Tanpa submit_id sama sekali -> tidak ada proteksi server; dua POST = dua order.
 *      Ini batas yang JUJUR dari proteksi yang ada, bukan hal yang wajar.
 *   5. Submit gagal validasi -> tidak ada order yang tersisa sama sekali (no partial data).
 *   6. Loading state benar-benar ada di markup+JS form Simpan Order.
 */
class JerseyOrderSimpanGandaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idKons;
    private int $tipe;
    private int $kategori;
    private int $bahan;
    private int $lengan;
    private int $kerah;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-05 09:00:00'));

        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);

        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'G', 'keterangan' => '-']);
        $this->idKons = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Klub Uji', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol,
        ]);

        $now = now();
        $ambil = function (string $tabel, string $nama, array $tambahan = []) use ($now): int {
            $id = DB::table($tabel)->where('name', $nama)->value('id');

            return (int) ($id ?: DB::table($tabel)->insertGetId(array_merge(
                ['name' => $nama, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                $tambahan
            )));
        };

        $this->tipe = $ambil('jersey_product_types', 'Jersey Uji', ['has_sleeve_collar' => 1]);
        $this->kategori = $ambil('jersey_categories', 'Futsal Uji');
        $this->bahan = $ambil('jersey_materials', 'Brazil Uji');
        $this->lengan = $ambil('jersey_sleeve_types', 'Pendek', ['price_surcharge' => 0, 'sort_order' => 1]);
        $this->kerah = $ambil('jersey_collar_types', 'O-Neck', ['price_surcharge' => 0, 'sort_order' => 1]);

        foreach (['S', 'M', 'L'] as $i => $u) {
            $ada = DB::table('jersey_sizes')->where('name', $u)->exists();
            if (!$ada) {
                DB::table('jersey_sizes')->insert([
                    'name' => $u, 'sort_order' => $i + 1, 'is_active' => 1,
                    'price_surcharge' => 0, 'kelompok' => 'dewasa', 'price_percent' => 0,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            DB::table('jersey_sizes')->where('name', $u)->update(['is_active' => 1]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pemain(int $n, string $ukuran = 'M'): array
    {
        return [
            'player_name' => "Pemain {$n}",
            'player_number' => (string) $n,
            'size' => $ukuran,
            'gender' => $n % 2 ? 'L' : 'P',
            'jersey_sleeve_type_id' => $this->lengan,
            'jersey_collar_type_id' => $this->kerah,
        ];
    }

    private function payload(array $items, array $ubah = []): array
    {
        $line = array_merge([
            'nama_barang' => 'Jersey Home',
            'jersey_product_type_id' => $this->tipe,
            'jersey_category_id' => $this->kategori,
            'jersey_material_id' => $this->bahan,
            'jersey_sleeve_type_id' => $this->lengan,
            'jersey_collar_type_id' => $this->kerah,
            'thumbnail' => 'jersey_thumbnails/uji.jpg',
            'order_type' => 'player_list',
            'items' => $items,
        ], $ubah);

        return array_merge([
            'order_date' => now()->toDateString(),
            'id_konsumen' => $this->idKons,
            'team_name' => 'Klub Uji FC',
            'lines' => [$line],
        ], $ubah['order_date'] ?? [] ? [] : []);
    }

    private function kirim(array $payload, ?string $submitId): \Illuminate\Testing\TestResponse
    {
        $data = $payload;
        if ($submitId !== null) {
            $data['submit_id'] = $submitId;
        }

        return $this->actingAs($this->admin)->post(route('jersey.orders.store'), $data);
    }

    private function jumlahOrder(): int
    {
        return DB::table('jersey_orders')->count();
    }

    private function jumlahLine(): int
    {
        return DB::table('jersey_order_lines')->count();
    }

    private function jumlahItem(): int
    {
        return DB::table('jersey_order_items')->count();
    }

    /* ================================================================
       1. Klik ganda: satu submit_id -> satu order
       ================================================================ */
    public function test_klik_ganda_submit_id_sama_membuat_satu_order_saja(): void
    {
        $payload = $this->payload([$this->pemain(1), $this->pemain(2), $this->pemain(3)]);
        $submitId = 'uji-klik-ganda-1';

        $pertama = $this->kirim($payload, $submitId);
        $pertama->assertRedirect();

        $kedua = $this->kirim($payload, $submitId);

        // Tidak boleh ada order kedua.
        $this->assertSame(1, $this->jumlahOrder(), 'Kirim ganda dengan submit_id sama harus menghasilkan tepat 1 order');
        $this->assertSame(1, $this->jumlahLine(), 'Detail barang harus tetap 1 baris');
        $this->assertSame(3, $this->jumlahItem(), 'Baris pemain harus tetap 3');

        // Kiriman kedua harus menjawab sukses, bukan diam-diam gagal.
        $kedua->assertRedirect();
        $this->assertStringContainsString(
            'tidak dicatat dua kali',
            (string) session('success'),
            'Kiriman ulang harus diberi tahu bahwa tidak dicatat dua kali'
        );
    }

    /* ================================================================
       2. Dua tab: isi sama -> tetap satu order
       ================================================================ */
    public function test_dua_tab_dengan_isi_sama_tetap_satu_order(): void
    {
        $payload = $this->payload([$this->pemain(1), $this->pemain(2)]);
        $submitId = 'uji-dua-tab-1';

        $this->kirim($payload, $submitId)->assertRedirect();
        $this->kirim($payload, $submitId);
        $this->kirim($payload, $submitId);

        $this->assertSame(1, $this->jumlahOrder(), 'Tiga kiriman dengan submit_id sama harus tetap 1 order');
        $this->assertSame(2, $this->jumlahItem());
    }

    /* ================================================================
       3. submit_id sama, isi BERBEDA -> keduanya diproses
          (sidik isi mencegah hasil lama dipakai untuk kiriman baru)
       ================================================================ */
    public function test_submit_id_sama_tapi_isi_berbeda_kedua_nya_diproses(): void
    {
        $submitId = 'uji-isi-berbeda-1';

        $this->kirim($this->payload([$this->pemain(1), $this->pemain(2)]), $submitId)->assertRedirect();
        $this->kirim($this->payload([$this->pemain(3), $this->pemain(4), $this->pemain(5)]), $submitId)->assertRedirect();

        $this->assertSame(2, $this->jumlahOrder(), 'submit_id sama + isi berbeda harus diproses dua-duanya');
        $this->assertSame(5, $this->jumlahItem());
    }

    /* ================================================================
       4. BATAS PROTESI: tanpa submit_id tidak ada pelindung server
          Test ini sengaja membuktikan BUKTI, bukan asumsi.
       ================================================================ */
    public function test_tanpa_submit_id_dua_post_membuat_dua_order(): void
    {
        $payload = $this->payload([$this->pemain(1), $this->pemain(2)]);

        $this->kirim($payload, null)->assertRedirect();
        $this->kirim($payload, null);

        // SubmitSekaliJalan hanya bekerja bila submit_id ada (middleware baris 44).
        // Artinya: SETIAP form yang bisa disubmit tanpa submit_id adalahlubang.
        $this->assertSame(2, $this->jumlahOrder(), 'Tanpa submit_id proteksi server tidak berlaku - dua order terl creations');
    }

    /* ================================================================
       5. Kegagalan validasi tidak meninggalkan data separuh
       ================================================================ */
    public function test_submit_gagal_validasi_tidak_meninggalkan_order_setengah_simpan(): void
    {
        $payload = $this->payload([$this->pemain(1)], ['thumbnail' => '']);

        $respons = $this->kirim($payload, 'uji-gagal-1');

        $respons->assertSessionHasErrors('lines.0.thumbnail');
        $this->assertSame(0, $this->jumlahOrder(), 'Order tidak boleh tersisa kalau submit gagal');
        $this->assertSame(0, $this->jumlahLine());
        $this->assertSame(0, $this->jumlahItem());
    }

    /* ================================================================
       6. Loading state benar-benar ada di form Simpan Order
       ================================================================ */
    public function test_form_simpan_order_punya_loading_state_dan_guard_klik_ganda(): void
    {
        $html = (string) file_get_contents(resource_path('views/jersey/orders/create.blade.php'));

        $this->assertStringContainsString('sedangMenyimpan', $html, 'Harus ada penanda submit sedang berjalan');
        $this->assertStringContainsString('if (sedangMenyimpan) return;', $html, 'Klik kedua harus ditolak');
        $this->assertStringContainsString('btnSimpan.disabled = true;', $html, 'Tombol harus dinonaktifkan saat submit');
        $this->assertStringContainsString('Menyimpan...', $html, 'Harus ada teks loading');
        $this->assertStringContainsString('btnSimpan.disabled = false;', $html, 'Tombol harus diaktifkan kembali setelah selesai');
    }

    /* ================================================================
       7. Tombol harus bisa diaktifkan kembali walau proses gagal
          (kalau tidak, operator terkunci selamanya)
       ================================================================ */
    public function test_tombol_diapulihkan_bila_validasi_menolak_sebelum_submit(): void
    {
        $html = (string) file_get_contents(resource_path('views/jersey/orders/create.blade.php'));

        // Pagar pengaman harus ada dan hanya menyalakan ulang tombol.
        $this->assertStringContainsString('setTimeout', $html, 'Harus ada pagar pengaman agar tombol tidak terkunci selamanya');
        $this->assertMatchesRegularExpression(
            '/setTimeout\([\s\S]{0,400}btnSimpan\.disabled = false;/',
            $html,
            'Pagar pengaman harus mengembalikan tombol ke keadaan aktif'
        );
    }
}