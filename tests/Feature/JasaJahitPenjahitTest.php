<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\TarifJahitJerseyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Jasa jahit borongan: penjahit wajib di setiap catatan, tarif jersey per lengan/kerah dipilih otomatis
 * per baju, catatan jersey tidak boleh melebihi baju yang belum dibayar, dan riwayat tidak ikut terhapus.
 */
class JasaJahitPenjahitTest extends TestCase
{
    use RefreshDatabase;

    private int $pendek;
    private int $panjang;
    private int $oneck;
    private int $wangki;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));

        $this->pendek = $this->master('jersey_sleeve_types', 'Pendek');
        $this->panjang = $this->master('jersey_sleeve_types', 'Panjang');
        $this->oneck = $this->master('jersey_collar_types', 'Oneck');
        $this->wangki = $this->master('jersey_collar_types', 'Wangki');
    }

    private function master(string $tabel, string $nama): int
    {
        return DB::table($tabel)->where('name', $nama)->value('id')
            ?? DB::table($tabel)->insertGetId(['name' => $nama, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function tarif(string $nama, float $harga, ?int $lengan = null, ?int $kerah = null): int
    {
        return DB::table('jasa_jahit')->insertGetId([
            'nama_jahit' => $nama, 'harga_borongan' => $harga, 'untuk_jersey' => true,
            'jersey_sleeve_type_id' => $lengan, 'jersey_collar_type_id' => $kerah, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function penjahit(string $nama = 'Bu Sri', bool $aktif = true): int
    {
        return DB::table('penjahit')->insertGetId(['nama' => $nama, 'aktif' => $aktif, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Order: 3 pemain lengan pendek oneck, 1 pemain lengan panjang (ikut baris), 2 pemain pendek wangki, plus 4 baju jumlah saja lengan panjang oneck. */
    private function order(): int
    {
        $o = DB::table('jersey_orders')->insertGetId([
            'order_number' => 'JO-UJI-1', 'order_date' => now()->toDateString(), 'customer_name' => 'Tim Garuda',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $baris = fn (string $tipe, int $qty, int $lengan, int $kerah) => DB::table('jersey_order_lines')->insertGetId([
            'jersey_order_id' => $o, 'nama_barang' => 'Jersey', 'thumbnail' => '', 'order_type' => $tipe, 'is_susulan' => 0,
            'qty' => $qty, 'harga_satuan' => 100000, 'subtotal' => 100000 * $qty, 'size_surcharge_total' => 0, 'sleeve_collar_surcharge_total' => 0,
            'jersey_sleeve_type_id' => $lengan, 'jersey_collar_type_id' => $kerah, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $pemain = fn (int $line, ?int $lengan, ?int $kerah) => DB::table('jersey_order_items')->insert([
            'jersey_order_line_id' => $line, 'player_name' => 'P', 'size' => 'L', 'gender' => 'L',
            'jersey_sleeve_type_id' => $lengan, 'jersey_collar_type_id' => $kerah, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $l1 = $baris('player_list', 6, $this->pendek, $this->oneck);
        $pemain($l1, null, null);
        $pemain($l1, null, null);
        $pemain($l1, null, null);
        $pemain($l1, $this->panjang, null);
        $pemain($l1, null, $this->wangki);
        $pemain($l1, null, $this->wangki);
        $baris('quantity_only', 4, $this->panjang, $this->oneck);

        return $o;
    }

    public function test_tarif_dipilih_per_baju_kerah_mengalahkan_lengan(): void
    {
        $pendek = $this->tarif('Lengan pendek', 5000, $this->pendek);
        $panjang = $this->tarif('Lengan panjang', 7000, $this->panjang);
        $wangki = $this->tarif('Kerah wangki', 8000, null, $this->wangki);

        $r = collect(TarifJahitJerseyService::rincian($this->order())['baris'])->keyBy('id_jasa_jahit');

        $this->assertSame(3, $r[$pendek]['qty']);
        $this->assertSame(5, $r[$panjang]['qty'], '1 pemain lengan panjang + 4 baju jumlah saja');
        $this->assertSame(2, $r[$wangki]['qty'], 'kerah wangki memakai tarif kerah walau lengannya pendek');
    }

    public function test_baju_tanpa_tarif_dilaporkan_bukan_diam_diam_hilang(): void
    {
        $this->tarif('Lengan pendek', 5000, $this->pendek);

        $hasil = TarifJahitJerseyService::rincian($this->order());

        $this->assertSame(10, $hasil['total_baju']);
        $this->assertSame(5, array_sum(array_column($hasil['tanpa_tarif'], 'qty')));
    }

    public function test_catat_jersey_membekukan_harga_dan_menolak_bayar_dua_kali(): void
    {
        $pendek = $this->tarif('Lengan pendek', 5000, $this->pendek);
        $panjang = $this->tarif('Lengan panjang', 7000, $this->panjang);
        $this->tarif('Kerah wangki', 8000, null, $this->wangki);
        $pj = $this->penjahit();
        $order = $this->order();

        $this->post('/jasa-jahit/catat-jersey', [
            'id_penjahit' => $pj, 'id_jersey_order' => $order, 'tgl_catat' => now()->toDateString(),
            'qty' => [$pendek => 3, $panjang => 2],
        ])->assertSessionHas('success');

        $this->assertEquals(15000 + 14000, DB::table('jasa_jahit_catat')->where('id_penjahit', $pj)->sum('total'));

        // Harga katalog naik: catatan lama tetap, sisa panjang hanya 3.
        DB::table('jasa_jahit')->where('id_jasa_jahit', $panjang)->update(['harga_borongan' => 9000]);
        $this->post('/jasa-jahit/catat-jersey', [
            'id_penjahit' => $pj, 'id_jersey_order' => $order, 'tgl_catat' => now()->toDateString(), 'qty' => [$panjang => 4],
        ])->assertSessionHas('error');
        $this->post('/jasa-jahit/catat-jersey', [
            'id_penjahit' => $pj, 'id_jersey_order' => $order, 'tgl_catat' => now()->toDateString(), 'qty' => [$pendek => 1],
        ])->assertSessionHas('error');

        $this->post('/jasa-jahit/catat-jersey', [
            'id_penjahit' => $pj, 'id_jersey_order' => $order, 'tgl_catat' => now()->toDateString(), 'qty' => [$panjang => 3],
        ])->assertSessionHas('success');
        $this->assertEquals([7000.0, 9000.0], DB::table('jasa_jahit_catat')->where('id_jasa_jahit', $panjang)->orderBy('id_jasa_jahit_catat')->pluck('harga_snapshot')->map(fn ($h) => (float) $h)->all());
    }

    public function test_penjahit_wajib_dan_harus_aktif(): void
    {
        $id = DB::table('jasa_jahit')->insertGetId(['nama_jahit' => 'Keliling banner', 'harga_borongan' => 2500, 'created_at' => now(), 'updated_at' => now()]);
        $data = ['id_jasa_jahit' => $id, 'qty' => 2, 'tgl_catat' => now()->toDateString()];

        $this->post('/jasa-jahit/catat', $data)->assertSessionHasErrors('id_penjahit');
        $this->post('/jasa-jahit/catat', $data + ['id_penjahit' => $this->penjahit('Pak Lama', false)])->assertSessionHasErrors('id_penjahit');
        $this->post('/jasa-jahit/catat', $data + ['id_penjahit' => $this->penjahit()])->assertSessionHas('success');
        $this->assertEquals(5000, DB::table('jasa_jahit_catat')->value('total'));
    }

    public function test_riwayat_tidak_ikut_terhapus(): void
    {
        $tarif = $this->tarif('Lengan pendek', 5000, $this->pendek);
        $pj = $this->penjahit();
        $this->post('/jasa-jahit/catat-jersey', [
            'id_penjahit' => $pj, 'id_jersey_order' => $this->order(), 'tgl_catat' => now()->toDateString(), 'qty' => [$tarif => 1],
        ])->assertSessionHas('success');

        $this->delete("/jasa-jahit/{$tarif}")->assertSessionHas('error');
        $this->assertTrue(DB::table('jasa_jahit')->where('id_jasa_jahit', $tarif)->exists());

        $this->delete("/jasa-jahit/penjahit/{$pj}")->assertSessionHas('warning');
        $this->assertSame(0, (int) DB::table('penjahit')->where('id_penjahit', $pj)->value('aktif'));
        $this->assertSame(1, DB::table('jasa_jahit_catat')->count());
    }

    public function test_kombinasi_lengan_kerah_yang_sama_ditolak(): void
    {
        $this->tarif('Lengan pendek', 5000, $this->pendek);

        $this->post('/jasa-jahit', ['nama_jahit' => 'Pendek lagi', 'harga_borongan' => 6000, 'untuk_jersey' => 1, 'jersey_sleeve_type_id' => $this->pendek])
            ->assertSessionHas('error');
        $this->post('/jasa-jahit', ['nama_jahit' => 'Pendek wangki', 'harga_borongan' => 9000, 'untuk_jersey' => 1, 'jersey_sleeve_type_id' => $this->pendek, 'jersey_collar_type_id' => $this->wangki])
            ->assertSessionHas('success');
    }

    public function test_halaman_dan_rincian_bisa_dibuka(): void
    {
        $this->tarif('Lengan pendek', 5000, $this->pendek);
        $this->penjahit();
        $order = $this->order();

        $this->get('/jasa-jahit')->assertOk()->assertSee('Catat Jahit Jersey')->assertSee('Bu Sri')->assertSee('JO-UJI-1');
        $this->getJson("/jasa-jahit/jersey/{$order}/rincian")->assertOk()->assertJsonPath('total_baju', 10)->assertJsonPath('baris.0.qty', 5);
    }
}
