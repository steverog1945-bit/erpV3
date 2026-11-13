<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Syarat nota boleh diedit lewat alur EDIT SELISIH (bukan batal + buat
 * ulang).
 *
 * Edit selisih hanya mengubah item yang memang diubah: item yang tidak
 * berubah tidak menyentuh stok sama sekali, item yang berubah
 * mengembalikan pemakaian bahan lamanya lalu memotong yang baru, item
 * yang dihapus mengembalikan stoknya, item baru memotong stok. Nota tetap
 * Aktif dan pembayaran tidak diubah - jadi ini koreksi ringan, bukan
 * pembatalan.
 *
 * Karena itu syaratnya jauh lebih ringan daripada alur lama (batal +
 * buat ulang): tidak ada pembatalan nota, tidak ada uang yang bergerak,
 * jadi tidak perlu persetujuan Administrator.
 */
class NotaEditService
{
    /**
     * @return array{boleh: bool, alasan: ?string}
     */
    public static function bolehEdit(object $transaksi, ?User $user = null, ?bool $adaItemSelesai = null, ?bool $adaItemProduksi = null, ?float $totalTerbayar = null): array
    {
        $user ??= Auth::user();

        if (!$transaksi) {
            return ['boleh' => false, 'alasan' => 'Nota tidak ditemukan.'];
        }
        if (!$user) {
            return ['boleh' => false, 'alasan' => 'Silakan login kembali.'];
        }
        if (($transaksi->status_nota ?? '') !== 'Aktif') {
            return ['boleh' => false, 'alasan' => 'Nota ini sudah dibatalkan.'];
        }

        // Kunci nota Lunas (keputusan bisnis final C): status pembayaran
        // dihitung dari StatusPembayaran::dari() (pembayaran_riwayat), BUKAN
        // dari kolom status_pembayaran yang bisa basi. Nota total <= 0
        // (tidak ada tagihan) tidak dianggap Lunas untuk tujuan kunci.
        $totalBayarLunas = (float) ($transaksi->total_bayar ?? 0);
        // Dihitung sekali (riwayat memakai LEFT JOIN subquery - tanpa ini, 1 query SUM per baris).
        $totalTerbayarLunas = $totalTerbayar ?? (float) DB::table('pembayaran_riwayat')
            ->where('id_transaksi', $transaksi->id_transaksi)
            ->sum('nominal');
        if ($totalBayarLunas > 0
            && \App\Services\StatusPembayaran::dari($totalBayarLunas, $totalTerbayarLunas) === \App\Services\StatusPembayaran::LUNAS) {
            return ['boleh' => false, 'alasan' => 'Nota sudah Lunas — tidak bisa diedit. Gunakan Batalkan Nota (persetujuan Administrator) atau buat nota baru untuk tambahan.'];
        }

        // Operator yang membuat nota boleh mengeditnya sendiri. Administrator
        // boleh mengedit nota siapa pun (b efetivo untuk memperbaiki salah
        // input kasir). Role lain tidak.
        $pemilik = (int) ($transaksi->id_user_kasir ?? 0) === (int) $user->id_user;
        $admin = optional($user->role)->nama_role === 'Administrator';
        if (!$pemilik && !$admin) {
            return ['boleh' => false, 'alasan' => 'Hanya operator yang membuat nota ini yang boleh mengedit.'];
        }

        // Syarat hari yang sama: pengembalian bahan ke stok hanya aman
        // selama bahan itu belum dimakan order lain. Kalau nota sudah
        // beberapa hari lalu, bahan-bahannya bisa sudah keluar - dan
        // pengembaliannya akan membuat stok melebihi reality.
        if (Carbon::parse($transaksi->tgl_order)->toDateString() !== now()->toDateString()) {
            return ['boleh' => false, 'alasan' => 'Edit hanya boleh dilakukan di hari yang sama dengan nota.'];
        }

        // Item yang sudah masuk produksi tidak boleh diedit: bahan-bahannya
        // bisa sudah dipotong, jadi pengembaliannya ke stok akan membuat
        // stok melebihi reality. 'Selesai' = sudah jadi, 'Cetak' = SPK-nya
        // sudah dicetak dan item sedang diproses.
        //
        // Status 'Cetak' ikut dihitung di sini karena penanda yang dipakai
        // daftar Riwayat (subquery di PenjualanController::riwayat) juga
        // menghitungnya - kalau hanya salah satu yang dipakai, tombol Edit di
        // daftar dan penolakan saat menyimpan bisa berbeda pendapat.
        // Item JASA (id_produk NULL) sengaja TIDAK dihitung: statusnya 'Selesai'
        // sejak nota dibuat dan tidak boleh mengunci koreksi - konsisten dengan
        // batalkanNota() dan komentar niat di PenjualanController.
        $adaItemSelesai ??= DB::table('transaksi_detail')
            ->where('id_transaksi', $transaksi->id_transaksi)
            ->whereNotNull('id_produk')
            ->whereIn('status_spk', ['Selesai', 'Cetak'])
            ->exists();
        if ($adaItemSelesai) {
            return ['boleh' => false, 'alasan' => 'Ada item yang sudah masuk produksi - nota ini tidak bisa diedit.'];
        }

        $adaItemProduksi ??= DB::table('transaksi_detail')
            ->where('id_transaksi', $transaksi->id_transaksi)
            ->whereNotNull('id_produk')
            ->exists();
        if ($adaItemProduksi && ($transaksi->status_collecting ?? '') === 'Sudah Diambil') {
            return ['boleh' => false, 'alasan' => 'Barang sudah diambil konsumen - nota ini tidak bisa diedit.'];
        }

        return ['boleh' => true, 'alasan' => null];
    }

    /**
     * Tanda "boleh diedit" untuk daftar di Riwayat Nota.
     *
     * Penanda item selesai & item produksi diambil sebagai subquery di
     * query utama, supaya daftar riwayat tidak jadi N+1 query (satu
     * nota satu query, bisa ratusan baris).
     */
    public static function penandaRiwayat(object $transaksi, ?User $user = null): array
    {
        return self::bolehEdit(
            $transaksi,
            $user,
            isset($transaksi->ada_item_selesai) ? (bool) $transaksi->ada_item_selesai : null,
            isset($transaksi->ada_item_produksi) ? (bool) $transaksi->ada_item_produksi : null,
            isset($transaksi->total_terbayar) ? (float) $transaksi->total_terbayar : null
        );
    }
}
