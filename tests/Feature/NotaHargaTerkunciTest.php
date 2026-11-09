<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotaHargaTerkunciTest extends TestCase
{
    use RefreshDatabase;

    private function siapkan(string $namaRole, bool $bolehHarga): array
    {
        $role = Role::factory()->create(['nama_role' => $namaRole]);
        DB::table('roles')->where('id_role', $role->id_role)->update(['boleh_lihat_harga' => $bolehHarga]);
        foreach ([3, 41] as $id) {
            DB::table('menus')->insertOrIgnore(['id_menu' => $id, 'nama_menu' => "Menu {$id}", 'url_link' => "m{$id}", 'parent_id' => 0]);
            DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => $id]);
        }
        $user = User::factory()->create(['id_role' => $role->id_role]);
        $idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $idK = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Uji', 'telepon' => '0811', 'alamat' => '-', 'id_golongan' => $idGol]);
        $idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'Uji Produk', 'metode_hitung' => 'quantity', 'status' => 'Aktif']);
        DB::table('setting_harga')->insert(['id_produk' => $idProduk, 'id_golongan' => $idGol, 'range_min' => 1, 'harga_jual' => 5000]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $user->id_user ?? $user->getKey(), 'saldo_awal' => 0, 'status_lock' => 'open']);

        return [$user, $idK, $idGol, $idProduk];
    }

    private function payload($idK, $idGol, $idProduk): array
    {
        return [
            'id_konsumen' => $idK, 'id_golongan' => $idGol,
            'items' => [[
                'id_produk' => $idProduk, 'nama_file' => 'a.pdf', 'qty_input' => 3, 'p' => 0, 'l' => 0,
                'biaya_cetak' => 1, 'biaya_desain' => 999999, 'biaya_finishing' => 888888,
            ]],
        ];
    }

    public function test_role_terkunci_harga_dari_klien_diabaikan_server_hitung_ulang(): void
    {
        [$user, $idK, $idGol, $idProduk] = $this->siapkan('Operator Desain', false);
        $this->actingAs($user)->postJson('/penjualan/store', $this->payload($idK, $idGol, $idProduk))->assertOk();

        $this->assertEquals(15000, (float) DB::table('transaksi')->value('total_bayar')); // 3 x 5000, tanpa desain/finishing kiriman klien
    }

    public function test_role_berhak_masih_bisa_menimpa_harga(): void
    {
        [$user, $idK, $idGol, $idProduk] = $this->siapkan('Kasir', true);
        $idFin = DB::table('finishing')->insertGetId(['nama_finishing' => 'Finishing Uji', 'harga_default' => 100, 'metode_hitung' => 'quantity']);
        $payload = $this->payload($idK, $idGol, $idProduk);
        $payload['items'][0]['finishing'] = [['id_finishing' => $idFin, 'status_finishing' => 'Custom', 'biaya' => 888888]];
        $this->actingAs($user)->postJson('/penjualan/store', $payload)->assertOk();

        $this->assertEquals(1 + 999999 + 888888, (float) DB::table('transaksi')->value('total_bayar'));
    }

    public function test_halaman_input_nota_membuat_kolom_harga_readonly_untuk_role_terkunci(): void
    {
        [$user] = $this->siapkan('Operator Desain', false);
        $html = $this->actingAs($user)->get('/penjualan/kasir')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="input_cetak"\s+readonly/', $html);
    }

    public function test_jalan_pintas_hanya_bertombol_bila_role_berhak_membuka_tujuan(): void
    {
        [$user] = $this->siapkan('Operator Desain', false); // punya menu 3 & 41, tidak punya 16 (kasir)
        $this->actingAs($user);
        \App\Support\JalurLanjut::set('uji', 'kasir.index', 'Buka');
        $this->assertNull(session('lanjut')['url']);

        \App\Support\JalurLanjut::set('uji', 'penjualan.create', 'Buka');
        $this->assertNotNull(session('lanjut')['url']);
    }

    public function test_detail_nota_menyembunyikan_harga_untuk_role_terkunci(): void
    {
        [$user, $idK, $idGol, $idProduk] = $this->siapkan('Operator Desain', false);
        $this->actingAs($user)->postJson('/penjualan/store', $this->payload($idK, $idGol, $idProduk))->assertOk();
        $id = DB::table('transaksi')->value('id_transaksi');

        $this->actingAs($user)->get("/penjualan/{$id}")->assertOk()->assertDontSee('Biaya Cetak')->assertDontSee('15.000');
    }
}
