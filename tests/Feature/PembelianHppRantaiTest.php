<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Uji E2E alur pembelian - rantai lengkap dari barang masuk sampai HPP.
 *
 * Alurnya ada TIGA langkah, dan dua yang terpisah secara eksplisit di kode:
 *
 *   1. POST /kedatangan-bahan            -> catat kedatangan (belum
 *      menggerakkan apa pun).
 *   2. PATCH /kedatangan-bahan/validate  -> STOK NAIK + kartu stok IN.
 *      Sumber: KedatanganBahanController::validateStatus() baris 249-266.
 *      Komentar di PembelianBahanController baris 230-235 menegaskan stok
 *      TIDAK boleh ditambah di faktur.
 *   3. POST /pembelian-bahan             -> HARGA RATA-RATA BERGERAK.
 *      Sumber: PembelianBahanController baris 256-275.
 *
 * Langkah 2 dan 3 terpisah inilah yang sering disalahpahami: stok sudah
 * bertambah tapi harga_rata_rata masih 0 sampai faktur dibuat. Test ini
 * mengunci urutan itu supaya tidak ada yang "memperbaiki" langkah 2 dengan
 * menyelipkan update harga di sana.
 *
 * Angka yang dipakai (nilai_konversi 1 supaya tidak ada pengali):
 *
 *   batch 1 : 10 rim x Rp 10.000
 *   batch 2 : 10 rim x Rp 20.000
 *   rata-rata tertimbang = (10x10.000 + 10x20.000) / 20 = Rp 15.000
 *   order berikutnya: 4 rim x Rp 15.000 = Rp 60.000
 *
 * Rata-rata sederhana akan memberi Rp 12.500 atau Rp 20.000 - test ini
 * membedakan secara eksplisit.
 */
class PembelianHppRantaiTest extends TestCase
{
    use RefreshDatabase;

    private const HARGA_BATCH_1 = 10000.0;

    private const HARGA_BATCH_2 = 20000.0;

    private const QTY_BATCH = 10.0;

    private const QTY_ORDER = 4.0;

    private int $idBahan;

    private int $idProduk;

    private int $idKonsumen;

    private int $idGol;

    private int $idSupplier;

    private int $idGudang;

