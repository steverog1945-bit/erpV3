<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Chart of Accounts minimal untuk double-entry ledger di jurnal_umum
 * (tabel jurnal_umum sudah ada sejak lama dengan skema debet/kredit yang
 * benar, tapi 0 baris & 0 kode yang pernah menulis ke sana - lihat audit
 * session ini). Daftar akun dibatasi hanya yang punya alur transaksi nyata
 * dan bisa diposting otomatis dari kode yang ada (kas, bank, piutang,
 * persediaan, hutang, modal, pendapatan, HPP) - bukan Chart of Accounts
 * lengkap ala software akuntansi umum, supaya tidak ada akun yang dibuat
 * tapi tidak pernah dipakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bag_akun', function (Blueprint $table) {
            $table->id('id_akun');
            $table->string('kode_akun', 20)->unique();
            $table->string('nama_akun', 100);
            $table->enum('tipe_akun', ['Aset', 'Kewajiban', 'Modal', 'Pendapatan', 'Beban']);
            $table->enum('saldo_normal', ['Debet', 'Kredit']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('bag_akun')->insert([
            ['kode_akun' => '1000', 'nama_akun' => 'Kas',                     'tipe_akun' => 'Aset',       'saldo_normal' => 'Debet',  'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['kode_akun' => '1010', 'nama_akun' => 'Bank',                    'tipe_akun' => 'Aset',       'saldo_normal' => 'Debet',  'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['kode_akun' => '1100', 'nama_akun' => 'Piutang Usaha',           'tipe_akun' => 'Aset',       'saldo_normal' => 'Debet',  'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['kode_akun' => '1200', 'nama_akun' => 'Persediaan Bahan Baku',   'tipe_akun' => 'Aset',       'saldo_normal' => 'Debet',  'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['kode_akun' => '2000', 'nama_akun' => 'Hutang Usaha',            'tipe_akun' => 'Kewajiban',  'saldo_normal' => 'Kredit', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['kode_akun' => '3000', 'nama_akun' => 'Modal',                   'tipe_akun' => 'Modal',      'saldo_normal' => 'Kredit', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['kode_akun' => '4000', 'nama_akun' => 'Pendapatan Penjualan',    'tipe_akun' => 'Pendapatan', 'saldo_normal' => 'Kredit', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['kode_akun' => '5000', 'nama_akun' => 'Harga Pokok Penjualan',   'tipe_akun' => 'Beban',      'saldo_normal' => 'Debet',  'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bag_akun');
    }
};
