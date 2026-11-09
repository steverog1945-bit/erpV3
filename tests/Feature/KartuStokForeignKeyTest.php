<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P1-3 (audit hardening): kartu_stok.id_bahan sebelumnya berakhir dengan DUA
 * foreign key terpisah yang merujuk bahan_baku (id_bahan) pada instalasi
 * fresh - 'kartu_stok_id_bahan_foreign' (nama otomatis dari migration
 * pembuat tabel) dan 'fk_kartu_stok_bahan' (dari migration "fix" yang
 * seharusnya mengganti, tapi salah nama constraint yang di-drop sehingga
 * malah menambah yang baru). Tes ini mengunci DUA hal: (1) hanya satu FK
 * yang tersisa, dan (2) aturan RESTRICT-nya (bahan dengan riwayat
 * kartu_stok tidak bisa dihapus) tetap berlaku - migrasi pembersihan tidak
 * boleh diam-diam melonggarkan proteksi ini.
 */
class KartuStokForeignKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_hanya_satu_foreign_key_ke_bahan_baku_di_kartu_stok(): void
    {
        $jumlah = DB::selectOne(
            "SELECT COUNT(*) as c FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'kartu_stok' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
               AND CONSTRAINT_NAME IN ('kartu_stok_id_bahan_foreign', 'fk_kartu_stok_bahan')"
        )->c;

        $this->assertSame(1, (int) $jumlah, 'harus tepat satu FK (bukan nol - proteksi hilang - atau dua - duplikat)');
    }

    public function test_bahan_dengan_riwayat_kartu_stok_masih_tidak_bisa_dihapus_permanen(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M1', 'tipe_mesin' => 'Cetak']);
        $idKat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $idBahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'B1', 'nama_bahan' => 'Bahan Uji', 'id_mesin' => $idMesin, 'id_kategori' => $idKat,
            'stok' => 10, 'nilai_konversi' => 1, 'harga_rata_rata' => 100, 'satuan_stok' => 'lbr', 'status' => 'aktif',
        ]);
        DB::table('kartu_stok')->insert([
            'id_bahan' => $idBahan, 'tanggal' => now(), 'tipe' => 'IN', 'qty' => 10,
            'stok_sebelum' => 0, 'stok_sesudah' => 10, 'referensi' => 'UJI', 'keterangan' => 'uji',
            'created_by' => $admin->id_user, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)->delete("/bahan-baku/{$idBahan}")->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('bahan_baku', ['id_bahan' => $idBahan]);
    }
}
