<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AUDIT Â§8 - keamanan pada alur Customer -> Order -> SPK -> Produksi ->
 * Collecting -> Invoice -> Payment -> Journal.
 *
 * PENTING soal cakupan: aplikasi ini ERP SATU PERUSAHAAN. Transaksi, SPK,
 * dan konsumen TIDAK punya kolom pemilik (id_user/id_karyawan/id_divisi) -
 * sudah diperiksa di database. Semua staf memang melihat semua order; itu
 * bukan kebocoran, itu desain. Karena itu IDOR antar-pengguna BUKAN kategori
 * finding yang berlaku di sini, dan test ini tidak memaksakan sebaliknya.
 *
 * Yang benar-benar menjadi batas keamanan adalah PERAN, ditegakkan oleh
 * middleware CheckMenuAccess (alias "menu.access"). Test ini membuktikan
 * Enforcement itu benar-benar jalan, bukan sekadar ada.
 *
 * Yang diuji - semuanya lewat HTTP sungguhan, bukan assertExists middleware:
 *   1. Tanpa login ditolak (redirect ke login)
 *   2. Role tanpa izin ditolak 403
 *   3. Boleh lihat != boleh tambah/ubah/hapus
 *   4. Role baca-saja (Owner) tidak boleh mengubah data apa pun
 *   5. Administrator tidak terkunci sendiri
 *   6. Respons 403 tidak membocorkan isi data
 *   7. Mengubah id di URL tidak melewati pemeriksaan izin
 */
class AlurKeamananTest extends TestCase
{
    use RefreshDatabase;

    private Role $roleAdmin;
    private Role $roleBacaSaja;
    private Role $roleTanpaIzin;

    /** Endpoint yang isi datanya dicari Throughout alur ini. */
    private const ALUR = [
        '/konsumen',
        '/spk',
        '/produksi',
        '/collecting',
        '/jersey',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleAdmin = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->roleBacaSaja = Role::factory()->create(['nama_role' => 'Owner']);
        $this->roleTanpaIzin = Role::factory()->create(['nama_role' => 'Tanpa Izin']);
    }

    /** @return array<int, array{0:string,1:bool,2:bool,3:bool}> */
    private function hak(int $idRole, int $idMenu, bool $view, bool $add = false, bool $edit = false, bool $delete = false): array
    {
        return [$idRole, $idMenu, $view, $add, $edit, $delete];
    }

    private function izin(array $baris): void
    {
        foreach ($baris as $r) {
            // id_menu punya FK ke tabel menus, jadi harus benar-benar ada.
            $ada = DB::table('menus')->where('id_menu', $r[1])->exists();
            if (!$ada) {
                DB::table('menus')->insert([
                    'id_menu' => $r[1],
                    'nama_menu' => 'Menu Uji ' . $r[1],
                    'url_link' => 'uji-' . $r[1],
                ]);
            }

            DB::table('role_permissions')->insert([
                'id_role' => $r[0],
                'id_menu' => $r[1],
                'can_view' => $r[2] ? 1 : 0,
                'can_add' => $r[3] ? 1 : 0,
                'can_edit' => $r[4] ? 1 : 0,
                'can_delete' => $r[5] ? 1 : 0,
            ]);
        }
    }

    private function user(Role $role): User
    {
        return User::factory()->create(['id_role' => $role->id_role]);
    }

