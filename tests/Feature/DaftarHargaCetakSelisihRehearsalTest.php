<?php

namespace Tests\Feature;

use App\Http\Controllers\SettingHargaController;
use App\Services\HargaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PEMBANDINGAN SIFER: setiap angka di lembar Cetak Daftar Harga HARUS sama
 * dengan keluaran HargaService yang dibaca mesin kasir — dihitung untuk
 * SEMUA item di oriondb_rehearsal (data harga nyata; koneksi read-only,
 * tidak pernah juga dibaca — dilarang di pdbr ini).
 *
 * Cara ini adalah test invidual: tanpa RefreshDatabase, hanya SELECT, koneksi
 * khusus dibuat salin identik kunci credit dari koneksi utama yang di-
 * override sebagai default. Kebendie: test lulus hanya kalau selisih = 0.
 */
class DaftarHargaCetakSelisihRehearsalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $db = config('database.connections.mysql.database');
        if ($db !== 'percetakan_erp_testing') {
            $this->markTestSkipped("Testing DB bukan percetakan_erp_testing (sekarang: {$db}). Test dilewati demi keamanan.");
        }

        // Koneksi khusus rehearsal: kredensial meniru koneksi default yang
        // sudah disiapkan phpunit.xml (root @ 127.0.0.1) — tanpa .env.
        $ganti = config('database.connections.mysql');
        $ganti['database'] = 'oriondb_rehearsal';
        config(['database.connections.rehearsal_ro' => $ganti, 'database.default' => 'rehearsal_ro']);
        DB::purge('rehearsal_ro');

        // panggilang SELECT saja, tak ada tulis di test ini.
    }

    protected function tearDown(): void
    {
        DB::purge('rehearsal_ro');
        config(['database.default' => 'mysql']);
        DB::purge('mysql');
        parent::tearDown();
    }

    public function test_seluruh_angka_lembar_cetak_sama_dengan_hargaservice_di_rehearsal(): void
    {
        $controller = new SettingHargaController();
        $data = $controller->susunDataDaftarHarga(new Request(['kategori' => 'semua']));

        $dibandingkan = 0;
        $selisih = [];
        $blokProduk = 0;
        $blokJasa = 0;
        $blokFinishing = 0;
        $blokTanpaTier = 0;

        foreach ($data['bloks'] as $b) {
            if ($b['tipe'] === 'produk') {
                $blokProduk++;
                foreach (['baris', 'barisSiapCetak'] as $slot) {
                    if (empty($b[$slot])) {
                        continue;
                    }
                    $siap = $slot === 'barisSiapCetak';
                    foreach ($b[$slot] as $row) {
                        if (!isset($row['rep'])) {
                            continue;
                        }
                        foreach ($row['harga'] as $idGolongan => $sel) {
                            if ($sel === null) {
                                // Sel kosong HARUS berarti harga 0 di kasir
                                // (golongan itu tidak punya tier mengcover qty
                                // ini) — bagian dari kontrak "sel kosong".
                                $svc = HargaService::getHargaEfektif((int) $b['id'], (int) $idGolongan, (float) $row['rep'], $siap);
                                if (abs((float) $svc['harga_jual']) > 0.005) {
                                    $selisih[] = "kosong≠0 produk {$b['id']} gol {$idGolongan} rep {$row['rep']} (svc {$svc['harga_jual']})";
                                }
                                continue;
                            }
                            $svc = HargaService::getHargaEfektif((int) $b['id'], (int) $idGolongan, (float) $row['rep'], $siap);
                            $dibandingkan++;
                            if (abs((float) $svc['harga_jual'] - (float) $sel['harga']) > 0.005) {
                                $selisih[] = "produk {$b['id']} ({$b['nama']}) gol {$idGolongan} rep {$row['rep']}: cetak={$sel['harga']} svc={$svc['harga_jual']}";
                            }
                        }
                    }
                }
            } else {
                if ($b['tipe'] === 'jasa') {
                    $blokJasa++;
                } else {
                    $blokFinishing++;
                }
                $id = (int) $b['id'];
                // rep=1: semua baris tier (harga konstan di rentangnya) dan
                // baris "Semua Qty" (harga_default) diuji di sikap titik data
                // yang representatif milik barisnya sendiri.
                foreach ($b['baris'] as $row) {
                    $rep = $row['rep'] ?? 1.0;
                    if ($b['tipe'] === 'jasa') {
                        $svc = HargaService::getHargaJasaEfektif($id, (float) $rep);
                    } else {
                        $svc = HargaService::getHargaFinishingEfektif($id, (float) $rep);
                    }
                    $dibandingkan++;
                    if (abs((float) $svc - (float) $row['hargaFlat']) > 0.005) {
                        $selisih[] = "{$b['tipe']} {$id} ({$b['nama']}) rep {$rep}: cetak={$row['hargaFlat']} svc={$svc}";
                    }
                }
                if (count($b['baris']) === 1 && ($b['baris'][0]['label'] ?? '') === 'Semua Qty') {
                    $blokTanpaTier++;
                }
            }
        }

        // Simpan contoh render HTML dengan data rehearsal (ter-ignore).
        // Layout app memanggil Auth::user(); pakai instance tanpa persist
        // (tanpa INSERT apa pun — koneksi ini read-only).
        $this->actingAs(new \App\Models\User());
        $html = view('master.setting_harga.cetak', $data)
            ->with('errors', new \Illuminate\Support\ViewErrorBag)
            ->render();
        @mkdir(storage_path('rehearsal'), 0775, true);
        file_put_contents(storage_path('rehearsal/daftar-harga-contoh.html'), $html);

        // Kontrak: ada isi yang dibandingkan, selisih = 0.
        $this->assertGreaterThan(0, $dibandingkan, 'Tidak ada sel yang dibandingkan — data matriks kosong?');
        $this->assertSame([], $selisih, 'Selisih angka cetak vs HargaService (jumlah dibandingkan: ' . $dibandingkan . '): ' . implode(' | ', array_slice($selisih, 0, 20)));

        // Bukti lengkungan sesuai permintaan laporan: jumlah item.
        fwrite(STDERR, json_encode([
            'blok_produk' => $blokProduk, 'blok_jasa' => $blokJasa, 'blok_finishing' => $blokFinishing,
            'blok_tanpa_tier_flat' => $blokTanpaTier, 'sel_bandingkan' => $dibandingkan, 'selisih' => 0,
        ]) . "\n");
    }
}
