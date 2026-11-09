<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jasa Jahit Borongan: penjahit dan tarif jahit jersey.
 *  - penjahit: siapa yang mengerjakan (dan kepada siapa uangnya dibayar).
 *  - jasa_jahit: tarif bisa ditandai khusus jersey, opsional terikat tipe lengan dan/atau tipe kerah
 *    (mis. "Jersey lengan pendek" 5.000, "Jersey lengan panjang" 7.000, "Jersey kerah wangki" 8.000).
 *  - jasa_jahit_catat: tercatat untuk penjahit siapa, dan bisa menunjuk order jersey.
 *  - Jenis jahit yang sudah punya catatan tidak lagi ikut terhapus beserta catatannya (RESTRICT).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('penjahit')) {
            Schema::create('penjahit', function (Blueprint $t) {
                $t->id('id_penjahit');
                $t->string('nama', 100);
                $t->string('telepon', 30)->nullable();
                $t->text('keterangan')->nullable();
                $t->boolean('aktif')->default(true);
                $t->timestamps();
            });
        }

        Schema::table('jasa_jahit', function (Blueprint $t) {
            if (!Schema::hasColumn('jasa_jahit', 'untuk_jersey')) {
                $t->boolean('untuk_jersey')->default(false)->after('id_finishing');
                $t->unsignedBigInteger('jersey_sleeve_type_id')->nullable()->after('untuk_jersey');
                $t->unsignedBigInteger('jersey_collar_type_id')->nullable()->after('jersey_sleeve_type_id');
                $t->foreign('jersey_sleeve_type_id')->references('id')->on('jersey_sleeve_types')->nullOnDelete();
                $t->foreign('jersey_collar_type_id')->references('id')->on('jersey_collar_types')->nullOnDelete();
            }
        });

        Schema::table('jasa_jahit_catat', function (Blueprint $t) {
            if (!Schema::hasColumn('jasa_jahit_catat', 'id_penjahit')) {
                $t->unsignedBigInteger('id_penjahit')->nullable()->after('id_jasa_jahit');
                $t->unsignedBigInteger('id_jersey_order')->nullable()->after('id_transaksi_detail');
                $t->foreign('id_penjahit')->references('id_penjahit')->on('penjahit')->restrictOnDelete();
                $t->foreign('id_jersey_order')->references('id')->on('jersey_orders')->nullOnDelete();
                $t->index(['id_penjahit', 'tgl_catat'], 'jasa_jahit_catat_penjahit_tgl_index');
            }
        });

        // Riwayat pengeluaran tidak boleh hilang ikut terhapus bersama jenis jahitnya.
        if (DB::getDriverName() === 'mysql') {
            $aturan = DB::selectOne("select delete_rule r from information_schema.referential_constraints where constraint_schema = database() and constraint_name = 'jasa_jahit_catat_id_jasa_jahit_foreign'");
            if ($aturan && $aturan->r === 'CASCADE') {
                Schema::table('jasa_jahit_catat', function (Blueprint $t) {
                    $t->dropForeign('jasa_jahit_catat_id_jasa_jahit_foreign');
                    $t->foreign('id_jasa_jahit')->references('id_jasa_jahit')->on('jasa_jahit')->restrictOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('jasa_jahit_catat', function (Blueprint $t) {
            if (Schema::hasColumn('jasa_jahit_catat', 'id_penjahit')) {
                $t->dropForeign(['id_penjahit']);
                $t->dropForeign(['id_jersey_order']);
                $t->dropIndex('jasa_jahit_catat_penjahit_tgl_index');
                $t->dropColumn(['id_penjahit', 'id_jersey_order']);
            }
        });
        Schema::table('jasa_jahit', function (Blueprint $t) {
            if (Schema::hasColumn('jasa_jahit', 'untuk_jersey')) {
                $t->dropForeign(['jersey_sleeve_type_id']);
                $t->dropForeign(['jersey_collar_type_id']);
                $t->dropColumn(['untuk_jersey', 'jersey_sleeve_type_id', 'jersey_collar_type_id']);
            }
        });
        Schema::dropIfExists('penjahit');
    }
};
