<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\NotifikasiService;
use App\Support\JalurLanjut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotifikasiRoleDibatasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_desain_tidak_ditawari_tujuan_yang_pasti_403_seperti_daftar_order_jersey(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Operator Desain']);
        foreach ([38, 42] as $id) {
            DB::table('menus')->insertOrIgnore(['id_menu' => $id, 'nama_menu' => "M{$id}", 'url_link' => "m{$id}", 'parent_id' => 0]);
            DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => $id, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 0, 'can_delete' => 0]);
        }
        $desain = User::factory()->create(['id_role' => $role->id_role]);
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);

        DB::table('jersey_orders')->insert(['order_number' => 'JO-DL', 'order_date' => now()->toDateString(), 'customer_name' => 'Tim', 'deadline' => now()->addDays(2)->toDateString(), 'status' => 'baru', 'total' => 1]);

        // Halaman daftar jersey memang 403 untuk Operator Desain...
        $this->actingAs($desain)->get('/jersey')->assertForbidden();

        // ...maka tidak boleh muncul di notifikasinya, sedangkan Administrator tetap melihatnya.
        Cache::flush();
        $urlDesain = collect(NotifikasiService::untuk($desain)['items'])->pluck('url')->implode(' ');
        $this->assertStringNotContainsString('/jersey', $urlDesain);
        Cache::flush();
        $this->assertStringContainsString('/jersey', collect(NotifikasiService::untuk($admin)['items'])->pluck('url')->implode(' '));

        $this->actingAs($desain);
        $this->assertFalse(JalurLanjut::bolehUntuk($desain, url('/jersey')));
    }
}
