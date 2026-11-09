<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Memecah bahan tinta bertanda "(CMYK)"/"CMYK" (Flora, DTF, Sublime) menjadi 4 bahan per warna,
 * seperti tinta Epson S80670: stok, pembelian, dan pemakaian tiap warna dilacak sendiri.
 * Bahan lama menjadi CYAN (riwayat & stok ikut), pemakaian per m2 dibagi rata ke 4 warna
 * (total sama, jadi biaya tinta di laporan tidak berubah). Idempotent.
 */
class TintaPisahWarnaCommand extends Command
{
    protected $signature = 'data:tinta-pisah-warna {--simulasi : Jalankan lalu batalkan} {--tanpa-backup : Lewati backup otomatis}';
    protected $description = 'Pecah tinta CMYK (Flora, DTF, Sublime) menjadi 4 bahan per warna';

    private const WARNA = ['CY' => 'CYAN', 'MG' => 'MAGENTA', 'YL' => 'YELLOW', 'BK' => 'BLACK'];

    /** kode bahan lama => [awalan nama baru, awalan kode baru] */
    private const TARGET = [
        'TNT-FLORA' => ['TINTA SOLVENT FLORA OUTDOOR', 'TNT-FLORA'],
        'TNT-DTF-CMYK' => ['TINTA DTF', 'TNT-DTF'],
        'TNT-SUBLIME' => ['TINTA SUBLIME', 'TNT-SUBLIME'],
    ];

    public function handle(): int
    {
        if (!$this->option('simulasi') && !$this->option('tanpa-backup')) {
            try {
                $this->info('Backup: ' . BackupService::run('manual')->file_path);
            } catch (\Throwable $e) {
                $this->warn('Backup gagal (' . $e->getMessage() . ') - lanjut.');
            }
        }

        $baru = 0;
        DB::beginTransaction();
        try {
            foreach (self::TARGET as $kodeLama => [$awalanNama, $awalanKode]) {
                // Bisa sudah dipecah (kode lama sudah menjadi -CY) pada jalan ulang.
                $lama = DB::table('bahan_baku')->where('kode_bahan', $kodeLama)->first()
                    ?? DB::table('bahan_baku')->where('kode_bahan', $awalanKode . '-CY')->first();
                if (!$lama) {
                    $this->warn("Bahan {$kodeLama} tidak ditemukan - dilewati.");
                    continue;
                }

                $pemakaianLama = DB::table('pemakaian_tinta')->where('id_bahan', $lama->id_bahan)->where('dasar', 'm2')->first();
                $totalPerM2 = $pemakaianLama ? (float) $pemakaianLama->pemakaian : 0.0;
                if ($totalPerM2 > 0 && !DB::table('bahan_baku')->where('kode_bahan', $awalanKode . '-MG')->exists()) {
                    $perWarna = round($totalPerM2 / count(self::WARNA), 6);
                } else {
                    $perWarna = $pemakaianLama ? (float) $pemakaianLama->pemakaian : 0.0; // jalan ulang: jangan dibagi lagi
                }

                foreach (self::WARNA as $kode => $warna) {
                    $nama = $awalanNama . ' ' . $warna;
                    $kodeBahan = $awalanKode . '-' . $kode;

                    if ($kode === 'CY') {
                        DB::table('bahan_baku')->where('id_bahan', $lama->id_bahan)->update(['nama_bahan' => $nama, 'kode_bahan' => $kodeBahan, 'updated_at' => now()]);
                        $idBahan = (int) $lama->id_bahan;
                    } else {
                        $ada = DB::table('bahan_baku')->where('kode_bahan', $kodeBahan)->first();
                        if ($ada) {
                            $idBahan = (int) $ada->id_bahan;
                        } else {
                            $idBahan = DB::table('bahan_baku')->insertGetId([
                                'kode_bahan' => $kodeBahan, 'nama_bahan' => $nama, 'id_mesin' => $lama->id_mesin, 'id_kategori' => $lama->id_kategori,
                                'id_merk' => $lama->id_merk, 'id_jenis_bahan' => $lama->id_jenis_bahan, 'stok' => 0, 'stok_minimal' => $lama->stok_minimal,
                                'id_satuan_stok' => $lama->id_satuan_stok, 'id_satuan_hitung' => $lama->id_satuan_hitung, 'nilai_konversi' => $lama->nilai_konversi,
                                'satuan_stok' => $lama->satuan_stok, 'harga_rata_rata' => $lama->harga_rata_rata, 'status' => 'aktif',
                                'created_at' => now(), 'updated_at' => now(),
                            ]);
                            $baru++;
                        }
                    }

                    if ($perWarna > 0) {
                        DB::table('pemakaian_tinta')->updateOrInsert(
                            ['id_mesin' => $lama->id_mesin, 'id_bahan' => $idBahan, 'dasar' => 'm2'],
                            ['pemakaian' => $perWarna, 'is_active' => 1, 'keterangan' => 'Estimasi awal: total pemakaian dibagi rata 4 warna - koreksi dengan pemakaian nyata', 'created_at' => now(), 'updated_at' => now()]
                        );
                    }
                }
            }

            $this->option('simulasi') ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('simulasi')) {
            $this->warn('SIMULASI - dibatalkan.');
        }
        $this->info("Bahan tinta baru per warna: {$baru}.");

        return self::SUCCESS;
    }
}
