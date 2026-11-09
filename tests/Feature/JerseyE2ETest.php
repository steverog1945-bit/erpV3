<?php

namespace Tests\Feature;

use App\Models\JerseyOrder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Uji E2E alur order jersey: master data → create order → hitung total → bayar → jurnal.
 *
 * Yang dibuktikan di sini adalah RANTAINYA utuh:
 *   Master data minimal → POST /jersey/store → order_number dibuat server
 *   → total dihitung server dari tier harga + surcharge → status pembayaran
 *   → POST /jersey/keuangan/{order}/bayar → jurnal dibuat dengan akun bener
 *   (Tunai → Kas Kecil 1005, bukan Kas 1000) → status berubah ke LUNAS.
 *
 * Angka yang dipakai:
 *   Tier harga: 50.000 (id 1), qty 10 → 500.000
 *   Diskon 50.000, additional_charge 10.000
 *   Grand total = 500.000 - 50.000 + 10.000 = 460.000
 *   Bayar tunai 460.000 → jurnal 1005/1100, status berubah ke LUNAS.
 */
class JerseyE2ETest extends TestCase
{
    use RefreshDatabase;

    private const TIER_HARGA = 50000.0;
    private const QTY = 10;
    private const DISKON = 50000.0;
    private const ADDITIONAL = 10000.0;

    private int $idKonsumen;
    private int $idGolongan;
    private int $idProduk;
    private int $idKategori;
    private int $idBahan;
    private int $idLengan;
    private int $idKerah;
    private array $sizeIds = [];
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();

        // Helper seperti JerseyOrderHttpTest::setUp()
        $ambil = function (string $tabel, string $nama, array $tambahan = []) use ($now): int {
            $id = DB::table($tabel)->where('name', $nama)->value('id');
            return (int) ($id ?: DB::table($tabel)->insertGetId(array_merge(
                ['name' => $nama, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now], $tambahan
            )));
        };

        // Role admin punya akses penuh ke jersey (menu 38 + 42)
        $role = Role::factory()->create(['nama_role' => 'Admin Uji Jersey']);
        DB::table('roles')->where('id_role', $role->id_role)->update(['boleh_lihat_harga' => 1]);
        foreach ([38, 42] as $id) {
            DB::table('menus')->insertOrIgnore([
                'id_menu' => $id, 'nama_menu' => "M{$id}", 'url_link' => "m{$id}", 'parent_id' => 0,
            ]);
            DB::table('role_permissions')->insertOrIgnore(['id_role' => $role->id_role, 'id_menu' => $id]);
        }
        $this->admin = User::factory()->create(['id_role' => $role->id_role]);

