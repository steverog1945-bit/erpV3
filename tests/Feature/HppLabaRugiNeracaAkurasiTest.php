<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Uji akurasi HPP, Laba Rugi, dan Neraca dengan angka yang sepenuhnya
 * dikendalikan.
 *
 * Yang sudah terbukti sebelumnya, dan tidak diulang di sini: jurnal itu
 * imbang (D = K). Tapi jurnal yang imbang belum tentu benar - satu order
 * Rp 50.000 dengan HPP Rp 20.000 bisa saja dicatat D=K dan tetap melenceng
 * di laporan.
 *
 * Test ini menutup celah itu dengan cara sebaliknya: semua angka dipilih
 * supaya hasilnya dapat dihitung tangan, lalu setiap angka yang muncul di
 * laporan dibandingkan dengan hasil hitungan tangan itu.
 *
 * Angka dasar yang dipakai:
 *
 *   bahan  : harga_rata_rata Rp 10.000 / satuan stok, nilai_konversi 4,
 *            stok awal 100. Satu satuan stok = 4 satuan hitung.
 *   produk : metode_hitung 'quantity', BOM satu bahan, rumus 'Q'.
 *   order  : 8 lembar.
 *
 *   qty_pakai (satuan hitung) = 8             (rumus 'Q')
 *   qty_pakai (satuan stok)   = 8 / 4    = 2
 *   HPP                       = 2 x 10.000  = Rp  20.000
 *   stok bahan                 = 100 - 2     = 98
 *   harga jual                = Rp 50.000 / lembar
 *   omzet                      = 8 x 50.000  = Rp 400.000
 *   laba kotor                 = 400.000 - 20.000 = Rp 380.000
 *
 * Role yang dipakai adalah role TANPA hak lihat harga (boleh_lihat_harga
 * = 0), supaya biaya cetak dihitung server dari master dan bukan dikirim
 * klien. Kalau role ini masih bisa-Andalkan angka kiriman, itu bug-nya
 * sendiri.
 */
class HppLabaRugiNeracaAkurasiTest extends TestCase
{
    use RefreshDatabase;

    private const HARGA_BAHAN = 10000.0;

    private const KONVERSI = 4.0;

    private const STOK_AWAL = 100.0;

    private const QTY_ORDER = 8.0;

    private const HARGA_JUAL = 50000.0;

    private int $idBahan;

    private int $idProduk;

    private int $idKonsumen;

    private int $idGol;

