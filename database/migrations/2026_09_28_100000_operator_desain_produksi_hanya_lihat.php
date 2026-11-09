<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Operator Desain: modul produksi (SPK, Produksi, Collecting, QC, Waste, Master Bahan) hanya Lihat di matriks hak akses. */
return new class extends Migration
{
    public function up(): void
    {
        $idRole = DB::table('roles')->where('nama_role', 'Operator Desain')->value('id_role');
        if ($idRole) {
            DB::table('role_permissions')->where('id_role', $idRole)->whereIn('id_menu', [22, 23, 24, 56, 57, 1])
                ->update(['can_add' => 0, 'can_edit' => 0, 'can_delete' => 0]);
        }
    }

    public function down(): void
    {
    }
};
