<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * MIGRASI KOREKTIF - memulihkan foreign key yang HILANG dari database
 * produksi (audit Fase 2, schema drift).
 *
 * Latar: setelah pemulihan crash MySQL 23-24 Sep 2026 database diimpor ulang
 * dengan constraint & indeks sekunder dilepas (backups/db_recovery_20260923),
 * dan hanya sebagian FK yang kembali. Migrasi asli mendeklarasikan ~116 FK,
 * database asli hanya punya 35. Akibat nyata: bahan yang sudah punya riwayat
 * stok bisa terhapus (BahanBakuController::destroy mengandalkan FK RESTRICT),
 * dan baris anak Order Jersey tertinggal orphan tiap order/baris diganti.
 *
 * Aturan:
 * (1) IDEMPOTEN - FK yang sudah ada pada kolom itu dilewati, jadi aman di
 *     database baru (FK-nya sudah lengkap) maupun yang rusak.
 * (2) Aturan hapus (CASCADE/RESTRICT/SET NULL) sama persis dengan hasil
 *     migrasi asli pada instalasi baru.
 * (3) Baris orphan pada relasi CASCADE dibuang HANYA setelah disalin ke
 *     storage/app/orphan_fk_*.json - orphan semacam itu memang seharusnya
 *     sudah terhapus bersama induknya. Orphan pada relasi lain TIDAK
 *     disentuh: FK-nya dilewati & dicatat di log agar diputuskan manusia.
 * (4) Kegagalan satu FK (tipe kolom beda, kolom NOT NULL untuk SET NULL,
 *     dst) tidak menggagalkan yang lain.
 */
