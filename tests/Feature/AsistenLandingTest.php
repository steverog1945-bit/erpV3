<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Asisten chat publik di landing page: HANYA seputar orderan. Topik
 * privasi/perusahaan (omzet, laba, gaji, dst.) harus ditolak tegas —
 * bahkan dengan kata-kata "iseng".
 */
class AsistenLandingTest extends TestCase
{
    use RefreshDatabase;

    private function tanya(string $pesan)
    {
        return $this->postJson(route('landing.tanya'), ['pesan' => $pesan]);
    }

    public function test_sapaan_menjawab_fungsi(): void
    {
        $r = $this->tanya('halo');
        $r->assertOk()->assertJsonStructure(['reply', 'intent']);
        $this->assertSame('sapaan', $r->json('intent'));
        $this->assertStringContainsString('orderan', mb_strtolower($r->json('reply')));
    }

    public function test_cara_pesan_menjelaskan_alur(): void
    {
        $r = $this->tanya('gimana cara pesan di sini');
        $r->assertOk();
        $this->assertSame('cara-pesan', $r->json('intent'));
        $this->assertStringContainsString('file desain', mb_strtolower($r->json('reply')));
    }

    public function test_kontak_memakai_pengaturan_toko(): void
    {
        $r = $this->tanya('kontak toko dong');
        $r->assertOk();
        $this->assertSame('kontak', $r->json('intent'));
        $this->assertStringContainsString('kontak resmi', $r->json('reply'));
    }

    public function test_privasi_omzet_ditolak(): void
    {
        $r = $this->tanya('berapa omzet toko bulan ini');
        $r->assertOk();
        $this->assertSame('ditolak-privasi', $r->json('intent'));
        $this->assertStringContainsString('internal', mb_strtolower($r->json('reply')));
    }

    public function test_privasi_laba_ditolak(): void
    {
        $r = $this->tanya('berapa laba kotor bulan ini ya');
        $r->assertOk();
        $this->assertSame('ditolak-privasi', $r->json('intent'));
    }

    public function test_privasi_gaji_owner_audit_ditolak(): void
    {
        foreach (['berapa gaji karyawan', 'siapa owner toko ini', 'laporan keuangan bulan ini', 'closing kas kemarin', 'target penjualan bulan ini'] as $pertanyaan) {
            $r = $this->tanya($pertanyaan);
            $r->assertOk();
            $this->assertSame('ditolak-privasi', $r->json('intent'), "harus ditolak: {$pertanyaan}");
        }
    }

    public function test_privasi_hpp_dan_modal_ditolak(): void
    {
        foreach (['berapa hpp banner per lembar', 'berapa modal bahan baku'] as $pertanyaan) {
            $r = $this->tanya($pertanyaan);
            $this->assertSame('ditolak-privasi', $r->json('intent'), "harus ditolak: {$pertanyaan}");
        }
    }

    public function test_harga_tanpa_katalog_tidak_menyebut_angka(): void
    {
        // DB kosong (tanpa produk): jawaban generik tanpa angka.
        $r = $this->tanya('berapa harga banner 3 x 1 meter');
        $r->assertOk();
        $this->assertSame('harga', $r->json('intent'));
        $this->assertStringContainsString('tergantung', mb_strtolower($r->json('reply')));
    }

    /** Seed minimal katalog: golongan Umum + produk + tier harga. */
    private function seedKatalog(): void
    {
        $idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Umum']);
        $idSatuan = DB::table('satuan')->insertGetId(['nama_satuan' => 'm²', 'nilai_konversi' => 1]);
        $idSatuanPcs = DB::table('satuan')->insertGetId(['nama_satuan' => 'pcs', 'nilai_konversi' => 1]);

        foreach ([
            ['Banner Flexi 280 gr', 'ukuran', 1.0, 1.0],
            ['Stiker Vinyl', 'ukuran', 0.0, 0.0],
            ['Kartu Nama', 'quantity', 0.0, 0.0],
        ] as $i => [$nama, $metode, $mp, $ml]) {
            $idProduk = DB::table('produk')->insertGetId([
                'nama_produk' => $nama, 'metode_hitung' => $metode,
                'min_order' => 0, 'status' => 'aktif',
                'min_panjang' => $mp, 'min_lebar' => $ml,
            ]);
            // Tier qty kecil lebih mahal, tier besar lebih murah.
            DB::table('setting_harga')->insert([
                ['id_produk' => $idProduk, 'id_golongan' => $idGol, 'range_min' => 1, 'range_max' => 10, 'harga_jual' => 30000 - $i * 1000, 'id_satuan' => $idSatuanPcs],
                ['id_produk' => $idProduk, 'id_golongan' => $idGol, 'range_min' => 11, 'range_max' => null, 'harga_jual' => 25000 - $i * 1000, 'id_satuan' => $idSatuanPcs],
            ]);
        }
    }

