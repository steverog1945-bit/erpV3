<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinishingGandaTest extends TestCase
{
    use RefreshDatabase;

    private int $idProduk;
    private int $idKonsumen;
    private int $idGol;
    private int $idLaminasi;
    private int $idKisscut;

    private function siapkan(string $namaRole, bool $bolehHarga): User
    {
        $role = Role::factory()->create(['nama_role' => $namaRole]);
        DB::table('roles')->where('id_role', $role->id_role)->update(['boleh_lihat_harga' => $bolehHarga]);
        foreach ([3, 41] as $id) {
            DB::table('menus')->insertOrIgnore(['id_menu' => $id, 'nama_menu' => "M{$id}", 'url_link' => "m{$id}", 'parent_id' => 0]);
            DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => $id]);
        }
        $user = User::factory()->create(['id_role' => $role->id_role]);

        $this->idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $this->idGol]);
        $this->idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'Stiker Uji', 'metode_hitung' => 'ukuran', 'status' => 'Aktif']);
        DB::table('setting_harga')->insert(['id_produk' => $this->idProduk, 'id_golongan' => $this->idGol, 'range_min' => 0.01, 'harga_jual' => 50000]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $user->id_user, 'saldo_awal' => 0, 'status_lock' => 'open']);

        $this->idLaminasi = DB::table('finishing')->insertGetId(['nama_finishing' => 'Laminasi Dingin Uji', 'harga_default' => 15000, 'metode_hitung' => 'luas']);
        $this->idKisscut = DB::table('finishing')->insertGetId(['nama_finishing' => 'Kisscut Uji', 'harga_default' => 20000, 'metode_hitung' => 'luas']);

        return $user;
    }

    private function payload(array $finishing): array
    {
        // 100 x 100 cm x 2 = 2 m2
        return ['id_konsumen' => $this->idKonsumen, 'id_golongan' => $this->idGol, 'items' => [[
            'id_produk' => $this->idProduk, 'nama_file' => 'a.jpg', 'p' => 100, 'l' => 100, 'qty_input' => 2, 'biaya_cetak' => 100000, 'finishing' => $finishing,
        ]]];
    }

    public function test_dua_finishing_standar_dihitung_server_dan_tersimpan_terpisah(): void
    {
        $admin = $this->siapkan('Administrator', true);
        $this->actingAs($admin)->postJson('/penjualan/store', $this->payload([
            ['id_finishing' => $this->idLaminasi, 'status_finishing' => 'Standar', 'biaya' => 1, 'catatan' => 'Doff'],
            ['id_finishing' => $this->idKisscut, 'status_finishing' => 'Standar', 'biaya' => 1],
        ]))->assertOk();

        // 2 m2 x 15.000 = 30.000 ; 2 m2 x 20.000 = 40.000 (biaya kiriman klien untuk Standar diabaikan)
        $rows = DB::table('transaksi_detail_finishing')->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertEquals([30000.0, 40000.0], $rows->pluck('biaya')->map(fn ($v) => (float) $v)->all());
        $this->assertEquals('Doff', $rows[0]->catatan);

        $d = DB::table('transaksi_detail')->first();
        $this->assertEquals(70000, (float) $d->biaya_finishing);
        $this->assertEquals($this->idLaminasi, $d->id_finishing);
        $this->assertEquals(100000 + 70000, (float) $d->subtotal); // cetak 2 x 50.000 + finishing 70.000
    }

    public function test_finishing_custom_boleh_manual_untuk_role_berhak_tapi_tidak_untuk_role_terkunci(): void
    {
        $admin = $this->siapkan('Administrator', true);
        $this->actingAs($admin)->postJson('/penjualan/store', $this->payload([
            ['id_finishing' => $this->idLaminasi, 'status_finishing' => 'Custom', 'biaya' => 12345],
        ]))->assertOk();
        $this->assertEquals(12345, (float) DB::table('transaksi_detail_finishing')->value('biaya'));
        $this->assertEquals('Custom', DB::table('transaksi_detail')->value('status_finishing'));

        // Role tanpa hak harga: Custom pun dihitung ulang server (30.000), kiriman klien diabaikan.
        DB::table('transaksi_detail_finishing')->delete();
        $od = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Operator Desain'])->id_role]);
        DB::table('roles')->where('nama_role', 'Operator Desain')->update(['boleh_lihat_harga' => 0]);
        foreach ([3, 41] as $id) {
            DB::table('role_permissions')->insert(['id_role' => $od->id_role, 'id_menu' => $id]);
        }
        DB::table('kas_harian')->update(['id_user' => $od->id_user]);
        $this->actingAs($od)->postJson('/penjualan/store', $this->payload([
            ['id_finishing' => $this->idLaminasi, 'status_finishing' => 'Custom', 'biaya' => 1],
        ]))->assertOk();
        $this->assertEquals(30000, (float) DB::table('transaksi_detail_finishing')->value('biaya'));
    }

    public function test_detail_nota_menampilkan_semua_finishing(): void
    {
        $admin = $this->siapkan('Administrator', true);
        $this->actingAs($admin)->postJson('/penjualan/store', $this->payload([
            ['id_finishing' => $this->idLaminasi, 'status_finishing' => 'Standar'],
            ['id_finishing' => $this->idKisscut, 'status_finishing' => 'Standar'],
        ]))->assertOk();
        $id = DB::table('transaksi')->value('id_transaksi');

        $this->actingAs($admin)->get("/penjualan/{$id}")->assertOk()->assertSee('Laminasi Dingin Uji')->assertSee('Kisscut Uji');
    }

    public function test_bentuk_lama_finishing_tunggal_tetap_diterima(): void
    {
        $admin = $this->siapkan('Administrator', true);
        $p = $this->payload([]);
        $p['items'][0]['id_finishing'] = $this->idKisscut;
        $p['items'][0]['status_finishing'] = 'Standar';
        unset($p['items'][0]['finishing']);
        $this->actingAs($admin)->postJson('/penjualan/store', $p)->assertOk();
        $this->assertEquals(1, DB::table('transaksi_detail_finishing')->count());
    }
}
