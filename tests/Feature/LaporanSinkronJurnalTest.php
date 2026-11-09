<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\DashboardEksekutifService as Analisis;
use App\Services\PembayaranHutangService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Angka laporan harus sama dengan jurnal dan sumber datanya: faktur batal tidak dihitung, hutang = sisa (bukan total
 * faktur), diskon nota mengurangi omzet, pembayaran Deposit bukan uang masuk, dan biaya yang dibayar pemilik bukan
 * pengeluaran kas.
 */
class LaporanSinkronJurnalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($this->admin);
    }

    private function kedatangan(string $sj): int
    {
        $sup = DB::table('supplier')->insertGetId(['nama_supplier' => 'PT ' . $sj, 'kontak' => '-']);
        $gudang = DB::table('gudang')->insertGetId(['nama_gudang' => 'G ' . $sj]);

        return DB::table('kedatangan_bahan')->insertGetId(['no_surat_jalan' => $sj, 'id_supplier' => $sup, 'id_gudang' => $gudang, 'tgl_datang' => now()->toDateString(), 'status' => 'Selesai']);
    }

    public function test_belanja_bahan_tanpa_faktur_batal_dan_hutang_adalah_sisa(): void
    {
        $a = DB::table('pembelian_bahan')->insertGetId(['id_kedatangan' => $this->kedatangan('SJ-A'), 'no_faktur' => 'FAK-A', 'tgl_transaksi' => now()->toDateString(),
            'tipe_pembelian' => 'Tempo', 'status_pembayaran' => 'Hutang', 'grand_total' => 1000000, 'jatuh_tempo_tgl' => now()->addDays(30)->toDateString()]);
        PembayaranHutangService::bayar($a, 600000, 'Transfer');
        DB::table('pembelian_bahan')->insert(['id_kedatangan' => $this->kedatangan('SJ-B'), 'no_faktur' => 'FAK-B', 'tgl_transaksi' => now()->toDateString(),
            'tipe_pembelian' => 'Tempo', 'status_pembayaran' => 'Hutang', 'grand_total' => 500000, 'jatuh_tempo_tgl' => now()->addDays(30)->toDateString(), 'deleted_at' => now()]);

        $hari = now()->toDateString();
        $this->get("/laporan/belanja-bahan?tgl_dari={$hari}&tgl_sampai={$hari}")->assertOk()
            ->assertViewHas('totalBelanja', fn ($v) => (float) $v === 1000000.0)
            ->assertViewHas('totalHutang', fn ($v) => (float) $v === 400000.0)
            ->assertViewHas('jumlahFaktur', 1);
    }

    private function konsumen(): int
    {
        $g = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);

        return DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Pelanggan Uji', 'telepon' => '0800', 'alamat' => '-', 'id_golongan' => $g]);
    }

    private function nota(int $k, string $nomor, float $total, string $status, ?float $bayar = null, string $metode = 'Tunai', $waktu = null): int
    {
        $waktu ??= now();
        $t = DB::table('transaksi')->insertGetId(['nomor_nota' => $nomor, 'tipe_order' => 'Reguler', 'id_konsumen' => $k, 'id_user_kasir' => $this->admin->id_user,
            'tgl_order' => $waktu, 'total_bayar' => $total, 'status_pembayaran' => $status, 'status_nota' => 'Aktif']);
        if ($bayar) {
            DB::table('pembayaran_riwayat')->insert(['id_transaksi' => $t, 'nominal' => $bayar, 'metode' => $metode, 'tgl_bayar' => $waktu]);
        }

        return $t;
    }

    /** Nota dua item (600.000 + 400.000) dengan diskon nota 100.000: omzet laporan harus 900.000, bukan 1.000.000. */
    private function notaDiskon(): void
    {
        $k = $this->konsumen();
        $t = DB::table('transaksi')->insertGetId(['nomor_nota' => 'N-DISK', 'tipe_order' => 'Reguler', 'id_konsumen' => $k, 'id_user_kasir' => $this->admin->id_user,
            'tgl_order' => now(), 'total_bayar' => 900000, 'diskon' => 100000, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif']);
        foreach (['Spanduk' => [600000, 200000], 'Stiker' => [400000, 100000]] as $nama => [$subtotal, $hpp]) {
            DB::table('transaksi_detail')->insert(['id_transaksi' => $t, 'nama_file' => $nama, 'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 1,
                'subtotal' => $subtotal, 'hpp_snapshot' => $hpp, 'status_spk' => 'Antre']);
        }
    }

    public function test_omzet_laporan_dikurangi_diskon_nota(): void
    {
        $this->notaDiskon();
        $hari = now()->toDateString();

        $this->get("/laporan/job-costing?tgl_dari={$hari}&tgl_sampai={$hari}")->assertOk()
            ->assertViewHas('total', fn ($v) => abs((float) $v['omzet'] - 900000) < 0.01);
        $this->get("/laporan/hpp?tgl_dari={$hari}&tgl_sampai={$hari}")->assertOk()
            ->assertViewHas('totalOmzet', fn ($v) => abs((float) $v - 900000) < 0.01)
            ->assertViewHas('breakdownDivisi', fn ($v) => abs((float) $v->sum('total_omzet') - 900000) < 0.01);

        $dari = now()->startOfDay();
        $sampai = now()->endOfDay();
        $this->assertEqualsWithDelta(900000, (float) Analisis::topProduk($dari, $sampai)->sum('omzet'), 0.01);
        $per = Analisis::profitabilitas('produk', $dari, $sampai)->keyBy('nama');
        $this->assertEqualsWithDelta(540000, (float) $per['Spanduk']->omzet, 0.01);
        $this->assertEqualsWithDelta(360000, (float) $per['Stiker']->omzet, 0.01);
    }

    public function test_rekap_closing_kasir_piutang_setelah_diskon(): void
    {
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'saldo_akhir' => 0,
            'status_lock' => 'open', 'created_at' => now()->startOfDay(), 'updated_at' => now()->startOfDay()]);
        $this->notaDiskon();

        $this->get('/kasir/closing-preview')->assertOk()
            ->assertViewHas('totalNotaOrder', fn ($v) => abs((float) $v - 900000) < 0.01)
            ->assertViewHas('piutangNotaOrder', fn ($v) => abs((float) $v - 900000) < 0.01);
    }

    /** Uang masuk = pembayaran nota tunai/transfer + setoran deposit baru; pemakaian saldo deposit dan kredit nota batal bukan uang baru. */
    public function test_kas_masuk_tanpa_deposit_dan_piutang_adalah_sisa(): void
    {
        $k = $this->konsumen();
        $this->nota($k, 'N-DP', 1000000, 'DP', 400000);
        $this->nota($k, 'N-DEP', 300000, 'Lunas', 300000, 'Deposit');
        DB::table('konsumen_deposit')->insert([
            ['id_konsumen' => $k, 'tipe' => 'Setoran', 'nominal' => 500000, 'metode_bayar' => 'Tunai', 'tgl_deposit' => now()->toDateString()],
            ['id_konsumen' => $k, 'tipe' => 'Setoran', 'nominal' => 200000, 'metode_bayar' => 'Kredit Nota Batal', 'tgl_deposit' => now()->toDateString()],
        ]);

        $this->get('/dashboard')->assertOk()
            ->assertViewHas('piutang', fn ($v) => abs((float) $v - 600000) < 0.01)
            ->assertViewHas('kasMasukHariIni', fn ($v) => abs((float) $v - 900000) < 0.01)
            ->assertViewHas('tren', fn ($v) => abs((float) $v->last()['kas'] - 900000) < 0.01);

        $hari = now()->toDateString();
        $this->get("/akuntansi/perbandingan?awal={$hari}&akhir={$hari}")->assertOk()
            ->assertViewHas('uangDiterima', fn ($v) => abs((float) $v - 400000) < 0.01)
            ->assertViewHas('setoranDeposit', fn ($v) => abs((float) $v - 500000) < 0.01);

        $k = \App\Services\AuditOwnerService::bulan(now())['keuangan'];
        $this->assertEqualsWithDelta(400000, $k['kas_masuk_total'], 0.01);
        $this->assertEqualsWithDelta(500000, $k['setoran_deposit'], 0.01);
        $this->assertEqualsWithDelta(300000, $k['pemakaian_deposit'], 0.01);
    }

    /** Hutang supplier dan biaya "Dibayar Pemilik" memakai uang pemilik (3100/3200), bukan kas usaha. */
    public function test_kas_keluar_tanpa_dana_pemilik(): void
    {
        $bulanLalu = now()->subMonthNoOverflow()->startOfMonth()->addDays(4);
        $a = DB::table('pembelian_bahan')->insertGetId(['id_kedatangan' => $this->kedatangan('SJ-P'), 'no_faktur' => 'FAK-P', 'tgl_transaksi' => $bulanLalu->toDateString(),
            'tipe_pembelian' => 'Tempo', 'status_pembayaran' => 'Hutang', 'grand_total' => 1000000, 'jatuh_tempo_tgl' => $bulanLalu->copy()->addDays(30)->toDateString()]);
        PembayaranHutangService::bayar($a, 600000, 'Transfer', $bulanLalu->toDateString());
        $kat = DB::table('kategori_kas_kecil')->insertGetId(['nama_kategori' => 'Uji Beban', 'jenis' => 'Keluar', 'kode_akun' => '5100']);
        DB::table('kas_kecil')->insert([
            ['tanggal' => $bulanLalu->toDateString(), 'id_kategori' => $kat, 'keterangan' => 'kertas', 'nominal' => 90000, 'jenis' => 'Keluar', 'metode' => 'Tunai'],
            ['tanggal' => $bulanLalu->toDateString(), 'id_kategori' => $kat, 'keterangan' => 'token', 'nominal' => 250000, 'jenis' => 'Keluar', 'metode' => 'Dibayar Pemilik'],
        ]);

        $est = Analisis::estimasiBulanDepan();
        $this->assertEqualsWithDelta(30000, $est['kas_keluar'], 0.01);

        $k = \App\Services\AuditOwnerService::bulan($bulanLalu)['keuangan'];
        $this->assertEqualsWithDelta(90000, $k['kas_kecil_total'], 0.01);
        $this->assertEqualsWithDelta(250000, $k['kas_kecil_pemilik'], 0.01);
        $this->assertEqualsWithDelta(600000, $k['pembayaran_hutang'], 0.01);
    }

    /** Ringkasan produksi tidak menghitung nota dibatalkan dan SPK Batal; detailnya tetap tampil sebagai jejak. */
    public function test_ringkasan_produksi_tanpa_nota_batal_dan_spk_batal(): void
    {
        $k = $this->konsumen();
        $produk = DB::table('produk')->insertGetId(['nama_produk' => 'Kartu Nama', 'metode_hitung' => 'quantity', 'status' => 'Aktif']);
        $aktif = $this->nota($k, 'N-AKTIF', 100000, 'Belum Bayar');
        $batal = $this->nota($k, 'N-BATAL', 100000, 'Belum Bayar');
        DB::table('transaksi')->where('id_transaksi', $batal)->update(['status_nota' => 'Dibatalkan']);
        foreach ([[$aktif, 5, 'Selesai'], [$aktif, 7, 'Batal'], [$batal, 11, 'Antre']] as [$t, $qty, $status]) {
            DB::table('transaksi_detail')->insert(['id_transaksi' => $t, 'id_produk' => $produk, 'nama_file' => 'f', 'p_ukuran' => 0, 'l_ukuran' => 0,
                'qty' => $qty, 'qty_cetak' => $qty, 'subtotal' => 1, 'status_spk' => $status]);
        }

        $hari = now()->toDateString();
        $this->get("/laporan/produksi?tgl_dari={$hari}&tgl_sampai={$hari}")->assertOk()
            ->assertViewHas('totalOrderKeseluruhan', fn ($v) => (float) $v === 5.0)
            ->assertViewHas('totalCetakKeseluruhan', fn ($v) => (float) $v === 5.0);
    }
}
