<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardBaruTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_administrator_menampilkan_kpi_tren_dan_perlu_tindakan(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);

        $res = $this->actingAs($admin)->get('/dashboard')->assertOk();
        $res->assertSee('Order Hari Ini')->assertSee('Kas Masuk Hari Ini')->assertSee('Alur Pekerjaan')->assertSee('Perlu Tindakan')
            ->assertSee('Order 7 Hari Terakhir')->assertSee('Kas Masuk 7 Hari Terakhir');
    }

    public function test_panduan_visual_finishing_tidak_lagi_tampil_di_dashboard_mana_pun(): void
    {
        // Diganti Postingan (Info & Panduan) yang isinya diatur sendiri oleh pemilik.
        foreach (['Administrator', 'Operator Desain', 'Operator Produksi'] as $nama) {
            $u = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => $nama])->id_role]);
            $this->actingAs($u)->get('/dashboard')->assertOk()->assertDontSee('Perbandingan Finishing Item Cetak')->assertDontSee('kartu-finishing', false);
        }
    }
}
