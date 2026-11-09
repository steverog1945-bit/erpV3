<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Alur "Tambah Order Susulan" versi baru.
 *
 * Yang diuji di sini:
 *  - LAYARNYA sama dengan Input Pesanan: /penjualan/kasir?susulan={id}
 *    merender form Input Pesanan (produk, ukuran, thumbnail, urgency,
 *    finishing, jasa, keranjang) - bukan form mini terpisah.
 *  - Data nota asal terkunci di layar: pelanggan, golongan harga, tipe
 *    order, tanggal, dan nomor nota.
 *  - Item yang dikirim dari layar itu memakai ATURAN YANG SAMA dengan nota
 *    baru (harga dihitung ulang dari master, finishing dari master, dll),
 *    dan menempel ke nota asal - bukan membuat nota baru.
 *  - Baris item susulan ditandai is_susulan=1 supaya bisa dibedakan dari
 *    item asli di detail nota & SPK.
 *  - Jurnal: produk ke akun Pendapatan Penjualan (4000), jasa ke akun
 *    Pendapatan Jasa (4100). Sebelumnya jasa susulan ikut masuk 4000.
 */
class PenjualanSusulanModeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $idProduk;

    private int $idKonsumen;

    private int $idGolongan;

    private int $idMesin;

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
            'stok' => 100, 'nilai_konversi' => 1, 'lebar_roll' => 1.2, 'satuan_stok' => 'm2',
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

        $this->idGolongan = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Konsumen Uji Susulan', 'telepon' => '0812', 'alamat' => '-', 'id_golongan' => $this->idGolongan,
        ]);

        DB::table('setting_harga')->insert([
            'id_produk' => $this->idProduk, 'id_golongan' => $this->idGolongan,
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

    private function payloadItem(array $tambahan = []): array
    {
        return [
            'items' => [array_merge([
                'id_produk' => $this->idProduk,
                'nama_file' => 'susulan.pdf',
                'p' => 100, 'l' => 100, 'qty_input' => 1,
                'biaya_cetak' => 50000,
                'biaya_desain' => 0,
                'status_urgensi' => 'Normal',
                'status_finishing' => 'Standar',
                'catatan_produksi' => null,
                'thumbnail' => null,
                'finishing' => [],
            ], $tambahan)],
        ];
    }

    public function test_layar_susulan_merender_form_input_pesanan_dengan_data_nota_terkunci(): void
    {
        $id = $this->buatNota('N-SUS-LAYAR');

        $r = $this->actingAs($this->admin)->get("/penjualan/kasir?susulan={$id}");

        $r->assertOk();
        $r->assertSee('TAMBAH');
        $r->assertSee('ORDER SUSULAN');
        $r->assertSee('N-SUS-LAYAR');
        // Pelanggan dari nota asal tampil terkunci (bukan dropdown select2).
        $r->assertSee('Konsumen Uji Susulan');
        $r->assertDontSee('id="id_konsumen" name="id_konsumen" class="select2-konsumen"', false);
        // Golongan harga juga terkunci ke nota asal.
        $r->assertSee('value="' . $this->idGolongan . '"', false);
        // Keranjang Input Pesanan tetap ada di mode ini.
        $r->assertSee('cart_body', false);
        $r->assertSee('Keranjang Pesanan');
        // Belum ada item di nota asal -> panel "sudah ada di nota ini" tidak muncul.
        $r->assertDontSee('Sudah ada di nota ini');
        // Tombol simpan menyebut nota tujuan, bukan "Simpan & Cetak Nota".
        $r->assertSee('Tambahkan ke Nota N-SUS-LAYAR');
    }

    /** Panel item lama hanya untuk mode susulan - layar Input Pesanan biasa tidak boleh memunculkannya. */
    public function test_panel_item_lama_tidak_muncul_di_layar_input_pesanan_biasa(): void
    {
        $r = $this->actingAs($this->admin)->get('/penjualan/kasir');

        $r->assertOk();
        $r->assertDontSee('Sudah ada di nota ini');
        $r->assertSee('Keranjang Pesanan');
    }

    public function test_operator_lain_tidak_bisa_membuka_layar_susulan(): void
    {
        $id = $this->buatNota('N-SUS-LAIN');
        $lain = User::factory()->create(['id_role' => $this->admin->id_role]);

        $this->actingAs($lain)->get("/penjualan/kasir?susulan={$id}")->assertForbidden();
    }

    public function test_nota_batal_tidak_bisa_dibuka_untuk_susulan(): void
    {
        $id = $this->buatNota('N-SUS-BATAL');
        DB::table('transaksi')->where('id_transaksi', $id)->update(['status_nota' => 'Dibatalkan']);

        $this->actingAs($this->admin)->get("/penjualan/kasir?susulan={$id}")->assertStatus(422);
    }

    public function test_nota_menunggu_kasir_tetap_bisa_dibuka_untuk_susulan(): void
    {
        // Nota 'Menunggu Kasir' masih boleh ditambah item: operator baru saja
        // membuat nota, belum sempat membuka layar Kasir.
        $id = $this->buatNota('N-SUS-MENUNGGU', 'Menunggu Kasir');

        $this->actingAs($this->admin)->get("/penjualan/kasir?susulan={$id}")->assertOk();
    }

    public function test_nota_yang_diterima_kasir_bisa_dibuka_untuk_susulan(): void
    {
        $id = $this->buatNota('N-SUS-DITERIMA', 'Diterima Kasir');

        $this->actingAs($this->admin)->get("/penjualan/kasir?susulan={$id}")->assertOk();
    }

    public function test_nota_order_dibatalkan_tidak_bisa_dibuka_untuk_susulan(): void
    {
        $id = $this->buatNota('N-SUS-ORDER-BATAL', 'Dibatalkan');

        $this->actingAs($this->admin)->get("/penjualan/kasir?susulan={$id}")->assertStatus(422);
    }

    public function test_nota_kemarin_tidak_bisa_dibuka_untuk_susulan(): void
    {
        $id = $this->buatNota('N-SUS-KEMARIN', 'Diterima Kasir', now()->subDay()->toDateString());

        $this->actingAs($this->admin)->get("/penjualan/kasir?susulan={$id}")->assertStatus(422);
    }

    public function test_item_menempel_ke_nota_asal_dan_total_naik(): void
    {
        $id = $this->buatNota('N-SUS-TEMPEL', 'Diterima Kasir', 'now', 100000);

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $detail = DB::table('transaksi_detail')->where('id_transaksi', $id)->first();
        $this->assertNotNull($detail);
        $this->assertSame('Antre', $detail->status_spk);
        $this->assertSame(1, (int) $detail->is_susulan, 'baris susulan harus ditandai is_susulan');

        $total = (float) DB::table('transaksi')->where('id_transaksi', $id)->value('total_bayar');
        $this->assertEquals(150000.0, $total, 'total nota = total lama + tambahan');
    }

    public function test_urgensi_dan_catatan_produksi_tersimpan_utuh_pada_item_susulan(): void
    {
        $id = $this->buatNota('N-SUS-URGENS');

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem([
                'status_urgensi' => 'Urgent',
                'catatan_produksi' => 'Pakai tinta biru',
            ]))
            ->assertOk();

        $detail = DB::table('transaksi_detail')->where('id_transaksi', $id)->first();
        $this->assertSame('Urgent', $detail->status_urgensi);
        $this->assertSame('Pakai tinta biru', $detail->catatan_produksi);
        $this->assertSame(1, (int) $detail->status_urgent, 'kolom status_urgent lama ikut diisi');
    }

    public function test_finishing_dihitung_dari_master_bukan_dari_nilai_client(): void
    {
        $finId = DB::table('finishing')->insertGetId([
            'nama_finishing' => 'Laminasi Uji', 'harga_default' => 7500, 'metode_hitung' => 'quantity',
        ]);

        $id = $this->buatNota('N-SUS-FINISHING');

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem([
                'finishing' => [[
                    'id_finishing' => $finId,
                    'status_finishing' => 'Standar',
                    'biaya' => 999999999, // client mengarang - server harus pakai master
                    'qty_pakai' => 1,
                ]],
            ]))
            ->assertOk();

        $detail = DB::table('transaksi_detail')->where('id_transaksi', $id)->first();
        $this->assertNotEquals(999999999, (float) $detail->biaya_finishing, 'biaya finishing wajib dihitung server dari master');
        $this->assertEquals(7500.0, (float) $detail->biaya_finishing);

        $barisFin = DB::table('transaksi_detail_finishing')->where('id_detail', $detail->id_detail)->first();
        $this->assertNotNull($barisFin, 'rincian finishing harus ikut tersimpan');
        $this->assertSame('Standar', $barisFin->status_finishing);
    }

    public function test_jasa_susulan_masuk_ke_akun_pendapatan_jasa(): void
    {
        $jasaId = DB::table('jasa')->insertGetId([
            'nama_jasa' => 'Jasa Uji Susulan', 'harga_default' => 35000,
        ]);

        $id = $this->buatNota('N-SUS-JASA');

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", [
                'items' => [[
                    'id_jasa' => $jasaId,
                    'nama_file' => 'Jasa Uji',
                    'qty_input' => 2,
                    'harga_satuan' => 35000,
                ]],
            ])
            ->assertOk();

        $detail = DB::table('transaksi_detail')->where('id_transaksi', $id)->first();
        $this->assertSame('Selesai', $detail->status_spk, 'baris jasa langsung selesai, tidak masuk antrian produksi');
        $this->assertEquals(70000.0, (float) $detail->subtotal);

        // Jurnal jasa harus ke 4100 (Pendapatan Jasa), bukan 4000 (Pendapatan Penjualan).
        $akunJasa = DB::table('jurnal_umum')
            ->where('no_referensi', 'N-SUS-JASA-susulan')
            ->where('kredit', '>', 0)
            ->pluck('kode_akun')
            ->all();
        $this->assertContains('4100', $akunJasa, 'jasa susulan harus diposting ke akun Pendapatan Jasa (4100)');
        $this->assertNotContains('4000', $akunJasa, 'jasa susulan tidak boleh masuk akun Pendapatan Penjualan (4000)');
    }

    public function test_produk_susulan_masuk_ke_akun_pendapatan_penjualan(): void
    {
        $id = $this->buatNota('N-SUS-PRODUK');

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
            ->assertOk();

        $akun = DB::table('jurnal_umum')
            ->where('no_referensi', 'N-SUS-PRODUK-susulan')
            ->where('kredit', '>', 0)
            ->pluck('kode_akun')
            ->all();
        $this->assertContains('4000', $akun, 'produk susulan diposting ke akun Pendapatan Penjualan (4000)');
    }

    public function test_kartu_stok_dan_snapshot_bahan_pakai_tercatat(): void
    {
        $id = $this->buatNota('N-SUS-STOK');

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
            ->assertOk();

        $this->assertGreaterThan(
            0,
            DB::table('kartu_stok')->where('referensi', 'like', '%(susulan)%')->count(),
            'kartu stok harus mencatat pemakaian susulan'
        );

        $detail = DB::table('transaksi_detail')->where('id_transaksi', $id)->first();
        $pemakaian = DB::table('transaksi_detail_bahan_pakai')->where('id_detail', $detail->id_detail)->get();
        $this->assertGreaterThan(0, $pemakaian->count(), 'snapshot bahan pakai harus menempel ke id_detail yang benar');
    }

    public function test_total_nota_yang_sudah_lunas_turun_status_tetapi_pembayaran_lama_tetap(): void
    {
        $id = $this->buatNota('N-SUS-LUNAS', 'Diterima Kasir', 'now', 100000);
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
        $this->assertEquals(
            100000.0,
            (float) DB::table('pembayaran_riwayat')->where('id_transaksi', $id)->sum('nominal'),
            'pembayaran lama tidak boleh berubah'
        );
    }

    /**
     * Item yang SUDAH ada di nota asal tampil di keranjang layar susulan,
     * sebagai daftar baca-saja - supaya operator tahu apa yang sudah
     * tercatat sebelum menambah item lagi.
     */
    public function test_item_lama_nota_tampil_read_only_di_layar_susulan(): void
    {
        $id = $this->buatNota('N-SUS-LAMA');

        $this->actingAs($this->admin)
            ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
            ->assertOk();

        $r = $this->actingAs($this->admin)->get("/penjualan/kasir?susulan={$id}");

        $r->assertOk();
        $r->assertSee('Sudah ada di nota ini');
        $r->assertSee('susulan.pdf');
        // Penjelasan kenapa item lama tidak bisa diubah dari layar ini.
        $r->assertSee('tidak bisa diubah dari layar ini');
    }

    /**
     * Layar (create) dan endpoint (tambah-item) harus memakai syarat
     * status_order YANG SAMA.
     *
     * Dulu keduanya berbeda: layar membolehkan nota 'Menunggu Kasir' tapi
     * endpoint menolaknya dengan "Nota belum dibuka kasir" - operator boleh
     * mengisi form penuh lalu ditolak saat menekan "Tambahkan ke Nota".
     */
    public function test_layar_dan_endpoint_sepakat_terhadap_status_order_yang_boleh(): void
    {
        foreach (['Menunggu Kasir', 'Diterima Kasir'] as $status) {
            $id = $this->buatNota('N-SUS-S-' . str_replace(' ', '-', $status), $status);

            $this->actingAs($this->admin)->get("/penjualan/kasir?susulan={$id}")->assertOk();
            $this->actingAs($this->admin)
                ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
                ->assertOk();
        }

        // Status yang TIDAK boleh: layar dan endpoint keduanya menolak.
        // 'Dibatalkan' adalah satu-satunya status_order di luar dua status
        // yang diizinkan (kolomnya enum).
        foreach (['Dibatalkan'] as $status) {
            $id = $this->buatNota('N-SUS-X-' . str_replace(' ', '-', $status), $status);

            $this->actingAs($this->admin)->get("/penjualan/kasir?susulan={$id}")->assertStatus(422);
            $this->actingAs($this->admin)
                ->postJson("/penjualan/{$id}/tambah-item", $this->payloadItem())
                ->assertStatus(422);
        }
    }

    public function test_tombol_tambah_item_di_riwayat_mengarah_ke_layar_input_pesanan(): void
    {
        $id = $this->buatNota('N-SUS-RIWAYAT');

        $r = $this->actingAs($this->admin)->get('/penjualan/riwayat');

        $r->assertOk();
        $r->assertSee(route('penjualan.create', ['susulan' => $id]), false);
    }
}
