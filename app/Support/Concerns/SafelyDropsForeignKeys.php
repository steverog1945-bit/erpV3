<?php

namespace App\Support\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dipakai migration untuk drop foreign key constraint TANPA meledak kalau
 * constraint itu ternyata tidak pernah berhasil dibuat sejak awal (audit
 * Phase 1 menemukan NOL foreign key di seluruh database production - FK
 * yang seharusnya dibuat migration lebih awal gagal senyap karena masalah
 * tipe kolom/tabel yang belum ada, tapi migration BERIKUTNYA yang
 * seharusnya "mengganti" FK itu tetap blind men-drop nama constraint yang
 * sama seolah pasti ada). Tanpa trait ini, migrate:fresh dari kosong
 * (instalasi baru/testing/CI) gagal keras di titik ini walau database
 * production yang sudah berjalan sekarang baik-baik saja (constraint-nya
 * memang sudah tidak ada di sana juga, drop jadi no-op yang aman).
 */
trait SafelyDropsForeignKeys
{
    protected function dropForeignIfExists(string $table, string $constraint): void
    {
        $ada = (int) DB::selectOne(
            "SELECT COUNT(*) as c FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $constraint]
        )->c;

        if ($ada > 0) {
            Schema::table($table, function ($t) use ($constraint) {
                $t->dropForeign($constraint);
            });
        }
    }
}
