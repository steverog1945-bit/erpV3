<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * "Kas Tidak Terduga" - pencatatan mutasi kas MANUAL yang tidak terikat nota
 * (mis. pengeluaran operasional dadakan, kas masuk di luar penjualan) -
 * sebelumnya kolom kas_harian.total_tunai_keluar SELALU 0 karena memang
 * belum ada tempat mencatat pengeluaran sama sekali (lihat komentar lama
 * "Belum ada fitur Kas Keluar" di KasirController). Mengikuti referensi
 * "Alur Kas Kasir": Waktu, Keterangan, Status (Debet/Kas Masuk atau
 * Kredit/Kas Keluar), Nominal.
 *
 * Dua akun akuntansi baru ditambahkan (Pendapatan Lain-lain & Biaya
 * Lain-lain) supaya mutasi ini tetap tercatat di jurnal_umum seperti
 * semua pergerakan kas lain di app ini - namanya sama persis dengan yang
 * sudah ada di referensi "Account Kas" milik pemilik aplikasi sendiri
 * (kode 12 & 13), bukan dikarang baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kas_manual', function (Blueprint $table) {
            $table->id('id_kas_manual');
            $table->unsignedBigInteger('id_kas');
            $table->dateTime('waktu');
            $table->text('keterangan')->nullable();
            $table->enum('status', ['Masuk', 'Keluar']);
            $table->decimal('nominal', 15, 2);
            $table->unsignedBigInteger('id_user');
            $table->timestamps();

            $table->foreign('id_kas')->references('id_kas')->on('kas_harian');
            $table->foreign('id_user')->references('id_user')->on('users');
        });

        $existing = DB::table('bag_akun')->max('kode_akun');
        if (!DB::table('bag_akun')->where('kode_akun', '4200')->exists()) {
            DB::table('bag_akun')->insert([
                'kode_akun' => '4200',
                'nama_akun' => 'Pendapatan Lain-lain',
                'tipe_akun' => 'Pendapatan',
                'saldo_normal' => 'Kredit',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        if (!DB::table('bag_akun')->where('kode_akun', '5100')->exists()) {
            DB::table('bag_akun')->insert([
                'kode_akun' => '5100',
                'nama_akun' => 'Biaya Lain-lain',
                'tipe_akun' => 'Beban',
                'saldo_normal' => 'Debet',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kas_manual');
        DB::table('bag_akun')->whereIn('kode_akun', ['4200', '5100'])->delete();
    }
};
