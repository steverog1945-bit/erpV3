<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya desain per operator desain + bagi hasil (komisi) menurut KELAS desainer.
 * - kelas_desainer: tingkat/kelas dengan persen komisi (diatur Administrator).
 * - users.id_kelas_desainer: kelas tiap operator desain.
 * - transaksi_detail: desainer yang menerapkan biaya desain + snapshot persen & komisi saat nota dibuat
 *   (perubahan kelas/persen di kemudian hari tidak mengubah komisi nota lama).
 * - Komisi dibayarkan berkala dari Kas Kecil (kategori "Komisi Desainer", akun Beban Komisi Desain 5210).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas_desainer', function (Blueprint $t) {
            $t->id('id_kelas');
            $t->string('nama_kelas', 60)->unique();
            $t->decimal('persen_komisi', 5, 2)->default(0);
            $t->string('keterangan', 200)->nullable();
            $t->timestamps();
        });
        DB::table('kelas_desainer')->insert(['nama_kelas' => 'Standar', 'persen_komisi' => 0, 'keterangan' => 'Kelas awal - atur persen komisinya di Bagi Hasil Desainer', 'created_at' => now(), 'updated_at' => now()]);

        Schema::table('users', function (Blueprint $t) {
            $t->unsignedBigInteger('id_kelas_desainer')->nullable();
        });

        Schema::table('transaksi_detail', function (Blueprint $t) {
            $t->unsignedBigInteger('id_desainer')->nullable()->after('biaya_desain');
            $t->decimal('komisi_desain_persen', 5, 2)->default(0)->after('id_desainer');
            $t->decimal('komisi_desain', 12, 2)->default(0)->after('komisi_desain_persen');
            $t->string('komisi_status', 10)->nullable()->after('komisi_desain');
            $t->timestamp('komisi_dibayar_at')->nullable()->after('komisi_status');
            $t->unsignedBigInteger('id_kas_kecil_komisi')->nullable()->after('komisi_dibayar_at');
            $t->index('id_desainer');
        });

        DB::table('bag_akun')->insertOrIgnore([
            'kode_akun' => '5210', 'nama_akun' => 'Beban Komisi Desain', 'tipe_akun' => 'Beban', 'saldo_normal' => 'Debet', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('kategori_kas_kecil')->insertOrIgnore([
            'nama_kategori' => 'Komisi Desainer', 'jenis' => 'Keluar', 'kode_akun' => '5210', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Menu hak akses: 65 = Laporan Biaya Desain (Operator Desain hanya melihat miliknya), 66 = Bagi Hasil Desainer.
        DB::table('menus')->insertOrIgnore([
            ['id_menu' => 65, 'nama_menu' => 'Laporan Biaya Desain', 'url_link' => 'laporan/biaya-desain', 'parent_id' => 0],
            ['id_menu' => 66, 'nama_menu' => 'Bagi Hasil Desainer', 'url_link' => 'pengaturan-desainer', 'parent_id' => 0],
        ]);
        foreach ([65 => 47, 66 => 37] as $baru => $induk) {
            foreach (DB::table('role_permissions')->where('id_menu', $induk)->get() as $rp) {
                DB::table('role_permissions')->updateOrInsert(
                    ['id_role' => $rp->id_role, 'id_menu' => $baru],
                    ['can_view' => $rp->can_view, 'can_add' => $rp->can_add, 'can_edit' => $rp->can_edit, 'can_delete' => $rp->can_delete]
                );
            }
        }
        $idDesain = DB::table('roles')->where('nama_role', 'Operator Desain')->value('id_role');
        if ($idDesain) {
            DB::table('role_permissions')->updateOrInsert(['id_role' => $idDesain, 'id_menu' => 65], ['can_view' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0]);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->whereIn('id_menu', [65, 66])->delete();
        DB::table('menus')->whereIn('id_menu', [65, 66])->delete();
        DB::table('kategori_kas_kecil')->where('nama_kategori', 'Komisi Desainer')->delete();
        DB::table('bag_akun')->where('kode_akun', '5210')->delete();
        Schema::table('transaksi_detail', function (Blueprint $t) {
            $t->dropIndex(['id_desainer']);
            $t->dropColumn(['id_desainer', 'komisi_desain_persen', 'komisi_desain', 'komisi_status', 'komisi_dibayar_at', 'id_kas_kecil_komisi']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('id_kelas_desainer'));
        Schema::dropIfExists('kelas_desainer');
    }
};
