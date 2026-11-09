<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit lanjutan dari temuan "menu ghost" sesi ini: ternyata SALAH DIAGNOSIS
 * sebelumnya. Cross-check penuh menus.url_link terhadap config/menu_access.php
 * (sumber kebenaran akses yang sesungguhnya, dikonfirmasi tidak pernah baca
 * kolom url_link sama sekali - middleware murni jalan dari config, menus
 * table cuma dipakai AksesController untuk grid checkbox Pengaturan Akses)
 * membuktikan 'Laporan Kas' & 'Laporan Produksi' SUDAH berfungsi penuh lewat
 * rule 'laporan/kas' & 'laporan/produksi' - url_link di tabel menus-nya saja
 * yang keliru ('laporan-kas'/'laporan-produksi', pakai strip bukan slash),
 * jadi terlihat seperti tidak terdaftar padahal sebenarnya jalan normal.
 *
 * Ini murni kosmetik (url_link tidak dipakai navigasi/keamanan sama sekali)
 * tapi tetap dirapikan supaya kolom ini tidak menyesatkan audit berikutnya
 * seperti yang baru saja terjadi di sesi ini sendiri.
 *
 * 'BOM Produk' (id=2, url_link='bom') SENGAJA TIDAK disentuh - itu bukan
 * salah label, tapi entri menu duplikat/lawas: BOM sudah digabung jadi
 * bagian dari halaman Produk (menu 'Produk & BOM', id=13) sejak lama, rute
 * /bom sendiri tidak pernah ada. Dibiarkan sebagai clutter tidak berbahaya
 * (tidak ada rule mengarah ke sana, jadi tidak pernah bisa memberi akses ke
 * mana pun) daripada dihapus tanpa kepastian dampaknya ke role_permissions.
 */
return new class extends Migration
{
    private array $perbaikan = [
        3  => 'penjualan',
        14 => 'setting-harga',
        25 => 'laporan/order',
        26 => 'laporan/produksi',
        29 => 'laporan/kas',
    ];

    public function up(): void
    {
        foreach ($this->perbaikan as $idMenu => $urlBenar) {
            DB::table('menus')->where('id_menu', $idMenu)->update(['url_link' => $urlBenar]);
        }
    }

    public function down(): void
    {
        $urlLama = [
            3  => 'transaksi',
            14 => 'setting_harga',
            25 => 'laporan-order',
            26 => 'laporan-produksi',
            29 => 'laporan-kas',
        ];
        foreach ($urlLama as $idMenu => $url) {
            DB::table('menus')->where('id_menu', $idMenu)->update(['url_link' => $url]);
        }
    }
};
