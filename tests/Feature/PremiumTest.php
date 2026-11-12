<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Halaman Premium: guard privasi keuangan (AksesHarga) + fitur per halaman.
 * Semua page guard memakai aturan yang sama dengan asisten: omzet/margin/
 * piutang hanya untuk role dengan roles.boleh_lihat_harga; halaman
 * operasional (prediksi stok, portal, cadangan) tetap terbuka semua role.
 */
class PremiumTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $desainer;

    protected function setUp(): void
    {
        parent::setUp();
        $adminRole = Role::factory()->create(['nama_role' => 'Administrator', 'boleh_lihat_harga' => true]);
        $this->owner = User::factory()->create(['id_role' => $adminRole->id_role]);

        // Role tanpa hak harga - persis kasus nyata: Operator Desain.
        $roleTanpaHarga = Role::factory()->create(['nama_role' => 'Operator Desain', 'boleh_lihat_harga' => false]);
        $this->desainer = User::factory()->create(['id_role' => $roleTanpaHarga->id_role]);
    }

    private function notaPiutang(string $nomor, int $hariLalu, float $total, float $terbayar = 0, string $telepon = '081234567890'): int
    {
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'G' . uniqid(), 'keterangan' => '-']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Pelanggan ' . $nomor, 'telepon' => $telepon, 'alamat' => '-', 'id_golongan' => $gol]);
        $id = DB::table('transaksi')->insertGetId([
            'nomor_nota' => $nomor, 'tipe_order' => 'Reguler', 'id_konsumen' => $kons,
            'id_user_kasir' => $this->owner->id_user,
            'tgl_order' => now()->subDays($hariLalu), 'created_at' => now()->subDays($hariLalu), 'updated_at' => now(),
            'total_bayar' => $total, 'status_pembayaran' => $terbayar > 0 ? 'DP' : 'Belum Bayar',
            'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir',
        ]);
        if ($terbayar > 0) {
            DB::table('pembayaran_riwayat')->insert(['id_transaksi' => $id, 'nominal' => $terbayar, 'metode' => 'Tunai', 'tgl_bayar' => now()->subDay()]);
        }

        return $id;
    }

    // ===== GUARD PRIVASI KEUANGAN =====

    public function test_analisis_ditolak_role_tanpa_hak_harga(): void
    {
        $r = $this->actingAs($this->desainer)->get(route('premium.analisis'));
        $r->assertStatus(403);
        $this->assertStringNotContainsString('Omzet', $r->getContent());
    }

    public function test_analisis_dibuka_role_berhak(): void
    {
        $r = $this->actingAs($this->owner)->get(route('premium.analisis'));
        $r->assertOk();
        $r->assertSee('Omzet');
        $r->assertSee('Konsumen Teratas');
    }

    public function test_piutang_wa_ditolak_role_tanpa_hak_harga(): void
    {
        $this->actingAs($this->desainer)->get(route('premium.piutang-wa'))->assertStatus(403);
        // POST template satu aturan dengan halaman asalnya.
        $this->actingAs($this->desainer)->post(route('premium.wa-template-save'), ['wa_pesan_template' => 'x'])->assertStatus(403);
    }

    public function test_halaman_operasional_tetap_terbuka_semua_role(): void
    {
        // Prediksi stok, portal, cadangan = operasional (tanpa nilai uang)
        // - role tanpa hak harga tetap harus bisa memakainya.
        $this->actingAs($this->desainer)->get(route('premium.prediksi-stok'))->assertOk();
        $this->actingAs($this->desainer)->get(route('premium.portal'))->assertOk();
        $this->actingAs($this->desainer)->get(route('premium.cadangan'))->assertOk();
        $this->actingAs($this->desainer)->get(route('premium.index'))->assertOk();
    }

    // ===== ANALISIS =====

    public function test_analisis_periode_7_menampilkan_7_hari(): void
    {
        $this->notaPiutang('A-1', 0, 100000);
        $r = $this->actingAs($this->owner)->get(route('premium.analisis', ['periode' => 7]));
        $r->assertOk();
        $r->assertSee('7 Hari Terakhir');
        $r->assertSee('Rp 100.000');
    }

    public function test_analisis_menampilkan_bandingan_periode(): void
    {
        // Omzet 100rb periode ini; periode sebelumnya 50rb → +100%.
        $this->notaPiutang('B-1', 0, 100000);
        $this->notaPiutang('B-2', 45, 50000);
        $r = $this->actingAs($this->owner)->get(route('premium.analisis', ['periode' => 30]));
        $r->assertOk();
        $r->assertSee('fa-arrow-trend-up');
    }

    public function test_analisis_menampilkan_konsumen_teratas(): void
    {
        $this->notaPiutang('C-1', 0, 300000);
        $r = $this->actingAs($this->owner)->get(route('premium.analisis', ['periode' => 30]));
        $r->assertOk();
        $r->assertSee('Pelanggan C-1');
        $r->assertSee('Rp 300.000');
    }

    public function test_analisis_csv_export_berisi_data_harian(): void
    {
        $this->notaPiutang('D-1', 0, 150000);
        $r = $this->actingAs($this->owner)->get(route('premium.analisis-csv', ['periode' => 7]));
        $r->assertOk();
        $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));
        $csv = $r->streamedContent();
        $this->assertStringContainsString('Omzet Harian', $csv);
        $this->assertStringContainsString('150000', $csv);
        // CSV = dokumen owner - guard yang sama berlaku.
        $this->actingAs($this->desainer)->get(route('premium.analisis-csv'))->assertStatus(403);
    }

    // ===== PIUTANG WA =====

    public function test_piutang_urut_dari_terlama(): void
    {
        $this->notaPiutang('E-1', 2, 100000);  // baru
        $this->notaPiutang('E-2', 40, 100000); // lama
        $r = $this->actingAs($this->owner)->get(route('premium.piutang-wa'));
        $r->assertOk();
        $html = $r->getContent();
        $this->assertLessThan(strpos($html, 'E-1'), strpos($html, 'E-2'), 'Nota terlama harus tampil lebih dulu');
    }

    public function test_piutang_filter_umur(): void
    {
        $this->notaPiutang('F-1', 10, 100000);
        $this->notaPiutang('F-2', 40, 100000);
        $this->actingAs($this->owner)->get(route('premium.piutang-wa', ['umur' => 7]))
            ->assertOk()
            ->assertSee('Tidak ada nota belum lunas')
            ->assertDontSee('F-2');
        $this->actingAs($this->owner)->get(route('premium.piutang-wa', ['umur' => 60]))
            ->assertOk()
            ->assertSee('F-2');
    }

    public function test_piutang_placeholder_sisa_dan_tombol_link(): void
    {
        // Total 100rb, terbayar 30rb → sisa 70rb; DP dianggap terisi template.
        DB::table('app_settings')->updateOrInsert(['id' => 1], ['wa_pesan_template' => '{nota} sisa {sisa}']);
        \App\Models\AppSetting::clearCache();
        $this->notaPiutang('G-1', 5, 100000, 30000);

        $r = $this->actingAs($this->owner)->get(route('premium.piutang-wa'));
        $r->assertOk();
        $html = $r->getContent();
        $this->assertStringContainsString('G-1 sisa 70.000', html_entity_decode($html));
        $this->assertStringContainsString('lacak', $html); // tombol "+ Link" dengan signed URL
    }

    // ===== PREDIKSI STOK =====

    private function bahan(string $nama, float $stok, float $min): int
    {
        $mst = DB::table('mesin')->insertGetId(['nama_mesin' => 'M' . uniqid(), 'tipe_mesin' => 'Cetak']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K' . uniqid()]);

        return DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'B' . uniqid(), 'nama_bahan' => $nama, 'id_mesin' => $mst,
            'id_kategori' => $kat, 'stok' => $stok, 'stok_minimal' => $min,
            'satuan_stok' => 'roll', 'status' => 'Aktif',
        ]);
    }

    private function pakai(int $idBahan, float $qty): void
    {
        DB::table('kartu_stok')->insert([
            'id_bahan' => $idBahan, 'tanggal' => now()->subDay(), 'tipe' => 'keluar', 'qty' => $qty,
            'stok_sebelum' => 10, 'stok_sesudah' => 10 - $qty, 'referensi' => 'test', 'created_by' => $this->owner->id_user,
        ]);
    }

    public function test_prediksi_menghilangkan_bahan_min_nol_tanpa_pemakaian(): void
    {
        // Min 0 + tidak pernah dipakai = bukan kandidat beli (false positive lama).
        $this->bahan('BANTALAN PISAU', 0, 0);

        $r = $this->actingAs($this->owner)->get(route('premium.prediksi-stok'));
        $r->assertOk()->assertDontSee('BANTALAN PISAU');
    }

    public function test_prediksi_menghitung_saran_beli(): void
    {
        // Stok 1, min 5, dipakai 60 per 60 hari (1/hari) → gap min 4, buffer 14-1=13 → saran 13.
        $id = $this->bahan('FLS280 Saran Uji', 1, 5);
        $this->pakai($id, 60);

        $r = $this->actingAs($this->owner)->get(route('premium.prediksi-stok'));
        $r->assertOk()->assertSee('FLS280 Saran Uji')->assertSee('13');
    }

    // ===== LACAK PUBLIK (signed URL) =====

    public function test_lacak_bisa_dibuka_dengan_signature_valid(): void
    {
        $id = $this->notaPiutang('H-1', 0, 100000);
        $url = URL::signedRoute('lacak.nota', ['id' => $id], now()->addDays(30));

        $this->get($url)->assertOk()->assertSee('H-1');
    }

    public function test_lacak_signature_rusak_ditolak(): void
    {
        $id = $this->notaPiutang('I-1', 0, 100000);
        $this->get('/lacak/' . $id . '?signature=tampered')->assertStatus(403);
    }
}
