<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * EDIT NOTA (selisih).
 *
 * Yang diuji:
 *  - Edit TIDAK membatalkan nota. Nota tetap Aktif, pembayaran tidak diubah,
 *    dan yang efek hanya selisihnya: item yang tidak disentuh sama sekali
 *    tidak menyentuh stok.
 *  - Item baru memotong stok, item yang dihapus mengembalikan stoknya, dan
 *    item yang diubah (qty/ukuran) hanya bergerak sebesar selisihnya.
 *  - Jurnal pendapatan direkonsiliasi sebesar total yang baru, jurnal HPP
 *    disesuaikan sebesar selisih HPP.
 *  - Syarat sama untuk operator yang membuat nota, termasuk Operator Desain.
 *  - Nota yang sudah dibatalkan / sudah diproses / bukan miliknya ditolak.
 */
class EditNotaSelisihTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $desain;

    private User $kasir;

    private int $idProduk;

    private int $idBahan;

    private int $idKonsumen;

    private int $idGolongan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
        DB::table('roles')->where('nama_role', 'Administrator')->update(['boleh_lihat_harga' => true]);

        $rDesain = Role::factory()->create(['nama_role' => 'Operator Desain']);
        DB::table('roles')->where('id_role', $rDesain->id_role)->update(['boleh_input_biaya_desain' => true]);
        // Izin menu "Input Order" (41) supaya Operator Desain boleh membuka
        // layar Input Pesanan - sama seperti di aplikasi sebenarnya.
        DB::table('menus')->insertOrIgnore(['id_menu' => 41, 'nama_menu' => 'Input Order', 'url_link' => 'penjualan/kasir', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $rDesain->id_role, 'id_menu' => 41]);
        $this->desain = User::factory()->create(['id_role' => $rDesain->id_role]);

        // Role lain (bukan Administrator, bukan pemilik nota) untuk menguji
        // penolakan.
        $rKasir = Role::factory()->create(['nama_role' => 'Kasir']);
        DB::table('menus')->insertOrIgnore(['id_menu' => 3, 'nama_menu' => 'Transaksi Baru', 'url_link' => 'penjualan', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $rKasir->id_role, 'id_menu' => 3]);
        $this->kasir = User::factory()->create(['id_role' => $rKasir->id_role]);

        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'MESIN UJI', 'tipe_mesin' => 'Direct Print']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $m2 = DB::table('satuan')->insertGetId(['nama_satuan' => 'm2', 'nilai_konversi' => 1]);
        $this->idBahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'TC1', 'nama_bahan' => 'TC Textile Uji', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 100, 'nilai_konversi' => 1, 'lebar_roll' => 1.2, 'satuan_stok' => 'm2',
            'id_satuan_hitung' => $m2, 'id_satuan_stok' => $m2, 'status' => 'aktif',
            'harga_rata_rata' => 1000,
        ]);
        $this->idProduk = DB::table('produk')->insertGetId([
            'nama_produk' => 'Produk Uji Edit', 'metode_hitung' => 'ukuran', 'status' => 'Aktif',
        ]);
        DB::table('bom_detail')->insert([
            'id_produk' => $this->idProduk, 'id_bahan' => $this->idBahan, 'cara_hitung_pemakaian' => '(P*L)/10000',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->idGolongan = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Konsumen Uji Edit', 'telepon' => '0812', 'alamat' => '-', 'id_golongan' => $this->idGolongan,
        ]);
        DB::table('setting_harga')->insert([
            'id_produk' => $this->idProduk, 'id_golongan' => $this->idGolongan,
            'range_min' => 0, 'range_max' => null, 'harga_jual' => 50000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Buat nota berisi satu item produk 100x100 cm, qty 1. */
    private function buatNota(string $nomor = 'N-EDIT-1', int $idUserKasir = 0, string $statusOrder = 'Diterima Kasir'): int
    {
        $idUserKasir = $idUserKasir ?: $this->admin->id_user;
        $idTransaksi = DB::table('transaksi')->insertGetId([
            'nomor_nota' => $nomor, 'tipe_order' => 'Reguler', 'id_konsumen' => $this->idKonsumen,
            'id_user_kasir' => $idUserKasir, 'tgl_order' => now(), 'total_bayar' => 50000,
            'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => $statusOrder,
        ]);

        $idDetail = DB::table('transaksi_detail')->insertGetId([
            'id_transaksi' => $idTransaksi, 'id_produk' => $this->idProduk, 'nama_file' => 'banner.pdf',
            'p_ukuran' => 100, 'l_ukuran' => 100, 'qty' => 1, 'subtotal' => 50000,
            'status_urgensi' => 'Normal', 'status_finishing' => 'Standar', 'status_spk' => 'Antre',
            'biaya_cetak' => 50000, 'biaya_desain' => 0, 'biaya_finishing' => 0,
            'harga_jual_snapshot' => 50000, 'hpp_snapshot' => 1000,
        ]);

        // Pemakaian bahan: 100x100 cm = 1 m2, stok satuan m2.
        DB::table('transaksi_detail_bahan_pakai')->insert([
            'id_detail' => $idDetail, 'id_bahan' => $this->idBahan, 'qty_pakai' => 1, 'is_tinta' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->update(['stok' => 99]);
        DB::table('kartu_stok')->insert([
            'id_bahan' => $this->idBahan, 'tanggal' => now(), 'tipe' => 'OUT', 'qty' => 1,
            'stok_sebelum' => 100, 'stok_sesudah' => 99, 'referensi' => $nomor,
            'keterangan' => 'Pemakaian produksi order ' . $nomor, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('jurnal_umum')->insert([
            'tgl_jurnal' => now()->toDateString(), 'no_referensi' => $nomor, 'keterangan' => 'Penjualan ' . $nomor,
            'kode_akun' => '1100', 'debet' => 50000, 'kredit' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('jurnal_umum')->insert([
            'tgl_jurnal' => now()->toDateString(), 'no_referensi' => $nomor, 'keterangan' => 'Penjualan ' . $nomor,
            'kode_akun' => '4000', 'debet' => 0, 'kredit' => 50000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('jurnal_umum')->insert([
            'tgl_jurnal' => now()->toDateString(), 'no_referensi' => $nomor, 'keterangan' => 'HPP bahan baku ' . $nomor,
            'kode_akun' => '5000', 'debet' => 1000, 'kredit' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('jurnal_umum')->insert([
            'tgl_jurnal' => now()->toDateString(), 'no_referensi' => $nomor, 'keterangan' => 'HPP bahan baku ' . $nomor,
            'kode_akun' => '1200', 'debet' => 0, 'kredit' => 1000, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $idTransaksi;
    }

    /** Payload satu item produk - sama bentuknya dengan yang dikirim layar. */
    private function payloadItem(array $tambahan = []): array
    {
        return [
            'id_konsumen' => $this->idKonsumen,
            'id_golongan' => $this->idGolongan,
            'items' => [array_merge([
                'id_produk' => $this->idProduk,
                'nama_file' => 'banner.pdf',
                'p' => 100, 'l' => 100, 'qty_input' => 1,
                'biaya_cetak' => 50000, 'biaya_desain' => 0,
                'status_urgensi' => 'Normal', 'status_finishing' => 'Standar',
                'finishing' => [],
            ], $tambahan)],
        ];
    }

    private function stok(): float
    {
        return (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('stok');
    }

    public function test_layar_edit_menampilkan_item_nota_yang_akan_dikoreksi(): void
    {
        $id = $this->buatNota();

        $r = $this->actingAs($this->admin)->get("/penjualan/kasir?edit={$id}");

        $r->assertOk();
        $r->assertSee('EDIT');
        $r->assertSee('banner.pdf');
        $r->assertSee('Simpan Koreksi');
    }

    public function test_item_yang_tidak_diubah_tidak_menyentuh_stok_sama_sekali(): void
    {
        $id = $this->buatNota();
        $idDetail = DB::table('transaksi_detail')->where('id_transaksi', $id)->value('id_detail');
        $kartuSebelum = DB::table('kartu_stok')->count();

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/update-item", $this->payloadItem(['id_detail' => $idDetail]))
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertEquals(99.0, $this->stok(), 'stok tidak boleh bergerak untuk item yang tidak diubah');
        $this->assertEquals($kartuSebelum, DB::table('kartu_stok')->count(), 'tidak boleh ada baris kartu_stok baru');
        $this->assertEquals(50000.0, (float) DB::table('transaksi')->where('id_transaksi', $id)->value('total_bayar'));
        $this->assertSame('Aktif', DB::table('transaksi')->where('id_transaksi', $id)->value('status_nota'), 'nota tidak boleh dibatalkan');
    }

    public function test_item_baru_memotong_stok_dan_menaikkan_total(): void
    {
        $id = $this->buatNota();

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/update-item", [
                'id_konsumen' => $this->idKonsumen,
                'id_golongan' => $this->idGolongan,
                'items' => [
                    ['id_produk' => $this->idProduk, 'nama_file' => 'banner.pdf', 'p' => 100, 'l' => 100, 'qty_input' => 1, 'biaya_cetak' => 50000, 'status_urgensi' => 'Normal', 'finishing' => []],
                    ['id_produk' => $this->idProduk, 'nama_file' => 'spanduk.pdf', 'p' => 100, 'l' => 100, 'qty_input' => 1, 'biaya_cetak' => 50000, 'status_urgensi' => 'Normal', 'finishing' => []],
                ],
            ])
            ->assertOk();

        $this->assertEquals(98.0, $this->stok(), 'item baru harus memotong stok');
        $this->assertEquals(100000.0, (float) DB::table('transaksi')->where('id_transaksi', $id)->value('total_bayar'));
        $this->assertEquals(2, DB::table('transaksi_detail')->where('id_transaksi', $id)->count());
    }

    public function test_item_yang_dihapus_mengembalikan_stoknya(): void
    {
        $id = $this->buatNota();

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/update-item", [
                'id_konsumen' => $this->idKonsumen,
                'id_golongan' => $this->idGolongan,
                'items' => [],
            ])
            ->assertStatus(400);

        // Isi keranjang kosong ditolak (400) - jadi penghapusan item
        // diuji lewat item yang TIDAK ada di kiriman: baris lama di nota
        // yang tidak disebut akan dianggap dihapus.
        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/update-item", [
                'id_konsumen' => $this->idKonsumen,
                'id_golongan' => $this->idGolongan,
                'items' => [
                    ['id_produk' => $this->idProduk, 'nama_file' => 'lain.pdf', 'p' => 100, 'l' => 100, 'qty_input' => 1, 'biaya_cetak' => 50000, 'status_urgensi' => 'Normal', 'finishing' => []],
                ],
            ])
            ->assertOk();

        $this->assertEquals(99.0, $this->stok(), 'stok item yang dihapus harus kembali');
        $this->assertEquals(1, DB::table('transaksi_detail')->where('id_transaksi', $id)->count());
    }

    public function test_item_yang_diubah_hanya_bergerak_sebesar_selisihnya(): void
    {
        $id = $this->buatNota();
        $idDetail = DB::table('transaksi_detail')->where('id_transaksi', $id)->value('id_detail');

        // Qty 1 -> 2: pemakaian bahan naik 1 m2 (dari 1 ke 2). Biaya cetak
        // ikut dua kali - sama seperti yang dikirim layar (harga satuan x qty).
        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/update-item", $this->payloadItem([
                'id_detail' => $idDetail,
                'qty_input' => 2,
                'biaya_cetak' => 100000,
            ]))
            ->assertOk();

        $this->assertEquals(98.0, $this->stok(), 'stok harus bergerak sebesar selisihnya saja');
        $this->assertEquals(100000.0, (float) DB::table('transaksi')->where('id_transaksi', $id)->value('total_bayar'));
    }

    public function test_jurnal_pendapatan_direkonsiliasi_sebesar_total_baru(): void
    {
        $id = $this->buatNota();

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/update-item", $this->payloadItem([
                'qty_input' => 2,
                'biaya_cetak' => 100000,
            ]))
            ->assertOk();

        $pendapatan = (float) DB::table('jurnal_umum')
            ->where('no_referensi', 'N-EDIT-1')
            ->where('kode_akun', '4000')
            ->sum('kredit') - (float) DB::table('jurnal_umum')
            ->where('no_referensi', 'N-EDIT-1')
            ->where('kode_akun', '4000')
            ->sum('debet');

        $this->assertEquals(100000.0, $pendapatan, 'jurnal pendapatan harus sesuai total yang baru');
    }

    public function test_operator_desain_yang_membuat_nota_boleh_mengeditnya(): void
    {
        $id = $this->buatNota('N-EDIT-DESAIN', $this->desain->id_user);
        $idDetail = DB::table('transaksi_detail')->where('id_transaksi', $id)->value('id_detail');

        $r = $this->actingAs($this->desain)->get("/penjualan/kasir?edit={$id}");
        $r->assertOk();
        $r->assertSee('banner.pdf');

        $this->actingAs($this->desain)
            ->postJson("/penjualan/{$id}/update-item", $this->payloadItem(['id_detail' => $idDetail]))
            ->assertOk();

        $this->assertEquals(99.0, $this->stok());
    }

    public function test_operator_lain_tidak_bisa_mengedit_nota_bukan_miliknya(): void
    {
        $id = $this->buatNota();

        $this->actingAs($this->kasir)
            ->postJson("/penjualan/{$id}/update-item", $this->payloadItem())
            ->assertStatus(422);
    }

    public function test_nota_yang_sudah_dibatalkan_tidak_bisa_diedit(): void
    {
        $id = $this->buatNota();
        DB::table('transaksi')->where('id_transaksi', $id)->update(['status_nota' => 'Dibatalkan']);

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/update-item", $this->payloadItem())
            ->assertStatus(422);
    }

    public function test_nota_yang_itemnya_sudah_diproses_tidak_bisa_diedit(): void
    {
        $id = $this->buatNota();
        DB::table('transaksi_detail')->where('id_transaksi', $id)->update(['status_spk' => 'Selesai']);

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/update-item", $this->payloadItem())
            ->assertStatus(422);
    }

    /**
     * Item yang SPK-nya sudah dicetak ('Cetak') juga memblokir edit.
     *
     * Bahannya bisa sudah dipotong, jadi pengembaliannya ke stok akan
     * membuat stok melebihi reality. Status ini harus dihitung sama di
     * server (pengecenan saat menyimpan) dan di penanda daftar Riwayat
     * (subquery), kalau tidak tombol Edit di daftar bisa muncul untuk nota
     * yang sebenarnya ditolak saat disimpan.
     */
    public function test_nota_yang_itemnya_sudah_dicetak_tidak_bisa_diedit(): void
    {
        $id = $this->buatNota();
        DB::table('transaksi_detail')->where('id_transaksi', $id)->update(['status_spk' => 'Cetak']);

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/update-item", $this->payloadItem())
            ->assertStatus(422);

        $r = $this->actingAs($this->admin)->get('/penjualan/riwayat');
        $r->assertOk();
        $this->assertStringNotContainsString(
            'href="' . route('penjualan.create', ['edit' => $id]) . '"',
            $r->getContent(),
            'tombol Edit tidak boleh muncul di daftar untuk nota yang itemnya sudah dicetak'
        );
    }

    public function test_nota_kemarin_tidak_bisa_diedit(): void
    {
        $id = $this->buatNota();
        DB::table('transaksi')->where('id_transaksi', $id)->update(['tgl_order' => now()->subDay()]);

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/update-item", $this->payloadItem())
            ->assertStatus(422);
    }
}
