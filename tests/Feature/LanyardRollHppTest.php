<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\BomCalculationService;
use App\Services\PenjualanCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Skema bahan roll Lanyard 20mm x 100m yang dijual eceran per pcs:
 *
 * - bahan_baku   : satuan_stok = roll, satuan_hitung = meter,
 *                  nilai_konversi = 100 (1 roll = 100 meter), ikuti pola
 *                  "rim = 500 lembar" / "sublime paper = 160 m2/roll".
 * - pembelian    : kedatangan qty diisi PER ROLL (barang fisik diterima
 *                  per roll); rata-rata bergerak dihitung per roll.
 * - produk       : "Lanyard" metode_hitung quantity (jual per pcs).
 * - bom_detail   : cara_hitung_pemakaian 'Q*1' = 1 meter kain per pcs
 *                  (sesuaikan panjang/aturan, mis. 'Q*0.9' untuk 90 cm).
 * - checkout     : qty_pakai (meter) dibagi nilai_konversi -> roll, stok
 *                  roll terpotong proporsional, HPP snapshot = meter/100 x
 *                  harga_rata_rata per roll. Jual 12 pcs = 0.12 roll, bukan
 *                  12 roll dan bukan 100 x lebih mahal.
 */
class LanyardRollHppTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $bahan;
    private int $produk;
    private int $satuanRoll;
    private int $satuanMeter;
    private int $satuanPcs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($this->admin);

        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin Jahit Test', 'tipe_mesin' => 'Jahit']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'Tekstil', 'kode_kategori' => 'TEK']);

        $this->satuanRoll = DB::table('satuan')->insertGetId(['nama_satuan' => 'roll', 'nilai_konversi' => 1, 'tipe_satuan' => 'Barang']);
        $this->satuanMeter = DB::table('satuan')->insertGetId(['nama_satuan' => 'meter', 'nilai_konversi' => 100, 'tipe_satuan' => 'Panjang']);
        $this->satuanPcs = DB::table('satuan')->insertGetId(['nama_satuan' => 'pcs', 'nilai_konversi' => 1, 'tipe_satuan' => 'Barang']);

        // 2 roll diterima, beli Rp 250.000 per roll -> harga_rata_rata per roll.
        $this->bahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'LAN-20MM', 'nama_bahan' => 'Roll Lanyard 20mm x 100m',
            'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 2, 'satuan_stok' => 'roll',
            'id_satuan_stok' => $this->satuanRoll, 'id_satuan_hitung' => $this->satuanMeter,
            'nilai_konversi' => 100,
            'lebar_roll' => 0.02, 'panjang_roll' => 100,
            'harga_rata_rata' => 250000, 'status' => 'aktif',
        ]);
        DB::table('kartu_stok')->insert(['id_bahan' => $this->bahan, 'tanggal' => now()->subDay(), 'tipe' => 'IN', 'qty' => 2,
            'stok_sebelum' => 0, 'stok_sesudah' => 2, 'referensi' => 'SALDO-AWAL', 'keterangan' => 'saldo awal 2 roll',
            'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);

        $this->produk = DB::table('produk')->insertGetId(['nama_produk' => 'Lanyard Custom', 'metode_hitung' => 'quantity', 'status' => 'aktif']);
        DB::table('bom_detail')->insert(['id_produk' => $this->produk, 'id_bahan' => $this->bahan,
            'cara_hitung_pemakaian' => 'Q*1', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_rumus_bom_lanyard_valid_dan_terbaca(): void
    {
        // 1 meter per pcs, 12 pcs -> 12 meter
        $this->assertEqualsWithDelta(12.0, BomCalculationService::hitungQtyPakai('Q*1', 0, 0, 12), 1e-9);
        $this->assertTrue(BomCalculationService::rumusValid('Q*1'));
        // Varian ring 90 cm -> 0.9 meter per pcs
        $this->assertEqualsWithDelta(9.0, BomCalculationService::hitungQtyPakai('Q*0.9', 0, 0, 10), 1e-9);
    }

    public function test_pengurangan_stok_per_pcs_bukan_per_roll_dan_hpp_proporsional(): void
    {
        $golongan = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $konsumen = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Pembeli Lanyard', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $golongan]);
        $transaksi = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'LAN-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $konsumen,
            'id_user_kasir' => $this->admin->id_user, 'tgl_order' => now(), 'total_bayar' => 60000,
            'status_pembayaran' => 'Lunas', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir',
        ]);
        $detail = DB::table('transaksi_detail')->insertGetId([
            'id_transaksi' => $transaksi, 'id_produk' => $this->produk,
            'nama_file' => 'lanyard.pdf', 'p_ukuran' => 0, 'l_ukuran' => 0,
            'qty' => 12, 'subtotal' => 60000, 'status_spk' => 'Antre',
        ]);

        $svc = new PenjualanCheckoutService();
        $hpp = $svc->pakaiBahanItem(
            ['id_produk' => $this->produk, 'p' => 0, 'l' => 0],
            $detail,
            'LAN-1',
            'Order lanyard 12 pcs',
            12
        );

        // Stok berkurang 12 m / 100 m per roll = 0.12 roll (BUKAN 12 roll).
        $stok = (float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('stok');
        $this->assertEqualsWithDelta(1.88, $stok, 0.0001);

        // kartu_stok mencatat OUT 0.12 roll
        $out = DB::table('kartu_stok')->where('id_bahan', $this->bahan)->where('tipe', 'OUT')->first();
        $this->assertEqualsWithDelta(0.12, (float) $out->qty, 0.0001);

        // Snapshot per baris transaksi (dipakai reversal) juga 0.12 roll
        $pakai = DB::table('transaksi_detail_bahan_pakai')->where('id_detail', $detail)->first();
        $this->assertEqualsWithDelta(0.12, (float) $pakai->qty_pakai, 0.0001);

        // HPP per baris = 0.12 roll x Rp 250.000 = Rp 30.000 -> Rp 2.500 per pcs
        $this->assertEqualsWithDelta(30000.0, (float) DB::table('transaksi_detail')->where('id_detail', $detail)->value('hpp_snapshot'), 0.01);
        $this->assertSame('Q*1', DB::table('bom_detail')->where('id_produk', $this->produk)->value('cara_hitung_pemakaian'));
    }
}
