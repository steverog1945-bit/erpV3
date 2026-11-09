<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Integrasi Order Jersey ke akuntansi (sebelumnya order & pembayaran Jersey
// sama sekali tidak menjurnal, jadi pendapatannya tidak masuk laporan
// keuangan). Akun pendapatan Jersey dipisah (4300) supaya terlihat sendiri
// di laporan.
//
// Data Jersey yang sudah ada TIDAK di-backfill ke jurnal (total_terjurnal
// mulai 0): sebelum go-live data uji direset, dan menjurnal ulang histori
// yang tanggalnya bisa jatuh di periode lama justru berisiko.
return new class extends Migration {
    public function up()
    {
        if (Schema::hasTable('bag_akun') && !DB::table('bag_akun')->where('kode_akun', '4300')->exists()) {
            DB::table('bag_akun')->insert([
                'kode_akun' => '4300', 'nama_akun' => 'Pendapatan Order Jersey', 'tipe_akun' => 'Pendapatan',
                'saldo_normal' => 'Kredit', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (!Schema::hasColumn('jersey_orders', 'total_terjurnal')) {
            Schema::table('jersey_orders', function (Blueprint $table) {
                $table->decimal('total_terjurnal', 15, 2)->default(0);
            });
        }

        if (!Schema::hasColumn('jersey_order_payments', 'no_referensi_jurnal')) {
            Schema::table('jersey_order_payments', function (Blueprint $table) {
                $table->string('no_referensi_jurnal', 100)->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('jersey_order_payments', 'no_referensi_jurnal')) {
            Schema::table('jersey_order_payments', fn (Blueprint $t) => $t->dropColumn('no_referensi_jurnal'));
        }
        if (Schema::hasColumn('jersey_orders', 'total_terjurnal')) {
            Schema::table('jersey_orders', fn (Blueprint $t) => $t->dropColumn('total_terjurnal'));
        }
        DB::table('bag_akun')->where('kode_akun', '4300')->delete();
    }
};
