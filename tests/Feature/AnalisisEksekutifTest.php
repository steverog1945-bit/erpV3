<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\ApprovalService;
use App\Services\DashboardEksekutifService as Analisis;
use App\Services\WasteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalisisEksekutifTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idBahan;
    private int $produkA;
    private int $produkB;
    private int $konsumenA;
    private int $konsumenB;
    private int $idDetailA;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $role->id_role]);
        $this->actingAs($this->admin);

        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin Digital', 'tipe_mesin' => 'Digital']);
        $idKategori = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kategori']);
        $this->idBahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'BHN-1', 'nama_bahan' => 'Flexi', 'stok' => 100, 'stok_minimal' => 5, 'harga_rata_rata' => 10000, 'nilai_konversi' => 1,
            'id_mesin' => $idMesin, 'id_kategori' => $idKategori, 'satuan_stok' => 'm2', 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->produkA = DB::table('produk')->insertGetId(['nama_produk' => 'Banner', 'metode_hitung' => 'ukuran', 'id_mesin' => $idMesin]);
        $this->produkB = DB::table('produk')->insertGetId(['nama_produk' => 'Stiker', 'metode_hitung' => 'quantity']);

        $idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $this->konsumenA = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Andi', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $idGol]);
        $this->konsumenB = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Budi', 'telepon' => '2', 'alamat' => '-', 'id_golongan' => $idGol]);

        // Nota aktif 1: Andi, Banner, omzet 500rb, HPP 150rb. Nota aktif 2: Budi, Stiker, omzet 300rb, HPP 90rb.
        $this->idDetailA = $this->nota('INV-A', $this->konsumenA, $this->produkA, 500000, 150000);
        $this->nota('INV-B', $this->konsumenB, $this->produkB, 300000, 90000);
        // Nota dibatalkan: tidak boleh ikut hitungan apa pun.
        $this->nota('INV-X', $this->konsumenA, $this->produkA, 999000, 500000, 'Dibatalkan');
    }

    private function nota(string $nomor, int $idKonsumen, int $idProduk, float $total, float $hpp, string $status = 'Aktif'): int
    {
        $idT = DB::table('transaksi')->insertGetId([
            'nomor_nota' => $nomor, 'tipe_order' => 'Reguler', 'id_konsumen' => $idKonsumen, 'id_user_kasir' => $this->admin->id_user,
            'tgl_order' => now(), 'total_bayar' => $total, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => $status, 'status_collecting' => 'Belum Diambil',
        ]);

        return DB::table('transaksi_detail')->insertGetId([
            'id_transaksi' => $idT, 'id_produk' => $idProduk, 'nama_file' => $nomor . '.pdf', 'p_ukuran' => 100, 'l_ukuran' => 100, 'qty' => 2,
            'status_urgent' => 0, 'status_urgensi' => 'Normal', 'status_finishing' => 'Standar', 'subtotal' => $total, 'potongan' => 0,
            'hpp_snapshot' => $hpp, 'harga_jual_snapshot' => $total / 2, 'status_spk' => 'Selesai', 'biaya_cetak' => $total, 'biaya_desain' => 0,
        ]);
    }

    private function periode(): array
    {
        return [now()->startOfMonth(), now()->endOfDay()];
    }

    public function test_ringkasan_menghitung_omzet_biaya_aktual_laba_dan_saldo_buku_besar(): void
    {
        // Rework selesai 20rb + waste normal 1 x 10rb.
        $idQc = DB::table('qc_inspeksi')->insertGetId([
            'nomor' => 'QC-T', 'id_detail' => $this->idDetailA, 'tanggal' => now()->toDateString(), 'qty_diperiksa' => 2, 'qty_lolos' => 1, 'qty_reject' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('rework')->insert([
            'nomor' => 'RW-T', 'id_qc_inspeksi' => $idQc, 'id_detail' => $this->idDetailA, 'qty_rework' => 1, 'status' => 'Selesai',
            'biaya_material' => 20000, 'selesai_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        WasteService::catat($this->idBahan, 1, 'Normal', 'Sisa potongan');
        AccountingService::postJournal(now()->toDateString(), 'UJI-1', 'Uji', [['kode_akun' => '1000', 'debet' => 700000], ['kode_akun' => '4000', 'kredit' => 700000]]);

        [$dari, $sampai] = $this->periode();
        $r = Analisis::ringkasan($dari, $sampai);

        $this->assertEquals(800000.0, $r['omzet']);
        $this->assertEquals(2, $r['jumlah_nota']);
        $this->assertEquals([240000.0, 20000.0, 10000.0, 270000.0, 530000.0, 66.3], [$r['hpp'], $r['rework'], $r['waste'], $r['biaya_aktual'], $r['laba_kotor'], $r['margin']]);
        // Kas: +700rb (uji) - 0; waste menjurnal 5200/1200, tidak menyentuh kas.
        $this->assertEquals(700000.0, $r['saldo_kas']);
    }

    public function test_tren_enam_bulan_dan_top_pelanggan_produk(): void
    {
        $tren = Analisis::trenOmzet(6);
        $this->assertCount(6, $tren);
        $this->assertEquals(now()->format('Y-m'), end($tren)['bulan']);
        $this->assertEquals(800000.0, end($tren)['omzet']);

        [$dari, $sampai] = $this->periode();
        $this->assertEquals(['Andi', 'Budi'], Analisis::topPelanggan($dari, $sampai)->pluck('nama')->all());
        $this->assertEquals(['Banner', 'Stiker'], Analisis::topProduk($dari, $sampai)->pluck('nama')->all());
    }

    public function test_profitabilitas_per_produk_pelanggan_dan_mesin(): void
    {
        [$dari, $sampai] = $this->periode();

        $produk = Analisis::profitabilitas('produk', $dari, $sampai)->keyBy('nama');
        $this->assertEquals([500000.0, 150000.0, 350000.0, 70.0], [(float) $produk['Banner']->omzet, (float) $produk['Banner']->hpp, $produk['Banner']->laba, $produk['Banner']->margin]);

        $pelanggan = Analisis::profitabilitas('pelanggan', $dari, $sampai)->keyBy('nama');
        $this->assertEquals(210000.0, $pelanggan['Budi']->laba);

        $mesin = Analisis::profitabilitas('mesin', $dari, $sampai)->keyBy('nama');
        $this->assertEquals(350000.0, $mesin['Mesin Digital']->laba);
        $this->assertEquals(210000.0, $mesin['Jasa / lainnya']->laba, 'produk tanpa mesin dikelompokkan terpisah');
    }

    public function test_pemakaian_bahan_memisahkan_order_rework_dan_waste(): void
    {
        foreach ([['INV-A', 60], ['RW-1', 3], ['WS-1', 2]] as [$ref, $qty]) {
            DB::table('kartu_stok')->insert([
                'id_bahan' => $this->idBahan, 'tanggal' => now(), 'tipe' => 'OUT', 'qty' => $qty, 'stok_sebelum' => 100, 'stok_sesudah' => 100 - $qty,
                'referensi' => $ref, 'keterangan' => '-', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('kartu_stok')->insert([
            'id_bahan' => $this->idBahan, 'tanggal' => now(), 'tipe' => 'IN', 'qty' => 999, 'stok_sebelum' => 0, 'stok_sesudah' => 999,
            'referensi' => 'SJ-1', 'keterangan' => '-', 'created_at' => now(), 'updated_at' => now(),
        ]);

        [$dari, $sampai] = $this->periode();
        $r = Analisis::pemakaianBahan($dari, $sampai)->first();

        $this->assertEquals([60.0, 3.0, 2.0, 65.0, 7.7, 50000.0], [(float) $r->qty_pemakaian, (float) $r->qty_rework, (float) $r->qty_waste, (float) $r->qty_total, $r->persen_susut, $r->nilai_susut]);
    }

    public function test_peringatan_hanya_menampilkan_yang_bernilai(): void
    {
        $this->assertSame([], Analisis::peringatan());

        ApprovalService::ajukan('uji', null, null, [], 'Uji', null);
        DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->update(['stok' => 1]);

        $labels = collect(Analisis::peringatan())->pluck('label')->all();
        $this->assertContains('Pengajuan menunggu persetujuan', $labels);
        $this->assertContains('Bahan di bawah stok minimal', $labels);
        $this->assertCount(2, $labels);
    }

    public function test_estimasi_bulan_depan_memakai_rata_rata_tiga_bulan_penuh(): void
    {
        $bulanLalu = now()->startOfMonth()->subMonth()->addDays(5);
        $this->nota('INV-LAMA', $this->konsumenA, $this->produkA, 900000, 0);
        DB::table('transaksi')->where('nomor_nota', 'INV-LAMA')->update(['tgl_order' => $bulanLalu]);

        $e = Analisis::estimasiBulanDepan();

        $this->assertEquals(300000.0, $e['omzet']); // 900rb / 3; bulan berjalan tidak dihitung
    }

    public function test_halaman_render_dan_dashboard_khusus_administrator(): void
    {
        $this->get('/eksekutif')->assertOk()->assertSee('Dashboard Eksekutif')->assertSee('Estimasi Bulan Depan')->assertSee('Andi');
        $this->get('/laporan/profitabilitas?dimensi=pelanggan')->assertOk()->assertSee('Budi');
        $this->get('/laporan/profitabilitas?dimensi=ngawur')->assertOk()->assertSee('Banner'); // jatuh ke default produk
        $this->get('/laporan/pemakaian-bahan')->assertOk()->assertSee('Analisis Pemakaian Bahan');
        $this->get('/eksekutif?tgl_dari=2030-01-10&tgl_sampai=2030-01-01')->assertOk(); // tanggal terbalik dirapikan

        $role = Role::factory()->create(['nama_role' => 'Kasir']);
        $kasir = User::factory()->create(['id_role' => $role->id_role]);
        $this->actingAs($kasir)->get('/eksekutif')->assertForbidden();
    }
}
