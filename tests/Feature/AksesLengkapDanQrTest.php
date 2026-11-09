<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AksesLengkapDanQrTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, array $izin = []): User
    {
        $r = Role::factory()->create(['nama_role' => $role]);
        foreach ($izin as $id => $flag) {
            DB::table('menus')->insertOrIgnore(['id_menu' => $id, 'nama_menu' => "M{$id}", 'url_link' => "m{$id}", 'parent_id' => 0]);
            DB::table('role_permissions')->insert(['id_role' => $r->id_role, 'id_menu' => $id, 'can_view' => 1, 'can_add' => $flag[0], 'can_edit' => $flag[1], 'can_delete' => $flag[2]]);
        }

        return User::factory()->create(['id_role' => $r->id_role]);
    }

    public function test_operator_desain_bisa_membuka_riwayat_nota_lewat_menu_riwayat_sendiri(): void
    {
        $desain = $this->user('Operator Desain', [61 => [0, 0, 0], 41 => [1, 0, 0]]);
        $this->actingAs($desain)->get('/penjualan/riwayat')->assertOk();

        $tanpa = $this->user('Gudang', [17 => [1, 1, 1]]);
        $this->actingAs($tanpa)->get('/penjualan/riwayat')->assertForbidden();
    }

    public function test_menu_yang_sebelumnya_tidak_berefek_kini_mengatur_halamannya(): void
    {
        // Menu 54 (Purchase Request) sekarang benar-benar membuka /purchase-request.
        $u = $this->user('Petugas PR', [54 => [1, 1, 1]]);
        $this->actingAs($u)->get('/purchase-request')->assertOk();

        // Menu 51/52/50 (Accounting Period / Backup / Approval) dapat diberikan ke non-admin.
        $a = $this->user('Auditor', [50 => [0, 0, 0], 52 => [0, 0, 0]]);
        $this->actingAs($a)->get('/approval')->assertOk();
        $this->actingAs($a)->get('/backup')->assertOk();
        $this->actingAs($a)->get('/accounting-period')->assertForbidden();
    }

    public function test_setiap_menu_di_pengaturan_akses_punya_efek_nyata(): void
    {
        $dipakai = collect(config('menu_access.rules'))->flatten()->unique()->all();
        // Menu-menu yang sebelumnya tidak mengatur apa pun (48-58) plus menu baru (60-64) kini dipakai aturan rute.
        foreach (array_merge(range(48, 58), range(60, 64)) as $id) {
            $this->assertContains($id, $dipakai, "Menu id {$id} tidak mengatur halaman apa pun.");
        }
    }

    public function test_cetak_spk_memuat_qr_alamat_pembayaran_bukan_barcode(): void
    {
        $admin = $this->user('Administrator');
        DB::table('app_settings')->count() || \App\Models\AppSetting::current();
        \App\Models\AppSetting::current()->update(['url_akses' => 'http://192.168.1.10/percetakan-app/public']);

        $g = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $k = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $g]);
        $p = DB::table('produk')->insertGetId(['nama_produk' => 'P', 'metode_hitung' => 'quantity', 'status' => 'Aktif']);
        $t = DB::table('transaksi')->insertGetId(['nomor_nota' => 'N-QR', 'tipe_order' => 'Reguler', 'id_konsumen' => $k, 'id_user_kasir' => $admin->id_user, 'tgl_order' => now(), 'total_bayar' => 1, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif']);
        $d = DB::table('transaksi_detail')->insertGetId(['id_transaksi' => $t, 'id_produk' => $p, 'nama_file' => 'a', 'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 1, 'subtotal' => 1, 'status_spk' => 'Antre']);

        $html = $this->actingAs($admin)->get("/spk/{$d}/print/thermal")->assertOk()->getContent();
        $this->assertStringContainsString('qr-spk', $html);
        $this->assertStringContainsString('http:\/\/192.168.1.10\/percetakan-app\/public\/kasir\/bayar-form\/' . $t, $html);
        $this->assertStringNotContainsString('JsBarcode', $html);
    }

    public function test_url_akses_wajib_berformat_http(): void
    {
        $admin = $this->user('Administrator');
        $this->actingAs($admin)->post('/pengaturan-aplikasi', ['nama_aplikasi' => 'X', 'url_akses' => 'bukan-url'])->assertSessionHasErrors('url_akses');
    }
}
