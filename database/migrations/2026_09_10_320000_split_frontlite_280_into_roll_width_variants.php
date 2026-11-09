<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * FRONTLITE 280gr (kode FLS280, id_bahan=1) secara fisik datang dalam
 * beberapa lebar roll berbeda dari supplier (3.2m, 2.2m, 2.6m, 1.6m, 1.1m) -
 * bukan cuma satu ukuran seperti yang diasumsikan skema bahan_baku saat ini
 * (satu lebar_roll/panjang_roll per record). Karena lebar roll adalah
 * batasan fisik keras untuk produksi (roll 2.2m tidak bisa dipakai cetak
 * banner 3m), menggabungkan semua lebar ke satu angka stok akan
 * menyembunyikan info paling penting: "apakah lebar yang pas untuk order
 * ini sedang tersedia". Solusinya: tiap lebar jadi bahan_baku terpisah,
 * seperti SKU berbeda - bukan field baru di record yang sama.
 *
 * id_bahan=1 (record asli) TIDAK dihapus/diganti ID-nya - sudah punya
 * riwayat kartu_stok, bom_detail, kedatangan_bahan_detail, dan
 * pembelian_bahan_detail yang harus tetap nyambung. Hanya kode_bahan &
 * nama_bahan-nya diperjelas jadi varian 3.2m (lebar_roll-nya memang
 * sudah 3.2 dari awal). 4 varian lain (2.2/2.6/1.6/1.1m) adalah SKU baru
 * yang belum pernah ada histori transaksinya, jadi aman dibuat sebagai
 * record baru dengan stok 0.
 *
 * panjang_roll & nilai_konversi varian baru SENGAJA dibiarkan default
 * (null / 1.000, "belum dikonfigurasi") karena panjang roll per lebar
 * belum dikonfirmasi - hanya lebar yang disebutkan eksplisit. Estimasi m2
 * di Master Bahan Baku & Kartu Stok otomatis tidak tampil untuk bahan
 * dengan nilai_konversi default, jadi tidak ada angka menyesatkan sampai
 * dimensi asli diisi lewat Kalkulator Dimensi Roll di form edit.
 */
return new class extends Migration
{
    public function up(): void
    {
        $induk = DB::table('bahan_baku')->where('id_bahan', 1)->first();

        if (!$induk || $induk->kode_bahan !== 'FLS280') {
            return; // sudah pernah dijalankan / data sudah berubah - jangan timpa
        }

        DB::table('bahan_baku')->where('id_bahan', 1)->update([
            'kode_bahan' => 'FLS280-320',
            'nama_bahan' => 'FRONTLITE 280gr 3.2m',
        ]);

        $varian = [
            ['kode_bahan' => 'FLS280-220', 'nama_bahan' => 'FRONTLITE 280gr 2.2m', 'lebar_roll' => 2.2],
            ['kode_bahan' => 'FLS280-260', 'nama_bahan' => 'FRONTLITE 280gr 2.6m', 'lebar_roll' => 2.6],
            ['kode_bahan' => 'FLS280-160', 'nama_bahan' => 'FRONTLITE 280gr 1.6m', 'lebar_roll' => 1.6],
            ['kode_bahan' => 'FLS280-110', 'nama_bahan' => 'FRONTLITE 280gr 1.1m', 'lebar_roll' => 1.1],
        ];

        foreach ($varian as $v) {
            $sudahAda = DB::table('bahan_baku')->where('kode_bahan', $v['kode_bahan'])->exists();
            if ($sudahAda) continue;

            DB::table('bahan_baku')->insert([
                'kode_bahan'       => $v['kode_bahan'],
                'nama_bahan'       => $v['nama_bahan'],
                'id_mesin'         => $induk->id_mesin,
                'id_kategori'      => $induk->id_kategori,
                'id_merk'          => $induk->id_merk,
                'id_jenis_bahan'   => $induk->id_jenis_bahan,
                'stok'             => 0,
                'stok_minimal'     => $induk->stok_minimal,
                'id_satuan_stok'   => $induk->id_satuan_stok,
                'id_satuan_hitung' => $induk->id_satuan_hitung,
                'nilai_konversi'   => 1.000,
                'lebar_roll'       => $v['lebar_roll'],
                'panjang_roll'     => null,
                'harga_rata_rata'  => 0,
                'satuan_stok'      => $induk->satuan_stok,
                'status'           => 'aktif',
                'created_at'       => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('bahan_baku')->whereIn('kode_bahan', [
            'FLS280-220', 'FLS280-260', 'FLS280-160', 'FLS280-110',
        ])->delete();

        DB::table('bahan_baku')->where('kode_bahan', 'FLS280-320')->update([
            'kode_bahan' => 'FLS280',
            'nama_bahan' => 'FRONTLITE 280gr',
        ]);
    }
};
