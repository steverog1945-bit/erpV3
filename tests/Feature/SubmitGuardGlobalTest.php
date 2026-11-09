<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * AUDIT: guard global anti-kirim-ganda di layouts/app.blade.php tidak berlaku
 * untuk form yang punya listener submit sendiri.
 *
 * Layout (baris 2178) memasang guard untuk semua form:
 *   - menolak klik ganda lewat dataset.sedangKirim
 *   - menonaktifkan tombol submit
 *   - menghidupkan lagi setelah 8 detik bila halaman tidak berpindah
 *
 * Tapi baris 2180 berbunyi:
 *   if (e.defaultPrevented || ...) return;
 *
 * Form yang listener-nya sendiri memanggil e.preventDefault() keluar dari guard
 * itu. Itu memang disengaja - halaman mau memvalidasi dulu sebelum mengirim.
 * Namun kalau handler-nya tidak memasang penaganya sendiri, form kehilangan:
 * pagaran klik ganda, tombol mati, dan pemulihannya.
 *
 * Inilah akar laporan "Simpan Order Jersey tidak punya loading state": form itu
 * memanggil preventDefault, jadi guard global dilewati.
 *
 * Yang dinilai "aman" di sini adalah perilaku menolak jalan dua kali, bukan
 * tampilannya. Aplikasi ini memakai dua bentuk penaga:
 *   a. penanda yang diperiksa di awal, mis. `if (this.__ok) return;`
 *      (kartu_stok, qc, postingan), `if (sedangKirim === '1') return;`
 *      (pagar global), `if (sedangMenyimpan) return;` (order jersey)
 *   b. tombol dinonaktifkan lalu dihidupkan lagi
 * Cukup salah satu.
 *
 * Test ini murni membaca berkas yang dikirim ke browser - memang itu yang
 * sedang diaudit, dan tidak ada yang bisa "__construct" melalui HTTP.
 */
