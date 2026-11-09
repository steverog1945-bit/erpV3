<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DivisiOtomatisTest extends TestCase
{
    use RefreshDatabase;

    public function test_mesin_baru_langsung_jadi_divisi_di_spk_dan_produksi_tanpa_order(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        DB::table('mesin')->insert(['nama_mesin' => 'MESIN BARU XYZ', 'tipe_mesin' => 'Uji']);
        // Buat transaksi dengan status_order = Diterima Kasir agar SPK/divisi filter bekerja
        $k = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Pelanggan Uji', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-'])]);
        $t = DB::table('transaksi')->insertGetId(['nomor_nota' => 'N-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $k, 'id_user_kasir' => $admin->id_user, 'tgl_order' => now(), 'total_bayar' => 1, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir']);
        $p = DB::table('produk')->insertGetId(['nama_produk' => 'P-XYZ', 'metode_hitung' => 'quantity', 'status' => 'Aktif', 'id_mesin' => DB::table('mesin')->where('nama_mesin', 'MESIN BARU XYZ')->value('id_mesin')]);
        DB::table('transaksi_detail')->insert(['id_transaksi' => $t, 'id_produk' => $p, 'nama_file' => 'FILE-XYZ', 'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 1, 'subtotal' => 1, 'status_spk' => 'Cetak']);

        $this->actingAs($admin)->get('/spk')->assertOk()->assertSee('MESIN BARU XYZ');
        $this->actingAs($admin)->get('/produksi')->assertOk()->assertSee('MESIN BARU XYZ');
    }

    public function test_filter_divisi_produksi_membatasi_item_sesuai_mesin(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $m1 = DB::table('mesin')->insertGetId(['nama_mesin' => 'M-SATU', 'tipe_mesin' => 'x']);
        $m2 = DB::table('mesin')->insertGetId(['nama_mesin' => 'M-DUA', 'tipe_mesin' => 'x']);
        $g = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $k = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Pelanggan Uji', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $g]);
        $t = DB::table('transaksi')->insertGetId(['nomor_nota' => 'N-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $k, 'id_user_kasir' => $admin->id_user, 'tgl_order' => now(), 'total_bayar' => 1, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir']);
        foreach ([[$m1, 'FILE-SATU'], [$m2, 'FILE-DUA']] as [$m, $nama]) {
            $p = DB::table('produk')->insertGetId(['nama_produk' => "P{$m}", 'metode_hitung' => 'quantity', 'status' => 'Aktif', 'id_mesin' => $m]);
            DB::table('transaksi_detail')->insert(['id_transaksi' => $t, 'id_produk' => $p, 'nama_file' => $nama, 'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 1, 'subtotal' => 1, 'status_spk' => 'Cetak']);
        }

        $this->actingAs($admin)->get("/produksi?divisi={$m1}")->assertOk()->assertSee('FILE-SATU')->assertDontSee('FILE-DUA');
    }

    public function test_operator_di_produksi_hanya_melihat_divisinya_dan_kas_open_tidak_muncul_untuknya(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $mA = DB::table('mesin')->insertGetId(['nama_mesin' => 'DIV-A', 'tipe_mesin' => 'x']);
        $mB = DB::table('mesin')->insertGetId(['nama_mesin' => 'DIV-B', 'tipe_mesin' => 'x']);
        $g = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $k = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'P', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $g]);
        $t = DB::table('transaksi')->insertGetId(['nomor_nota' => 'N-9', 'tipe_order' => 'Reguler', 'id_konsumen' => $k, 'id_user_kasir' => $admin->id_user, 'tgl_order' => now(), 'total_bayar' => 1, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir']);
        foreach ([[$mA, 'FILE-A'], [$mB, 'FILE-B']] as [$m, $n]) {
            $p = DB::table('produk')->insertGetId(['nama_produk' => "P{$m}", 'metode_hitung' => 'quantity', 'status' => 'Aktif', 'id_mesin' => $m]);
            DB::table('transaksi_detail')->insert(['id_transaksi' => $t, 'id_produk' => $p, 'nama_file' => $n, 'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 1, 'subtotal' => 1, 'status_spk' => 'Cetak']);
        }
        // Kas dibuka admin lebih dari 24 jam lalu.
        DB::table('kas_harian')->insert(['tgl_kas' => now()->subDays(3)->toDateString(), 'id_user' => $admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)]);

        $kar = DB::table('karyawan')->insertGetId(['nama_karyawan' => 'Op', 'bagian' => 'Produksi', 'telepon' => '1', 'status_aktif' => 1]);
        DB::table('karyawan_mesin')->insert(['id_karyawan' => $kar, 'id_mesin' => $mA]);
        $role = Role::factory()->create(['nama_role' => 'Operator Produksi']);
        $op = User::factory()->create(['id_role' => $role->id_role, 'id_karyawan' => $kar]);
        DB::table('menus')->insertOrIgnore(['id_menu' => 23, 'nama_menu' => 'Produksi', 'url_link' => 'produksi', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => 23]);

        $this->actingAs($op)->get('/produksi')->assertOk()
            ->assertSee('FILE-A')->assertDontSee('FILE-B')->assertDontSee('DIV-B')->assertSee('Divisi Anda')
            ->assertDontSee('Kasir Open')->assertDontSee('Belum di-closing');

        $this->actingAs($admin)->get('/produksi')->assertOk()->assertSee('Belum di-closing');
    }

    public function test_mesin_pendukung_tidak_tampil_sebagai_divisi(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        DB::table('mesin')->insert(['nama_mesin' => 'MESIN-PRODUKSI-OK', 'tipe_mesin' => 'x', 'tampil_divisi' => 1]);
        DB::table('mesin')->insert(['nama_mesin' => 'ROLAND-PENDUKUNG', 'tipe_mesin' => 'Cutting Plotter', 'tampil_divisi' => 0]);

        $this->actingAs($admin)->get('/spk')->assertOk()->assertSee('MESIN-PRODUKSI-OK')->assertDontSee('ROLAND-PENDUKUNG');
        $this->actingAs($admin)->get('/produksi')->assertOk()->assertDontSee('ROLAND-PENDUKUNG');
    }
}