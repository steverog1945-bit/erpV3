<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regresi untuk catatan penting di akhir brief awal:
 *  - Jasa dan Jasa Jahit Borongan bisa diubah/disimpan oleh role non-Administrator yang diberi izin menunya;
 *  - logout tidak mengikat ke halaman terakhir, dan login selalu mendarat di Dashboard.
 */
class CatatanAwalRegresiTest extends TestCase
{
    use RefreshDatabase;

    private function userDenganMenu(array $idMenu, array $flag = [1, 1, 1, 1]): User
    {
        $role = Role::factory()->create(['nama_role' => 'Staf Uji']);
        foreach ($idMenu as $id) {
            DB::table('menus')->insertOrIgnore(['id_menu' => $id, 'nama_menu' => "M{$id}", 'url_link' => "m{$id}", 'parent_id' => 0]);
            DB::table('role_permissions')->insert([
                'id_role' => $role->id_role, 'id_menu' => $id,
                'can_view' => $flag[0], 'can_add' => $flag[1], 'can_edit' => $flag[2], 'can_delete' => $flag[3],
            ]);
        }

        return User::factory()->create(['id_role' => $role->id_role]);
    }

    public function test_role_dengan_izin_menu_jasa_bisa_tambah_ubah_dan_atur_tier_harga(): void
    {
        $u = $this->userDenganMenu([32]);

        $this->actingAs($u)->post('/jasa', ['nama_jasa' => 'Laminasi Uji', 'harga_default' => 5000])->assertSessionHas('success');
        $id = DB::table('jasa')->where('nama_jasa', 'Laminasi Uji')->value('id_jasa');
        $this->assertNotNull($id);

        $this->actingAs($u)->put("/jasa/{$id}", ['nama_jasa' => 'Laminasi Diubah', 'harga_default' => 7000])->assertSessionHas('success');
        $this->assertSame('Laminasi Diubah', DB::table('jasa')->where('id_jasa', $id)->value('nama_jasa'));

        $r = $this->actingAs($u)->postJson("/jasa/{$id}/harga", ['range_min' => 1, 'range_max' => 10, 'harga' => 6500]);
        $r->assertOk()->assertJsonPath('success', true);
        $tier = DB::table('jasa_harga')->where('id_jasa', $id)->value('id_jasa_harga');

        $this->actingAs($u)->patchJson("/jasa-harga/{$tier}", ['range_min' => 1, 'range_max' => 10, 'harga' => 6000])->assertOk();
        $this->assertEquals(6000, DB::table('jasa_harga')->where('id_jasa_harga', $tier)->value('harga'));
    }

    public function test_role_dengan_izin_menu_jasa_jahit_bisa_ubah_dan_catat(): void
    {
        $u = $this->userDenganMenu([68]);

        $this->actingAs($u)->post('/jasa-jahit', ['nama_jahit' => 'Jahit Kaos Uji', 'harga_borongan' => 3000])->assertSessionHas('success');
        $id = DB::table('jasa_jahit')->where('nama_jahit', 'Jahit Kaos Uji')->value('id_jasa_jahit');
        $this->assertNotNull($id);

        $this->actingAs($u)->put("/jasa-jahit/{$id}", ['nama_jahit' => 'Jahit Kaos Diubah', 'harga_borongan' => 3500])->assertSessionHas('success');
        $this->assertSame('Jahit Kaos Diubah', DB::table('jasa_jahit')->where('id_jasa_jahit', $id)->value('nama_jahit'));
        $this->assertEquals(3500, DB::table('jasa_jahit')->where('id_jasa_jahit', $id)->value('harga_borongan'));
    }

    public function test_role_tanpa_izin_edit_jasa_tetap_ditolak(): void
    {
        $u = $this->userDenganMenu([32], [1, 0, 0, 0]);
        $id = DB::table('jasa')->insertGetId(['nama_jasa' => 'X', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($u)->put("/jasa/{$id}", ['nama_jasa' => 'Y'])->assertForbidden();
    }

    public function test_login_selalu_ke_dashboard_walau_ada_halaman_tujuan_tersimpan(): void
    {
        User::factory()->create(['username' => 'ani_uji', 'password' => Hash::make('rahasia123')]);

        $this->withSession(['url.intended' => url('/penjualan/riwayat')])
            ->post('/login', ['username' => 'ani_uji', 'password' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_logout_membuang_halaman_terakhir_dan_login_berikutnya_ke_dashboard(): void
    {
        config(['landing_page_builder.enabled' => false]);
        $u = User::factory()->create(['username' => 'budi_uji', 'password' => Hash::make('rahasia123')]);

        // Gate B OFF: logout mendarat di halaman login publik /masuk.
        $this->actingAs($u)->withSession(['url.intended' => url('/kasir')])->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertNull(session('url.intended'));

        $this->post('/login', ['username' => 'budi_uji', 'password' => 'rahasia123'])->assertRedirect(route('dashboard'));
    }

    public function test_halaman_login_tidak_menampilkan_aset_karangan_dan_tidak_disimpan_cache(): void
    {
        $r = $this->get('/');

        $r->assertOk();
        $this->assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));
    }
}
