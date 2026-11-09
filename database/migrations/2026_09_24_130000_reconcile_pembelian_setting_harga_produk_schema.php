<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drift skema akibat create_complex_system_tables (2026_02_25_111057) yang
 * membuat tabel dengan kolom "generasi lama", sementara add_missing_erp_tables
 * dan reconcile_inventory_module_schema hanya menambah kolom (guard hasTable /
 * hasColumn) tanpa menyingkirkan kolom lama yang NOT NULL. Hasilnya migrate
 * dari nol berbeda dari DB dev/production dan controller gagal menyimpan:
 *
 *  - pembelian_bahan : no_faktur_supplier, id_supplier, tgl_pembelian, subtotal
 *                      NOT NULL yang tidak pernah diisi PembelianBahanController.
 *  - setting_harga   : satuan_berlaku NOT NULL tanpa default; id_satuan,
 *                      qty_berlaku, id_satuan_berlaku & timestamps tidak ada.
 *  - produk          : id_kategori NOT NULL yang tidak diisi ProdukController;
 *                      barcode, min_panjang, min_lebar & timestamps tidak ada.
 *
 * Idempotent & aman: DB dev/production yang sudah benar tidak berubah. Kolom
 * lama hanya di-drop bila tabel kosong (instalasi baru); bila ada data, cukup
 * dijadikan nullable supaya tidak ada data yang hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->pembelianBahan();
        $this->settingHarga();
        $this->produk();
    }

    public function down(): void
    {
        // Tidak dibalik: skema lama adalah drift yang tidak diinginkan.
    }

    private function pembelianBahan(): void
    {
        if (!Schema::hasTable('pembelian_bahan')) {
            return;
        }

        // Kolom yang dipakai kode (sama dengan dev), bila belum ada.
        Schema::table('pembelian_bahan', function (Blueprint $t) {
            if (!Schema::hasColumn('pembelian_bahan', 'no_faktur')) {
                $t->string('no_faktur')->nullable();
            }
            if (!Schema::hasColumn('pembelian_bahan', 'tgl_transaksi')) {
                $t->date('tgl_transaksi')->nullable();
            }
        });

        $kosong = DB::table('pembelian_bahan')->count() === 0;
        $legacy = ['no_faktur_supplier', 'id_supplier', 'tgl_pembelian', 'subtotal', 'pajak'];

        foreach ($legacy as $col) {
            if (!Schema::hasColumn('pembelian_bahan', $col)) {
                continue;
            }
            if ($kosong) {
                $this->dropForeignKeysOn('pembelian_bahan', $col);
                Schema::table('pembelian_bahan', fn (Blueprint $t) => $t->dropColumn($col));
            } else {
                $this->makeNullable('pembelian_bahan', $col);
            }
        }

        // status_pajak di dev varchar(50) NULL; versi lama enum NOT NULL tanpa default.
        if (Schema::hasColumn('pembelian_bahan', 'status_pajak')) {
            DB::statement('ALTER TABLE pembelian_bahan MODIFY status_pajak VARCHAR(50) NULL');
        }

        // no_faktur & tgl_transaksi NOT NULL seperti dev, hanya bila tidak ada NULL.
        if (!DB::table('pembelian_bahan')->whereNull('no_faktur')->exists()) {
            DB::statement('ALTER TABLE pembelian_bahan MODIFY no_faktur VARCHAR(255) NOT NULL');
            if (!$this->indexExists('pembelian_bahan', 'pembelian_bahan_no_faktur_unique')) {
                DB::statement('ALTER TABLE pembelian_bahan ADD UNIQUE pembelian_bahan_no_faktur_unique (no_faktur)');
            }
        }
        if (!DB::table('pembelian_bahan')->whereNull('tgl_transaksi')->exists()) {
            DB::statement('ALTER TABLE pembelian_bahan MODIFY tgl_transaksi DATE NOT NULL');
        }
    }

    private function settingHarga(): void
    {
        if (!Schema::hasTable('setting_harga')) {
            return;
        }

        Schema::table('setting_harga', function (Blueprint $t) {
            if (!Schema::hasColumn('setting_harga', 'id_satuan')) {
                $t->unsignedBigInteger('id_satuan')->nullable();
            }
            if (!Schema::hasColumn('setting_harga', 'qty_berlaku')) {
                $t->integer('qty_berlaku')->nullable()->default(1);
            }
            if (!Schema::hasColumn('setting_harga', 'id_satuan_berlaku')) {
                $t->unsignedBigInteger('id_satuan_berlaku')->nullable();
            }
            if (!Schema::hasColumn('setting_harga', 'created_at')) {
                $t->timestamps();
            }
        });

        if (Schema::hasColumn('setting_harga', 'satuan_berlaku')) {
            if (DB::table('setting_harga')->count() === 0) {
                Schema::table('setting_harga', fn (Blueprint $t) => $t->dropColumn('satuan_berlaku'));
            } else {
                $this->makeNullable('setting_harga', 'satuan_berlaku');
            }
        }
    }

    private function produk(): void
    {
        if (!Schema::hasTable('produk')) {
            return;
        }

        Schema::table('produk', function (Blueprint $t) {
            if (!Schema::hasColumn('produk', 'barcode')) {
                $t->string('barcode', 50)->nullable();
            }
            if (!Schema::hasColumn('produk', 'min_panjang')) {
                $t->decimal('min_panjang', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('produk', 'min_lebar')) {
                $t->decimal('min_lebar', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('produk', 'created_at')) {
                $t->timestamps();
            }
        });

        if (Schema::hasColumn('produk', 'id_kategori')) {
            if (DB::table('produk')->count() === 0) {
                $this->dropForeignKeysOn('produk', 'id_kategori');
                Schema::table('produk', fn (Blueprint $t) => $t->dropColumn('id_kategori'));
            } else {
                $this->makeNullable('produk', 'id_kategori');
            }
        }
    }

    /** Jadikan kolom nullable dengan mempertahankan tipenya. */
    private function makeNullable(string $table, string $column): void
    {
        $row = DB::selectOne(
            'SELECT COLUMN_TYPE AS tipe FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::getDatabaseName(), $table, $column]
        );
        if ($row) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$row->tipe} NULL");
        }
    }

    private function dropForeignKeysOn(string $table, string $column): void
    {
        $fks = DB::select(
            'SELECT CONSTRAINT_NAME AS nama FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
             AND REFERENCED_TABLE_NAME IS NOT NULL',
            [DB::getDatabaseName(), $table, $column]
        );
        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk->nama}`");
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::selectOne(
            'SELECT 1 AS ada FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [DB::getDatabaseName(), $table, $index]
        ) !== null;
    }
};
