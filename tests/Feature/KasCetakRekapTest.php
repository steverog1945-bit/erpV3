<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Opsi cetak rekap closing, dan pemisahan riwayatnya dari Kelola Kas.
 *
 * Aturan yang dijaga:
 *
 * 1. Setiap closing harus menyisakan pintasan cetak. Rekap harian diambil
 *    tepat setelah uang dihitung, jadi kalau pilihan cetak hanya ada di
 *    halaman lain, kasir harus mencarinya - dan sering tidak jadi dicetak.
 *
 * 2. Flash "sesi baru ditutup" hanya hidup sekali. Begitu kasir membuka
 *    sesi berikutnya, rekap yang barusan ditutup harus tetap bisa dicetak.
 *
 * 3. Riwayat closing TIDAK ditumpuk di Kelola Kas. Halaman itu sudah
 *    memuat mutasi pembayaran dan mutasi kas tidak terduga untuk sesi
 *    berjalan; ditambah 26 hari kerja riwayat closing, halamannya keluar
 *    dari layar dan kedua tabel saling menggeser. Yang tampil di Kelola Kas
 *    hanya pintasan ke Laporan Kas.
 *
 * 4. Hanya sesi yang sudah TUTUP yang punya tombol cetak. Angka isi laci
 *    dan selisih belum ada sebelum closing.
 */
class KasCetakRekapTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private int $idKonsumen;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-15 19:00:00'));
        AppSetting::clearCache();

        $roleKasir = Role::factory()->create(['nama_role' => 'Kasir']);
        $this->kasir = User::factory()->create(['id_role' => $roleKasir->id_role]);

        // Menu 16 = Kasir (Buka/Tutup Kas), menu 29 = Laporan Kas. Kasir yang
        // boleh mengelola kas harus boleh membuka laporan rekapnya.
        DB::table('menus')->insertOrIgnore(['id_menu' => 16, 'nama_menu' => 'Kasir', 'url_link' => 'kasir', 'parent_id' => 0]);
        DB::table('menus')->insertOrIgnore(['id_menu' => 29, 'nama_menu' => 'Laporan Kas', 'url_link' => 'laporan/kas', 'parent_id' => 0]);
        DB::table('role_permissions')->insert([
            ['id_role' => $roleKasir->id_role, 'id_menu' => 16],
            ['id_role' => $roleKasir->id_role, 'id_menu' => 29],
        ]);

        $idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Uji', 'telepon' => '0811', 'alamat' => '-', 'id_golongan' => $idGol]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sesiTerbuka(float $saldoAwal = 100000): int
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

    private function sesiTertutup(string $tgl, float $isiLaci, string $tutup, string $rolling = 'Rolling'): int
    {
        return DB::table('kas_harian')->insertGetId([
            'tgl_kas' => $tgl,
            'id_user' => $this->kasir->id_user,
            'saldo_awal' => 100000,
            'saldo_akhir' => $isiLaci,
            'saldo_fisik' => $isiLaci,
            'selisih' => 0,
            'status_lock' => 'closed',
            'status_rolling' => $rolling,
            'waktu_tutup' => $tutup,
            'created_at' => $tgl . ' 08:00:00',
            'updated_at' => $tutup,
        ]);
    }

    /** Laporan Kas dengan rentang tanggal yang mencakup semua sesi uji. */
    private function laporanKas()
    {
        return $this->actingAs($this->kasir)->get(route('laporan.kas', [
            'status_lock' => 'closed',
            'tgl_dari' => '2026-01-01',
            'tgl_sampai' => '2026-12-31',
        ]));
    }

    // ------------------------------------------------------------------
    // PINTA-SAN SETELAH CLOSING
    // ------------------------------------------------------------------

    public function test_setelah_closing_tampil_pilihan_cetak_langsung(): void
    {
        $id = $this->sesiTerbuka(100000);

        $response = $this->actingAs($this->kasir)->post(route('kasir.simpan-closing'), [
            'id_kas' => $id,
            'saldo_fisik' => '100000',
            'status_rolling' => 'Rolling',
            'keterangan' => 'Aman',
        ]);

        $response->assertRedirect(route('kasir.kelola'));
        $response->assertSessionHas('kas_baru_ditutup', $id);
        $response->assertSessionHasNoErrors();

        $this->actingAs($this->kasir)->get(route('kasir.kelola'))
            ->assertOk()
            ->assertSee('Rekap Closing Sesi #' . $id . ' Tersimpan')
            ->assertSee('Cetak Thermal 58mm')
            ->assertSee('Cetak A4 Lengkap');
    }

    public function test_blok_tertutup_tidak_muncul_kalau_belum_ada_closing_baru(): void
    {
        $this->sesiTertutup('2026-10-08', 436193, '2026-10-08 20:00:00');

        $this->actingAs($this->kasir)
            ->get(route('kasir.kelola'))
            ->assertOk()
            ->assertDontSee('Tersimpan');
    }

    // ------------------------------------------------------------------
    // PEMISAHAN: KELOLA KAS vs LAPORAN KAS
    // ------------------------------------------------------------------

    public function test_kelola_kas_punya_shortcut_ke_laporan_riwayat_closing(): void
    {
        $this->sesiTerbuka(100000);

        $html = $this->actingAs($this->kasir)->get(route('kasir.kelola'))->assertOk()->getContent();

        // Pintasan harus menuju Laporan Kas, sudah difilter ke sesi tertutup.
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
        $this->assertStringContainsString('laporan/kas', $decoded);
        $this->assertStringContainsString('status_lock=closed', $decoded);
        $this->assertStringContainsString('Buka Laporan', $decoded);
    }

    public function test_kelola_kas_tidak_menumpuk_tombol_print_per_sesi(): void
    {
        $this->sesiTerbuka(100000);
        $this->sesiTertutup('2026-10-08', 436193, '2026-10-08 20:00:00');
        $this->sesiTertutup('2026-10-10', 150000, '2026-10-10 19:00:00');

        $html = $this->actingAs($this->kasir)->get(route('kasir.kelola'))->assertOk()->getContent();

        // Riwayat ada di Laporan Kas, bukan di sini. Kalau jumlah tautan print
        // di Kelola Kas ikut bertambah, berarti tabelnya menumpuk lagi.
        $this->assertSame(
            0,
            substr_count($html, 'print-closing'),
            'Kelola Kas tidak boleh menumpuk tautan print per sesi closing.'
        );
    }

    // ------------------------------------------------------------------
    // LAPORAN KAS = TEMPAT RIWAYAT + CETAK
    // ------------------------------------------------------------------

    public function test_laporan_kas_menampilkan_tombol_print_untuk_setiap_sesi_tutup(): void
    {
        $idA = $this->sesiTertutup('2026-10-08', 436193, '2026-10-08 20:00:00');
        $idB = $this->sesiTertutup('2026-10-10', 150000, '2026-10-10 19:00:00');

        $html = $this->laporanKas()->assertOk()->getContent();
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);

        foreach ([$idA, $idB] as $id) {
            $this->assertStringContainsString(route('kasir.print-closing', ['id' => $id, 'format' => 'a4']), $decoded);
            $this->assertStringContainsString(route('kasir.print-closing', ['id' => $id, 'format' => 'thermal']), $decoded);
        }

        // Dua sesi x dua format.
        $this->assertSame(4, substr_count($html, 'print-closing'));
    }

    public function test_tautan_print_membuka_tab_baru(): void
    {
        $this->sesiTertutup('2026-10-08', 436193, '2026-10-08 20:00:00');

        $html = $this->laporanKas()->assertOk()->getContent();

        // Tanpa target="_blank", kasir kehilangan halaman laporan saat mencetak.
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener"', $html);
    }

    public function test_sesi_yang_masih_terbuka_tidak_dapat_ditetak(): void
    {
        $this->sesiTerbuka(100000);

        $html = $this->actingAs($this->kasir)
            ->get(route('laporan.kas', [
                'tgl_dari' => '2026-01-01',
                'tgl_sampai' => '2026-12-31',
            ]))
            ->assertOk()
            ->getContent();

        // Sesi open belum punya saldo fisik/selisih final, jadi tidak boleh
        // punya tombol cetak sama sekali.
        $this->assertSame(0, substr_count($html, 'print-closing'));
    }

    public function test_laporan_menampilkan_keterangan_pembukaan_sesi(): void
    {
        $id = $this->sesiTertutup('2026-10-08', 436193, '2026-10-08 20:00:00');
        DB::table('kas_harian')->where('id_kas', $id)
            ->update(['keterangan_pembukaan' => 'Sebagian uang diambil owner.']);

        $this->laporanKas()
            ->assertOk()
            ->assertSee('Sebagian uang diambil owner.')
            ->assertSee('saldo awal berbeda dari isi laci sebelumnya');
    }

    public function test_laporan_menampilkan_angka_saldo_dan_selisih(): void
    {
        $id = $this->sesiTertutup('2026-10-08', 436193, '2026-10-08 20:00:00');
        DB::table('kas_harian')->where('id_kas', $id)->update(['selisih' => -25000]);

        $this->laporanKas()
            ->assertOk()
            ->assertSee('436.193')
            ->assertSee('25.000');
    }

    public function test_laporan_kas_bisa_disaring_ke_sesi_tutup_saja(): void
    {
        $idTutup = $this->sesiTertutup('2026-10-08', 436193, '2026-10-08 20:00:00');
        $this->sesiTerbuka(100000);

        $decoded = html_entity_decode($this->laporanKas()->assertOk()->getContent(), ENT_QUOTES | ENT_HTML5);

        $this->assertStringContainsString(route('kasir.print-closing', ['id' => $idTutup, 'format' => 'a4']), $decoded);
        $this->assertSame(2, substr_count($this->laporanKas()->getContent(), 'print-closing'));
    }

    public function test_riwayat_26_hari_kerja_tetap_berpaginasi(): void
    {
        // 26 sesi tertutup harus tetap terbagi halaman, bukan dump 26 baris
        // sekaligus - inilah alasan riwayatnya dipisah dari Kelola Kas.
        for ($d = 1; $d <= 26; $d++) {
            $this->sesiTertutup(
                sprintf('2026-09-%02d', $d),
                100000 + ($d * 1000),
                sprintf('2026-09-%02d 18:00:00', $d)
            );
        }

        $response = $this->laporanKas()->assertOk();
        $data = $response->viewData('data');

        $this->assertSame(26, $data->total(), 'Semua 26 sesi harus terbaca oleh laporan.');
        $this->assertCount(20, $data->items(), 'Halaman pertama dibatasi 20 baris.');
        $this->assertSame(2, $data->lastPage(), '26 baris harus jadi 2 halaman.');
    }
}
