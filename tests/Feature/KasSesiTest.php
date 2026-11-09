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

class KasSesiTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private User $admin;
    private int $idKonsumen;

    protected function setUp(): void
    {
        parent::setUp();

        // Rabu, 23 Sep 2026 10:00.
        Carbon::setTestNow(Carbon::parse('2026-09-23 10:00:00'));
        AppSetting::clearCache();

        $roleKasir = Role::factory()->create(['nama_role' => 'Kasir']);
        $roleAdmin = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->kasir = User::factory()->create(['id_role' => $roleKasir->id_role]);
        $this->admin = User::factory()->create(['id_role' => $roleAdmin->id_role]);
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

    /** Sesi dibuka kemarin malam (22:00) - sudah lewat tengah malam. */
    private function sesiKemarinMalam(User $pemilik, float $saldoAwal = 500000): int
    {
        return DB::table('kas_harian')->insertGetId([
            'tgl_kas' => '2026-09-22', 'id_user' => $pemilik->id_user, 'saldo_awal' => $saldoAwal, 'saldo_akhir' => $saldoAwal,
            'status_lock' => 'open', 'created_at' => '2026-09-22 22:00:00', 'updated_at' => '2026-09-22 22:00:00',
        ]);
    }

    private function bayar(string $nomor, float $nominal, string $waktu, string $metode = 'Tunai'): void
    {
        $idT = DB::table('transaksi')->insertGetId([
            'nomor_nota' => $nomor, 'tipe_order' => 'Reguler', 'id_konsumen' => $this->idKonsumen, 'id_user_kasir' => $this->kasir->id_user,
            'tgl_order' => $waktu, 'total_bayar' => $nominal, 'status_pembayaran' => 'Lunas', 'status_nota' => 'Aktif', 'status_collecting' => 'Belum Diambil',
        ]);
        DB::table('pembayaran_riwayat')->insert(['id_transaksi' => $idT, 'nominal' => $nominal, 'metode' => $metode, 'tgl_bayar' => $waktu]);
    }

    public function test_sesi_yang_belum_closing_tetap_aktif_melewati_pergantian_tanggal(): void
    {
        $id = $this->sesiKemarinMalam($this->kasir);

        $this->assertSame($id, (int) KasSesiService::aktif()->id_kas);
        $this->assertEquals(12, KasSesiService::umurJam(KasSesiService::aktif()));
        $this->assertFalse(KasSesiService::basi(KasSesiService::aktif()));
        $this->assertNotNull(KasSesiService::aktifUntukTransaksi());

        $this->actingAs($this->kasir)->get('/dashboard')->assertOk()->assertSee('Kasir Open')->assertDontSee('Kasir Standby');
    }

    public function test_sesi_lebih_dari_24_jam_diberi_peringatan(): void
    {
        $this->sesiKemarinMalam($this->kasir);
        Carbon::setTestNow(Carbon::parse('2026-09-24 09:00:00')); // 35 jam kemudian

        $this->assertTrue(KasSesiService::basi(KasSesiService::aktif()));
        $this->actingAs($this->kasir)->get('/dashboard')->assertOk()->assertSee('Belum di-closing');
        $this->actingAs($this->kasir)->get('/kasir/kelola-kas')->assertOk()->assertSee('belum di-closing');
    }

    public function test_tidak_bisa_membuka_sesi_baru_selama_sesi_lama_belum_closing(): void
    {
        $this->sesiKemarinMalam($this->kasir);

        $this->actingAs($this->kasir)->post('/kasir/store-kas', ['jumlah' => 100000])->assertSessionHas('error');
        $this->assertEquals(1, DB::table('kas_harian')->count());

        $lain = User::factory()->create(['id_role' => $this->kasir->id_role]);
        $this->actingAs($lain)->post('/kasir/store-kas', ['jumlah' => 100000])->assertSessionHas('error');
        $this->assertEquals(1, DB::table('kas_harian')->count());
    }

    public function test_closing_menghitung_pembayaran_setelah_tengah_malam_dan_mengabaikan_sebelum_sesi(): void
    {
        $id = $this->sesiKemarinMalam($this->kasir, 500000);
        $this->bayar('INV-K0', 999999, '2026-09-22 20:00:00');           // sebelum sesi dibuka - tidak ikut
        $this->bayar('INV-K1', 100000, '2026-09-22 23:30:00');           // sebelum tengah malam
        $this->bayar('INV-K2', 250000, '2026-09-23 01:15:00');           // setelah tengah malam
        $this->bayar('INV-K3', 70000, '2026-09-23 09:00:00', 'Transfer');

        $this->actingAs($this->kasir)->get('/kasir/closing-preview')->assertOk()->assertSee('Rekap sesi kas sejak');
        $this->actingAs($this->kasir)->post('/kasir/closing-simpan', ['id_kas' => $id, 'saldo_fisik' => '850.000', 'status_rolling' => 'Rolling', 'keterangan' => 'Aman'])
            ->assertSessionHas('success');

        $kas = DB::table('kas_harian')->where('id_kas', $id)->first();
        $this->assertSame('closed', $kas->status_lock);
        $this->assertEquals(350000, (float) $kas->total_tunai_masuk);      // 100.000 + 250.000
        $this->assertEquals(70000, (float) $kas->total_transfer_masuk);
        $this->assertEquals(850000, (float) $kas->saldo_akhir);            // 500.000 + 350.000
        $this->assertEquals(0, (float) $kas->selisih);
        $this->assertNull(KasSesiService::aktif());
    }

    public function test_tutup_paksa_administrator_wajib_alasan_dan_tercatat_kasir_lain_ditolak(): void
    {
        $id = $this->sesiKemarinMalam($this->kasir);
        $lain = User::factory()->create(['id_role' => $this->kasir->id_role]);

        // Kasir lain (bukan pemilik, bukan admin) ditolak.
        $this->actingAs($lain)->get('/kasir/closing-preview')->assertSessionHas('error');
        $this->actingAs($lain)->post('/kasir/closing-simpan', ['id_kas' => $id, 'saldo_fisik' => '500000', 'status_rolling' => 'Rolling', 'keterangan' => 'x'])->assertSessionHas('error');
        $this->assertSame('open', DB::table('kas_harian')->where('id_kas', $id)->value('status_lock'));

        // Administrator: tanpa alasan ditolak, dengan alasan berhasil dan tercatat.
        $this->actingAs($this->admin)->get('/kasir/closing-preview')->assertOk()->assertSee('Tutup paksa');
        $this->actingAs($this->admin)->post('/kasir/closing-simpan', ['id_kas' => $id, 'saldo_fisik' => '500000', 'status_rolling' => 'Tidak Rolling', 'keterangan' => ''])->assertSessionHas('error');
        $this->assertSame('open', DB::table('kas_harian')->where('id_kas', $id)->value('status_lock'));

        $this->actingAs($this->admin)->post('/kasir/closing-simpan', ['id_kas' => $id, 'saldo_fisik' => '500000', 'status_rolling' => 'Tidak Rolling', 'keterangan' => 'Kasir lupa closing'])->assertSessionHas('success');
        $this->assertSame('closed', DB::table('kas_harian')->where('id_kas', $id)->value('status_lock'));
        $this->assertEquals(1, DB::table('audit_logs')->where('auditable_type', 'kas_harian')->where('keterangan', 'like', '%Tutup paksa%')->count());
    }

    public function test_minggu_libur_memblokir_transaksi_tetapi_closing_tetap_boleh(): void
    {
        AppSetting::current()->update(['libur_kas_minggu' => true]);
        AppSetting::clearCache();
        Carbon::setTestNow(Carbon::parse('2026-09-27 09:00:00')); // Minggu
        $id = DB::table('kas_harian')->insertGetId([
            'tgl_kas' => '2026-09-26', 'id_user' => $this->kasir->id_user, 'saldo_awal' => 100000, 'saldo_akhir' => 100000,
            'status_lock' => 'open', 'created_at' => '2026-09-26 18:00:00', 'updated_at' => '2026-09-26 18:00:00',
        ]);

        $this->assertNotNull(KasSesiService::aktif());
        $this->assertNull(KasSesiService::aktifUntukTransaksi());
        $this->assertStringContainsString('Minggu', KasSesiService::alasanTerblokir());

        $this->actingAs($this->kasir)->post('/kasir/closing-simpan', ['id_kas' => $id, 'saldo_fisik' => '100000', 'status_rolling' => 'Rolling', 'keterangan' => 'Aman'])->assertSessionHas('success');
        $this->assertSame('closed', DB::table('kas_harian')->where('id_kas', $id)->value('status_lock'));
    }
}
