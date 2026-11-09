<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotifikasiTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $nama, array $menu = []): User
    {
        $r = Role::factory()->create(['nama_role' => $nama]);
        foreach ($menu as $id) {
            DB::table('menus')->insertOrIgnore(['id_menu' => $id, 'nama_menu' => "M{$id}", 'url_link' => "m{$id}", 'parent_id' => 0]);
            DB::table('role_permissions')->insert(['id_role' => $r->id_role, 'id_menu' => $id]);
        }

        return User::factory()->create(['id_role' => $r->id_role]);
    }

    public function test_admin_melihat_approval_menunggu_di_notifikasi_dan_lonceng_tampil(): void
    {
        $adm = $this->user('Administrator');
        $this->actingAs($adm);
        ApprovalService::ajukan('batalkan_nota', 'transaksi', 1, [], 'uji');
        Cache::flush();

        $data = $this->actingAs($adm)->getJson('/sinyal')->assertOk()->json('notif');
        $item = collect($data['items'])->firstWhere('key', 'approval');
        $this->assertSame(1, $item['count']);
        $this->assertGreaterThanOrEqual(1, $data['total']);

        $this->actingAs($adm)->get('/dashboard')->assertOk()->assertSee('id="notif-btn"', false)->assertSee('Pekerjaan masuk');
    }

    public function test_role_hanya_melihat_notifikasi_untuk_halaman_yang_boleh_dibukanya(): void
    {
        $adm = $this->user('Administrator');
        $this->actingAs($adm);
        $idT = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $idK = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $idT]);
        // status_order wajib diisi 'Diterima Kasir'. Tanpa itu MySQL memakai nilai
        // pertama enum yaitu 'Menunggu Kasir', dan notifikasi spk-antre memang
        // tidak menghitung nota yang transaksinya belum dibuka kasir (lihat
        // NotifikasiService). Assertion di bawah tetap sama: operator yang
        // boleh membuka halaman SPK harus melihat notifikasi itu.
        $idN = DB::table('transaksi')->insertGetId(['nomor_nota' => 'X-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $idK, 'id_user_kasir' => $adm->id_user, 'tgl_order' => now(), 'total_bayar' => 1000, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir', 'status_collecting' => 'Belum Diambil']);
        DB::table('transaksi_detail')->insert(['id_transaksi' => $idN, 'nama_file' => 'a', 'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 1, 'subtotal' => 1000, 'status_spk' => 'Antre']);
        ApprovalService::ajukan('batalkan_nota', 'transaksi', 1, [], 'uji');
        Cache::flush();

        $op = $this->user('Operator Produksi', [22]); // hanya menu SPK
        $keys = collect($this->actingAs($op)->getJson('/sinyal')->json('notif.items'))->pluck('key')->all();

        $this->assertContains('spk-antre', $keys);
        $this->assertNotContains('approval', $keys);
        $this->assertNotContains('kasir-bayar', $keys);
    }
}
