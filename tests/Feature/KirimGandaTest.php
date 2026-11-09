<?php

namespace Tests\Feature;

use App\Models\JerseyOrder;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DOUBLE SUBMISSION / IDEMPOTENCY: permintaan yang sama dikirim dua kali dengan submit_id yang sama (klik ganda,
 * coba ulang setelah jaringan putus, dua tab) hanya boleh menghasilkan SATU transaksi, satu jurnal, satu pergerakan.
 * submit_id yang berbeda = dua transaksi sah.
 */
class KirimGandaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $kons;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($this->admin);
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail']);
        $this->kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 1000000, 'status_lock' => 'open', 'created_at' => now()->subMinute()]);
    }

    private function jejak(): array
    {
        return collect(['transaksi', 'kartu_stok', 'konsumen_deposit', 'kas_kecil', 'kas_manual', 'jersey_order_payments', 'jasa_jahit_catat', 'waste_log', 'jurnal_umum'])
            ->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
    }

    /** Kirim dua kali dengan submit_id sama; jejak setelah kiriman kedua harus sama dengan setelah kiriman pertama. */
    private function kirimDuaKali(string $uri, array $data, string $tabelUtama): void
    {
        $data['submit_id'] = 'uji-' . uniqid();
        $awal = $this->jejak();
        $this->post($uri, $data);
        $sekali = $this->jejak();
        $this->assertSame($awal[$tabelUtama] + 1, $sekali[$tabelUtama], "kiriman pertama ke {$uri} tidak tersimpan");

        $this->post($uri, $data)->assertRedirect();
        $this->assertSame($sekali, $this->jejak(), "kiriman ulang ke {$uri} menggandakan transaksi");

        // submit_id berbeda = transaksi baru yang sah.
        $data['submit_id'] = 'uji-' . uniqid();
        $this->post($uri, $data);
        $this->assertSame($sekali[$tabelUtama] + 1, DB::table($tabelUtama)->count(), "submit_id baru ke {$uri} ditolak");
    }

    private function bahan(): int
    {
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'x']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K', 'kode_kategori' => 'K']);

        return DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'B', 'nama_bahan' => 'Flexi', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => 100, 'nilai_konversi' => 1, 'harga_rata_rata' => 1000, 'satuan_stok' => 'm2', 'status' => 'aktif']);
    }

    public function test_penjualan_dan_jasa(): void
    {
        $this->kirimDuaKali('/penjualan-barang/store', ['id_konsumen' => $this->kons, 'items' => [['tipe' => 'bahan', 'id_bahan' => $this->bahan(), 'qty' => 3, 'harga' => 5000]]], 'transaksi');
    }

    public function test_setoran_deposit(): void
    {
        $this->kirimDuaKali('/konsumen-deposit', ['id_konsumen' => $this->kons, 'nominal' => 50000, 'metode_bayar' => 'Tunai', 'tgl_deposit' => now()->toDateString()], 'konsumen_deposit');
    }

    public function test_pengeluaran_kas_kecil(): void
    {
        AccountingService::postKasKecilMasuk('KK-SALDO', '3000', 500000);
        $kat = DB::table('kategori_kas_kecil')->insertGetId(['nama_kategori' => 'ATK', 'jenis' => 'Keluar', 'kode_akun' => '5160']);
        $this->kirimDuaKali('/kas-kecil', ['tanggal' => now()->toDateString(), 'jenis' => 'Keluar', 'id_kategori' => $kat, 'keterangan' => 'Kertas', 'nominal' => 25000], 'kas_kecil');
    }

    public function test_pembayaran_order_jersey(): void
    {
        $o = JerseyOrder::create(['order_number' => 'JO-GANDA', 'order_date' => now()->toDateString(), 'customer_name' => 'Tim', 'additional_charge' => 300000]);
        $o->recalculateTotal();
        $this->kirimDuaKali("/jersey/keuangan/{$o->id}/bayar", ['tanggal_bayar' => now()->toDateString(), 'jumlah' => 50000, 'metode_bayar' => 'Transfer'], 'jersey_order_payments');
    }

    public function test_kas_tidak_terduga(): void
    {
        $this->kirimDuaKali('/kasir/kas-manual', ['keterangan' => 'Beli galon', 'status' => 'Keluar', 'nominal' => 20000], 'kas_manual');
    }

    public function test_waste(): void
    {
        $this->kirimDuaKali('/waste', ['id_bahan' => $this->bahan(), 'qty' => 2, 'jenis' => 'Normal', 'alasan' => 'Sisa'], 'waste_log');
    }

    public function test_catat_jahit(): void
    {
        $pj = DB::table('penjahit')->insertGetId(['nama' => 'Bu Sri', 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
        $tarif = DB::table('jasa_jahit')->insertGetId(['nama_jahit' => 'Keliling', 'harga_borongan' => 2500, 'created_at' => now(), 'updated_at' => now()]);
        $this->kirimDuaKali('/jasa-jahit/catat', ['id_penjahit' => $pj, 'id_jasa_jahit' => $tarif, 'qty' => 2, 'tgl_catat' => now()->toDateString()], 'jasa_jahit_catat');
    }
}
