<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\JerseyOrder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KasKecilBukuKasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->actingAs(User::factory()->create(['id_role' => $role->id_role]));
    }

    private function saldo(string $kode): float
    {
        return (float) DB::table('jurnal_umum')->where('kode_akun', $kode)->sum(DB::raw('debet - kredit'));
    }

    public function test_pemasukan_manual_dijurnal_dr_kas_cr_akun_kategori_dan_tampil_di_halaman(): void
    {
        $kat = DB::table('kategori_kas_kecil')->where('nama_kategori', 'Pendapatan Lain-lain')->first();
        $this->assertSame('Masuk', $kat->jenis);

        $this->post('/kas-kecil', [
            'tanggal' => now()->toDateString(), 'jenis' => 'Masuk', 'id_kategori' => $kat->id_kategori_kas_kecil,
            'keterangan' => 'Jual kardus bekas', 'nominal' => 75000,
        ])->assertSessionHas('success');

        $this->assertEquals(75000, $this->saldo('1005'));
        $this->assertEquals(0, $this->saldo('1000'), 'laci tidak tersentuh');
        $this->assertEquals(-75000, $this->saldo('4200'));
        $this->get('/kas-kecil')->assertOk()->assertSee('Jual kardus bekas')->assertSee('Pemasukan Tunai');
    }

    public function test_jenis_harus_cocok_dengan_kategori(): void
    {
        $kat = DB::table('kategori_kas_kecil')->where('nama_kategori', 'Pendapatan Lain-lain')->first();

        $this->post('/kas-kecil', [
            'tanggal' => now()->toDateString(), 'jenis' => 'Keluar', 'id_kategori' => $kat->id_kategori_kas_kecil,
            'keterangan' => 'Salah jenis', 'nominal' => 1000,
        ])->assertSessionHasErrors('id_kategori');
        $this->assertSame(0, DB::table('kas_kecil')->count());
    }

    public function test_pembayaran_jersey_tercermin_di_kas_kecil_tidak_dijurnal_ganda_dan_hilang_saat_void(): void
    {
        $o = JerseyOrder::create(['order_number' => 'JO-T1', 'order_date' => now()->toDateString(), 'customer_name' => 'Tim', 'additional_charge' => 500000]);
        $o->recalculateTotal();

        $this->post("/jersey/keuangan/{$o->id}/bayar", ['tanggal_bayar' => now()->toDateString(), 'jumlah' => 200000, 'metode_bayar' => 'Transfer']);

        $baris = DB::table('kas_kecil')->where('sumber', 'jersey')->first();
        $this->assertSame('Masuk', $baris->jenis);
        $this->assertSame('Transfer', $baris->metode);
        $this->assertEquals(200000, (float) $baris->nominal);
        $this->assertEquals(200000, $this->saldo('1010'), 'jurnal hanya dari pembayaran jersey (Bank), tidak dobel');
        $this->assertEquals(0, $this->saldo('1005'));

        // Tidak bisa dibatalkan dari Kas Kecil.
        $this->delete("/kas-kecil/{$baris->id}")->assertSessionHas('error');
        $this->assertSame(0, ApprovalRequest::where('tipe_aksi', 'batalkan_kas_kecil')->count());

        // Void pembayaran jersey -> baris cermin ikut hilang.
        $idBayar = DB::table('jersey_order_payments')->value('id');
        \App\Services\JerseyAccountingService::voidPembayaran($idBayar, 'salah input');
        $this->assertSame(0, DB::table('kas_kecil')->where('sumber', 'jersey')->count());
    }

    public function test_kas_kecil_terpisah_dari_laci(): void
    {
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => auth()->id(), 'saldo_awal' => 500000, 'status_lock' => 'open', 'created_at' => now()->subMinute(), 'updated_at' => now()]);

        $this->assertFalse(DB::table('kategori_kas_kecil')->where('kode_akun', '1000')->exists(), 'kategori "Isi dari Kas Laci" sudah dihapus');
        $laci = DB::table('kategori_kas_kecil')->insertGetId(['nama_kategori' => 'Coba dari laci', 'jenis' => 'Masuk', 'kode_akun' => '1000']);
        $this->post('/kas-kecil', ['tanggal' => now()->toDateString(), 'jenis' => 'Masuk', 'id_kategori' => $laci, 'keterangan' => 'Modal', 'nominal' => 100000])
            ->assertSessionHas('error');
        $this->assertSame(0, DB::table('kas_kecil')->count());
        $this->assertSame(0, DB::table('kas_manual')->count());

        // Kategori baru juga tidak bisa dipetakan ke akun laci atau Kas Kecil itu sendiri.
        $this->post('/kategori-kas-kecil', ['nama_kategori' => 'Isi laci lagi', 'jenis' => 'Masuk', 'kode_akun' => '1000'])->assertSessionHasErrors('kode_akun');
    }

    public function test_uang_keluar_dari_laci_tidak_boleh_melebihi_isi_laci(): void
    {
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => auth()->id(), 'saldo_awal' => 100000, 'status_lock' => 'open', 'created_at' => now()->subMinute(), 'updated_at' => now()]);

        $this->post('/kasir/kas-manual', ['keterangan' => 'Beli galon', 'status' => 'Keluar', 'nominal' => 150000])->assertSessionHas('error');
        $this->assertSame(0, DB::table('kas_manual')->count());
        $this->post('/kasir/kas-manual', ['keterangan' => 'Beli galon', 'status' => 'Keluar', 'nominal' => 100000])->assertSessionHas('success');
    }

    public function test_saldo_awal_kas_kecil_sekali_oleh_administrator(): void
    {
        $this->post('/kas-kecil/saldo-awal', ['tanggal' => now()->toDateString(), 'nominal' => 10000000])->assertSessionHas('success');

        $this->assertEquals(10000000, $this->saldo('1005'));
        $this->assertEquals(-10000000, $this->saldo('3000'), 'lawannya Modal');
        $this->assertSame('saldo_awal', DB::table('kas_kecil')->value('sumber'));

        // Kedua kalinya ditolak.
        $this->post('/kas-kecil/saldo-awal', ['tanggal' => now()->toDateString(), 'nominal' => 5000000])->assertSessionHas('error');
        $this->assertEquals(10000000, $this->saldo('1005'));

        // Belanja ATK berkurang dari saldo itu.
        $atk = DB::table('kategori_kas_kecil')->where('jenis', 'Keluar')->where('kode_akun', '5170')->value('id_kategori_kas_kecil')
            ?? DB::table('kategori_kas_kecil')->insertGetId(['nama_kategori' => 'ATK', 'jenis' => 'Keluar', 'kode_akun' => '5170']);
        $this->post('/kas-kecil', ['tanggal' => now()->toDateString(), 'jenis' => 'Keluar', 'id_kategori' => $atk, 'keterangan' => 'Kertas HVS', 'nominal' => 250000])
            ->assertSessionHas('success');
        $this->assertEquals(9750000, $this->saldo('1005'));
        $this->get('/kas-kecil')->assertOk()->assertSee('Saldo Kas Kecil Saat Ini')->assertSee('9.750.000')->assertDontSee('Saldo awal Kas Kecil belum ditetapkan');
    }

    public function test_saldo_awal_hanya_administrator(): void
    {
        $kasir = \App\Models\User::factory()->create(['id_role' => \App\Models\Role::factory()->create(['nama_role' => 'Kasir'])->id_role]);

        $this->actingAs($kasir)->post('/kas-kecil/saldo-awal', ['tanggal' => now()->toDateString(), 'nominal' => 10000000])->assertForbidden();
        $this->assertSame(0, DB::table('kas_kecil')->count());
    }
}
