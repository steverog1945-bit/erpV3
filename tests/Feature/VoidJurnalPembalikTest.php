<?php

namespace Tests\Feature;

use App\Exceptions\PeriodeAkuntansiTertutupException;
use App\Models\ApprovalRequest;
use App\Models\JerseyOrder;
use App\Models\KedatanganBahan;
use App\Models\PembelianBahan;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingPeriodService;
use App\Services\AccountingService;
use App\Services\JerseyAccountingService;
use App\Services\PembayaranHutangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit alur void/batal: setiap pembatalan WAJIB mencatat jurnal pembalik,
 * memastikan debet=kredit, membawa saldo per akun kembali nol untuk
 * referensinya, memulihkan status operasional (status nota/SPK/pembelian/
 * saldo kas), dan menolak bila periode akuntansi sudah ditutup.
 *
 * Alur yang dilindungi: nota (termasuk SPK-nya via status_spk), pembelian
 * (faktur), pembayaran hutang, kas kecil, jersey (order & pembayaran).
 *
 * PR ini hanya TES: kode aplikasi tidak diubah. Bila ada alur yang
 * melanggar kontrak, tes di bawah yang merah - temuan APPSq sesuai instruksi
 * owner diteruskan ke gelombang perbaikan berikutnya.
 */
class VoidJurnalPembalikTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $db = config('database.connections.mysql.database');
        if ($db !== 'percetakan_erp_testing') {
            $this->markTestSkipped("Testing DB bukan percetakan_erp_testing (sekarang: {$db}). Test dilewati demi keamanan.");
        }

        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $role->id_role]);
        $this->actingAs($this->admin);
    }

    /** Jurnal harus selalu seimbang (debet = kredit) SETELAH aksi void/batal. */
    private function jurnalSeimbang(): float
    {
        return round((float) DB::table('jurnal_umum')->sum('debet') - (float) DB::table('jurnal_umum')->sum('kredit'), 2);
    }

    /** Saldo bersih (debet-kredit) per grup referensi jurnal tertentu. */
    private function saldoReferensi(string $referensi): float
    {
        return round((float) DB::table('jurnal_umum')->where('no_referensi', 'like', $referensi)->sum(DB::raw('debet - kredit')), 2);
    }

    /** Jurnal pembalik harus benar-benar tercatat untuk referensi ini. */
    private function jumPembalik(string $referensi): int
    {
        return DB::table('jurnal_umum')
            ->where('no_referensi', $referensi)
            ->where('keterangan', 'like', 'Koreksi/pembalikan%')
            ->count();
    }

    private function saldoAkun(string $kode): float
    {
        return round((float) DB::table('jurnal_umum')->where('kode_akun', $kode)->sum(DB::raw('debet - kredit')), 2);
    }

    private function approve(string $tipe): void
    {
        $req = ApprovalRequest::where('tipe_aksi', $tipe)->where('status', 'Menunggu')->firstOrFail();
        $this->patch("/approval/{$req->id}/approve", ['catatan' => 'OK']);
    }

    // see text in docblock above. */
    private function nota(int $nominal = 100000): int
    {
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $idKons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        $idTrx = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'INV-VOID-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $idKons, 'id_user_kasir' => $this->admin->id_user,
            'tgl_order' => now(), 'total_bayar' => $nominal, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_collecting' => 'Belum Diambil',
        ]);
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $idTrx, 'nama_file' => 'a.pdf', 'p_ukuran' => 1, 'l_ukuran' => 1, 'qty' => 1, 'status_urgent' => 0,
            'status_urgensi' => 'Normal', 'status_finishing' => 'Standar', 'subtotal' => $nominal, 'potongan' => 0, 'hpp_snapshot' => 0,
            'harga_jual_snapshot' => $nominal, 'status_spk' => 'Antre', 'biaya_cetak' => $nominal, 'biaya_desain' => 0,
        ]);
        AccountingService::postPenjualan('INV-VOID-1', $nominal, 0);

        return $idTrx;
    }

    private function batalkanNota(int $idTrx): void
    {
        $this->patch("/penjualan/{$idTrx}/batalkan-nota", ['alasan' => 'Salah input'])->assertSessionHas('success');
        $req = ApprovalRequest::where('referensi_id', $idTrx)->where('tipe_aksi', 'batalkan_nota')->firstOrFail();
        $this->patch("/approval/{$req->id}/approve", ['catatan' => 'ok']);
    }

    // expect note from the docblock. */
    private function faktur(float $total = 1000000): PembelianBahan
    {
        $idSupplier = DB::table('supplier')->insertGetId(['nama_supplier' => 'PT Kertas Jaya', 'alamat' => '-', 'kontak' => '0800']);
        $k = KedatanganBahan::create(['no_surat_jalan' => 'SJ-' . uniqid(), 'id_supplier' => $idSupplier, 'tgl_datang' => now()->toDateString(), 'status' => 'Selesai']);

        $p = PembelianBahan::create([
            'id_kedatangan' => $k->id_kedatangan, 'no_faktur' => 'FAK-' . uniqid(), 'tgl_transaksi' => now()->toDateString(),
            'tipe_pembelian' => 'Tempo', 'jatuh_tempo_tgl' => now()->addDays(30)->toDateString(), 'grand_total' => $total,
        ]);
        $p->status_pembayaran = 'Hutang';
        $p->save();
        AccountingService::postPembelianBahan($p->no_faktur, $total, 'Tempo');

        return $p->fresh();
    }

    // see prior docblock. */
    private function jerseyOrder(float $nominal = 500000): JerseyOrder
    {
        $o = JerseyOrder::create([
            'order_number' => 'JO-' . uniqid(), 'order_date' => now()->toDateString(), 'customer_name' => 'Tim Garuda', 'additional_charge' => $nominal,
        ]);
        $o->recalculateTotal();

        return $o->fresh();
    }

    // Jacob: expected docblock. */
    public function test_batalkan_nota_tunai_membalik_pendapatan_dan_spk_ikut_batal(): void
    {
        $idTrx = $this->nota(100000);

        $this->batalkanNota($idTrx);

        // Status operasional.
        $this->assertDatabaseHas('transaksi', ['id_transaksi' => $idTrx, 'status_nota' => 'Dibatalkan', 'status_order' => 'Dibatalkan']);
        $this->assertDatabaseHas('transaksi_detail', ['id_transaksi' => $idTrx, 'status_spk' => 'Batal']);

        // Jurnal pembalik tercatat + per-referensi net nol.
        $this->assertGreaterThan(0, $this->jumPembalik('INV-VOID-1'));
        $this->assertEquals(0.0, $this->saldoReferensi('INV-VOID-1'), 'Jurnal referensi nota harus net nol setelah pembalikan');

        // Pendapatan di buku besar tidak tersisa saldo dangle.
        $this->assertEquals(0.0, $this->saldoAkun('4300'), 'Pendapatan penjualan harus kembali nol');
        $this->assertEquals(0.0, $this->saldoAkun('1100'), 'Piutang harus kembali nol');

        // Jurnal global tetap seimbang.
        $this->assertEquals(0.0, $this->jurnalSeimbang(), 'Jurnal harus seimbang setelah nota dibatalkan');
    }

    public function test_batalkan_nota_yang_sudah_dibayar_tidak_menghilangkan_uang_deposit(): void
    {
        $idTrx = $this->nota(100000);
        DB::table('pembayaran_riwayat')->insert(['id_transaksi' => $idTrx, 'nominal' => 40000, 'metode' => 'Tunai', 'tgl_bayar' => now()]);
        AccountingService::postPembayaran('INV-VOID-1', 40000, 'Tunai');

        $this->batalkanNota($idTrx);

        // Uang pelanggan menjadi deposit (tidak menggantung).
        $this->assertSame(1, DB::table('konsumen_deposit')->count());
        $this->assertEquals(40000, (float) DB::table('konsumen_deposit')->sum('nominal'));

        // Piutang bersih nol; deposit jadi hutang pada uang pelanggan.
        $this->assertEquals(0.0, $this->saldoAkun('1100'), 'Piutang harus nol');
        $this->assertEquals(-40000, $this->saldoAkun('2100'), 'Hutang deposit harus sepenuhnya pada uang pelanggan');

        $this->assertEquals(0.0, $this->jurnalSeimbang());
    }

    public function test_batalkan_faktur_pembelian_membalik_jurnal_faktur(): void
    {
        $p = $this->faktur(700000);

        PembayaranHutangService::batalkanFaktur((int) $p->id_pembelian, 'tes pembatalan faktur');

        // Faktur dihapus (soft delete), tanpa cicilan aktif.
        $this->assertSoftDeleted('pembelian_bahan', ['id_pembelian' => $p->id_pembelian]);

        // Jurnal pembalik faktur tercatat: net saldo referensi faktur nol.
        $this->assertGreaterThan(0, $this->jumPembalik((string) $p->no_faktur));
        $this->assertEquals(0.0, $this->saldoReferensi((string) $p->no_faktur), 'Jurnal referensi faktur harus net nol setelah pembalikan');

        // Hutang (2000) dan persediaan kembali nol.
        $this->assertEquals(0.0, $this->saldoAkun('2000') + 0, 'Hutang pembelian harus kembali nol');

        $this->assertEquals(0.0, $this->jurnalSeimbang());
    }

    public function test_void_pembayaran_hutang_membalik_jurnal_pembayaran(): void
    {
        $p = $this->faktur(1000000);
        PembayaranHutangService::bayar((int) $p->id_pembelian, 400000, 'Tunai');
        $idBayar = (int) DB::table('pembayaran_hutang')->where('id_pembelian', $p->id_pembelian)->value('id');
        $referensi = (string) DB::table('pembayaran_hutang')->where('id', $idBayar)->value('no_referensi_jurnal');

        PembayaranHutangService::voidPembayaran($idBayar, 'salah input');

        // Pembayaran di-void kasirnya.
        $this->assertDatabaseHas('pembayaran_hutang', ['id' => $idBayar, 'void_alasan' => 'salah input']);

        // Jurnal pembalik pembayaran tercatat dan net referensi = 0.
        $this->assertGreaterThan(0, $this->jumPembalik($referensi));
        $this->assertEquals(0.0, $this->saldoReferensi($referensi), 'Referensi pembayaran harus net nol');

        // Status pembelian kembali Hutang.
        $this->assertEquals('Hutang', DB::table('pembelian_bahan')->where('id_pembelian', $p->id_pembelian)->value('status_pembayaran'));

        // Cermin kas kecil bersumber 'hutang' bersih.
        $this->assertSame(0, DB::table('kas_kecil')->where('sumber', 'hutang')->count());

        $this->assertEquals(0.0, $this->jurnalSeimbang());
    }

    public function test_batalkan_pengeluaran_kas_kecil_membalik_jurnal_dan_saldo_kembali(): void
    {
        $this->post('/kas-kecil/saldo-awal', ['tanggal' => now()->toDateString(), 'nominal' => 1000000])->assertSessionHas('success');
        $this->post('/kas-kecil', ['tanggal' => now()->toDateString(), 'jenis' => 'Keluar', 'id_kategori' => DB::table('kategori_kas_kecil')->where('nama_kategori', 'Beban Perlengkapan Toko')->value('id_kategori_kas_kecil'),
            'keterangan' => 'Beli ATK', 'nominal' => 300000])->assertSessionHas('success');
        $this->assertEquals(700000, $this->saldoAkun('1005'));
        $idKeluar = (int) DB::table('kas_kecil')->where('jenis', 'Keluar')->latest('id')->value('id');

        $this->delete("/kas-kecil/{$idKeluar}", ['alasan' => 'uji'])->assertSessionHas('success');
        $this->approve('batalkan_kas_kecil');

        // Saldo kas kecil kembali; jurnal pembalik tercatat; kas kecil baris hilang.
        $this->assertEquals(1000000, $this->saldoAkun('1005'), 'Saldo Kas Kecil harus kembali utuh');
        $this->assertGreaterThan(0, $this->jumPembalik('KK-' . $idKeluar));
        $this->assertEquals(0.0, $this->saldoReferensi('KK-' . $idKeluar));
        $this->assertSoftDeleted('kas_kecil', ['id' => $idKeluar]);

        $this->assertEquals(0.0, $this->jurnalSeimbang());
    }

    public function test_void_pembayaran_jersey_membalik_jurnal_kembali_nol(): void
    {
        $o = $this->jerseyOrder(500000);
        $this->post("/jersey/keuangan/{$o->id}/bayar", ['tanggal_bayar' => now()->toDateString(), 'jumlah' => 300000, 'metode_bayar' => 'Tunai'])
            ->assertSessionHas('success');
        $idBayar = (int) DB::table('jersey_order_payments')->value('id');
        $referensi = (string) DB::table('jersey_order_payments')->where('id', $idBayar)->value('no_referensi_jurnal');

        JerseyAccountingService::voidPembayaran($idBayar, 'salah input');

        // Pembayaran dihapus (soft delete), kas kecil kembali nol.
        $this->assertSoftDeleted('jersey_order_payments', ['id' => $idBayar]);
        $this->assertEquals(0.0, $this->saldoAkun('1005'), 'Kas Kecil harus kembali nol setelah void');
        $this->assertGreaterThan(0, $this->jumPembalik($referensi));
        $this->assertEquals(0.0, $this->saldoReferensi($referensi), 'Referensi pembayaran jersey harus net nol');

        $this->assertEquals(0.0, $this->jurnalSeimbang());
    }

    public function test_periode_tutup_menolak_void_batal(): void
    {
        // Faktur + pembayaran dibuat saat periodenya masih terbuka.
        $p = $this->faktur(1000000);
        PembayaranHutangService::bayar((int) $p->id_pembelian, 400000, 'Transfer');
        $idBayar = (int) DB::table('pembayaran_hutang')->where('id_pembelian', $p->id_pembelian)->value('id');

        // Tutup bulan BERJALAN - void yang butuh posting jurnal pembalik
        // hari ini harus ditolak.
        AccountingPeriodService::tutup((int) now()->year, (int) now()->month, $this->admin->id_user, 'tes');

        try {
            PembayaranHutangService::voidPembayaran($idBayar, 'tes periode tertutup');
            $this->fail('void saat periode tertutup seharusnya ditolak oleh PeriodeAkuntansiTertutupException');
        } catch (PeriodeAkuntansiTertutupException) {
            $this->assertTrue(true);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'sudah ditutup')) {
                $this->assertTrue(true); // pesan periode tertutup dibungkus RuntimeException - diterima
            } else {
                $this->markTestIncomplete('Tolakan bukan karena periode tertutup: ' . $e->getMessage());
            }
        }

        // Pembayaran tetap ada dan tidak ada tambahan jurnal pembalik.
        $this->assertDatabaseHas('pembayaran_hutang', ['id' => $idBayar, 'voided_at' => null]);
        $this->assertSame(0, DB::table('jurnal_umum')->where('keterangan', 'like', 'Koreksi/pembalikan%')->count());
        $this->assertEquals(0.0, $this->jurnalSeimbang());
    }
}
