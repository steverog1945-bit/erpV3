<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fitur "Tambah Item Susulan" ke nota yang sudah berjalan.
 *
 * Aturan yang dikunci test ini:
 *   - hanya operator yang membuat nota (id_user_kasir)
 *   - hanya hari yang sama (DATE(tgl_order) = hari ini)
 *   - hanya nota Diterima Kasir + Aktif
 *   - item baru masuk SPK sebagai 'Antre'
 *   - total_bayar naik sebesar subtotal item baru
 *   - stok bahan baku terpotong
 *   - jurnal pendapatan/HPP diposting untuk tambahan
 */
class TambahItemSusulanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $idProduk;

    private int $idKonsumen;

    private int $idMesin;
    private int $idGolongan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);

        $this->idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'MESIN UJI', 'tipe_mesin' => 'Direct Print']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $m2 = DB::table('satuan')->insertGetId(['nama_satuan' => 'm2', 'nilai_konversi' => 1]);
        $bahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'TC1', 'nama_bahan' => 'TC Textile Uji', 'id_mesin' => $this->idMesin, 'id_kategori' => $kat,
            'stok' => 10, 'nilai_konversi' => 1, 'lebar_roll' => 1.2, 'satuan_stok' => 'm2',
            'id_satuan_hitung' => $m2, 'id_satuan_stok' => $m2, 'status' => 'aktif',
        ]);
        $this->idProduk = DB::table('produk')->insertGetId([
            'nama_produk' => 'Produk Uji Susulan', 'metode_hitung' => 'ukuran', 'status' => 'Aktif',
            'id_mesin' => $this->idMesin, 'spk_lengkap' => 1,
        ]);
        DB::table('bom_detail')->insert([
            'id_produk' => $this->idProduk, 'id_bahan' => $bahan, 'cara_hitung_pemakaian' => '(P*L)/10000',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // golongan dulu, baru konsumen, baru setting_harga (urutan FK)
        $g = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->idGolongan = $g;
        $this->idKonsumen = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Konsumen Uji Susulan', 'telepon' => '0812', 'alamat' => '-', 'id_golongan' => $g,
        ]);

        DB::table('setting_harga')->insert([
            'id_produk' => $this->idProduk, 'id_golongan' => $g,
            'range_min' => 0, 'range_max' => null, 'harga_jual' => 50000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }


    private function buatNota(string $nomor, string $statusOrder = 'Diterima Kasir', string $tglOrder = 'now', float $total = 100000): int
    {
        $tgl = $tglOrder === 'now' ? now() : \Carbon\Carbon::parse($tglOrder);

        return DB::table('transaksi')->insertGetId([
            'nomor_nota' => $nomor, 'tipe_order' => 'Reguler', 'id_konsumen' => $this->idKonsumen,
            'id_user_kasir' => $this->admin->id_user, 'tgl_order' => $tgl, 'total_bayar' => $total,
            'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => $statusOrder,
        ]);
    }
    private function payloadItem(): array
    {
        return [
            'items' => [[
                'id_produk' => $this->idProduk,
                'nama_file' => 'susulan.jpg',
                'p' => 100, 'l' => 100, 'qty_input' => 1,
                'biaya_cetak' => 50000,
                'biaya_desain' => 0,
                'file_siap_cetak' => 0,
            ]],
        ];
    }

    public function test_operator_pembuat_nota_bisa_menambah_item_susulan_di_hari_yang_sama(): void
    {
        $id = $this->buatNota('N-SUS-1');

        $res = $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertSame(1, DB::table('transaksi_detail')->where('id_transaksi', $id)->count());
        $detail = DB::table('transaksi_detail')->where('id_transaksi', $id)->first();
        $this->assertSame('Antre', $detail->status_spk);

        $totalBaru = (float) DB::table('transaksi')->where('id_transaksi', $id)->value('total_bayar');
        $this->assertEquals(150000.0, $totalBaru, 'total_bayar harus naik sebesar subtotal item baru');
    }

    public function test_operator_lain_tidak_bisa_menambah_item(): void
    {
        $id = $this->buatNota('N-SUS-2');

        $lain = User::factory()->create(['id_role' => $this->admin->id_role]);

        $this->actingAs($lain)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
            ->assertStatus(403);
    }

    public function test_tidak_bisa_menambah_item_di_hari_kemarin(): void
    {
        $id = $this->buatNota('N-SUS-3', 'Diterima Kasir', now()->subDay()->toDateString());

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
            ->assertStatus(422);
    }

    /**
     * Nota 'Menunggu Kasir' BOLEH ditambah item susulan.
     *
     * Dulu test ini menuntut 422, dan itu benar waktu itu: layar Input
     * Pesanan mode susulan juga menolak nota 'Menunggu Kasir'. Sekarang
     * keduanya mengizinkan - operator baru saja membuat nota dan belum
     * sempat membuka kasir, jadi boleh menambah item.
     *
     * Syaratnya harus PERSIS sama dengan yang dicegat create() (layar),
     * supaya tidak ada keadaan di mana operator boleh mengisi form tapi
     * ditolak saat menekan "Tambahkan ke Nota".
     */
    public function test_bisa_menambah_item_ke_nota_yang_masih_menunggu_kasir(): void
    {
        $id = $this->buatNota('N-SUS-4', 'Menunggu Kasir');

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertSame(1, (int) DB::table('transaksi_detail')->where('id_transaksi', $id)->value('is_susulan'));
    }

    public function test_tidak_bisa_menambah_item_ke_nota_yang_dibatalkan(): void
    {
        $id = $this->buatNota('N-SUS-5');
        DB::table('transaksi')->where('id_transaksi', $id)->update(['status_nota' => 'Dibatalkan']);

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
            ->assertStatus(422);
    }

    public function test_stok_bahan_baku_terpotong_saat_tambah_item(): void
    {
        $stokSebelum = (float) DB::table('bahan_baku')->value('stok');

        $id = $this->buatNota('N-SUS-6');

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
            ->assertOk();

        $stokSesudah = (float) DB::table('bahan_baku')->value('stok');
        $this->assertLessThan($stokSebelum, $stokSesudah, 'stok bahan baku harus berkurang');
    }

    public function test_kartu_stok_tercatat_saat_tambah_item(): void
    {
        $id = $this->buatNota('N-SUS-7');

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
            ->assertOk();

        $this->assertGreaterThan(0, DB::table('kartu_stok')->where('referensi', 'like', '%susulan%')->count());
    }

    public function test_jurnal_pendapatan_diposting_saat_tambah_item(): void
    {
        $jurnalSebelum = DB::table('jurnal_umum')->count();

        $id = $this->buatNota('N-SUS-8');

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
            ->assertOk();

        $this->assertGreaterThan($jurnalSebelum, DB::table('jurnal_umum')->count(), 'jurnal pendapatan harus diposting untuk tambahan');
    }

    public function test_item_susulan_masuk_ke_spk_sebagai_antre(): void
    {
        $id = $this->buatNota('N-SUS-9');

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
            ->assertOk();

        $detail = DB::table('transaksi_detail')->where('id_transaksi', $id)->first();
        $this->assertSame('Antre', $detail->status_spk);
    }

    public function test_total_nota_lama_lunas_tetap_hanya_tambahan_yang_terutang(): void
    {
        // Nota lama sudah Lunas (total 100000, pembayaran 100000). Setelah
        // tambah item 50000, total jadi 150000, terbayar tetap 100000.
        // Status pembayaran turun jadi DP, sisa = 50000 (hanya tambahan).
        $id = $this->buatNota('N-SUS-10', 'Diterima Kasir', 'now', 100000);
        DB::table('transaksi')->where('id_transaksi', $id)->update(['status_pembayaran' => 'Lunas']);
        DB::table('pembayaran_riwayat')->insert([
            'id_transaksi' => $id, 'nominal' => 100000, 'metode' => 'Tunai',
            'tgl_bayar' => now(), 'keterangan' => 'pelunasan',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
            ->assertStatus(422)
            ->assertJsonFragment(['msg' => 'Nota sudah Lunas — tidak bisa ditambah item susulan. Buat nota baru untuk tambahan.']);

        $t = DB::table('transaksi')->where('id_transaksi', $id)->first();
        $this->assertEquals(100000.0, (float) $t->total_bayar);
        $this->assertSame('Lunas', $t->status_pembayaran, 'nota Lunas tetap Lunas karena tambahan ditolak');
    }

    public function test_detail_nota_lama_tidak_berubah_saat_tambah_item(): void
    {
        $id = $this->buatNota('N-SUS-11');
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $id, 'id_produk' => $this->idProduk, 'nama_file' => 'lama.jpg',
            'p_ukuran' => 100, 'l_ukuran' => 100, 'qty' => 1, 'subtotal' => 100000,
            'status_spk' => 'Antre', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $jumlahSebelum = DB::table('transaksi_detail')->where('id_transaksi', $id)->count();

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
            ->assertOk();

        $jumlahSesudah = DB::table('transaksi_detail')->where('id_transaksi', $id)->count();
        $this->assertSame($jumlahSebelum + 1, $jumlahSesudah, 'item lama tidak boleh dihapus, hanya ditambah');
    }
}