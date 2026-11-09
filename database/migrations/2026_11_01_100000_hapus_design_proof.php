<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Fitur Design Proof dihapus atas keputusan pemilik: tidak pernah punya jalur masuk dari menu dan tidak ada datanya.
// Tabel, menu, hak akses, dan pengajuan "buka_revisi_desain" ikut dibersihkan. Penghapusan ditolak bila tabel ternyata
// berisi, supaya proof yang sempat terunggah tidak hilang diam-diam.
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('design_proofs')) {
            if (DB::table('design_proofs')->exists()) {
                throw new RuntimeException('Tabel design_proofs berisi data - cadangkan dulu sebelum fitur Design Proof dihapus.');
            }
            Schema::drop('design_proofs');
        }

        $idMenu = DB::table('menus')->where('url_link', 'design-proof')->pluck('id_menu');
        if ($idMenu->isNotEmpty()) {
            DB::table('role_permissions')->whereIn('id_menu', $idMenu)->delete();
            DB::table('menus')->whereIn('id_menu', $idMenu)->delete();
        }

        if (Schema::hasTable('approval_requests')) {
            DB::table('approval_requests')->where('tipe_aksi', 'buka_revisi_desain')->where('status', 'Menunggu')->update(['status' => 'Ditolak']);
        }
    }

    public function down(): void
    {
        // Fitur tidak dikembalikan.
    }
};
