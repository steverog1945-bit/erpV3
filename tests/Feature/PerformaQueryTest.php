<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit performa: composer view('*') dulu menjalankan query kas aktif untuk setiap view dan komponen
 * (terukur 316 kali pada satu halaman). Status kas cukup dibaca sekali per permintaan.
 */
class PerformaQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_kas_tidak_dibaca_berulang_untuk_setiap_komponen_view(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);

        foreach (['/produk', '/konsumen', '/supplier'] as $uri) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($admin)->get($uri)->assertOk();
            $kas = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'kas_harian') && str_contains($q['query'], 'status_lock'))->count();
            $this->assertLessThanOrEqual(3, $kas, "{$uri}: query status kas berulang {$kas} kali");
        }
    }
}
