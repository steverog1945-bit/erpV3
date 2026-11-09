<?php

namespace App\Support;

use App\Models\User;

/**
 * Hak "boleh melihat & mengubah harga/nilai uang" (kolom roles.boleh_lihat_harga,
 * diatur di Pengaturan Hak Akses). Administrator SELALU boleh. Role tanpa hak ini
 * (Operator Desain, Operator Produksi) hanya menginput nota dan berproduksi:
 * halaman berharga ditutup (lihat menu_access.khusus_harga) dan kolom/angka uang
 * disembunyikan lewat @bolehHarga di view.
 */
class AksesHarga
{
    public static function bolehLihat(?User $user = null): bool
    {
        $user ??= auth()->user();
        if (!$user) {
            return false;
        }

        $role = $user->role;
        if (!$role) {
            return false;
        }

        return $role->nama_role === 'Administrator' || (bool) $role->boleh_lihat_harga;
    }

    /**
     * Hak MENGINPUT nilai Biaya Desain di Input Order (kolom
     * roles.boleh_input_biaya_desain, diatur di Pengaturan Hak Akses).
     *
     * Bedanya dengan bolehLihat() di atas, dan kenapa harus dipisah:
     *   - bolehLihat() membuka SEGALA angka harga: harga produk, biaya cetak,
     *     biaya finishing, kolom nilai di detail nota, sampai halaman khusus
     *     harga (Finishing, Quotation, timeline nilai pelanggan). Itu izin besar.
     *   - method ini hanya mengizinkan mengisi SATU field: nilai Biaya Desain
     *     pada satu baris Input Order. Role yang punya hak ini tetap tidak bisa
     *     melihat halaman harga maupun harga produk/cetak/finishing.
     *
     * Kenapa perlu ada: Operator Desain pekerjaannya justru desain, tapi role itu
     * sengaja dibuat tidak boleh melihat harga. Dulu tombol "+ Biaya Desain" ikut
     * di-gate oleh @bolehHarga, jadi ia tidak pernah bisa mengisi biaya yang
     * ia kerjakan. Menyalakan boleh_lihat_harga untuknya demi satu field itu akan
     * memberi izin jauh melebihi kebutuhan.
     *
     * Default kolomnya 0 (gagal-terutup): role yang tidak dicentang di Pengaturan
     * Hak Akses TIDAK boleh menginput Biaya Desain, dan server memaksa nilainya 0
     * (lihat PenjualanController::store). Administrator tetap selalu boleh, sama
     * seperti di bolehLihat().
     *
     * Ini TIDAK mengubah perilaku bolehLihat(): harga produk, biaya cetak, dan
     * biaya finishing tetap dikunci server untuk role yang tidak punya
     * boleh_lihat_harga, apa pun isi kolom baru ini.
     *
     * PENTING - kenapa boleh_lihat_harga ikut_OR di sini:
     * kolom baru mulai default 0. Kalau hanya kolom itu yang dicek, maka
     * Kasir, Gudang, dan Owner - yang TIDAK AKAN pernah dicentang karena
     * biaya desain memang buat kasir - ikut kehilangan field Biaya Desain
     * yang sebelumnya bisa mereka isi (dulu gate-nya cuma @bolehHarga).
     * Itu regresi. Jadi syaratnya: sudah boleh melihat harga ATAU punya izin
     * baru. Operator Desain tetap hanya bisa lewat kolom baru, karena
     * boleh_lihat_harga-nya memang 0.
     */
    public static function bolehInputBiayaDesain(?User $user = null): bool
    {
        $user ??= auth()->user();
        if (!$user) {
            return false;
        }

        $role = $user->role;
        if (!$role) {
            return false;
        }

        return $role->nama_role === 'Administrator'
            || (bool) $role->boleh_lihat_harga
            || (bool) $role->boleh_input_biaya_desain;
    }

    /** Apakah path (mis. "finishing/3", "konsumen/5/timeline") termasuk halaman khusus harga. */
    public static function pathKhususHarga(string $path): bool
    {
        foreach (config('menu_access.khusus_harga', []) as $pola) {
            if ($path === $pola || str_starts_with($path, $pola . '/') || fnmatch($pola, $path)) {
                return true;
            }
        }

        return false;
    }
}
