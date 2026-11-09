<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanCetakSeragamTest extends TestCase
{
    use RefreshDatabase;

    public function test_laporan_hpp_hutang_dan_job_costing_punya_tombol_cetak_dan_kop_cetak_standar(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);

        foreach ([
            '/laporan/hpp' => 'Laporan HPP & Margin',
            '/laporan/hutang' => 'Laporan Hutang Pemasok',
            '/laporan/job-costing' => 'Laporan Biaya Pekerjaan',
        ] as $url => $judul) {
            $res = $this->actingAs($admin)->get($url)->assertOk();
            $res->assertSee('window.print()', false);
            $res->assertSee(e($judul), false);
            $res->assertSee('hidden print:block', false);
        }
    }
}
