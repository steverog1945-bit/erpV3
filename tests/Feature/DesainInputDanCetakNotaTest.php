<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dua pemisahan hak yang diuji di sini sama-sama berakar dari "role punya satu
 * menu tapi haknya bukan satu jenis", dan keduanya HARUS tetap terpisah:
 *
 * 1. Biaya Desain vs harga. Dulu tombol "+ Biaya Desain" dan input nominalnya
 *    di Input Order di-gate oleh @bolehHarga, jadi Operator Desain (yang
 *    pekerjaannya justru desain, tapi tidak boleh melihat harga) tidak pernah
 *    bisa mengisi biaya yang ia kerjakan. Sebelumnya bagian biaya desain ikut
 *    dihitung server saat role tidak boleh melihat harga. Sekarang keduanya
 *    dipisah: roles.boleh_input_biaya_desain untuk input Biaya Desain saja,
 *    boleh_lihat_harga tetap governs seluruh harga lain. Yang diuji di sini
 *   Biaya CETAK tetap terkunci walau role punya izin input desain - kalau
 *    ini bocor berarti role tanpa akses harga bisa menimpa harga barang.
 *
 * 2. Melihat Riwayat Nota vs mencetak ulang nota. Menu 61 dipakai supaya role
 *    bisa membaca daftar nota; role itu mewarisi hak cetak nota dari aturan
 *    yang sama. print-opsi sekarang butuh menu 3/16/48, bukan 61.
 */
class DesainInputDanCetakNotaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Role dengan menu tertentu. $bolehHarga dan $bolehDesain sengaja
     * diturunkan dari DEFAULT kolom (bukan di-set di sini) supaya test ikut
     * menangkap kalau default kolom berubah jadi 1 - test harus gagal kalau
     * ada role yang diam-diam boleh sebelum pemilik mencentangnya.
     */
    private function role(string $nama, array $menus, ?bool $bolehHarga = null, ?bool $bolehDesain = null): Role
    {
        $role = Role::factory()->create(['nama_role' => $nama]);
        if ($bolehHarga !== null) {
            DB::table('roles')->where('id_role', $role->id_role)->update(['boleh_lihat_harga' => $bolehHarga]);
        }
        if ($bolehDesain !== null) {
            DB::table('roles')->where('id_role', $role->id_role)->update(['boleh_input_biaya_desain' => $bolehDesain]);
        }
        foreach ($menus as $idMenu) {
            DB::table('menus')->insertOrIgnore(['id_menu' => $idMenu, 'nama_menu' => "Menu {$idMenu}", 'url_link' => "m{$idMenu}", 'parent_id' => 0]);
            DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => $idMenu]);
        }
        $role->refresh();

        return $role;
    }

    private function user(Role $role): User
    {
        $user = User::factory()->create(['id_role' => $role->id_role]);
        DB::table('kas_harian')->insert([
            'tgl_kas' => now()->toDateString(),
            'id_user' => $user->id_user,
            'saldo_awal' => 0,
            'status_lock' => 'open',
            'created_at' => now()->subMinute(),
        ]);

        return $user;
    }

    /** Produk + golongan + harga 5.000, supaya hargaCetak = 3 x 5.000 = 15.000. */
    private function master(): array
    {
        $idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $idKons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Uji', 'telepon' => '0811', 'alamat' => '-', 'id_golongan' => $idGol]);
        $idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'Uji Produk', 'metode_hitung' => 'quantity', 'status' => 'Aktif']);
        DB::table('setting_harga')->insert(['id_produk' => $idProduk, 'id_golongan' => $idGol, 'range_min' => 1, 'harga_jual' => 5000]);

        return [$idKons, $idGol, $idProduk];
    }

    private function payload(int $idKons, int $idGol, int $idProduk): array
    {
        return [
            'id_konsumen' => $idKons, 'id_golongan' => $idGol,
            'items' => [[
                'id_produk' => $idProduk, 'nama_file' => 'a.pdf', 'qty_input' => 3, 'p' => 0, 'l' => 0,
                // Klien sengaja mengirim angka yang dipalsukan: biaya cetak
                // dipalsukan jadi 1, biaya desain dipalsukan jadi 999.999.
                // Yang diterima server hanya bila role punya haknya masing-masing.
                'biaya_cetak' => 1, 'biaya_desain' => 999999,
            ]],
        ];
    }

    private function detailTerakhir(): object
    {
        return DB::table('transaksi_detail')->latest('id_detail')->first();
    }

    // ---------- 1. Pemisahan hak input Biaya Desain vs hak lihat harga ----------

    public function test_biaya_desain_masuk_klien_dipaksa_nol_kalau_role_tidak_dicentang(): void
    {
        $role = $this->role('Operator Desain Tanpa Izin Desain', [3, 41], false);
        $user = $this->user($role);
        [$idKons, $idGol, $idProduk] = $this->master();

        $this->actingAs($user)->postJson('/penjualan/store', $this->payload($idKons, $idGol, $idProduk))->assertOk();

        $detail = $this->detailTerakhir();
        $this->assertSame(0.0, (float) $detail->biaya_desain, 'tanpa izin designs, nilai desain dari klien harus dipaksa 0');
        $this->assertSame(15000.0, (float) $detail->biaya_cetak, 'biaya cetak tetap dihitung server dari master');
    }

    public function test_role_tanpa_hak_lihat_harga_tapi_boleh_input_desain_tetap_bisa_mengisi_biaya_desain(): void
    {
        // Izinkan desain SAJA. Harga produk/cetak/finishing tetap tertutup.
        $role = $this->role('Operator Desain', [3, 41], false, true);
        $user = $this->user($role);
        [$idKons, $idGol, $idProduk] = $this->master();

        $this->actingAs($user)->postJson('/penjualan/store', $this->payload($idKons, $idGol, $idProduk))->assertOk();

        $detail = $this->detailTerakhir();
        $this->assertSame(999999.0, (float) $detail->biaya_desain, 'role berizin desain harus bisa mengisi biaya desain');
        // Pagar mutlak: izin desain TIDAK boleh ikut melonggarkan harga barang.
        $this->assertSame(15000.0, (float) $detail->biaya_cetak, 'izin desain tidak boleh membuat biaya cetak bisa ditimpa klien');
        $this->assertSame(15000.0 + 999999.0, (float) DB::table('transaksi')->value('total_bayar'));
    }

    public function test_alur_lengkap_centang_lalu_muncul_di_input_order(): void
    {
        // Menggabungkan kedua separuh dalam satu skenario, persis urutan yang
        // dilakukan operator: buka Pengaturan Hak Akses -> centang "Boleh
        // menambah Biaya Desain" -> Simpan -> buka Input Order. Kalau centangnya
        // tidak tersimpan, dua test lain tetap hijau karena mereka menulis flag
        // langsung ke database - hanya test ini yang benar-benar memakai jalurnya.
        $admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
        DB::table('roles')->where('nama_role', 'Administrator')->update([
            'boleh_lihat_harga' => true,
            'boleh_input_biaya_desain' => true,
        ]);

        $target = $this->role('Operator Desain', [3, 41], false, false);
        $desainer = $this->user($target);

        // Sebelum dicentang: tidak ada tombol, input terkunci.
        $html = $this->actingAs($desainer)->get('/penjualan/kasir')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="btn_tambah_desain"\s+[^>]*hidden/', $html, 'sebelum dicentang, tombol harus tersembunyi');

        // Centang lewat form Pengaturan Hak Akses.
        $this->actingAs($admin)->post(route('akses.update'), [
            'role_id' => $target->id_role,
            '_versi' => '',
            'boleh_input_biaya_desain' => '1',
            'izin' => [3 => ['view' => '1']],
        ])->assertSessionMissing('error');

        // Sesudahnya: tombol tampil dan input bisa diisi.
        // refresh() WAJIB: $desainer dipakai lagi untuk request kedua, dan
        // relasi role-nya sudah ter-cache dengan nilai lama sejak request
        // pertama. Tanpa refresh, test ini menguji data basi - bukan kondisi
        // nyata. Di aplikasi nyata user di-load ulang dari database tiap
        // request, jadi tidak ada masalah cache seperti ini.
        $desainer->refresh();
        $desainer->load('role');
        $html = $this->actingAs($desainer)->get('/penjualan/kasir')->assertOk()->getContent();
        $this->assertSame(1, (int) $desainer->role->boleh_input_biaya_desain, 'basis data harus sudah nyala sebelum halaman dirender');
        $this->assertDoesNotMatchRegularExpression('/id="btn_tambah_desain"\s+[^>]*hidden/', $html, 'sesudah dicentang, tombol "+ Biaya Desain" harus tampil');
        $this->assertDoesNotMatchRegularExpression('/id="input_desain"\s+readonly/', $html, 'sesudah dicentang, input Biaya Desain harus bisa diisi');

        // Dan harga produk tetap tertutup untuk role ini: isian harga yang
        // memuat nominal produk harus tetap terkunci. Yang diuji adalah
        // properti yang benar-benar relevan (input harga tidak bisa diedit),
        // bukan string tertentu, karena isi halaman bisa berubahsesaat-waktu.
        $this->assertMatchesRegularExpression('/id="input_cetak"\s+readonly/', $html, 'menyalakan izin desain tidak boleh membuka input biaya cetak');
        $this->assertMatchesRegularExpression('/id="input_finishing_biaya"\s+readonly/', $html, 'menyalakan izin desain tidak boleh membuka input biaya finishing');
    }

    public function test_centang_di_halaman_pengaturan_akses_benar_benar_tersimpan(): void
    {
        // Jalur yang SEBENARNYA dipakai operator: centang di
        // resources/views/akses/index.blade.php lalu tekan "Simpan Hak Akses".
        // Test lain di berkas ini menulis flag langsung lewat query builder,
        // jadi kalau formnya tidak pernah mengirim field ini, semua test lain
        // tetap hijau padahal.fiturnya tidak bisa dipakai sama sekali.
        $admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
        DB::table('roles')->where('nama_role', 'Administrator')->update([
            'boleh_lihat_harga' => true,
            'boleh_input_biaya_desain' => true,
        ]);

        $target = $this->role('Desain Tanpa Izin', [3, 41], false, false);

        // Versi dikosongkan: menguji penyimpanan, bukan optimistic locking.
        $res = $this->actingAs($admin)->post(route('akses.update'), [
            'role_id' => $target->id_role,
            '_versi' => '',
            'boleh_lihat_harga' => '0',
            'boleh_input_biaya_desain' => '1',
            'izin' => [3 => ['view' => '1']],
        ]);

        $res->assertRedirect(route('akses.index', ['role' => $target->id_role]));
        $res->assertSessionMissing('error');

        $tersimpan = (int) DB::table('roles')->where('id_role', $target->id_role)->value('boleh_input_biaya_desain');
        $this->assertSame(1, $tersimpan, 'centang "Boleh menambah Biaya Desain" harus tersimpan ke database');
    }

    public function test_centang_kosong_mematikan_izin_desain_yang_sebelumnya_nyala(): void
    {
        $admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
        DB::table('roles')->where('nama_role', 'Administrator')->update([
            'boleh_lihat_harga' => true,
            'boleh_input_biaya_desain' => true,
        ]);

        $target = $this->role('Desain Dengan Izin', [3, 41], false, true);

        $this->actingAs($admin)->post(route('akses.update'), [
            'role_id' => $target->id_role,
            '_versi' => '',
            'boleh_lihat_harga' => '0',
            'izin' => [3 => ['view' => '1']],
        ])->assertSessionMissing('error');

        $tersimpan = (int) DB::table('roles')->where('id_role', $target->id_role)->value('boleh_input_biaya_desain');
        $this->assertSame(0, $tersimpan, 'melepas centang harus mematikan izin desain');
    }

    public function test_centang_boleh_lihat_harga_juga_bisa_disimpan_lewat_form(): void
    {
        // Centang "Boleh melihat & mengubah harga" punya bug yang sama persis
        // dan sudah lama: closure update() tidak menangkap $validated, jadi nilainya
        // selalu tersimpan 0 apa pun yang diklik. Nilai yang sudah terlihat benar di
        // database memang diisi langsung, bukan lewat form ini. Test ini mengunci
        // agar tidak rusak diam-diam lagi.
        $admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
        DB::table('roles')->where('nama_role', 'Administrator')->update([
            'boleh_lihat_harga' => true,
            'boleh_input_biaya_desain' => true,
        ]);

        $target = $this->role('Tanpa Harga', [3, 41], false, false);

        $this->actingAs($admin)->post(route('akses.update'), [
            'role_id' => $target->id_role,
            '_versi' => '',
            'boleh_lihat_harga' => '1',
            'izin' => [3 => ['view' => '1']],
        ])->assertSessionMissing('error');

        $tersimpan = (int) DB::table('roles')->where('id_role', $target->id_role)->value('boleh_lihat_harga');
        $this->assertSame(1, $tersimpan, 'centang "Boleh melihat & mengubah harga" harus tersimpan');
    }

    public function test_hak_lihat_harga_tetap_sudah_cukup_untuk_mengisi_biaya_desain(): void
    {
        $role = $this->role('Kasir', [3, 41], true, false);
        $user = $this->user($role);
        [$idKons, $idGol, $idProduk] = $this->master();

        $this->actingAs($user)->postJson('/penjualan/store', $this->payload($idKons, $idGol, $idProduk))->assertOk();

        $detail = $this->detailTerakhir();
        $this->assertSame(1.0, (float) $detail->biaya_cetak, 'role berhak harga boleh menimpa biaya cetak seperti sebelumnya');
        // Kasir/Gudang/Owner tidak akan pernah dicentang di kolom baru, karena
        // biaya desain memang punya mereka. Kalau biomarker baru jadi satu-satunya
        // syarat, mereka kehilangan field yang mereka isi sebelum pemisahan hak ini
        // dibuat - itu regresi, bukan tightening. Syaratnya: boleh melihat harga
        // ATAU punya izin baru.
        $this->assertSame(999999.0, (float) $detail->biaya_desain, 'boleh melihat harga tetap berarti boleh mengisi biaya desain, seperti sebelum pemisahan hak');
    }

    public function test_tanpa_hak_harga_maupun_hak_desain_desain_dipaksa_nol(): void
    {
        $role = $this->role('Tanpa Hak Apapun', [3, 41], false, false);
        $user = $this->user($role);
        [$idKons, $idGol, $idProduk] = $this->master();

        $this->actingAs($user)->postJson('/penjualan/store', $this->payload($idKons, $idGol, $idProduk))->assertOk();

        $detail = $this->detailTerakhir();
        $this->assertSame(15000.0, (float) $detail->biaya_cetak, 'biaya cetak tetap dihitung server dari master');
        $this->assertSame(0.0, (float) $detail->biaya_desain, 'tanpa izin desain, nilai desain dipaksa 0');
    }

    public function test_administrator_selalu_boleh_input_desain_walau_tidak_ada_centang(): void
    {
        $role = $this->role('Administrator', [], null, false);
        $user = $this->user($role);
        [$idKons, $idGol, $idProduk] = $this->master();

        $this->actingAs($user)->postJson('/penjualan/store', $this->payload($idKons, $idGol, $idProduk))->assertOk();

        $this->assertSame(999999.0, (float) $this->detailTerakhir()->biaya_desain);
    }

    public function test_kolom_default_gagal_tertutup_untuk_role_baru(): void
    {
        $role = $this->role('Desain Tanpa Centang', [3, 41]);
        $this->assertSame(0, (int) DB::table('roles')->where('id_role', $role->id_role)->value('boleh_input_biaya_desain'));
    }

    public function test_halaman_input_order_menampilkan_tombol_desain_hanya_bila_dicentang(): void
    {
        $tanpa = $this->user($this->role('Desain Tanpa Izin', [3, 41], false));
        $html = $this->actingAs($tanpa)->get('/penjualan/kasir')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="input_desain"\s+readonly/', $html, 'tanpa izin desain, input harus readonly');
        $this->assertMatchesRegularExpression('/id="btn_tambah_desain"\s+[^>]*hidden/', $html, 'tanpa izin desain, tombol + Biaya Desain harus disembunyikan');

        $dengan = $this->user($this->role('Desain Dengan Izin', [3, 41], false, true));
        $html = $this->actingAs($dengan)->get('/penjualan/kasir')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/id="input_desain"\s+readonly/', $html, 'dengan izin desain, input harus bisa diisi');
        $this->assertDoesNotMatchRegularExpression('/id="btn_tambah_desain"\s+[^>]*hidden/', $html, 'dengan izin desain, tombol harus tampil');
    }

    public function test_hak_desain_tidak_membuka_halaman_harga(): void
    {
        $user = $this->user($this->role('Desain Dengan Izin', [3, 41], false, true));
        $this->actingAs($user)->get('/finishing')->assertForbidden();
        $this->actingAs($user)->get('/penjualan/kasir')->assertOk();
    }

    // ---------- 2. Menu 61 (lihat riwayat) tidak lagi berarti boleh cetak nota ----------

    private function nota(): object
    {
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Toko Maju', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        $kasir = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Kasir'])->id_role]);
        $idTransaksi = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'ORI-001', 'tipe_order' => 'Reguler', 'id_konsumen' => $kons, 'id_user_kasir' => $kasir->id_user,
            'tgl_order' => now(), 'total_bayar' => 50000, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_collecting' => 'Belum Diambil',
        ]);
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $idTransaksi, 'id_produk' => null, 'nama_file' => 'banner.pdf', 'p_ukuran' => 1, 'l_ukuran' => 1, 'qty' => 1,
            'status_urgensi' => 'Normal', 'status_finishing' => 'Standar', 'subtotal' => 50000,
            'hpp_snapshot' => 0, 'harga_jual_snapshot' => 50000, 'status_spk' => 'Selesai', 'biaya_cetak' => 50000, 'biaya_desain' => 0,
        ]);

        return DB::table('transaksi')->where('id_transaksi', $idTransaksi)->first();
    }

    public function test_operator_desain_bisa_lihat_riwayat_tapi_tidak_bisa_mencetak_nota(): void
    {
        $t = $this->nota();
        $user = $this->user($this->role('Operator Desain', [41, 61], false));
        $cetak = route('kasir.print-opsi', $t->id_transaksi);

        // Melihat riwayat tetap boleh (menu 61) - ini yang TIDAK boleh ikut hilang.
        $this->actingAs($user)->get('/penjualan/riwayat')->assertOk()->assertDontSee($cetak, false);
        $this->actingAs($user)->get('/penjualan/' . $t->id_transaksi)->assertOk()->assertDontSee($cetak, false);

        // Mencetak nota harus 403 di server, bukan hanya tombolnya disembunyikan.
        $this->actingAs($user)->get($cetak)->assertForbidden();
    }

    public function test_kasir_dan_administrator_masih_bisa_mencetak_nota(): void
    {
        $t = $this->nota();
        $cetak = route('kasir.print-opsi', $t->id_transaksi);

        $kasir = $this->user($this->role('Kasir', [3, 16], true));
        $this->actingAs($kasir)->get('/penjualan/riwayat')->assertOk()->assertSee($cetak, false);
        $this->actingAs($kasir)->get($cetak)->assertOk()->assertSee($t->nomor_nota);

        $admin = $this->user($this->role('Administrator', [], true));
        $this->actingAs($admin)->get('/penjualan/riwayat')->assertOk()->assertSee($cetak, false);
        $this->actingAs($admin)->get($cetak)->assertOk()->assertSee($t->nomor_nota);
    }

    public function test_rule_print_opsi_tidak_memuat_lagi_menu_riwayat(): void
    {
        // Kunci langsung di config supaya penambahan 61 di masa depan langsung ketahuan.
        $this->assertNotContains(61, (array) config('menu_access.rules')['kasir/print-opsi']);
        $this->assertSame([3, 16, 48], (array) config('menu_access.rules')['kasir/print-opsi']);

        // Menu 61 TETAP ada di aturan riwayat - Operator Desain harus tetap bisa melihat.
        $this->assertContains(61, (array) config('menu_access.rules')['penjualan/riwayat']);
    }
}
