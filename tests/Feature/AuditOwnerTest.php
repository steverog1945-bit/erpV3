<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditOwnerTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $r = Role::factory()->create(['nama_role' => 'Owner']);
        DB::table('roles')->where('id_role', $r->id_role)->update(['boleh_lihat_harga' => 1]);
        DB::table('menus')->insertOrIgnore(['id_menu' => 60, 'nama_menu' => 'Audit', 'url_link' => 'audit-owner', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $r->id_role, 'id_menu' => 60]);

        return User::factory()->create(['id_role' => $r->id_role]);
    }

    public function test_owner_bisa_buka_audit_dan_unduh_csv(): void
    {
        $o = $this->owner();
        $this->actingAs($o)->get('/audit-owner?bulan=' . now()->format('Y-m'))->assertOk()->assertSee('Keuangan bulan ini')->assertSee('Pemakaian bahan');
        $this->actingAs($o)->get('/audit-owner/csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($o)->get('/eksekutif')->assertOk();
        $this->actingAs($o)->get('/log-aktivitas')->assertOk();
    }

    public function test_owner_baca_saja_semua_perubahan_ditolak(): void
    {
        $o = $this->owner();
        $this->actingAs($o)->post('/penjualan/store', [])->assertForbidden();
        $this->actingAs($o)->post('/kasir/kas-manual', [])->assertForbidden();
    }

    public function test_owner_diarahkan_ke_audit_dari_dashboard_dan_role_lain_tidak_boleh_masuk(): void
    {
        $o = $this->owner();
        $this->actingAs($o)->get('/dashboard')->assertRedirect(route('audit-owner.index'));

        $r = Role::factory()->create(['nama_role' => 'Kasir']);
        $k = User::factory()->create(['id_role' => $r->id_role]);
        $this->actingAs($k)->get('/audit-owner')->assertForbidden();
    }
}