    /**
     * bahan_baku punya FK NOT NULL ke kategori dan mesin, jadi keduanya harus
     * ada dulu. Dipakai oleh test yang butuh satu baris bahan nyata.
     */
    private function bahanBaku(string $kode, string $nama): int
    {
        $kategori = (int) (DB::table('kategori')->where('nama_kategori', 'Kat ' . $kode)->value('id_kategori')
            ?: DB::table('kategori')->insertGetId(['nama_kategori' => 'Kat ' . $kode]));

        $mesin = (int) (DB::table('mesin')->where('nama_mesin', 'Mesin ' . $kode)->value('id_mesin')
            ?: DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin ' . $kode, 'tipe_mesin' => 'Uji']));

        return (int) DB::table('bahan_baku')->insertGetId([
            'nama_bahan' => $nama,
            'kode_bahan' => $kode,
            'id_kategori' => $kategori,
            'id_mesin' => $mesin,
            'satuan_stok' => 'Pcs',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /* ================================================================
       1. Tanpa login
       ================================================================ */
    public function test_endpoint_alur_menolak_tanpa_login(): void
    {
        foreach (self::ALUR as $url) {
            $r = $this->get($url);

            $this->assertTrue(
                $r->isRedirect() || $r->status() === 401 || $r->status() === 403,
                "{$url} tanpa login seharusnya ditolak, dapat status {$r->status()}"
            );

            // Halaman yang dilindungi tidak boleh bocor lewat respons 200.
            // Di aplikasi ini login berupa modal di landing page "/", jadi
            // tujuan redirect adalah "/" BUKAN "/login" - yang diuji di sini
            // adalah tidak adanya data, bukan tujuan redirect-nya.
            if ($r->isRedirect()) {
                $tujuan = (string) $r->headers->get('Location');
                $this->assertNotSame(
                    $url,
                    $tujuan,
                    "{$url} tanpa login tidak boleh kembali ke dirinya sendiri (loop)"
                );
            }
        }
    }

    /**
     * Yang paling penting: tanpa login, ISI HALAMAN tidak boleh keluar sama
     * sekali - bukan hanya statusnya yang berbeda.
     */
    public function test_endpoint_alur_tanpa_login_tidak_membocorkan_isi_halaman(): void
    {
        $this->bahanBaku('RAHASIA002', 'Bahan Jangan Bocor');

        foreach (self::ALUR as $url) {
            $r = $this->get($url);
            if ($r->status() === 200) {
                $this->fail("{$url} tanpa login mengembalikan 200 - halaman tidak terlindungi");
            }
            $this->assertStringNotContainsString(
                'Bahan Jangan Bocor',
                (string) $r->getContent(),
                "{$url} tanpa login jangan sampai memuat data tabel master"
            );
        }
    }

    /** Query string dan AJAX tidak boleh jadi jalur bypass. */
    public function test_endpoint_alur_ditolak_tanpa_login_waupun_via_ajax(): void
    {
        foreach (self::ALUR as $url) {
            $r = $this->getJson($url);
            $this->assertContains(
                $r->status(),
                [401, 403],
                "{$url} via AJAX tanpa login harus 401/403, dapat {$r->status()}"
            );
        }
    }

    /* ================================================================
       2. Role tanpa izin
       ================================================================ */
    public function test_role_tanpa_izin_menolak_dengan_403(): void
    {
        $u = $this->user($this->roleTanpaIzin);

        foreach (self::ALUR as $url) {
            $r = $this->actingAs($u)->get($url);
            $this->assertSame(403, $r->status(), "{$url} harus 403 untuk role tanpa izin");
        }
    }

    /* ================================================================
       3. Boleh lihat != boleh mengubah
       ================================================================ */
    public function test_boleh_lihat_tetapi_tidak_boleh_tambah_data(): void
    {
        // id_menu 1 = bahan-baku (lihat config/menu_access.php: 'bahan-baku' => [1])
        $this->izin([[$this->roleTanpaIzin->id_role, 1, true, false, false, false]]);
        $u = $this->user($this->roleTanpaIzin);

        $lihat = $this->actingAs($u)->get('/bahan-baku');
        $this->assertSame(200, $lihat->status(), 'Role dengan izin view harus bisa membuka halaman');

        $tambah = $this->actingAs($u)->post('/bahan-baku', [
            'nama_bahan' => 'Bahan Uji',
            'kode_bahan' => 'UJI001',
            'id_kategori' => 1,
        ]);
        $this->assertSame(403, $tambah->status(), 'Role view-only tidak boleh menambah data');
        $this->assertDatabaseMissing('bahan_baku', ['kode_bahan' => 'UJI001']);
    }

    /* ================================================================
       4. Role baca-saja tidak boleh mengubah apa pun
       ================================================================ */
    public function test_role_baca_saja_ditolak_mengubah_data(): void
    {
        // Role "Owner" ada di config menu_access.roles_baca_saja.
        // Beri izin LENGAP supaya yang diuji benar-benar gembok baca-saja,
        // bukan ketiadaan izin menu.
        $this->izin([
            [$this->roleBacaSaja->id_role, 1, true, true, true, true],
            [$this->roleBacaSaja->id_role, 2, true, true, true, true],
        ]);
        $u = $this->user($this->roleBacaSaja);

        $lihat = $this->actingAs($u)->get('/bahan-baku');
        $this->assertSame(200, $lihat->status(), 'Role baca-saja harus tetap boleh melihat');

        $tambah = $this->actingAs($u)->post('/bahan-baku', [
            'nama_bahan' => 'Harus Ditolak',
            'kode_bahan' => 'DITOLAK001',
            'id_kategori' => 1,
        ]);
        $this->assertSame(403, $tambah->status(), 'Role baca-saja harus ditolak saat menambah');
        $this->assertDatabaseMissing('bahan_baku', ['kode_bahan' => 'DITOLAK001']);
    }

    /* ================================================================
       5. Administrator tidak terkunci
       ================================================================ */
    public function test_administrator_tidak_terkunci_sendiri(): void
    {
        $u = $this->user($this->roleAdmin);

        foreach (self::ALUR as $url) {
            $r = $this->actingAs($u)->get($url);
            $this->assertContains(
                $r->status(),
                [200, 302],
                "Administrator harus boleh membuka {$url}, dapat {$r->status()}"
            );
        }
    }

    /* ================================================================
       6. Respons 403 tidak membocorkan data
       ================================================================ */
    public function test_respons_403_tidak_membocorkan_isi_data(): void
    {
        // Siapkan data sensitif yang TIDAK boleh keluar lewat respons 403.
        $this->bahanBaku('RAHASIA001', 'Bahan Super Rahasia');

        $u = $this->user($this->roleTanpaIzin);
        $r = $this->actingAs($u)->get('/bahan-baku');

        $this->assertSame(403, $r->status());
        $isi = $r->getContent();
        $this->assertStringNotContainsString('Bahan Super Rahasia', (string) $isi,
            'Respons 403 tidak boleh membocorkan nama bahan');
        $this->assertStringNotContainsString('RAHASIA001', (string) $isi,
            'Respons 403 tidak boleh membocorkan kode bahan');

        // Versi JSON juga tidak boleh bocor.
        $j = $this->actingAs($u)->getJson('/bahan-baku');
        $this->assertSame(403, $j->status());
        $this->assertStringNotContainsString('RAHASIA001', $j->getContent(),
            'Respons 403 JSON tidak boleh membocorkan kode bahan');
    }

    /* ================================================================
       7. Mengubah id di URL tidak melewati pemeriksaan izin
       ================================================================ */
    public function test_mengubah_id_di_url_tidak_melewati_pemeriksaan_izin(): void
    {
        // Ini yang paling dekat dengan IDOR di aplikasi tanpa kepemilikan baris:
        // apakah memindahkan/mengubah id di URL bisa melewati pagar menu?
        // Untuk modul yang TIDAK punya izin, jawabannya harus tetap 403
        // berapa pun id yang dicoba.
        $u = $this->user($this->roleTanpaIzin);

        // Hanya URL yang benar-benar punya route GET dengan id.
        // /konsumen/{id} sengaja tidak dipakai: resource-nya except(['show'])
        // jadi route-nya memang tidak ada (menjawab 405, bukan temuan keamanan).
        foreach ([1, 2, 999999, 0] as $id) {
            foreach (['/jersey/' . $id, '/spk/' . $id . '/print'] as $url) {
                $r = $this->actingAs($u)->get($url);
                $this->assertContains(
                    $r->status(),
                    [403, 404],
                    "{$url} harus 403/404 untuk role tanpa izin (id {$id}), dapat {$r->status()}"
                );
            }
        }
    }

    /** Melewati id tidak boleh membuat perbedaan: 403 bukan 404 ( Existence leak). */
    public function test_id_ada_dan_tidak_ada_memberi_status_yang_sama(): void
    {
        $adaId = $this->bahanBaku('ADA001', 'Ada');

        $u = $this->user($this->roleTanpaIzin);

        $sAda = $this->actingAs($u)->get('/bahan-baku/' . $adaId)->status();
        $sTakAda = $this->actingAs($u)->get('/bahan-baku/99999999')->status();

        $this->assertSame(
            $sAda,
            $sTakAda,
            "Status harus sama untuk id yang ada ({$sAda}) dan yang tidak ada ({$sTakAda}) "
            . '- kalau berbeda, keberadaan data bisa ditebak dari perbedaan status'
        );
    }

    /* ================================================================
       8. Endpoint privat tidak bocor lewat publik
       ================================================================ */
    public function test_storage_privat_menolak_folder_di_luar_daftar(): void
    {
        $u = $this->user($this->roleAdmin);

        // Folder di luar whitelist harus 404 walau admin.
        foreach (['app', 'logs', 'framework', '..'] as $folder) {
            $r = $this->actingAs($u)->get('/storage/' . $folder . '/apa-saja.jpg');
            $this->assertContains($r->status(), [403, 404],
                "Folder /storage/{$folder} harus ditolak, dapat {$r->status()}");
        }
    }

    public function test_storage_privat_menolak_path_traversal(): void
    {
        $u = $this->user($this->roleAdmin);

        foreach ([
            '/storage/order_thumbnails/../../.env',
            '/storage/order_thumbnails/..%2F..%2F.env',
            '/storage/order_thumbnails/....//....//.env',
        ] as $url) {
            $r = $this->actingAs($u)->get($url);
            $this->assertContains($r->status(), [403, 404],
                "Path traversal {$url} harus ditolak, dapat {$r->status()}");
        }
    }

    /**
     * Tanpa login, berkas privat tidak boleh terbaca.
     *
     * 302 ke /login juga sah: middleware "auth" yang menolak, dan itu memang
     * perilaku standarnya di aplikasi ini. Yang penting: tidak ada byte isi
     * berkas yang keluar.
     */
    public function test_storage_privat_menolak_tanpa_login(): void
    {
        $r = $this->get('/storage/order_thumbnails/apa-saja.jpg');

        $this->assertContains(
            $r->status(),
            [302, 401, 403, 404],
            'Berkas privat harus menolak tanpa login, dapat ' . $r->status()
        );
        $this->assertStringNotContainsString(
            '<?php',
            (string) $r->getContent(),
            'Respons tanpa login tidak boleh memuat isi berkas'
        );
    }
}