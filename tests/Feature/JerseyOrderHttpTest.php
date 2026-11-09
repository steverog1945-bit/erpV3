<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit Order Jersey (HTTP, dari form sampai database): baris pemain ke-1, ke-10,
 * ke-20, ke-50 tidak boleh menghasilkan galat larik/indeks; semua ukuran master
 * (XS sampai 3XL) dan ukuran kustom yang dibuat dari Master otomatis diterima;
 * ukuran yang tidak terdaftar ditolak dengan indeks baris yang benar.
 */
class JerseyOrderHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idKons;
    private int $tipe;
    private int $kategori;
    private int $bahan;
    private int $lengan;
    private int $kerah;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00:00'));

        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'G', 'keterangan' => '-']);
        $this->idKons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Klub Uji', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);

        // Master bawaan migrasi (tipe, kategori, bahan, lengan, kerah, ukuran) dipakai apa adanya; hanya yang belum ada dibuat.
        $now = now();
        $ambil = function (string $tabel, string $nama, array $tambahan = []) use ($now): int {
            $id = DB::table($tabel)->where('name', $nama)->value('id');

            return (int) ($id ?: DB::table($tabel)->insertGetId(array_merge(['name' => $nama, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now], $tambahan)));
        };
        $this->tipe = $ambil('jersey_product_types', 'Jersey Uji', ['has_sleeve_collar' => 1]);
        $this->kategori = $ambil('jersey_categories', 'Futsal Uji');
        $this->bahan = $ambil('jersey_materials', 'Brazil Uji');
        $this->lengan = $ambil('jersey_sleeve_types', 'Pendek', ['price_surcharge' => 0, 'sort_order' => 1]);
        $this->kerah = $ambil('jersey_collar_types', 'O-Neck', ['price_surcharge' => 0, 'sort_order' => 1]);

        foreach (['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'] as $i => $u) {
            $ada = DB::table('jersey_sizes')->where('name', $u)->exists();
            if (!$ada) {
                DB::table('jersey_sizes')->insert(['name' => $u, 'sort_order' => $i + 1, 'is_active' => 1, 'price_surcharge' => 0, 'kelompok' => 'dewasa', 'price_percent' => 0, 'created_at' => $now, 'updated_at' => $now]);
            }
            DB::table('jersey_sizes')->where('name', $u)->update(['is_active' => 1]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pemain(int $n, string $ukuran): array
    {
        return [
            'player_name' => "Pemain {$n}", 'player_number' => (string) $n, 'size' => $ukuran, 'gender' => $n % 2 ? 'L' : 'P',
            'jersey_sleeve_type_id' => $this->lengan, 'jersey_collar_type_id' => $this->kerah,
        ];
    }

    private function payload(array $items): array
    {
        return [
            'order_date' => now()->toDateString(), 'id_konsumen' => $this->idKons, 'team_name' => 'Klub Uji FC',
            'lines' => [[
                'nama_barang' => 'Jersey Home', 'jersey_product_type_id' => $this->tipe, 'jersey_category_id' => $this->kategori,
                'jersey_material_id' => $this->bahan, 'jersey_sleeve_type_id' => $this->lengan, 'jersey_collar_type_id' => $this->kerah,
                'thumbnail' => 'jersey_thumbnails/uji.jpg', 'order_type' => 'player_list', 'items' => $items,
            ]],
        ];
    }

    public function test_enam_puluh_pemain_semua_ukuran_master_dan_ukuran_kustom_tersimpan_tanpa_galat_indeks(): void
    {
        // Ukuran kustom dibuat dari Master SETELAH aplikasi berjalan: tidak ada whitelist hard-code yang boleh menolaknya.
        DB::table('jersey_sizes')->insert(['name' => 'XXL Kustom Uji', 'sort_order' => 99, 'is_active' => 1, 'price_surcharge' => 0, 'kelompok' => 'dewasa', 'price_percent' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $ukuran = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', 'XXL Kustom Uji'];

        $items = [];
        for ($n = 1; $n <= 60; $n++) {
            $items[] = $this->pemain($n, $ukuran[($n - 1) % count($ukuran)]);
        }

        $r = $this->actingAs($this->admin)->post('/jersey/store', $this->payload($items));

        $r->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('jersey_orders')->count());
        $this->assertSame(60, DB::table('jersey_order_items')->count());
        foreach ([1, 10, 20, 50, 60] as $n) {
            $baris = DB::table('jersey_order_items')->where('player_name', "Pemain {$n}")->first();
            $this->assertNotNull($baris, "Pemain ke-{$n} harus tersimpan");
            $this->assertNotNull($baris->jersey_size_id, "Pemain ke-{$n}: ukuran harus tertaut ke master");
        }
        $this->assertGreaterThan(0, DB::table('jersey_order_items')->where('size', 'XXL Kustom Uji')->count());
        $this->assertSame(60, (int) DB::table('jersey_order_lines')->value('qty'));
    }

    public function test_ukuran_tidak_terdaftar_ditolak_dengan_indeks_baris_yang_benar_dan_tidak_menyimpan_sebagian(): void
    {
        foreach ([0, 9, 19, 49] as $indeks) {
            $items = [];
            for ($n = 1; $n <= 50; $n++) {
                $items[] = $this->pemain($n, $n - 1 === $indeks ? 'ZZ' : 'M');
            }

            $this->actingAs($this->admin)->post('/jersey/store', $this->payload($items))->assertSessionHasErrors("lines.0.items.{$indeks}.size");
            $this->assertSame(0, DB::table('jersey_orders')->count(), "Baris pemain ke-" . ($indeks + 1) . " salah: tidak boleh ada order tersimpan sebagian");
        }
    }

    public function test_ukuran_master_yang_dinonaktifkan_ditolak(): void
    {
        DB::table('jersey_sizes')->where('name', '3XL')->update(['is_active' => 0]);

        $this->actingAs($this->admin)->post('/jersey/store', $this->payload([$this->pemain(1, '3XL')]))->assertSessionHasErrors('lines.0.items.0.size');
    }

    public function test_dua_order_di_hari_yang_sama_mendapat_nomor_berbeda_dan_berurutan(): void
    {
        $this->actingAs($this->admin)->post('/jersey/store', $this->payload([$this->pemain(1, 'M')]))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/jersey/store', $this->payload([$this->pemain(1, 'L')]))->assertSessionHasNoErrors();

        $nomor = DB::table('jersey_orders')->orderBy('id')->pluck('order_number')->all();
        $this->assertSame(['JO-20260928-001', 'JO-20260928-002'], $nomor);
    }

    public function test_pemain_tanpa_jenis_kelamin_tidak_menyebabkan_galat_basis_data(): void
    {
        $p = $this->pemain(1, 'M');
        unset($p['gender']);

        $r = $this->actingAs($this->admin)->post('/jersey/store', $this->payload([$p]));

        $r->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('jersey_order_items')->count());
    }

    /** Role tanpa hak harga (Operator Desain) yang boleh menginput order jersey lewat menu 42. */
    private function operatorDesain(): User
    {
        $role = Role::factory()->create(['nama_role' => 'Desain Uji ' . uniqid(), 'boleh_lihat_harga' => 0]);
        DB::table('menus')->insertOrIgnore(['id_menu' => 42, 'nama_menu' => 'Order Jersey (Input Order)', 'url_link' => 'jersey/create', 'parent_id' => 0]);
        DB::table('role_permissions')->insert(['id_role' => $role->id_role, 'id_menu' => 42, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1]);

        return User::factory()->create(['id_role' => $role->id_role]);
    }

    public function test_role_tanpa_hak_harga_tidak_bisa_mengisi_diskon_saat_membuat_order(): void
    {
        $desain = $this->operatorDesain();

        $this->actingAs($desain)->get('/jersey/create')->assertOk()->assertDontSee('name="discount"', false);
        $this->actingAs($desain)->post('/jersey/store', $this->payload([$this->pemain(1, 'M')]) + ['discount' => 50000, 'additional_charge' => 10000])
            ->assertSessionHasNoErrors();

        $o = \App\Models\JerseyOrder::latest('id')->first();
        $this->assertEquals(0, (float) $o->discount);
        $this->assertEquals(0, (float) $o->additional_charge);
    }

    public function test_role_tanpa_hak_harga_mengubah_status_tidak_menghapus_atau_mengubah_diskon(): void
    {
        $this->actingAs($this->admin)->post('/jersey/store', $this->payload([$this->pemain(1, 'M')]) + ['discount' => 20000])->assertSessionHasNoErrors();
        $o = \App\Models\JerseyOrder::latest('id')->first();
        $this->assertEquals(20000, (float) $o->discount);
        $total = (float) $o->total;

        $desain = $this->operatorDesain();
        $this->actingAs($desain)->get("/jersey/{$o->id}")->assertOk()->assertDontSee('name="discount"', false);

        // Form tanpa kolom diskon (yang dilihat role ini), lalu percobaan mengirim diskon lewat permintaan buatan.
        $this->actingAs($desain)->put("/jersey/{$o->id}", ['status' => 'proses', 'notes' => 'mulai potong'])->assertSessionHas('success');
        $this->actingAs($desain)->put("/jersey/{$o->id}", ['status' => 'proses', 'discount' => 999999])->assertSessionHas('success');

        $o->refresh();
        $this->assertEquals(20000, (float) $o->discount);
        $this->assertEquals($total, (float) $o->total);
        $this->assertSame('proses', $o->status);
    }

    private function orderBernilai(int $tambahan): object
    {
        $this->actingAs($this->admin)->post('/jersey/store', $this->payload([$this->pemain(1, 'XL')]) + ['additional_charge' => $tambahan])->assertSessionHasNoErrors();

        return DB::table('jersey_orders')->latest('id')->first();
    }

    public function test_total_order_tidak_boleh_turun_di_bawah_uang_yang_sudah_dibayar(): void
    {
        $o = $this->orderBernilai(100000);
        $this->actingAs($this->admin)->post("/jersey/keuangan/{$o->id}/bayar", ['tanggal_bayar' => now()->toDateString(), 'jumlah' => (float) $o->total, 'metode_bayar' => 'Transfer']);

        // Diskon setelah lunas: total akan di bawah pembayaran -> ditolak dengan pesan, total tidak berubah.
        $this->actingAs($this->admin)->put("/jersey/{$o->id}", ['status' => 'baru', 'discount' => 50000, 'additional_charge' => 100000])->assertSessionHas('error');

        $this->assertEquals((float) $o->total, (float) DB::table('jersey_orders')->where('id', $o->id)->value('total'));
        $this->assertGreaterThanOrEqual(0, (float) DB::table('jurnal_umum')->where('kode_akun', '1100')->sum(DB::raw('debet - kredit')), 'piutang menjadi minus');
    }

    public function test_jurnal_pembayaran_bertanggal_sama_dengan_tanggal_bayar(): void
    {
        $o = $this->orderBernilai(100000);
        $kemarin = now()->subDay()->toDateString();
        $this->actingAs($this->admin)->post("/jersey/keuangan/{$o->id}/bayar", ['tanggal_bayar' => $kemarin, 'jumlah' => 50000, 'metode_bayar' => 'Transfer'])->assertSessionHasNoErrors();

        $this->assertSame($kemarin, (string) DB::table('jurnal_umum')->where('kode_akun', '1010')->value('tgl_jurnal'));
    }

    public function test_order_dengan_upah_jahit_belum_dibayar_tidak_bisa_dihapus(): void
    {
        $o = $this->orderBernilai(0);
        $pj = DB::table('penjahit')->insertGetId(['nama' => 'Bu Sri', 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
        $jenis = DB::table('jasa_jahit')->insertGetId(['nama_jahit' => 'Jahit Jersey', 'harga_borongan' => 5000, 'untuk_jersey' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('jasa_jahit_catat')->insert(['id_jasa_jahit' => $jenis, 'id_penjahit' => $pj, 'id_jersey_order' => $o->id, 'qty' => 1, 'harga_snapshot' => 5000, 'total' => 5000,
            'tgl_catat' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->admin)->delete("/jersey/{$o->id}")->assertSessionHas('error');
        $this->assertTrue(DB::table('jersey_orders')->where('id', $o->id)->exists());

        // Upah jahit jersey tidak bisa dicatat lewat form jahit biasa (melewati batas sisa baju per order).
        $this->actingAs($this->admin)->post('/jasa-jahit/catat', ['id_penjahit' => $pj, 'id_jasa_jahit' => $jenis, 'qty' => 5, 'tgl_catat' => now()->toDateString()])
            ->assertSessionHas('error');
        $this->assertSame(1, DB::table('jasa_jahit_catat')->count());
    }

    public function test_ukuran_yang_dinonaktifkan_setelah_dipakai_order_tetap_bisa_dikelola(): void
    {
        $o = $this->orderBernilai(0);
        DB::table('jersey_sizes')->where('name', 'XL')->update(['is_active' => false]);

        $this->actingAs($this->admin)->put("/jersey/{$o->id}/lines", $this->payload([$this->pemain(1, 'XL'), $this->pemain(2, 'XL')]))->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('jersey_order_items')->count());

        // Order baru tetap tidak boleh memakai ukuran nonaktif.
        $this->actingAs($this->admin)->post('/jersey/store', $this->payload([$this->pemain(1, 'XL')]))->assertSessionHasErrors('lines.0.items.0.size');
    }
}
