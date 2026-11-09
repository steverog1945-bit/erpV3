<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Uang keluar dari Kas Kecil lewat modul lain (bayar penjahit, bayar komisi desainer) mensyaratkan hak tambah di menu
 * Kas Kecil, bukan hanya hak di modul asal. Saldo awal Kas Kecil hanya Administrator.
 */
class IzinKasKecilTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(array $menus): User
    {
        $role = Role::factory()->create(['nama_role' => 'Staf Uji ' . uniqid()]);
        foreach ($menus as $id) {
            DB::table('menus')->insertOrIgnore(['id_menu' => $id, 'nama_menu' => 'Menu ' . $id, 'url_link' => 'uji-' . $id, 'parent_id' => 0]);
            DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => $id, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1]);
        }

        return User::factory()->create(['id_role' => $role->id_role]);
    }

    private function catatanJahitBelumDibayar(): array
    {
        AccountingService::postKasKecilMasuk('KK-SALDO', '3000', 100000);
        $pj = DB::table('penjahit')->insertGetId(['nama' => 'Bu Sri', 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
        $tarif = DB::table('jasa_jahit')->insertGetId(['nama_jahit' => 'Keliling', 'harga_borongan' => 2500, 'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('jasa_jahit_catat')->insertGetId(['id_jasa_jahit' => $tarif, 'id_penjahit' => $pj, 'qty' => 4, 'harga_snapshot' => 2500, 'total' => 10000, 'tgl_catat' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);

        return [$pj, $id];
    }

    public function test_hak_jasa_jahit_saja_tidak_bisa_membayar_penjahit(): void
    {
        [$pj, $id] = $this->catatanJahitBelumDibayar();
        $u = $this->pengguna([68]);

        $this->actingAs($u)->get('/jasa-jahit')->assertOk()->assertDontSee('onclick="bukaBayar(', false);
        $this->actingAs($u)->post('/jasa-jahit/bayar', ['id_penjahit' => $pj, 'catat' => [$id]])->assertForbidden();
        $this->assertSame(0, DB::table('kas_kecil')->count());
    }

    public function test_dengan_hak_kas_kecil_bisa_membayar_penjahit(): void
    {
        [$pj, $id] = $this->catatanJahitBelumDibayar();
        $u = $this->pengguna([68, 39]);

        $this->actingAs($u)->post('/jasa-jahit/bayar', ['id_penjahit' => $pj, 'catat' => [$id]])->assertSessionHas('success');
        $this->assertSame(1, DB::table('kas_kecil')->count());
    }

    public function test_hak_laporan_biaya_desain_saja_tidak_bisa_membayar_komisi(): void
    {
        $u = $this->pengguna([65]);

        $this->actingAs($u)->post('/laporan/biaya-desain/bayar', [
            'id_desainer' => $u->id_user, 'tgl_dari' => now()->startOfMonth()->toDateString(), 'tgl_sampai' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_saldo_awal_kas_kecil_tetap_hanya_administrator_walau_punya_hak_kas_kecil(): void
    {
        $u = $this->pengguna([39]);

        $this->actingAs($u)->post('/kas-kecil/saldo-awal', ['tanggal' => now()->toDateString(), 'nominal' => 1000000])->assertForbidden();
        $this->actingAs($u)->get('/kas-kecil')->assertOk()->assertSee('Minta Administrator menetapkannya');
    }

    /** Peta hak untuk tampilan diturunkan dari aturan yang sama dengan middleware. */
    public function test_peta_hak_tampilan_sama_dengan_aturan_server(): void
    {
        $u = $this->pengguna([7]);
        DB::table('role_permissions')->where('id_role', $u->id_role)->where('id_menu', 7)->update(['can_edit' => 0, 'can_delete' => 0]);

        $peta = \App\Support\Izin::petaUntukTampilan($u);
        $this->assertFalse($peta['admin']);
        $this->assertSame('1100', $peta['aturan']['konsumen']);
        $this->assertSame('0000', $peta['aturan']['kas-kecil']);

        $html = $this->actingAs($u)->get('/konsumen')->assertOk()->getContent();
        $this->assertStringContainsString('rapikanTombolHak', $html);
        $this->assertStringContainsString('"konsumen":"1100"', $html);

        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->assertTrue(\App\Support\Izin::petaUntukTampilan($admin)['admin']);
    }
}
