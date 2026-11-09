<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Persen komisi awal yang wajar (karyawan sudah digaji tetap): Standar 5%, Senior 10%. Operator desain tanpa kelas masuk Standar. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('kelas_desainer')->where('nama_kelas', 'Standar')->where('persen_komisi', 0)->update([
            'persen_komisi' => 5, 'keterangan' => 'Kelas awal operator desain (karyawan gaji tetap)', 'updated_at' => now(),
        ]);
        DB::table('kelas_desainer')->insertOrIgnore([
            'nama_kelas' => 'Senior', 'persen_komisi' => 10, 'keterangan' => 'Desainer berpengalaman', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $idStandar = DB::table('kelas_desainer')->where('nama_kelas', 'Standar')->value('id_kelas');
        $idRoleDesain = DB::table('roles')->where('nama_role', 'Operator Desain')->value('id_role');
        if ($idStandar && $idRoleDesain) {
            DB::table('users')->where('id_role', $idRoleDesain)->whereNull('id_kelas_desainer')->update(['id_kelas_desainer' => $idStandar]);
        }
    }

    public function down(): void
    {
    }
};
