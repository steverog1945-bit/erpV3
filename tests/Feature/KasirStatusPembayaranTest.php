<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\StatusPembayaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regresi ambiguitas status pembayaran.
 *
 * Keluhan yang jadi pemicu test ini: satu halaman menampilkan dua jawaban
 * yang bertentangan untuk nota yang sama - kolom atas "Sudah dibayar
 * Rp 98.133", lalu baris paling bawah "BELUM BAYAR". Akar masalahnya ada
 * dua, dan keduanya diuji di sini:
 *
 *  1. Kolom transaksi.status_pembayaran hanya SALINAN hasil hitungan waktu
 *     pembayaran. Kalau pembayaran_riwayat berubah lewat jalur lain, salinannya
 *     tertinggal dan badge di Monitoring tidak lagi sinkron dengan kenyataan.
 *  2. Di form pembayaran, cabang `bayar <= 0` diperiksa lebih dulu sehingga
 *     nota yang sudah lunas (sisa tagihan 0) selalu ditulis "BELUM BAYAR".
 */
class KasirStatusPembayaranTest extends TestCase
{
    use RefreshDatabase;

    private function nota(array $override = [], array $pembayaran = []): array
    {
        $admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);

        $g = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $k = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $g]);
        $p = DB::table('produk')->insertGetId(['nama_produk' => 'Stiker Uji', 'metode_hitung' => 'quantity', 'status' => 'Aktif']);

        $t = DB::table('transaksi')->insertGetId(array_merge([
            'nomor_nota' => 'N-ST-1',
            'tipe_order' => 'Reguler',
            'id_konsumen' => $k,
            'id_user_kasir' => $admin->id_user,
            'tgl_order' => now(),
            'total_bayar' => 100000,
            'status_pembayaran' => 'Belum Bayar',
            'status_nota' => 'Aktif',
            'status_order' => 'Diterima Kasir',
        ], $override));

        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $t, 'id_produk' => $p, 'nama_file' => 'a',
            'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 1,
            'biaya_cetak' => 100000, 'subtotal' => 100000, 'status_spk' => 'Antre',
        ]);

        DB::table('kas_harian')->insert([
            'tgl_kas' => now()->toDateString(),
            'id_user' => $admin->id_user,
            'saldo_awal' => 0,
            'status_lock' => 'open',
        ]);

        foreach ($pembayaran as $b) {
            DB::table('pembayaran_riwayat')->insert(array_merge([
                'id_transaksi' => $t,
                'nominal' => 0,
                'metode' => 'Tunai',
                'tgl_bayar' => now(),
            ], $b));
        }

        return [$admin, $t];
    }

    public function test_cabang_nota_sudah_lunas_diperiksa_sebelum_cabang_belum_bayar(): void
    {
        // Bug aslinya ada di urutan percabangan, bukan di teksnya: cabang
        // `bayar <= 0` diuji lebih dulu sehingga nota lunas (sisa tagihan 0)
        // SELALU ditulis "BELUM BAYAR" walau uangnya sudah tercatat. Jadi
        // yang diuji adalah URUTAN, bukan keberadaan string "BELUM BAYAR"
        // (cabang itu memang harus tetap ada untuk nota yang belum bayar).
        [$admin, $t] = $this->nota(
            ['total_bayar' => 100000, 'status_pembayaran' => 'Lunas'],
            [['nominal' => 100000, 'metode' => 'Transfer']]
        );

        $html = $this->actingAs($admin)->get("/kasir/bayar-form/{$t}")->assertOk()->getContent();

        $posisiLunas = strpos($html, 'if (notaSudahLunas) {');
        $posisiKosong = strpos($html, '} else if (bayar <= 0) {');

        $this->assertNotFalse($posisiLunas, 'Cabang notaSudahLunas harus ada di calculateInvoice().');
        $this->assertNotFalse($posisiKosong, 'Cabang bayar <= 0 harus tetap ada.');
        $this->assertLessThan(
            $posisiKosong,
            $posisiLunas,
            'notaSudahLunas harus diperiksa SEBELUM bayar <= 0, kalau tidak nota lunas tampil "BELUM BAYAR".'
        );
    }

    public function test_uang_diterima_diisi_nominal_yang_sudah_tercatat_dan_dikunci(): void
    {
        [$admin, $t] = $this->nota(
            ['total_bayar' => 100000, 'status_pembayaran' => 'Lunas'],
            [['nominal' => 60000, 'metode' => 'Tunai'], ['nominal' => 40000, 'metode' => 'Tunai']]
        );

        $this->actingAs($admin)->get("/kasir/bayar-form/{$t}")->assertOk()
            // Nominal yang sama persis dengan yang tercatat, sebagai bukti
            // pendataan/pengingat.
            ->assertSee('100.000')
            ->assertSee('data-terkunci', false)
            // Bayar dimatikan supaya nominal bukti itu tidak terkirim lagi.
            ->assertSee('id="btn-bayar"', false);
    }

    public function test_metode_pembayaran_ditampilkan_di_kotak_status(): void
    {
        [$admin, $t] = $this->nota(
            ['total_bayar' => 100000, 'status_pembayaran' => 'Lunas'],
            [['nominal' => 100000, 'metode' => 'Transfer']]
        );

        $this->actingAs($admin)->get("/kasir/bayar-form/{$t}")
            ->assertOk()
            ->assertSee('id="metode-label"', false);
    }

    public function test_nota_cetak_menulis_status_metode_dan_nominal_uang_diterima(): void
    {
        [$admin, $t] = $this->nota(
            ['total_bayar' => 100000, 'status_pembayaran' => 'Lunas'],
            [['nominal' => 100000, 'metode' => 'Transfer']]
        );

        $html = $this->actingAs($admin)->get("/kasir/print-opsi/{$t}")->assertOk()->getContent();

        $this->assertStringContainsString('LUNAS', $html);
        $this->assertStringContainsString('Status Bayar', $html);
        $this->assertStringContainsString('Metode Bayar', $html);
        $this->assertStringContainsString('Transfer', $html);
        // Nominal uang diterima harus ikut tertulis, bukan cuma penjumlahannya.
        $this->assertStringContainsString('Uang Diterima', $html);
        $this->assertStringContainsString('100.000', $html);
    }

    public function test_nota_cetak_menulis_belum_bayar_bila_uangnya_belum_masuk(): void
    {
        [$admin, $t] = $this->nota(['total_bayar' => 100000, 'status_pembayaran' => 'Belum Bayar']);

        $html = $this->actingAs($admin)->get("/kasir/print-opsi/{$t}")->assertOk()->getContent();

        // Status dicetak kapital supaya mudah terbaca di printer thermal.
        $this->assertStringContainsString('BELUM BAYAR', $html);
        $this->assertStringContainsString('Status Bayar', $html);
    }

    public function test_badge_monitoring_mengikuti_pembayaran_bukan_kolom_salinan(): void
    {
        // Salinan kolom says Lunas, tapi pembayaran yang tercatat baru 40%.
        // Ini persis kondisi yang bikin layar menampilkan dua cerita.
        [$admin, $t] = $this->nota(
            ['total_bayar' => 100000, 'status_pembayaran' => 'Lunas'],
            [['nominal' => 40000, 'metode' => 'Tunai']]
        );

        $html = $this->actingAs($admin)
            ->get("/kasir/monitoring?status_filtered=1&status_bayar=" . urlencode('Belum Lunas'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('N-ST-1', $html, 'Nota harus muncul di daftar "Belum Lunas".');

        // Periksa baris nota itu saja. Memeriksa seluruh halaman tidak
        // berguna: dropdown filter juga memuat teks "Lunas".
        preg_match_all('/<tr[^>]*>.*?<\/tr>/s', $html, $baris);
        $barisNota = collect($baris[0])->first(fn ($b) => str_contains($b, 'N-ST-1'));

        $this->assertNotNull($barisNota, 'Baris nota N-ST-1 harus ada di tabel.');
        $this->assertStringContainsString('DP', $barisNota);
        $this->assertStringNotContainsString(
            '>Lunas<',
            $barisNota,
            'Badge tidak boleh menulis Lunas untuk nota yang pembayarannya baru 40%.'
        );
    }

    public function test_kolom_salinan_yang_tertinggal_diperbaiki_oleh_perintah_selaraskan(): void
    {
        [$admin, $t] = $this->nota(
            ['total_bayar' => 100000, 'status_pembayaran' => 'Belum Bayar'],
            [['nominal' => 100000, 'metode' => 'Tunai']]
        );

        $this->assertNotEmpty(StatusPembayaran::cariYangSelaras());

        $hasil = StatusPembayaran::selaraskan();

        $this->assertSame(1, $hasil['diperbaiki']);
        $this->assertSame('Lunas', DB::table('transaksi')->where('id_transaksi', $t)->value('status_pembayaran'));
        $this->assertEmpty(StatusPembayaran::cariYangSelaras());
    }

    public function test_perintah_selaraskan_tidak_mengubah_angka_uang(): void
    {
        [$admin, $t] = $this->nota(
            ['total_bayar' => 100000, 'status_pembayaran' => 'Belum Bayar'],
            [['nominal' => 100000, 'metode' => 'Tunai']]
        );

        StatusPembayaran::selaraskan();

        $this->assertSame(100000.00, (float) DB::table('transaksi')->where('id_transaksi', $t)->value('total_bayar'));
        $this->assertSame(1, DB::table('pembayaran_riwayat')->where('id_transaksi', $t)->count());
        $this->assertSame(100000.00, (float) DB::table('pembayaran_riwayat')->where('id_transaksi', $t)->sum('nominal'));
    }

    /**
     * Aturan PHP dan aturan SQL harus selalu sama. Kalau hanya satu
     * yang berubah, layar dan filter akan memakai definisi "lunas" yang
     * berbeda - dan ambiguitas yang dikeluhkan kasir akan kembali tanpa ada
     * yang menyadarinya.
     */
    public function test_aturan_php_dan_sql_memberi_status_yang_sama(): void
    {
        $kasus = [
            [100000, 0,    'Belum Bayar'],
            [100000, 40000, 'DP'],
            [100000, 100000, 'Lunas'],
            [100000, 120000, 'Lunas'],
            [100000, 99999.999, 'Lunas'], // toleransi pembulatan float
            [0,      0,    'Lunas'],
            [0,      50000, 'Lunas'],
            [50000,  25000, 'DP'],
        ];

        foreach ($kasus as [$tagihan, $terbayar, $harapan]) {
            $dariPhp = StatusPembayaran::dari($tagihan, $terbayar);

            // Angka ditulis sebagai literal (bukan placeholder) supaya SQL-nya
            // persis sama bentuknya dengan sqlCase() yang dipakai Monitoring.
            $sql = 'SELECT ' . StatusPembayaran::sqlCase(
                number_format($tagihan, 4, '.', ''),
                number_format($terbayar, 4, '.', '')
            ) . ' AS status';

            $dariSql = DB::selectOne($sql)->status;

            $this->assertSame($harapan, $dariPhp, "PHP untuk tagihan {$tagihan}, terbayar {$terbayar}");
            $this->assertSame($dariPhp, $dariSql, "SQL untuk tagihan {$tagihan}, terbayar {$terbayar}");
        }
    }

    public function test_accessor_model_memperbaiki_kolom_salinan_yang_tertinggal(): void
    {
        [$admin, $t] = $this->nota(
            ['total_bayar' => 100000, 'status_pembayaran' => 'Belum Bayar'],
            [['nominal' => 100000, 'metode' => 'Tunai']]
        );

        $model = Transaksi::findOrFail($t);

        $this->assertSame('Lunas', $model->display_status_pembayaran);
        $this->assertSame(100000.00, $model->total_diterima);
    }

    public function test_filter_tanggal_default_memesan_query_bukan_hanya_form(): void
    {
        // Nota 5 tahun lalu ada di luar rentang default; nota hari ini tidak.
        [$admin, $t] = $this->nota(['nomor_nota' => 'N-BARU', 'tgl_order' => now()]);

        $html = $this->actingAs($admin)->get('/kasir/monitoring?status_filtered=1&status_bayar=Belum%20Lunas')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('N-BARU', $html);
    }
}