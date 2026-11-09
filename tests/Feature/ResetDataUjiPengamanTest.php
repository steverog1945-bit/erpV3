<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** data:reset-uji menghapus seluruh transaksi: di lingkungan production ditolak, bahkan dengan --force. */
class ResetDataUjiPengamanTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_data_uji_ditolak_di_production_walau_dengan_force(): void
    {
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'A', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        DB::table('transaksi')->insert(['nomor_nota' => 'INV-ASLI', 'tipe_order' => 'Reguler', 'id_konsumen' => $kons, 'id_user_kasir' => \App\Models\User::factory()->create()->id_user, 'tgl_order' => now(), 'total_bayar' => 1000,
            'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif']);

        $this->app['env'] = 'production';
        $this->artisan('data:reset-uji', ['--force' => true])->assertFailed();

        $this->assertSame(1, DB::table('transaksi')->count(), 'data transaksi produksi terhapus');
    }
}
