<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * API publik (routes/api.php): autentikasi lewat Bearer token, lalu menyajikan
 * data transaksi, produksi (SPK), stok bahan, dan laporan dalam format JSON.
 *
 * Dua hal yang dijaga di sini:
 *  - Tanpa token (atau token salah) berhenti di 401, bukan bocor data.
 *  - Endpoint GET hanya membaca; POST checkout ditutup hingga setara dengan web.
 */
class ApiPublikTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // API tetap dapat diuji terisolasi meski default produksi ditutup.
        config()->set('orion_api.enabled', true);

        // RBAC API aktif (ApiRbac): role acak dari factory tidak punya baris
        // hak akses apa pun sehingga semua endpoint akan 403. Test ini
        // membuktikan pembacaan data, bukan RBAC - gunakan Administrator.
        $roleAdmin = \App\Models\Role::factory()->create(['nama_role' => 'Administrator']);
        $this->user = User::factory()->create([
            'username' => 'apiuser',
            'id_role' => $roleAdmin->id_role,
        ]);
        $this->token = \Tests\Support\TokenApiPengujian::token($this->user);

        // Satu nota + satu item produksi + satu bahan + satu pembayaran,
        // cukup untuk membuktikan tiap endpoint membaca data nyata.
        $idGolongan = DB::table('golongan_konsumen')->insertGetId([
            'nama_golongan' => 'Retail', 'keterangan' => '-',
        ]);
        $idKonsumen = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Pelanggan API', 'telepon' => '0812', 'alamat' => '-',
            'id_golongan' => $idGolongan, 'tipe_konsumen' => 'temporary', 'tgl_registrasi' => now(),
        ]);
        $idTransaksi = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'ORG-API-001', 'tgl_order' => now(), 'id_konsumen' => $idKonsumen,
            'total_bayar' => 100000, 'status_pembayaran' => 'DP', 'status_nota' => 'Aktif',
            'status_order' => 'Diterima Kasir', 'id_user_kasir' => $this->user->id_user,
        ]);
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $idTransaksi, 'nama_file' => 'File API', 'qty' => 2,
            'p_ukuran' => 100, 'l_ukuran' => 100,
            'biaya_cetak' => 50000, 'subtotal' => 100000, 'status_spk' => 'Antre',
        ]);
        DB::table('pembayaran_riwayat')->insert([
            'id_transaksi' => $idTransaksi, 'nominal' => 40000, 'metode' => 'Tunai', 'tgl_bayar' => now(),
        ]);
        $idMesin = DB::table('mesin')->insertGetId([
            'nama_mesin' => 'Mesin API', 'tipe_mesin' => 'Digital',
        ]);
        $idKategori = DB::table('kategori')->insertGetId([
            'nama_kategori' => 'Kategori API',
        ]);
        $this->idBahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'API01', 'nama_bahan' => 'Bahan API', 'id_mesin' => $idMesin,
            'id_kategori' => $idKategori, 'stok' => 10, 'stok_minimal' => 2,
            'satuan_stok' => 'pcs', 'status' => 'Aktif',
        ]);
        DB::table('kartu_stok')->insert([
            'id_bahan' => $this->idBahan, 'tanggal' => now(), 'tipe' => 'Masuk', 'qty' => 10,
            'stok_sebelum' => 0, 'stok_sesudah' => 10, 'referensi' => 'API', 'created_by' => $this->user->id_user,
        ]);

        $this->idTransaksi = $idTransaksi;
    }

    private int $idBahan;
    private int $idTransaksi;

    private function auth(string $extra = ''): array
    {
        return ['Authorization' => 'Bearer ' . $this->token, 'Accept' => 'application/json'] + (array) json_decode($extra, true);
    }

    public function test_tanpa_token_ditolak(): void
    {
        $this->getJson('/api/konsumen')->assertUnauthorized();
        $this->getJson('/api/stok')->assertUnauthorized();
        $this->getJson('/api/laporan/penjualan')->assertUnauthorized();
    }

    public function test_token_salah_ditolak(): void
    {
        $this->getJson('/api/konsumen', ['Authorization' => 'Bearer ' . str_repeat('0', 80)])
            ->assertUnauthorized();
    }

    public function test_master_dan_transaksi_terbaca(): void
    {
        $this->getJson('/api/konsumen', $this->auth())->assertOk()
            ->assertJsonPath('status', 'success');
        $this->getJson('/api/produk', $this->auth())->assertOk();
        $this->getJson('/api/transaksi', $this->auth())->assertOk()
            ->assertJsonFragment(['nomor_nota' => 'ORG-API-001']);
        $this->getJson('/api/transaksi/' . $this->idTransaksi, $this->auth())->assertOk();
    }

    public function test_pembayaran_dan_sisa_tagihan(): void
    {
        $this->getJson('/api/pembayaran', $this->auth())->assertOk()
            ->assertJsonFragment(['metode' => 'Tunai']);

        $this->getJson('/api/transaksi/' . $this->idTransaksi . '/pembayaran', $this->auth())
            ->assertOk()
            ->assertJsonPath('data.total_bayar', 100000)
            ->assertJsonPath('data.total_dibayar', 40000)
            ->assertJsonPath('data.sisa_tagihan', 60000);
    }

    public function test_spk_dan_stok_terbaca(): void
    {
        $this->getJson('/api/spk', $this->auth())->assertOk()
            ->assertJsonFragment(['status_spk' => 'Antre']);

        $this->getJson('/api/stok', $this->auth())->assertOk()
            ->assertJsonFragment(['nama_bahan' => 'Bahan API']);

        $this->getJson('/api/stok/' . $this->idBahan . '/kartu', $this->auth())->assertOk()
            ->assertJsonPath('data.stok', 10);
    }

    public function test_laporan_terhitung(): void
    {
        $this->getJson('/api/laporan/penjualan', $this->auth())->assertOk()
            ->assertJsonPath('total_nilai', 100000);

        $this->getJson('/api/laporan/piutang', $this->auth())->assertOk()
            ->assertJsonPath('total_piutang', 60000);

        $this->getJson('/api/laporan/kas', $this->auth())->assertOk();
    }

    public function test_dashboard_kpi_terbaca(): void
    {
        $this->getJson('/api/dashboard/kpi', $this->auth())->assertOk()
            ->assertJsonPath('status', 'success');
    }
}
