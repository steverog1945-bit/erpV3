<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// LEVEL 2 (bagian 13-14 dokumen audit): Quotation / Penawaran. Nota
// (transaksi) tetap menjadi Sales Order aplikasi ini; quotation berujung ke
// nota lewat tombol "Buat Order" (id_transaksi terisi saat order tersimpan).
return new class extends Migration {
    public function up()
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 50)->unique();
            $table->date('tanggal');
            $table->date('berlaku_sampai');
            $table->unsignedBigInteger('id_konsumen')->nullable();
            $table->string('nama_pelanggan', 150);
            $table->string('telepon', 30)->nullable();
            $table->unsignedBigInteger('id_golongan')->nullable();
            $table->enum('status', ['Draft', 'Terkirim', 'Disetujui', 'Ditolak', 'Menjadi Order'])->default('Draft');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('diskon', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('id_transaksi')->nullable();
            $table->unsignedBigInteger('id_user_pembuat');
            $table->timestamps();

            $table->foreign('id_konsumen')->references('id_konsumen')->on('konsumen')->nullOnDelete();
            $table->foreign('id_golongan')->references('id_golongan')->on('golongan_konsumen')->nullOnDelete();
            $table->foreign('id_transaksi')->references('id_transaksi')->on('transaksi')->nullOnDelete();
            $table->foreign('id_user_pembuat')->references('id_user')->on('users')->cascadeOnDelete();
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_quotation')->constrained('quotations')->cascadeOnDelete();
            $table->enum('tipe', ['Produk', 'Jasa']);
            $table->unsignedBigInteger('id_produk')->nullable();
            $table->unsignedBigInteger('id_jasa')->nullable();
            $table->unsignedBigInteger('id_finishing')->nullable();
            $table->string('nama_item', 150);
            $table->decimal('p_ukuran', 10, 2)->default(0);
            $table->decimal('l_ukuran', 10, 2)->default(0);
            $table->decimal('qty', 15, 2);
            $table->decimal('qty_hitung', 15, 4);
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('biaya_finishing', 15, 2)->default(0);
            $table->decimal('biaya_desain', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->string('catatan', 255)->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('menus') && !DB::table('menus')->where('url_link', 'quotation')->exists()) {
            $newId = (int) DB::table('menus')->max('id_menu') + 1;
            DB::table('menus')->insert(['id_menu' => $newId, 'nama_menu' => 'Quotation', 'url_link' => 'quotation', 'parent_id' => 0]);

            // Akses mengikuti Input Order (id_menu 3 = Kasir, 41 = input order saja).
            $roles = Schema::hasTable('role_permissions')
                ? DB::table('role_permissions')->whereIn('id_menu', [3, 41])->pluck('id_role')->unique()
                : collect();

            foreach ($roles as $idRole) {
                DB::table('role_permissions')->insert(['id_role' => $idRole, 'id_menu' => $newId]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');

        $id = DB::table('menus')->where('url_link', 'quotation')->value('id_menu');
        if ($id) {
            DB::table('role_permissions')->where('id_menu', $id)->delete();
            DB::table('menus')->where('id_menu', $id)->delete();
        }
    }
};
