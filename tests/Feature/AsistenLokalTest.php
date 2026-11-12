<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AsistenLokalTest extends TestCase
{
    use RefreshDatabase;

    private int $urutanKode = 1;

    private function nota(string $nomor, string $status = 'Aktif', string $bayar = 'Lunas', float $total = 100000): void
    {
        $seq = $this->urutanKode++;
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail-' . $seq, 'keterangan' => '-']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Pelanggan', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        $id = DB::table('transaksi')->insertGetId([
            'nomor_nota' => $nomor, 'tipe_order' => 'Reguler', 'id_konsumen' => $kons,
            'id_user_kasir' => User::first()->id_user, 'tgl_order' => now(), 'total_bayar' => $total,
            'status_pembayaran' => $bayar, 'status_nota' => $status, 'status_order' => 'Diterima Kasir',
        ]);
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M' . $seq, 'tipe_mesin' => 'Cetak']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K' . $seq, 'kode_kategori' => 'K' . $seq]);
        $produk = DB::table('produk')->insertGetId(['nama_produk' => 'Produk', 'metode_hitung' => 'quantity', 'status' => 'aktif']);
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $id, 'id_produk' => $produk,
            'nama_file' => 'F', 'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 1,
            'subtotal' => $total, 'hpp_snapshot' => $total / 2, 'status_spk' => 'Antre',
        ]);
    }

    private function tanya(string $pesan, ?User $user = null)
    {
        $user = $user ?? User::first();
        return $this->actingAs($user)->postJson(route('premium.asisten.tanya'), ['pesan' => $pesan]);
    }

    public function test_berapa_order_hari_ini(): void
    {
        User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->nota('LAN-1');
        $r = $this->tanya('berapa order hari ini');
        $r->assertOk()->assertJsonStructure(['reply', 'intent']);
        $this->assertStringContainsString('1 nota', $r->json('reply'));
    }

    public function test_omzet_ditolak_tanpa_hak_harga(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Kasir Biasa', 'boleh_lihat_harga' => false]);
        $user = User::factory()->create(['id_role' => $role->id_role]);
        $r = $this->tanya('omzet hari ini', $user);
        $r->assertOk();
        $this->assertStringContainsString('harga', $r->json('reply'));
    }

    public function test_nota_lookup_menemukan_nota(): void
    {
        User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->nota('LAN-77', 'Aktif', 'Lunas', 175000);
        $r = $this->tanya('nota LAN-77');
        $r->assertOk();
        $this->assertStringContainsString('LAN-77', $r->json('reply'));
        $this->assertStringContainsString('175.000', $r->json('reply'));
    }

    public function test_bantuan_mencantumkan_perintah(): void
    {
        User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $r = $this->tanya('/bantuan');
        $r->assertOk();
        $this->assertStringContainsString('rekap bulan ini', $r->json('reply'));
    }

    public function test_rekap_bulan_ini_admin_memuat_angka(): void
    {
        User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->nota('LAN-9', 'Aktif', 'Lunas', 200000);
        $r = $this->tanya('rekap bulan ini');
        $r->assertOk();
        $this->assertStringContainsString('omzet', mb_strtolower($r->json('reply')));
    }

    public function test_pertanyaan_kosong_dibalas_halus(): void
    {
        User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $r = $this->tanya('   ');
        $r->assertStatus(422);
    }

    // ===== ASISTEN HPP (internal, guard AksesHarga, idem engine checkout) =====

    /**
     * Produk + BOM 1 bahan. Rumus dipilih dari daftar yang DIAKUI
     * BomCalculationService::hitungQtyPakai - jadi angka HPP tes = angka
     * checkout nyata dengan data yang sama.
     */
    private function bomProduk(string $namaProduk, string $rumus, float $hargaRata, float $nilaiKonversi = 1): void
    {
        $seq = $this->urutanKode++;
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'MHP' . $seq, 'tipe_mesin' => 'Cetak']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'KHP' . $seq, 'kode_kategori' => 'KHP' . $seq]);
        $bahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'BH' . $seq, 'nama_bahan' => 'Frontlite 280gr', 'id_mesin' => $mesin,
            'id_kategori' => $kat, 'stok' => 100, 'satuan_stok' => 'm2',
            'harga_rata_rata' => $hargaRata, 'nilai_konversi' => $nilaiKonversi,
        ]);
        $produk = DB::table('produk')->insertGetId([
            'nama_produk' => $namaProduk, 'id_mesin' => $mesin,
            'metode_hitung' => 'ukuran', 'status' => 'aktif',
        ]);
        DB::table('bom_detail')->insert(['id_produk' => $produk, 'id_bahan' => $bahan, 'cara_hitung_pemakaian' => $rumus]);
    }

    public function test_hpp_per_m2_dari_bom_idem_engine_checkout(): void
    {
        User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator', 'boleh_lihat_harga' => true])->id_role]);
        // Rumus (P*L)/10000, harga rata-rata 50rb per m2 → per m² = 50rb;
        // ukuran 3x1 (300x100 cm) = 3 m² → 150rb.
        $this->bomProduk('FLS280', '(P*L)/10000', 50000);

        $perM2 = $this->tanya('hpp FLS280');
        $perM2->assertOk();
        $this->assertStringContainsString('Estimasi HPP FLS280', $perM2->json('reply'));
        $this->assertStringContainsString('Rp 50.000', $perM2->json('reply'));
        $this->assertStringContainsString('per m²', $perM2->json('reply'));

        $ukuran = $this->tanya('hpp FLS280 3x1');
        $this->assertStringContainsString('Rp 150.000', $ukuran->json('reply'));
        $this->assertStringContainsString('Rp 50.000/m²', $ukuran->json('reply'));
    }

    public function test_hpp_menghormati_nilai_konversi_seperti_checkout(): void
    {
        User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator', 'boleh_lihat_harga' => true])->id_role]);
        // Bahan dijual per roll 50m2 (nilai_konversi 50): 1 m² pakai = 1/50
        // roll × 500.000/roll = 10.000 per m² - persis cara checkout memotong stok.
        $this->bomProduk('FLS280', '(P*L)/10000', 500000, 50);

        $r = $this->tanya('hpp FLS280');
        $this->assertStringContainsString('Rp 10.000', $r->json('reply'));
    }

    public function test_hpp_nota_dari_snapshot_bukan_hitung_ulang(): void
    {
        User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator', 'boleh_lihat_harga' => true])->id_role]);
        // nota() menyimpan hpp_snapshot = total/2 → 100.000 dari jual 200.000 (50%).
        $this->nota('LAN-5', 'Aktif', 'Lunas', 200000);
        $r = $this->tanya('hpp nota LAN-5');
        $r->assertOk();
        $reply = $r->json('reply');
        $this->assertStringContainsString('LAN-5', $reply);
        $this->assertStringContainsString('Rp 100.000', $reply);
        $this->assertStringContainsString('hpp_snapshot', $reply);
    }

    public function test_hpp_lewat_alias_tanpa_hardcode(): void
    {
        User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator', 'boleh_lihat_harga' => true])->id_role]);
        $this->bomProduk('FLS280', '(P*L)/10000', 50000);
        // Alias dari kamus owner (tabel produk_alias), bukan mapping di kode.
        $idProduk = DB::table('produk')->where('nama_produk', 'FLS280')->value('id_produk');
        DB::table('produk_alias')->insert(['id_produk' => $idProduk, 'alias' => 'spanduk', 'created_at' => now(), 'updated_at' => now()]);

        $r = $this->tanya('hpp spanduk');
        $r->assertOk();
        $this->assertStringContainsString('Estimasi HPP FLS280', $r->json('reply'));
    }

    public function test_hpp_ditolak_tanpa_hak_harga(): void
    {
        $role = Role::factory()->create(['nama_role' => 'Operator Desain', 'boleh_lihat_harga' => false]);
        $user = User::factory()->create(['id_role' => $role->id_role]);
        $this->nota('LAN-8');
        $r = $this->tanya('hpp nota LAN-8', $user);
        $r->assertOk();
        $reply = $r->json('reply');
        $this->assertStringContainsString('harga', $reply);
        $this->assertStringNotContainsString('Rp', $reply);
    }
}
