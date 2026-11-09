<?php

namespace Tests\Feature;

use App\Models\JerseyOrder;
use App\Models\Role;
use App\Models\User;
use App\Services\JerseyStokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JerseyPotongStokTest extends TestCase
{
    use RefreshDatabase;

    private int $idBahan;
    private int $idOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]));

        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'M', 'tipe_mesin' => 'x']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K']);
        $this->idBahan = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'KB', 'nama_bahan' => 'Kain Brazil Uji', 'id_mesin' => $mesin, 'id_kategori' => $kat, 'stok' => 25.35, 'harga_rata_rata' => 58000, 'nilai_konversi' => 1, 'satuan_stok' => 'kg', 'status' => 'aktif']);
        $mat = DB::table('jersey_materials')->insertGetId(['name' => 'Brazil Uji', 'is_active' => 1, 'id_bahan' => $this->idBahan, 'created_at' => now(), 'updated_at' => now()]);

        $this->idOrder = DB::table('jersey_orders')->insertGetId(['order_number' => 'JRS-UJI-1', 'order_date' => now()->toDateString(), 'customer_name' => 'Tim Uji', 'status' => 'baru', 'total' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $line = DB::table('jersey_order_lines')->insertGetId(['jersey_order_id' => $this->idOrder, 'jersey_material_id' => $mat, 'nama_barang' => 'Jersey', 'thumbnail' => 'x.jpg', 'qty' => 10, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('jersey_order_line_materials')->insert(['jersey_order_line_id' => $line, 'nama_barang' => 'Body', 'penggunaan_kg' => 4.5, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('jersey_order_line_materials')->insert(['jersey_order_line_id' => $line, 'nama_barang' => 'Lengan', 'penggunaan_kg' => 1.5, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function stok(): float
    {
        return (float) DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->value('stok');
    }

    private function saldoHpp(): float
    {
        return (float) DB::table('jurnal_umum')->where('kode_akun', '5000')->sum(DB::raw('debet - kredit'));
    }

    public function test_status_proses_memotong_kain_dan_menjurnal_hpp_lalu_kembali_ke_baru_mengembalikan(): void
    {
        $order = JerseyOrder::find($this->idOrder);

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'proses']);
            JerseyStokService::selaraskan($order);
        });
        $this->assertEqualsWithDelta(25.35 - 6.0, $this->stok(), 0.0001);          // 4,5 + 1,5 kg
        $this->assertEqualsWithDelta(6.0 * 58000, $this->saldoHpp(), 0.01);        // 348.000
        $this->assertNotNull(DB::table('jersey_orders')->where('id', $this->idOrder)->value('stok_dipotong_at'));

        // Proses kedua kali tidak memotong dobel.
        DB::transaction(fn () => JerseyStokService::selaraskan($order->fresh()));
        $this->assertEqualsWithDelta(19.35, $this->stok(), 0.0001);

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'baru']);
            JerseyStokService::selaraskan($order);
        });
        $this->assertEqualsWithDelta(25.35, $this->stok(), 0.0001);
        $this->assertEqualsWithDelta(0.0, $this->saldoHpp(), 0.01);
        $this->assertNull(DB::table('jersey_orders')->where('id', $this->idOrder)->value('stok_dipotong_at'));
    }

    public function test_perubahan_barang_setelah_dipotong_menyesuaikan_potongan(): void
    {
        $order = JerseyOrder::find($this->idOrder);
        DB::transaction(function () use ($order) {
            $order->update(['status' => 'proses']);
            JerseyStokService::selaraskan($order);
        });

        DB::table('jersey_order_line_materials')->where('nama_barang', 'Body')->update(['penggunaan_kg' => 8.0]); // estimasi berubah: total 9,5 kg
        DB::transaction(fn () => JerseyStokService::segarkan($order));

        $this->assertEqualsWithDelta(25.35 - 9.5, $this->stok(), 0.0001);
        $this->assertEqualsWithDelta(9.5 * 58000, $this->saldoHpp(), 0.01);
    }

    public function test_stok_boleh_minus_dengan_peringatan_dan_bahan_tanpa_sinkron_diabaikan(): void
    {
        DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->update(['stok' => 2]);
        $order = JerseyOrder::find($this->idOrder);
        $order->update(['status' => 'proses']);
        $peringatan = DB::transaction(fn () => JerseyStokService::selaraskan($order));
        $this->assertNotEmpty($peringatan);
        $this->assertEqualsWithDelta(-4.0, $this->stok(), 0.0001);

        // Bahan jersey tanpa sinkron: tidak ada potongan.
        DB::table('jersey_materials')->update(['id_bahan' => null]);
        $this->assertSame([], JerseyStokService::kebutuhan($this->idOrder));
    }

    public function test_pengembalian_memakai_catatan_potongan_asli_walau_estimasi_material_berubah_setelahnya(): void
    {
        $order = JerseyOrder::find($this->idOrder);
        DB::transaction(function () use ($order) {
            $order->update(['status' => 'proses']);
            JerseyStokService::selaraskan($order);
        });
        $this->assertEqualsWithDelta(19.35, $this->stok(), 0.0001);

        // Estimasi kain berubah SETELAH dipotong (mis. edit material): 6,0 kg -> 9,0 kg.
        DB::table('jersey_order_line_materials')->update(['penggunaan_kg' => 4.5]);
        DB::table('bahan_baku')->where('id_bahan', $this->idBahan)->update(['harga_rata_rata' => 99000]); // harga juga berubah

        DB::transaction(fn () => JerseyStokService::kembalikan($order->fresh()));

        // Kembali persis yang dipotong dulu (6,0 kg), bukan 9,0 kg hasil estimasi baru.
        $this->assertEqualsWithDelta(25.35, $this->stok(), 0.0001);
        $this->assertEqualsWithDelta(0.0, $this->saldoHpp(), 0.01, 'HPP dibalik sebesar biaya asli (6 kg x 58.000), bukan harga baru');
    }

    public function test_dua_pengembalian_berturutan_dari_objek_basi_tidak_menambah_stok_dua_kali(): void
    {
        $order = JerseyOrder::find($this->idOrder);
        DB::transaction(function () use ($order) {
            $order->update(['status' => 'proses']);
            JerseyStokService::selaraskan($order);
        });

        // Dua pemanggil memegang objek order yang SAMA-SAMA basi (masih menganggap stok terpotong).
        $a = JerseyOrder::find($this->idOrder);
        $b = JerseyOrder::find($this->idOrder);
        DB::transaction(fn () => JerseyStokService::kembalikan($a));
        DB::transaction(fn () => JerseyStokService::kembalikan($b));

        $this->assertEqualsWithDelta(25.35, $this->stok(), 0.0001, 'Stok tidak boleh melebihi awal');
        $this->assertEquals(1, DB::table('kartu_stok')->where('tipe', 'IN')->where('id_bahan', $this->idBahan)->count(), 'Satu bahan -> tepat satu baris IN pengembalian');
    }
}
