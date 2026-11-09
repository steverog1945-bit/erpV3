<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Diskon atau perubahan biaya item di layar pembayaran kasir mengubah total nota; piutang dan pendapatan di jurnal
 * ikut disesuaikan (selisihnya), supaya nota yang dilunasi sesuai total baru benar-benar tidak menyisakan piutang.
 */
class KasirUbahHargaJurnalTest extends TestCase
{
    use RefreshDatabase;

    private object $nota;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($admin);
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'A', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'x']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K', 'kode_kategori' => 'K']);
        $bahan = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'B', 'nama_bahan' => 'Flexi', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 50, 'nilai_konversi' => 1, 'harga_rata_rata' => 0, 'satuan_stok' => 'm2', 'status' => 'aktif']);
        $jasa = DB::table('jasa')->insertGetId(['nama_jasa' => 'Press', 'harga_default' => 20000]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()->subMinute()]);

        // Bahan 45.000 (Pendapatan Penjualan) + jasa 20.000 (Pendapatan Jasa) = 65.000.
        $this->post('/penjualan-barang/store', ['id_konsumen' => $kons, 'items' => [
            ['tipe' => 'bahan', 'id_bahan' => $bahan, 'qty' => 3, 'harga' => 15000],
            ['tipe' => 'jasa', 'id_jasa' => $jasa, 'qty' => 1, 'harga' => 20000],
        ]])->assertSessionHasNoErrors();
        $this->nota = DB::table('transaksi')->latest('id_transaksi')->first();
        DB::table('transaksi')->where('id_transaksi', $this->nota->id_transaksi)->update(['status_order' => 'Diterima Kasir']);
    }

    private function saldo(string $kode): float
    {
        return round((float) DB::table('jurnal_umum')->where('kode_akun', $kode)->sum(DB::raw('debet - kredit')), 2);
    }

    private function bayar(string $jumlah, string $diskon, string $sid)
    {
        return $this->post('/kasir/proses-bayar', ['id_transaksi' => $this->nota->id_transaksi, 'jumlah_bayar' => $jumlah, 'diskon' => $diskon,
            'metode_pembayaran' => 'Transfer', 'submit_id' => $sid]);
    }

    public function test_diskon_kasir_menyesuaikan_piutang_dan_pendapatan_lalu_lunas_tanpa_sisa(): void
    {
        $this->assertEquals(65000, $this->saldo('1100'));

        $this->bayar('0', '6.500', 'd-1')->assertSessionHas('success');
        $this->assertEquals(58500, (float) DB::table('transaksi')->where('id_transaksi', $this->nota->id_transaksi)->value('total_bayar'));
        $this->assertEquals(58500, $this->saldo('1100'), 'piutang tidak ikut turun setelah diskon');
        $this->assertEquals(-58500, $this->saldo('4000') + $this->saldo('4100'));

        // Simpan ulang dengan diskon yang sama tidak menyesuaikan dua kali.
        $this->bayar('0', '6.500', 'd-2');
        $this->assertEquals(58500, $this->saldo('1100'));

        $this->bayar('58.500', '6.500', 'd-3')->assertSessionHas('success');
        $this->assertSame('Lunas', DB::table('transaksi')->where('id_transaksi', $this->nota->id_transaksi)->value('status_pembayaran'));
        $this->assertEquals(0, $this->saldo('1100'), 'nota lunas masih menyisakan piutang');
        $this->assertEquals((float) DB::table('jurnal_umum')->sum('debet'), (float) DB::table('jurnal_umum')->sum('kredit'));
    }

    public function test_diskon_dihapus_lagi_mengembalikan_piutang_semula(): void
    {
        $this->bayar('0', '6.500', 'e-1');
        $this->bayar('0', '0', 'e-2');

        $this->assertEquals(65000, $this->saldo('1100'));
        $this->assertEquals(-45000, $this->saldo('4000'));
        $this->assertEquals(-20000, $this->saldo('4100'));
    }

    public function test_harga_nol_di_penjualan_dan_jasa_ditolak_server(): void
    {
        $sebelum = DB::table('transaksi')->count();

        $this->post('/penjualan-barang/store', ['id_konsumen' => $this->nota->id_konsumen, 'items' => [
            ['tipe' => 'custom', 'nama' => 'Gantungan', 'qty' => 2, 'harga' => 0],
        ]])->assertSessionHasErrors('items.0.harga');

        $this->assertSame($sebelum, DB::table('transaksi')->count());
    }

    public function test_diskon_tersimpan_tidak_hilang_di_pembayaran_berikutnya(): void
    {
        // DP dengan diskon 6.500: total menjadi 58.500.
        $this->bayar('20.000', '6.500', 'g-1')->assertSessionHas('success');
        $this->assertEquals(58500, (float) DB::table('transaksi')->where('id_transaksi', $this->nota->id_transaksi)->value('total_bayar'));

        // Form pelunasan dibuka lagi: diskon yang sudah disepakati tampil, bukan kembali 0.
        $this->get(route('kasir.bayarForm', $this->nota->id_transaksi))->assertOk()
            ->assertSee('id="input-diskon" name="diskon" value="6.500"', false);

        // Pelunasan tanpa isian diskon (klien lama/permintaan tanpa field itu): diskon tersimpan tetap berlaku.
        $this->post('/kasir/proses-bayar', ['id_transaksi' => $this->nota->id_transaksi, 'jumlah_bayar' => '38.500', 'metode_pembayaran' => 'Transfer', 'submit_id' => 'g-2'])
            ->assertSessionHas('success');

        $t = DB::table('transaksi')->where('id_transaksi', $this->nota->id_transaksi)->first();
        $this->assertEquals(58500, (float) $t->total_bayar, 'diskon hilang di pembayaran berikutnya');
        $this->assertSame('Lunas', $t->status_pembayaran);
        $this->assertEquals(0, $this->saldo('1100'), 'pelanggan yang sudah membayar sesuai kesepakatan masih tercatat berutang');
    }

    public function test_diskon_tidak_boleh_membuat_total_di_bawah_uang_yang_sudah_dibayar(): void
    {
        $this->bayar('40.000', '0', 'u-1')->assertSessionHas('success');

        // Total 65.000 - diskon 30.000 = 35.000, padahal sudah dibayar 40.000.
        $this->bayar('0', '30.000', 'u-2')->assertSessionHas('error');

        $this->assertEquals(65000, (float) DB::table('transaksi')->where('id_transaksi', $this->nota->id_transaksi)->value('total_bayar'));
        $this->assertGreaterThanOrEqual(0, $this->saldo('1100'), 'piutang nota menjadi minus');
    }

    public function test_pemeriksaan_antrean_kasir_menghitung_nota_baru_yang_belum_dibayar(): void
    {
        // Antrean baru = Menunggu Kasir. Nota setUp sudah Diterima, jadi
        // buat satu nota Menunggu Kasir murni untuk pemeriksaan ini.
        DB::table('transaksi')->insert([
            'nomor_nota' => 'N-ANTRE-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $this->nota->id_konsumen,
            'id_user_kasir' => $this->nota->id_user_kasir, 'tgl_order' => now(), 'total_bayar' => 1000,
            'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Menunggu Kasir',
        ]);
        $this->getJson('/kasir/check-pembayaran')->assertOk()->assertJson(['total' => 1]);
    }
}
