<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\JerseyOrder;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TRANSAKSI & ROLLBACK: langkah terakhir (jurnal) dibuat gagal dengan menghapus akun tujuannya dari bag_akun
 * (insert jurnal_umum ditolak foreign key). Seluruh langkah sebelumnya (stok, kartu stok, pembayaran, status,
 * Kas Kecil, laci, deposit) harus ikut batal - tidak boleh ada keadaan setengah jadi.
 */
class RollbackAtomikTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $kons;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($this->admin);
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $this->kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Pelanggan Uji', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 1000000, 'status_lock' => 'open', 'created_at' => now()->subMinute()]);
    }

    /** Potret seluruh keadaan yang bisa tersentuh transaksi uang/stok. */
    private function potret(): array
    {
        $hitung = [];
        foreach (['transaksi', 'transaksi_detail', 'pembayaran_riwayat', 'jurnal_umum', 'kartu_stok', 'kas_kecil', 'kas_manual', 'konsumen_deposit',
                  'jersey_order_payments', 'pembayaran_hutang', 'waste_log', 'jasa_jahit_catat'] as $t) {
            $hitung[$t] = DB::table($t)->count();
        }

        return $hitung + [
            'stok' => DB::table('bahan_baku')->orderBy('id_bahan')->pluck('stok')->map(fn ($s) => (float) $s)->all(),
            'status_nota' => DB::table('transaksi')->orderBy('id_transaksi')->get(['status_nota', 'status_pembayaran'])->map(fn ($r) => (array) $r)->all(),
            'jersey' => DB::table('jersey_orders')->orderBy('id')->get(['deposit', 'status_pembayaran'])->map(fn ($r) => (array) $r)->all(),
            'pembelian' => DB::table('pembelian_bahan')->orderBy('id_pembelian')->get(['status_pembayaran'])->map(fn ($r) => (array) $r)->all(),
            'jahit_dibayar' => DB::table('jasa_jahit_catat')->whereNotNull('id_kas_kecil')->count(),
            'approval' => DB::table('approval_requests')->orderBy('id')->pluck('status')->all(),
        ];
    }

    private ?array $akunDihapus = null;

    private function gagalkanAkun(string $kode): void
    {
        $this->assertSame(0, DB::table('jurnal_umum')->where('kode_akun', $kode)->count(), "akun {$kode} sudah dipakai, tidak bisa dijadikan titik gagal");
        $this->akunDihapus = (array) DB::table('bag_akun')->where('kode_akun', $kode)->first();
        DB::table('bag_akun')->where('kode_akun', $kode)->delete();
    }

    /** Kontrol: setelah akun dipulihkan, permintaan yang sama harus BERHASIL mengubah data (bukan ditolak validasi). */
    private function kontrolBerhasil(array $sebelum, \Closure $ulangi): void
    {
        DB::table('bag_akun')->insert($this->akunDihapus);
        $ulangi();
        $this->assertNotSame($sebelum, $this->potret(), 'kontrol: permintaan yang sama tanpa kegagalan tidak mengubah apa pun, tes tidak membuktikan rollback');
        $this->assertEquals((float) DB::table('jurnal_umum')->sum('debet'), (float) DB::table('jurnal_umum')->sum('kredit'));
    }

    private function bahan(float $stok = 50): int
    {
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin', 'tipe_mesin' => 'Digital']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kategori', 'kode_kategori' => 'KTG']);

        return DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'BHN-RB', 'nama_bahan' => 'Flexi', 'stok' => $stok, 'stok_minimal' => 0, 'harga_rata_rata' => 10000, 'nilai_konversi' => 1,
            'id_mesin' => $mesin, 'id_kategori' => $kat, 'satuan_stok' => 'm2', 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function nota(float $total = 100000): int
    {
        $id = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'INV-RB-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $this->kons, 'id_user_kasir' => $this->admin->id_user,
            'tgl_order' => now(), 'total_bayar' => $total, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir', 'status_collecting' => 'Belum Diambil',
        ]);
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $id, 'nama_file' => 'a.pdf', 'p_ukuran' => 1, 'l_ukuran' => 1, 'qty' => 1, 'status_urgent' => 0,
            'status_urgensi' => 'Normal', 'status_finishing' => 'Standar', 'subtotal' => $total, 'potongan' => 0, 'hpp_snapshot' => 0,
            'harga_jual_snapshot' => $total, 'status_spk' => 'Antre', 'biaya_cetak' => $total, 'biaya_desain' => 0,
        ]);
        AccountingService::postPenjualan('INV-RB-1', $total, 0);

        return $id;
    }

    public function test_pembayaran_kasir_batal_seluruhnya_bila_jurnal_gagal(): void
    {
        $id = $this->nota();
        $this->gagalkanAkun('1000');
        $sebelum = $this->potret();
        $aksi = fn () => $this->post('/kasir/proses-bayar', ['id_transaksi' => $id, 'jumlah_bayar' => '100.000', 'metode_pembayaran' => 'Tunai', 'submit_id' => 'rb-' . uniqid()]);

        $aksi();

        $this->assertSame($sebelum, $this->potret(), 'pembayaran tersimpan tanpa jurnal');
        $this->kontrolBerhasil($sebelum, $aksi);
    }

    public function test_penjualan_bahan_batal_seluruhnya_bila_jurnal_hpp_gagal(): void
    {
        $bahan = $this->bahan(50);
        $this->gagalkanAkun('5000');
        $sebelum = $this->potret();
        $aksi = fn () => $this->post('/penjualan-barang/store', ['id_konsumen' => $this->kons, 'items' => [['tipe' => 'bahan', 'id_bahan' => $bahan, 'qty' => 3, 'harga' => 15000]]]);

        $aksi();

        $this->assertSame($sebelum, $this->potret(), 'stok berkurang tanpa jurnal');
        $this->kontrolBerhasil($sebelum, $aksi);
    }

    public function test_pembayaran_jersey_batal_seluruhnya_bila_jurnal_gagal(): void
    {
        $o = JerseyOrder::create(['order_number' => 'JO-RB-1', 'order_date' => now()->toDateString(), 'customer_name' => 'Tim', 'additional_charge' => 200000]);
        $o->recalculateTotal();
        $this->gagalkanAkun('1005');
        $sebelum = $this->potret();
        $aksi = fn () => $this->post("/jersey/keuangan/{$o->id}/bayar", ['tanggal_bayar' => now()->toDateString(), 'jumlah' => 100000, 'metode_bayar' => 'Tunai']);

        $aksi();

        $this->assertSame($sebelum, $this->potret(), 'pembayaran jersey / Kas Kecil tersimpan tanpa jurnal');
        $this->kontrolBerhasil($sebelum, $aksi);
    }

    public function test_waste_batal_seluruhnya_bila_jurnal_gagal(): void
    {
        $this->bahan(50);
        $this->gagalkanAkun('5240');
        $sebelum = $this->potret();
        $aksi = fn () => $this->post('/waste', ['id_bahan' => DB::table('bahan_baku')->value('id_bahan'), 'qty' => 3, 'jenis' => 'Normal', 'alasan' => 'Sisa potongan']);

        $aksi();

        $this->assertSame($sebelum, $this->potret(), 'stok berkurang tanpa jurnal beban');
        $this->kontrolBerhasil($sebelum, $aksi);
    }

    public function test_pengeluaran_kas_kecil_batal_bila_jurnal_gagal(): void
    {
        AccountingService::postKasKecilMasuk('KK-SALDO', '3000', 500000);
        $kat = DB::table('kategori_kas_kecil')->insertGetId(['nama_kategori' => 'ATK Uji', 'jenis' => 'Keluar', 'kode_akun' => '5160']);
        $this->gagalkanAkun('5160');
        $sebelum = $this->potret();
        $aksi = fn () => $this->post('/kas-kecil', ['tanggal' => now()->toDateString(), 'jenis' => 'Keluar', 'id_kategori' => $kat, 'keterangan' => 'Kertas', 'nominal' => 50000]);

        $aksi();

        $this->assertSame($sebelum, $this->potret(), 'catatan Kas Kecil tersimpan tanpa jurnal');
        $this->kontrolBerhasil($sebelum, $aksi);
    }

    public function test_setoran_deposit_tunai_batal_seluruhnya_bila_jurnal_gagal(): void
    {
        $this->gagalkanAkun('2100');
        $sebelum = $this->potret();
        $aksi = fn () => $this->post('/konsumen-deposit', ['id_konsumen' => $this->kons, 'nominal' => 50000, 'metode_bayar' => 'Tunai', 'tgl_deposit' => now()->toDateString()]);

        $aksi();

        $this->assertSame($sebelum, $this->potret(), 'deposit / cermin laci tersimpan tanpa jurnal');
        $this->kontrolBerhasil($sebelum, $aksi);
    }

    public function test_pembatalan_nota_bayar_sebagian_batal_seluruhnya_bila_jurnal_gagal(): void
    {
        $id = $this->nota();
        DB::table('pembayaran_riwayat')->insert(['id_transaksi' => $id, 'nominal' => 40000, 'metode' => 'Transfer', 'tgl_bayar' => now()]);
        AccountingService::postPembayaran('INV-RB-1', 40000, 'Transfer');
        DB::table('transaksi')->where('id_transaksi', $id)->update(['status_pembayaran' => 'DP']);

        $this->patch("/penjualan/{$id}/batalkan-nota", ['alasan' => 'Salah input']);
        $req = ApprovalRequest::where('referensi_id', $id)->firstOrFail();
        $this->gagalkanAkun('2100');
        $sebelum = $this->potret();
        $aksi = fn () => $this->patch("/approval/{$req->id}/approve", ['catatan' => 'ok']);

        $aksi();

        $this->assertSame($sebelum, $this->potret(), 'nota batal / deposit / approval berubah tanpa jurnal');
        $this->kontrolBerhasil($sebelum, $aksi);
    }

    public function test_pelunasan_hutang_batal_seluruhnya_bila_jurnal_gagal(): void
    {
        $sup = DB::table('supplier')->insertGetId(['nama_supplier' => 'PT Uji', 'alamat' => '-', 'kontak' => '0']);
        $k = \App\Models\KedatanganBahan::create(['no_surat_jalan' => 'SJ-RB', 'id_supplier' => $sup, 'tgl_datang' => now()->toDateString(), 'status' => 'Selesai']);
        $p = \App\Models\PembelianBahan::create([
            'id_kedatangan' => $k->id_kedatangan, 'no_faktur' => 'FAK-RB', 'tgl_transaksi' => now()->toDateString(), 'tipe_pembelian' => 'Tempo',
            'status_pembayaran' => 'Hutang', 'grand_total' => 500000, 'jatuh_tempo_tgl' => now()->addDays(10)->toDateString(),
        ]);
        AccountingService::postPembelianBahan('FAK-RB', 500000, 'Tempo');
        $this->gagalkanAkun('3100');
        $sebelum = $this->potret();
        $aksi = fn () => $this->patch("/pembelian-bahan/pelunasan/{$p->id_pembelian}", ['nominal' => 200000, 'metode' => 'Transfer', 'tanggal' => now()->toDateString()]);

        $aksi();

        $this->assertSame($sebelum, $this->potret(), 'pembayaran hutang tersimpan tanpa jurnal');
        $this->kontrolBerhasil($sebelum, $aksi);
    }

    public function test_bayar_penjahit_batal_seluruhnya_bila_jurnal_gagal(): void
    {
        AccountingService::postKasKecilMasuk('KK-SALDO', '3000', 500000);
        $pj = DB::table('penjahit')->insertGetId(['nama' => 'Bu Sri', 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
        $tarif = DB::table('jasa_jahit')->insertGetId(['nama_jahit' => 'Keliling', 'harga_borongan' => 2500, 'created_at' => now(), 'updated_at' => now()]);
        $c = DB::table('jasa_jahit_catat')->insertGetId(['id_jasa_jahit' => $tarif, 'id_penjahit' => $pj, 'qty' => 4, 'harga_snapshot' => 2500, 'total' => 10000, 'tgl_catat' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        $this->gagalkanAkun('5220');
        $sebelum = $this->potret();
        $aksi = fn () => $this->post('/jasa-jahit/bayar', ['id_penjahit' => $pj, 'catat' => [$c]]);

        $aksi();

        $this->assertSame($sebelum, $this->potret(), 'upah jahit ditandai dibayar tanpa jurnal');
        $this->kontrolBerhasil($sebelum, $aksi);
    }

    private function saldo(string $kode): float
    {
        return round((float) DB::table('jurnal_umum')->where('kode_akun', $kode)->sum(DB::raw('debet - kredit')), 2);
    }

    /** ALUR LINTAS MODUL: Penjualan dan Jasa -> jurnal maju -> bayar sebagian -> batal ditolak aman -> lunas -> rekonsiliasi. */
    public function test_penjualan_dan_jasa_menjurnal_piutang_pendapatan_hpp_lalu_lunas_dan_rekonsiliasi_lulus(): void
    {
        $bahan = $this->bahan(50);
        // Stok awal 50 x Rp 10.000 tercatat di Persediaan, seperti bila bahan masuk lewat pembelian.
        AccountingService::postSaldoAwalPersediaan('SA-BHN-RB', 'Saldo awal Flexi', 500000);
        $idJasa = DB::table('jasa')->insertGetId(['nama_jasa' => 'Press', 'harga_default' => 20000]);

        $this->post('/penjualan-barang/store', ['id_konsumen' => $this->kons, 'items' => [
            ['tipe' => 'bahan', 'id_bahan' => $bahan, 'qty' => 3, 'harga' => 15000],
            ['tipe' => 'jasa', 'id_jasa' => $idJasa, 'qty' => 1, 'harga' => 20000],
        ]])->assertSessionHasNoErrors();
        $t = DB::table('transaksi')->first();

        // Maju: piutang, pendapatan barang & jasa, HPP bahan.
        $this->assertEquals(65000, $this->saldo('1100'));
        $this->assertEquals(-45000, $this->saldo('4000'));
        $this->assertEquals(-20000, $this->saldo('4100'));
        $this->assertEquals(30000, $this->saldo('5000'));
        $this->assertEquals(470000, $this->saldo('1200'), 'persediaan berkurang sebesar HPP');
        $this->assertEquals(47.0, (float) DB::table('bahan_baku')->where('id_bahan', $bahan)->value('stok'));

        // Bayar sebagian lewat kasir: piutang berkurang, tidak minus.
        $this->post('/kasir/proses-bayar', ['id_transaksi' => $t->id_transaksi, 'jumlah_bayar' => '25.000', 'metode_pembayaran' => 'Transfer', 'submit_id' => 'pb-1'])
            ->assertSessionHas('success');
        $this->assertEquals(40000, $this->saldo('1100'));

        // Batal nota Penjualan dan Jasa kini boleh diajukan (status Selesai item langsung tidak mengunci), tetapi hanya
        // pengajuan: stok dan jurnal tidak berubah sebelum Administrator menyetujui.
        $sebelum = $this->potret();
        $this->patch("/penjualan/{$t->id_transaksi}/batalkan-nota", ['alasan' => 'Salah input'])->assertSessionHas('success');
        $this->assertSame(array_diff_key($sebelum, ['approval' => 1]), array_diff_key($this->potret(), ['approval' => 1]));

        // Lunasi sisanya: piutang nol, dan rekonsiliasi pembukuan lulus.
        $this->post('/kasir/proses-bayar', ['id_transaksi' => $t->id_transaksi, 'jumlah_bayar' => '40.000', 'metode_pembayaran' => 'Tunai', 'submit_id' => 'pb-2'])
            ->assertSessionHas('success');
        $this->assertEquals(0, $this->saldo('1100'));
        $this->assertSame('Lunas', DB::table('transaksi')->where('id_transaksi', $t->id_transaksi)->value('status_pembayaran'));
        \Illuminate\Support\Facades\Artisan::call('audit:integritas', ['--json' => true]);
        $this->assertSame([], json_decode(\Illuminate\Support\Facades\Artisan::output(), true)['temuan']);
        $this->assertEquals((float) DB::table('jurnal_umum')->sum('debet'), (float) DB::table('jurnal_umum')->sum('kredit'));
    }
}
