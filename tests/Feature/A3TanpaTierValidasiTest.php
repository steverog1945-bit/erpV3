<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Validasi server-side: "Potong Manual A3" TIDAK boleh dipakai bila
 * master finishing-nya belum punya tier harga (finishing_harga kosong).
 * Harus ditolak dengan pesan ramah, HTTP 422 (bukan 500).
 */
class A3TanpaTierValidasiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idProduk;
    private int $idKonsumen;
    private int $idGolongan;
    private int $idFinishing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
        DB::table('roles')->where('nama_role', 'Administrator')->update(['boleh_lihat_harga' => true]);

        $this->idGolongan = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Konsumen A3', 'telepon' => '0812', 'alamat' => '-', 'id_golongan' => $this->idGolongan,
        ]);

        $this->idProduk = DB::table('produk')->insertGetId([
            'nama_produk' => 'HVS A3 Uji', 'metode_hitung' => 'quantity', 'status' => 'Aktif',
        ]);
        DB::table('setting_harga')->insert([
            'id_produk' => $this->idProduk, 'id_golongan' => $this->idGolongan,
            'range_min' => 0, 'range_max' => null, 'harga_jual' => 5000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('kas_harian')->insert([
            'tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user,
            'saldo_awal' => 0, 'status_lock' => 'open',
        ]);

        $this->idFinishing = (int) DB::table('finishing')->where('nama_finishing', 'Potong Manual A3')->value('id_finishing');

        // Pastikan TIDAK ada tier harga untuk A3 (simulasi master belum diisi).
        DB::table('finishing_harga')->where('id_finishing', $this->idFinishing)->delete();
    }

    private function payloadA3(): array
    {
        return [
            'id_konsumen' => $this->idKonsumen,
            'id_golongan' => $this->idGolongan,
            'items' => [[
                'id_produk' => $this->idProduk,
                'nama_file' => 'a3.pdf',
                'p' => 0, 'l' => 0, 'qty_input' => 1,
                'biaya_cetak' => 5000,
                'biaya_desain' => 0,
                'finishing' => [[
                    'id_finishing' => $this->idFinishing,
                    'status_finishing' => 'Standar',
                    'lebar_potong' => 10, 'tinggi_potong' => 10,
                    'lebar_media' => 100, 'tinggi_media' => 100,
                ]],
            ]],
        ];
    }

    public function test_pos_store_a3_tanpa_tier_ditolak_422(): void
    {
        $r = $this->actingAs($this->admin)->postJson('/penjualan/store', $this->payloadA3());

        $r->assertStatus(422);
        $this->assertStringContainsString('Tier harga Potong Manual A3 belum diisi', (string) $r->json('msg'));
    }

    public function test_pos_store_a3_tanpa_tier_tidak_menyimpan_nota(): void
    {
        $this->actingAs($this->admin)->postJson('/penjualan/store', $this->payloadA3());

        $this->assertSame(0, DB::table('transaksi')->count(), 'Nota tidak boleh tersimpan');
        $this->assertSame(0, DB::table('transaksi_detail_finishing')->count(), 'Finishing tidak boleh tersimpan');
    }

    public function test_edit_nota_a3_tanpa_tier_ditolak_422(): void
    {
        // Buat tier dulu agar nota awal bisa dibuat.
        DB::table('finishing_harga')->insert([
            'id_finishing' => $this->idFinishing,
            'range_min' => 1, 'range_max' => null, 'harga' => 450,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $r0 = $this->actingAs($this->admin)->postJson('/penjualan/store', $this->payloadA3());
        $r0->assertOk();
        $idTransaksi = (int) $r0->json('id_transaksi');
        $idDetail = (int) DB::table('transaksi_detail')->where('id_transaksi', $idTransaksi)->value('id_detail');

        // Hapus tier (simulasi master dikosongkan) lalu coba edit nota
        // dengan menambah A3 finishing lagi.
        DB::table('finishing_harga')->where('id_finishing', $this->idFinishing)->delete();

        // Kirim update-item dengan item yang sama + A3 finishing (tanpa tier).
        $r = $this->actingAs($this->admin)->postJson("/penjualan/{$idTransaksi}/update-item", [
            'items' => [[
                'id_detail' => $idDetail,
                'id_produk' => $this->idProduk,
                'nama_file' => 'a3.pdf',
                'p' => 0, 'l' => 0, 'qty_input' => 1,
                'biaya_cetak' => 5000,
                'biaya_desain' => 0,
                'finishing' => [[
                    'id_finishing' => $this->idFinishing,
                    'status_finishing' => 'Standar',
                    'lebar_potong' => 10, 'tinggi_potong' => 10,
                    'lebar_media' => 100, 'tinggi_media' => 100,
                ]],
            ]],
        ]);

        $r->assertStatus(422);
        $this->assertStringContainsString('Tier harga Potong Manual A3 belum diisi', (string) $r->json('msg'));
    }

    public function test_spk_simpan_finishing_a3_tanpa_tier_ditolak_ramah(): void
    {
        // Setup: butuh transaksi_detail (item SPK). Buat via store dulu.
        DB::table('finishing_harga')->insert([
            'id_finishing' => $this->idFinishing,
            'range_min' => 1, 'range_max' => null, 'harga' => 450,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $r0 = $this->actingAs($this->admin)->postJson('/penjualan/store', $this->payloadA3());
        $r0->assertOk();
        $idDetail = (int) DB::table('transaksi_detail')->first()->id_detail;

        // Hapus tier.
        DB::table('finishing_harga')->where('id_finishing', $this->idFinishing)->delete();

        $req = \Illuminate\Http\Request::create("/spk/{$idDetail}/finishing", 'POST', [
            'finishing' => [[
                'id_finishing' => $this->idFinishing,
                'status_finishing' => 'Standar',
                'biaya' => 100,
                'catatan' => 'uji',
            ]],
        ]);
        $req->setUserResolver(fn () => $this->admin);
        // Referer supaya back() mengarah ke halaman yang masuk akal.
        $req->headers->set('Referer', 'http://localhost/spk');
        $ctrl = app(\App\Http\Controllers\SpkController::class);
        $resp = $ctrl->simpanFinishing($req, $idDetail);

        $this->assertTrue($resp instanceof \Illuminate\Http\RedirectResponse, 'Harus redirect (pesan ramah)');
        $session = $resp->getSession();
        $this->assertSame('Tier harga Potong Manual A3 belum diisi, hubungi admin.', $session->get('error'),
            'Pesan ramah harus muncul di flash error (SPK)');
    }
}
