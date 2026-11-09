<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Menu Purchase Request & Purchase Order - di-grant ke role yang SAMA
// dengan yang sudah punya akses menu 'pembelian-bahan' (mengikuti pola
// add_stock_opname_menu). Persetujuan PR tetap lewat /approval (khusus
// Administrator).
return new class extends Migration
{
    private array $menus = [
        'purchase-request' => 'Purchase Request',
        'purchase-order' => 'Purchase Order',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('menus')) {
            return;
        }

        $refMenuId = DB::table('menus')->where('url_link', 'pembelian-bahan')->value('id_menu');
        $roleIds = ($refMenuId && Schema::hasTable('role_permissions'))
            ? DB::table('role_permissions')->where('id_menu', $refMenuId)->pluck('id_role')
            : collect();

        foreach ($this->menus as $url => $nama) {
            if (DB::table('menus')->where('url_link', $url)->exists()) {
                continue;
            }

            $newId = (int) DB::table('menus')->max('id_menu') + 1;
            DB::table('menus')->insert(['id_menu' => $newId, 'nama_menu' => $nama, 'url_link' => $url, 'parent_id' => 0]);

            foreach ($roleIds as $idRole) {
                DB::table('role_permissions')->insert(['id_role' => $idRole, 'id_menu' => $newId]);
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->menus) as $url) {
            $id = DB::table('menus')->where('url_link', $url)->value('id_menu');
            if ($id) {
                DB::table('role_permissions')->where('id_menu', $id)->delete();
                DB::table('menus')->where('id_menu', $id)->delete();
            }
        }
    }
};
