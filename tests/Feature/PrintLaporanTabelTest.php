<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Aturan cetak tabel LAPORAN.
 *
 * Masalah yang diperbaiki: beberapa tabel laporan punya kolom lebih dari
 * yang muat di lebar A4, jadi kolom paling kanan terdorong keluar area
 * cetak dan isinya tidak pernah tercetak. Huruf 13px yang dipaksakan
 * ui-refresh.css untuk LAYAR ikut berlaku saat cetak, dan itulah yang
 * membuat tabel melebar.
 *
 * Yang diuji:
 *  1. Semua halaman laporan memakai partial aturan yang sama - tidak ada
 *     laporan yang bisa lupa ikut aturan.
 *  2. Ukuran huruf tabel dikondisikan per jumlah kolom (bukan satu angka
 *     untuk semua tabel).
 *  3. Teks panjang membungkus di dalam kolom, bukan mendorong kolom.
 *  4. Header tabel diulang di tiap halaman dan satu baris tidak terbelah.
 */
class PrintLaporanTabelTest extends TestCase
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

    /** Halaman laporan yang memakai partial print-report-header. */
    private function halamanLaporan(): array
    {
        return [
            '/laporan/order',
            '/laporan/stok-bahan',
            '/laporan/produksi',
            '/laporan/kas',
            '/laporan/piutang',
            '/laporan/hutang',
            '/laporan/hpp',
            '/laporan/job-costing',
            '/laporan/biaya-desain',
            '/laporan/evaluasi-master',
            '/laporan/order-jersey',
            '/laporan/belanja-bahan',
            '/akuntansi/jurnal',
            '/akuntansi/buku-besar',
            '/akuntansi/laba-rugi',
            '/akuntansi/neraca',
            '/akuntansi/neraca-saldo',
            '/akuntansi/perbandingan',
            '/kas-kecil',
            '/audit-owner',
        ];
    }

    public function test_partial_aturan_cetak_tabel_dipakai_semua_halaman_laporan(): void
    {
        $tanpaAturan = [];
        foreach ($this->halamanLaporan() as $url) {
            $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

            // Aturan bersama harus ada di setiap laporan.
            foreach (['--fs-tabel', 'table-header-group', 'break-inside: avoid', 'overflow-wrap: anywhere'] as $needle) {
                if (strpos($html, $needle) === false) {
                    $tanpaAturan[] = $url . ' (tidak ada: ' . $needle . ')';
                }
            }
        }

        $this->assertSame([], $tanpaAturan, 'Halaman laporan tanpa aturan cetak tabel: ' . implode(', ', $tanpaAturan));
    }

    /**
     * Ukuran huruf tabel harus SERAGAM untuk semua tabel laporan.
     *
     * Sebelumnya ukuran huruf ikut jumlah kolom (10px untuk tabel 4 kolom,
     * 8px untuk tabel 13 kolom). Effect-nya: dua laporan yang berdekatan
     * tercetak dengan huruf berbeda-beda, jadi hasilnya terlihat tidak
     * senada. Sekarang semuanya memakai satu ukuran yang sama.
     *
     * Ditambah: elemen di dalam sel (span/div ber classed text-[...]) ikut
     * aturan 13px milik ui-refresh.css kalau tidak ditimpa - sehingga ada
     * sel 13px di antara sel 9px. Aturan font-size: inherit menutup celah
     * itu.
     */
    public function test_ukuran_huruf_tabel_seragam_untuk_semua_tabel(): void
    {
        $html = $this->actingAs($this->admin)->get('/laporan/order')->assertOk()->getContent();

        // Tier ukuran huruf per jumlah kolom harus hilang.
        foreach (['kolom <= 5 ? 10', 'kolom <= 8 ? 9', 'kolom <= 11 ? 8.5'] as $needle) {
            $this->assertStringNotContainsString($needle, $html, 'Ukuran huruf tabel masih bertingkat: ' . $needle);
        }

        // Satu ukuran untuk semua tabel.
        $this->assertStringContainsString('--fs-tabel: 9px', $html, 'Ukuran huruf tabel harus satu untuk semua tabel');

        // Ukuran huruf pakai var(--fs-tabel) yang menimpa aturan 13px
        // milik ui-refresh.css - selector harus bertingkat kelas, kalau tidak
        // aturan 13px itu menang.
        $this->assertStringContainsString('table[class] th, table[class] td', $html);

        // Elemen di dalam sel ikut ukuran selnya.
        $this->assertStringContainsString('table[class] th *, table[class] td *', $html);
        $this->assertStringContainsString('font-size: inherit !important', $html);
    }

    /**
     * Aturan ukuran huruf yang menimpa ui-refresh.css harus pakai selector
     * yang VALID.
     *
     * Ini penting: nama kelas seperti text-[10.5px] mengandung TITIK, dan
     * di CSS titik di dalam nama kelas harus di-escape (\.). Kalau tidak,
     * selector itu dianggap rusak - dan karena satu selector rusak membuat
     * SELURUH daftar selector aturan itu dibuang peramban, semua aturan
     * penyeragaman huruf yang lain ikut hilang tanpa sadari. Gejalanya: sebagian
     * teks masih tercetak 13px padahal aturannya ada di berkas.
     */
    public function test_selector_ukuran_huruf_valid_tidak_ada_titik_yang_tidak_di_escape(): void
    {
        $html = $this->actingAs($this->admin)->get('/laporan/order')->assertOk()->getContent();

        $this->assertStringContainsString('text-\[10\.5px\]', $html, 'titik pada nama kelas harus di-escape');
        $this->assertStringNotContainsString('text-\[10.5px\]', $html, 'titik pada nama kelas tidak boleh di-escape');

        // Daftar selector utuh - kalau satu anggota rusak, semuanya hilang.
        $this->assertStringContainsString('body .text-xs, body .text-sm, body .text-base', $html);
        $this->assertStringContainsString('body .text-lg, body .text-xl', $html);
    }

    public function test_teks_panjang_dibungkus_bukan_mendorong_kolom(): void
    {
        $html = $this->actingAs($this->admin)->get('/laporan/order')->assertOk()->getContent();

        $this->assertStringContainsString('word-break: break-word', $html);
        $this->assertStringContainsString('white-space: normal !important', $html);
        $this->assertStringContainsString('min-width: 0 !important', $html);
    }
}