class SubmitGuardGlobalTest extends TestCase
{
    /**
     * Apakah handler ini benar-benar menolak jalan dua kali?
     *
     * Hanya dua bentuk yang benar-benar fulfills itu:
     *   1. tombol dinonaktifkan lalu dihidupkan lagi (satu handler, dua kondisi)
     *   2. penanda yang bertahan lintas submit: form.dataset.X = '1',
     *      this.__X = true, sedangMenyimpan = true
     *
     * YANG BUKAN penaga: `if (validasiGagal) return;` di dalam handler.
     * Itu menahan submit yang memang tidak boleh jalan, bukan menahan submit
     * kedua. Menghitungnya sebagai penaga membuat test hijau padahal formnya
     * tetap bisa terkirim dua kali - sudah terjadi sekali saat audit ini.
     */
    private function adaPenaga(string $badan): bool
    {
        // (1) tombol: mati lalu hidup
        if (preg_match('/\.disabled\s*=\s*true/', $badan) === 1
            && preg_match('/\.disabled\s*=\s*false/', $badan) === 1) {
            return true;
        }

        // (2) penanda yang di-set ke nilai lalu diperiksa lagi
        $set = '(?:dataset\.[\w.]+\s*=\s*[\'"][01][\'"]|this\.__\w+\s*=\s*true|\b\w*[Mm]enyimpan\w*\s*=\s*true)';
        if (preg_match('/' . $set . '/', $badan) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Apakah handler ini benar-benar MENGAMBIL ALIH pengiriman - yang
     * membuatnya keluar dari guard global?
     *
     * Dua tanda, keduanya dipakai aplikasi ini:
     *   1. preventDefault() sebagai pernyataan PERTAMA handler. Itu berarti
     *      bukan sekadar memblokir jalur gagal, tapi mengambil alih submit
     *      untuk semua kasus - jadi guard global tidak pernah ikut jalan.
     *
     *      PENTING: handler yang memanggil e.preventDefault() DI DALAM kondisi
     *      "kalau tidak valid" justru BENAR dan tidak boleh dihitung. Jalur
     *      suksesnya tidak diubah, jadi guard global tetap berlaku. Menghitung
     *      yang ini sebagai bypass akan menghasilkan temuan palsu.
     *
     *   2. form.submit() / this.submit() dipanggil sendiri.
     *
     * @return array{ambilAlih: bool, penaga: bool}
     */
    private function analisisHandler(string $badan): array
    {
        // Komentar dibuang supaya preventDefault di dalam catatan tidak terhitung.
        $kode = preg_replace('#/\*.*?\*/#s', '', $badan);
        $kode = preg_replace('#//[^\n]*#', '', (string) $kode);

        // Isi setelah pembuka fungsi.
        $isi = preg_replace('/^\s*(?:async\s+)?function\s*\([^)]*\)\s*\{/', '', (string) $kode, 1);

        // preventDefault sebagai pernyataan pertama (dengan atau tanpa
        // pemeriksaan pembuka di depannya).
        $ambilAlih = preg_match('/^\s*(?:if\s*\([^)]*\)\s*\{?\s*return;?\s*\}?\s*)?e\.preventDefault\(\)/', (string) $isi) === 1
            || preg_match('/(^|[^.\w])this\.submit\(\)/', (string) $kode) === 1
            || preg_match('/(^|[^.\w])form\.submit\(\)/', (string) $kode) === 1;

        return ['ambilAlih' => $ambilAlih, 'penaga' => $this->adaPenaga($badan)];
    }

    /**
     * Semua listener submit yang memanggil preventDefault, beserta penilaian
     * penaganya.
     *
     * @return array<string, array{ada: bool, baris: int, selalu: bool}>
     */
    private function formYangLewatGuard(): array
    {
        $hasil = [];

        $iterator = function (string $d) use (&$iterator): \Generator {
            foreach (glob($d . '/*') ?: [] as $f) {
                if (is_dir($f)) {
                    yield from $iterator($f);
                } elseif (str_ends_with($f, '.blade.php')) {
                    yield $f;
                }
            }
        };

        foreach ($iterator(resource_path('views')) as $file) {
            $isi = file_get_contents($file);
            if (!preg_match_all(
                "/addEventListener\(\s*'submit'\s*,\s*(?:async\s+)?function\s*\(\s*\\\$?\w+\s*\)\s*\{/",
                $isi,
                $m,
                PREG_OFFSET_CAPTURE
            )) {
                continue;
            }

            foreach ($m[0] as $hit) {
                [, $offset] = $hit;
                $dari = strpos($isi, '{', $offset);
                if ($dari === false) {
                    continue;
                }

                $depth = 0;
                $akhir = $dari;
                $batas = min(strlen($isi), $dari + 9000);
                for ($i = $dari; $i < $batas; $i++) {
                    if ($isi[$i] === '{') {
                        $depth++;
                    } elseif ($isi[$i] === '}') {
                        $depth--;
                        if ($depth === 0) {
                            $akhir = $i;
                            break;
                        }
                    }
                }
                $badan = substr($isi, $dari, $akhir - $dari + 1);

                if (!str_contains($badan, 'preventDefault') && !str_contains($badan, '.submit()')) {
                    continue;
                }

                $rel = str_replace('\\', '/', substr($file, strlen(realpath(dirname(__DIR__, 2))) + 1));

                $a = $this->analisisHandler($badan);
                if (!$a['ambilAlih']) {
                    // Handler yang hanya memblokir jalur gagal - guard global
                    // tetap berlaku, jadi tidak relevan untuk audit ini.
                    continue;
                }

                // layouts/app.blade.php adalah sumber guard global itu sendiri.
                if (str_contains($rel, 'layouts/app.blade.php')) {
                    continue;
                }

                $hasil[$rel . ' baris ' . (substr_count(substr($isi, 0, $offset), "\n") + 1)] = $a;
            }
        }

        return $hasil;
    }

    /**
     * Guard global harus ada, dan harus keluar pada e.defaultPrevented.
     * Inilah yang membuat form yang validate-dulu kehilangan pagarnya, jadi
     * harus terkunci di sini supaya tidak hilang diam-diam.
     */
    public function test_guard_global_ada_dan_keluar_pada_e_default_prevented(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString("addEventListener('submit'", $layout, 'Guard global submit harus ada di layout');

        $this->assertMatchesRegularExpression(
            '/dataset\.sedangKirim\s*===\s*.1.\)\s*\{\s*e\.preventDefault\(\);\s*return;/',
            $layout,
            'Guard global harus menolak submit kedua lewat dataset.sedangKirim'
        );

        // Kondisi layout berisi kurung bersarang, jadi [^)]* tidak boleh dipakai.
        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*e\.defaultPrevented[\s\S]{0,240}?\)\s*return;/',
            $layout,
            'Guard global harus keluar bila e.defaultPrevented'
        );

        $this->assertMatchesRegularExpression(
            '/t\.disabled\s*=\s*true/',
            $layout,
            'Guard global harus menonaktifkan tombol submit'
        );
        $this->assertMatchesRegularExpression(
            '/t\.disabled\s*=\s*false/',
            $layout,
            'Guard global harus menghidupkan lagi tombolnya'
        );
    }

