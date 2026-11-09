<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InputOrderGambarKategoriTest extends TestCase
{
    use RefreshDatabase;

    public function test_alamat_gambar_keranjang_mengikuti_alamat_aplikasi_dan_filter_produk_dikunci_ke_kategori_aktif(): void
    {
        $admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $html = $this->actingAs($admin)->get('/penjualan/kasir')->assertOk()->getContent();

        $this->assertStringNotContainsString('src="/storage/${item.thumbnail}"', $html, 'alamat root-relatif gagal di subfolder/VPS berdomain lain');
        $this->assertStringContainsString('/storage/${item.thumbnail}"', $html);
        $this->assertStringContainsString("document.getElementById('filter_kategori').value", $html, 'matcher membaca kategori yang tampil');
        $this->assertStringContainsString('Kategori tidak cocok', $html);
    }
}
