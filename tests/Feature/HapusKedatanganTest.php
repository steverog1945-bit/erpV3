<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RECIPROCAL EFFECT - hapus kedatangan bahan (satuan dan massal):
 *   kedatangan divalidasi -> stok +100 -> hapus -> stok kembali, pergerakan balik tercatat, rantai kartu utuh.
 * FAILURE PATH: stok yang sudah terpakai tidak boleh membuat stok minus; kedatangan yang sudah difakturkan ditolak
 * dengan pesan, tanpa perubahan apa pun.
 */
class HapusKedatanganTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $bahan;
    private int $sup;
    private int $gudang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($this->admin);
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'x']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K', 'kode_kategori' => 'K']);
        $this->bahan = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'B', 'nama_bahan' => 'Flexi', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 0, 'nilai_konversi' => 1, 'harga_rata_rata' => 1000, 'satuan_stok' => 'm2', 'status' => 'aktif']);
        $this->sup = DB::table('supplier')->insertGetId(['nama_supplier' => 'PT A', 'alamat' => '-', 'kontak' => '0']);
        $this->gudang = DB::table('gudang')->insertGetId(['nama_gudang' => 'Utama']);
    }

    private function kedatanganDivalidasi(string $sj, float $qty): int
    {
        $this->post('/kedatangan-bahan', ['no_surat_jalan' => $sj, 'id_supplier' => $this->sup, 'tgl_datang' => now()->toDateString(),
            'id_gudang' => $this->gudang, 'id_bahan' => [$this->bahan], 'qty' => [$qty]])->assertRedirect();
        $id = (int) DB::table('kedatangan_bahan')->where('no_surat_jalan', $sj)->value('id_kedatangan');
        $this->patch("/kedatangan-bahan/validate/{$id}");

        return $id;
    }

    private function stok(): float
    {
        return (float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('stok');
    }

    private function potret(): array
    {
        return [
            'stok' => $this->stok(),
            'kartu' => DB::table('kartu_stok')->count(),
            'kedatangan' => DB::table('kedatangan_bahan')->count(),
            'detail' => DB::table('kedatangan_bahan_detail')->count(),
        ];
    }

    public function test_hapus_kedatangan_mengembalikan_stok_dan_mencatat_pergerakan_balik(): void
    {
        $id = $this->kedatanganDivalidasi('SJ-1', 100);
        $this->assertEquals(100, $this->stok());

        $this->delete("/kedatangan-bahan/{$id}")->assertSessionHasNoErrors();

        $this->assertEquals(0, $this->stok());
        $kartu = DB::table('kartu_stok')->where('id_bahan', $this->bahan)->orderBy('id')->get();
        $this->assertCount(2, $kartu);
        $this->assertSame(['IN', 'OUT'], $kartu->pluck('tipe')->all());
        $this->assertEquals((float) $kartu[0]->stok_sesudah, (float) $kartu[1]->stok_sebelum, 'rantai kartu stok putus');
        $this->assertSame(0, DB::table('kedatangan_bahan')->count());
    }

    public function test_stok_yang_sudah_terpakai_tidak_boleh_membuat_stok_minus_saat_kedatangan_dihapus(): void
    {
        $id = $this->kedatanganDivalidasi('SJ-2', 100);
        // 95 terpakai (mis. dijual langsung).
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()->subMinute()]);
        $this->post('/penjualan-barang/store', ['id_konsumen' => $kons, 'items' => [['tipe' => 'bahan', 'id_bahan' => $this->bahan, 'qty' => 95, 'harga' => 2000]]]);
        $this->assertEquals(5, $this->stok());
        $sebelum = $this->potret();

        $this->delete("/kedatangan-bahan/{$id}")->assertSessionHas('error');

        $this->assertSame($sebelum, $this->potret(), 'stok menjadi minus / data berubah padahal penghapusan seharusnya ditolak');
    }

    public function test_hapus_massal_mengikuti_aturan_yang_sama(): void
    {
        $a = $this->kedatanganDivalidasi('SJ-A', 10);
        $b = $this->kedatanganDivalidasi('SJ-B', 20);
        DB::table('bahan_baku')->where('id_bahan', $this->bahan)->update(['stok' => 25]); // 5 terpakai (disederhanakan)
        DB::table('kartu_stok')->insert(['id_bahan' => $this->bahan, 'tanggal' => now(), 'tipe' => 'OUT', 'qty' => 5, 'stok_sebelum' => 30, 'stok_sesudah' => 25, 'referensi' => 'UJI', 'keterangan' => 'pakai', 'created_at' => now(), 'updated_at' => now()]);
        $sebelum = $this->potret();

        // Menghapus keduanya akan butuh 30 padahal stok 25: seluruh penghapusan massal ditolak, tidak setengah jalan.
        $this->post('/kedatangan-bahan/bulk/delete', ['ids' => [$a, $b]])->assertSessionHas('error');
        $this->assertSame($sebelum, $this->potret());

        // Yang masih tertutup stok boleh dihapus.
        $this->post('/kedatangan-bahan/bulk/delete', ['ids' => [$a]])->assertSessionHasNoErrors();
        $this->assertEquals(15, $this->stok());
    }

    public function test_kedatangan_yang_sudah_difakturkan_ditolak_dengan_pesan_tanpa_perubahan(): void
    {
        $id = $this->kedatanganDivalidasi('SJ-F', 100);
        \App\Models\PembelianBahan::create(['id_kedatangan' => $id, 'no_faktur' => 'FAK-F', 'tgl_transaksi' => now()->toDateString(), 'tipe_pembelian' => 'Tempo',
            'status_pembayaran' => 'Hutang', 'grand_total' => 100000, 'jatuh_tempo_tgl' => now()->addDays(10)->toDateString()]);
        $sebelum = $this->potret();

        $this->delete("/kedatangan-bahan/{$id}")->assertRedirect()->assertSessionHas('error');

        $this->assertSame($sebelum, $this->potret());
    }

    public function test_hapus_massal_tidak_lagi_menghapus_faktur_diam_diam(): void
    {
        $id = $this->kedatanganDivalidasi('SJ-M', 100);
        $p = \App\Models\PembelianBahan::create(['id_kedatangan' => $id, 'no_faktur' => 'FAK-M', 'tgl_transaksi' => now()->toDateString(), 'tipe_pembelian' => 'Tempo',
            'status_pembayaran' => 'Hutang', 'grand_total' => 100000, 'jatuh_tempo_tgl' => now()->addDays(10)->toDateString()]);
        \App\Services\AccountingService::postPembelianBahan('FAK-M', 100000, 'Tempo');
        $sebelum = $this->potret();

        $this->post('/kedatangan-bahan/bulk/delete', ['ids' => [$id]])->assertSessionHas('error');

        $this->assertSame($sebelum, $this->potret());
        $this->assertFalse($p->fresh()->trashed(), 'faktur terhapus tanpa approval dan tanpa membalik jurnal');
        $this->assertEquals(100000, -(float) DB::table('jurnal_umum')->where('kode_akun', '2000')->sum(DB::raw('debet - kredit')), 'hutang tetap tercatat');
    }
}
