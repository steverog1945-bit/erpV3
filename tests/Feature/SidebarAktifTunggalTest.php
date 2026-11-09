<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarAktifTunggalTest extends TestCase
{
    use RefreshDatabase;

    /** Hanya satu tautan sidebar yang menyala, mis. Deposit Konsumen tidak ikut menyalakan Data Konsumen. */
    public function test_menu_deposit_konsumen_tidak_menyalakan_data_konsumen(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);

        $html = $this->actingAs($admin)->get('/konsumen-deposit')->assertOk()->getContent();
        preg_match_all('#<a href="([^"]+)" class="[^"]*sb-active[^"]*"#', $html, $m);

        $this->assertCount(1, $m[1], 'menu aktif harus tunggal, ditemukan: ' . implode(', ', $m[1]));
        $this->assertStringContainsString('konsumen-deposit', $m[1][0]);
    }
}
