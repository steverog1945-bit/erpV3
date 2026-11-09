<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sebagian biaya dibayar langsung oleh pemilik (listrik, internet, PDAM, BPJS, perlengkapan kantor, marketing,
 * operasional kantor): tetap dicatat (Dr Beban / Cr 3200 Dana Pemilik untuk Biaya Operasional) tetapi tidak mengurangi
 * saldo Kas Kecil. Biaya yang tetap dari Kas Kecil: administrasi & umum, ekspedisi, komisi desainer, upah jahit,
 * biaya lain-lain, perlengkapan toko. Laporan Perbandingan menyandingkan pemasukan Input Order dengan kedua jenis
 * pengeluaran itu.
 */
class PengeluaranPemilikTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($this->admin);
    }

    private function saldo(string $kode): float
    {
        return round((float) DB::table('jurnal_umum')->where('kode_akun', $kode)->sum(DB::raw('debet - kredit')), 2);
    }

    private function kategori(string $nama): object
    {
        return DB::table('kategori_kas_kecil')->where('nama_kategori', $nama)->first();
    }

    private function keluar(string $kategori, float $nominal, string $ket = 'uji')
    {
        return $this->post('/kas-kecil', ['tanggal' => now()->toDateString(), 'jenis' => 'Keluar', 'id_kategori' => $this->kategori($kategori)->id_kategori_kas_kecil,
            'keterangan' => $ket, 'nominal' => $nominal]);
    }

    public function test_pembagian_kategori_sesuai_keputusan_pemilik(): void
    {
        foreach (['Beban BPJS', 'Beban Internet', 'Beban Listrik & Telepon', 'Beban PDAM', 'Beban Perlengkapan Kantor', 'Biaya Marketing', 'Biaya Operasional Kantor'] as $n) {
            $this->assertEquals(1, (int) $this->kategori($n)->dibayar_pemilik, "{$n} seharusnya dibayar pemilik");
        }
        foreach (['Beban Administrasi & Umum', 'Biaya Ongkos Kirim & Ekspedisi', 'Komisi Desainer', 'Upah Jahit Borongan', 'Biaya Lain-lain', 'Beban Perlengkapan Toko'] as $n) {
            $this->assertEquals(0, (int) $this->kategori($n)->dibayar_pemilik, "{$n} seharusnya dari Kas Kecil");
        }
    }

    public function test_biaya_dibayar_pemilik_dicatat_tanpa_mengurangi_kas_kecil(): void
    {
        $this->post('/kas-kecil/saldo-awal', ['tanggal' => now()->toDateString(), 'nominal' => 100000]);

        // Lebih besar dari saldo Kas Kecil, tetapi bukan uang Kas Kecil: tetap tercatat.
        $this->keluar('Beban Listrik & Telepon', 500000, 'Listrik Oktober')->assertSessionHas('success');

        $this->assertEquals(100000, $this->saldo('1005'), 'biaya yang dibayar pemilik mengurangi saldo Kas Kecil');
        $this->assertEquals(500000, $this->saldo('5140'));
        $this->assertEquals(-500000, $this->saldo('3200'));
        $this->assertSame('Dibayar Pemilik', DB::table('kas_kecil')->where('keterangan', 'Listrik Oktober')->value('metode'));

        // Biaya dari Kas Kecil tetap dijaga saldonya.
        $this->keluar('Beban Perlengkapan Toko', 150000)->assertSessionHas('error');
        $this->keluar('Beban Perlengkapan Toko', 40000)->assertSessionHas('success');
        $this->assertEquals(60000, $this->saldo('1005'));
    }

    public function test_pembatalan_biaya_dibayar_pemilik_tidak_terhalang_saldo(): void
    {
        $this->keluar('Beban Internet', 300000, 'Internet')->assertSessionHas('success');
        $id = (int) DB::table('kas_kecil')->where('keterangan', 'Internet')->value('id');

        $this->delete("/kas-kecil/{$id}", ['alasan' => 'salah'])->assertSessionHas('success');
        $req = ApprovalRequest::where('tipe_aksi', 'batalkan_kas_kecil')->firstOrFail();
        $this->patch("/approval/{$req->id}/approve", ['catatan' => 'ok'])->assertSessionHas('success');

        $this->assertEquals(0, $this->saldo('3200'));
        $this->assertEquals(0, $this->saldo('5130'));
    }

    public function test_tanda_dibayar_pemilik_bisa_diubah_dari_master_kategori(): void
    {
        $k = $this->kategori('Biaya Marketing');
        $this->put("/kategori-kas-kecil/{$k->id_kategori_kas_kecil}", ['nama_kategori' => $k->nama_kategori, 'jenis' => 'Keluar', 'kode_akun' => $k->kode_akun])
            ->assertSessionHasNoErrors();
        $this->assertEquals(0, (int) $this->kategori('Biaya Marketing')->dibayar_pemilik);

        $this->put("/kategori-kas-kecil/{$k->id_kategori_kas_kecil}", ['nama_kategori' => $k->nama_kategori, 'jenis' => 'Keluar', 'kode_akun' => $k->kode_akun, 'dibayar_pemilik' => 1]);
        $this->assertEquals(1, (int) $this->kategori('Biaya Marketing')->dibayar_pemilik);
    }

    public function test_laporan_perbandingan_pemasukan_input_order_dan_pengeluaran(): void
    {
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'A', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        $jasa = DB::table('jasa')->insertGetId(['nama_jasa' => 'Press', 'harga_default' => 20000]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()->subMinute()]);
        $this->post('/penjualan-barang/store', ['id_konsumen' => $kons, 'items' => [
            ['tipe' => 'custom', 'nama' => 'Stiker', 'qty' => 3, 'harga' => 15000],
            ['tipe' => 'jasa', 'id_jasa' => $jasa, 'qty' => 1, 'harga' => 20000],
        ]])->assertSessionHasNoErrors();
        $t = DB::table('transaksi')->latest('id_transaksi')->first();
        $this->post('/kasir/proses-bayar', ['id_transaksi' => $t->id_transaksi, 'jumlah_bayar' => '25.000', 'metode_pembayaran' => 'Transfer', 'submit_id' => 'pb-1']);

        $this->post('/kas-kecil/saldo-awal', ['tanggal' => now()->toDateString(), 'nominal' => 100000]);
        $this->keluar('Beban Perlengkapan Toko', 10000);
        $this->keluar('Biaya Ongkos Kirim & Ekspedisi', 5000);
        $this->keluar('Beban Listrik & Telepon', 500000);

        $r = $this->get('/akuntansi/perbandingan?awal=' . now()->toDateString() . '&akhir=' . now()->toDateString())->assertOk();

        $r->assertViewHas('totalPemasukan', 65000.0);
        $r->assertViewHas('uangDiterima', 25000.0);
        $r->assertViewHas('totalKasKecil', 15000.0);
        $r->assertViewHas('totalPemilik', 500000.0);
        $r->assertViewHas('selisih', 65000.0 - 515000.0);
        $r->assertSee('Beban Listrik &amp; Telepon', false)->assertSee('Beban Perlengkapan Toko');
    }

    public function test_pembayaran_dari_saldo_deposit_tidak_dihitung_dua_kali_sebagai_uang_masuk(): void
    {
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'A', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()->subMinute()]);

        // Uang masuk sekali: setoran deposit 40.000 lewat transfer.
        $this->post('/konsumen-deposit', ['id_konsumen' => $kons, 'nominal' => 40000, 'metode_bayar' => 'Transfer', 'tgl_deposit' => now()->toDateString()])
            ->assertSessionHas('success');

        // Nota 25.000 dibayar dari saldo deposit: bukan uang masuk baru.
        $this->post('/penjualan-barang/store', ['id_konsumen' => $kons, 'items' => [['tipe' => 'custom', 'nama' => 'Stiker', 'qty' => 1, 'harga' => 25000]]])
            ->assertSessionHasNoErrors();
        $t = DB::table('transaksi')->latest('id_transaksi')->first();
        $this->post('/kasir/proses-bayar', ['id_transaksi' => $t->id_transaksi, 'jumlah_bayar' => '25.000', 'metode_pembayaran' => 'Deposit', 'submit_id' => 'dp-1'])
            ->assertSessionHas('success');

        $hari = now()->toDateString();
        $this->get("/akuntansi/perbandingan?awal={$hari}&akhir={$hari}")->assertOk()
            ->assertViewHas('uangDiterima', 0.0)
            ->assertViewHas('setoranDeposit', 40000.0);
    }
}
