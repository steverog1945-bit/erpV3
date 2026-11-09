<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hak akses per modul dengan 4 aksi (lihat / tambah / kelola-ubah / hapus), per role dan (opsional) per user,
 * plus kolom Menu Editor (aktif, nama kustom, urutan, jenis) dan pengaturan jam kerja.
 * Baris role_permissions yang sudah ada otomatis bernilai semua-aksi (perilaku lama tidak berubah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_permissions', function (Blueprint $t) {
            $t->boolean('can_view')->default(true);
            $t->boolean('can_add')->default(true);
            $t->boolean('can_edit')->default(true);
            $t->boolean('can_delete')->default(true);
        });

        Schema::create('user_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('id_user');
            $t->unsignedBigInteger('id_menu');
            $t->boolean('can_view')->default(false);
            $t->boolean('can_add')->default(false);
            $t->boolean('can_edit')->default(false);
            $t->boolean('can_delete')->default(false);
            $t->timestamps();
            $t->primary(['id_user', 'id_menu']);
        });

        Schema::table('menus', function (Blueprint $t) {
            $t->boolean('aktif')->default(true);
            $t->string('label_kustom', 100)->nullable();
            $t->integer('urutan')->default(0);
            $t->string('jenis', 10)->default('standar'); // standar | kustom
            $t->string('ikon', 50)->nullable();
        });

        Schema::create('jam_kerja', function (Blueprint $t) {
            $t->unsignedTinyInteger('hari')->primary(); // 1 = Senin ... 7 = Minggu
            $t->boolean('buka')->default(true);
            $t->time('jam_mulai')->default('08:00:00');
            $t->time('jam_selesai')->default('17:00:00');
        });

        $baris = [];
        for ($h = 1; $h <= 7; $h++) {
            $baris[] = ['hari' => $h, 'buka' => $h !== 7, 'jam_mulai' => '08:00:00', 'jam_selesai' => '17:00:00'];
        }
        \Illuminate\Support\Facades\DB::table('jam_kerja')->insert($baris);

        // Ikuti aturan lama "kas libur hari Minggu" supaya perilaku tidak berubah saat migrasi.
        $lama = \Illuminate\Support\Facades\DB::table('app_settings')->first();
        if ($lama && property_exists($lama, 'libur_kas_minggu')) {
            \Illuminate\Support\Facades\DB::table('jam_kerja')->where('hari', 7)->update(['buka' => !$lama->libur_kas_minggu]);
        }

        Schema::table('app_settings', function (Blueprint $t) {
            $t->boolean('batasi_jam_kerja')->default(false); // true = transaksi baru di luar jam kerja ditolak
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', fn (Blueprint $t) => $t->dropColumn('batasi_jam_kerja'));
        Schema::dropIfExists('jam_kerja');
        Schema::table('menus', fn (Blueprint $t) => $t->dropColumn(['aktif', 'label_kustom', 'urutan', 'jenis', 'ikon']));
        Schema::dropIfExists('user_permissions');
        Schema::table('role_permissions', fn (Blueprint $t) => $t->dropColumn(['can_view', 'can_add', 'can_edit', 'can_delete']));
    }
};
