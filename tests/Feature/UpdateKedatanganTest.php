<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Edit kedatangan bahan yang sudah divalidasi:
 *   - status tidak turun diam-diam ke Draft (form edit tidak mengirim status) dan stok hanya bergeser sebesar selisih qty;
 *   - kedatangan yang sudah difakturkan ditolak (faktur, hutang, dan persediaan mengacu ke qty lama);
 *   - edit yang membuat stok minus (bahan sudah terpakai) ditolak tanpa perubahan apa pun.
 */
class UpdateKedatanganTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $bahan;
    private int $sup;
    private int $gudang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($this->admin);
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'x']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K', 'kode_kategori' => 'K']);
        $this->bahan = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'B', 'nama_bahan' => 'Flexi', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 0, 'nilai_konversi' => 1, 'harga_rata_rata' => 1000, 'satuan_stok' => 'm2', 'status' => 'aktif']);
        $this->sup = DB::table('supplier')->insertGetId(['nama_supplier' => 'PT A', 'alamat' => '-', 'kontak' => '0']);
        $this->gudang = DB::table('gudang')->insertGetId(['nama_gudang' => 'Utama']);
    }

    private function kedatanganDivalidasi(string $sj, float $qty): int
    {
        $this->post('/kedatangan-bahan', ['no_surat_jalan' => $sj, 'id_supplier' => $this->sup, 'tgl_datang' => now()->toDateString(),
            'id_gudang' => $this->gudang, 'id_bahan' => [$this->bahan], 'qty' => [$qty]])->assertRedirect();
        $id = (int) DB::table('kedatangan_bahan')->where('no_surat_jalan', $sj)->value('id_kedatangan');
        $this->patch("/kedatangan-bahan/validate/{$id}");

        return $id;
    }

    /** Isi form edit seperti yang dikirim halaman edit (tanpa field status). */
    private function edit(int $id, string $sj, float $qty)
    {
        return $this->put("/kedatangan-bahan/{$id}", ['no_surat_jalan' => $sj, 'id_supplier' => $this->sup, 'tgl_datang' => now()->toDateString(),
            'id_gudang' => $this->gudang, 'id_bahan' => [$this->bahan], 'qty' => [$qty], 'satuan' => ['m2']]);
    }

    private function stok(): float
    {
        return (float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('stok');
    }

    private function potret(): array
    {
        return [
            'stok' => $this->stok(),
            'kartu' => DB::table('kartu_stok')->count(),
            'detail' => DB::table('kedatangan_bahan_detail')->get(['id_bahan', 'qty'])->map(fn ($d) => [(int) $d->id_bahan, (float) $d->qty])->all(),
            'status' => DB::table('kedatangan_bahan')->pluck('status')->all(),
        ];
    }

    public function test_edit_kedatangan_selesai_tetap_selesai_dan_stok_bergeser_sebesar_selisih(): void
    {
        $id = $this->kedatanganDivalidasi('SJ-1', 100);
        $this->assertEquals(100, $this->stok());

        $this->edit($id, 'SJ-1', 120)->assertSessionHasNoErrors()->assertSessionMissing('error');

        $this->assertSame('Selesai', DB::table('kedatangan_bahan')->where('id_kedatangan', $id)->value('status'), 'status turun ke Draft diam-diam');
        $this->assertEquals(120, $this->stok());
        $kartu = DB::table('kartu_stok')->where('id_bahan', $this->bahan)->orderBy('id')->get();
        foreach ($kartu as $i => $k) {
            if ($i > 0) {
                $this->assertEquals((float) $kartu[$i - 1]->stok_sesudah, (float) $k->stok_sebelum, 'rantai kartu stok putus');
            }
        }
        $this->assertEquals(120, (float) $kartu->last()->stok_sesudah);
    }

    public function test_edit_kedatangan_draft_tetap_draft_tanpa_mengubah_stok(): void
    {
        $this->post('/kedatangan-bahan', ['no_surat_jalan' => 'SJ-D', 'id_supplier' => $this->sup, 'tgl_datang' => now()->toDateString(),
            'id_gudang' => $this->gudang, 'id_bahan' => [$this->bahan], 'qty' => [10]]);
        $id = (int) DB::table('kedatangan_bahan')->where('no_surat_jalan', 'SJ-D')->value('id_kedatangan');

        $this->edit($id, 'SJ-D', 15)->assertSessionMissing('error');

        $this->assertSame('Draft', DB::table('kedatangan_bahan')->where('id_kedatangan', $id)->value('status'));
        $this->assertEquals(0, $this->stok());
        $this->assertEquals(15, (float) DB::table('kedatangan_bahan_detail')->where('id_kedatangan', $id)->value('qty'));
    }

    public function test_edit_kedatangan_yang_sudah_difakturkan_ditolak(): void
    {
        $id = $this->kedatanganDivalidasi('SJ-F', 100);
        \App\Models\PembelianBahan::create(['id_kedatangan' => $id, 'no_faktur' => 'FAK-F', 'tgl_transaksi' => now()->toDateString(), 'tipe_pembelian' => 'Tempo',
            'status_pembayaran' => 'Hutang', 'grand_total' => 100000, 'jatuh_tempo_tgl' => now()->addDays(10)->toDateString()]);
        $sebelum = $this->potret();

        $this->edit($id, 'SJ-F', 50)->assertSessionHas('error');

        $this->assertSame($sebelum, $this->potret());
    }

    public function test_edit_yang_membuat_stok_minus_ditolak_tanpa_perubahan(): void
    {
        $id = $this->kedatanganDivalidasi('SJ-2', 100);
        DB::table('bahan_baku')->where('id_bahan', $this->bahan)->update(['stok' => 5]);
        DB::table('kartu_stok')->insert(['id_bahan' => $this->bahan, 'tanggal' => now(), 'tipe' => 'OUT', 'qty' => 95, 'stok_sebelum' => 100, 'stok_sesudah' => 5, 'referensi' => 'UJI', 'keterangan' => 'pakai', 'created_at' => now(), 'updated_at' => now()]);
        $sebelum = $this->potret();

        // 100 -> 50: stok 5 - 100 + 50 = -45.
        $this->edit($id, 'SJ-2', 50)->assertSessionHas('error');

        $this->assertSame($sebelum, $this->potret());
    }

    public function test_edit_dengan_qty_tidak_sah_ditolak_validasi_tanpa_perubahan(): void
    {
        $id = $this->kedatanganDivalidasi('SJ-V', 100);
        $sebelum = $this->potret();
        $isian = ['no_surat_jalan' => 'SJ-V', 'id_supplier' => $this->sup, 'tgl_datang' => now()->toDateString(), 'id_gudang' => $this->gudang];

        $this->put("/kedatangan-bahan/{$id}", $isian + ['id_bahan' => [$this->bahan], 'qty' => [-500]])->assertSessionHasErrors('qty.0');
        $this->put("/kedatangan-bahan/{$id}", $isian + ['id_bahan' => [$this->bahan], 'qty' => ['seratus']])->assertSessionHasErrors('qty.0');
        $this->put("/kedatangan-bahan/{$id}", $isian + ['id_bahan' => [999999], 'qty' => [10]])->assertSessionHasErrors('id_bahan.0');
        $this->put("/kedatangan-bahan/{$id}", $isian + ['id_bahan' => [$this->bahan, $this->bahan], 'qty' => [10]])->assertSessionHasErrors('qty');

        $this->assertSame($sebelum, $this->potret(), 'isian tidak sah tetap mengubah stok/detail');
    }

    public function test_edit_boleh_lagi_setelah_fakturnya_dibatalkan(): void
    {
        $id = $this->kedatanganDivalidasi('SJ-B', 100);
        \App\Models\PembelianBahan::create(['id_kedatangan' => $id, 'no_faktur' => 'FAK-B', 'tgl_transaksi' => now()->toDateString(), 'tipe_pembelian' => 'Tempo',
            'status_pembayaran' => 'Hutang', 'grand_total' => 100000, 'jatuh_tempo_tgl' => now()->addDays(10)->toDateString()])->delete();

        // Faktur sudah dibatalkan (jurnalnya dibalik): qty kedatangan yang salah sekarang bisa dikoreksi.
        $this->edit($id, 'SJ-B', 90)->assertSessionMissing('error');
        $this->assertEquals(90, $this->stok());
    }

    private function faktur(int $idKed, string $no, float $qty, float $harga, bool $ppn = false)
    {
        return $this->post('/pembelian-bahan', ['id_kedatangan' => $idKed, 'no_faktur' => $no, 'tgl_transaksi' => now()->toDateString(), 'tipe_pembelian' => 'Tempo',
            'id_bahan' => [$this->bahan], 'qty' => [$qty], 'harga' => [(string) $harga], 'diskon' => ['0'], 'potongan' => ['0']] + ($ppn ? ['include_pajak' => 1] : []));
    }

    private function avg(): float
    {
        return round((float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('harga_rata_rata'), 2);
    }

    public function test_harga_rata_rata_memakai_stok_saat_barang_masuk_bukan_saat_faktur(): void
    {
        DB::table('bahan_baku')->where('id_bahan', $this->bahan)->update(['stok' => 100, 'harga_rata_rata' => 10]);
        $id = $this->kedatanganDivalidasi('SJ-AVG', 50);   // stok 150
        DB::table('bahan_baku')->where('id_bahan', $this->bahan)->update(['stok' => 10]); // 140 terpakai sebelum faktur datang

        $this->faktur($id, 'FAK-AVG', 50, 20)->assertSessionHas('success');

        // (100 x 10 + 50 x 20) / 150 = 13,33. Rumus lama memakai stok 10 - 50 (negatif) sehingga menjadi 20.
        $this->assertEquals(13.33, $this->avg());
    }

    public function test_harga_rata_rata_memakai_harga_efektif_termasuk_ppn_seperti_jurnal_persediaan(): void
    {
        DB::table('bahan_baku')->where('id_bahan', $this->bahan)->update(['harga_rata_rata' => 0]);
        $id = $this->kedatanganDivalidasi('SJ-PPN', 10);

        $this->faktur($id, 'FAK-PPN', 10, 100, true)->assertSessionHas('success');

        // Jurnal Persediaan = 10 x 100 x 1,11 = 1.110, jadi harga satuan efektif 111.
        $this->assertEquals(111, $this->avg());
    }
}
