<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menambah ukuran ANAK (nama unik, tidak bentrok dengan ukuran dewasa) beserta:
 *  - selisih harga persen dari harga tier (kelompok anak lebih murah),
 *  - estimasi pemakaian bahan lengkap (komponen, lengan, kerah) yang diturunkan dari ukuran dewasa S.
 * Idempotent: baris yang sudah ada tidak ditimpa, jadi aman dijalankan ulang.
 */
class JerseySizeAnakCommand extends Command
{
    protected $signature = 'jersey:size-anak {--simulasi : Jalankan lalu batalkan (rollback)} {--tanpa-backup : Lewati backup otomatis}';
    protected $description = 'Tambah ukuran anak (Anak XS 4-5 th s/d Anak 2XL 14-15 th) + estimasi bahan + selisih harga persen';

    /**
     * nama lengkap (ukuran + peruntukan usia) => [faktor pemakaian bahan terhadap dewasa S, selisih harga %, nama lama]
     * Nama lama dipakai untuk mengganti nama baris yang sudah terlanjur dibuat dengan versi sebelumnya.
     */
    private const SIZE_ANAK = [
        'Anak XS (4-5 th)' => [0.50, -35, 'Anak 4'],
        'Anak S (6-7 th)' => [0.58, -30, 'Anak 6'],
        'Anak M (8-9 th)' => [0.66, -25, 'Anak 8'],
        'Anak L (10-11 th)' => [0.75, -20, 'Anak 10'],
        'Anak XL (12-13 th)' => [0.84, -15, 'Anak 12'],
        'Anak 2XL (14-15 th)' => [0.93, -10, 'Anak 14'],
    ];

    /** Tier harga anak = harga tier dewasa x faktor ini (dibulatkan ke Rp500). Bisa diubah di Master Data > Tier Harga. */
    private const FAKTOR_HARGA_ANAK = 0.80;

    /** Selisih % antar usia relatif terhadap tier anak (usia lebih kecil sedikit lebih murah, lebih besar sedikit lebih mahal). */
    private const PERSEN_USIA = ['Anak XS (4-5 th)' => -10, 'Anak S (6-7 th)' => -6, 'Anak M (8-9 th)' => -3, 'Anak L (10-11 th)' => 0, 'Anak XL (12-13 th)' => 4, 'Anak 2XL (14-15 th)' => 8];
    /** Persen versi awal (relatif ke harga dewasa) - hanya baris yang masih memakai nilai ini yang diganti. */
    private const PERSEN_LAMA = ['Anak XS (4-5 th)' => -35, 'Anak S (6-7 th)' => -30, 'Anak M (8-9 th)' => -25, 'Anak L (10-11 th)' => -20, 'Anak XL (12-13 th)' => -15, 'Anak 2XL (14-15 th)' => -10];

    public function handle(): int
    {
        if (!$this->option('simulasi') && !$this->option('tanpa-backup')) {
            try {
                $this->info('Backup: ' . BackupService::run('manual')->file_path);
            } catch (\Throwable $e) {
                $this->warn('Backup gagal (' . $e->getMessage() . ') - lanjut tanpa backup.');
            }
        }

        $dasar = DB::table('jersey_sizes')->where('name', 'S')->first();
        if (!$dasar) {
            $this->error('Ukuran dewasa "S" tidak ditemukan - dipakai sebagai dasar estimasi.');

            return self::FAILURE;
        }

        $baru = 0;
        $estimasi = 0;
        $tierBaru = 0;

        DB::beginTransaction();
        try {
            // Ukuran dewasa digeser ke belakang (urutan tampil): anak dulu, dewasa setelahnya.
            DB::table('jersey_sizes')->where('kelompok', 'dewasa')->where('sort_order', '<', 10)->increment('sort_order', 10);

            $urut = 1;
            foreach (self::SIZE_ANAK as $nama => [$faktor, $persen, $namaLama]) {
                // Ganti nama versi lama (mis. "Anak 4") menjadi nama lengkap dengan peruntukan usia.
                DB::table('jersey_sizes')->where('name', $namaLama)->where('kelompok', 'anak')->update(['name' => $nama, 'updated_at' => now()]);
                $ada = DB::table('jersey_sizes')->where('name', $nama)->first();
                if ($ada) {
                    $idSize = $ada->id;
                } else {
                    $idSize = DB::table('jersey_sizes')->insertGetId([
                        'name' => $nama, 'sort_order' => $urut, 'is_active' => 1, 'price_surcharge' => 0,
                        'kelompok' => 'anak', 'price_percent' => $persen, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $baru++;
                }
                $urut++;

                foreach ([
                    ['jersey_size_component_usage', 'jersey_component_id'],
                    ['jersey_size_sleeve_usage', 'jersey_sleeve_type_id'],
                    ['jersey_size_collar_usage', 'jersey_collar_type_id'],
                ] as [$tabel, $kolom]) {
                    foreach (DB::table($tabel)->where('jersey_size_id', $dasar->id)->get() as $u) {
                        $sudah = DB::table($tabel)->where('jersey_size_id', $idSize)->where($kolom, $u->$kolom)->exists();
                        if ($sudah) {
                            continue;
                        }
                        DB::table($tabel)->insert([
                            'jersey_size_id' => $idSize, $kolom => $u->$kolom,
                            'usage_meter' => round((float) $u->usage_meter * $faktor, 3),
                            'usage_weight_gram' => round((float) $u->usage_weight_gram * $faktor, 2),
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $estimasi++;
                    }
                }
            }

            // Tier harga kelompok ANAK: salinan tier dewasa yang aktif x faktor (baris yang sudah ada tidak ditimpa).
            foreach (DB::table('jersey_price_settings')->where('kelompok', 'dewasa')->where('is_active', 1)->get() as $t) {
                $ada = DB::table('jersey_price_settings')->where('kelompok', 'anak')
                    ->where('jersey_product_type_id', $t->jersey_product_type_id)->where('jersey_category_id', $t->jersey_category_id)
                    ->where('jersey_material_id', $t->jersey_material_id)->where('qty_min', $t->qty_min)->exists();
                if ($ada) {
                    continue;
                }
                DB::table('jersey_price_settings')->insert([
                    'jersey_product_type_id' => $t->jersey_product_type_id, 'jersey_category_id' => $t->jersey_category_id, 'jersey_material_id' => $t->jersey_material_id,
                    'qty_min' => $t->qty_min, 'qty_max' => $t->qty_max, 'price' => round((float) $t->price * self::FAKTOR_HARGA_ANAK / 500) * 500,
                    'is_active' => 1, 'kelompok' => 'anak', 'created_at' => now(), 'updated_at' => now(),
                ]);
                $tierBaru++;
            }

            // Selisih persen antar usia (relatif ke tier anak) - hanya menggantikan nilai bawaan versi awal.
            foreach (self::PERSEN_USIA as $nama => $persen) {
                DB::table('jersey_sizes')->where('name', $nama)->where('price_percent', self::PERSEN_LAMA[$nama])->update(['price_percent' => $persen, 'updated_at' => now()]);
            }

            if ($this->option('simulasi')) {
                DB::rollBack();
                $this->warn('SIMULASI - dibatalkan.');
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Ukuran anak baru: {$baru}; baris estimasi bahan ditambah: {$estimasi}; tier harga anak baru: {$tierBaru}.");

        return self::SUCCESS;
    }
}
