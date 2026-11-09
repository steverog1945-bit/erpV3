<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P0-5 (audit hardening): DashboardController::ringkasanCollecting() dulu
 * meng-get() SEMUA baris transaksi_detail (join ke transaksi, tanpa filter
 * tanggal/status apa pun) ke Collection PHP hanya untuk dihitung jadi 2
 * angka. Diganti agregat SQL murni - tes ini membuktikan angkanya tetap
 * identik dengan rumus lama untuk kombinasi nota: item tunggal selesai,
 * item tunggal belum, multi-item campuran (sebagian Batal dianggap "kelar"),
 * dan multi-item yang masih Antre/Cetak.
 */
class DashboardRingkasanCollectingTest extends TestCase
{
    use RefreshDatabase;

    private int $idKasir;

    private function buatNota(array $itemStatus): void
    {
        $idKonsumen = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'X', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => 1]);
        $idTransaksi = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'UJI-' . uniqid(), 'id_konsumen' => $idKonsumen, 'tgl_order' => now(),
            'total_bayar' => 0, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif',
            'id_user_kasir' => $this->idKasir,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($itemStatus as $status) {
            DB::table('transaksi_detail')->insert([
                'id_transaksi' => $idTransaksi, 'nama_file' => 'f', 'qty' => 1, 'subtotal' => 0,
                'p_ukuran' => 0, 'l_ukuran' => 0,
                'status_spk' => $status, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function ringkasanViaRefleksi(): array
    {
        $method = new \ReflectionMethod(DashboardController::class, 'ringkasanCollecting');
        $method->setAccessible(true);

        return $method->invoke(new DashboardController());
    }

    public function test_ringkasan_collecting_sesuai_rumus_lama_untuk_berbagai_kombinasi_nota(): void
    {
        DB::table('golongan_konsumen')->insert(['id_golongan' => 1, 'nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->idKasir = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Kasir'])->id_role])->id_user;

        $this->buatNota(['Selesai']);                       // nota 1: lengkap (1 item, selesai)
        $this->buatNota(['Selesai', 'Batal']);               // nota 2: lengkap (Batal ikut dianggap "kelar")
        $this->buatNota(['Antre']);                          // nota 3: belum lengkap
        $this->buatNota(['Selesai', 'Cetak']);               // nota 4: belum lengkap (campuran)
        $this->buatNota(['Batal', 'Batal', 'Batal']);        // nota 5: lengkap (semua Batal)

        [$notaLengkap, $notaBelumLengkap] = $this->ringkasanViaRefleksi();

        $this->assertSame(3, $notaLengkap, 'nota 1, 2, 5 harus terhitung lengkap');
        $this->assertSame(2, $notaBelumLengkap, 'nota 3, 4 harus terhitung belum lengkap');
    }

    public function test_ringkasan_collecting_nol_saat_tidak_ada_nota(): void
    {
        [$notaLengkap, $notaBelumLengkap] = $this->ringkasanViaRefleksi();

        $this->assertSame(0, $notaLengkap);
        $this->assertSame(0, $notaBelumLengkap);
    }
}