    private function siapkan(): User
    {
        $role = Role::factory()->create(['nama_role' => 'Kasir Uji E2E']);
        DB::table('roles')->where('id_role', $role->id_role)->update(['boleh_lihat_harga' => 0]);

        // 3 dan 41 = menu Input Order (PenjualanController).
        // 36 = menu Akuntansi, satu-satunya izin yang mengunci /akuntansi/*
        // (config/menu_access.php baris 150: 'akuntansi' => [36]). Tanpa ini
        // halaman laba-rugi dan neraca balas 403 dan tidak ada yang bisa
        // diverifikasi.
        foreach ([3, 41, 36] as $id) {
            DB::table('menus')->insertOrIgnore([
                'id_menu' => $id, 'nama_menu' => "M{$id}", 'url_link' => "m{$id}", 'parent_id' => 0,
            ]);
            DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => $id]);
        }

        $user = User::factory()->create(['id_role' => $role->id_role]);

        $this->idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail Uji E2E', 'keterangan' => '-']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Konsumen Uji E2E', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $this->idGol,
        ]);

        // id_mesin pada bahan WAJIB ada (foreign key ke tabel mesin), tapi
        // produk uji TIDAK diberi id_mesin. Komponen tinta dicari lewat
        // produk.id_mesin (BomCalculationService::komponen baris 55-69),
        // jadi produk tanpa mesin tidak menarik tinta ke HPP dan hitungan
        // tangannya tetap satu komponen.
        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin Uji E2E', 'tipe_mesin' => 'Uji']);
        $idKategori = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kategori Uji E2E']);

        $this->idBahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'E2E-01',
            'nama_bahan' => 'Bahan Uji E2E',
            'id_mesin' => $idMesin,
            'id_kategori' => $idKategori,
            'stok' => self::STOK_AWAL,
            'stok_minimal' => 0,
            'nilai_konversi' => self::KONVERSI,
            'harga_rata_rata' => self::HARGA_BAHAN,
            'satuan_stok' => 'rim',
            'created_at' => now(),
        ]);

        $this->idProduk = DB::table('produk')->insertGetId([
            'nama_produk' => 'Produk Uji E2E',
            'metode_hitung' => 'quantity',
            'status' => 'Aktif',
            'min_order' => 1, 'min_panjang' => 1, 'min_lebar' => 1,
            'spk_lengkap' => 0,
            'created_at' => now(),
        ]);

        DB::table('bom_detail')->insert([
            'id_produk' => $this->idProduk,
            'id_bahan' => $this->idBahan,
            'cara_hitung_pemakaian' => 'Q',
        ]);

        DB::table('setting_harga')->insert([
            'id_produk' => $this->idProduk,
            'id_golongan' => $this->idGol,
            'range_min' => 0.01,
            'harga_jual' => self::HARGA_JUAL,
        ]);

        DB::table('kas_harian')->insert([
            'tgl_kas' => now()->toDateString(),
            'id_user' => $user->id_user,
            'saldo_awal' => 0,
            'status_lock' => 'open',
        ]);

        return $user;
    }

    private function orderkan(User $user): void
    {
        $this->actingAs($user)->postJson('/penjualan/store', [
            'id_konsumen' => $this->idKonsumen,
            'id_golongan' => $this->idGol,
            'items' => [[
                'id_produk' => $this->idProduk,
                'nama_file' => 'e2e.jpg',
                'p' => 10,
                'l' => 10,
                'qty_input' => (int) self::QTY_ORDER,
                // Dikirim sengaja angka ngawur. Role ini tidak punya hak
                // lihat harga, jadi server HARUS mengabaikannya dan
                // menghitung dari master.
                'biaya_cetak' => 1,
                'finishing' => [],
            ]],
        ])->assertOk();
    }

    private function labaRugi(User $user)
    {
        $tgl = now()->toDateString();

        return $this->actingAs($user)->get('/akuntansi/laba-rugi?awal=' . $tgl . '&akhir=' . $tgl)->assertOk();
    }

    private function hppHarapan(): float
    {
        return (self::QTY_ORDER / self::KONVERSI) * self::HARGA_BAHAN;
    }

    private function omzetHarapan(): float
    {
        return self::HARGA_JUAL * self::QTY_ORDER;
    }

    public function test_hpp_snapshot_sama_dengan_hitungan_tangan(): void
    {
        $user = $this->siapkan();
        $this->orderkan($user);

        $d = DB::table('transaksi_detail')->first();

        // 8 lembar / konversi 4 = 2 satuan stok; 2 x 10.000
        $this->assertEqualsWithDelta(
            $this->hppHarapan(),
            (float) $d->hpp_snapshot,
            0.01,
            'HPP harus Rp 20.000 dari (8 / 4) x 10.000'
        );
    }

    public function test_stok_dan_kartu_stok_konsisten_dengan_hpp(): void
    {
        $user = $this->siapkan();
        $this->orderkan($user);

        $bahan = DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->first();

        $this->assertEqualsWithDelta(
            self::STOK_AWAL - (self::QTY_ORDER / self::KONVERSI),
            (float) $bahan->stok,
            0.0001,
            'stok bahan harus berkurang tepat 2 rim (8 lembar / konversi 4)'
        );

        $pakai = DB::table('transaksi_detail_bahan_pakai')->first();
        $this->assertNotNull($pakai, 'snapshot pemakaian bahan harus ada untuk reversal');
        $this->assertEqualsWithDelta(2.0, (float) $pakai->qty_pakai, 0.0001);

        $kartu = DB::table('kartu_stok')->where('tipe', 'OUT')->first();
        $this->assertNotNull($kartu, 'pemakaian bahan harus tercatat di kartu stok sebagai OUT');
        $this->assertEqualsWithDelta(2.0, (float) $kartu->qty, 0.0001);
        $this->assertEqualsWithDelta(self::STOK_AWAL, (float) $kartu->stok_sebelum, 0.0001);
        $this->assertEqualsWithDelta(98.0, (float) $kartu->stok_sesudah, 0.0001);
    }

    public function test_biaya_cetak_dihitung_server_bukan_dari_angka_kiriman(): void
    {
        $user = $this->siapkan();
        $this->orderkan($user);

        $d = DB::table('transaksi_detail')->first();

        // Role tanpa hak harga mengirim biaya_cetak = 1. Master bilang
        // 50.000 x 8 lembar.
        $this->assertEqualsWithDelta(
            $this->omzetHarapan(),
            (float) $d->biaya_cetak,
            0.01,
            'biaya cetak harus dihitung server dari master harga, bukan kiriman klien'
        );
    }

    public function test_laba_rugi_setiap_barisnya_sama_dengan_hitungan_tangan(): void
    {
        $user = $this->siapkan();
        $this->orderkan($user);

        $view = $this->labaRugi($user);
        $hpp = $this->hppHarapan();
        $omzet = $this->omzetHarapan();

        $this->assertEqualsWithDelta($omzet, (float) $view->viewData('totalPendapatan'), 0.01, 'pendapatan harus sama dengan omzet');
        $this->assertEqualsWithDelta($hpp, (float) $view->viewData('totalHpp'), 0.01, 'HPP di laba rugi harus sama dengan hpp_snapshot');
        $this->assertEqualsWithDelta($omzet - $hpp, (float) $view->viewData('labaKotor'), 0.01, 'laba kotor harus omzet dikurangi HPP');

        // Skenario ini tidak punya beban usaha sama sekali - akun Biaya
        // Lain-lain (5100) pun tidak disentuh, jadi 0.
        $this->assertEqualsWithDelta(0.0, (float) $view->viewData('totalBebanUsaha'), 0.01, 'skenario ini tidak punya beban usaha');
        $this->assertEqualsWithDelta(
            $omzet - $hpp,
            (float) $view->viewData('labaBersih'),
            0.01,
            'tidak ada beban usaha, jadi laba bersih sama dengan laba kotor'
        );

        // Cross-check ke jurnal, bukan hanya ke view.
        $jurnalHpp = (float) DB::table('jurnal_umum')->where('kode_akun', '5000')->sum(DB::raw('debet - kredit'));
        $this->assertEqualsWithDelta($hpp, $jurnalHpp, 0.01, 'jurnal akun 5000 harus sama dengan HPP');

        $jurnalPendapatan = (float) DB::table('jurnal_umum')->where('kode_akun', '4000')->sum(DB::raw('kredit - debet'));
        $this->assertEqualsWithDelta($omzet, $jurnalPendapatan, 0.01, 'jurnal akun 4000 harus sama dengan omzet');
    }

    public function test_neraca_balance_dan_laba_berjalan_sama_dengan_laba_rugi(): void
    {
        $user = $this->siapkan();
        $this->orderkan($user);

        $tanggal = now()->toDateString();
        $view = $this->actingAs($user)->get('/akuntansi/neraca?tanggal=' . $tanggal)->assertOk();

        $totalAset = (float) $view->viewData('totalAset');
        $totalPasiva = (float) $view->viewData('totalPasiva');

        $this->assertTrue(
            $view->viewData('balance'),
            "neraca tidak balance: aset {$totalAset} vs pasiva {$totalPasiva}"
        );
        $this->assertEqualsWithDelta($totalAset, $totalPasiva, 0.01);

        // Laba berjalan di neraca (kumulatif sejak awal) harus sama dengan
        // laba rugi periode berjalan, karena tidak ada jurnal sebelum
        // order uji.
        $this->assertEqualsWithDelta(
            (float) $this->labaRugi($user)->viewData('labaBersih'),
            (float) $view->viewData('labaBerjalan'),
            0.01,
            'laba berjalan di neraca harus sama dengan laba bersih di laba rugi'
        );
    }

    public function test_pendapatan_tidak_bocor_ke_periode_lain(): void
    {
        $user = $this->siapkan();
        $this->orderkan($user);

        // Jurnal order ini bertanggal hari ini. Laba rugi periode lain
        // harus nol - kalau tidak, filter tanggalnya tidak bekerja.
        $kemarin = now()->subDay()->toDateString();
        $view = $this->actingAs($user)->get('/akuntansi/laba-rugi?awal=' . $kemarin . '&akhir=' . $kemarin)->assertOk();

        $this->assertEqualsWithDelta(0.0, (float) $view->viewData('totalPendapatan'), 0.01, 'periode lain harus nol');
        $this->assertEqualsWithDelta(0.0, (float) $view->viewData('totalHpp'), 0.01);
    }

    public function test_pembatalan_nota_menembalikan_hpp_dan_stok_setelah_disetujui(): void
    {
        $user = $this->siapkan();
        $this->orderkan($user);

        $idTransaksi = (int) DB::table('transaksi')->value('id_transaksi');
        $this->assertNotSame(0, $idTransaksi, 'nota harus tersimpan');
        $this->assertEqualsWithDelta(
            98.0,
            (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('stok'),
            0.0001
        );

        // PENTING: PATCH /penjualan/{id}/batalkan-nota TIDAK membatalkan
        // nota. Ia hanya mengajukan lewat ApprovalService (lihat
        // PenjualanController::batalkanNota - isinya habis di
        // ApprovalService::ajukan lalu redirect dengan flash
        // "menunggu persetujuan Administrator"). Stok dan jurnal baru
        // berubah setelah ApprovalController memanggil
        // eksekusiPembatalanNota().
        //
        // 'kembalikan_stok' wajib true, kalau tidak bahan tetap terpotong
        // meski nota sudah dibatalkan.
        $respons = $this->actingAs($user)->patchJson('/penjualan/' . $idTransaksi . '/batalkan-nota', [
            'alasan' => 'Uji E2E',
            'kembalikan_stok' => true,
        ]);

        $respons->assertRedirect();
        $this->assertNull($respons->baseResponse->getSession()->get('error'), 'flash error harus null');

        $req = \App\Models\ApprovalRequest::where('tipe_aksi', 'batalkan_nota')
            ->where('referensi_id', $idTransaksi)
            ->first();
        $this->assertNotNull($req, 'pengajuan pembatalan harus tercatat');
        $this->assertSame('Menunggu', $req->status);

        // Sebelum disetujui: nota masih hidup dan stok masih terpotong.
        $this->assertNotSame('Dibatalkan', DB::table('transaksi')->where('id_transaksi', $idTransaksi)->value('status_nota'));
        $this->assertEqualsWithDelta(
            98.0,
            (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('stok'),
            0.0001,
            'stok belum boleh berubah sebelum pengajuan disetujui'
        );

        // Sekarang Administrator menyetujui.
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($admin)->patch('/approval/' . $req->id . '/approve', ['catatan' => 'Disetujui'])->assertRedirect();

        $this->assertSame(
            'Dibatalkan',
            DB::table('transaksi')->where('id_transaksi', $idTransaksi)->value('status_nota'),
            'nota harus berstatus Dibatalkan setelah disetujui'
        );

        $this->assertEqualsWithDelta(
            self::STOK_AWAL,
            (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('stok'),
            0.0001,
            'stok bahan harus kembali ke 100 setelah pembatalan disetujui'
        );

        $jurnalHpp = (float) DB::table('jurnal_umum')->where('kode_akun', '5000')->sum(DB::raw('debet - kredit'));
        $this->assertEqualsWithDelta(0.0, $jurnalHpp, 0.01, 'akun 5000 harus nol setelah pembatalan');

        // hpp_snapshot SENGAJA tidak dinolkan. Namanya snapshot: ia catatan
        // historis berapa HPP item ini pada saat order dibuat, dan
        // harga_jual_snapshot tidak dinolkan juga. Yang menentukan apakah
        // nilai itu dihitung adalah pembaca di hilir, bukan nilai kolomnya.
        $this->assertEqualsWithDelta(
            20000.0,
            (float) DB::table('transaksi_detail')->value('hpp_snapshot'),
            0.01,
            'hpp_snapshot adalah catatan historis dan tidak boleh dihapus'
        );

        // Buktinya: Dashboard Eksekutif menyaring nota batal, jadi Rp20.000
        // tadi TIDAK boleh muncul di HPP maupun laba kotor eksekutif.
        $ringkasan = \App\Services\DashboardEksekutifService::ringkasan(
            now()->startOfDay(),
            now()->endOfDay()
        );

        $this->assertEqualsWithDelta(
            0.0,
            (float) $ringkasan['hpp'],
            0.01,
            'dashboard eksekutif harus mengeluarkan nota yang dibatalkan dari HPP'
        );
        $this->assertEqualsWithDelta(
            0.0,
            (float) $ringkasan['omzet'],
            0.01,
            'dashboard eksekutif harus mengeluarkan nota yang dibatalkan dari omzet'
        );
    }

    public function test_pembatalan_tanpa_kembalikan_stok_tidak_menambah_stok(): void
    {
        $user = $this->siapkan();
        $this->orderkan($user);

        $idTransaksi = (int) DB::table('transaksi')->value('id_transaksi');

        // Tanpa flag kembalikan_stok: bahan dianggap sudah terpakai
        // (mis. sudah dibuang ke produksi), jadi stok TIDAK dikembalikan.
        $this->actingAs($user)->patchJson('/penjualan/' . $idTransaksi . '/batalkan-nota', [
            'alasan' => 'Uji E2E tanpa kembalikan stok',
        ])->assertRedirect();

        $req = \App\Models\ApprovalRequest::where('tipe_aksi', 'batalkan_nota')->where('referensi_id', $idTransaksi)->first();
        $this->assertNotNull($req);

        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($admin)->patch('/approval/' . $req->id . '/approve', ['catatan' => 'ok'])->assertRedirect();

        $this->assertSame('Dibatalkan', DB::table('transaksi')->where('id_transaksi', $idTransaksi)->value('status_nota'));
        $this->assertEqualsWithDelta(
            98.0,
            (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('stok'),
            0.0001,
            'tanpa kembalikan_stok, stok bahan harus tetap 98 - bahan dianggap sudah terpakai'
        );
    }
}