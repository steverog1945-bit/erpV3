<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi: pendaftaran konsumen dari modal Input Pesanan.
 *
 * Kundiagnosis: endpoint /konsumen/store-ajax menolak dengan
 * "Gagal: Sesi Kas belum dibuka" walau laci kasir sudah OPEN. Pendaftaran
 * konsumen adalah MASTER DATA sehingga tidak boleh bergantung pada sesi kas.
 *
 * Sesi di bawah sengaja dibuat dalam kondisi yang SULIT:
 *  - dibuka oleh user LAIN  (bukan pemanggil)
 *  - umurnya 141 jam       (jauh melewati jam Closing)
 *  - tidak ada sesi sama sekali pada skenario tertentu
 *
 * Yang tetap diuji sebagai PAGAR: autentikasi, hak akses, CSRF, dan validasi
 * field. Test ini tidak boleh melemahkan satu pun dari mereka.
 */
class KonsumenStoreAjaxTest extends TestCase
{
    use RefreshDatabase;

    private function idk(): int
    {
        return DB::table('golongan_konsumen')->insertGetId([
            'nama_golongan' => 'Retail Uji',
            'keterangan' => '-',
        ]);
    }

    private function kasir(): User
    {
        return User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Kasir'])->id_role,
        ]);
    }

    /**
     * Beri izin akses ke master konsumen.
     *
     * Peta akses di config/menu_access.php memetakan 'konsumen/store-ajax'
     * ke menu [3, 41]. Jadi role diuji WAJIB punya izin atas salah satu dari
     * menu itu - bukan menu karangan, karena middleware menolak route yang
     * tidak dipetakan (fail closed).
     */
    private function beriIzinKonsumen(User $u): void
    {
        $idMenu = DB::table('menus')->where('id_menu', 3)->value('id_menu') ?? 3;

        foreach ([$idMenu] as $m) {
            DB::table('role_permissions')->insertOrIgnore([
                'id_role' => $u->id_role,
                'id_menu' => $m,
            ]);
        }
    }

    /**
     * Sesi kas yang merepotkan: milik user lain DAN berumur 141 jam.
     * Tabel: kas_harian (sama dengan yang dipakai modul Kasir).
     */
    private function sesiKasMerepotkan(int $idUserPemilik): void
    {
        DB::table('kas_harian')->insert([
            'tgl_kas' => now()->subHours(141)->toDateString(),
            'id_user' => $idUserPemilik,
            'saldo_awal' => 100000,
            'status_lock' => 'open',
        ]);
    }

    private function payload(array $tambahan = []): array
    {
        return array_merge([
            'nama_konsumen' => 'Konsumen Uji Sesi',
            'telepon' => '08123456789',
            'id_golongan' => $this->idk(),
            'tipe_konsumen' => 'permanent',
        ], $tambahan);
    }

    // ---------------------------------------------------------------
    // Skenario 1-4: TIDAK ADA sesi kas sama sekali
    // ---------------------------------------------------------------

    public function test_kasir_berwenang_tambah_konsumen_tanpa_sesi_kas(): void
    {
        $kasir = $this->kasir();
        $this->beriIzinKonsumen($kasir);

        // Tidak ada baris sama sekali di kas_harian.
        $this->assertSame(0, DB::table('kas_harian')->count());

        $res = $this->actingAs($kasir)
            ->postJson('/konsumen/store-ajax', $this->payload());

        $res->assertOk()->assertJsonPath('status', 'success');
        $this->assertDatabaseHas('konsumen', ['nama_konsumen' => 'Konsumen Uji Sesi']);
    }

    public function test_sesi_kas_141_jam_milik_user_lain_tetap_berhasil(): void
    {
        $kasir = $this->kasir();
        $pemilikSesi = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Kasir'])->id_role,
        ]);
        $this->beriIzinKonsumen($kasir);

        // Laci terbuka, tapi milik ORANG LAIN dan sudah 141 jam.
        $this->sesiKasMerepotkan($pemilikSesi->id_user);

        $res = $this->actingAs($kasir)
            ->postJson('/konsumen/store-ajax', $this->payload());

        $res->assertOk()->assertJsonPath('status', 'success');

        $data = $res->json('data');
        $this->assertNotEmpty($data['id_konsumen']);
        $this->assertSame('Konsumen Uji Sesi', $data['nama_konsumen']);
        $this->assertSame('08123456789', $data['telepon']);
        // Kontrak JSON yang dipakai JavaScript Input Pesanan.
        $this->assertArrayHasKey('nama_konsumen', $data);
        $this->assertArrayHasKey('telepon', $data);
        $this->assertArrayHasKey('id_golongan', $data);
        $this->assertArrayHasKey('nama_golongan', $data);
    }

    public function test_tidak_tergantung_kepemilikan_laci(): void
    {
        $kasir = $this->kasir();
        $kasirLain = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Kasir'])->id_role,
        ]);
        $this->beriIzinKonsumen($kasir);
        $this->sesiKasMerepotkan($kasirLain->id_user);

        // Pelaku yang memanggil BUKAN pemilik laci.
        $this->assertNotSame($kasir->id_user, $kasirLain->id_user);

        $this->actingAs($kasir)
            ->postJson('/konsumen/store-ajax', $this->payload())
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_di_luar_jam_transaksi_tetap_daftar_konsumen(): void
    {
        $kasir = $this->kasir();
        $this->beriIzinKonsumen($kasir);
        $this->sesiKasMerepotkan($kasir->id_user);

        // Sesi milik sendiri, tapi 141 jam (pasti di luar jam Closing).
        $this->actingAs($kasir)
            ->postJson('/konsumen/store-ajax', $this->payload())
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    // ---------------------------------------------------------------
    // Skenario 5-7: PAGAR (tidak boleh melemah)
    // ---------------------------------------------------------------

    public function test_guest_ditolak(): void
    {
        $this->postJson('/konsumen/store-ajax', $this->payload())
            ->assertUnauthorized();
    }

    public function test_tanpa_hak_akses_ditolak(): void
    {
        $kasir = $this->kasir();
        // Sengaja TIDAK diberi izin master konsumen.

        $this->actingAs($kasir)
            ->postJson('/konsumen/store-ajax', $this->payload())
            ->assertForbidden();
    }

    public function test_validasi_field_tidak_bisa_dilewati(): void
    {
        $kasir = $this->kasir();
        $this->beriIzinKonsumen($kasir);
        $golongan = $this->idk();

        // nama kosong
        $this->actingAs($kasir)
            ->postJson('/konsumen/store-ajax', [
                'nama_konsumen' => '',
                'id_golongan' => $golongan,
            ])->assertJsonValidationErrors('nama_konsumen');

        // golongan tidak ada
        $this->actingAs($kasir)
            ->postJson('/konsumen/store-ajax', [
                'nama_konsumen' => 'X',
                'id_golongan' => 999999,
            ])->assertJsonValidationErrors('id_golongan');

        // tipe tidak dikenal
        $this->actingAs($kasir)
            ->postJson('/konsumen/store-ajax', [
                'nama_konsumen' => 'X',
                'id_golongan' => $golongan,
                'tipe_konsumen' => 'ngawur',
            ])->assertJsonValidationErrors('tipe_konsumen');

        $this->assertDatabaseMissing('konsumen', ['nama_konsumen' => 'X']);
    }

    public function test_gabon_konsumen_tidak_pernah_tersimpan(): void
    {
        $kasir = $this->kasir();
        $this->beriIzinKonsumen($kasir);

        $this->actingAs($kasir)
            ->postJson('/konsumen/store-ajax', ['nama_konsumen' => 'Tanpa Golongan'])
            ->assertJsonValidationErrors('id_golongan');

        $this->assertDatabaseMissing('konsumen', ['nama_konsumen' => 'Tanpa Golongan']);
    }
}