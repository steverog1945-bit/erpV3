<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\NotifikasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Filter tanggal /spk dan keselarasan lonceng notifikasi dengan tabelnya.
 *
 * Filter tanggal di halaman ini sebelumnya tidak punya satu pun test, sehingga
 * bisa quietly menyaring order yang ada tanpa operator diberi tahu. Test di
 * bawah mengunci perilaku yang disepakati:
 *
 *   - Halaman terbuka tanpa parameter = antrean HARI INI, dan tanggal itu
 *     harus TERTULIS di form filter, bukan kolom kosong.
 *   - Rentang yang dipilih operator dihormati, termasuk order pada detik
 *     terakhir hari tersebut.
 *   - "Tunggakan Aktif" berarti order LAMA yang belum selesai, bukan
 *     gabungan dua syarat yang saling meniadakan.
 *   - Lonceng notifikasi memakai syarat yang sama dengan tabel, sehingga tidak
 *     lagi berbunyi untuk nota yang transaksinya belum dibuka kasir.
 */
class SpkFilterTanggalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $idProduk;

    private int $idKonsumen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);

        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'MESIN UJI', 'tipe_mesin' => 'Direct Print']);
        $this->idProduk = DB::table('produk')->insertGetId([
            'nama_produk' => 'Produk Uji Tanggal', 'metode_hitung' => 'ukuran', 'status' => 'Aktif',
            'id_mesin' => $mesin, 'spk_lengkap' => 1,
        ]);

        $g = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Konsumen Uji Tanggal', 'telepon' => '0812', 'alamat' => '-', 'id_golongan' => $g,
        ]);
    }

    /**
     * Buat nota beserta satu baris SPK. $tglOrder menentukan di mana nota ini
     * harus terlihat; $statusOrder menentukan apakah sudah dibuka kasir.
     */
    private function buatNota(string $nomor, string $tglOrder, string $statusOrder = 'Diterima Kasir', string $statusSpk = 'Antre'): int
    {
        $t = DB::table('transaksi')->insertGetId([
            'nomor_nota' => $nomor, 'tipe_order' => 'Reguler', 'id_konsumen' => $this->idKonsumen,
            'id_user_kasir' => $this->admin->id_user, 'tgl_order' => $tglOrder, 'total_bayar' => 100000,
            'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => $statusOrder,
        ]);

        return DB::table('transaksi_detail')->insertGetId([
            'id_transaksi' => $t, 'id_produk' => $this->idProduk, 'nama_file' => $nomor . '.jpg',
            'p_ukuran' => 100, 'l_ukuran' => 100, 'qty' => 1, 'subtotal' => 100000,
            'status_spk' => $statusSpk,
        ]);
    }

    public function test_tanggal_default_hari_ini_tertulis_di_form_filter(): void
    {
        // Kernanya bug yang dilaporkan: server menyaring HARI INI sementara
        // kolom tanggal di layar kosong, jadi operator mengira tabelnya kosong.
        $html = $this->actingAs($this->admin)->get('/spk')->assertOk()->getContent();

        $hariIni = now()->toDateString();

        $this->assertMatchesRegularExpression(
            '/name="tgl_dari"\s+value="' . preg_quote($hariIni, '/') . '"/',
            $html,
            'kolom "Tanggal Order Dari" harus terisi tanggal hari ini, bukan kosong'
        );
        $this->assertMatchesRegularExpression(
            '/name="tgl_sampai"\s+value="' . preg_quote($hariIni, '/') . '"/',
            $html,
            'kolom "Sampai" harus terisi tanggal hari ini, bukan kosong'
        );
    }

    public function test_default_hari_ini_menyembunyikan_order_kemarin(): void
    {
        // Perilaku yang disengaja: /spk adalah antrean kerja harian.
        $this->buatNota('YARI', now()->subDay()->setTime(10, 0));

        $html = $this->actingAs($this->admin)->get('/spk')->assertOk()->getContent();

        $this->assertStringNotContainsString('YARI', $html, 'order kemarin tidak boleh muncul saat halaman dibuka tanpa filter');
    }

    public function test_rentang_tanggal_yang_dipilih_operator_menampilkan_order_kemarin(): void
    {
        $this->buatNota('YARI', now()->subDay()->setTime(10, 0));

        $html = $this->actingAs($this->admin)
            ->get('/spk?tgl_dari=' . now()->subDay()->toDateString() . '&tgl_sampai=' . now()->toDateString())
            ->assertOk()->getContent();

        $this->assertStringContainsString('YARI', $html, 'operator yang memilih rentang harus bisa melihat order kemarin');
    }

    public function test_tgl_sampai_hari_ini_tetap_memasukkan_order_pukul_235959(): void
    {
        // Batas atas dihitung sebagai 00:00:00 hari berikutnya. Kalau ditempel
        // ' +1 day' ke string hasil format(), MySQL membuang bagian itu dan
        // batasnya turun menjadi < 23:59:59 sehingga order ini hilang.
        $this->buatNota('DETIKTERAKHIR', now()->setTime(23, 59, 59));

        $html = $this->actingAs($this->admin)
            ->get('/spk?tgl_dari=' . now()->toDateString() . '&tgl_sampai=' . now()->toDateString())
            ->assertOk()->getContent();

        $this->assertStringContainsString('DETIKTERAKHIR', $html, 'order pukul 23:59:59 pada hari yang dipilih harus ikut masuk');
    }

    public function test_tunggakan_aktif_menampilkan_order_lama_yang_belum_selesai(): void
    {
        // Kedua syarat lama (rentang tanggal >= hari ini dan tunggakan < hari ini)
        // saling meniadakan, jadi checkbox ini selalu mengembalikan nol baris.
        $this->buatNota('TUNGGAKAN', now()->subDays(5)->setTime(9, 0), 'Diterima Kasir', 'Antre');

        $html = $this->actingAs($this->admin)
            ->get('/spk?tunggakan_aktif=1')->assertOk()->getContent();

        $this->assertStringContainsString('TUNGGAKAN', $html, '"Tunggakan Aktif" harus mengembalikan order lama yang belum selesai');
    }

    public function test_tunggakan_aktif_tidak_menampilkan_order_hari_ini(): void
    {
        $this->buatNota('HARIINI', now()->setTime(10, 0));

        $html = $this->actingAs($this->admin)
            ->get('/spk?tunggakan_aktif=1')->assertOk()->getContent();

        $this->assertStringNotContainsString('HARIINI', $html, '"Tunggakan Aktif" berarti order lama, bukan order hari ini');
    }

    public function test_lonceng_tidak_menghitung_nota_yang_belum_diterima_kasir(): void
    {
        // Nota ini status_spk='Antre' sehingga harusnya masuk hitungan lonceng,
        // tapi transaksinya belum dibuka kasir sehingga tidak akan pernah
        // terlihat di tabel /spk. Menghitungnya membuat lonceng berbunyi dan
        // operator tetap mendapati tabel kosong.
        $this->buatNota('BELUMDITERIMA', now()->setTime(10, 0), 'Menunggu Kasir', 'Antre');
        Cache::flush();

        $items = collect(NotifikasiService::untuk($this->admin)['items'])->keyBy('key');

        $this->assertTrue(
            ! $items->has('spk-antre'),
            'nota berstatus_order Menunggu Kasir tidak boleh dihitung sebagai SPK antre'
        );
    }

    public function test_lonceng_menghitung_nota_yang_sudah_diterima_kasir(): void
    {
        $this->buatNota('DITERIMA', now()->setTime(10, 0), 'Diterima Kasir', 'Antre');
        Cache::flush();

        $items = collect(NotifikasiService::untuk($this->admin)['items'])->keyBy('key');

        $this->assertSame(1, (int) $items->get('spk-antre')['count'] ?? 0);
    }

    public function test_lonceng_tetap_menghitung_order_lama_yang_bukan_hari_ini(): void
    {
        // Lonceng sengaja TIDAK difilter tanggal. Kalau ikut, order tertinggal
        // hilang dari lonceng dan tabel sekaligus lalu menggantung tanpa terlihat.
        $this->buatNota('LAMA', now()->subDays(4)->setTime(10, 0), 'Diterima Kasir', 'Antre');
        Cache::flush();

        $items = collect(NotifikasiService::untuk($this->admin)['items'])->keyBy('key');

        $this->assertSame(1, (int) $items->get('spk-antre')['count'] ?? 0, 'pekerjaan lama yang masih menunggu harus tetap diperingatkan lonceng');
    }

    public function test_pengumuman_tertinggal_muncul_saat_ada_spk_antre_di_luar_rentang(): void
    {
        // Gejala yang dilaporkan: lonceng berbunyi, tabel kosong. Tanpa
        // pengumuman ini operator tidak punya cara tahu kenapa bedanya.
        $this->buatNota('TERTINGGAL', now()->subDays(3)->setTime(10, 0));

        $html = $this->actingAs($this->admin)->get('/spk')->assertOk()->getContent();

        $this->assertStringContainsString('masih antre di luar rentang tanggal ini', $html);
        $this->assertStringContainsString('Tunggakan Aktif', $html, 'pengumuman harus menyebut jalur yang tersedia untuk membukanya');
        $this->assertStringNotContainsString('TERTINGGAL', $html, 'nota di luar rentang memang tidak tampil di tabel');
    }

    public function test_pengumuman_tertinggal_tidak_muncul_saat_semua_spk_antre_masuk_rentang(): void
    {
        $this->buatNota('DALAMRENTANG', now()->setTime(10, 0));

        $html = $this->actingAs($this->admin)->get('/spk')->assertOk()->getContent();

        $this->assertStringNotContainsString('masih antre di luar rentang tanggal ini', $html);
        $this->assertStringContainsString('DALAMRENTANG', $html);
    }

    public function test_tunggakan_aktif_membuka_spk_antre_di_luar_rentang(): void
    {
        $this->buatNota('TERTINGGAL', now()->subDays(3)->setTime(10, 0));

        $html = $this->actingAs($this->admin)
            ->get('/spk?tunggakan_aktif=1')->assertOk()->getContent();

        $this->assertStringContainsString('TERTINGGAL', $html, 'jalur yang disebut pengumuman harus benar-benar membuka pekerjaan tertinggal');
        $this->assertStringNotContainsString('masih antre di luar rentang tanggal ini', $html, 'setelah semua ditampilkan, pengumuman ini harus hilang');
    }

    public function test_tunggakan_aktif_tidak_menampilkan_yang_sudah_selesai_atau_batal(): void
    {
        $this->buatNota('SELESAI', now()->subDays(2)->setTime(10, 0), 'Diterima Kasir', 'Selesai');
        $this->buatNota('DIBATALKAN', now()->subDays(2)->setTime(11, 0), 'Diterima Kasir', 'Batal');

        $html = $this->actingAs($this->admin)
            ->get('/spk?tunggakan_aktif=1')->assertOk()->getContent();

        $this->assertStringNotContainsString('SELESAI', $html);
        $this->assertStringNotContainsString('DIBATALKAN', $html);
    }
}