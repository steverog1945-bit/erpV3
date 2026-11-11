<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_harga_tidak_menyebut_angka_tunggal(): void
    {
        $r = $this->tanya('berapa harga banner 3 x 1 meter');
        $r->assertOk();
        $this->assertSame('harga', $r->json('intent'));
        $this->assertStringContainsString('tergantung', mb_strtolower($r->json('reply')));
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
