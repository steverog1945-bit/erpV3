<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\KasSesiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rincian closing kas per divisi (mesin).
 *
 * Aturan yang dijaga di sini:
 *
 * 1. SETIAP divisi di master mesin harus muncul di rekap, termasuk yang
 *    nol. Versi lama hanya melakukan GROUP BY dari transaksi_detail, jadi
 *    divisi yang tidak menerima order hari itu lenyap dari laporan - padahal
 *    absennya itu informasi, dan daftar yang berubah-ubah tiap hari tidak
 *    bisa dipakai membandingkan hari ini dengan kemarin.
 *
 * 2. Urutan baris harus stabil: yang ada pekerjaannya dulu, lalu yang nol,
 *    lalu diurutkan nama. Posisi yang bergeser setiap hari membuatoperator
 *    salah baca.
 *
 * 3. Jumlah seluruh divisi harus sama persis dengan total "Nota Order Cetak".
 */
class KasClosingPerDivisiTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private int $idKonsumen;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-15 15:00:00'));
        AppSetting::clearCache();

        $roleKasir = Role::factory()->create(['nama_role' => 'Kasir']);
        $this->kasir = User::factory()->create(['id_role' => $roleKasir->id_role]);
        DB::table('menus')->insertOrIgnore(['id_menu' => 16, 'nama_menu' => 'Kasir', 'url_link' => 'kasir', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $roleKasir->id_role, 'id_menu' => 16]);

        $idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Uji', 'telepon' => '0811', 'alamat' => '-', 'id_golongan' => $idGol]);

        // Tiga divisi. Hanya dua yang akan punya order - "KONICA MINOLTA"
        // sengaja dibiarkan kosong untuk menguji divisi tanpa pekerjaan.
        $this->mesinCetak = DB::table('mesin')->insertGetId(['nama_mesin' => 'EPSON S80670', 'tipe_mesin' => 'Digital']);
        $this->mesinDtf = DB::table('mesin')->insertGetId(['nama_mesin' => 'RHINO DTF', 'tipe_mesin' => 'DTF']);
        DB::table('mesin')->insertGetId(['nama_mesin' => 'KONICA MINOLTA', 'tipe_mesin' => 'Digital']);
    }

    private int $mesinCetak;
    private int $mesinDtf;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function bukaSesi(float $saldoAwal = 100000): int
    {
        return DB::table('kas_harian')->insertGetId([
            'tgl_kas' => '2026-10-15',
            'id_user' => $this->kasir->id_user,
            'saldo_awal' => $saldoAwal,
            'saldo_akhir' => $saldoAwal,
            'status_lock' => 'open',
            'created_at' => '2026-10-15 09:00:00',
            'updated_at' => '2026-10-15 09:00:00',
        ]);
    }

    private function produk(int $idMesin, string $nama): int
    {
        return DB::table('produk')->insertGetId([
            'nama_produk' => $nama,
            'id_mesin' => $idMesin,
            'metode_hitung' => 'ukuran',
        ]);
    }

    private function notaDenganItems(string $nomor, array $items, string $tglOrder, string $status = 'Aktif'): float
    {
        $total = array_sum(array_column($items, 'subtotal'));
        $idT = DB::table('transaksi')->insertGetId([
            'nomor_nota' => $nomor, 'tipe_order' => 'Reguler', 'id_konsumen' => $this->idKonsumen,
            'id_user_kasir' => $this->kasir->id_user, 'tgl_order' => $tglOrder,
            'total_bayar' => $total, 'diskon' => 0, 'status_pembayaran' => 'Lunas',
            'status_nota' => $status, 'status_collecting' => 'Belum Diambil',
        ]);
        foreach ($items as $it) {
            DB::table('transaksi_detail')->insert([
                'id_transaksi' => $idT,
                'id_produk' => $it['id_produk'],
                'nama_file' => $it['nama_file'] ?? 'x',
                'p_ukuran' => 100, 'l_ukuran' => 100, 'qty' => 1, 'qty_cetak' => 1,
                'subtotal' => $it['subtotal'],
                'biaya_cetak' => 0, 'biaya_desain' => 0, 'biaya_finishing' => 0,
            ]);
        }
        return (float) $total;
    }

    public function test_divisi_tanpa_order_tetap_muncul_dengan_nol(): void
    {
        $this->bukaSesi();
        $p = $this->produk($this->mesinCetak, 'Banner 3m');
        $this->notaDenganItems('N-1', [['id_produk' => $p, 'subtotal' => 500000]], '2026-10-15 10:00:00');

        $this->actingAs($this->kasir)->get(route('kasir.preview-closing'))->assertOk();

        // Ketiga divisi harus terlihat.
        $this->actingAs($this->kasir)->get(route('kasir.preview-closing'))
            ->assertSee('EPSON S80670')
            ->assertSee('RHINO DTF')
            ->assertSee('KONICA MINOLTA');

        // Divisi yang kosong ditandai eksplisit, bukan disembunyikan.
        $this->actingAs($this->kasir)->get(route('kasir.preview-closing'))
            ->assertSee('tanpa order');
    }

    public function test_divisi_boleh_tidak_punya_produk(): void
    {
        $this->bukaSesi();
        $p = $this->produk($this->mesinCetak, 'Banner 3m');
        $this->notaDenganItems('N-1', [['id_produk' => $p, 'subtotal' => 100000]], '2026-10-15 10:00:00');

        $this->actingAs($this->kasir)->get(route('kasir.preview-closing'))
            ->assertOk()
            ->assertSee('KONICA MINOLTA');
    }

    public function test_urutan_divisi_bertrabisan(): void
    {
        $this->bukaSesi();
        $pCetak = $this->produk($this->mesinCetak, 'Banner 3m');
        $pDtf = $this->produk($this->mesinDtf, 'DTF Sticker');

        // Cetak lebih besar dari DTF, supaya urutannya bisa diuji.
        $this->notaDenganItems('N-1', [['id_produk' => $pCetak, 'subtotal' => 900000]], '2026-10-15 10:00:00');
        $this->notaDenganItems('N-2', [['id_produk' => $pDtf, 'subtotal' => 250000]], '2026-10-15 11:00:00');

        $html = $this->actingAs($this->kasir)->get(route('kasir.preview-closing'))->assertOk()->getContent();

        $posCetak = strpos($html, 'EPSON S80670');
        $posDtf = strpos($html, 'RHINO DTF');
        $posKosong = strpos($html, 'KONICA MINOLTA');

        $this->assertNotFalse($posCetak, 'EPSON S80670 harus ada');
        $this->assertNotFalse($posDtf, 'RHINO DTF harus ada');
        $this->assertNotFalse($posKosong, 'KONICA MINOLTA harus ada');

        // Yang punya order dulu (berdasarkan nilai), yang nol belakangan.
        $this->assertLessThan($posDtf, $posCetak, 'EPSON (900rb) harus di atas RHINO (250rb).');
        $this->assertLessThan($posKosong, $posDtf, 'Divisi tanpa order harus paling bawah.');
    }

    public function test_jumlah_seluruh_divisi_sama_dengan_total_nota_order(): void
    {
        $this->bukaSesi();
        $pCetak = $this->produk($this->mesinCetak, 'Banner 3m');
        $pDtf = $this->produk($this->mesinDtf, 'DTF Sticker');

        $this->notaDenganItems('N-1', [
            ['id_produk' => $pCetak, 'subtotal' => 400000],
            ['id_produk' => $pDtf, 'subtotal' => 150000],
        ], '2026-10-15 10:00:00');

        $html = $this->actingAs($this->kasir)->get(route('kasir.preview-closing'))->assertOk()->getContent();

        // 400.000 + 150.000 = 550.000, dan total nota order harus sama.
        $this->assertStringContainsString('550.000', $html);
    }

    public function test_nota_dibatalkan_tidak_masuk_rekap(): void
    {
        $this->bukaSesi();
        $p = $this->produk($this->mesinCetak, 'Banner 3m');
        $this->notaDenganItems('N-1', [['id_produk' => $p, 'subtotal' => 700000]], '2026-10-15 10:00:00');
        $this->notaDenganItems('N-2', [['id_produk' => $p, 'subtotal' => 300000]], '2026-10-15 11:00:00', 'Dibatalkan');

        $html = $this->actingAs($this->kasir)->get(route('kasir.preview-closing'))->assertOk()->getContent();

        $this->assertStringContainsString('700.000', $html);
        $this->assertStringNotContainsString('1.000.000', $html, 'Nota batal tidak boleh ikut dihitung.');
    }

    public function test_produk_tanpa_masuk_masuk_bucket_jasa(): void
    {
        $this->bukaSesi();
        $p = $this->produk($this->mesinCetak, 'Banner 3m');
        $this->notaDenganItems('N-1', [['id_produk' => $p, 'subtotal' => 300000]], '2026-10-15 10:00:00');

        // Baris jasa: tanpa id_produk, jadi tidak punya mesin dan masuk
        // ke bucket "Jasa / Lain-lain". id_jasa harus benar-benar ada di tabel
        // jasa (foreign key ON DELETE SET NULL).
        $idJasa = DB::table('jasa')->insertGetId(['nama_jasa' => 'Desain logo']);

        $idT = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'N-JASA', 'tipe_order' => 'Reguler', 'id_konsumen' => $this->idKonsumen,
            'id_user_kasir' => $this->kasir->id_user, 'tgl_order' => '2026-10-15 12:00:00',
            'total_bayar' => 200000, 'diskon' => 0, 'status_pembayaran' => 'Lunas',
            'status_nota' => 'Aktif', 'status_collecting' => 'Belum Diambil',
        ]);
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $idT, 'id_jasa' => $idJasa, 'nama_file' => 'Jasa desain',
            'p_ukuran' => 100, 'l_ukuran' => 100, 'qty' => 1, 'qty_cetak' => 1, 'subtotal' => 200000,
            'biaya_cetak' => 0, 'biaya_desain' => 0, 'biaya_finishing' => 0,
        ]);

        $this->actingAs($this->kasir)->get(route('kasir.preview-closing'))
            ->assertOk()
            ->assertSee('Jasa / Lain-lain');
    }
}
