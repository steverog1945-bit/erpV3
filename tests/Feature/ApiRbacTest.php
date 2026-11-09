<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RBAC untuk routes/api.php (middleware ApiRbac).
 *
 * Yang dibuktikan di sini (temuan API-2 audit: satu token sah membaca SEMUA
 * endpoint - diperbaiki):
 *  - Pemegang token role kecil hanya membaca endpoint yang id_menu-nya
 *    relevan dengan hak web-nya (sumber kebenaran SAMA: role_permissions
 *    lewat App\Support\Izin - bukan sistem izin kedua).
 *  - Laporan kas/piutang, stok, SPK, dan angka KPI ditolak untuk role yang
 *    di web juga tidak menyajikan halamannya.
 *  - Endpoint yang belum dipetakan = 403 walau matrix hak kandidat cocok
 *    (fail-closed), begitu juga metode HTTP yang tidak terdaftar.
 *  - Gate persiapan (503) tetap lebih dulu: RBAC tidak melonggarkan
 *    keadaan API tertutup.
 */
class ApiRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('orion_api.enabled', true);

        // RefreshDatabase hanya menjalankan migrasi - baris menu (dari
        // DatabaseSeeder di produksi) disuntik sendiri, selebar id_menu
        // yang dipakai test ini (pola yang sama dipakai MatriksIzinTest).
        DB::table('menus')->insertOrIgnore([
            ['id_menu' => 1, 'nama_menu' => 'Bahan Baku', 'url_link' => 'bahan-baku', 'parent_id' => 0],
            ['id_menu' => 3, 'nama_menu' => 'Transaksi Baru', 'url_link' => 'penjualan', 'parent_id' => 0],
            ['id_menu' => 16, 'nama_menu' => 'Kasir', 'url_link' => 'kasir', 'parent_id' => 0],
            ['id_menu' => 22, 'nama_menu' => 'SPK', 'url_link' => 'spk', 'parent_id' => 0],
            ['id_menu' => 25, 'nama_menu' => 'Laporan Order', 'url_link' => 'laporan/order', 'parent_id' => 0],
            ['id_menu' => 29, 'nama_menu' => 'Laporan Kas', 'url_link' => 'laporan/kas', 'parent_id' => 0],
            ['id_menu' => 41, 'nama_menu' => 'Input Order', 'url_link' => 'penjualan', 'parent_id' => 0],
            ['id_menu' => 44, 'nama_menu' => 'Laporan Piutang', 'url_link' => 'laporan/piutang', 'parent_id' => 0],
            ['id_menu' => 46, 'nama_menu' => 'Laporan Stok Bahan', 'url_link' => 'laporan/stok-bahan', 'parent_id' => 0],
            ['id_menu' => 60, 'nama_menu' => 'Dashboard Eksekutif', 'url_link' => 'eksekutif', 'parent_id' => 0],
            ['id_menu' => 61, 'nama_menu' => 'Riwayat Nota', 'url_link' => 'penjualan/riwayat', 'parent_id' => 0],
        ]);
    }

    /** Bikin user bertoken role $namaRole dengan matriks [id_menu => ['view','add',...]]. @return array{0: User, 1: string} */
    private function bikinUser(string $namaRole, array $matriks = []): array
    {
        $role = Role::factory()->create(['nama_role' => $namaRole]);
        foreach ($matriks as $idMenu => $diizinkan) {
            DB::table('role_permissions')->insert([
                'id_role' => $role->id_role,
                'id_menu' => $idMenu,
                'can_view' => in_array('view', $diizinkan, true),
                'can_add' => in_array('add', $diizinkan, true),
                'can_edit' => in_array('edit', $diizinkan, true),
                'can_delete' => in_array('delete', $diizinkan, true),
            ]);
        }

        $user = User::factory()->create(['id_role' => $role->id_role]);
        $token = \Tests\Support\TokenApiPengujian::token($user);

        return [$user, $token];
    }

    private function auth(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
    }

    public function test_administrator_membaca_semua_endpoint(): void
    {
        [, $token] = $this->bikinUser('Administrator');

        foreach ([
            '/api/konsumen', '/api/produk', '/api/transaksi', '/api/pembayaran',
            '/api/spk', '/api/stok', '/api/laporan/penjualan', '/api/laporan/piutang',
            '/api/laporan/kas', '/api/dashboard/kpi',
        ] as $endpoint) {
            $this->getJson($endpoint, $this->auth($token))->assertOk(
                "Administrator boleh $endpoint"
            );
        }
    }

    public function test_kasir_membaca_modulnya_dan_ditolak_di_laporan_keuangan(): void
    {
        // Selaras web: Transaksi Baru [3] + Kasir [16] + Input Order [41] + Riwayat Nota [61].
        [, $token] = $this->bikinUser('Kasir Uji', [
            3 => ['view', 'add'],
            16 => ['view'],
            41 => ['view', 'add'],
            61 => ['view'],
        ]);

        // Endpoints modulnya: lolos.
        $this->getJson('/api/transaksi', $this->auth($token))->assertOk();
        $this->getJson('/api/konsumen', $this->auth($token))->assertOk();
        $this->getJson('/api/produk', $this->auth($token))->assertOk();
        $this->getJson('/api/pembayaran', $this->auth($token))->assertOk();

        // RBAC lolos (hak add), tetapi checkout API ditutup hingga paritas lengkap.
        $this->postJson('/api/transaksi', [], $this->auth($token))->assertStatus(503);

        // Laporan keuangan/su/data milik admin: role Kasir tidak diberi
        // menu 25/29/44/22/1/46/60 => 403.
        $this->getJson('/api/laporan/kas', $this->auth($token))->assertForbidden();
        $this->getJson('/api/laporan/piutang', $this->auth($token))->assertForbidden();
        $this->getJson('/api/laporan/penjualan', $this->auth($token))->assertForbidden();
        $this->getJson('/api/stok', $this->auth($token))->assertForbidden();
        $this->getJson('/api/spk', $this->auth($token))->assertForbidden();
        $this->getJson('/api/dashboard/kpi', $this->auth($token))->assertForbidden();
    }

    public function test_role_riwayat_saja_tidak_bisa_menulis_atau_membaca_master(): void
    {
        // Role dengan sekadar menu 61 (Riwayat Nota, view) - profil laporan
        // Operator Desain di produksi. Untuk web hanya sebuah halaman lihat;
        // API juga tidak mungkin lebih dari ini.
        [, $token] = $this->bikinUser('Riwayat Saja Uji', [61 => ['view']]);

        $this->getJson('/api/transaksi', $this->auth($token))->assertOk();
        $this->postJson('/api/transaksi', [], $this->auth($token))->assertForbidden();
        $this->getJson('/api/konsumen', $this->auth($token))->assertForbidden();
        $this->getJson('/api/pembayaran', $this->auth($token))->assertForbidden();
        $this->getJson('/api/laporan/kas', $this->auth($token))->assertForbidden();
        $this->getJson('/api/dashboard/kpi', $this->auth($token))->assertForbidden();
    }

    public function test_riwayat_nota_saja_tidak_bisa_membaca_pembayaran_tersarang(): void
    {
        [, $token] = $this->bikinUser('Riwayat Saja Uji', [61 => ['view']]);

        // Sebelumnya prefix transaksi GET ([3,61]) membolehkan route pembayaran.
        $this->getJson('/api/transaksi/1/pembayaran', $this->auth($token))
            ->assertForbidden();
    }

    public function test_owner_tetap_baca_saja_meski_permission_add_dicentang(): void
    {
        [, $token] = $this->bikinUser('Owner', [
            3 => ['view', 'add'],
            41 => ['view', 'add'],
            61 => ['view'],
        ]);

        $this->getJson('/api/transaksi', $this->auth($token))->assertOk();
        $this->postJson('/api/transaksi', [], $this->auth($token))
            ->assertForbidden();
    }

    public function test_pembayaran_tidak_membocorkan_nota_kasir_lain(): void
    {
        [$kasirA, $tokenA] = $this->bikinUser('Kasir A', [16 => ['view']]);
        [$kasirB, $tokenB] = $this->bikinUser('Kasir B', [16 => ['view']]);
        [, $tokenAdmin] = $this->bikinUser('Administrator');

        $idGolongan = DB::table('golongan_konsumen')->insertGetId([
            'nama_golongan' => 'Retail API Audit', 'keterangan' => '-',
        ]);
        $idKonsumen = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Konsumen Scope API', 'telepon' => '0800',
            'alamat' => '-', 'id_golongan' => $idGolongan,
            'tipe_konsumen' => 'temporary', 'tgl_registrasi' => now(),
        ]);
        $notaA = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'RBAC-OWN-A', 'tgl_order' => now(),
            'id_konsumen' => $idKonsumen, 'total_bayar' => 100000,
            'status_pembayaran' => 'DP', 'status_nota' => 'Aktif',
            'status_order' => 'Diterima Kasir', 'id_user_kasir' => $kasirA->id_user,
        ]);
        $notaB = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'RBAC-OWN-B', 'tgl_order' => now(),
            'id_konsumen' => $idKonsumen, 'total_bayar' => 100000,
            'status_pembayaran' => 'DP', 'status_nota' => 'Aktif',
            'status_order' => 'Diterima Kasir', 'id_user_kasir' => $kasirB->id_user,
        ]);
        DB::table('pembayaran_riwayat')->insert([
            ['id_transaksi' => $notaA, 'nominal' => 10000, 'metode' => 'Tunai', 'tgl_bayar' => now()],
            ['id_transaksi' => $notaB, 'nominal' => 20000, 'metode' => 'Tunai', 'tgl_bayar' => now()],
        ]);

        $this->getJson('/api/transaksi/' . $notaA . '/pembayaran', $this->auth($tokenA))->assertOk();
        $this->getJson('/api/transaksi/' . $notaB . '/pembayaran', $this->auth($tokenA))->assertNotFound();
        $this->getJson('/api/transaksi/' . $notaB . '/pembayaran', $this->auth($tokenB))->assertOk();
        $this->getJson('/api/transaksi/' . $notaB . '/pembayaran', $this->auth($tokenAdmin))->assertOk();

        $this->getJson('/api/pembayaran', $this->auth($tokenA))
            ->assertOk()->assertJsonFragment(['nomor_nota' => 'RBAC-OWN-A'])
            ->assertDontSee('RBAC-OWN-B');
        $this->getJson('/api/pembayaran', $this->auth($tokenAdmin))
            ->assertOk()->assertJsonFragment(['nomor_nota' => 'RBAC-OWN-B']);
    }

    public function test_role_tanpa_hak_tidak_membaca_apa_pun(): void
    {
        // Profil "satu token salah satu staf": role tanpa baris hak sama sekali
        // (temuan audit: token Operator Desain membaca laporan kas).
        [, $token] = $this->bikinUser('Staff Kosong Uji');

        foreach (['/api/transaksi', '/api/konsumen', '/api/pembayaran', '/api/spk', '/api/stok', '/api/laporan/kas', '/api/dashboard/kpi'] as $endpoint) {
            $this->getJson($endpoint, $this->auth($token))->assertForbidden(
                "Role tanpa hak ditolak di $endpoint"
            );
        }
    }

    public function test_endpoint_yang_belum_dipetakan_fail_closed(): void
    {
        config()->set('orion_api.menus', [
            // Hanya transaksi yang dipetakan - konsumen "hilang" dari peta
            // walau matrix kasir punya hak 41.
            'transaksi' => ['GET' => [3, 61]],
        ]);
        [, $token] = $this->bikinUser('Kasir Uji', [3 => ['view', 'add'], 41 => ['view', 'add'], 61 => ['view']]);

        // Yang dipetakan tetap jalan.
        $this->getJson('/api/transaksi', $this->auth($token))->assertOk();

        // Yang tidak lagi dipetakan: 403 meskipun hak matrix ada.
        $this->getJson('/api/konsumen', $this->auth($token))->assertForbidden()->assertJsonPath(
            'message',
            'Endpoint API ini belum dibuka untuk role apa pun.'
        );
        $this->getJson('/api/stok', $this->auth($token))->assertForbidden();
    }

    public function test_metode_tidak_terdaftar_ditolak(): void
    {
        // Peta khusus: transaksi hanya GET. POST /api/transaksi TETAP
        // melewati middleware (routenya ada), tapi metodenya tak terdaftar
        // pada peta => 403 meski hak add menu 3 tersedia.
        config()->set('orion_api.menus', [
            'transaksi' => ['GET' => [3, 61]],
        ]);
        [, $token] = $this->bikinUser('Kasir Uji', [3 => ['view', 'add']]);

        $this->getJson('/api/transaksi', $this->auth($token))->assertOk();
        // POST tidak terdaftar pada endpoint transaksi di peta test ini => 403.
        $this->postJson('/api/transaksi', ['nama_file' => 'X'], $this->auth($token))
            ->assertForbidden()
            ->assertJsonPath('message', 'Metode POST tidak dibuka pada endpoint ini.');
    }

    public function test_gate_persiapan_lebih_dulu_dari_rbac(): void
    {
        // API mati: gate 503 berjalan SEBELUM RBAC - RBAC tidak melonggarkan.
        config()->set('orion_api.enabled', false);
        [, $token] = $this->bikinUser('Administrator');

        $this->getJson('/api/laporan/kas', $this->auth($token))
            ->assertStatus(503)
            ->assertHeaderContains('Cache-Control', 'no-store');

        // Dan tanpa token pun tetap 503, bukan 401 (gate lebih luar).
        $this->getJson('/api/laporan/kas')->assertStatus(503);
    }
}
