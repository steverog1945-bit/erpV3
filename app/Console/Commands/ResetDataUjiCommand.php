<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengosongkan DATA TRANSAKSI/UJI menjelang pemakaian nyata, tanpa menyentuh data master
 * (bahan baku, mesin, kategori, supplier, jasa, produk + BOM/harga, user/role/akses, pengaturan,
 * master jersey, dst). Stok bahan baku & harga rata-rata dipertahankan; kartu stok dimulai ulang
 * dengan satu entri "Saldo Awal" per bahan bila stoknya > 0, supaya kartu stok tetap cocok dengan angka stok.
 *
 * Order jersey riil yang dipertahankan ditentukan lewat --jersey=ID. Jurnal akuntansi dikosongkan;
 * saldo awal (Kas, Kas Kecil, Bank, Piutang, Persediaan, Hutang) ditetapkan sesudahnya lewat
 * `php artisan akuntansi:saldo-awal`.
 */
class ResetDataUjiCommand extends Command
{
    protected $signature = 'data:reset-uji {--jersey= : id order jersey RIIL yang dipertahankan} {--dry-run : hanya tampilkan jumlah baris, tidak menghapus} {--force : jalankan tanpa konfirmasi}';

    protected $description = 'Kosongkan data transaksi/uji (nota, pembelian, kedatangan, jersey uji, jurnal, kas) - data master & stok tetap';

    /** Tabel yang dikosongkan seluruhnya. */
    private const KOSONGKAN = [
        'transaksi', 'transaksi_detail', 'transaksi_detail_finishing', 'pembayaran_riwayat', 'pengambilan', 'nota_edit_request',
        'qc_inspeksi', 'rework', 'waste_log', 'order_berkas', 'quotations', 'quotation_items',
        'kas_harian', 'kas_manual', 'kas_kecil', 'konsumen_deposit',
        'purchase_requests', 'purchase_request_details', 'purchase_orders', 'purchase_order_details',
        'pembelian_bahan', 'pembelian_bahan_detail', 'pembayaran_hutang', 'kedatangan_bahan', 'kedatangan_bahan_detail',
        'stock_opnames', 'stock_opname_details', 'kartu_stok', 'jurnal_umum',
        'approval_requests', 'audit_logs', 'document_number_counters',
        'jersey_order_payments', 'jersey_order_potong_stok',
    ];

    public function handle(): int
    {
        // Mengosongkan seluruh transaksi: tidak pernah boleh jalan di production, termasuk dengan --force.
        if (app()->environment('production')) {
            $this->error('Ditolak: perintah ini menghapus seluruh data transaksi dan tidak boleh dijalankan di lingkungan production.');

            return self::FAILURE;
        }

        $idJersey = $this->option('jersey') !== null ? (int) $this->option('jersey') : null;
        $dry = (bool) $this->option('dry-run');

        if ($idJersey && !DB::table('jersey_orders')->where('id', $idJersey)->exists()) {
            $this->error("Order jersey #{$idJersey} tidak ditemukan.");

            return self::FAILURE;
        }

        $this->info($dry ? '=== DRY RUN: tidak ada yang dihapus ===' : '=== Reset data uji ===');
        $rows = [];
        foreach (self::KOSONGKAN as $t) {
            if (Schema::hasTable($t)) {
                $rows[] = [$t, DB::table($t)->count(), 'dikosongkan'];
            }
        }
        $jerseyHapus = DB::table('jersey_orders')->when($idJersey, fn ($q) => $q->where('id', '!=', $idJersey))->count();
        $rows[] = ['jersey_orders (+ baris, size, bahan, item)', $jerseyHapus, $idJersey ? "dihapus, order #{$idJersey} dipertahankan" : 'dihapus semua'];
        $rows[] = ['konsumen berawalan "DEMO "', DB::table('konsumen')->where('nama_konsumen', 'like', 'DEMO %')->count(), 'dihapus (konsumen lain tidak diubah)'];
        $this->table(['Tabel', 'Baris', 'Tindakan'], $rows);

        $bahanStok = DB::table('bahan_baku')->where('stok', '>', 0)->get(['id_bahan', 'stok', 'harga_rata_rata']);
        $nilaiPersediaan = round($bahanStok->sum(fn ($b) => (float) $b->stok * (float) $b->harga_rata_rata), 2);
        $this->line('Bahan baku bersaldo stok > 0: ' . $bahanStok->count() . ' | nilai persediaan: Rp ' . number_format($nilaiPersediaan, 0, ',', '.'));

        if ($dry) {
            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('Lanjutkan? Backup otomatis dibuat dulu.', false)) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        $log = BackupService::run('manual');
        $this->info("Backup dibuat: {$log->file_path}");

        Schema::disableForeignKeyConstraints();
        try {
            foreach (self::KOSONGKAN as $t) {
                if (Schema::hasTable($t)) {
                    DB::table($t)->truncate();
                }
            }

            $idsHapus = DB::table('jersey_orders')->when($idJersey, fn ($q) => $q->where('id', '!=', $idJersey))->pluck('id');
            $idsLine = DB::table('jersey_order_lines')->whereIn('jersey_order_id', $idsHapus)->pluck('id');
            foreach (['jersey_order_items', 'jersey_order_size_quantities', 'jersey_order_line_materials'] as $t) {
                if (Schema::hasTable($t)) {
                    DB::table($t)->whereIn('jersey_order_line_id', $idsLine)->delete();
                }
            }
            DB::table('jersey_order_lines')->whereIn('id', $idsLine)->delete();
            DB::table('jersey_orders')->whereIn('id', $idsHapus)->delete();

            DB::table('konsumen')->where('nama_konsumen', 'like', 'DEMO %')->delete();

            // Order jersey yang dipertahankan: dianggap tercakup saldo awal piutang, jadi tidak dijurnal ulang.
            if ($idJersey) {
                DB::table('jersey_orders')->where('id', $idJersey)->update(['total_terjurnal' => DB::raw('total'), 'stok_dipotong_at' => null]);
            }

            // Kartu stok dimulai ulang: satu entri Saldo Awal per bahan yang stoknya > 0.
            foreach ($bahanStok as $b) {
                DB::table('kartu_stok')->insert([
                    'id_bahan' => $b->id_bahan, 'tanggal' => now(), 'tipe' => 'IN', 'qty' => $b->stok, 'stok_sebelum' => 0, 'stok_sesudah' => $b->stok,
                    'referensi' => 'SALDO-AWAL', 'keterangan' => 'Saldo awal stok (data uji dibersihkan)', 'created_by' => null,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->call('cache:clear');
        $this->info('Selesai. Langkah berikutnya: php artisan akuntansi:saldo-awal (Persediaan = Rp ' . number_format($nilaiPersediaan, 0, ',', '.') . ').');

        return self::SUCCESS;
    }
}
