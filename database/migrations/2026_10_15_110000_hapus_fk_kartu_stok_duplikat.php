<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\Concerns\SafelyDropsForeignKeys;

/**
 * Hardening P1-3 (audit): pada instalasi FRESH (migrate:fresh dari kosong -
 * database testing, CI, atau deploy VPS baru), kartu_stok.id_bahan berakhir
 * dengan DUA foreign key terpisah yang merujuk bahan_baku(id_bahan):
 * 'kartu_stok_id_bahan_foreign' (nama otomatis dari
 * 2026_09_07_000001_reconcile_inventory_module_schema.php saat membuat
 * tabel) dan 'fk_kartu_stok_bahan' (dari
 * 2026_09_10_310000_fix_kartu_stok_bahan_fk_to_restrict.php, yang mencoba
 * dropForeignIfExists('kartu_stok', 'fk_kartu_stok_bahan') - nama yang
 * BELUM PERNAH ada saat itu, jadi drop-nya no-op, lalu menambahkan
 * constraint baru dengan nama itu alih-alih mengganti yang lama).
 *
 * Hasil akhirnya secara FUNGSIONAL tetap benar (keduanya ON DELETE
 * RESTRICT), jadi ini bukan bug data - murni migrasi berlipat yang
 * membebani setiap INSERT ke kartu_stok (tabel paling sering ditulis:
 * setiap baris item order, setiap reversal, setiap penyesuaian stok
 * manual) dengan validasi constraint yang sama dua kali.
 *
 * Aman di database mana pun (termasuk yang di dalamnya kedua constraint
 * ini ternyata sudah tidak ada sama sekali, mis. audit menemukan database
 * kerja saat ini nol FK di kolom ini - lihat SafelyDropsForeignKeys):
 * dropForeignIfExists mengecek dulu, tidak meledak kalau tidak ada apa-apa
 * untuk didrop.
 */
return new class extends Migration
{
    use SafelyDropsForeignKeys;

    public function up(): void
    {
        // Sisakan 'fk_kartu_stok_bahan' (eksplisit RESTRICT) - drop yang lama/otomatis.
        $this->dropForeignIfExists('kartu_stok', 'kartu_stok_id_bahan_foreign');
    }

    public function down(): void
    {
        // Tidak direkonstruksi - constraint yang tersisa ('fk_kartu_stok_bahan')
        // sudah menegakkan aturan RESTRICT yang identik untuk kolom yang sama;
        // menambah baris kedua yang isinya sama lagi cuma mengulang masalah
        // yang migrasi ini perbaiki.
    }
};
