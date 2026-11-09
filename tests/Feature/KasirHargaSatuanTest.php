<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KasirHargaSatuanTest extends TestCase
{
    use RefreshDatabase;

    public function test_kolom_harga_form_pembayaran_menampilkan_biaya_cetak_per_satuan_bukan_nol(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $g = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $k = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $g]);
        $p = DB::table('produk')->insertGetId(['nama_produk' => 'Stiker Uji', 'metode_hitung' => 'quantity', 'status' => 'Aktif']);
        $t = DB::table('transaksi')->insertGetId(['nomor_nota' => 'N-KSR-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $k, 'id_user_kasir' => $admin->id_user, 'tgl_order' => now(), 'total_bayar' => 130000, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir']);
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $t, 'id_produk' => $p, 'nama_file' => 'a', 'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 4,
            'biaya_cetak' => 100000, 'biaya_desain' => 10000, 'biaya_finishing' => 20000, 'subtotal' => 130000, 'status_spk' => 'Antre',
        ]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open']);

        // Biaya cetak 100.000 / 4 pcs = Rp 25.000 per satuan
        $this->actingAs($admin)->get("/kasir/bayar-form/{$t}")->assertOk()->assertSee('Rp 25.000');
    }

    public function test_simpan_tanpa_uang_tidak_menolkan_total_nota(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $g = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $k = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $g]);
        $p = DB::table('produk')->insertGetId(['nama_produk' => 'Stiker Uji', 'metode_hitung' => 'quantity', 'status' => 'Aktif']);
        $t = DB::table('transaksi')->insertGetId(['nomor_nota' => 'N-KSR-2', 'tipe_order' => 'Reguler', 'id_konsumen' => $k, 'id_user_kasir' => $admin->id_user, 'tgl_order' => now(), 'total_bayar' => 130000, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir']);
        $d = DB::table('transaksi_detail')->insertGetId([
            'id_transaksi' => $t, 'id_produk' => $p, 'nama_file' => 'a', 'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 4,
            'biaya_cetak' => 100000, 'biaya_desain' => 10000, 'biaya_finishing' => 20000, 'subtotal' => 130000, 'status_spk' => 'Antre',
        ]);

        // Persis isian form Input Pembayaran saat tombol "Simpan" (uang 0, diskon 0).
        $this->actingAs($admin)->post('/kasir/proses-bayar', [
            'id_transaksi' => $t, 'tipe_bayar' => 'lunas', 'metode_pembayaran' => 'Tunai', 'jumlah_bayar' => '0', 'diskon' => '0', 'total_akhir' => '130000',
            'items' => [$d => ['harga' => '0', 'biaya_cetak' => '100000', 'biaya_desain' => '10000', 'biaya_finishing' => '20000', 'potongan' => '0']],
        ])->assertRedirect();

        $this->assertEquals(130000, (float) DB::table('transaksi')->where('id_transaksi', $t)->value('total_bayar'));
        $this->assertEquals(130000, (float) DB::table('transaksi_detail')->where('id_detail', $d)->value('subtotal'));
    }

    public function test_diskon_sama_dengan_total_nota_ditolak_agar_total_tidak_menjadi_nol(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $g = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $k = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $g]);
        $p = DB::table('produk')->insertGetId(['nama_produk' => 'Stiker Uji', 'metode_hitung' => 'quantity', 'status' => 'Aktif']);
        $t = DB::table('transaksi')->insertGetId(['nomor_nota' => 'N-KSR-3', 'tipe_order' => 'Reguler', 'id_konsumen' => $k, 'id_user_kasir' => $admin->id_user, 'tgl_order' => now(), 'total_bayar' => 130000, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir']);
        $d = DB::table('transaksi_detail')->insertGetId(['id_transaksi' => $t, 'id_produk' => $p, 'nama_file' => 'a', 'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 4, 'biaya_cetak' => 130000, 'subtotal' => 130000, 'status_spk' => 'Antre']);

        $this->actingAs($admin)->post('/kasir/proses-bayar', [
            'id_transaksi' => $t, 'tipe_bayar' => 'lunas', 'metode_pembayaran' => 'Tunai', 'jumlah_bayar' => '0', 'diskon' => '130000',
            'items' => [$d => ['biaya_cetak' => '130000', 'biaya_desain' => '0', 'biaya_finishing' => '0', 'potongan' => '0']],
        ]);

        $this->assertEquals(130000, (float) DB::table('transaksi')->where('id_transaksi', $t)->value('total_bayar'));
    }
}
