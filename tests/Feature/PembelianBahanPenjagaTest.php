<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit Fase 2: PembelianBahanController::store harus menolak SERVER-SIDE
 * faktur ganda untuk satu kedatangan, kedatangan yang belum Selesai, dan
 * bahan di luar kedatangan - sebelumnya hanya dropdown create() yang menyaring.
 */
class PembelianBahanPenjagaTest extends TestCase
{
    use RefreshDatabase;

    private int $idSup;
    private int $idGudang;
    private int $idBahan;
    private int $idBahanLain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));

        $this->idSup = DB::table('supplier')->insertGetId(['nama_supplier' => 'PT Uji', 'kontak' => '-']);
        $this->idGudang = DB::table('gudang')->insertGetId(['nama_gudang' => 'Utama']);
        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'D']);
        $idKat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $this->idBahan = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'P1', 'nama_bahan' => 'Kertas', 'id_mesin' => $idMesin, 'id_kategori' => $idKat, 'nilai_konversi' => 1, 'stok' => 5, 'harga_rata_rata' => 0, 'satuan_stok' => 'rim', 'status' => 'aktif']);
        $this->idBahanLain = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'P2', 'nama_bahan' => 'Tinta', 'id_mesin' => $idMesin, 'id_kategori' => $idKat, 'nilai_konversi' => 1, 'stok' => 5, 'harga_rata_rata' => 0, 'satuan_stok' => 'btl', 'status' => 'aktif']);
    }

    private function kedatangan(string $status = 'Selesai'): int
    {
        $id = DB::table('kedatangan_bahan')->insertGetId(['no_surat_jalan' => 'SJ-' . uniqid(), 'id_supplier' => $this->idSup, 'id_gudang' => $this->idGudang, 'tgl_datang' => now()->toDateString(), 'status' => $status]);
        DB::table('kedatangan_bahan_detail')->insert(['id_kedatangan' => $id, 'id_bahan' => $this->idBahan, 'qty' => 2, 'status' => 'OK']);

        return $id;
    }

    private function faktur(int $idKed, string $no, ?int $idBahan = null)
    {
        return $this->post('/pembelian-bahan', [
            'id_kedatangan' => $idKed, 'no_faktur' => $no, 'tgl_transaksi' => '2026-09-01', 'tipe_pembelian' => 'Tempo',
            'id_bahan' => [$idBahan ?? $this->idBahan], 'qty' => [2], 'harga' => ['100000'], 'diskon' => ['0'], 'potongan' => ['0'],
        ]);
    }

    public function test_faktur_kedua_untuk_kedatangan_yang_sama_ditolak_tanpa_jurnal_dan_tanpa_rata_rata_ganda(): void
    {
        $ked = $this->kedatangan();

        $this->faktur($ked, 'F-1')->assertSessionHas('success');
        $jurnal = DB::table('jurnal_umum')->count();
        $avg = (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('harga_rata_rata');

        // Nomor faktur BERBEDA (unique no_faktur tidak menolongnya) - tetap harus ditolak.
        $this->faktur($ked, 'F-2')->assertSessionHas('error');

        $this->assertSame(1, DB::table('pembelian_bahan')->where('id_kedatangan', $ked)->count());
        $this->assertSame($jurnal, DB::table('jurnal_umum')->count());
        $this->assertEquals($avg, (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('harga_rata_rata'));
    }

    public function test_kedatangan_belum_selesai_tidak_bisa_difaktur(): void
    {
        $ked = $this->kedatangan('Draft');

        $this->faktur($ked, 'F-D')->assertSessionHas('error');

        $this->assertSame(0, DB::table('pembelian_bahan')->count());
    }

    public function test_bahan_di_luar_kedatangan_ditolak(): void
    {
        $ked = $this->kedatangan();

        $this->faktur($ked, 'F-X', $this->idBahanLain)->assertSessionHas('error');

        $this->assertSame(0, DB::table('pembelian_bahan')->count());
        $this->assertSame(0, DB::table('pembelian_bahan_detail')->count());
    }

    public function test_kedatangan_tidak_ada_ditolak_dengan_pesan_bisnis_bukan_error_sql(): void
    {
        $this->faktur(999999, 'F-N')->assertSessionHas('error', 'Kedatangan bahan tidak ditemukan.');
    }

    public function test_nomor_faktur_kembar_ditolak_dengan_pesan_termasuk_faktur_yang_sudah_dibatalkan(): void
    {
        $this->faktur($this->kedatangan(), 'FAK-SAMA')->assertSessionHas('success');

        // Nomor yang sama untuk kedatangan lain: ditolak dengan pesan di kolom nomor faktur, bukan galat sistem.
        $this->faktur($this->kedatangan(), 'FAK-SAMA')->assertSessionHasErrors('no_faktur');

        // Faktur yang sudah dibatalkan (soft delete) tetap memegang nomornya: pembalikan jurnal memakai nomor itu.
        DB::table('pembelian_bahan')->where('no_faktur', 'FAK-SAMA')->update(['deleted_at' => now()]);
        $this->faktur($this->kedatangan(), 'FAK-SAMA')->assertSessionHasErrors('no_faktur');
        $this->assertSame(1, DB::table('pembelian_bahan')->where('no_faktur', 'FAK-SAMA')->count());
    }
}
