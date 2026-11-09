<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Role;
use App\Models\StockOpname;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * STOCK INTEGRITY lintas modul pada SATU bahan:
 *   kedatangan (IN) -> Input Pesanan ber-BOM (OUT) -> Penjualan dan Jasa (OUT) -> waste (OUT)
 *   -> penyesuaian manual (IN, lewat approval) -> stock opname (ADJUSTMENT, lewat approval) -> batal nota (IN reversal)
 * Setelah SETIAP langkah dibuktikan:
 *   stok awal + Σ pergerakan = stok akhir; rantai kartu stok tidak putus; tanda pergerakan sesuai tipe;
 *   tepat satu pergerakan baru per langkah (tidak ganda, tidak hilang); semua pergerakan punya referensi; stok tidak minus.
 */
class StokIntegritasAlurTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $bahan;
    private int $produk;
    private int $kons;
    private int $gol;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00:00'));
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($this->admin);

        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M1', 'tipe_mesin' => 'Cetak']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K', 'kode_kategori' => 'K']);
        $this->bahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'STK-1', 'nama_bahan' => 'Kertas Uji', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 0, 'nilai_konversi' => 1, 'harga_rata_rata' => 1000, 'satuan_stok' => 'lembar', 'status' => 'aktif',
        ]);
        $this->produk = DB::table('produk')->insertGetId(['nama_produk' => 'Produk Uji', 'metode_hitung' => 'quantity', 'status' => 'aktif']);
        DB::table('bom_detail')->insert(['id_produk' => $this->produk, 'id_bahan' => $this->bahan, 'cara_hitung_pemakaian' => 'Q', 'created_at' => now(), 'updated_at' => now()]);
        $this->gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $this->gol]);
        DB::table('setting_harga')->insert(['id_produk' => $this->produk, 'id_golongan' => $this->gol, 'range_min' => 0, 'harga_jual' => 10000]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()->subMinute()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function stok(): float
    {
        return (float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('stok');
    }

    /** Pembuktian invarian; $baruDiharapkan = jumlah baris kartu stok yang seharusnya bertambah pada langkah ini. */
    private function buktikan(string $langkah, float $stokDiharapkan, int $barisSebelum, int $baruDiharapkan): int
    {
        $kartu = DB::table('kartu_stok')->where('id_bahan', $this->bahan)->orderBy('id')->get();
        $this->assertCount($barisSebelum + $baruDiharapkan, $kartu, "{$langkah}: jumlah pergerakan stok tidak sesuai (ganda atau hilang)");
        $this->assertEquals($stokDiharapkan, $this->stok(), "{$langkah}: stok akhir");

        $berjalan = 0.0;
        foreach ($kartu as $i => $k) {
            $this->assertEquals($berjalan, (float) $k->stok_sebelum, "{$langkah}: rantai kartu stok putus di baris ke-" . ($i + 1));
            $delta = round((float) $k->stok_sesudah - (float) $k->stok_sebelum, 4);
            if ($k->tipe === 'IN') {
                $this->assertEquals(abs((float) $k->qty), $delta, "{$langkah}: baris IN tidak menambah sebesar qty");
            } elseif ($k->tipe === 'OUT') {
                $this->assertEquals(-abs((float) $k->qty), $delta, "{$langkah}: baris OUT tidak mengurangi sebesar qty");
            } else {
                $this->assertSame('ADJUSTMENT', $k->tipe);
            }
            $this->assertNotEmpty($k->referensi, "{$langkah}: pergerakan tanpa transaksi sumber");
            $berjalan = (float) $k->stok_sesudah;
        }
        $this->assertEquals($this->stok(), $berjalan, "{$langkah}: stok awal + Σ pergerakan != stok akhir");
        $this->assertGreaterThanOrEqual(0, $this->stok(), "{$langkah}: stok minus");

        return $kartu->count();
    }

    public function test_tujuh_alur_berurutan_menjaga_persamaan_stok_dan_rantai_kartu(): void
    {
        $n = 0;

        // 1. Kedatangan bahan (barang masuk) +100.
        $sup = DB::table('supplier')->insertGetId(['nama_supplier' => 'PT Kertas', 'alamat' => '-', 'kontak' => '0']);
        $gudang = DB::table('gudang')->insertGetId(['nama_gudang' => 'Utama']);
        $this->post('/kedatangan-bahan', ['no_surat_jalan' => 'SJ-STK', 'id_supplier' => $sup, 'tgl_datang' => now()->toDateString(),
            'id_gudang' => $gudang, 'id_bahan' => [$this->bahan], 'qty' => [100]])->assertRedirect();
        $n = $this->buktikan('kedatangan dicatat (belum divalidasi)', 0, $n, 0);
        $idK = (int) DB::table('kedatangan_bahan')->where('no_surat_jalan', 'SJ-STK')->value('id_kedatangan');
        $this->patch("/kedatangan-bahan/validate/{$idK}");
        $n = $this->buktikan('kedatangan divalidasi', 100, $n, 1);
        $this->patch("/kedatangan-bahan/validate/{$idK}");
        $n = $this->buktikan('validasi diulang', 100, $n, 0);

        // 2. Input Pesanan produk ber-BOM qty 5 (pemakaian produksi) -5.
        $this->postJson('/penjualan/store', ['id_konsumen' => $this->kons, 'id_golongan' => $this->gol,
            'items' => [['id_produk' => $this->produk, 'nama_file' => 'a', 'p' => 0, 'l' => 0, 'qty_input' => 5, 'biaya_cetak' => 50000]]])->assertOk();
        $n = $this->buktikan('input pesanan', 95, $n, 1);
        $this->assertEquals(5, (float) DB::table('transaksi_detail_bahan_pakai')->where('id_bahan', $this->bahan)->sum('qty_pakai'), 'snapshot pemakaian tidak sama dengan pergerakan');
        $idNota = (int) DB::table('transaksi')->value('id_transaksi');

        // 3. Penjualan dan Jasa (barang keluar dijual langsung) -3.
        $this->post('/penjualan-barang/store', ['id_konsumen' => $this->kons, 'items' => [['tipe' => 'bahan', 'id_bahan' => $this->bahan, 'qty' => 3, 'harga' => 2000]]])
            ->assertSessionHasNoErrors();
        $n = $this->buktikan('penjualan dan jasa', 92, $n, 1);

        // 4. Waste normal -2.
        $this->post('/waste', ['id_bahan' => $this->bahan, 'qty' => 2, 'jenis' => 'Normal', 'alasan' => 'Sisa potong'])->assertSessionHasNoErrors();
        $n = $this->buktikan('waste', 90, $n, 1);

        // 5. Revisi stok manual 90 -> 94. Diajukan Administrator = langsung disetujui & dijalankan, tetap tercatat di approval.
        $this->post('/kartu-stok/adjustment', ['id_bahan' => $this->bahan, 'tipe' => 'ADJUSTMENT', 'stok_baru' => 94, 'keterangan' => 'Temuan gudang']);
        $n = $this->buktikan('revisi stok manual', 94, $n, 1);
        $this->assertSame('Disetujui', ApprovalRequest::where('tipe_aksi', 'penyesuaian_stok_manual')->value('status'));

        // 6. Stock opname: fisik 91 (selisih -3) -> ADJUSTMENT setelah disetujui.
        $this->post('/stock-opname', ['tanggal' => now()->toDateString()]);
        $opname = StockOpname::firstOrFail();
        $detail = $opname->details()->where('id_bahan', $this->bahan)->firstOrFail();
        $this->patch(route('stock-opname.update-details', $opname->id), ['detail' => [$detail->id => ['stok_fisik' => 91, 'alasan' => 'Hitung ulang gudang']]]);
        $this->patch(route('stock-opname.ajukan', $opname->id));
        $reqOpname = ApprovalRequest::where('tipe_aksi', 'stock_opname')->firstOrFail();
        if ($reqOpname->status === 'Menunggu') {
            $n = $this->buktikan('opname diajukan', 94, $n, 0);
            $this->patch("/approval/{$reqOpname->id}/approve", ['catatan' => 'ok']);
        }
        $n = $this->buktikan('opname disetujui', 91, $n, 1);

        // 7. Batal nota Input Pesanan (item masih Antre, bahan belum terpakai, pilih kembalikan stok) -> +5 (reversal).
        $this->patch("/penjualan/{$idNota}/batalkan-nota", ['alasan' => 'Salah input', 'kembalikan_stok' => 1]);
        $reqBatal = ApprovalRequest::where('tipe_aksi', 'batalkan_nota')->firstOrFail();
        $this->patch("/approval/{$reqBatal->id}/approve", ['catatan' => 'ok']);
        $this->assertSame('Dibatalkan', DB::table('transaksi')->where('id_transaksi', $idNota)->value('status_nota'), 'nota tidak berstatus Dibatalkan: ' . json_encode(ApprovalRequest::find($reqBatal->id)->only(['status', 'catatan_keputusan'])) . ' stok=' . $this->stok());
        $n = $this->buktikan('batal nota', 96, $n, 1);

        // Pembatalan diulang tidak boleh mengembalikan stok dua kali.
        $this->patch("/penjualan/{$idNota}/batalkan-nota", ['alasan' => 'Ulang'])->assertSessionHas('error');
        $this->buktikan('batal diulang', 96, $n, 0);

        // Rekonsiliasi lintas modul lulus (stok vs kartu, jurnal seimbang).
        \Illuminate\Support\Facades\Artisan::call('audit:integritas', ['--json' => true]);
        $temuan = collect(json_decode(\Illuminate\Support\Facades\Artisan::output(), true)['temuan'])->pluck(0)->all();
        $this->assertNotContains('stok_tidak_sinkron', $temuan);
        $this->assertNotContains('jurnal_tidak_balance', $temuan);
    }
}
