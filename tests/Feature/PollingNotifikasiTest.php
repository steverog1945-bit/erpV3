<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PollingNotifikasiTest extends TestCase
{
    use RefreshDatabase;

    private function loginAdmin(): User
    {
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        return User::factory()->create(['id_role' => $role->id_role]);
    }

    public function test_halaman_spk_render_dengan_komponen_polling(): void
    {
        $response = $this->actingAs($this->loginAdmin())->get('/spk');
        $response->assertOk();
        $response->assertSee(route('spk.cek-antrian-baru'), false);
    }

    public function test_endpoint_cek_antrian_baru_spk_mengembalikan_json(): void
    {
        $response = $this->actingAs($this->loginAdmin())->get(route('spk.cek-antrian-baru'));
        $response->assertOk();
        $response->assertJsonStructure(['total']);
    }

    public function test_halaman_produksi_render_dengan_komponen_polling(): void
    {
        $response = $this->actingAs($this->loginAdmin())->get('/produksi');
        $response->assertOk();
        $response->assertSee(route('produksi.cek-antrian-baru'), false);
    }

    public function test_endpoint_cek_antrian_baru_produksi_mengembalikan_json(): void
    {
        $response = $this->actingAs($this->loginAdmin())->get(route('produksi.cek-antrian-baru'));
        $response->assertOk();
        $response->assertJsonStructure(['total']);
    }

    public function test_halaman_collecting_render_dengan_komponen_polling(): void
    {
        $response = $this->actingAs($this->loginAdmin())->get('/collecting');
        $response->assertOk();
        $response->assertSee(route('collecting.cek-antrian-baru'), false);
    }

    public function test_endpoint_cek_antrian_baru_collecting_mengembalikan_json(): void
    {
        $response = $this->actingAs($this->loginAdmin())->get(route('collecting.cek-antrian-baru'));
        $response->assertOk();
        $response->assertJsonStructure(['total']);
    }

    public function test_notifikasi_spk_terhitung_benar_saat_ada_antrian_baru(): void
    {
        $admin = $this->loginAdmin();

        // Endpoint harus mengembalikan angka sesuai jumlah item status
        // Antre pada nota Aktif - dites lewat data nyata (bukan cuma
        // struktur JSON) untuk memastikan query-nya benar.
        $golongan = \Illuminate\Support\Facades\DB::table('golongan_konsumen')->insertGetId([
            'nama_golongan' => 'Retail',
        ]);
        $konsumen = \Illuminate\Support\Facades\DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Test', 'telepon' => '08123', 'alamat' => '-', 'id_golongan' => $golongan,
        ]);
        $idTransaksi = \Illuminate\Support\Facades\DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'TEST-001', 'tipe_order' => 'Reguler', 'id_konsumen' => $konsumen,
            'id_user_kasir' => $admin->id_user, 'tgl_order' => now(), 'total_bayar' => 0,
            'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir',
        ]);
        \Illuminate\Support\Facades\DB::table('transaksi_detail')->insert([
            'id_transaksi' => $idTransaksi, 'nama_file' => 'test.pdf', 'p_ukuran' => 1, 'l_ukuran' => 1,
            'qty' => 1, 'status_urgent' => 0, 'status_urgensi' => 'Normal', 'status_finishing' => 'Standar',
            'subtotal' => 0, 'potongan' => 0, 'hpp_snapshot' => 0, 'harga_jual_snapshot' => 0,
            'status_spk' => 'Antre', 'biaya_cetak' => 0, 'biaya_desain' => 0,
        ]);

        $response = $this->actingAs($admin)->get(route('spk.cek-antrian-baru'));
        $response->assertJson(['total' => 1]);
    }
}
