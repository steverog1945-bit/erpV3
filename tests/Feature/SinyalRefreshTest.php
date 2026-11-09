<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SinyalRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $r = Role::factory()->create(['nama_role' => $role]);
        return User::factory()->create(['id_role' => $r->id_role]);
    }

    public function test_sinyal_berubah_saat_data_berubah_dan_ringan(): void
    {
        $adm = $this->user('Administrator');
        Cache::forget('sinyal_perubahan');
        $a = $this->actingAs($adm)->getJson('/sinyal')->assertOk()->json();
        $this->assertArrayHasKey('v', $a);
        $this->assertSame(0, $a['approval']);

        ApprovalService::ajukan('batalkan_nota', 'transaksi', 1, [], 'uji');
        Cache::forget('sinyal_perubahan');
        $b = $this->actingAs($adm)->getJson('/sinyal')->json();
        $this->assertNotSame($a['v'], $b['v']);
        $this->assertSame(1, $b['approval']);
    }

    public function test_non_admin_boleh_sinyal_tanpa_jumlah_approval(): void
    {
        $op = $this->user('Operator Desain');
        $this->actingAs($op)->getJson('/sinyal')->assertOk()->assertJson(['approval' => null]);
    }

    public function test_pengajuan_menyalakan_jalan_pintas_ke_antrian_approval(): void
    {
        $adm = $this->user('Administrator');
        $this->actingAs($adm);
        ApprovalService::ajukan('batalkan_nota', 'transaksi', 1, [], 'uji');
        $l = session('lanjut');
        $this->assertSame(route('approval.index'), $l['url']);
    }
}
