<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi: tombol "Terima" vs "Terima dan Bayar" di Antrean Baru.
 *
 * Kontrak bisnis yang dikunci:
 *  1. "Terima" / "Terima dan Bayar" KEDUANYA mengaktifkan SPK lewat endpoint
 *     terimaTransaksi yang sama persis (status_order -> Diterima Kasir).
 *     Tidak ada jalur baru, tidak ada SPK yang ter-skip.
 *  2. "Terima dan Bayar" diteruskan ke bayar-form (sumber=langsung) -
 *     konsumen lunas/DP langsung tanpa harus membuka Monitoring Kasir.
 *  3. Keterangan di pembayaran_riwayat dibedakan sumbernya:
 *     - langsung   => "Langsung Bayar {nomor_nota}"
 *     - monitoring => "Pelunasan transaksi untuk transaksi : {nomor_nota}"
 *  4. Arus terima-biasa tetap memasukkan nota ke Monitoring (perilaku lama).
 */
class TerimaBayarLangsungTest extends TestCase
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
        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
        $this->actingAs($this->admin);

        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M1', 'tipe_mesin' => 'Cetak']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K', 'kode_kategori' => 'K']);
        $this->bahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'TBL-1', 'nama_bahan' => 'Kertas TerimaBayar', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 100, 'nilai_konversi' => 1, 'harga_rata_rata' => 1000, 'satuan_stok' => 'lembar', 'status' => 'aktif',
        ]);
        $this->gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Konsumen TerimaBayar', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $this->gol]);
        $this->produk = DB::table('produk')->insertGetId(['nama_produk' => 'Brosur', 'metode_hitung' => 'quantity', 'status' => 'aktif']);
        DB::table('bom_detail')->insert(['id_produk' => $this->produk, 'id_bahan' => $this->bahan, 'cara_hitung_pemakaian' => 'Q', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('setting_harga')->insert(['id_produk' => $this->produk, 'id_golongan' => $this->gol, 'range_min' => 0, 'harga_jual' => 10000]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()->subMinute()]);
    }

    private function buatNota(float $biaya = 50000): object
    {
        $this->postJson('/penjualan/store', [
            'id_konsumen' => $this->kons, 'id_golongan' => $this->gol, 'submit_id' => 'tbl-' . uniqid(),
            'items' => [['id_produk' => $this->produk, 'nama_file' => 'uji.pdf', 'p' => 0, 'l' => 0, 'qty_input' => 1, 'biaya_cetak' => $biaya]],
        ])->assertOk();

        return DB::table('transaksi')->first();
    }

    public function test_antrean_menampilkan_dua_tombol_saat_kas_buka(): void
    {
        $this->buatNota();
        $this->get('/kasir/waiting-list')->assertOk()
            ->assertSee('Terima dan Bayar', false)
            ->assertSee('Terima', false);
    }

    public function test_terima_biasa_tetap_ke_monitoring_tanpa_pembayaran(): void
    {
        $nota = $this->buatNota();
        $r = $this->post(route('kasir.terimaTransaksi', $nota->id_transaksi), ['submit_id' => 'tbl-terima-1']);
        $r->assertSessionHas('success');

        // masuk SPK, TIDAK ada pembayaran, masuk Monitoring (redirect index)
        $this->assertSame('Diterima Kasir', DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->value('status_order'));
        $this->assertSame('Antre', DB::table('transaksi_detail')->where('id_transaksi', $nota->id_transaksi)->value('status_spk'));
        $this->assertSame(0, DB::table('pembayaran_riwayat')->where('id_transaksi', $nota->id_transaksi)->count());
    }

    public function test_terima_dan_bayar_langsung_keluar_bayar_form_dan_keterangan_langsung_bayar(): void
    {
        $nota = $this->buatNota();
        $r = $this->post(route('kasir.terimaTransaksi', $nota->id_transaksi), ['submit_id' => 'tbl-terima-2', 'lanjut' => 'bayar']);
        $r->assertRedirect(route('kasir.bayarForm', ['id' => $nota->id_transaksi, 'sumber' => 'langsung']));

        // SPK tetap diaktifkan - identik dengan terima biasa
        $this->assertSame('Diterima Kasir', DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->value('status_order'));
        $this->assertSame('Antre', DB::table('transaksi_detail')->where('id_transaksi', $nota->id_transaksi)->value('status_spk'));

        // Jalur redirect membawa sumber=langsung; halaman form menampilkan hidden input dengan benar
        $html = $this->get(route('kasir.bayarForm', ['id' => $nota->id_transaksi, 'sumber' => 'langsung']))->assertOk()->getContent();
        $this->assertStringContainsString('name="sumber_bayar" value="langsung"', $html);

        // Bayar lunas dari jalur "langsung" -> keterangan Wajib "Langsung Bayar"
        $this->post('/kasir/proses-bayar', [
            'id_transaksi' => $nota->id_transaksi, 'jumlah_bayar' => '50.000',
            'metode_pembayaran' => 'Tunai', 'submit_id' => 'tbl-bayar-1', 'sumber_bayar' => 'langsung',
        ])->assertSessionHas('success');

        $keterangan = DB::table('pembayaran_riwayat')->where('id_transaksi', $nota->id_transaksi)->value('keterangan');
        $this->assertSame('Langsung Bayar ' . $nota->nomor_nota, $keterangan);
        $this->assertSame('Lunas', DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->value('status_pembayaran'));
    }

    public function test_bayar_jalur_monitoring_berketerangan_pelunasan(): void
    {
        $nota = $this->buatNota();
        $this->post(route('kasir.terimaTransaksi', $nota->id_transaksi), ['submit_id' => 'tbl-terima-3']);

        // Bayar dari kasir/monitoring (sumber monitoring) -> "Pelunasan transaksi untuk transaksi : NOTA"
        $this->post('/kasir/proses-bayar', [
            'id_transaksi' => $nota->id_transaksi, 'jumlah_bayar' => '50.000',
            'metode_pembayaran' => 'Tunai', 'submit_id' => 'tbl-bayar-2', 'sumber_bayar' => 'monitoring',
        ])->assertSessionHas('success');

        $keterangan = DB::table('pembayaran_riwayat')->where('id_transaksi', $nota->id_transaksi)->value('keterangan');
        $this->assertSame('Pelunasan transaksi untuk transaksi : ' . $nota->nomor_nota, $keterangan);
        $this->assertSame('Lunas', DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->value('status_pembayaran'));
    }
}