    /**
     * Form yang keluar dari guard global wajib memasang penaganya sendiri.
     */
    public function test_form_yang_lewat_guard_global_memasang_penaga_sendiri(): void
    {
        $tanpa = [];

        foreach ($this->formYangLewatGuard() as $key => $a) {
            if (!$a['penaga']) {
                $tanpa[] = $key . '  penaga=' . ($a['penaga'] ? 'ada' : 'TIDAK');
            }
        }

        $this->assertSame(
            [],
            $tanpa,
            "Form berikut mengambil alih submit (jadi keluar dari guard global) tanpa memasang penaga.\n"
            . "Akibatnya: klik ganda tidak tertahan, tombol tidak mati, tidak ada pemulihannya.\n"
            . " - " . implode("\n - ", $tanpa)
        );
    }

    /**
     * Halaman Simpan Order Jersey adalah laporan awal, jadi loading state-nya
     * harus lengkap - bukan hanya penanda.
     */
    public function test_simpan_order_jersey_punya_loading_state_lengkap(): void
    {
        $isi = file_get_contents(resource_path('views/jersey/orders/create.blade.php'));

        $this->assertStringContainsString('if (sedangMenyimpan) return;', $isi);
        $this->assertStringContainsString('btnSimpan.disabled = true;', $isi);
        $this->assertStringContainsString('btnSimpan.disabled = false;', $isi);
        $this->assertStringContainsString('Menyimpan...', $isi);
    }

    /**
     * Jalur take-over submit yang diperbaiki audit ini harus punya penaga
     * lengkap: penanda, tombol mati, tombol hidup, dan pemulihan berbatas
     * waktu. Tanpa yang keempat, form terkunci begitu validasi ditolak.
     *
     * @dataProvider halamanTakeOver
     */
    public function test_jalur_take_over_submit_punya_penaga_lengkap(string $rel, string $label): void
    {
        $isi = file_get_contents(dirname(__DIR__, 2) . '/' . $rel);

        // Nama penflaganya bebas (sedangMenyimpan, sedangSimpanSusulan, ...).
        // Yang diuji: nama yang sama muncul di pemeriksaan di awal DAN di
        // penyetelantrue, jadi penandanya benar-benar bekerja - bukan hanya
        // ada teks "if (...) return" yang tidak melakukan apa pun.
        preg_match('/if\s*\(\s*(\w*[Mm]enyimpan\w*|\w*[Ss]impan\w*|busy|isSubmitting)\s*\)\s*return;/', $isi, $m);
        $this->assertNotEmpty($m, $label . ': klik kedua harus ditolak oleh penanda');

        $nama = $m[1];
        $this->assertMatchesRegularExpression(
            '/\b' . preg_quote($nama, '/') . '\s*=\s*true/',
            $isi,
            $label . ': penanda "' . $nama . '" harus diset true, kalau tidak tidak menahan apa pun'
        );

        $this->assertStringContainsString('.disabled = true;', $isi, $label . ': tombol harus mati saat submit');
        $this->assertStringContainsString('.disabled = false;', $isi, $label . ': tombol harus hidup lagi');
        $this->assertStringContainsString('setTimeout', $isi, $label . ': harus ada pagar pengaman');
    }

    public static function halamanTakeOver(): array
    {
        return [
            'Kelola Barang Jersey' => ['resources/views/jersey/orders/edit-lines.blade.php', 'Kelola Barang Jersey'],
            'Tambah Barang Susulan' => ['resources/views/jersey/orders/show.blade.php', 'Tambah Barang Susulan'],
            'Pesanan Jersey Baru' => ['resources/views/jersey/orders/create.blade.php', 'Pesanan Jersey Baru'],
        ];
    }

    /** Pemulihan tombol harus ada batas waktunya, kalau tidak form terkunci. */
    public function test_pemulihan_tombol_punya_batas_waktu(): void
    {
        $isi = file_get_contents(resource_path('views/jersey/orders/create.blade.php'));

        $this->assertMatchesRegularExpression(
            '/setTimeout\([\s\S]{0,500}btnSimpan\.disabled = false;/',
            $isi,
            'Tombol harus dipulihkan dalam setTimeout sebagai pagar pengaman'
        );
    }
}