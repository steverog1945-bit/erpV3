<?php

namespace Tests\Feature;

use App\Models\JerseyOrder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KonsumenTimelineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idKonsumen;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $role->id_role]);
        $this->actingAs($this->admin);

        $idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Bu Sari', 'telepon' => '0811', 'alamat' => '-', 'id_golongan' => $idGol]);
    }

    private function nota(string $nomor, float $total, string $status = 'Aktif', ?int $idKonsumen = null, string $tgl = 'now'): int
    {
        return DB::table('transaksi')->insertGetId([
            'nomor_nota' => $nomor, 'tipe_order' => 'Reguler', 'id_konsumen' => $idKonsumen ?? $this->idKonsumen, 'id_user_kasir' => $this->admin->id_user,
            'tgl_order' => $tgl === 'now' ? now() : $tgl, 'total_bayar' => $total, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => $status,
            'status_collecting' => 'Belum Diambil',
        ]);
    }

    public function test_ringkasan_menghitung_omzet_piutang_deposit_dan_mengabaikan_nota_batal(): void
    {
        $n1 = $this->nota('INV-T-1', 500000);
        $this->nota('INV-T-2', 300000, 'Dibatalkan');
        DB::table('pembayaran_riwayat')->insert(['id_transaksi' => $n1, 'nominal' => 200000, 'metode' => 'Tunai', 'tgl_bayar' => now()]);
        DB::table('konsumen_deposit')->insert([
            ['id_konsumen' => $this->idKonsumen, 'tipe' => 'Setoran', 'nominal' => 100000, 'tgl_deposit' => now()->toDateString()],
            ['id_konsumen' => $this->idKonsumen, 'tipe' => 'Pemakaian', 'nominal' => 30000, 'tgl_deposit' => now()->toDateString()],
        ]);

        $r = $this->get("/konsumen/{$this->idKonsumen}/timeline")->assertOk()->viewData('ringkasan');

        $this->assertEquals(
            [1, 500000.0, 200000.0, 300000.0, 70000.0, 1],
            [$r['jumlah_nota'], $r['omzet'], $r['dibayar'], $r['piutang'], $r['saldo_deposit'], $r['belum_diambil']]
        );
    }

    public function test_kejadian_semua_jenis_tampil_urut_terbaru_dan_hanya_milik_pelanggan_ini(): void
    {
        $lain = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Orang Lain', 'telepon' => '0822', 'alamat' => '-', 'id_golongan' => DB::table('golongan_konsumen')->value('id_golongan')]);
        $this->nota('INV-LAIN-1', 999000, 'Aktif', $lain);

        $n1 = $this->nota('INV-T-1', 500000, 'Aktif', null, now()->subDays(5)->toDateTimeString());
        DB::table('pembayaran_riwayat')->insert(['id_transaksi' => $n1, 'nominal' => 100000, 'metode' => 'Transfer', 'tgl_bayar' => now()->subDays(4)]);
        DB::table('quotations')->insert([
            'nomor' => 'QT-T-1', 'tanggal' => now()->subDays(6)->toDateString(), 'berlaku_sampai' => now()->addDays(1)->toDateString(),
            'id_konsumen' => $this->idKonsumen, 'nama_pelanggan' => 'Bu Sari', 'status' => 'Menjadi Order', 'total' => 500000, 'id_user_pembuat' => $this->admin->id_user,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        JerseyOrder::create(['order_number' => 'JO-T-1', 'order_date' => now()->subDay()->toDateString(), 'customer_name' => 'Bu Sari', 'id_konsumen' => $this->idKonsumen]);

        $response = $this->get("/konsumen/{$this->idKonsumen}/timeline")->assertOk()
            ->assertSee('INV-T-1')->assertSee('QT-T-1')->assertSee('JO-T-1')->assertSee('Pembayaran')->assertDontSee('INV-LAIN-1');

        $urutan = $response->viewData('kejadian')->pluck('jenis')->all();
        $this->assertEquals(['Jersey', 'Pembayaran', 'Order', 'Penawaran'], $urutan);
    }

    public function test_pelanggan_tanpa_aktivitas_dan_id_tidak_dikenal(): void
    {
        $this->get("/konsumen/{$this->idKonsumen}/timeline")->assertOk()->assertSee('Belum ada aktivitas');
        $this->get('/konsumen/999999/timeline')->assertNotFound();
    }

    public function test_akses_mengikuti_menu_data_konsumen_dan_tombol_ada_di_daftar(): void
    {
        $roleKasir = Role::factory()->create(['nama_role' => 'Kasir']);
        $kasir = User::factory()->create(['id_role' => $roleKasir->id_role]);
        $roleGudang = Role::factory()->create(['nama_role' => 'Gudang']);
        $gudang = User::factory()->create(['id_role' => $roleGudang->id_role]);
        DB::table('menus')->insertOrIgnore(['id_menu' => 7, 'nama_menu' => 'Data Konsumen', 'url_link' => 'konsumen', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $roleKasir->id_role, 'id_menu' => 7]);

        $this->actingAs($kasir)->get("/konsumen/{$this->idKonsumen}/timeline")->assertOk();
        $this->actingAs($gudang)->get("/konsumen/{$this->idKonsumen}/timeline")->assertForbidden();
        $this->actingAs($kasir)->get('/konsumen')->assertOk()->assertSee("/konsumen/{$this->idKonsumen}/timeline", false);
    }
}
