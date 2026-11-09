<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seragamkan collation kolom teks ke utf8mb4_unicode_ci (bawaan config/database.php).
 *
 * Tabel yang berasal dari impor data lama memakai utf8mb4_general_ci, sedangkan tabel dari migrasi memakai
 * utf8mb4_unicode_ci. Membandingkan atau menggabungkan dua kolom yang collation-nya berbeda membuat MySQL/MariaDB
 * menolak kueri ("Illegal mix of collations") - dan karena database tes dibangun dari migrasi (seragam), galat ini
 * hanya muncul di database produksi.
 *
 * - Kolom JSON (utf8mb4_bin) tidak disentuh: tabel yang punya kolom bin dilewati.
 * - MariaDB tidak mengizinkan mengubah kolom yang dipakai foreign key, walau FOREIGN_KEY_CHECKS=0. Foreign key
 *   pada kolom teks dilepas dulu, lalu dipasang kembali dengan aturan ON UPDATE/ON DELETE yang sama.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $adaBin = collect(DB::select("SELECT DISTINCT table_name AS t FROM information_schema.columns WHERE table_schema = DATABASE() AND collation_name = 'utf8mb4_bin'"))
            ->pluck('t')->all();
        $tabel = collect(DB::select("SELECT DISTINCT table_name AS t FROM information_schema.columns WHERE table_schema = DATABASE() AND collation_name = 'utf8mb4_general_ci'"))
            ->pluck('t')->reject(fn ($t) => in_array($t, $adaBin, true))->values()->all();

        if (! $tabel) {
            return;
        }

        // Foreign key yang kolomnya teks dan menyentuh tabel yang akan diubah (di sisi mana pun).
        $fk = DB::select("
            SELECT k.constraint_name AS nama, k.table_name AS tabel, k.column_name AS kolom,
                   k.referenced_table_name AS tabel_rujukan, k.referenced_column_name AS kolom_rujukan,
                   r.update_rule AS saat_ubah, r.delete_rule AS saat_hapus
            FROM information_schema.key_column_usage k
            JOIN information_schema.referential_constraints r
              ON r.constraint_schema = k.table_schema AND r.constraint_name = k.constraint_name AND r.table_name = k.table_name
            JOIN information_schema.columns c
              ON c.table_schema = k.table_schema AND c.table_name = k.table_name AND c.column_name = k.column_name
            WHERE k.table_schema = DATABASE() AND k.referenced_table_name IS NOT NULL AND c.collation_name IS NOT NULL
        ");
        $fk = array_values(array_filter($fk, fn ($f) => in_array($f->tabel, $tabel, true) || in_array($f->tabel_rujukan, $tabel, true)));

        foreach ($fk as $f) {
            DB::statement("ALTER TABLE `{$f->tabel}` DROP FOREIGN KEY `{$f->nama}`");
        }

        foreach ($tabel as $t) {
            DB::statement("ALTER TABLE `{$t}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        foreach ($fk as $f) {
            DB::statement("ALTER TABLE `{$f->tabel}` ADD CONSTRAINT `{$f->nama}` FOREIGN KEY (`{$f->kolom}`) REFERENCES `{$f->tabel_rujukan}` (`{$f->kolom_rujukan}`) ON UPDATE {$f->saat_ubah} ON DELETE {$f->saat_hapus}");
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan: collation campuran adalah keadaan yang salah.
    }
};
