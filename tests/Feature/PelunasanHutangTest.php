<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PelunasanHutangTest extends TestCase
{
    use RefreshDatabase;

    private int $idSup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
        $this->idSup = DB::table('supplier')->insertGetId(['nama_supplier' => 'PT Uji', 'kontak' => '-']);
    }

    private function faktur(string $no, float $total, string $jatuhTempo): int
    {
        $idGudang = DB::table('gudang')->first()->id_gudang ?? DB::table('gudang')->insertGetId(['nama_gudang' => 'Utama']);
        $idKed = DB::table('kedatangan_bahan')->insertGetId(['no_surat_jalan' => 'SJ-' . $no, 'id_supplier' => $this->idSup, 'id_gudang' => $idGudang, 'tgl_datang' => now()->toDateString(), 'status' => 'Selesai']);

        return DB::table('pembelian_bahan')->insertGetId([
            'id_kedatangan' => $idKed, 'no_faktur' => $no, 'tgl_transaksi' => now()->subDays(40)->toDateString(), 'tipe_pembelian' => 'Tempo', 'status_pembayaran' => 'Hutang',
            'jatuh_tempo_tgl' => $jatuhTempo, 'grand_total' => $total,
        ]);
    }

    private function saldo(string $kode): float
    {
        return (float) DB::table('jurnal_umum')->where('kode_akun', $kode)->sum(DB::raw('debet - kredit'));
    }

    public function test_info_pelunasan_faktur_dan_supplier(): void
    {
        $a = $this->faktur('F-A', 1000000, now()->subDays(5)->toDateString());
        $this->faktur('F-B', 500000, now()->addDays(10)->toDateString());

        $this->getJson("/pembelian-bahan/pelunasan-info?faktur={$a}")->assertOk()->assertJsonPath('sisa', 1000000)->assertJsonPath('fakturs.0.telat_hari', 5);
        $this->getJson("/pembelian-bahan/pelunasan-info?supplier={$this->idSup}")->assertOk()->assertJsonPath('sisa', 1500000)->assertJsonCount(2, 'fakturs');
    }

    public function test_pembayaran_bahan_di_luar_semua_kas_dan_tidak_memakai_kas_kecil(): void
    {
        $f = $this->faktur('F-A', 1000000, now()->addDays(3)->toDateString());

        // Kas Kecil bukan sumber pembayaran bahan.
        $this->patch("/pembelian-bahan/pelunasan/{$f}", ['nominal' => 100000, 'metode' => 'Kas Kecil', 'tanggal' => now()->toDateString()])->assertSessionHas('error');
        $this->assertSame(0, DB::table('pembayaran_hutang')->count());

        $this->patch("/pembelian-bahan/pelunasan/{$f}", ['nominal' => 100000, 'metode' => 'Transfer', 'tanggal' => now()->toDateString(), 'keterangan' => 'bukti BCA'])->assertSessionHas('success');

        $this->assertEquals(-100000, $this->saldo('3100'), 'tercatat sebagai dana pemilik');
        $this->assertEquals(0.0, $this->saldo('1000') + $this->saldo('1005') + $this->saldo('1010'), 'tidak ada kas yang berkurang');
        $this->assertEquals(100000, $this->saldo('2000'));
        $this->assertSame(0, DB::table('kas_kecil')->count());
    }

    public function test_bayar_supplier_dibagi_ke_faktur_paling_lama_jatuh_tempo_dulu(): void
    {
        $lama = $this->faktur('F-LAMA', 400000, now()->subDays(20)->toDateString());
        $baru = $this->faktur('F-BARU', 600000, now()->addDays(20)->toDateString());

        $this->post("/pembelian-bahan/bayar-supplier/{$this->idSup}", ['nominal' => 700000, 'metode' => 'Tunai'])->assertSessionHas('success');

        $this->assertSame('Lunas', DB::table('pembelian_bahan')->where('id_pembelian', $lama)->value('status_pembayaran'));
        $this->assertSame('Hutang', DB::table('pembelian_bahan')->where('id_pembelian', $baru)->value('status_pembayaran'));
        $this->assertEquals(300000, (float) DB::table('pembayaran_hutang')->where('id_pembelian', $baru)->sum('nominal'));

        $this->post("/pembelian-bahan/bayar-supplier/{$this->idSup}", ['nominal' => 999999999, 'metode' => 'Tunai'])->assertSessionHas('error');
    }

    public function test_daftar_pembelian_menampilkan_hitungan_jatuh_tempo(): void
    {
        $this->faktur('F-TELAT', 100000, now()->subDays(4)->toDateString());
        $this->faktur('F-SEGERA', 200000, now()->addDays(3)->toDateString());

        $this->get('/pembelian-bahan')->assertOk()->assertSee('Telat 4 hari')->assertSee('3 hari lagi')->assertSee('Telat Jatuh Tempo');
        $this->get('/pembelian-bahan?jt=telat')->assertOk()->assertSee('F-TELAT')->assertDontSee('F-SEGERA');
    }

    public function test_faktur_tempo_tanpa_jatuh_tempo_otomatis_30_hari_dan_usulan_termin_supplier(): void
    {
        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'D']);
        $idKat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $idBahan = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'T1', 'nama_bahan' => 'Kertas', 'id_mesin' => $idMesin, 'id_kategori' => $idKat, 'nilai_konversi' => 1, 'stok' => 5, 'harga_rata_rata' => 0, 'satuan_stok' => 'rim', 'status' => 'aktif']);
        $idGudang = DB::table('gudang')->insertGetId(['nama_gudang' => 'Utama']);
        $idKed = DB::table('kedatangan_bahan')->insertGetId(['no_surat_jalan' => 'SJ-T', 'id_supplier' => $this->idSup, 'id_gudang' => $idGudang, 'tgl_datang' => now()->toDateString(), 'status' => 'Selesai']);
        DB::table('kedatangan_bahan_detail')->insert(['id_kedatangan' => $idKed, 'id_bahan' => $idBahan, 'qty' => 2, 'status' => 'OK']);

        // Tanpa tempo_hari & jatuh_tempo_tgl -> default 30 hari.
        $this->post('/pembelian-bahan', [
            'id_kedatangan' => $idKed, 'no_faktur' => 'F-T1', 'tgl_transaksi' => '2026-09-01', 'tipe_pembelian' => 'Tempo',
            'id_bahan' => [$idBahan], 'qty' => [2], 'harga' => ['100000'], 'diskon' => ['0'], 'potongan' => ['0'], 'subtotal' => [200000], 'grand_total' => 200000,
        ])->assertSessionHas('success');
        $p = DB::table('pembelian_bahan')->where('no_faktur', 'F-T1')->first();
        $this->assertSame('2026-10-01', $p->jatuh_tempo_tgl);
        $this->assertSame(30, (int) $p->jatuh_tempo_hari);

        // Usulan termin supplier ini ikut di data detail kedatangan lain.
        $idKed2 = DB::table('kedatangan_bahan')->insertGetId(['no_surat_jalan' => 'SJ-T2', 'id_supplier' => $this->idSup, 'id_gudang' => $idGudang, 'tgl_datang' => now()->toDateString(), 'status' => 'Selesai']);
        $this->getJson("/pembelian-bahan/kedatangan-detail?id_kedatangan={$idKed2}")->assertOk()->assertJsonPath('tempo_terakhir', 30);
    }

    public function test_filter_telat_mencakup_faktur_tanpa_tanggal_jatuh_tempo_memakai_tanggal_transaksi(): void
    {
        $id = $this->faktur('F-NULL', 300000, now()->addDays(9)->toDateString());
        DB::table('pembelian_bahan')->where('id_pembelian', $id)->update(['jatuh_tempo_tgl' => null, 'tgl_transaksi' => now()->subDays(12)->toDateString()]);

        $this->get('/pembelian-bahan?jt=telat')->assertOk()->assertSee('F-NULL');
    }

    public function test_filter_belum_jatuh_tempo_tidak_memuat_yang_telat_dan_ada_opsi_semua(): void
    {
        $this->faktur('F-TELAT2', 100000, now()->subDays(3)->toDateString());
        $this->faktur('F-DEKAT', 100000, now()->addDays(4)->toDateString());
        $this->faktur('F-JAUH', 100000, now()->addDays(25)->toDateString());

        $this->get('/pembelian-bahan?jt=aman')->assertOk()->assertSee('F-JAUH')->assertDontSee('F-TELAT2')->assertDontSee('F-DEKAT')->assertSee('Tampilkan semua');
        $this->get('/pembelian-bahan')->assertOk()->assertSee('F-JAUH')->assertSee('F-TELAT2')->assertSee('F-DEKAT');
    }
}
