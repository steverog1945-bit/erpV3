<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P2-3 (audit hardening): AkunController mengelola Chart of Accounts
 * (bag_akun) yang dirujuk setiap baris jurnal_umum di seluruh aplikasi -
 * sebelum tes ini, TIDAK ADA satu tes pun yang mengunci dua aturan
 * bisnis pentingnya: kode_akun tidak boleh diubah setelah dipakai jurnal
 * (supaya baris jurnal lama tidak jadi yatim), dan akun tidak bisa
 * dihapus kalau sudah punya riwayat jurnal.
 */
class AkunControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // Administrator selalu lolos CheckMenuAccess tanpa perlu row role_permissions (lihat middleware).
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $role->id_role]);
    }

    private function buatAkun(string $kode = 'AK-1', string $nama = 'Kas Uji'): int
    {
        return DB::table('bag_akun')->insertGetId([
            'kode_akun' => $kode, 'nama_akun' => $nama, 'tipe_akun' => 'Aset', 'saldo_normal' => 'Debet',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_akun_baru_ditolak_bila_kode_duplikat(): void
    {
        $this->buatAkun('AK-1');

        $this->actingAs($this->admin)->post('/akun', [
            'kode_akun' => 'AK-1', 'nama_akun' => 'Lain', 'tipe_akun' => 'Aset', 'saldo_normal' => 'Debet',
        ])->assertSessionHasErrors('kode_akun');

        $this->assertSame(1, DB::table('bag_akun')->where('kode_akun', 'AK-1')->count());
    }

    public function test_akun_baru_ditolak_bila_tipe_atau_saldo_normal_di_luar_daftar_sah(): void
    {
        $this->actingAs($this->admin)->post('/akun', [
            'kode_akun' => 'AK-2', 'nama_akun' => 'X', 'tipe_akun' => 'BukanTipe', 'saldo_normal' => 'Debet',
        ])->assertSessionHasErrors('tipe_akun');

        $this->actingAs($this->admin)->post('/akun', [
            'kode_akun' => 'AK-3', 'nama_akun' => 'X', 'tipe_akun' => 'Aset', 'saldo_normal' => 'BukanSaldo',
        ])->assertSessionHasErrors('saldo_normal');
    }

    public function test_kode_akun_tidak_bisa_diubah_setelah_dipakai_jurnal_tapi_nama_masih_bisa(): void
    {
        $id = $this->buatAkun('AK-1', 'Kas Lama');
        DB::table('jurnal_umum')->insert(['kode_akun' => 'AK-1', 'keterangan' => 'x', 'debet' => 1000, 'kredit' => 0, 'tgl_jurnal' => now(), 'no_referensi' => 'UJI', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->admin)->put("/akun/{$id}", [
            'kode_akun' => 'AK-1-BARU', 'nama_akun' => 'Kas Lama', 'tipe_akun' => 'Aset', 'saldo_normal' => 'Debet',
        ])->assertSessionHas('error');
        $this->assertSame('AK-1', DB::table('bag_akun')->where('id_akun', $id)->value('kode_akun'), 'kode_akun TIDAK BOLEH berubah karena sudah dipakai jurnal');

        // Nama/tipe boleh diubah selama kode_akun tetap sama.
        $this->actingAs($this->admin)->put("/akun/{$id}", [
            'kode_akun' => 'AK-1', 'nama_akun' => 'Kas Baru', 'tipe_akun' => 'Aset', 'saldo_normal' => 'Debet',
        ])->assertSessionHas('success');
        $this->assertSame('Kas Baru', DB::table('bag_akun')->where('id_akun', $id)->value('nama_akun'));
    }

    public function test_kode_akun_masih_bisa_diubah_selama_belum_pernah_dipakai_jurnal(): void
    {
        $id = $this->buatAkun('AK-1');

        $this->actingAs($this->admin)->put("/akun/{$id}", [
            'kode_akun' => 'AK-1-BARU', 'nama_akun' => 'Kas Uji', 'tipe_akun' => 'Aset', 'saldo_normal' => 'Debet',
        ])->assertSessionHas('success');
        $this->assertSame('AK-1-BARU', DB::table('bag_akun')->where('id_akun', $id)->value('kode_akun'));
    }

    public function test_akun_tidak_bisa_dihapus_bila_sudah_punya_riwayat_jurnal(): void
    {
        $id = $this->buatAkun('AK-1');
        DB::table('jurnal_umum')->insert(['kode_akun' => 'AK-1', 'keterangan' => 'x', 'debet' => 1000, 'kredit' => 0, 'tgl_jurnal' => now(), 'no_referensi' => 'UJI', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->admin)->delete("/akun/{$id}")->assertSessionHas('error');
        $this->assertDatabaseHas('bag_akun', ['id_akun' => $id]);
    }

    public function test_akun_tanpa_riwayat_jurnal_bisa_dihapus(): void
    {
        $id = $this->buatAkun('AK-1');

        $this->actingAs($this->admin)->delete("/akun/{$id}")->assertSessionHas('success');
        $this->assertDatabaseMissing('bag_akun', ['id_akun' => $id]);
    }

    public function test_toggle_active_membalik_status(): void
    {
        $id = $this->buatAkun('AK-1');

        $this->actingAs($this->admin)->patch("/akun/{$id}/toggle-active")->assertSessionHas('success');
        $this->assertSame(0, (int) DB::table('bag_akun')->where('id_akun', $id)->value('is_active'));

        $this->actingAs($this->admin)->patch("/akun/{$id}/toggle-active");
        $this->assertSame(1, (int) DB::table('bag_akun')->where('id_akun', $id)->value('is_active'));
    }
}
