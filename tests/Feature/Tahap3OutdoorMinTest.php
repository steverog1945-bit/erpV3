<?php
namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * T1-T3: Minimum luas outdoor (total per kelompok), PENYAJIAN HYBRID
 * (keputusan owner, notasi SETUJU T4-A): selisih tidak lagi jadi baris
 * terpisah — digulung ke subtotal baris item TERBESAR kelompok itu, dan
 * kolom is_minimum_order menandai baris item yang membawa selisih.
 * Ditulis terhadap perilaku yang DIHARAPKAN (aturan final + hybrid).
 * Harus GAGAL sebelum perbaikan, HIJAU setelah.
 */
class Tahap3OutdoorMinTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $gol;
    private int $kons;
    private int $mesin;
    private int $kat;
    private int $m2;
    private int $bahanA;
    private int $bahanB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
        DB::table('roles')->where('nama_role', 'Administrator')->update(['boleh_lihat_harga' => true]);
        $this->actingAs($this->admin);

        $this->mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M1', 'tipe_mesin' => 'Cetak']);
        $this->kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $this->m2 = DB::table('satuan')->insertGetId(['nama_satuan' => 'm2', 'nilai_konversi' => 1]);
        $this->bahanA = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'BA', 'nama_bahan' => 'Bahan A', 'id_mesin' => $this->mesin, 'id_kategori' => $this->kat,
            'stok' => 100, 'nilai_konversi' => 1, 'lebar_roll' => 1.2, 'satuan_stok' => 'm2',
            'id_satuan_hitung' => $this->m2, 'id_satuan_stok' => $this->m2, 'status' => 'aktif', 'harga_rata_rata' => 1000,
        ]);
        $this->bahanB = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'BB', 'nama_bahan' => 'Bahan B', 'id_mesin' => $this->mesin, 'id_kategori' => $this->kat,
            'stok' => 100, 'nilai_konversi' => 1, 'lebar_roll' => 1.2, 'satuan_stok' => 'm2',
            'id_satuan_hitung' => $this->m2, 'id_satuan_stok' => $this->m2, 'status' => 'aktif', 'harga_rata_rata' => 2000,
        ]);
        $this->gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Kons Uji', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $this->gol]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()->subMinute()]);
    }

    private function makeProduk(string $nama, string $kelompok, float $minLuas, int $bahan, float $harga): int
    {
        $id = DB::table('produk')->insertGetId([
            'nama_produk' => $nama, 'metode_hitung' => 'ukuran', 'status' => 'aktif',
            'min_luas_total' => $minLuas, 'kelompok_minimum' => $kelompok,
        ]);
        DB::table('bom_detail')->insert(['id_produk' => $id, 'id_bahan' => $bahan, 'cara_hitung_pemakaian' => '(P*L)/10000', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('setting_harga')->insert(['id_produk' => $id, 'id_golongan' => $this->gol, 'range_min' => 0.01, 'harga_jual' => $harga]);
        return $id;
    }

    private function store(array $items): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/penjualan/store', [
            'id_konsumen' => $this->kons, 'id_golongan' => $this->gol, 'submit_id' => 't3-' . uniqid(),
            'items' => $items,
        ]);
    }

    // biaya_cetak dikirim = tarif x luas aktual (baris item ditagih luas
    // aktualnya), lalu selisih minimum digulung server ke baris terbesar
    // kelompok.
    private function item(int $idProduk, float $p, float $l, float $qty, float $harga): array
    {
        return [
            'id_produk' => $idProduk, 'nama_file' => 'uji.pdf', 'p' => $p, 'l' => $l, 'qty_input' => $qty,
            'biaya_cetak' => round($harga * (($p * $l / 10000) * $qty), 2), 'siap_cetak' => false,
        ];
    }

    private function barisNota(): array
    {
        $nota = DB::table('transaksi')->first();
        return DB::table('transaksi_detail')->where('id_transaksi', $nota->id_transaksi)
            ->whereNull('id_jasa')->orderBy('id_detail')->get()->all();
    }

    public function test_satu_item_0_6_ditagih_1_0(): void
    {
        $prod = $this->makeProduk('Outdoor A', 'Outdoor A', 1.0, $this->bahanA, 50000);
        $this->store([$this->item($prod, 60, 100, 1, 50000)])->assertOk();
        $nota = DB::table('transaksi')->first();
        // Tidak ada baris "Minimum Order - ..." terpisah di nota.
        $barisTerpisah = DB::table('transaksi_detail')
            ->where('id_transaksi', $nota->id_transaksi)
            ->whereNull('id_produk')->whereNull('id_jasa')->count();
        $this->assertSame(0, $barisTerpisah, 'mode hybrid: tidak ada baris minimum terpisah');
        // Satu baris item: subtotal digulung ke tarif x min_luas_total (0.6+0.4 = 1.0 x 50000).
        $baris = $this->barisNota();
        $this->assertCount(1, $baris);
        $this->assertEquals(50000.0, (float) $baris[0]->subtotal, 0.01);
        // qty tetap aktual (bukan 1.0 selisih), penanda minimum di baris item.
        $this->assertEquals(1, (int) $baris[0]->qty);
        $this->assertEquals(1, (int) $baris[0]->is_minimum_order);
        // Total nota = tarif x minimum kelompok.
        $this->assertEquals(50000.0, (float) DB::table('transaksi')->value('total_bayar'), 0.01);
    }

    public function test_0_6_plus_0_3_bahan_sama_ditagih_1_0(): void
    {
        $prod = $this->makeProduk('Outdoor A', 'Outdoor A', 1.0, $this->bahanA, 50000);
        $this->store([$this->item($prod, 60, 100, 1, 50000), $this->item($prod, 30, 100, 1, 50000)])->assertOk();
        $nota = DB::table('transaksi')->first();
        $baris = $this->barisNota();
        $this->assertCount(2, $baris, 'dua baris item, tanpa baris minimum terpisah');
        // Selisih 0.1 x 50000 = 5000 digulung ke baris TERBESAR (0.6).
        $this->assertEquals(35000.0, (float) $baris[0]->subtotal, 0.01); // 0.6 x 50000 + 0.1 x 50000
        $this->assertEquals(1, (int) $baris[0]->is_minimum_order);
        $this->assertEquals(15000.0, (float) $baris[1]->subtotal, 0.01); // 0.3 x 50000 aktual
        $this->assertEquals(0, (int) $baris[1]->is_minimum_order);
        $this->assertEquals(50000.0, (float) DB::table('transaksi')->value('total_bayar'), 0.01);
    }

    public function test_0_6_plus_0_7_tanpa_selisih_total_1_3(): void
    {
        $prod = $this->makeProduk('Outdoor A', 'Outdoor A', 1.0, $this->bahanA, 50000);
        $this->store([$this->item($prod, 60, 100, 1, 50000), $this->item($prod, 70, 100, 1, 50000)])->assertOk();
        $baris = $this->barisNota();
        foreach ($baris as $b) {
            $this->assertEquals(0, (int) $b->is_minimum_order, 'total >= 1, tanpa penanda minimum');
        }
        $this->assertEquals(30000.0, (float) $baris[0]->subtotal, 0.01);
        $this->assertEquals(35000.0, (float) $baris[1]->subtotal, 0.01);
    }

    public function test_qty_5_x_50x50_tanpa_selisih(): void
    {
        $prod = $this->makeProduk('Outdoor A', 'Outdoor A', 1.0, $this->bahanA, 50000);
        $this->store([$this->item($prod, 50, 50, 5, 50000)])->assertOk();
        $baris = $this->barisNota();
        $this->assertSame(0, (int) $baris[0]->is_minimum_order);
        $this->assertEquals(62500.0, (float) $baris[0]->subtotal, 0.01); // 1.25 m2 x 50000
    }

    public function test_0_5_A_plus_0_5_B_selisih_per_kelompok_total_2_0(): void
    {
        $prodA = $this->makeProduk('Outdoor A', 'Kelompok A', 1.0, $this->bahanA, 50000);
        $prodB = $this->makeProduk('Outdoor B', 'Kelompok B', 1.0, $this->bahanB, 60000);
        $this->store([$this->item($prodA, 50, 100, 1, 50000), $this->item($prodB, 50, 100, 1, 60000)])->assertOk();
        $baris = $this->barisNota();
        // Masing-masing kelompok: satu baris item membawa selisihnya sendiri.
        $this->assertCount(2, $baris);
        $flagged = array_values(array_filter($baris, fn ($b) => (int) $b->is_minimum_order === 1));
        $this->assertCount(2, $flagged, 'kedua kelompok selisih 0.5, penanda di baris item masing-masing');
        $subtotalFlagged = array_sum(array_map(fn ($b) => (float) $b->subtotal, $flagged));
        $this->assertEquals(50000.0 + 60000.0, $subtotalFlagged, 0.01); // (0.5+0.5) x tarif masing-masing
    }

    public function test_produk_tanpa_min_luas_total_tidak_kena(): void
    {
        $prod = DB::table('produk')->insertGetId(['nama_produk' => 'Indoor', 'metode_hitung' => 'ukuran', 'status' => 'aktif', 'min_luas_total' => 0, 'kelompok_minimum' => null]);
        DB::table('bom_detail')->insert(['id_produk' => $prod, 'id_bahan' => $this->bahanA, 'cara_hitung_pemakaian' => '(P*L)/10000', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('setting_harga')->insert(['id_produk' => $prod, 'id_golongan' => $this->gol, 'range_min' => 0.01, 'harga_jual' => 10000]);
        $this->store([$this->item($prod, 10, 10, 1, 10000)])->assertOk();
        $baris = $this->barisNota();
        $this->assertSame(0, (int) $baris[0]->is_minimum_order);
    }

    public function test_penanda_minimum_di_baris_item_tetap_beralur_spk(): void
    {
        $prod = $this->makeProduk('Outdoor C', 'Kelompok C', 1.0, $this->bahanA, 50000);
        $this->store([$this->item($prod, 60, 100, 1, 50000)])->assertOk();
        $baris = $this->barisNota();
        $flagged = array_values(array_filter($baris, fn ($b) => (int) $b->is_minimum_order === 1));
        $this->assertCount(1, $flagged);
        // Baris flagged adalah baris ITEM sungguhan: punya produk, berstatus
        // 'Antre' (masuk alur SPK/produksi normal), bukan baris hantu.
        $this->assertEquals($prod, (int) $flagged[0]->id_produk);
        $this->assertSame('Antre', $flagged[0]->status_spk);
        // Catatan minimum ikut tersimpan supaya bisa menjelaskan tambahan subtotal.
        $this->assertStringContainsString('Minimum Order', (string) $flagged[0]->catatan_produksi);
    }
}
