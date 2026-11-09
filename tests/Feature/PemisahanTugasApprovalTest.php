<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\JerseyOrder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit Fase 4 (alur ERP): pemisahan tugas pada persetujuan. Pengaju tidak
 * menyetujui pengajuannya sendiri bila ada Administrator lain; bila dia satu-
 * satunya Administrator, boleh tetapi keputusan diberi catatan.
 */
class PemisahanTugasApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Role $roleAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roleAdmin = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $this->roleAdmin->id_role]);
    }

    /** Pengajuan void pembayaran Jersey oleh $pengaju. */
    private function ajukanVoid(User $pengaju): ApprovalRequest
    {
        $o = JerseyOrder::create(['order_number' => 'JO-' . uniqid(), 'order_date' => now()->toDateString(), 'customer_name' => 'Tim', 'additional_charge' => 500000]);
        $o->recalculateTotal();
        $this->actingAs($pengaju)->post("/jersey/keuangan/{$o->id}/bayar", ['tanggal_bayar' => now()->toDateString(), 'jumlah' => 100000, 'metode_bayar' => 'Tunai'])->assertSessionHas('success');
        $idBayar = DB::table('jersey_order_payments')->where('jersey_order_id', $o->id)->value('id');
        $this->actingAs($pengaju)->delete("/jersey/keuangan/bayar/{$idBayar}", ['alasan' => 'Salah input'])->assertSessionHas('success');

        return ApprovalRequest::where('tipe_aksi', 'void_pembayaran_jersey')->where('status', 'Menunggu')->latest('id')->firstOrFail();
    }

    public function test_satu_satunya_administrator_boleh_menyetujui_sendiri_dengan_catatan(): void
    {
        $req = $this->ajukanVoid($this->admin);

        $this->actingAs($this->admin)->patch("/approval/{$req->id}/approve", ['catatan' => 'OK'])->assertSessionHas('success');

        $req->refresh();
        $this->assertSame('Disetujui', $req->status);
        $this->assertStringContainsString('pengaju sendiri', $req->catatan_keputusan);
        $this->assertStringContainsString('OK', $req->catatan_keputusan);
    }

    public function test_bila_ada_administrator_lain_pengaju_tidak_bisa_menyetujui_sendiri(): void
    {
        $req = $this->ajukanVoid($this->admin);
        $admin2 = User::factory()->create(['id_role' => $this->roleAdmin->id_role]);

        $this->actingAs($this->admin)->patch("/approval/{$req->id}/approve", ['catatan' => 'OK'])
            ->assertSessionHas('error');
        $this->assertSame('Menunggu', $req->fresh()->status);
        $this->assertSame(1, DB::table('jersey_order_payments')->whereNull('deleted_at')->count(), 'Aksi tidak boleh dieksekusi');

        // Administrator lain bisa memutuskan, tanpa catatan pengaju-sendiri.
        $this->actingAs($admin2)->patch("/approval/{$req->id}/approve", ['catatan' => 'Sudah dicek'])->assertSessionHas('success');
        $req->refresh();
        $this->assertSame('Disetujui', $req->status);
        $this->assertSame($admin2->id_user, (int) $req->decided_by);
        $this->assertStringNotContainsString('pengaju sendiri', $req->catatan_keputusan);
    }

    public function test_pengaju_tetap_boleh_menolak_pengajuannya_sendiri(): void
    {
        $req = $this->ajukanVoid($this->admin);
        User::factory()->create(['id_role' => $this->roleAdmin->id_role]);

        $this->actingAs($this->admin)->patch("/approval/{$req->id}/reject", ['catatan' => 'Ternyata tidak jadi'])->assertSessionHas('success');

        $this->assertSame('Ditolak', $req->fresh()->status);
    }
}
