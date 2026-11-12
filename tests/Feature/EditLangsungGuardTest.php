<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hardening approval edit nota: endpoint /penjualan/{id}/edit-langsung
 * (self-approve) HANYA boleh berjalan saat approval edit nota nonaktif.
 * Sebelumnya flag itu hanya dicek di requestEditNota, jadi POST manual ke
 * URL ini bisa membatalkan nota + membalik stok/jurnal tanpa persetujuan
 * Administrator walau fitur approval sedang aktif.
 */
class EditLangsungGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private int $idTransaksi;

    protected function setUp(): void
    {
        parent::setUp();

        // Administrator agar lolos middleware izin route (guard flag yang
        // diuji, bukan RBAC). Kepemilikan nota tetap terpenuhi (dia pembuat).
        $this->kasir = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);

        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Umum', 'keterangan' => '-']);
        $kons = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Pelanggan Uji', 'telepon' => '08123', 'alamat' => '-', 'id_golongan' => $gol,
        ]);

        $this->idTransaksi = (int) DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'NOTA-GUARD-1',
            'id_konsumen' => $kons,
            'id_user_kasir' => $this->kasir->id_user,
            'total_bayar' => 100000,
            'status_pembayaran' => 'Belum Bayar',
            'status_nota' => 'Aktif',
            'status_order' => 'Diterima Kasir',
            'tgl_order' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_edit_langsung_ditolak_saat_approval_aktif(): void
    {
        DB::table((new AppSetting)->getTable())->update(['approval_edit_nota' => true]);

        $res = $this->actingAs($this->kasir)
            ->post('/penjualan/' . $this->idTransaksi . '/edit-langsung');

        $res->assertRedirect();
        $res->assertSessionHas('error', function (string $m) {
            return str_contains($m, 'Approval edit nota');
        });

        // Tidak ada request "Disetujui" yang lahir, nota tetap Aktif.
        $this->assertSame(0, DB::table('nota_edit_request')->where('id_transaksi', $this->idTransaksi)->count());
        $this->assertSame('Aktif', DB::table('transaksi')->where('id_transaksi', $this->idTransaksi)->value('status_nota'));
    }

    public function test_edit_langsung_tetap_jalan_saat_approval_nonaktif(): void
    {
        // Approval nonaktif (default): jalur langsung sah untuk pemilik nota.
        DB::table((new AppSetting)->getTable())->update(['approval_edit_nota' => false]);

        // Tanpa item di nota, eksekusi koreksi boleh kosong - yang diuji
        // di sini hanya guard flag: request berjalan (bukan ditolak flag).
        $res = $this->actingAs($this->kasir)
            ->post('/penjualan/' . $this->idTransaksi . '/edit-langsung');

        $res->assertRedirect();
        $this->assertTrue(
            DB::table('nota_edit_request')->where('id_transaksi', $this->idTransaksi)->exists(),
            'saat approval nonaktif, jalur langsung harus tetap bekerja'
        );
    }
}
