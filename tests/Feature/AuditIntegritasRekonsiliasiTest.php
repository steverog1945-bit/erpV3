<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** audit:integritas: buku Kas Kecil vs jurnal 1005, Piutang 1100 vs sisa tagihan, collation seragam. */
class AuditIntegritasRekonsiliasiTest extends TestCase
{
    use RefreshDatabase;

    private function temuan(): array
    {
        Artisan::call('audit:integritas', ['--json' => true]);

        return collect(json_decode(Artisan::output(), true)['temuan'])->pluck(0)->all();
    }

    public function test_alur_lewat_aplikasi_lulus(): void
    {
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
        $this->post('/kas-kecil/saldo-awal', ['tanggal' => now()->toDateString(), 'nominal' => 1000000])->assertSessionHas('success');

        $this->assertSame([], $this->temuan());
    }

    public function test_jurnal_kas_kecil_tanpa_catatan_buku_ketahuan(): void
    {
        AccountingService::postKasKecilMasuk('KK-SELUNDUP', '3000', 50000);

        $this->assertContains('kas_kecil_tidak_sinkron', $this->temuan());
    }

    public function test_piutang_tanpa_tagihan_ketahuan(): void
    {
        AccountingService::postJournal(now()->toDateString(), 'UJI-PIUTANG', 'Piutang tanpa nota', [
            ['kode_akun' => '1100', 'debet' => 75000, 'kredit' => 0],
            ['kode_akun' => '4200', 'debet' => 0, 'kredit' => 75000],
        ]);

        $this->assertContains('piutang_tidak_sinkron', $this->temuan());
    }

    public function test_tabel_tes_seragam_collation(): void
    {
        $this->assertNotContains('collation_campuran', $this->temuan());
    }

    public function test_bahan_aktif_bersaldo_tanpa_harga_rata_rata_ketahuan(): void
    {
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'x']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K', 'kode_kategori' => 'K']);
        $bahan = fn (string $kode, float $stok, string $status) => DB::table('bahan_baku')->insert(['kode_bahan' => $kode, 'nama_bahan' => 'Bahan ' . $kode,
            'id_mesin' => $mesin, 'id_kategori' => $kat, 'stok' => $stok, 'nilai_konversi' => 1, 'harga_rata_rata' => 0, 'satuan_stok' => 'm2', 'status' => $status]);
        $bahan('KOSONG', 0, 'aktif');
        $bahan('NONAKTIF', 5, 'nonaktif');
        $this->assertNotContains('bahan_tanpa_harga', $this->temuan(), 'bahan tanpa stok/nonaktif tidak perlu dilaporkan');

        // Dijual atau dipakai produksi, HPP-nya tercatat 0 dan laba terlihat lebih besar dari kenyataan.
        $bahan('ADA', 10, 'aktif');
        $this->assertContains('bahan_tanpa_harga', $this->temuan());
    }

    public function test_biaya_dibayar_pemilik_tidak_dianggap_kas_kecil_tidak_sinkron(): void
    {
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
        $listrik = DB::table('kategori_kas_kecil')->where('nama_kategori', 'Beban Listrik & Telepon')->value('id_kategori_kas_kecil');
        $this->post('/kas-kecil', ['tanggal' => now()->toDateString(), 'jenis' => 'Keluar', 'id_kategori' => $listrik, 'keterangan' => 'Listrik', 'nominal' => 300000])
            ->assertSessionHas('success');

        $this->assertSame([], $this->temuan());
    }

    public function test_nota_lama_tanpa_jurnal_tidak_membuat_alarm_piutang_permanen(): void
    {
        // Nota dari sebelum integrasi akuntansi: punya tagihan, tetapi tidak pernah dijurnal ke Piutang.
        $kasir = User::factory()->create();
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Lama', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        DB::table('transaksi')->insert(['nomor_nota' => 'INV-LAMA-1', 'tipe_order' => 'Reguler', 'id_konsumen' => $kons, 'id_user_kasir' => $kasir->id_user,
            'tgl_order' => now()->subYear(), 'total_bayar' => 75000, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif']);

        $this->assertNotContains('piutang_tidak_sinkron', $this->temuan());
    }
}
