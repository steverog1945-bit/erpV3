<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// N+1 audit: halaman daftar Kasir, Riwayat Penjualan, dan Produksi harus
// menjalankan jumlah query yang TIDAK bertambah mengikuti jumlah baris data.
//
// Konteks penting (hasil inspect, bukan asumsi):
//   - Ketiga controller memakai DB::table + JOIN dalam SATU query utama -
//     tidak ada Eloquent, jadi tidak ada with() yang bisa/ perlu ditambahkan.
//   - Blade tidak memanggil DB di dalam loop (@foreach hanya di dropdown).
//   - Layout sidebar menjalankan query badge notifikasi per modul - itu
//     overhead TETAP per halaman (ada yang di-cache), bukan N+1.
//
// Karena overhead layout ikut terhitung, test ini TIDAK memakai ambang absolut
// yang rapuh. Yang diuji: jumlah query SAMA PERSIS pada volume data kecil dan
// besar (setelah satu request pemanasan supaya cache layout sudah hangat).
// Kalau ada query per baris, 10x data langsung menambah query dan test gagal.
class QueryCountTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idKonsumen;
    private int $idProduk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);

        $gol = DB::table('golongan_konsumen')->insertGetId([
            'nama_golongan' => 'Umum', 'keterangan' => '-',
        ]);
        $this->idKonsumen = (int) DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Pelanggan Uji', 'telepon' => '08123', 'alamat' => '-',
            'id_golongan' => $gol,
        ]);

        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin Uji', 'tipe_mesin' => 'Uji']);
        $this->idProduk = (int) DB::table('produk')->insertGetId([
            'nama_produk' => 'Produk Uji', 'barcode' => 'PUJI',
            'metode_hitung' => 'quantity', 'min_order' => 0,
            'min_panjang' => 0, 'min_lebar' => 0, 'status' => 'aktif',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('produk')->where('id_produk', $this->idProduk)->update(['id_mesin' => $mesin]);
    }

    private function seedTransaksi(int $nota, int $itemPerNota, string $prefix): void
    {
        for ($n = 1; $n <= $nota; $n++) {
            $id = DB::table('transaksi')->insertGetId([
                'nomor_nota' => "NOTA-{$prefix}-{$n}",
                'id_konsumen' => $this->idKonsumen,
                'id_user_kasir' => $this->admin->id_user,
                'total_bayar' => 100000,
                'status_pembayaran' => 'Belum Bayar',
                'status_nota' => 'Aktif',
                'status_collecting' => 'Belum Diambil',
                'tgl_order' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            for ($i = 1; $i <= $itemPerNota; $i++) {
                DB::table('transaksi_detail')->insert([
                    'id_transaksi' => $id,
                    'id_produk' => $this->idProduk,
                    'nama_file' => "uji-{$prefix}-{$n}-{$i}",
                    'p_ukuran' => 10,
                    'l_ukuran' => 10,
                    'qty' => 1,
                    'status_spk' => 'Desain',
                    'subtotal' => 10000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function hitungQuery(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get($url)->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    /**
     * @dataProvider daftarHalaman
     */
    public function test_jumlah_query_tidak_bertambah_mengikuti_data(string $url, string $label): void
    {
        // Volume kecil: 3 nota @ 2 item.
        $this->seedTransaksi(3, 2, 'KECIL');

        // Pemanasan: request pertama menghangatkan cache layout (badge
        // notifikasi sidebar), supaya pengukuran tidak tercampur cache-miss.
        $this->actingAs($this->admin)->get($url)->assertOk();
        $kecil = $this->hitungQuery($url);

        // Volume besar: tambah 27 nota @ 3 item (total 30 nota, 87 item).
        // Kalau ada query per baris, hitungan ini langsung melonjak.
        $this->seedTransaksi(27, 3, 'BESAR');
        $besar = $this->hitungQuery($url);

        $this->assertSame(
            $kecil,
            $besar,
            "{$label}: query naik dari {$kecil} menjadi {$besar} saat data 10x lipat - ada query per baris (N+1)"
        );

        // Pagar kewarasan: total query satu halaman daftar tidak boleh
        // meledak walau datanya banyak.
        $this->assertLessThanOrEqual(
            60,
            $besar,
            "{$label}: {$besar} query untuk satu halaman daftar terlalu banyak"
        );
    }

    public static function daftarHalaman(): array
    {
        return [
            'monitoring kasir' => ['/kasir/monitoring', 'Monitoring kasir'],
            'riwayat penjualan' => ['/penjualan/riwayat', 'Riwayat penjualan'],
            'produksi' => ['/produksi', 'Produksi'],
        ];
    }
}