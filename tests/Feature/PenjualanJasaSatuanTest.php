<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penjualan dan Jasa: qty jasa mengikuti tipe satuannya di Master Satuan (Luas = p x l x jumlah,
 * Panjang = p x jumlah, lainnya qty biasa), dihitung ulang di server. HPP jasa 0 seperti Input Pesanan.
 */
class PenjualanJasaSatuanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $konsumen;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->konsumen = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()]);
    }

    private function jasa(string $nama, string $satuan, string $tipe): int
    {
        $idSatuan = DB::table('satuan')->where('nama_satuan', $satuan)->value('id_satuan')
            ?? DB::table('satuan')->insertGetId(['nama_satuan' => $satuan, 'nilai_konversi' => 1, 'tipe_satuan' => $tipe]);

        return DB::table('jasa')->insertGetId(['nama_jasa' => $nama, 'id_satuan' => $idSatuan, 'harga_default' => 50000, 'biaya_operasional' => 5000]);
    }

    private function jual(array $item)
    {
        return $this->actingAs($this->admin)->post('/penjualan-barang/store', [
            'id_konsumen' => $this->konsumen, 'items' => [$item + ['tipe' => 'jasa', 'harga' => 50000]],
        ]);
    }

    public function test_jasa_luas_dihitung_dari_ukuran_di_server(): void
    {
        $id = $this->jasa('Pasang Stiker', 'm2', 'Luas');

        // qty yang dikirim sengaja salah: server memakai 2 x 1,5 x 3 = 9.
        $this->jual(['id_jasa' => $id, 'qty' => 1, 'p' => 2, 'l' => 1.5, 'jumlah' => 3])->assertSessionHasNoErrors();

        $d = DB::table('transaksi_detail')->first();
        $this->assertEquals(9, $d->qty);
        $this->assertEquals(2, $d->p_ukuran);
        $this->assertEquals(1.5, $d->l_ukuran);
        $this->assertEquals(450000, $d->subtotal);
        $this->assertEquals(0, $d->hpp_snapshot, 'HPP jasa 0, bukan harga jual master');
        $this->assertEquals(450000, DB::table('transaksi')->value('total_bayar'));
    }

    public function test_jasa_luas_tanpa_ukuran_ditolak(): void
    {
        $id = $this->jasa('Pasang Stiker', 'm2', 'Luas');

        $this->jual(['id_jasa' => $id, 'qty' => 2])->assertSessionHas('error');
        $this->assertSame(0, DB::table('transaksi')->count());
    }

    public function test_jasa_panjang_dan_jasa_satuan_biasa(): void
    {
        $jahit = $this->jasa('Jahit Tepi', 'm', 'Panjang');
        $press = $this->jasa('Press Hidrolik', 'pcs', 'Barang');

        $this->actingAs($this->admin)->post('/penjualan-barang/store', [
            'id_konsumen' => $this->konsumen, 'items' => [
                ['tipe' => 'jasa', 'id_jasa' => $jahit, 'qty' => 1, 'harga' => 2000, 'p' => 4, 'jumlah' => 2],
                ['tipe' => 'jasa', 'id_jasa' => $press, 'qty' => 5, 'harga' => 3000],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertEquals([8.0, 5.0], DB::table('transaksi_detail')->orderBy('id_detail')->pluck('qty')->map(fn ($q) => (float) $q)->all());
    }

    public function test_halaman_mengirim_tipe_satuan_jasa(): void
    {
        $this->jasa('Pasang Stiker', 'm2', 'Luas');

        $this->actingAs($this->admin)->get('/penjualan-barang')->assertOk()->assertSee('data-tipe-satuan="Luas"', false)->assertSee('blok_ukuran_jasa', false);
    }
}
