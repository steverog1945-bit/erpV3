<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\JerseyOrder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kas Kecil adalah uang fisik terpisah dari laci: saldonya tidak boleh minus lewat jalur mana pun.
 *   - membatalkan pemasukan (saldo awal, setoran pemilik) yang uangnya sudah terpakai ditolak;
 *   - void pembayaran jersey tunai yang uangnya sudah terpakai ditolak;
 *   - kategori tanpa akun tidak bisa dipakai (catatan tanpa jurnal tidak mengurangi saldo, jadi penjaga saldo terlewati).
 */
class KasKecilTidakMinusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Baris dari koneksi kedua (tes konkurensi) sudah di-commit, jadi tidak ikut rollback RefreshDatabase. Dibersihkan
        // setelah transaksi tes ditutup; sebelum itu transaksi tes masih memegang kunci baca atas baris tersebut.
        $this->beforeApplicationDestroyed(function () {
            if (config('database.connections.mysql_kedua')) {
                DB::connection('mysql_kedua')->table('jurnal_umum')->where('no_referensi', 'UJI-KONKUREN')->delete();
                DB::purge('mysql_kedua');
            }
        });
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));
    }

    private function saldo(): float
    {
        return round((float) DB::table('jurnal_umum')->where('kode_akun', '1005')->sum(DB::raw('debet - kredit')), 2);
    }

    private function kategori(string $nama): int
    {
        return (int) DB::table('kategori_kas_kecil')->where('nama_kategori', $nama)->value('id_kategori_kas_kecil');
    }

    private function keluar(float $nominal): int
    {
        $this->post('/kas-kecil', ['tanggal' => now()->toDateString(), 'jenis' => 'Keluar', 'id_kategori' => $this->kategori('Beban Perlengkapan Toko'),
            'keterangan' => 'Beli ATK', 'nominal' => $nominal])->assertSessionHas('success');

        return (int) DB::table('kas_kecil')->where('jenis', 'Keluar')->latest('id')->value('id');
    }

    private function batalkan(int $id)
    {
        $this->delete("/kas-kecil/{$id}", ['alasan' => 'uji'])->assertSessionHas('success');
        $req = ApprovalRequest::where('tipe_aksi', 'batalkan_kas_kecil')->where('referensi_id', $id)->latest('id')->firstOrFail();

        return $this->patch("/approval/{$req->id}/approve", ['catatan' => 'ok']);
    }

    public function test_batal_saldo_awal_yang_sudah_terpakai_ditolak(): void
    {
        $this->post('/kas-kecil/saldo-awal', ['tanggal' => now()->toDateString(), 'nominal' => 1000000])->assertSessionHas('success');
        $idAwal = (int) DB::table('kas_kecil')->where('sumber', 'saldo_awal')->value('id');
        $idKeluar = $this->keluar(800000);
        $this->assertEquals(200000, $this->saldo());

        $this->batalkan($idAwal)->assertSessionHas('error');

        $this->assertEquals(200000, $this->saldo(), 'saldo Kas Kecil menjadi minus');
        $this->assertTrue(DB::table('kas_kecil')->where('id', $idAwal)->exists());

        // Pengajuan yang gagal dieksekusi tetap menunggu keputusan; Administrator menolaknya.
        $gagal = ApprovalRequest::where('tipe_aksi', 'batalkan_kas_kecil')->where('referensi_id', $idAwal)->firstOrFail();
        $this->assertSame('Menunggu', $gagal->status);
        $this->patch("/approval/{$gagal->id}/reject", ['catatan' => 'uang sudah terpakai']);

        // Kontrol: setelah pengeluarannya dibatalkan, uangnya kembali utuh dan saldo awal boleh dibatalkan.
        $this->batalkan($idKeluar)->assertSessionHas('success');
        $this->batalkan($idAwal)->assertSessionHas('success');
        $this->assertEquals(0, $this->saldo());
    }

    public function test_void_pembayaran_jersey_tunai_yang_sudah_terpakai_ditolak(): void
    {
        $o = JerseyOrder::create(['order_number' => 'JO-KK', 'order_date' => now()->toDateString(), 'customer_name' => 'Tim', 'additional_charge' => 500000]);
        $o->recalculateTotal();
        $this->post("/jersey/keuangan/{$o->id}/bayar", ['tanggal_bayar' => now()->toDateString(), 'jumlah' => 300000, 'metode_bayar' => 'Tunai']);
        $this->assertEquals(300000, $this->saldo());
        $this->keluar(250000);
        $idBayar = (int) DB::table('jersey_order_payments')->value('id');

        try {
            \App\Services\JerseyAccountingService::voidPembayaran($idBayar, 'salah input');
            $this->fail('void pembayaran tunai yang sudah terpakai seharusnya ditolak');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Kas Kecil', $e->getMessage());
        }

        $this->assertEquals(50000, $this->saldo());
        $this->assertSame(1, DB::table('jersey_order_payments')->whereNull('deleted_at')->count());
    }

    public function test_void_pembayaran_jersey_transfer_tidak_terhalang_saldo_kas_kecil(): void
    {
        $o = JerseyOrder::create(['order_number' => 'JO-TF', 'order_date' => now()->toDateString(), 'customer_name' => 'Tim', 'additional_charge' => 500000]);
        $o->recalculateTotal();
        $this->post("/jersey/keuangan/{$o->id}/bayar", ['tanggal_bayar' => now()->toDateString(), 'jumlah' => 300000, 'metode_bayar' => 'Transfer']);

        \App\Services\JerseyAccountingService::voidPembayaran((int) DB::table('jersey_order_payments')->value('id'), 'salah input');

        $this->assertSame(0, DB::table('jersey_order_payments')->whereNull('deleted_at')->count());
    }

    /** Pembayaran yang dikoreksi kembaliannya (jurnal -KMB) lalu di-void: jurnal koreksinya ikut dibalik, Kas Kecil kembali 0. */
    public function test_void_pembayaran_jersey_yang_dikoreksi_kembalian_membalik_jurnal_koreksi(): void
    {
        $o = JerseyOrder::create(['order_number' => 'JO-KMB', 'order_date' => now()->toDateString(), 'customer_name' => 'Tim', 'additional_charge' => 500000]);
        $o->recalculateTotal();
        $this->post("/jersey/keuangan/{$o->id}/bayar", ['tanggal_bayar' => now()->toDateString(), 'jumlah' => 300000, 'metode_bayar' => 'Tunai']);
        // Data lama: tagihan sebenarnya 250.000, pembayaran tunai 300.000 (50.000 kembalian ikut tercatat).
        DB::table('jersey_orders')->where('id', $o->id)->update(['total' => 250000]);
        $this->artisan('jersey:koreksi-pembukuan', ['--terapkan' => true])->assertSuccessful();
        $this->assertEquals(250000, $this->saldo());

        \App\Services\JerseyAccountingService::voidPembayaran((int) DB::table('jersey_order_payments')->value('id'), 'salah input');

        $this->assertEquals(0, $this->saldo());
        $this->assertEquals(0, round((float) DB::table('jurnal_umum')->where('no_referensi', 'like', 'JO-KMB/BYR-%')->sum(DB::raw('debet - kredit')), 2));
    }

    /** Akun kategori harus cocok dengan jenisnya: pengeluaran ke Beban/Modal, pemasukan ke Pendapatan/Modal. */
    public function test_akun_kategori_harus_sesuai_jenis(): void
    {
        foreach ([['Keluar', '1100'], ['Keluar', '4200'], ['Keluar', '5000'], ['Masuk', '5100'], ['Masuk', '4000'], ['Masuk', '4300'], ['Masuk', '2100'], ['Keluar', '1010']] as [$jenis, $akun]) {
            $this->post('/kategori-kas-kecil', ['nama_kategori' => "Uji {$jenis} {$akun}", 'jenis' => $jenis, 'kode_akun' => $akun])
                ->assertSessionHasErrors('kode_akun');
        }
        $this->assertSame(0, DB::table('kategori_kas_kecil')->where('nama_kategori', 'like', 'Uji %')->count());

        foreach ([['Keluar', '5100'], ['Keluar', '3000'], ['Masuk', '4200'], ['Masuk', '3000']] as [$jenis, $akun]) {
            $this->post('/kategori-kas-kecil', ['nama_kategori' => "Sah {$jenis} {$akun}", 'jenis' => $jenis, 'kode_akun' => $akun])
                ->assertSessionHasNoErrors();
        }
        $this->assertSame(4, DB::table('kategori_kas_kecil')->where('nama_kategori', 'like', 'Sah %')->count());
    }

    public function test_kategori_tanpa_akun_tidak_bisa_dipakai_dan_tidak_bisa_dibuat(): void
    {
        $this->post('/kas-kecil/saldo-awal', ['tanggal' => now()->toDateString(), 'nominal' => 100000]);
        $id = DB::table('kategori_kas_kecil')->insertGetId(['nama_kategori' => 'Tanpa Akun', 'jenis' => 'Keluar', 'kode_akun' => null]);

        $this->post('/kas-kecil', ['tanggal' => now()->toDateString(), 'jenis' => 'Keluar', 'id_kategori' => $id, 'keterangan' => 'x', 'nominal' => 50000])
            ->assertSessionHas('error');
        $this->assertSame(0, DB::table('kas_kecil')->where('jenis', 'Keluar')->count(), 'pengeluaran tanpa jurnal lolos melewati penjaga saldo');
        $this->assertEquals(100000, $this->saldo());

        $this->post('/kategori-kas-kecil', ['nama_kategori' => 'Baru', 'jenis' => 'Keluar'])->assertSessionHasErrors('kode_akun');
    }

    /**
     * Penjaga saldo harus melihat pengeluaran yang di-commit transaksi lain SETELAH transaksi ini membaca sesuatu.
     * Disimulasikan pasti (bukan bergantung waktu): baca biasa dulu (potret MySQL terbentuk), lalu koneksi kedua
     * mencatat pengeluaran Rp90.000 dan commit, baru penjaga dijalankan.
     */
    public function test_penjaga_saldo_melihat_pengeluaran_yang_baru_di_commit_transaksi_lain(): void
    {
        // Saldo awal ditulis langsung (bukan lewat halaman, yang mengunci baris akun 1005 sampai tes selesai dan membuat
        // koneksi kedua menunggu kunci itu).
        DB::table('jurnal_umum')->insert([
            ['tgl_jurnal' => now()->toDateString(), 'no_referensi' => 'UJI-SALDO', 'keterangan' => 'saldo', 'kode_akun' => '1005', 'debet' => 100000, 'kredit' => 0],
            ['tgl_jurnal' => now()->toDateString(), 'no_referensi' => 'UJI-SALDO', 'keterangan' => 'saldo', 'kode_akun' => '3000', 'debet' => 0, 'kredit' => 100000],
        ]);
        config(['database.connections.mysql_kedua' => config('database.connections.mysql')]);
        $kedua = DB::connection('mysql_kedua');

        DB::table('jurnal_umum')->where('kode_akun', '1005')->sum('debet'); // potret terbentuk di sini
        $kedua->table('jurnal_umum')->insert(['tgl_jurnal' => now()->toDateString(), 'no_referensi' => 'UJI-KONKUREN', 'keterangan' => 'pengeluaran dari transaksi lain',
            'kode_akun' => '1005', 'debet' => 0, 'kredit' => 90000, 'created_at' => now(), 'updated_at' => now()]);

        try {
            \App\Services\KasKecilService::pastikanSaldoCukup(50000);
            $this->fail('pengeluaran Rp50.000 lolos padahal saldo tinggal Rp10.000 (penjaga membaca potret lama)');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('tidak cukup', $e->getMessage());
        }
    }
}
