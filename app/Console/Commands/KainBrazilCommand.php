<?php

namespace App\Console\Commands;

use App\Services\AccountingService;
use App\Services\AuditLogger;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bahan kain jersey riil: Brazil Set KT 190-200 (25,35 kg @ Rp58.000 = Rp1.470.300) didaftarkan di Master Bahan Baku
 * (stok awal + jurnal saldo awal persediaan) dan disinkronkan dengan bahan jersey "Brazil". Idempotent.
 */
class KainBrazilCommand extends Command
{
    protected $signature = 'data:kain-brazil {--tanpa-backup}';
    protected $description = 'Daftarkan kain Brazil Set KT 190-200 ke Master Bahan dan sinkronkan dengan bahan jersey Brazil';

    public function handle(): int
    {
        if (!$this->option('tanpa-backup')) {
            try {
                $this->info('Backup: ' . BackupService::run('manual')->file_path);
            } catch (\Throwable $e) {
                $this->warn('Backup gagal - lanjut.');
            }
        }

        $kode = 'KAIN-BRAZIL-KT190';
        $stok = 25.35;
        $harga = 58000.0;
        $nilai = round($stok * $harga, 2); // 1.470.300

        DB::beginTransaction();
        try {
            $bahan = DB::table('bahan_baku')->where('kode_bahan', $kode)->first();
            if (!$bahan) {
                $idKg = (int) DB::table('satuan')->where('nama_satuan', 'kg')->value('id_satuan');
                $id = DB::table('bahan_baku')->insertGetId([
                    'kode_bahan' => $kode, 'nama_bahan' => 'KAIN BRAZIL SET KT 190-200',
                    'id_mesin' => (int) DB::table('mesin')->where('nama_mesin', 'LAINNYA')->value('id_mesin') ?: (int) DB::table('mesin')->value('id_mesin'),
                    'id_kategori' => (int) DB::table('kategori')->where('nama_kategori', 'Lain-lain')->value('id_kategori') ?: (int) DB::table('kategori')->value('id_kategori'),
                    'id_satuan_stok' => $idKg, 'id_satuan_hitung' => $idKg, 'nilai_konversi' => 1, 'satuan_stok' => 'kg',
                    'stok' => $stok, 'stok_minimal' => 0, 'harga_rata_rata' => $harga, 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('kartu_stok')->insert([
                    'id_bahan' => $id, 'tanggal' => now(), 'tipe' => 'IN', 'qty' => $stok, 'stok_sebelum' => 0, 'stok_sesudah' => $stok,
                    'referensi' => 'STOK-AWAL', 'keterangan' => 'Stok awal kain Brazil Set KT 190-200 (25,35 kg @ Rp58.000)', 'created_at' => now(), 'updated_at' => now(),
                ]);
                AccountingService::postSaldoAwalPersediaan('STOK-AWAL-' . $kode, "Saldo awal persediaan KAIN BRAZIL SET KT 190-200 ({$stok} kg x Rp 58.000)", $nilai);
                AuditLogger::log('created', 'bahan_baku', $id, null, ['stok' => $stok, 'harga' => $harga], 'Kain Brazil Set KT 190-200 didaftarkan (total Rp ' . number_format($nilai, 0, ',', '.') . ')');
                $this->info("Bahan dibuat (id {$id}), saldo awal persediaan Rp " . number_format($nilai, 0, ',', '.'));
            } else {
                $id = (int) $bahan->id_bahan;
                $this->info('Bahan sudah ada (id ' . $id . ') - tidak diubah.');
            }

            DB::table('jersey_materials')->where('name', 'Brazil')->update(['id_bahan' => $id, 'updated_at' => now()]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Bahan jersey "Brazil" tersinkron dengan Master Bahan.');

        return self::SUCCESS;
    }
}
