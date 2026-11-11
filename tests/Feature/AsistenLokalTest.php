<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AsistenLokalTest extends TestCase
{
    use RefreshDatabase;

    private int $urutanKode = 1;

    private function nota(string $nomor, string $status = 'Aktif', string $bayar = 'Lunas', float $total = 100000): void
    {
        $seq = $this->urutanKode++;
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail-' . $seq, 'keterangan' => '-']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Pelanggan', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        $id = DB::table('transaksi')->insertGetId([
            'nomor_nota' => $nomor, 'tipe_order' => 'Reguler', 'id_konsumen' => $kons,
            'id_user_kasir' => User::first()->id_user, 'tgl_order' => now(), 'total_bayar' => $total,
            'status_pembayaran' => $bayar, 'status_nota' => $status, 'status_order' => 'Diterima Kasir',
        ]);
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M' . $seq, 'tipe_mesin' => 'Cetak']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K' . $seq, 'kode_kategori' => 'K' . $seq]);
        $produk = DB::table('produk')->insertGetId(['nama_produk' => 'Produk', 'metode_hitung' => 'quantity', 'status' => 'aktif']);
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $id, 'id_produk' => $produk,
            'nama_file' => 'F', 'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 1,
            'subtotal' => $total, 'hpp_snapshot' => $total / 2, 'status_spk' => 'Antre',
        ]);
    }

    private function tanya(string $pesan, ?User $user = null)
    {
        $user = $user ?? User::first();
        return $this->actingAs($user)->postJson(route('premium.asisten.tanya'), ['pesan' => $pesan]);
    }

    public function test_berapa_order_hari_ini(): void
    {
        User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->nota('LAN-1');
        $r = $this->tanya('berapa order hari ini');
        $r->assertOk()->assertJsonStructure(['reply', 'intent']);
        $this->assertStringContainsString('1 nota', $r->json('reply'));
    }

    public function test_omzet_ditolak_tanpa_hak_harga(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Kasir Biasa', 'boleh_lihat_harga' => false]);
        $user = User::factory()->create(['id_role' => $role->id_role]);
        $r = $this->tanya('omzet hari ini', $user);
        $r->assertOk();
        $this->assertStringContainsString('harga', $r->json('reply'));
    }

    public function test_nota_lookup_menemukan_nota(): void
    {
        User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->nota('LAN-77', 'Aktif', 'Lunas', 175000);
        $r = $this->tanya('nota LAN-77');
        $r->assertOk();
        $this->assertStringContainsString('LAN-77', $r->json('reply'));
        $this->assertStringContainsString('175.000', $r->json('reply'));
    }

    public function test_bantuan_mencantumkan_perintah(): void
    {
        User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $r = $this->tanya('/bantuan');
        $r->assertOk();
        $this->assertStringContainsString('rekap bulan ini', $r->json('reply'));
    }

    public function test_rekap_bulan_ini_admin_memuat_angka(): void
    {
        User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->nota('LAN-9', 'Aktif', 'Lunas', 200000);
        $r = $this->tanya('rekap bulan ini');
        $r->assertOk();
        $this->assertStringContainsString('omzet', mb_strtolower($r->json('reply')));
    }

    public function test_pertanyaan_kosong_dibalas_halus(): void
    {
        User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $r = $this->tanya('   ');
        $r->assertStatus(422);
    }
}
