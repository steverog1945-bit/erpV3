<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Menambah field yang dibutuhkan modul SPK & Produksi.
 * Semua perubahan bersifat aditif (kolom baru nullable/default, enum lama
 * dipertahankan) - tidak ada data existing yang diubah atau dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transaksi_detail')) {
            Schema::table('transaksi_detail', function (Blueprint $table) {
                if (!Schema::hasColumn('transaksi_detail', 'status_urgent')) {
                    $table->boolean('status_urgent')->default(false)->after('qty');
                }
                if (!Schema::hasColumn('transaksi_detail', 'qty_cetak')) {
                    $table->integer('qty_cetak')->nullable()->after('status_urgent');
                }
                if (!Schema::hasColumn('transaksi_detail', 'catatan_produksi')) {
                    $table->text('catatan_produksi')->nullable()->after('qty_cetak');
                }
            });

// SQLite-compatible: DB::selectOne("SHOW COLUMNS...") does not work on SQLite.
            $column = null;
            $conn = DB::connection();
            if ($conn->getDriverName() === 'sqlite') {
                $row = DB::selectOne("SELECT type FROM pragma_table_info('transaksi_detail') WHERE name = 'status_spk'");
                $column = $row ? $row->type : null;
            } else {
                $column = DB::selectOne("SHOW COLUMNS FROM transaksi_detail WHERE Field = 'status_spk'");
            }

            if ($column && strpos($column->Type ?? '', "'Batal'") === false) {
                // SQLite does not support ALTER TABLE MODIFY ENUM. Use a simple add column instead.
                $table = $conn->getSchemaBuilder();
                if (!$table->hasColumn('transaksi_detail', 'status_spk')) {
                    $table->string('status_spk', 20)->default('Antre')->change();
                }
            }
        }

        // Daftarkan menu SPK & Produksi supaya bisa diatur lewat Setting Akses
        // yang sudah ada (bukan bikin sistem permission baru).
        if (Schema::hasTable('menus')) {
            $existing = DB::table('menus')->whereIn('url_link', ['spk', 'produksi'])->pluck('url_link')->all();
            $maxId = (int) DB::table('menus')->max('id_menu');

            $toInsert = [];
            if (!in_array('spk', $existing)) {
                $toInsert[] = ['id_menu' => ++$maxId, 'nama_menu' => 'SPK', 'url_link' => 'spk', 'parent_id' => 0];
            }
            if (!in_array('produksi', $existing)) {
                $toInsert[] = ['id_menu' => ++$maxId, 'nama_menu' => 'Produksi', 'url_link' => 'produksi', 'parent_id' => 0];
            }

            if (!empty($toInsert)) {
                DB::table('menus')->insert($toInsert);

                // Beri akses ke role Administrator (id_role = 1) secara default,
                // supaya menu langsung terlihat oleh admin. Role lain tetap
                // seperti semula, diatur manual lewat Setting Akses oleh admin.
                if (Schema::hasTable('role_permissions') && Schema::hasTable('roles')) {
                    $adminRole = DB::table('roles')->where('nama_role', 'Administrator')->first();
                    if ($adminRole) {
                        foreach ($toInsert as $menu) {
                            $already = DB::table('role_permissions')
                                ->where('id_role', $adminRole->id_role)
                                ->where('id_menu', $menu['id_menu'])
                                ->exists();
                            if (!$already) {
                                DB::table('role_permissions')->insert([
                                    'id_role' => $adminRole->id_role,
                                    'id_menu' => $menu['id_menu'],
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transaksi_detail')) {
            Schema::table('transaksi_detail', function (Blueprint $table) {
                if (Schema::hasColumn('transaksi_detail', 'catatan_produksi')) {
                    $table->dropColumn('catatan_produksi');
                }
                if (Schema::hasColumn('transaksi_detail', 'qty_cetak')) {
                    $table->dropColumn('qty_cetak');
                }
                if (Schema::hasColumn('transaksi_detail', 'status_urgent')) {
                    $table->dropColumn('status_urgent');
                }
            });
        }

        if (Schema::hasTable('menus')) {
            $ids = DB::table('menus')->whereIn('url_link', ['spk', 'produksi'])->pluck('id_menu');
            DB::table('role_permissions')->whereIn('id_menu', $ids)->delete();
            DB::table('menus')->whereIn('id_menu', $ids)->delete();
        }
    }
};
