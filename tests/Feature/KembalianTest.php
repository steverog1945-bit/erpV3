<?php

namespace Tests\Feature;

use App\Models\JerseyOrder;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Uang tunai yang lebih dari tagihan: yang dicatat (riwayat, kas, jurnal, Kas Kecil) sebesar tagihan;
 * uang yang diserahkan dan kembaliannya disimpan terpisah dan tercetak di nota.
 */
class KembalianTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($this->admin);
    }

    public function test_pelunasan_jersey_200_ribu_untuk_tagihan_128_640_mencatat_128_640_dan_kembalian_71_360(): void
    {
        $o = JerseyOrder::create(['order_number' => 'JO-KMB-1', 'order_date' => now()->toDateString(), 'customer_name' => 'Tim', 'additional_charge' => 128640]);
        $o->recalculateTotal();

        $this->post("/jersey/keuangan/{$o->id}/bayar", ['tanggal_bayar' => now()->toDateString(), 'jumlah' => 200000, 'metode_bayar' => 'Tunai'])
            ->assertSessionHas('success', fn ($p) => str_contains($p, 'Kembalian untuk pelanggan: Rp 71.360'));

        $bayar = DB::table('jersey_order_payments')->first();
        $this->assertEquals(128640, (float) $bayar->jumlah);
        $this->assertEquals(200000, (float) $bayar->uang_diterima);
        $this->assertEquals(71360, (float) $bayar->kembalian);
        $this->assertEquals(128640, (float) DB::table('kas_kecil')->where('sumber', 'jersey')->value('nominal'));
        $this->assertEquals(128640, (float) DB::table('jurnal_umum')->where('kode_akun', '1005')->sum('debet'));

        foreach (['thermal', 'normal'] as $format) {
            $nota = $this->get("/jersey/{$o->id}/print/{$format}")->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/Uang Diterima<\/td><td class="t">:<\/td><td class="v">Rp 200\.000/', $nota);
            $this->assertMatchesRegularExpression('/Kembali<\/td><td class="t">:<\/td><td class="v">Rp 71\.360/', $nota);
            $this->assertMatchesRegularExpression('/Sisa Tagihan<\/td><td class="t">:<\/td><td class="v">Rp 0/', $nota);
        }
    }

    public function test_kasir_uang_lebih_tersimpan_dan_nota_mencetak_kembalian(): void
    {
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        $id = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'INV-KMB-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $kons, 'id_user_kasir' => $this->admin->id_user,
            'tgl_order' => now(), 'total_bayar' => 80000, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir', 'status_collecting' => 'Belum Diambil',
        ]);
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $id, 'nama_file' => 'Id Card', 'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 10, 'status_urgent' => 0,
            'status_urgensi' => 'Normal', 'status_finishing' => 'Standar', 'subtotal' => 80000, 'potongan' => 0, 'hpp_snapshot' => 0,
            'harga_jual_snapshot' => 80000, 'status_spk' => 'Selesai', 'biaya_cetak' => 80000, 'biaya_desain' => 0,
        ]);
        AccountingService::postPenjualan('INV-KMB-1', 80000, 0);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()]);

        $this->post('/kasir/proses-bayar', ['id_transaksi' => $id, 'jumlah_bayar' => '100.000', 'metode_pembayaran' => 'Tunai', 'submit_id' => 'kmb-1'])
            ->assertSessionHas('success', fn ($p) => str_contains($p, 'Kembalian untuk pelanggan: Rp 20.000'));

        $r = DB::table('pembayaran_riwayat')->first();
        $this->assertEquals(80000, (float) $r->nominal);
        $this->assertEquals(100000, (float) $r->uang_diterima);
        $this->assertEquals(20000, (float) $r->kembalian);

        $nota = $this->get("/kasir/print-opsi/{$id}")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Kembali<\/td><td class="t">:<\/td><td class="v">20\.000/', $nota);
        $this->assertStringContainsString('Saldo deposit anda saat ini', $nota);
        $this->assertStringContainsString('[ 8.000 ]', $nota);
    }

    public function test_perintah_koreksi_membetulkan_kelebihan_bayar_lama(): void
    {
        $o = JerseyOrder::create(['order_number' => 'JO-KMB-3', 'order_date' => now()->toDateString(), 'customer_name' => 'Tim', 'additional_charge' => 128640]);
        $o->recalculateTotal();
        // Keadaan lama: pembayaran tercatat 200.000 (sebelum aturan kembalian).
        $p = $o->payments()->create(['tanggal_bayar' => now()->toDateString(), 'jumlah' => 200000, 'metode_bayar' => 'Tunai', 'created_by' => $this->admin->id_user]);
        \App\Services\JerseyAccountingService::catatPembayaran($o, $p);
        $o->recalculatePembayaran();

        Artisan::call('jersey:koreksi-pembukuan', ['--terapkan' => true]);

        $this->assertEquals(128640, (float) $p->fresh()->jumlah);
        $this->assertEquals(128640, (float) DB::table('kas_kecil')->where('id_referensi', $p->id)->value('nominal'));
        $this->assertEquals(0, (float) DB::table('jurnal_umum')->where('kode_akun', '1100')->selectRaw('SUM(debet) - SUM(kredit) s')->value('s'));
        $this->assertEquals((float) DB::table('jurnal_umum')->sum('debet'), (float) DB::table('jurnal_umum')->sum('kredit'));
        $this->assertSame(0, Artisan::call('audit:integritas'));
    }
}
