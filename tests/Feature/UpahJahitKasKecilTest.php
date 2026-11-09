<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\KasKecilService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Upah jahit borongan dibayar dari Kas Kecil; status dibayar/belum mengikuti pengeluaran Kas Kecil-nya. */
class UpahJahitKasKecilTest extends TestCase
{
    use RefreshDatabase;

    private int $penjahit;
    private int $tarif;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
        $this->penjahit = DB::table('penjahit')->insertGetId(['nama' => 'Bu Sri', 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->tarif = DB::table('jasa_jahit')->insertGetId(['nama_jahit' => 'Keliling banner', 'harga_borongan' => 2500, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function isiKasKecil(float $nominal): void
    {
        AccountingService::postKasKecilMasuk('KK-SALDO-' . uniqid(), '3000', $nominal);
    }

    private function catat(float $qty): int
    {
        $this->post('/jasa-jahit/catat', ['id_penjahit' => $this->penjahit, 'id_jasa_jahit' => $this->tarif, 'qty' => $qty, 'tgl_catat' => now()->toDateString()])
            ->assertSessionHas('success');

        return (int) DB::table('jasa_jahit_catat')->max('id_jasa_jahit_catat');
    }

    private function saldo(string $kode): float
    {
        return (float) DB::table('jurnal_umum')->where('kode_akun', $kode)->sum(DB::raw('debet - kredit'));
    }

    public function test_bayar_sebagian_membuat_pengeluaran_kas_kecil_dan_jurnal_beban_upah_jahit(): void
    {
        $this->isiKasKecil(100000);
        $a = $this->catat(4);   // 10.000
        $b = $this->catat(2);   // 5.000

        $this->post('/jasa-jahit/bayar', ['id_penjahit' => $this->penjahit, 'catat' => [$a]])->assertSessionHas('success');

        $kk = DB::table('kas_kecil')->first();
        $this->assertEquals(10000, $kk->nominal);
        $this->assertSame('Keluar', $kk->jenis);
        $this->assertSame((int) $kk->id, (int) DB::table('jasa_jahit_catat')->where('id_jasa_jahit_catat', $a)->value('id_kas_kecil'));
        $this->assertNull(DB::table('jasa_jahit_catat')->where('id_jasa_jahit_catat', $b)->value('id_kas_kecil'), 'yang tidak dipilih tetap belum dibayar');
        $this->assertEquals(10000, $this->saldo('5220'));
        $this->assertEquals(90000, $this->saldo('1005'));

        // Dibayar dua kali ditolak.
        $this->post('/jasa-jahit/bayar', ['id_penjahit' => $this->penjahit, 'catat' => [$a]])->assertSessionHas('error');
        $this->assertSame(1, DB::table('kas_kecil')->count());
    }

    public function test_saldo_kas_kecil_kurang_ditolak(): void
    {
        $this->isiKasKecil(3000);
        $a = $this->catat(4);

        $this->post('/jasa-jahit/bayar', ['id_penjahit' => $this->penjahit, 'catat' => [$a]])->assertSessionHas('error');
        $this->assertSame(0, DB::table('kas_kecil')->count());
        $this->assertNull(DB::table('jasa_jahit_catat')->value('id_kas_kecil'));
    }

    public function test_catatan_penjahit_lain_tidak_bisa_ikut_dibayar(): void
    {
        $this->isiKasKecil(100000);
        $a = $this->catat(4);
        $lain = DB::table('penjahit')->insertGetId(['nama' => 'Pak Udin', 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);

        $this->post('/jasa-jahit/bayar', ['id_penjahit' => $lain, 'catat' => [$a]])->assertSessionHas('error');
        $this->assertSame(0, DB::table('kas_kecil')->count());
    }

    public function test_catatan_terbayar_tidak_bisa_dihapus_dan_pembatalan_kas_kecil_mengembalikan_status(): void
    {
        $this->isiKasKecil(100000);
        $a = $this->catat(4);
        $this->post('/jasa-jahit/bayar', ['id_penjahit' => $this->penjahit, 'catat' => [$a]])->assertSessionHas('success');

        $this->delete("/jasa-jahit/catat/{$a}")->assertSessionHas('error');
        $this->assertTrue(DB::table('jasa_jahit_catat')->where('id_jasa_jahit_catat', $a)->exists());

        KasKecilService::batalkan((int) DB::table('kas_kecil')->value('id'));

        $this->assertNull(DB::table('jasa_jahit_catat')->where('id_jasa_jahit_catat', $a)->value('id_kas_kecil'));
        $this->assertEquals(0, $this->saldo('5220'));
        $this->assertEquals(100000, $this->saldo('1005'));
        $this->delete("/jasa-jahit/catat/{$a}")->assertSessionHas('success');
    }

    public function test_halaman_menampilkan_status_pembayaran(): void
    {
        $this->isiKasKecil(100000);
        $a = $this->catat(4);
        $this->catat(2);
        $this->post('/jasa-jahit/bayar', ['id_penjahit' => $this->penjahit, 'catat' => [$a]]);

        $this->get('/jasa-jahit')->assertOk()->assertSee('Sudah dibayar')->assertSee('Belum dibayar')->assertSee('Bayar dari Kas Kecil');
    }
}
