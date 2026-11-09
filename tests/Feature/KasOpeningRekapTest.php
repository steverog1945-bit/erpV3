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
 * Guard saldo awal + rekap closing kas yang bisa dicetak.
 *
 * Dua hal yang diuji di sini:
 *
 * 1. Saldo awal sesi baru harus cocok dengan ISI LACI sesi sebelumnya, bukan
 *    dengan saldo akhir sistem dan bukan sebagai addition atas sesi sebelumnya.
 *    Sesi kas dihitung terpisah (tidak ada SUM antar sesi), jadi uang yang
 *    tetap di laci hanya boleh dihitung satu kali - di sesi berikutnya.
 *
 * 2. Rekap closing hanya bisa dicetak setelah sesi ditutup, dan angkanya harus
 *    sama persis dengan yang tersimpan (bukan dihitung ulang dari sumber lain,
 *    supaya cetakan tidak pernah berbeda dari arsipnya).
 */
class KasOpeningRekapTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private int $idKonsumen;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-15 09:00:00'));
        AppSetting::clearCache();

        $roleKasir = Role::factory()->create(['nama_role' => 'Kasir']);
        $this->kasir = User::factory()->create(['id_role' => $roleKasir->id_role]);
        DB::table('menus')->insertOrIgnore(['id_menu' => 16, 'nama_menu' => 'Kasir', 'url_link' => 'kasir', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $roleKasir->id_role, 'id_menu' => 16]);

        $idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Uji', 'telepon' => '0811', 'alamat' => '-', 'id_golongan' => $idGol]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Sesi tertutup pada 10 Okt dengan isi laci tertentu dan status Rolling.
     *
     * Angka tunai/transfer/debit yang dicetak rekap diambil dari kolom yang
     * SUDAH TERSIMPAN di kas_harian, bukan dihitung ulang dari
     * pembayaran_riwayat - supaya cetakan tidak pernah berbeda dari arsipnya.
     */
    private function sesiTertutupRolling(float $isiLaci, string $tgl = '2026-10-10', float $tunaiMasuk = 0): int
    {
        return DB::table('kas_harian')->insertGetId([
            'tgl_kas' => $tgl,
            'id_user' => $this->kasir->id_user,
            'saldo_awal' => 100000,
            'total_tunai_masuk' => $tunaiMasuk,
            'saldo_akhir' => $isiLaci,
            'saldo_fisik' => $isiLaci,
            'selisih' => 0,
            'status_lock' => 'closed',
            'status_rolling' => 'Rolling',
            'waktu_tutup' => $tgl . ' 18:00:00',
            'created_at' => $tgl . ' 08:00:00',
            'updated_at' => $tgl . ' 18:00:00',
        ]);
    }

    // ------------------------------------------------------------------
    // GUARD SALDO AWAL
    // ------------------------------------------------------------------

    public function test_saldo_awal_sama_dengan_isi_laci_sebelumnya_tidak_butuh_alasan(): void
    {
        $this->sesiTertutupRolling(436193);

        $this->actingAs($this->kasir)
            ->post(route('kasir.store-kas'), ['jumlah' => '436193'])
            ->assertRedirect(route('kasir.index'));

        $baru = KasSesiService::aktif();
        $this->assertNotNull($baru);
        $this->assertEquals(436193, (float) $baru->saldo_awal);
        $this->assertNull($baru->keterangan_pembukaan, 'Saldo cocok, tidak boleh ada catatan pembuka.');
    }

    public function test_saldo_awal_berbeda_dari_isi_laci_ditolak_kalau_alasan_kosong(): void
    {
        $this->sesiTertutupRolling(436193);

        $this->actingAs($this->kasir)
            ->post(route('kasir.store-kas'), ['jumlah' => '100000'])
            ->assertRedirect(route('kasir.kelola'))
            ->assertSessionHas('error');

        $this->assertNull(
            KasSesiService::aktif(),
            'Sesi baru tidak boleh dibuat kalau saldo awal tidak bisa dijelaskan.'
        );
    }

    public function test_selisih_satu_rupiah_tetap_ditolak(): void
    {
        $this->sesiTertutupRolling(436193);

        $this->actingAs($this->kasir)
            ->post(route('kasir.store-kas'), ['jumlah' => '436192'])
            ->assertSessionHas('error');

        $this->assertNull(KasSesiService::aktif());
    }

    public function test_saldo_awal_berbeda_dengan_alasan_diterima_dan_catatan_tersimpan(): void
    {
        $this->sesiTertutupRolling(436193);

        $this->actingAs($this->kasir)
            ->post(route('kasir.store-kas'), [
                'jumlah' => '100000',
                'keterangan_pembukaan' => 'Sebagian uang diambil owner pada 14 Okt.',
            ])
            ->assertRedirect(route('kasir.index'));

        $baru = KasSesiService::aktif();
        $this->assertNotNull($baru);
        $this->assertEquals(100000, (float) $baru->saldo_awal);
        $this->assertSame(
            'Sebagian uang diambil owner pada 14 Okt.',
            $baru->keterangan_pembukaan,
            'Alasan pembuka wajib tersimpan supaya selisih bisa ditelusuri.'
        );
    }

    public function test_tanpa_sesi_sebelumnya_tidak_ada_yang_wajib_berbeda(): void
    {
        // Tidak ada sesi tertutup sama sekali - tidak ada acuan, jadi berapapun
        // saldo awal yang diketik harus diterima.
        $this->actingAs($this->kasir)
            ->post(route('kasir.store-kas'), ['jumlah' => '75000'])
            ->assertRedirect(route('kasir.index'));

        $baru = KasSesiService::aktif();
        $this->assertNotNull($baru);
        $this->assertEquals(75000, (float) $baru->saldo_awal);
        $this->assertNull($baru->keterangan_pembukaan);
    }

    public function test_sesi_sebelumnya_tidak_rolling_tidak_jadi_acuan(): void
    {
        // Ditutup dengan status "Tidak Rolling" - uangnya sudah diambil, jadi
        // tidak boleh dipaksakan sebagai saldo awal sesi berikutnya.
        DB::table('kas_harian')->insert([
            'tgl_kas' => '2026-10-10', 'id_user' => $this->kasir->id_user,
            'saldo_awal' => 100000, 'saldo_akhir' => 900000, 'saldo_fisik' => 900000,
            'status_lock' => 'closed', 'status_rolling' => 'Tidak Rolling',
            'waktu_tutup' => '2026-10-10 18:00:00',
        ]);

        $this->actingAs($this->kasir)
            ->post(route('kasir.store-kas'), ['jumlah' => '100000'])
            ->assertRedirect(route('kasir.index'));

        $this->assertNull(KasSesiService::aktif()->keterangan_pembukaan);
    }

    public function test_saldo_awal_bukan_angka_sistem_tapi_isi_laci(): void
    {
        // Sesi ditutup dengan selisih: sistem bilang 1.000.000, isi laci 1.050.000.
        // Yang diwariskan ke sesi berikutnya harus ISI LACI, bukan angka sistem.
        DB::table('kas_harian')->insert([
            'tgl_kas' => '2026-10-10', 'id_user' => $this->kasir->id_user,
            'saldo_awal' => 100000, 'saldo_akhir' => 1000000, 'saldo_fisik' => 1050000,
            'selisih' => 50000, 'status_lock' => 'closed', 'status_rolling' => 'Rolling',
            'waktu_tutup' => '2026-10-10 18:00:00',
        ]);

        $response = $this->actingAs($this->kasir)->get(route('kasir.kelola'));
        $response->assertOk();

        // Saran di dialog harus 1.050.000 (isi laci), bukan 1.000.000 (sistem).
        $response->assertSee('1050000', false);
    }

    public function test_tutup_lalu_buka_di_hari_yang_sama_tidak_ditolak(): void
    {
        // Skenario yang paling sering dipakai kasir: tutup sesi, lalu buka
        // lagi di hari yang sama. tgl_kas sesi itu sama dengan hari ini.
        //
        // Kalau acuan masih dibatasi tgl_kas < hari_ini, sesi yang baru
        // ditutup TIDAK terlihat - guard akan membandingkan saldo yang
        // benar dengan isi laci sesi yang sudah lama, lalu menolaknya dan
        // minta alasan. Itu false positive: uangnya tidak berubah.
        $kemarin = '2026-10-14';
        DB::table('kas_harian')->insert([
            'tgl_kas' => $kemarin, 'id_user' => $this->kasir->id_user,
            'saldo_awal' => 100000, 'saldo_akhir' => 436193, 'saldo_fisik' => 436193,
            'status_lock' => 'closed', 'status_rolling' => 'Rolling',
            'waktu_tutup' => $kemarin . ' 20:00:00',
        ]);

        // Sesi hari ini (tgl_kas = hari ini) ditutup dengan isi laci 150.000.
        DB::table('kas_harian')->insert([
            'tgl_kas' => '2026-10-15', 'id_user' => $this->kasir->id_user,
            'saldo_awal' => 436193, 'saldo_akhir' => 150000, 'saldo_fisik' => 150000,
            'status_lock' => 'closed', 'status_rolling' => 'Rolling',
            'waktu_tutup' => '2026-10-15 08:00:00',
        ]);

        // Buka lagi dengan isi laci yang SAMA - harus diterima tanpa alasan.
        $this->actingAs($this->kasir)
            ->post(route('kasir.store-kas'), ['jumlah' => '150000'])
            ->assertRedirect(route('kasir.index'));

        $baru = KasSesiService::aktif();
        $this->assertNotNull($baru);
        $this->assertEquals(150000, (float) $baru->saldo_awal);
        $this->assertNull(
            $baru->keterangan_pembukaan,
            'Saldo sama dengan isi laci sesi yang baru ditutup - tidak ada yang perlu dijelaskan.'
        );
    }

    public function test_saran_rolling_di_kelola_kas_ikut_sesi_yang_baru_ditutup(): void
    {
        // Saran di dialog harus berasal dari sesi tertutup TERAKHIR, bukan
        // dari sesi paling lama yang tanggalnya masih di bawah hari ini.
        DB::table('kas_harian')->insert([
            'tgl_kas' => '2026-10-08', 'id_user' => $this->kasir->id_user,
            'saldo_awal' => 100000, 'saldo_akhir' => 436193, 'saldo_fisik' => 436193,
            'status_lock' => 'closed', 'status_rolling' => 'Rolling',
            'waktu_tutup' => '2026-10-08 20:00:00',
        ]);
        DB::table('kas_harian')->insert([
            'tgl_kas' => '2026-10-15', 'id_user' => $this->kasir->id_user,
            'saldo_awal' => 436193, 'saldo_akhir' => 150000, 'saldo_fisik' => 150000,
            'status_lock' => 'closed', 'status_rolling' => 'Rolling',
            'waktu_tutup' => '2026-10-15 08:00:00',
        ]);

        $this->actingAs($this->kasir)
            ->get(route('kasir.kelola'))
            ->assertOk()
            ->assertSee('150000', false);
    }

    // ------------------------------------------------------------------
    // CETAK REKAP CLOSING
    // ------------------------------------------------------------------

    public function test_rekap_tidak_bisa_dicetak_saat_sesi_masih_berjalan(): void
    {
        $idAktif = DB::table('kas_harian')->insertGetId([
            'tgl_kas' => '2026-10-15', 'id_user' => $this->kasir->id_user,
            'saldo_awal' => 100000, 'saldo_akhir' => 100000, 'status_lock' => 'open',
        ]);

        $this->actingAs($this->kasir)
            ->get(route('kasir.print-closing', ['id' => $idAktif, 'format' => 'a4']))
            ->assertRedirect(route('kasir.kelola'))
            ->assertSessionHas('error');
    }

    public function test_rekap_sesi_tidak_ada_menghasilkan_404(): void
    {
        $this->actingAs($this->kasir)
            ->get(route('kasir.print-closing', ['id' => 999999, 'format' => 'a4']))
            ->assertNotFound();
    }

    public function test_rekap_a4_menampilkan_rantai_rekonsiliasi_lengkap(): void
    {
        $id = $this->sesiTertutupRolling(436193, '2026-10-08', 336193);

        // Deposit TIDAK pernah ikut closing (tidak menyentuh uang fisik), jadi
        // tidak punya kolom di kas_harian - harus dihitung dari pembayaran_riwayat.
        DB::table('pembayaran_riwayat')->insert([
            'id_transaksi' => 1, 'nominal' => 75000, 'metode' => 'Deposit',
            'tgl_bayar' => '2026-10-08 11:00:00',
        ]);

        $response = $this->actingAs($this->kasir)
            ->get(route('kasir.print-closing', ['id' => $id, 'format' => 'a4']));

        $response->assertOk();

        // Rantai: saldo awal -> + tunai -> = saldo akhir sistem.
        $response->assertSee('Saldo awal / modal');
        $response->assertSee('+ Pemasukan tunai nota');
        $response->assertSee('Saldo akhir sistem');
        $response->assertSee('336.193');

        // Penghitungan fisik + selisih.
        $response->assertSee('Total isi laci');
        $response->assertSee('436.193');
        $response->assertSee('Selisih');

        // Non-tunai dicatat terpisah dan dinyatakan tidak ada di laci.
        $response->assertSee('Deposit masuk');
        $response->assertSee('tidak ada di laci');
    }

    public function test_rekap_menampilkan_catatan_pembukaan_bila_ada(): void
    {
        $id = $this->sesiTertutupRolling(436193, '2026-10-08');
        DB::table('kas_harian')->where('id_kas', $id)
            ->update(['keterangan_pembukaan' => 'Sebagian uang diambil owner.']);

        $this->actingAs($this->kasir)
            ->get(route('kasir.print-closing', ['id' => $id, 'format' => 'a4']))
            ->assertOk()
            ->assertSee('Catatan Pembukaan Sesi')
            ->assertSee('Sebagian uang diambil owner.');
    }

    public function test_rekap_tanpa_catatan_pembukaan_tidak_menampilkan_blok_it(): void
    {
        $id = $this->sesiTertutupRolling(436193, '2026-10-08');

        $this->actingAs($this->kasir)
            ->get(route('kasir.print-closing', ['id' => $id, 'format' => 'a4']))
            ->assertOk()
            ->assertDontSee('Catatan Pembukaan Sesi');
    }

    public function test_format_apa_saja_selain_thermal_dianggap_a4(): void
    {
        $id = $this->sesiTertutupRolling(436193, '2026-10-08');

        $this->actingAs($this->kasir)
            ->get(route('kasir.print-closing', ['id' => $id, 'format' => 'ngawur']))
            ->assertOk()
            ->assertSee('D. Pembayaran Non-Tunai');
    }

    public function test_thermal_lebih_ringkas_daripada_a4(): void
    {
        $id = $this->sesiTertutupRolling(436193, '2026-10-08');

        $a4 = $this->actingAs($this->kasir)->get(route('kasir.print-closing', ['id' => $id, 'format' => 'a4']));
        $thermal = $this->actingAs($this->kasir)->get(route('kasir.print-closing', ['id' => $id, 'format' => 'thermal']));

        $a4->assertOk();
        $thermal->assertOk();

        // Thermal tidak membawa tabel lengkap A4 (bagian non-tunai & catatan
        // pembuka), tapi tetap membawa angka inti buat nyocokin laci.
        $a4->assertSee('D. Pembayaran Non-Tunai');
        $thermal->assertDontSee('D. Pembayaran Non-Tunai');
        $thermal->assertSee('SALDO AWAL BESOK');
        $thermal->assertSee('436.193');
    }
}