    private function siapkan(): User
    {
        $role = Role::factory()->create(['nama_role' => 'Kasir Uji Pembelian']);
        DB::table('roles')->where('id_role', $role->id_role)->update(['boleh_lihat_harga' => 0]);

        // 3, 41 = menu Input Order (PenjualanController).
        // 36 = menu Akuntansi (config/menu_access.php baris 150).
        // 17 = menu Kedatangan Bahan (baris 90). TANPA ini PATCH
        // /kedatangan-bahan/validate/{id} balas 403 - dan karena
        // validateStatus() yang menggerakkan stok, stok tidak pernah
        // berubah.
        // 20 = menu Pembelian Bahan (baris 95) untuk faktur.
        // 1 = menu Bahan Baku.
        foreach ([3, 41, 36, 17, 20, 1] as $id) {
            DB::table('menus')->insertOrIgnore([
                'id_menu' => $id, 'nama_menu' => "M{$id}", 'url_link' => "m{$id}", 'parent_id' => 0,
            ]);
            DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => $id]);
        }

        $user = User::factory()->create(['id_role' => $role->id_role]);

        $this->idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail Uji', 'keterangan' => '-']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Konsumen Uji Pembelian', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $this->idGol,
        ]);

        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin Uji', 'tipe_mesin' => 'Uji']);
        $idKategori = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kategori Uji']);

        $this->idBahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'PEM-01',
            'nama_bahan' => 'Bahan Uji Pembelian',
            'id_mesin' => $idMesin,
            'id_kategori' => $idKategori,
            'stok' => 0,
            'stok_minimal' => 0,
            'nilai_konversi' => 1,
            'harga_rata_rata' => 0,
            'satuan_stok' => 'rim',
            'status' => 'aktif',
            'created_at' => now(),
        ]);

        $this->idProduk = DB::table('produk')->insertGetId([
            'nama_produk' => 'Produk Uji Pembelian',
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
            'harga_jual' => 99999,
        ]);

        $this->idSupplier = DB::table('supplier')->insertGetId([
            'nama_supplier' => 'PT Supplier Uji', 'alamat' => '-', 'kontak' => '0800',
        ]);
        $this->idGudang = DB::table('gudang')->insertGetId(['nama_gudang' => 'Gudang Uji']);

        DB::table('kas_harian')->insert([
            'tgl_kas' => now()->toDateString(),
            'id_user' => $user->id_user,
            'saldo_awal' => 0,
            'status_lock' => 'open',
            'created_at' => now()->subMinute(),
        ]);

        return $user;
    }

    /**
     * Langkah 1 + 2: catat kedatangan, lalu validasi (stok naik).
     * Mengembalikan id_kedatangan untuk dipakai langkah 3.
     */
    private function barangMasuk(User $user, string $noSj, float $qty): int
    {
        $this->actingAs($user)->post('/kedatangan-bahan', [
            'no_surat_jalan' => $noSj,
            'id_supplier' => $this->idSupplier,
            'tgl_datang' => now()->toDateString(),
            'id_gudang' => $this->idGudang,
            'id_bahan' => [$this->idBahan],
            'qty' => [$qty],
        ])->assertRedirect();

        $id = (int) DB::table('kedatangan_bahan')->where('no_surat_jalan', $noSj)->value('id_kedatangan');
        $this->assertNotSame(0, $id, 'kedatangan harus tercatat');

        $this->actingAs($user)->patch("/kedatangan-bahan/validate/{$id}")->assertRedirect();

        return $id;
    }

    /**
     * Langkah 3: buat faktur pembelian. Ini yang menggerakkan harga_rata_rata.
     */
    private function buatFaktur(User $user, int $idKedatangan, string $noFaktur, float $harga): void
    {
        $this->actingAs($user)->post('/pembelian-bahan', [
            'id_kedatangan' => $idKedatangan,
            'no_faktur' => $noFaktur,
            'tgl_transaksi' => now()->toDateString(),
            'tipe_pembelian' => 'Cash',
            'id_bahan' => [$this->idBahan],
            'qty' => [self::QTY_BATCH],
            'harga' => [$harga],
        ])->assertRedirect();
    }

    private function stok(): float
    {
        return (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('stok');
    }

    private function rataRata(): float
    {
        return (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('harga_rata_rata');
    }

    public function test_kedatangan_menggerakkan_stok_tapi_bukan_harga(): void
    {
        $user = $this->siapkan();

        $id = $this->barangMasuk($user, 'SJ-STEP', 5);

        // Langkah 2 sudah menggerakkan stok...
        $this->assertEqualsWithDelta(5.0, $this->stok(), 0.0001, 'stok harus 5 setelah validate');

        // ...tapi harga_rata_rata SENGAJA belum berubah. Komentar di
        // PembelianBahanController baris 237 bilang "Harga rata-rata diperbarui
        // SETELAH total faktur akhir diketahui". Kalau harga diupdate di
        // langkah 2, sebuah faktur bisa menimpa harga yang lebih dulu sudah
        // dipakai order lain (lost update pada harga).
        $this->assertEqualsWithDelta(0.0, $this->rataRata(), 0.01, 'harga_rata_rata belum boleh berubah sebelum faktur');

        // Dan faktur hanya bisa dibuat dari kedatangan berstatus Selesai.
        $this->assertSame('Selesai', DB::table('kedatangan_bahan')->where('id_kedatangan', $id)->value('status'));
    }

    public function test_faktur_batch_pertama_menjadikan_harga_batch_itu_sendiri(): void
    {
        $user = $this->siapkan();

        $id = $this->barangMasuk($user, 'SJ-B1', self::QTY_BATCH);
        $this->buatFaktur($user, $id, 'INV-B1', self::HARGA_BATCH_1);

        $this->assertEqualsWithDelta(self::QTY_BATCH, $this->stok(), 0.0001);
        $this->assertEqualsWithDelta(self::HARGA_BATCH_1, $this->rataRata(), 0.01, 'batch pertama: harga_rata_rata = harga batch');
    }

    public function test_faktur_batch_kedua_menghitung_rata_rata_tertimbang(): void
    {
        $user = $this->siapkan();

        $id1 = $this->barangMasuk($user, 'SJ-B1', self::QTY_BATCH);
        $this->buatFaktur($user, $id1, 'INV-B1', self::HARGA_BATCH_1);

        $id2 = $this->barangMasuk($user, 'SJ-B2', self::QTY_BATCH);
        $this->buatFaktur($user, $id2, 'INV-B2', self::HARGA_BATCH_2);

        // (10 x 10.000 + 10 x 20.000) / 20 = 15.000
        $harapan = ((self::HARGA_BATCH_1 * self::QTY_BATCH) + (self::HARGA_BATCH_2 * self::QTY_BATCH)) / (2 * self::QTY_BATCH);

        $this->assertEqualsWithDelta(2 * self::QTY_BATCH, $this->stok(), 0.0001, 'stok harus 20 rim');
        $this->assertEqualsWithDelta($harapan, $this->rataRata(), 0.01, 'harga_rata_rata harus rata-rata tertimbang Rp 15.000');
    }

    public function test_rata_rata_tertimbang_dengan_qty_batch_berbeda(): void
    {
        $user = $this->siapkan();

        // 40 rim @ Rp 10.000 lalu 10 rim @ Rp 20.000.
        //   tertimbang = (40x10.000 + 10x20.000) / 50 = Rp 12.000
        //   sederhana  = (10.000 + 20.000) / 2         = Rp 15.000  <- beda!
        $id1 = $this->barangMasuk($user, 'SJ-A', 40);
        $this->buatFaktur($user, $id1, 'INV-A', self::HARGA_BATCH_1);

        $id2 = $this->barangMasuk($user, 'SJ-B', 10);
        $this->buatFaktur($user, $id2, 'INV-B', self::HARGA_BATCH_2);

        $tertimbang = ((self::HARGA_BATCH_1 * 40) + (self::HARGA_BATCH_2 * 10)) / 50;

        $this->assertEqualsWithDelta(50.0, $this->stok(), 0.0001, 'stok harus 50 rim');
        $this->assertEqualsWithDelta($tertimbang, $this->rataRata(), 0.01, 'harus rata-rata tertimbang Rp 12.000');
        $this->assertNotEquals(15000.0, round($this->rataRata(), 2), 'jangan sampai jatuh ke rata-rata sederhana');
    }

    public function test_hpp_order_berikutnya_memakai_harga_rata_rata_terbaru(): void
    {
        $user = $this->siapkan();

        $id1 = $this->barangMasuk($user, 'SJ-B1', self::QTY_BATCH);
        $this->buatFaktur($user, $id1, 'INV-B1', self::HARGA_BATCH_1);

        $id2 = $this->barangMasuk($user, 'SJ-B2', self::QTY_BATCH);
        $this->buatFaktur($user, $id2, 'INV-B2', self::HARGA_BATCH_2);

        $rataRata = $this->rataRata();
        $this->assertEqualsWithDelta(15000.0, $rataRata, 0.01);

        $this->actingAs($user)->postJson('/penjualan/store', [
            'id_konsumen' => $this->idKonsumen,
            'id_golongan' => $this->idGol,
            'items' => [[
                'id_produk' => $this->idProduk,
                'nama_file' => 'uji-pembelian.jpg',
                'p' => 10,
                'l' => 10,
                'qty_input' => (int) self::QTY_ORDER,
                'biaya_cetak' => 1,
                'finishing' => [],
            ]],
        ])->assertOk();

        $hpp = (float) DB::table('transaksi_detail')->value('hpp_snapshot');

        // 4 rim x Rp 15.000. Kalau kode memakai harga batch terakhir
        // (20.000) hasilnya 80.000; kalau batch pertama (10.000) hasilnya
        // 40.000. Hanya 60.000 yang benar.
        $this->assertEqualsWithDelta(self::QTY_ORDER * $rataRata, $hpp, 0.01, 'HPP harus 4 rim x harga_rata_rata');
        $this->assertEqualsWithDelta(60000.0, $hpp, 0.01);
        $this->assertNotEquals(80000.0, round($hpp, 2), 'jangan memakai harga batch terakhir');
        $this->assertNotEquals(40000.0, round($hpp, 2), 'jangan memakai harga batch pertama');

        // Dan stok ikut berkurang 4.
        $this->assertEqualsWithDelta(20.0 - self::QTY_ORDER, $this->stok(), 0.0001);
    }

    public function test_laba_rugi_mengikuti_harga_rata_rata_terbaru(): void
    {
        $user = $this->siapkan();

        $id1 = $this->barangMasuk($user, 'SJ-B1', self::QTY_BATCH);
        $this->buatFaktur($user, $id1, 'INV-B1', self::HARGA_BATCH_1);

        $id2 = $this->barangMasuk($user, 'SJ-B2', self::QTY_BATCH);
        $this->buatFaktur($user, $id2, 'INV-B2', self::HARGA_BATCH_2);

        $this->actingAs($user)->postJson('/penjualan/store', [
            'id_konsumen' => $this->idKonsumen,
            'id_golongan' => $this->idGol,
            'items' => [[
                'id_produk' => $this->idProduk,
                'nama_file' => 'uji-pembelian.jpg',
                'p' => 10,
                'l' => 10,
                'qty_input' => (int) self::QTY_ORDER,
                'biaya_cetak' => 1,
                'finishing' => [],
            ]],
        ])->assertOk();

        $tgl = now()->toDateString();
        $view = $this->actingAs($user)->get('/akuntansi/laba-rugi?awal=' . $tgl . '&akhir=' . $tgl)->assertOk();

        $this->assertEqualsWithDelta(60000.0, (float) $view->viewData('totalHpp'), 0.01, 'HPP di laba rugi harus Rp 60.000');

        // Cross-check jurnal akun 5000 langsung ke database.
        $jurnal = (float) DB::table('jurnal_umum')->where('kode_akun', '5000')->sum(DB::raw('debet - kredit'));
        $this->assertEqualsWithDelta(60000.0, $jurnal, 0.01);
    }

    public function test_kartu_stok_mencatat_masuk_dan_keluar_secara_berurutan(): void
    {
        $user = $this->siapkan();

        $id = $this->barangMasuk($user, 'SJ-B1', self::QTY_BATCH);
        $this->buatFaktur($user, $id, 'INV-B1', self::HARGA_BATCH_1);

        $this->actingAs($user)->postJson('/penjualan/store', [
            'id_konsumen' => $this->idKonsumen,
            'id_golongan' => $this->idGol,
            'items' => [[
                'id_produk' => $this->idProduk,
                'nama_file' => 'uji-pembelian.jpg',
                'p' => 10,
                'l' => 10,
                'qty_input' => (int) self::QTY_ORDER,
                'biaya_cetak' => 1,
                'finishing' => [],
            ]],
        ])->assertOk();

        $kartu = DB::table('kartu_stok')->where('id_bahan', $this->idBahan)->orderBy('id')->get();

        $this->assertCount(2, $kartu, 'harus ada satu IN dan satu OUT');
        $this->assertSame('IN', $kartu[0]->tipe);
        $this->assertSame('OUT', $kartu[1]->tipe);

        // Rantai stok harus tersambung.
        $this->assertEqualsWithDelta((float) $kartu[0]->stok_sesudah, (float) $kartu[1]->stok_sebelum, 0.0001, 'rantai kartu stok putus');
        $this->assertEqualsWithDelta(10.0, (float) $kartu[0]->stok_sesudah, 0.0001);
        $this->assertEqualsWithDelta(6.0, (float) $kartu[1]->stok_sesudah, 0.0001);
    }
}