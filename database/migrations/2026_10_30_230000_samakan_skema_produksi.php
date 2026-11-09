<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Samakan kolom database produksi (hasil impor data lama) dengan definisi migrasi, supaya tes - yang dibangun dari
 * migrasi - menguji aturan yang sama dengan produksi. Ditemukan dengan membandingkan information_schema kedua
 * database. Yang disamakan hanya perbedaan yang bisa membuat aksi gagal di produksi tetapi lulus di tes:
 *   - golongan_konsumen.keterangan NOT NULL di produksi: tambah golongan tanpa keterangan galat.
 *   - kolom enum di produksi (status kedatangan, status detail, status pembayaran faktur, tipe kartu stok):
 *     nilai di luar daftar ditolak hanya di produksi.
 *   - kolom lebih pendek/kurang presisi di produksi (referensi kartu stok, keterangan kedatangan, nama gudang,
 *     nilai konversi bahan).
 *   - kedatangan_bahan.id_gudang NOT NULL di produksi (kedatangan dari PO tanpa gudang galat).
 *   - unique no_surat_jalan hanya ada di tes: ditambahkan di produksi bila datanya tidak ganda.
 * Setiap kolom diperiksa lebih dulu; bila sudah sama (mis. di database tes) tidak diubah.
 */
return new class extends Migration
{
    private const TARGET = [
        // [tabel, kolom, definisi tujuan, tipe tujuan (information_schema.column_type), nullable tujuan]
        ['golongan_konsumen', 'keterangan', 'VARCHAR(255) NULL', 'varchar(255)', 'YES'],
        ['gudang', 'nama_gudang', 'VARCHAR(100) NOT NULL', 'varchar(100)', 'NO'],
        ['kartu_stok', 'referensi', 'VARCHAR(255) NULL', 'varchar(255)', 'YES'],
        ['kartu_stok', 'tipe', 'VARCHAR(10) NOT NULL', 'varchar(10)', 'NO'],
        ['kedatangan_bahan', 'status', "VARCHAR(20) NOT NULL DEFAULT 'Draft'", 'varchar(20)', 'NO'],
        ['kedatangan_bahan', 'id_gudang', 'BIGINT UNSIGNED NULL', 'bigint(20) unsigned', 'YES'],
        ['kedatangan_bahan', 'keterangan', 'TEXT NULL', 'text', 'YES'],
        ['kedatangan_bahan_detail', 'status', 'VARCHAR(20) NULL', 'varchar(20)', 'YES'],
        ['pembelian_bahan', 'status_pembayaran', "VARCHAR(20) NULL DEFAULT 'Hutang'", 'varchar(20)', 'YES'],
        ['bahan_baku', 'nilai_konversi', 'DECIMAL(15,4) NOT NULL DEFAULT 1.0000', 'decimal(15,4)', 'NO'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::TARGET as [$tabel, $kolom, $definisi, $tipe, $nullable]) {
            $kini = DB::selectOne('SELECT column_type AS t, is_nullable AS n FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?', [$tabel, $kolom]);
            if (! $kini || (strtolower($kini->t) === $tipe && $kini->n === $nullable)) {
                continue;
            }
            if ($kolom === 'status' && $tabel === 'kedatangan_bahan') {
                DB::table($tabel)->whereNull($kolom)->update([$kolom => 'Draft']);
            }
            // Kolom yang dipakai foreign key harus dilepas dulu (MariaDB menolak mengubahnya), lalu dipasang lagi.
            $fk = DB::select('SELECT k.constraint_name AS nama, k.referenced_table_name AS rt, k.referenced_column_name AS rc, r.update_rule AS u, r.delete_rule AS d
                FROM information_schema.key_column_usage k JOIN information_schema.referential_constraints r
                  ON r.constraint_schema = k.table_schema AND r.constraint_name = k.constraint_name AND r.table_name = k.table_name
                WHERE k.table_schema = DATABASE() AND k.table_name = ? AND k.column_name = ? AND k.referenced_table_name IS NOT NULL', [$tabel, $kolom]);
            foreach ($fk as $f) {
                DB::statement("ALTER TABLE `{$tabel}` DROP FOREIGN KEY `{$f->nama}`");
            }
            DB::statement("ALTER TABLE `{$tabel}` MODIFY `{$kolom}` {$definisi}");
            foreach ($fk as $f) {
                DB::statement("ALTER TABLE `{$tabel}` ADD CONSTRAINT `{$f->nama}` FOREIGN KEY (`{$kolom}`) REFERENCES `{$f->rt}` (`{$f->rc}`) ON UPDATE {$f->u} ON DELETE {$f->d}");
            }
        }

        $adaUnik = DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'kedatangan_bahan' AND column_name = 'no_surat_jalan' AND non_unique = 0");
        $ganda = DB::table('kedatangan_bahan')->select('no_surat_jalan')->groupBy('no_surat_jalan')->havingRaw('COUNT(*) > 1')->exists();
        if ((int) $adaUnik->n === 0 && ! $ganda) {
            DB::statement('ALTER TABLE kedatangan_bahan ADD UNIQUE kedatangan_bahan_no_surat_jalan_unique (no_surat_jalan)');
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan: perbedaan skema adalah keadaan yang salah.
    }
};
