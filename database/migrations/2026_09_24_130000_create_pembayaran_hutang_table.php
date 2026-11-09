<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Payment Management sisi hutang supplier (roadmap LEVEL 1, Accounts
// Payable): riwayat pembayaran per faktur pembelian, mendukung cicilan.
//
// bagian_faktur = true: pembayaran yang sudah ikut terjurnal di referensi
// faktur itu sendiri (pembelian Cash, dan pelunasan lama sebelum tabel ini
// ada) - tidak punya referensi jurnal sendiri sehingga tidak bisa di-void
// terpisah; ikut terbalik saat faktur dibatalkan.
return new class extends Migration {
    public function up()
    {
        Schema::create('pembayaran_hutang', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_pembelian');
            $table->date('tanggal');
            $table->decimal('nominal', 15, 2);
            $table->enum('metode', ['Tunai', 'Transfer'])->default('Tunai');
            $table->string('keterangan', 255)->nullable();
            $table->string('no_referensi_jurnal', 100)->nullable();
            $table->boolean('bagian_faktur')->default(false);
            $table->unsignedBigInteger('id_user')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('void_alasan', 255)->nullable();
            $table->timestamps();

            $table->foreign('id_pembelian')->references('id_pembelian')->on('pembelian_bahan')->cascadeOnDelete();
            $table->index(['id_pembelian', 'voided_at']);
        });

        // Backfill: faktur Lunas yang sudah ada dianggap dibayar penuh
        // (jurnalnya sudah ada di referensi faktur) - tanpa ini sisa hutang
        // faktur lama akan terhitung penuh lagi.
        if (Schema::hasTable('pembelian_bahan')) {
            $lunas = DB::table('pembelian_bahan')->where('status_pembayaran', 'Lunas')->get();

            foreach ($lunas as $p) {
                DB::table('pembayaran_hutang')->insert([
                    'id_pembelian' => $p->id_pembelian,
                    'tanggal' => $p->tgl_pelunasan ? substr((string) $p->tgl_pelunasan, 0, 10) : $p->tgl_transaksi,
                    'nominal' => $p->grand_total,
                    'metode' => 'Tunai',
                    'keterangan' => 'Data awal (sebelum riwayat pembayaran hutang)',
                    'bagian_faktur' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('pembayaran_hutang');
    }
};