    public function test_harga_produk_sebut_harga_mulai_nyata(): void
    {
        $this->seedKatalog();

        $r = $this->tanya('berapa harga banner 3 x 1 meter');
        $r->assertOk();
        $this->assertSame('harga-produk', $r->json('intent'));
        $this->assertStringContainsString('Rp 30.000', $r->json('reply')); // tier terkecil, bukan 25.000
        $this->assertStringContainsString('harga umum', mb_strtolower($r->json('reply')));
        $this->assertStringNotContainsString('25.000', $r->json('reply')); // tier besar tidak bocor
    }

    public function test_ukuran_produk_sebut_batas_ukuran(): void
    {
        $this->seedKatalog();

        $r = $this->tanya('ukuran minimal banner');
        $r->assertOk();
        $this->assertSame('ukuran-produk', $r->json('intent'));
        $this->assertStringContainsString('1 x 1 m', $r->json('reply'));
    }

    public function test_harga_promo_menang_ditampilkan_sebagai_promo(): void
    {
        $this->seedKatalog();
        $idProduk = DB::table('produk')->where('nama_produk', 'Banner Flexi 280 gr')->value('id_produk');
        DB::table('harga_promo')->insert([
            'id_produk' => $idProduk, 'harga_promo' => 20000,
            'tgl_mulai' => today()->subDay(), 'tgl_selesai' => today()->addDay(), 'status_promo' => 'aktif',
        ]);

        $r = $this->tanya('harga banner berapa');
        $r->assertOk();
        $this->assertSame('harga-produk', $r->json('intent'));
        $this->assertStringContainsString('PROMO', $r->json('reply'));
        $this->assertStringContainsString('Rp 20.000', $r->json('reply'));
    }

    public function test_harga_generik_sebut_daftar_katalog(): void
    {
        $this->seedKatalog();

        $r = $this->tanya('pengen tahu harga cetakan di sini');
        $r->assertOk();
        $this->assertSame('harga', $r->json('intent'));
        $reply = $r->json('reply');
        $this->assertStringContainsString('Banner Flexi 280 gr', $reply);
        $this->assertStringContainsString('Rp 30.000', $reply);
    }

    public function test_privasi_tetap_menang_di_atas_harga_produk(): void
    {
        $this->seedKatalog();

        // "modal" + "banner" → privasi harus menang, bukan jawaban harga banner.
        foreach (['berapa modal banner', 'hpp banner berapa', 'berapa untung dari banner'] as $p) {
            $r = $this->tanya($p);
            $r->assertOk();
            $this->assertSame('ditolak-privasi', $r->json('intent'), "harus ditolak: {$p}");
        }
    }

    public function test_produk_nonaktif_tidak_muncul(): void
    {
        $this->seedKatalog();
        DB::table('produk')->where('nama_produk', 'Kartu Nama')->update(['status' => 'non-aktif']);

        $r = $this->tanya('layanan apa saja yang tersedia');
        $r->assertOk();
        $reply = mb_strtolower($r->json('reply'));
        $this->assertStringNotContainsString('kartu nama', $reply);
    }

    public function test_pertanyaan_asing_dibalas_dengan_bantuan(): void
    {
        $r = $this->tanya('asdfqwer zxcv');
        $r->assertOk();
        $this->assertSame('fallback', $r->json('intent'));
        $this->assertStringContainsString('orderan', mb_strtolower($r->json('reply')));
    }

    public function test_pesan_kosong_ditolak_validasi(): void
    {
        $r = $this->tanya('');
        $r->assertStatus(422);
    }

    public function test_bisa_menjawab_tanpa_login(): void
    {
        $this->app['auth']->guard()->logout();
        $r = $this->tanya('halo');
        $r->assertOk();
    }
}