return new class extends Migration
{
    /** [tabel, kolom, tabel_induk, kolom_induk, aturan_hapus] */
    private const FK = [
        ['bahan_baku', 'id_jenis_bahan', 'jenis_bahan', 'id_jenis_bahan', 'SET NULL'],
        ['bahan_baku', 'id_kategori', 'kategori', 'id_kategori', 'RESTRICT'],
        ['bahan_baku', 'id_merk', 'merk', 'id_merk', 'SET NULL'],
        ['bahan_baku', 'id_mesin', 'mesin', 'id_mesin', 'RESTRICT'],
        ['bahan_baku', 'id_satuan_hitung', 'satuan', 'id_satuan', 'SET NULL'],
        ['bahan_baku', 'id_satuan_stok', 'satuan', 'id_satuan', 'SET NULL'],
        ['bom_detail', 'id_bahan', 'bahan_baku', 'id_bahan', 'RESTRICT'],
        ['bom_detail', 'id_produk', 'produk', 'id_produk', 'CASCADE'],
        ['bom_finishing', 'id_finishing', 'finishing', 'id_finishing', 'RESTRICT'],
        ['bom_finishing', 'id_produk', 'produk', 'id_produk', 'RESTRICT'],
        ['finishing_harga', 'id_finishing', 'finishing', 'id_finishing', 'CASCADE'],
        ['harga_promo', 'id_produk', 'produk', 'id_produk', 'RESTRICT'],
        ['jasa', 'id_satuan', 'satuan', 'id_satuan', 'SET NULL'],
        ['jasa_harga', 'id_jasa', 'jasa', 'id_jasa', 'CASCADE'],
        ['jersey_components', 'jersey_category_id', 'jersey_categories', 'id', 'SET NULL'],
        ['jersey_components', 'jersey_product_type_id', 'jersey_product_types', 'id', 'SET NULL'],
        ['jersey_order_items', 'jersey_collar_type_id', 'jersey_collar_types', 'id', 'SET NULL'],
        ['jersey_order_items', 'jersey_order_line_id', 'jersey_order_lines', 'id', 'CASCADE'],
        ['jersey_order_items', 'jersey_sleeve_type_id', 'jersey_sleeve_types', 'id', 'SET NULL'],
        ['jersey_order_line_materials', 'jersey_order_line_id', 'jersey_order_lines', 'id', 'CASCADE'],
        ['jersey_order_lines', 'jersey_category_id', 'jersey_categories', 'id', 'SET NULL'],
        ['jersey_order_lines', 'jersey_collar_type_id', 'jersey_collar_types', 'id', 'SET NULL'],
        ['jersey_order_lines', 'jersey_material_id', 'jersey_materials', 'id', 'SET NULL'],
        ['jersey_order_lines', 'jersey_order_id', 'jersey_orders', 'id', 'CASCADE'],
        ['jersey_order_lines', 'jersey_price_setting_id', 'jersey_price_settings', 'id', 'SET NULL'],
        ['jersey_order_lines', 'jersey_product_type_id', 'jersey_product_types', 'id', 'SET NULL'],
        ['jersey_order_lines', 'jersey_sleeve_type_id', 'jersey_sleeve_types', 'id', 'SET NULL'],
        ['jersey_order_payments', 'created_by', 'users', 'id_user', 'SET NULL'],
        ['jersey_order_payments', 'jersey_order_id', 'jersey_orders', 'id', 'CASCADE'],
        ['jersey_order_size_quantities', 'jersey_order_line_id', 'jersey_order_lines', 'id', 'CASCADE'],
        ['jersey_order_size_quantities', 'jersey_size_id', 'jersey_sizes', 'id', 'RESTRICT'],
        ['jersey_orders', 'created_by', 'users', 'id_user', 'SET NULL'],
        ['jersey_orders', 'id_konsumen', 'konsumen', 'id_konsumen', 'SET NULL'],
        ['jersey_orders', 'jersey_designer_id', 'jersey_designers', 'id', 'SET NULL'],
        ['jersey_price_settings', 'jersey_category_id', 'jersey_categories', 'id', 'SET NULL'],
        ['jersey_price_settings', 'jersey_material_id', 'jersey_materials', 'id', 'SET NULL'],
        ['jersey_price_settings', 'jersey_product_type_id', 'jersey_product_types', 'id', 'SET NULL'],
        ['jersey_size_collar_usage', 'jersey_collar_type_id', 'jersey_collar_types', 'id', 'CASCADE'],
        ['jersey_size_collar_usage', 'jersey_size_id', 'jersey_sizes', 'id', 'CASCADE'],
        ['jersey_size_component_usage', 'jersey_component_id', 'jersey_components', 'id', 'CASCADE'],
        ['jersey_size_component_usage', 'jersey_size_id', 'jersey_sizes', 'id', 'CASCADE'],
        ['jersey_size_sleeve_usage', 'jersey_size_id', 'jersey_sizes', 'id', 'CASCADE'],
        ['jersey_size_sleeve_usage', 'jersey_sleeve_type_id', 'jersey_sleeve_types', 'id', 'CASCADE'],
        ['kartu_stok', 'id_bahan', 'bahan_baku', 'id_bahan', 'RESTRICT'],
        ['karyawan_mesin', 'id_karyawan', 'karyawan', 'id_karyawan', 'CASCADE'],
        ['karyawan_mesin', 'id_mesin', 'mesin', 'id_mesin', 'CASCADE'],
        ['kas_kecil', 'id_kategori', 'kategori_kas_kecil', 'id_kategori_kas_kecil', 'RESTRICT'],
        ['kas_kecil', 'id_user', 'users', 'id_user', 'SET NULL'],
        ['kas_manual', 'id_kas', 'kas_harian', 'id_kas', 'RESTRICT'],
        ['kas_manual', 'id_user', 'users', 'id_user', 'RESTRICT'],
        ['kedatangan_bahan', 'id_gudang', 'gudang', 'id_gudang', 'RESTRICT'],
        ['kedatangan_bahan', 'id_supplier', 'supplier', 'id_supplier', 'RESTRICT'],
        ['kedatangan_bahan_detail', 'id_bahan', 'bahan_baku', 'id_bahan', 'RESTRICT'],
        ['kedatangan_bahan_detail', 'id_kedatangan', 'kedatangan_bahan', 'id_kedatangan', 'CASCADE'],
        ['konsumen', 'id_golongan', 'golongan_konsumen', 'id_golongan', 'RESTRICT'],
        ['konsumen_deposit', 'id_konsumen', 'konsumen', 'id_konsumen', 'RESTRICT'],
        ['konsumen_deposit', 'id_transaksi', 'transaksi', 'id_transaksi', 'SET NULL'],
        ['konsumen_deposit', 'id_user', 'users', 'id_user', 'SET NULL'],
        ['nota_edit_request', 'id_transaksi', 'transaksi', 'id_transaksi', 'RESTRICT'],
        ['nota_edit_request', 'id_user_approver', 'users', 'id_user', 'RESTRICT'],
        ['nota_edit_request', 'id_user_requester', 'users', 'id_user', 'RESTRICT'],
        ['pembayaran_riwayat', 'id_bank', 'bank_perusahaan', 'id_bank', 'SET NULL'],
        ['pembelian_bahan', 'id_kedatangan', 'kedatangan_bahan', 'id_kedatangan', 'SET NULL'],
        ['pembelian_bahan_detail', 'id_bahan', 'bahan_baku', 'id_bahan', 'RESTRICT'],
        ['pembelian_bahan_detail', 'id_pembelian', 'pembelian_bahan', 'id_pembelian', 'CASCADE'],
        ['role_permissions', 'id_menu', 'menus', 'id_menu', 'RESTRICT'],
        ['role_permissions', 'id_role', 'roles', 'id_role', 'RESTRICT'],
        ['setting_harga', 'id_produk', 'produk', 'id_produk', 'RESTRICT'],
        ['jurnal_umum', 'kode_akun', 'bag_akun', 'kode_akun', 'RESTRICT'],
        ['transaksi', 'id_konsumen', 'konsumen', 'id_konsumen', 'RESTRICT'],
        ['transaksi', 'id_user_pembatal_nota', 'users', 'id_user', 'SET NULL'],
        ['transaksi', 'id_user_collecting', 'users', 'id_user', 'SET NULL'],
        ['transaksi', 'id_user_kasir', 'users', 'id_user', 'RESTRICT'],
        ['transaksi_detail', 'id_bahan', 'bahan_baku', 'id_bahan', 'SET NULL'],
        ['transaksi_detail', 'id_finishing', 'finishing', 'id_finishing', 'SET NULL'],
        ['transaksi_detail', 'id_jasa', 'jasa', 'id_jasa', 'SET NULL'],
        ['transaksi_detail', 'id_produk', 'produk', 'id_produk', 'RESTRICT'],
        ['transaksi_detail', 'id_transaksi', 'transaksi', 'id_transaksi', 'CASCADE'],
        ['users', 'id_karyawan', 'karyawan', 'id_karyawan', 'RESTRICT'],
        ['users', 'id_role', 'roles', 'id_role', 'RESTRICT'],
    ];

    public function up(): void
    {
        $arsip = [];
        $dilewati = [];
        $ditambah = 0;

        foreach (self::FK as [$tabel, $kolom, $induk, $kolomInduk, $aturan]) {
            if (!Schema::hasTable($tabel) || !Schema::hasTable($induk) || !Schema::hasColumn($tabel, $kolom)) {
                continue;
            }
            if ($this->sudahAdaFk($tabel, $kolom)) {
                continue;
            }

            $orphan = DB::table($tabel . ' as a')
                ->leftJoin($induk . ' as b', 'b.' . $kolomInduk, '=', 'a.' . $kolom)
                ->whereNotNull('a.' . $kolom)->whereNull('b.' . $kolomInduk)
                ->select('a.*');

            $jumlahOrphan = (clone $orphan)->count();

            if ($jumlahOrphan > 0) {
                if ($aturan !== 'CASCADE') {
                    $dilewati[] = "{$tabel}.{$kolom}: {$jumlahOrphan} baris orphan (aturan {$aturan}) - FK TIDAK dibuat, perlu keputusan manual";
                    continue;
                }
                $arsip["{$tabel}.{$kolom}"] = $orphan->get()->map(fn ($r) => (array) $r)->all();
                DB::table($tabel)->whereNotNull($kolom)
                    ->whereNotIn($kolom, DB::table($induk)->select($kolomInduk))
                    ->delete();
            }

            $nama = substr($tabel . '_' . $kolom . '_foreign', 0, 64);
            try {
                DB::statement("ALTER TABLE `{$tabel}` ADD CONSTRAINT `{$nama}` FOREIGN KEY (`{$kolom}`) REFERENCES `{$induk}` (`{$kolomInduk}`) ON DELETE {$aturan}");
                $ditambah++;
            } catch (\Throwable $e) {
                $dilewati[] = "{$tabel}.{$kolom}: " . substr($e->getMessage(), 0, 160);
            }
        }

        if ($arsip !== []) {
            $file = storage_path('app/orphan_fk_' . date('Ymd_His') . '.json');
            @file_put_contents($file, json_encode($arsip, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            Log::warning('restore_missing_foreign_keys: baris orphan diarsipkan sebelum dibuang', ['file' => $file, 'ringkasan' => array_map('count', $arsip)]);
        }
        Log::info("restore_missing_foreign_keys: {$ditambah} FK ditambahkan");
        foreach ($dilewati as $d) {
            Log::warning('restore_missing_foreign_keys dilewati: ' . $d);
        }
    }

    /**
     * Sengaja tidak melepas apa pun: di database yang FK-nya berasal dari
     * migrasi asli, melepas FK bernama sama akan MERUSAK desain aslinya, dan
     * baris orphan yang sudah dibuang hanya bisa dipulihkan dari file arsip
     * JSON / backup. Rollback = restore backup.
     */
    public function down(): void
    {
    }

    private function sudahAdaFk(string $tabel, string $kolom): bool
    {
        foreach (Schema::getForeignKeys($tabel) as $fk) {
            if (in_array($kolom, $fk['columns'], true)) {
                return true;
            }
        }

        return false;
    }
};
