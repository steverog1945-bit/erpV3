<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cetak nota thermal - posisi cetak, label operator, dan penyembunyian
 * ukuran.
 *
 * 1) POSISI CETAK KE KIRI (thermal)
 *    Printer thermal kadang kena error koneksi dan yang keluar hanya bagian
 *    kiri kertas. Supaya bagian kiri itu yang berisi kepala struk (nama
 *    toko, nomor nota, item pertama), blok nota ditarik ke kiri - bukan
 *    dipusatkan. Isi di dalamnya tetap terpusat seperti sebelumnya.
 *
 * 2) LABEL "OPERATOR", bukan "Kasir"
 *    Yang mencatat order adalah operator; struk yang ditulis "Kasir"
 *    sering dikira sebagai nama orang yang menerima uang.
 *
 * 3) UKURAN TIDAK DICETAK untuk produk ber-metode hitung 'ukuran'
 *    Ukuran di form mengikuti ukuran bahan, jadi bisa berbeda dari file
 *    asli. Memprintingnya membuat konsumen salah paham. Produk per lembar
 *    (A3, foto, dll) tidak punya ukuran di form, jadi tidak ada yang perlu
 *    disembunyikan.
 */
class PrintNotaThermalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
        DB::table('roles')->where('nama_role', 'Administrator')->update(['boleh_lihat_harga' => true]);
    }

    private function buatNota(string $metodeProduk, float $p, float $l): int
    {
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $kons = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Toko Maju', 'telepon' => '0812', 'alamat' => '-', 'id_golongan' => $gol,
        ]);
        $produk = DB::table('produk')->insertGetId([
            'nama_produk' => 'Produk Uji', 'metode_hitung' => $metodeProduk, 'status' => 'aktif',
        ]);

        $idTransaksi = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'ORI-THERMAL', 'tipe_order' => 'Reguler', 'id_konsumen' => $kons,
            'id_user_kasir' => $this->admin->id_user, 'tgl_order' => now(), 'total_bayar' => 50000,
            'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_collecting' => 'Belum Diambil',
        ]);

        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $idTransaksi, 'id_produk' => $produk, 'nama_file' => 'banner.pdf',
            'p_ukuran' => $p, 'l_ukuran' => $l, 'qty' => 1, 'subtotal' => 50000,
            'status_urgensi' => 'Normal', 'status_finishing' => 'Standar',
            'hpp_snapshot' => 0, 'harga_jual_snapshot' => 50000, 'status_spk' => 'Selesai',
            'biaya_cetak' => 50000, 'biaya_desain' => 0, 'biaya_finishing' => 0,
        ]);

        return $idTransaksi;
    }

    private function cetak(int $idTransaksi, string $format): string
    {
        // Format nota ikut pengaturan aplikasi (AppSetting::format_nota).
        $setting = \App\Models\AppSetting::current();
        $setting->format_nota = $format;
        $setting->save();
        // Pengaturan di-cache, jadi cache harus dibuang atau format lamanya
        // yang terpakai.
        \App\Models\AppSetting::clearCache();

        return $this->actingAs($this->admin)
            ->get("/kasir/print-opsi/{$idTransaksi}")
            ->assertOk()
            ->getContent();
    }

    public function test_thermal_menarik_blok_nota_ke_kiri_sedangkan_a4_tetap_dipusatkan(): void
    {
        $id = $this->buatNota('ukuran', 100, 50);

        $thermal = $this->cetak($id, 'thermal');
        // .sheet pada thermal memakai margin kiri (bukan auto = dipusatkan).
        $this->assertMatchesRegularExpression('/\.sheet\s*\{[^}]*margin:\s*0\s*;/s', $thermal);
        $this->assertDoesNotMatchRegularExpression('/\.sheet\s*\{[^}]*margin:\s*0 auto\s*;/s', $thermal);

        $a4 = $this->cetak($id, 'a4');
        $this->assertMatchesRegularExpression('/\.sheet\s*\{[^}]*margin:\s*0 auto\s*;/s', $a4);
    }

    /**
     * Halaman thermal harus selebar kertasnya (80mm) supaya "Save as PDF"
     * dari dialog cetak menghasilkan halaman nota saja - bukan A4 dengan
     * ruang kosong excessive. Aturan @page yang mengaturnya.
     */
    public function test_halaman_thermal_selebar_kertas_80mm(): void
    {
        $id = $this->buatNota('ukuran', 100, 50);

        $thermal = $this->cetak($id, 'thermal');

        $this->assertStringContainsString('max-width: 80mm', $thermal);
        $this->assertStringContainsString('width: 80mm', $thermal);
        $this->assertMatchesRegularExpression('/@page\s*\{[^}]*size:\s*80mm auto/s', $thermal);
        $this->assertMatchesRegularExpression('/@page\s*\{[^}]*margin:\s*0/s', $thermal);

        // A4/continuous tidak ikut memakai ukuran kertas thermal.
        $a4 = $this->cetak($id, 'a4');
        $this->assertStringNotContainsString('size: 80mm auto', $a4);
    }

    /** Tanpa preview: dialog cetak langsung terbuka saat halaman dimuat. */
    public function test_thermal_membuka_dialog_cetak_langsung(): void
    {
        $id = $this->buatNota('ukuran', 100, 50);

        // Auto-print dibedakan dari tombol "Cetak" di layar (yang ada di
        // semua format) - yang searched adalah blok script yang memanggil
        // window.print() sendiri saat halaman selesai dimuat.
        $autoPrint = "setTimeout(function () { window.print(); }, 300);";

        $thermal = $this->cetak($id, 'thermal');
        $this->assertStringContainsString($autoPrint, $thermal);

        $a4 = $this->cetak($id, 'a4');
        $this->assertStringNotContainsString($autoPrint, $a4, 'A4 tidak buka dialog cetak otomatis');
    }

    /**
     * Auto-cetak TIDAK boleh jalan di dalam iframe.
     *
     * Pratinjau nota di Pengaturan Aplikasi ditampilkan lewat iframe ke
     * halaman cetak yang sama. Kalau auto-cetak ikut jalan di sana,
     * membuka Pengaturan Aplikasi akan memunculkan dialog cetak yang tidak
     * diminta - dan dialog itu memblokir seluruh halaman sampai ditutup.
     */
    public function test_auto_cetak_dilewati_di_dalam_iframe(): void
    {
        $id = $this->buatNota('ukuran', 100, 50);

        $thermal = $this->cetak($id, 'thermal');

        $this->assertStringContainsString(
            'if (window.top !== window.self) return;',
            $thermal,
            'auto-cetak harus dilewati saat halaman berada di dalam iframe'
        );
    }

    public function test_label_kasir_di_nota_berubah_jadi_operator(): void
    {
        $id = $this->buatNota('ukuran', 100, 50);

        $html = $this->cetak($id, 'thermal');

        $this->assertStringContainsString('Operator', $html);
        $this->assertStringNotContainsString('>Kasir<', $html);
    }

    public function test_ukuran_tidak_dicetak_untuk_produk_ber_metode_ukuran(): void
    {
        $id = $this->buatNota('ukuran', 100, 50);

        $html = $this->cetak($id, 'thermal');

        // "100x50 cm" tidak boleh muncul - ukuran di form mengikuti ukuran
        // bahan, bisa berbeda dari file asli.
        $this->assertStringNotContainsString('100x50', $html);
        $this->assertStringNotContainsString(' cm', $html);
        // Nama file & nilainya tetap tercetak.
        $this->assertStringContainsString('banner.pdf', $html);
        $this->assertStringContainsString('50.000', $html);
    }

    public function test_produk_per_lembar_tidak_berubah_karena_tidak_punya_ukuran(): void
    {
        $id = $this->buatNota('quantity', 0, 0);

        $html = $this->cetak($id, 'thermal');

        $this->assertStringContainsString('banner.pdf', $html);
        $this->assertStringNotContainsString(' cm', $html);
    }

    public function test_item_tanpa_produk_tetap_aman(): void
    {
        // Item tanpa produk (jasa / item custom): kolom produk kosong, jadi
        // metode_hitung_produk ikut kosong dan tidak ada ukuran yang bocor.
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $kons = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Toko Lama', 'telepon' => '-', 'alamat' => '-', 'id_golongan' => $gol,
        ]);

        $idTransaksi = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'ORI-LAMA', 'tipe_order' => 'Reguler', 'id_konsumen' => $kons,
            'id_user_kasir' => $this->admin->id_user, 'tgl_order' => now(), 'total_bayar' => 10000,
            'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif',
        ]);
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $idTransaksi, 'id_produk' => null, 'nama_file' => 'jasa jahit.pdf',
            'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 1, 'subtotal' => 10000,
            'status_urgensi' => 'Normal', 'status_finishing' => 'Standar', 'status_spk' => 'Selesai',
            'biaya_cetak' => 10000, 'biaya_desain' => 0, 'biaya_finishing' => 0,
        ]);

        $html = $this->cetak($idTransaksi, 'thermal');

        $this->assertStringContainsString('jasa jahit.pdf', $html);
        $this->assertStringNotContainsString(' cm', $html);
    }
}