        $this->idGolongan = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail Uji', 'keterangan' => '-']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Konsumen Jersey', 'telepon' => '08123456789', 'alamat' => '-', 'id_golongan' => $this->idGolongan,
        ]);

        // Master jersey minimal (baris 39-58 di JerseyOrderHttpTest::setUp)
        $this->idProduk = $ambil('jersey_product_types', 'Jersey Uji', ['has_sleeve_collar' => 1]);
        $this->idKategori = $ambil('jersey_categories', 'Kategori Uji');
        // id_bahan wajib untuk HPP; buat bahan_baku dulu
        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin Jersey', 'tipe_mesin' => 'Digital']);
        $idKat = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kategori Jersey']);
        $idBahanBaku = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'JRS-01', 'nama_bahan' => 'Kain Jersey', 'id_mesin' => $idMesin,
            'id_kategori' => $idKat, 'stok' => 1000, 'stok_minimal' => 0,
            'nilai_konversi' => 1, 'harga_rata_rata' => 50000, 'satuan_stok' => 'm', 'status' => 'aktif',
        ]);
        $this->idBahan = $ambil('jersey_materials', 'Kain Uji', ['id_bahan' => $idBahanBaku]);
        $this->idLengan = $ambil('jersey_sleeve_types', 'Pendek', ['price_surcharge' => 0, 'sort_order' => 1]);
        $this->idKerah = $ambil('jersey_collar_types', 'O-Neck', ['price_surcharge' => 0, 'sort_order' => 1]);

        // Semua ukuran harus AKTIF (validasi items.*.size mengecek jersey_sizes.is_active)
        foreach (['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'] as $i => $u) {
            $id = DB::table('jersey_sizes')->updateOrInsert(
                ['name' => $u],
                ['sort_order' => $i + 1, 'is_active' => 1, 'price_surcharge' => 0,
                 'kelompok' => 'dewasa', 'price_percent' => 0,
                 'created_at' => $now, 'updated_at' => $now]
            );
            $this->sizeIds[$u] = DB::table('jersey_sizes')->where('name', $u)->value('id');
        }

        // Designer
        $idDesainer = $ambil('jersey_designers', 'Desainer Uji');

        // Tier harga: 50.000 per pcs (tanpa min qty, tanpa diskon tier)
        // Kolom aktual: qty_min, qty_max, price
        DB::table('jersey_price_settings')->insert([
            'jersey_product_type_id' => $this->idProduk,
            'jersey_category_id' => $this->idKategori,
            'jersey_material_id' => $this->idBahan,
            'qty_min' => 1,
            'qty_max' => 9999,
            'price' => self::TIER_HARGA,
            'is_active' => 1,
            'kelompok' => 'dewasa',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('kas_harian')->insert([
            'tgl_kas' => now()->toDateString(),
            'id_user' => $this->admin->id_user,
            'saldo_awal' => 0,
            'status_lock' => 'open',
        ]);
    }

    private function payload(array $lines, array $headerUbah = []): array
    {
        return array_merge([
            'order_date' => now()->toDateString(),
            'id_konsumen' => $this->idKonsumen,
            'team_name' => 'Tim Uji FC',
            'lines' => $lines,
        ], $headerUbah);
    }

    private function linePlayerList(): array
    {
        return [
            'nama_barang' => 'Jersey Home',
            'jersey_product_type_id' => $this->idProduk,
            'jersey_category_id' => $this->idKategori,
            'jersey_material_id' => $this->idBahan,
            'jersey_sleeve_type_id' => $this->idLengan,
            'jersey_collar_type_id' => $this->idKerah,
            'thumbnail' => 'jersey_thumbnails/uji.jpg',
            'order_type' => 'player_list',
            'items' => [
                ['player_name' => 'Pemain 1', 'player_number' => '1', 'size' => 'M', 'gender' => 'L',
                 'jersey_sleeve_type_id' => $this->idLengan, 'jersey_collar_type_id' => $this->idKerah],
                ['player_name' => 'Pemain 2', 'player_number' => '2', 'size' => 'L', 'gender' => 'L',
                 'jersey_sleeve_type_id' => $this->idLengan, 'jersey_collar_type_id' => $this->idKerah],
            ],
            'size_quantities' => [],
        ];
    }

    public function test_create_order_menghasilkan_order_number_dan_total_dihitung_server(): void
    {
        $res = $this->actingAs($this->admin)->post('/jersey/store', $this->payload([
            $this->linePlayerList(),
        ], [
            'discount' => self::DISKON,
            'additional_charge' => self::ADDITIONAL,
        ]));

        // store() mengembalikan redirect ke jersey.orders.show
        $res->assertRedirect();
        $res->assertSessionHas('success');

        $order = JerseyOrder::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertStringStartsWith('JO-', $order->order_number);
        $qtyLines = (int) $order->lines()->sum('qty');
        $this->assertEquals(2, $qtyLines); // 2 pemain

        // Grand total = (harga * qty) - discount + additional_charge
        // harga 50.000 x 2 pcs = 100.000 - 50.000 + 10.000 = 60.000
        $totalHarapan = (self::TIER_HARGA * 2) - self::DISKON + self::ADDITIONAL;
        $this->assertEqualsWithDelta($totalHarapan, (float) $order->total, 0.01);

        // Server isi otomatis: customer_name, designer_name, order_number, status_pembayaran
        $this->assertNotNull($order->order_number);
        $this->assertStringStartsWith('JO-', $order->order_number);
        $this->assertEquals('Belum Bayar', $order->status_pembayaran);
        $this->assertEquals('Konsumen Jersey', $order->customer_name);

        // Jurnal order creation: 2 baris (Piutang 1100 / Pendapatan Jersey 4300)
        $jurnalOrder = DB::table('jurnal_umum')
            ->where('no_referensi', $order->order_number)
            ->get();
        $this->assertCount(2, $jurnalOrder);
        $this->assertEquals('4300', $jurnalOrder->where('kode_akun', '4300')->first()->kode_akun);
    }

    public function test_bayar_tunai_masukan_ke_kas_kecil_dan_status_lunas(): void
    {
        $res = $this->actingAs($this->admin)->post('/jersey/store', $this->payload([
            $this->linePlayerList(),
        ], [
            'discount' => self::DISKON,
            'additional_charge' => self::ADDITIONAL,
        ]));
        $res->assertRedirect();

        $order = JerseyOrder::latest('id')->first();
        $total = (float) $order->total;

        // Bayar tunai penuh
        $bayar = $this->actingAs($this->admin)->post("/jersey/keuangan/{$order->id}/bayar", [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah' => $total,
            'metode_bayar' => 'Tunai',
        ]);
        $bayar->assertRedirect();
        $bayar->assertSessionHas('success');

        // Cek status order berubah
        $order->refresh();
        $this->assertEquals('Lunas', $order->status_pembayaran);

        // Jurnal pembayaran: pakai no_referensi dengan suffix /BYR-{id}
        $payment1 = \App\Models\JerseyOrderPayment::where('jersey_order_id', $order->id)->latest('id')->first();
        $jurnal = DB::table('jurnal_umum')
            ->where('no_referensi', $order->order_number . '/BYR-' . $payment1->id)
            ->get();
        $this->assertCount(2, $jurnal);

        $debet = $jurnal->where('debet', '>', 0)->first();
        $kredit = $jurnal->where('kredit', '>', 0)->first();

        $this->assertEquals('1005', $debet->kode_akun, 'Tunai jersey harus masuk ke Kas Kecil (1005)');
        $this->assertEquals('1100', $kredit->kode_akun, 'Piutang Usaha (1100) harus dikreditkan');
        $this->assertEqualsWithDelta($total, (float) $debet->debet, 0.01);
        $this->assertEqualsWithDelta($total, (float) $kredit->kredit, 0.01);

        // Kas kecil dicatat
        $kasKecil = DB::table('kas_kecil')
            ->where('sumber', 'jersey')
            ->where('jenis', 'Masuk')
            ->first();
        $this->assertNotNull($kasKecil, 'pembayaran jersey tunai harus tercatat di kas kecil');
        $this->assertEqualsWithDelta($total, (float) $kasKecil->nominal, 0.01);
    }

    public function test_bayar_parsial_mengubah_status_ke_piutang(): void
    {
        $res = $this->actingAs($this->admin)->post('/jersey/store', $this->payload([
            $this->linePlayerList(),
        ]));
        $res->assertRedirect();

        $order = JerseyOrder::latest('id')->first();
        $total = (float) $order->total;

        // Bayar 50% saja
        $bayar = $this->actingAs($this->admin)->post("/jersey/keuangan/{$order->id}/bayar", [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah' => $total / 2,
            'metode_bayar' => 'Transfer',
        ]);
        $bayar->assertRedirect();
        $bayar->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals('DP', $order->status_pembayaran);

        // Jurnal pakai BANK (1010) karena Transfer
        $payment2 = \App\Models\JerseyOrderPayment::where('jersey_order_id', $order->id)->latest('id')->first();
        $jurnal = DB::table('jurnal_umum')
            ->where('no_referensi', $order->order_number . '/BYR-' . $payment2->id)
            ->where('kode_akun', '1010')
            ->first();
        $this->assertNotNull($jurnal, 'Transfer harus pakai akun Bank (1010)');
        $this->assertEqualsWithDelta($total / 2, (float) $jurnal->debet, 0.01);
    }

    public function test_bayar_melebihi_sisa_ditolak(): void
    {
        $res = $this->actingAs($this->admin)->post('/jersey/store', $this->payload([
            $this->linePlayerList(),
        ]));
        $res->assertRedirect();

        $order = JerseyOrder::latest('id')->first();
        $total = (float) $order->total;

        // Bayar lebih dari total dengan TRANSFER (bukan tunai) - harus ditolak
        $bayar = $this->actingAs($this->admin)->post("/jersey/keuangan/{$order->id}/bayar", [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah' => $total + 10000,
            'metode_bayar' => 'Transfer',  // Non-cash: overpay ditolak
        ]);
        $bayar->assertRedirect();
        $bayar->assertSessionHas('error', function ($msg) {
            return is_string($msg) && str_contains($msg, 'melebihi');
        });
    }

    public function test_bayar_tunai_overpay_menghitung_kembalian_otomatis(): void
    {
        $res = $this->actingAs($this->admin)->post('/jersey/store', $this->payload([
            $this->linePlayerList(),
        ]));
        $res->assertRedirect();

        $order = JerseyOrder::latest('id')->first();
        $total = (float) $order->total;

        // Bayar tunai 500.000, total 60.000 → kembalian 440.000
        // Jumlah yang dicatat di jurnal = total (60.000), kembalian = 440.000
        $bayar = $this->actingAs($this->admin)->post("/jersey/keuangan/{$order->id}/bayar", [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah' => 500000,        // uang yang diserahkan
            'metode_bayar' => 'Tunai',
        ]);
        $bayar->assertRedirect();
        $bayar->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals('Lunas', $order->status_pembayaran);

        $pembayaran = DB::table('jersey_order_payments')
            ->where('jersey_order_id', $order->id)
            ->first();
        $this->assertNotNull($pembayaran);
        $this->assertEqualsWithDelta($total, (float) $pembayaran->jumlah, 0.01, 'jumlah dicatat = total tagihan');
        $this->assertEqualsWithDelta(500000 - $total, (float) $pembayaran->kembalian, 0.01, 'kembalian = uang diterima - total');
        $this->assertEqualsWithDelta(500000, (float) $pembayaran->uang_diterima, 0.01, 'uang_diterima = jumlah + kembalian');
    }

    public function test_bayar_tunai_dengan_uang_diterima_lebih_dari_sisa_mencatat_kembalian(): void
    {
        $res = $this->actingAs($this->admin)->post('/jersey/store', $this->payload([
            $this->linePlayerList(),
        ]));
        $res->assertRedirect();

        $order = JerseyOrder::latest('id')->first();
        $total = (float) $order->total;

        // Bayar tunai 500.000, total 60.000 → kembalian 440.000
        $bayar = $this->actingAs($this->admin)->post("/jersey/keuangan/{$order->id}/bayar", [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah' => $total,          // yang dicatat di jurnal
            'uang_diterima' => 500000,   // uang tunai fisik
            'metode_bayar' => 'Tunai',
        ]);
        $bayar->assertRedirect();
        $bayar->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals('Lunas', $order->status_pembayaran);

        $pembayaran = DB::table('jersey_order_payments')
            ->where('jersey_order_id', $order->id)
            ->first();
        $this->assertNotNull($pembayaran);
        $this->assertEqualsWithDelta($total, (float) $pembayaran->jumlah, 0.01);
        $this->assertEqualsWithDelta(500000 - $total, (float) $pembayaran->kembalian, 0.01);
        $this->assertEqualsWithDelta(500000, (float) $pembayaran->uang_diterima, 0.01);
    }

    public function test_tidak_ada_akun_1000_di_jurnal_jersey_tunai(): void
    {
        $res = $this->actingAs($this->admin)->post('/jersey/store', $this->payload([
            $this->linePlayerList(),
        ]));
        $res->assertRedirect();

        $order = JerseyOrder::latest('id')->first();
        $total = (float) $order->total;

        $this->actingAs($this->admin)->post("/jersey/keuangan/{$order->id}/bayar", [
            'tanggal_bayar' => now()->toDateString(),
            'jumlah' => $total,
            'metode_bayar' => 'Tunai',
        ])->assertRedirect()->assertSessionHas('success');

        // Pastikan TIDAK ada akun 1000 (Kas) di jurnal jersey
        $adaKas = DB::table('jurnal_umum')
            ->where('no_referensi', 'LIKE', $order->order_number . '%')
            ->where('kode_akun', '1000')
            ->exists();
        $this->assertFalse($adaKas, 'Jurnal jersey tunai tidak boleh pakai akun Kas (1000), harus Kas Kecil (1005)');
    }
}