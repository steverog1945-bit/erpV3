<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Data riil Epson S80670 (indoor) & pendukung finishing Roland G540:
 *  - tinta dipecah per warna (9 bahan terpisah, tidak lagi disatukan sebagai CMYK) + cleaner memakai satuan kartrid,
 *  - Roland G540 dicatat sebagai ASET mesin pendukung (bukan divisi produksi) beserta pisau cutter & bantalan pisau
 *    sebagai bahan habis pakai (harga diisi saat pembelian).
 * Idempotent: aman dijalankan ulang.
 */
class TintaEpsonRolandCommand extends Command
{
    protected $signature = 'data:tinta-epson-roland {--simulasi : Jalankan lalu batalkan} {--tanpa-backup : Lewati backup otomatis}';
    protected $description = 'Pecah tinta Epson S80670 per warna + cleaner + Roland G540 (aset) dan perlengkapannya';

    /** kode => nama warna (set UltraChrome GS3 tanpa White/Silver) */
    private const WARNA = [
        'CY' => 'CYAN', 'LC' => 'LIGHT CYAN', 'MG' => 'MAGENTA', 'LM' => 'LIGHT MAGENTA', 'YL' => 'YELLOW',
        'BK' => 'BLACK', 'LK' => 'LIGHT BLACK', 'OR' => 'ORANGE', 'RD' => 'RED',
    ];

    /** Total pemakaian tinta lama (ml per m2) dibagi rata ke sembilan warna sampai ada data pemakaian nyata. */
    private const TOTAL_ML_PER_M2 = 10.0;

    public function handle(): int
    {
        if (!$this->option('simulasi') && !$this->option('tanpa-backup')) {
            try {
                $this->info('Backup: ' . BackupService::run('manual')->file_path);
            } catch (\Throwable $e) {
                $this->warn('Backup gagal (' . $e->getMessage() . ') - lanjut.');
            }
        }

        $lama = DB::table('bahan_baku')->where('kode_bahan', 'TNT-S80670')->first();
        if (!$lama) {
            $lama = DB::table('bahan_baku')->where('kode_bahan', 'TNT-S80670-CY')->first();
        }
        if (!$lama) {
            $this->error('Bahan tinta Epson S80670 acuan tidak ditemukan.');

            return self::FAILURE;
        }

        $baru = 0;
        DB::beginTransaction();
        try {
            $pakaiPerWarna = round(self::TOTAL_ML_PER_M2 / count(self::WARNA), 6);
            $idMesinEpson = (int) $lama->id_mesin;

            foreach (self::WARNA as $kode => $warna) {
                $nama = 'TINTA ECO-SOLVENT EPSON S80670 ' . $warna;
                $kodeBahan = 'TNT-S80670-' . $kode;

                if ($kode === 'CY') {
                    // Bahan lama (riwayat stok & pemakaian menempel di sini) menjadi CYAN.
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

                // Hanya diisi bila belum ada: angka yang sudah dikoreksi dengan pemakaian nyata tidak ditimpa saat dijalankan ulang.
                $kunci = ['id_mesin' => $idMesinEpson, 'id_bahan' => $idBahan, 'dasar' => 'm2'];
                if (!DB::table('pemakaian_tinta')->where($kunci)->exists()) {
                    DB::table('pemakaian_tinta')->insert($kunci + [
                        'pemakaian' => $pakaiPerWarna, 'is_active' => 1, 'keterangan' => 'Estimasi awal: total 10 ml/m2 dibagi rata 9 warna - koreksi dengan pemakaian nyata',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            // Cleaner: satuan kartrid (botol) 350 ml, bukan "ml" dengan harga per kartrid.
            $idBotol = (int) DB::table('satuan')->where('nama_satuan', 'botol')->value('id_satuan');
            $idMl = (int) DB::table('satuan')->where('nama_satuan', 'ml')->value('id_satuan');
            if ($idBotol && $idMl) {
                DB::table('bahan_baku')->where('kode_bahan', 'CLEANER-EPSON')->update([
                    'id_satuan_stok' => $idBotol, 'id_satuan_hitung' => $idMl, 'nilai_konversi' => 350, 'satuan_stok' => 'botol', 'updated_at' => now(),
                ]);
            }

            // Roland G540: aset mesin pendukung (tidak tampil sebagai divisi produksi).
            $idRoland = DB::table('mesin')->where('nama_mesin', 'ROLAND G540')->value('id_mesin')
                ?? DB::table('mesin')->insertGetId(['nama_mesin' => 'ROLAND G540', 'tipe_mesin' => 'Cutting Plotter', 'tampil_divisi' => 0]);
            DB::table('mesin')->where('id_mesin', $idRoland)->update(['tampil_divisi' => 0]);

            $idPcs = (int) DB::table('satuan')->where('nama_satuan', 'pcs')->value('id_satuan');
            foreach ([
                'PDK-ROL-PISAU' => 'PISAU CUTTER ROLAND G540',
                'PDK-ROL-BANTALAN' => 'BANTALAN PISAU ROLAND G540',
            ] as $kodeBahan => $nama) {
                if (DB::table('bahan_baku')->where('kode_bahan', $kodeBahan)->exists()) {
                    continue;
                }
                DB::table('bahan_baku')->insert([
                    'kode_bahan' => $kodeBahan, 'nama_bahan' => $nama, 'id_mesin' => $idRoland, 'id_kategori' => $lama->id_kategori,
                    'stok' => 0, 'stok_minimal' => 1, 'id_satuan_stok' => $idPcs ?: null, 'id_satuan_hitung' => $idPcs ?: null, 'nilai_konversi' => 1,
                    'satuan_stok' => 'pcs', 'harga_rata_rata' => 0, 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
                ]);
                $baru++;
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
        $this->info("Bahan baru: {$baru}. Tinta Epson kini 9 warna terpisah; cleaner 1 kartrid = 350 ml; Roland G540 = aset pendukung.");

        return self::SUCCESS;
    }
}
