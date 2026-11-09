<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiCheckoutGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sah_tidak_bisa_memakai_store_api_lama(): void
    {
        config()->set('orion_api.enabled', true);
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $user = User::factory()->create(['id_role' => $role->id_role]);
        $token = \Tests\Support\TokenApiPengujian::token($user);

        $sebelum = [
            DB::table('transaksi')->count(),
            DB::table('transaksi_detail')->count(),
            DB::table('kartu_stok')->count(),
        ];

        $this->postJson('/api/transaksi', [
            'id_konsumen' => 1,
            'items' => [['id_produk' => 1, 'qty_input' => 10]],
        ], ['Authorization' => 'Bearer ' . $token])
            ->assertStatus(503)
            ->assertHeaderContains('Cache-Control', 'no-store')
            ->assertJsonPath('message', 'Checkout API belum tersedia; gunakan alur ERP web.');

        $this->assertSame($sebelum, [
            DB::table('transaksi')->count(),
            DB::table('transaksi_detail')->count(),
            DB::table('kartu_stok')->count(),
        ]);
    }

    public function test_tanpa_token_masih_401_saat_api_dibuka(): void
    {
        config()->set('orion_api.enabled', true);
        $this->postJson('/api/transaksi', [])->assertUnauthorized();
    }

    public function test_api_default_off_tetap_503(): void
    {
        config()->set('orion_api.enabled', false);
        $this->postJson('/api/transaksi', [])->assertStatus(503);
    }
}
