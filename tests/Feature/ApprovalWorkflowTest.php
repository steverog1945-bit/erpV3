<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        return User::factory()->create(['id_role' => $role->id_role]);
    }

    private function buatNotaAktif(User $kasir): array
    {
        $golongan = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $konsumen = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Test', 'telepon' => '08123', 'alamat' => '-', 'id_golongan' => $golongan,
        ]);
        $idTransaksi = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'INV-TEST-001', 'tipe_order' => 'Reguler', 'id_konsumen' => $konsumen,
            'id_user_kasir' => $kasir->id_user, 'tgl_order' => now(), 'total_bayar' => 100000,
            'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_collecting' => 'Belum Diambil',
        ]);
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $idTransaksi, 'nama_file' => 'test.pdf', 'p_ukuran' => 1, 'l_ukuran' => 1,
            'qty' => 1, 'status_urgent' => 0, 'status_urgensi' => 'Normal', 'status_finishing' => 'Standar',
            'subtotal' => 100000, 'potongan' => 0, 'hpp_snapshot' => 0, 'harga_jual_snapshot' => 100000,
            'status_spk' => 'Antre', 'biaya_cetak' => 100000, 'biaya_desain' => 0,
        ]);

        return [$idTransaksi];
    }

    public function test_ajukan_pembatalan_nota_tidak_langsung_membatalkan(): void
    {
        $admin = $this->admin();
        [$idTransaksi] = $this->buatNotaAktif($admin);

        $response = $this->actingAs($admin)->patch("/penjualan/{$idTransaksi}/batalkan-nota", [
            'alasan' => 'Salah input konsumen',
        ]);

        $response->assertRedirect();

        // Nota HARUS masih Aktif - baru jadi pengajuan, belum dieksekusi.
        $this->assertDatabaseHas('transaksi', ['id_transaksi' => $idTransaksi, 'status_nota' => 'Aktif']);
        $this->assertDatabaseHas('approval_requests', [
            'tipe_aksi' => 'batalkan_nota',
            'referensi_type' => 'transaksi',
            'referensi_id' => $idTransaksi,
            'status' => 'Menunggu',
        ]);
    }

    public function test_pengajuan_dobel_untuk_nota_yang_sama_ditolak(): void
    {
        $admin = $this->admin();
        [$idTransaksi] = $this->buatNotaAktif($admin);

        $this->actingAs($admin)->patch("/penjualan/{$idTransaksi}/batalkan-nota", ['alasan' => 'Alasan 1']);
        $this->actingAs($admin)->patch("/penjualan/{$idTransaksi}/batalkan-nota", ['alasan' => 'Alasan 2']);

        $this->assertEquals(1, ApprovalRequest::where('referensi_id', $idTransaksi)->where('status', 'Menunggu')->count());
    }

    public function test_setujui_pengajuan_benar_benar_membatalkan_nota(): void
    {
        $admin = $this->admin();
        [$idTransaksi] = $this->buatNotaAktif($admin);

        $this->actingAs($admin)->patch("/penjualan/{$idTransaksi}/batalkan-nota", ['alasan' => 'Salah input']);
        $req = ApprovalRequest::where('referensi_id', $idTransaksi)->first();

        $response = $this->actingAs($admin)->patch("/approval/{$req->id}/approve", ['catatan' => 'OK, disetujui']);
        $response->assertRedirect();

        $this->assertDatabaseHas('transaksi', ['id_transaksi' => $idTransaksi, 'status_nota' => 'Dibatalkan']);
        $this->assertDatabaseHas('transaksi_detail', ['id_transaksi' => $idTransaksi, 'status_spk' => 'Batal']);
        $this->assertDatabaseHas('approval_requests', ['id' => $req->id, 'status' => 'Disetujui']);
    }

    public function test_tolak_pengajuan_nota_tetap_aktif(): void
    {
        $admin = $this->admin();
        [$idTransaksi] = $this->buatNotaAktif($admin);

        $this->actingAs($admin)->patch("/penjualan/{$idTransaksi}/batalkan-nota", ['alasan' => 'Salah input']);
        $req = ApprovalRequest::where('referensi_id', $idTransaksi)->first();

        $response = $this->actingAs($admin)->patch("/approval/{$req->id}/reject", ['catatan' => 'Tidak valid']);
        $response->assertRedirect();

        $this->assertDatabaseHas('transaksi', ['id_transaksi' => $idTransaksi, 'status_nota' => 'Aktif']);
        $this->assertDatabaseHas('approval_requests', ['id' => $req->id, 'status' => 'Ditolak']);
    }

    public function test_pengajuan_yang_sudah_diputuskan_tidak_bisa_diputuskan_ulang(): void
    {
        $admin = $this->admin();
        [$idTransaksi] = $this->buatNotaAktif($admin);

        $this->actingAs($admin)->patch("/penjualan/{$idTransaksi}/batalkan-nota", ['alasan' => 'Salah input']);
        $req = ApprovalRequest::where('referensi_id', $idTransaksi)->first();

        $this->actingAs($admin)->patch("/approval/{$req->id}/approve", ['catatan' => 'OK']);

        // Coba setujui LAGI pengajuan yang sama - harus gagal aman, bukan
        // eksekusi dobel (mis. StokReversalService kembali dua kali).
        $response = $this->actingAs($admin)->patch("/approval/{$req->id}/approve", ['catatan' => 'OK lagi']);
        $response->assertSessionHas('error');
    }

    public function test_approvalservice_lockforupdate_mencegah_keputusan_ganda(): void
    {
        $admin = $this->admin();
        $req = ApprovalRequest::create([
            'tipe_aksi' => 'test_aksi', 'requested_by' => $admin->id_user, 'status' => 'Menunggu',
            'ringkasan' => 'Test',
        ]);

        $jumlahEksekusi = 0;
        ApprovalService::setujui($req, function () use (&$jumlahEksekusi) {
            $jumlahEksekusi++;
        });

        $this->expectException(\RuntimeException::class);
        ApprovalService::setujui($req, function () use (&$jumlahEksekusi) {
            $jumlahEksekusi++;
        });
    }

    public function test_bukan_administrator_tidak_bisa_akses_halaman_approval(): void
    {
        $roleKasir = Role::factory()->create(['nama_role' => 'Kasir']);
        $kasir = User::factory()->create(['id_role' => $roleKasir->id_role]);

        // Non-Administrator sama sekali tidak ada di role_permissions untuk
        // menu 'approval' (lihat migration add_approval_menu) - CheckMenuAccess
        // fail-closed, harus 403.
        $response = $this->actingAs($kasir)->get('/approval');
        $response->assertForbidden();
    }

    public function test_administrator_bisa_akses_halaman_approval(): void
    {
        $response = $this->actingAs($this->admin())->get('/approval');
        $response->assertOk();
        $response->assertSee('Alur Persetujuan');
    }
}
