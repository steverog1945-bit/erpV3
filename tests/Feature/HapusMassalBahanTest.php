<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hapus massal bahan baku memakai aturan yang sama dengan hapus satuan: bahan yang sudah punya riwayat tidak terhapus
 * walau tabel riwayatnya tidak punya FK ke bahan_baku (purchase request, opname, waste, nota, jersey); bahan tanpa
 * riwayat tetap terhapus, dan pesan menyebut bahan mana yang ditahan.
 */
class HapusMassalBahanTest extends TestCase
{
    use RefreshDatabase;

    public function test_hapus_massal_tidak_menghapus_bahan_berriwayat(): void
    {
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'x']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K', 'kode_kategori' => 'K']);
        $bahan = fn (string $kode) => DB::table('bahan_baku')->insertGetId(['kode_bahan' => $kode, 'nama_bahan' => 'Bahan ' . $kode, 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 0, 'nilai_konversi' => 1, 'harga_rata_rata' => 0, 'satuan_stok' => 'm2', 'status' => 'aktif']);
        $berriwayat = $bahan('B1');
        $bersih = $bahan('B2');

        // Baris riwayat di tabel tanpa FK ke bahan_baku (induk purchase request tidak relevan untuk uji ini).
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('purchase_request_details')->insert(['id_purchase_request' => 999, 'id_bahan' => $berriwayat, 'qty' => 5]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->post('/bahan-baku/bulk-delete', ['ids' => [$berriwayat, $bersih]])->assertSessionHas('success');

        $this->assertTrue(DB::table('bahan_baku')->where('id_bahan', $berriwayat)->exists(), 'bahan berriwayat ikut terhapus lewat hapus massal');
        $this->assertFalse(DB::table('bahan_baku')->where('id_bahan', $bersih)->exists(), 'bahan tanpa riwayat seharusnya tetap terhapus');
        $this->assertStringContainsString('Bahan B1', session('success'));
    }
}
