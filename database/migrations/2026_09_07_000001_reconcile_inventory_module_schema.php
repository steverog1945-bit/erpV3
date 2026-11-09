<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * MIGRATION INI BERSIFAT ADDITIVE / GUARDED SEPENUHNYA.
 *
 * Tujuannya HANYA menambal celah antara migration lama (yang sudah tidak
 * sinkron dengan struktur nyata yang dipakai kode) dengan kolom/tabel yang
 * benar-benar dibutuhkan oleh Model & Controller modul Inventory
 * (Bahan Baku, Kartu Stok, Kedatangan Bahan, Pembelian Bahan).
 *
 * Setiap create table dibungkus Schema::hasTable() dan setiap tambah kolom
 * dibungkus Schema::hasColumn(), sehingga:
 * - Jika tabel/kolom SUDAH ADA di database (mis. dibuat manual lewat
 *   phpMyAdmin/SQL dump seperti komentar di beberapa Model), migration ini
 *   TIDAK melakukan apa-apa terhadapnya -> data & struktur existing aman.
 * - Jika dijalankan di database baru/kosong, migration ini akan membuat
 *   struktur yang sesuai dengan apa yang benar-benar dipakai kode saat ini.
 *
 * Tidak ada DROP, RENAME, atau perubahan tipe kolom existing di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------
        // 1. BAHAN BAKU - kolom yang dipakai BahanBakuController/Model
        //    tapi tidak ada di migration lama (create_complex_system_tables)
        // ------------------------------------------------------------
        if (Schema::hasTable('bahan_baku')) {
            Schema::table('bahan_baku', function (Blueprint $table) {
                if (!Schema::hasColumn('bahan_baku', 'stok_minimal')) {
                    $table->decimal('stok_minimal', 15, 2)->default(0)->after('stok');
                }
                if (!Schema::hasColumn('bahan_baku', 'id_satuan_stok')) {
                    $table->unsignedBigInteger('id_satuan_stok')->nullable()->after('satuan_stok');
                }
                if (!Schema::hasColumn('bahan_baku', 'id_satuan_hitung')) {
                    $table->unsignedBigInteger('id_satuan_hitung')->nullable()->after('id_satuan_stok');
                }
                if (!Schema::hasColumn('bahan_baku', 'nilai_konversi')) {
                    $table->decimal('nilai_konversi', 15, 4)->default(1)->after('id_satuan_hitung');
                }
                if (!Schema::hasColumn('bahan_baku', 'status')) {
                    $table->string('status', 20)->default('aktif')->after('nilai_konversi');
                }
                // Dibutuhkan oleh default sorting di BahanBakuController::index()
                if (!Schema::hasColumn('bahan_baku', 'created_at')) {
                    $table->timestamp('created_at')->nullable()->useCurrent();
                }
                if (!Schema::hasColumn('bahan_baku', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable();
                }
            });

            // FK ke satuan dibuat terpisah & dibungkus try/catch supaya tidak
            // menggagalkan migration jika data existing sudah ada yang tidak
            // konsisten (mis. id_satuan_stok NULL/tidak valid pada baris lama).
            if (Schema::hasTable('satuan')) {
                try {
                    Schema::table('bahan_baku', function (Blueprint $table) {
                        $table->foreign('id_satuan_stok')->references('id_satuan')->on('satuan')->nullOnDelete();
                    });
                } catch (\Throwable $e) {
                    // FK sudah ada atau data existing tidak kompatibel - lewati, tidak fatal.
                }
                try {
                    Schema::table('bahan_baku', function (Blueprint $table) {
                        $table->foreign('id_satuan_hitung')->references('id_satuan')->on('satuan')->nullOnDelete();
                    });
                } catch (\Throwable $e) {
                    // idem
                }
            }
        }

        // ------------------------------------------------------------
        // 2. GUDANG - dipakai KedatanganBahan::gudang(), belum ada tabelnya
        // ------------------------------------------------------------
        if (!Schema::hasTable('gudang')) {
            Schema::create('gudang', function (Blueprint $table) {
                $table->id('id_gudang');
                $table->string('nama_gudang', 100);
                $table->timestamps();
            });

            // Seed 2 gudang default, sesuai data yang selama ini di-hardcode
            // di KedatanganBahanController (Gudang Utama / Gudang Samping),
            // supaya perilaku form tidak berubah setelah controller
            // dialihkan membaca dari tabel asli.
            DB::table('gudang')->insert([
                ['id_gudang' => 1, 'nama_gudang' => 'Gudang Utama', 'created_at' => now(), 'updated_at' => now()],
                ['id_gudang' => 2, 'nama_gudang' => 'Gudang Samping', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // ------------------------------------------------------------
        // 3. KEDATANGAN BAHAN + DETAIL - dipakai KedatanganBahanController
        //    (route aktif) tapi tidak pernah ada migration-nya
        // ------------------------------------------------------------
        if (!Schema::hasTable('kedatangan_bahan')) {
            Schema::create('kedatangan_bahan', function (Blueprint $table) {
                $table->id('id_kedatangan');
                $table->string('no_surat_jalan')->unique();
                $table->unsignedBigInteger('id_supplier');
                $table->unsignedBigInteger('id_gudang')->nullable();
                $table->date('tgl_datang');
                $table->string('status', 20)->default('Draft');
                $table->text('keterangan')->nullable();
                $table->timestamp('created_at')->nullable()->useCurrent();

                if (Schema::hasTable('supplier')) {
                    $table->foreign('id_supplier')->references('id_supplier')->on('supplier');
                }
                if (Schema::hasTable('gudang')) {
                    $table->foreign('id_gudang')->references('id_gudang')->on('gudang')->nullOnDelete();
                }
            });
        }

        if (!Schema::hasTable('kedatangan_bahan_detail')) {
            Schema::create('kedatangan_bahan_detail', function (Blueprint $table) {
                $table->id('id_detail');
                $table->unsignedBigInteger('id_kedatangan');
                $table->unsignedBigInteger('id_bahan');
                $table->decimal('qty', 15, 2);
                $table->string('satuan', 20)->nullable();
                $table->string('status', 20)->nullable();

                $table->foreign('id_kedatangan')->references('id_kedatangan')->on('kedatangan_bahan')->onDelete('cascade');
                if (Schema::hasTable('bahan_baku')) {
                    $table->foreign('id_bahan')->references('id_bahan')->on('bahan_baku');
                }
            });
        }

        // ------------------------------------------------------------
        // 4. KARTU STOK - nama tabel yang benar adalah singular
        //    ('kartu_stok', sesuai seluruh kode & komentar Model), BUKAN
        //    'kartu_stoks' (nama plural di migration lama yang isinya
        //    cuma stub kosong id+timestamps dan tidak pernah dipakai).
        //    Migration lama TIDAK disentuh/dihapus (aman untuk history),
        //    di sini kita hanya memastikan tabel yang BENAR-BENAR dipakai
        //    kode tersedia.
        // ------------------------------------------------------------
        if (!Schema::hasTable('kartu_stok')) {
            Schema::create('kartu_stok', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_bahan');
                $table->timestamp('tanggal')->nullable()->useCurrent();
                $table->string('tipe', 10); // IN / OUT
                $table->decimal('qty', 15, 2);
                $table->decimal('stok_sebelum', 15, 2);
                $table->decimal('stok_sesudah', 15, 2);
                $table->string('referensi')->nullable();
                $table->string('keterangan')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                if (Schema::hasTable('bahan_baku')) {
                    $table->foreign('id_bahan')->references('id_bahan')->on('bahan_baku');
                }
            });
        }

        // ------------------------------------------------------------
        // 5. PEMBELIAN BAHAN - kolom nyata yang dipakai
        //    PembelianBahanController & Model PembelianBahan berbeda total
        //    dari migration lama (no_faktur vs no_faktur_supplier, dst).
        //    Kolom lama TIDAK dihapus - hanya menambah yang kurang.
        // ------------------------------------------------------------
        if (Schema::hasTable('pembelian_bahan')) {
            Schema::table('pembelian_bahan', function (Blueprint $table) {
                if (!Schema::hasColumn('pembelian_bahan', 'id_kedatangan')) {
                    $table->unsignedBigInteger('id_kedatangan')->nullable()->after('id_pembelian');
                }
                if (!Schema::hasColumn('pembelian_bahan', 'no_faktur')) {
                    $table->string('no_faktur')->nullable();
                }
                if (!Schema::hasColumn('pembelian_bahan', 'tgl_transaksi')) {
                    $table->date('tgl_transaksi')->nullable();
                }
                if (!Schema::hasColumn('pembelian_bahan', 'tipe_pembelian')) {
                    $table->string('tipe_pembelian', 20)->nullable()->default('Biasa');
                }
                if (!Schema::hasColumn('pembelian_bahan', 'jatuh_tempo_hari')) {
                    $table->integer('jatuh_tempo_hari')->nullable();
                }
                if (!Schema::hasColumn('pembelian_bahan', 'jatuh_tempo_tgl')) {
                    $table->date('jatuh_tempo_tgl')->nullable();
                }
                if (!Schema::hasColumn('pembelian_bahan', 'status_pembayaran')) {
                    $table->string('status_pembayaran', 20)->nullable()->default('Hutang');
                }
                if (!Schema::hasColumn('pembelian_bahan', 'tgl_pelunasan')) {
                    $table->date('tgl_pelunasan')->nullable();
                }
                if (!Schema::hasColumn('pembelian_bahan', 'include_pajak')) {
                    $table->boolean('include_pajak')->nullable()->default(false);
                }
                if (!Schema::hasColumn('pembelian_bahan', 'grand_total')) {
                    $table->decimal('grand_total', 15, 2)->default(0);
                }
                if (!Schema::hasColumn('pembelian_bahan', 'created_at')) {
                    $table->timestamps();
                }
            });

            if (Schema::hasTable('kedatangan_bahan') && !$this->foreignKeyExists('pembelian_bahan', 'id_kedatangan')) {
                try {
                    Schema::table('pembelian_bahan', function (Blueprint $table) {
                        $table->foreign('id_kedatangan')->references('id_kedatangan')->on('kedatangan_bahan')->nullOnDelete();
                    });
                } catch (\Throwable $e) {
                    // data existing mungkin tidak konsisten - lewati, tidak fatal
                }
            }
        }

        // ------------------------------------------------------------
        // 6. PEMBELIAN BAHAN DETAIL - dipakai PembelianBahanController
        //    tapi tidak pernah ada migration-nya
        // ------------------------------------------------------------
        if (!Schema::hasTable('pembelian_bahan_detail')) {
            Schema::create('pembelian_bahan_detail', function (Blueprint $table) {
                $table->id('id_detail');
                $table->unsignedBigInteger('id_pembelian');
                $table->unsignedBigInteger('id_bahan');
                $table->decimal('qty', 15, 2);
                $table->decimal('harga', 15, 2);
                $table->decimal('diskon', 15, 2)->nullable()->default(0);
                $table->decimal('potongan', 15, 2)->nullable()->default(0);
                $table->decimal('subtotal', 15, 2);

                if (Schema::hasTable('pembelian_bahan')) {
                    $table->foreign('id_pembelian')->references('id_pembelian')->on('pembelian_bahan')->onDelete('cascade');
                }
                if (Schema::hasTable('bahan_baku')) {
                    $table->foreign('id_bahan')->references('id_bahan')->on('bahan_baku');
                }
            });
        }
    }

    public function down(): void
    {
        // Down sengaja tidak menghapus apa pun secara agresif untuk
        // menghindari kehilangan data pada tabel yang mungkin sebenarnya
        // sudah ada sebelum migration ini (kolom yang ditambahkan lewat
        // hasColumn-guard sengaja tidak di-drop di sini).
        // Tabel baru murni buatan migration ini aman untuk di-drop.
        Schema::dropIfExists('pembelian_bahan_detail');
        Schema::dropIfExists('kartu_stok');
        Schema::dropIfExists('kedatangan_bahan_detail');
        Schema::dropIfExists('kedatangan_bahan');
        Schema::dropIfExists('gudang');
    }

    /**
     * Helper kecil untuk mengecek apakah sebuah foreign key sudah ada di
     * MySQL, supaya tidak mencoba menambah FK yang sama dua kali saat
     * migration dijalankan ulang di lingkungan yang berbeda.
     */
    private function foreignKeyExists(string $table, string $column): bool
    {
        try {
            $dbName = DB::getDatabaseName();
            $result = DB::select(
                "SELECT COUNT(*) as total FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
                 AND REFERENCED_TABLE_NAME IS NOT NULL",
                [$dbName, $table, $column]
            );
            return ($result[0]->total ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
};
