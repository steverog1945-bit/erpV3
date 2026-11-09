<?php
namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Reproduksi T4 (validasi minimum) dan T5 (kunci nota Lunas) — HARUS GAGAL
 * sebelum perbaikan. Ditulis terhadap perilaku yang DIHARAPKAN (aturan final),
 * bukan perilaku sekarang.
 */
class Tahap2ReproTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $gol;
    private int $kons;
    private int $camel;
    private int $lanyard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
        DB::table('roles')->where('nama_role', 'Administrator')->update(['boleh_lihat_harga' => true]);
        $this->actingAs($this->admin);

        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M1', 'tipe_mesin' => 'Cetak']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $m2 = DB::table('satuan')->insertGetId(['nama_satuan' => 'm2', 'nilai_konversi' => 1]);

        // Camel: min_panjang 155, min_lebar 20 (stiker)
        $this->camel = DB::table('produk')->insertGetId([
            'nama_produk' => 'Camel Stiker Uji', 'metode_hitung' => 'ukuran', 'status' => 'aktif',
            'min_panjang' => 155, 'min_lebar' => 20, 'min_order' => 0,
        ]);
        DB::table('bom_detail')->insert(['id_produk' => $this->camel, 'id_bahan' => DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'C1', 'nama_bahan' => 'Kertas Camel', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 100, 'nilai_konversi' => 1, 'lebar_roll' => 1.2, 'satuan_stok' => 'm2',
            'id_satuan_hitung' => $m2, 'id_satuan_stok' => $m2, 'status' => 'aktif', 'harga_rata_rata' => 1000,
        ]), 'cara_hitung_pemakaian' => '(P*L)/10000', 'created_at' => now(), 'updated_at' => now()]);

        // Lanyard: min_order 10 pcs (quantity)
        $this->lanyard = DB::table('produk')->insertGetId([
            'nama_produk' => 'Lanyard Uji', 'metode_hitung' => 'quantity', 'status' => 'aktif',
            'min_panjang' => 0, 'min_lebar' => 0, 'min_order' => 10,
        ]);
        DB::table('bom_detail')->insert(['id_produk' => $this->lanyard, 'id_bahan' => DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'L1', 'nama_bahan' => 'Lanyard Bahan', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 100, 'nilai_konversi' => 1, 'lebar_roll' => 1, 'satuan_stok' => 'pcs',
            'id_satuan_hitung' => $m2, 'id_satuan_stok' => $m2, 'status' => 'aktif', 'harga_rata_rata' => 500,
        ]), 'cara_hitung_pemakaian' => 'Q', 'created_at' => now(), 'updated_at' => now()]);

        $this->gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Kons Uji', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $this->gol]);
        DB::table('setting_harga')->insert(['id_produk' => $this->camel, 'id_golongan' => $this->gol, 'range_min' => 0, 'harga_jual' => 1000]);
        DB::table('setting_harga')->insert(['id_produk' => $this->lanyard, 'id_golongan' => $this->gol, 'range_min' => 0, 'harga_jual' => 500]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()->subMinute()]);
    }

    private function store(array $items): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/penjualan/store', [
            'id_konsumen' => $this->kons, 'id_golongan' => $this->gol, 'submit_id' => 'repro-' . uniqid(),
            'items' => $items,
        ]);
    }

    // T4 — validasi minimum (harus GAGAL sekarang)
    public function test_camel_panjang_154_ditolak(): void
    {
        $this->store([['id_produk' => $this->camel, 'nama_file' => 'a.pdf', 'p' => 154, 'l' => 20, 'qty_input' => 1, 'biaya_cetak' => 1000]])
            ->assertStatus(422);
    }
    public function test_camel_155x19_ditolak(): void
    {
        $this->store([['id_produk' => $this->camel, 'nama_file' => 'a.pdf', 'p' => 155, 'l' => 19, 'qty_input' => 1, 'biaya_cetak' => 1000]])
            ->assertStatus(422);
    }
    public function test_camel_155x20_diterima(): void
    {
        $this->store([['id_produk' => $this->camel, 'nama_file' => 'a.pdf', 'p' => 155, 'l' => 20, 'qty_input' => 1, 'biaya_cetak' => 1000]])
            ->assertOk();
    }
    public function test_camel_20x155_ditolak(): void
    {
        $this->store([['id_produk' => $this->camel, 'nama_file' => 'a.pdf', 'p' => 20, 'l' => 155, 'qty_input' => 1, 'biaya_cetak' => 1000]])
            ->assertStatus(422);
    }
    public function test_lanyard_9_ditolak(): void
    {
        $this->store([['id_produk' => $this->lanyard, 'nama_file' => 'b.pdf', 'p' => 0, 'l' => 0, 'qty_input' => 9, 'biaya_cetak' => 500]])
            ->assertStatus(422);
    }
    public function test_lanyard_10_diterima(): void
    {
        $this->store([['id_produk' => $this->lanyard, 'nama_file' => 'b.pdf', 'p' => 0, 'l' => 0, 'qty_input' => 10, 'biaya_cetak' => 500]])
            ->assertOk();
    }
    public function test_post_manual_tanpa_layar_ditolak(): void
    {
        // camel 154 via POST langsung — harus ditolak backend (bukan hanya JS)
        $this->store([['id_produk' => $this->camel, 'nama_file' => 'c.pdf', 'p' => 154, 'l' => 20, 'qty_input' => 1, 'biaya_cetak' => 1000]])
            ->assertStatus(422);
    }

    // T5 — kunci nota (harus GAGAL sekarang karena bolehEdit tidak cek Lunas)
    public function test_update_item_pada_nota_lunas_ditolak(): void
    {
        $this->postJson('/penjualan/store', ['id_konsumen' => $this->kons, 'id_golongan' => $this->gol, 'submit_id' => 'lunas-1',
            'items' => [['id_produk' => $this->lanyard, 'nama_file' => 'd.pdf', 'p' => 0, 'l' => 0, 'qty_input' => 10, 'biaya_cetak' => 500]]])->assertOk();
        $nota = DB::table('transaksi')->first();
        // Lunas yang BENAR: pembayaran_riwayat nominal = total (bukan hanya kolom status)
        DB::table('pembayaran_riwayat')->insert([
            'id_transaksi' => $nota->id_transaksi, 'nominal' => $nota->total_bayar,
            'metode' => 'Tunai', 'tgl_bayar' => now(), 'keterangan' => 'pelunasan',
        ]);
        DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->update(['status_pembayaran' => 'Lunas']);
        $detail = DB::table('transaksi_detail')->where('id_transaksi', $nota->id_transaksi)->first();
        $this->postJson("/penjualan/{$nota->id_transaksi}/update-item", [
            'items' => [['id_detail' => $detail->id_detail, 'id_produk' => $this->lanyard, 'nama_file' => 'd.pdf', 'p' => 0, 'l' => 0, 'qty_input' => 10, 'biaya_cetak' => 500]],
        ])->assertStatus(422);
    }
}
