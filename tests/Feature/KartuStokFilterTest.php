<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KartuStokFilterTest extends TestCase
{
    use RefreshDatabase;

    private int $idMesin;
    private int $idKat;

    private int $urutKode = 0;

    private function bahan(string $nama, float $stok, float $min, string $status = 'aktif', ?int $kat = null): int
    {
        // Kode berurutan (bukan acak): dua bahan bernama mirip pernah bentrok di unique kode_bahan.
        return DB::table('bahan_baku')->insertGetId(['kode_bahan' => strtoupper(substr($nama, 0, 4)) . str_pad((string) ++$this->urutKode, 3, '0', STR_PAD_LEFT), 'nama_bahan' => $nama, 'id_mesin' => $this->idMesin, 'id_kategori' => $kat ?? $this->idKat,
            'nilai_konversi' => 1, 'stok' => $stok, 'stok_minimal' => $min, 'harga_rata_rata' => 0, 'satuan_stok' => 'rim', 'status' => $status]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
        $this->idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M1', 'tipe_mesin' => 'D']);
        $this->idKat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K1']);
    }

    public function test_filter_status_stok_kategori_status_bahan_dan_mutasi_pada_daftar_kartu_stok(): void
    {
        $kat2 = DB::table('kategori')->insertGetId(['nama_kategori' => 'K2']);
        $habis = $this->bahan('Bahan Habis', 0, 5);
        $tipis = $this->bahan('Bahan Tipis', 3, 5);
        $aman = $this->bahan('Bahan Aman', 50, 5, 'aktif', $kat2);
        $mati = $this->bahan('Bahan Mati', 9, 1, 'nonaktif');
        DB::table('kartu_stok')->insert(['id_bahan' => $aman, 'tanggal' => '2026-09-10 10:00:00', 'tipe' => 'IN', 'qty' => 5, 'stok_sebelum' => 45, 'stok_sesudah' => 50]);

        $this->get('/kartu-stok?status_stok=habis')->assertOk()->assertSee('Bahan Habis')->assertDontSee('Bahan Tipis')->assertDontSee('Bahan Aman');
        $this->get('/kartu-stok?status_stok=menipis')->assertOk()->assertSee('Bahan Tipis')->assertDontSee('Bahan Habis');
        $this->get('/kartu-stok?status_stok=aman')->assertOk()->assertSee('Bahan Aman')->assertDontSee('Bahan Tipis');
        $this->get("/kartu-stok?kategori={$kat2}")->assertOk()->assertSee('Bahan Aman')->assertDontSee('Bahan Habis');
        $this->get('/kartu-stok')->assertOk()->assertDontSee('Bahan Mati');
        $this->get('/kartu-stok?status=nonaktif')->assertOk()->assertSee('Bahan Mati')->assertDontSee('Bahan Habis');
        $this->get('/kartu-stok?dari=2026-09-01&sampai=2026-09-30&tipe=IN')->assertOk()->assertSee('Bahan Aman')->assertDontSee('Bahan Tipis');
        $this->get('/kartu-stok?dari=2026-10-01')->assertOk()->assertDontSee('Bahan Aman');
    }

    public function test_detail_kartu_stok_bisa_dicari_disaring_pencatat_dan_menampilkan_ringkasan(): void
    {
        $id = $this->bahan('Kertas Uji', 10, 2);
        $u = auth()->id();
        DB::table('kartu_stok')->insert([
            ['id_bahan' => $id, 'tanggal' => '2026-09-05 09:00:00', 'tipe' => 'IN', 'qty' => 10, 'stok_sebelum' => 0, 'stok_sesudah' => 10, 'referensi' => 'SJ-AAA', 'keterangan' => 'Penerimaan Gudang', 'created_by' => $u],
            ['id_bahan' => $id, 'tanggal' => '2026-09-06 09:00:00', 'tipe' => 'OUT', 'qty' => 4, 'stok_sebelum' => 10, 'stok_sesudah' => 6, 'referensi' => 'ORG-BBB', 'keterangan' => 'Pemakaian produksi', 'created_by' => $u],
        ]);
        $q = '?awal=2026-09-01&akhir=2026-09-30';

        $this->get("/kartu-stok/{$id}{$q}&cari=SJ-AAA")->assertOk()->assertSee('SJ-AAA')->assertDontSee('ORG-BBB');
        $this->get("/kartu-stok/{$id}{$q}&oleh={$u}")->assertOk()->assertSee('SJ-AAA')->assertSee('ORG-BBB');
        $this->get("/kartu-stok/{$id}{$q}&oleh=999999")->assertOk()->assertDontSee('SJ-AAA');
        $this->get("/kartu-stok/{$id}{$q}")->assertOk()->assertSee('Total Masuk')->assertSee('Total Keluar')->assertSee('Rentang cepat');
    }
}
