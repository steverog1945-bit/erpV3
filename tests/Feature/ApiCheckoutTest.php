<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountingPeriodService;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * API-3: checkout API via PenjualanCheckoutService yang sama dengan web.
 *
 * Yang dijaga (misi API-3):
 *  - Gate dua lapis: API mati default → 503; API baca menyala tetapi checkout
 *    gate OFF → POST /api/transaksi TETAP 503 (Cache-Control: no-store).
 *  - Identitas dari token (bukan sesi/cookie/flash/CSRF web).
 *  - Whitelist payload ketat: jasa, susulan, koreksi (id_detail), finishing,
 *    biaya manual, tipe non-Reguler, dan id_golongan kiriman → 422 eksplisit,
 *    tidak diproses sebagian.
 *  - Harga/subtotal/HPP dihitung server lewat konteks jalur 'api' (biaya
 *    kiriman client tidak pernah dipercaya). id_golongan dari DB, produk
 *    wajib aktif.
 *  - Satu alur bisnis: nomor nota atomik, detail, BOM+konversi, stok,
 *    kartu_stok, snapshot bahan/HPP, jurnal balance, status awal web-like,
 *    deposit otomatis HANYA bila aktor pemilik sesi kas aktif.
 *  - Idempotensi tahan-crash: X-Submit-Id + UNIQUE (id_user, submit_id);
 *    replay 200/ulang=true; payload beda → 409; gagal → klaim dilepas
 *    (retry sah); finalisasi klaim atomik dengan efek bisnis.
 *  - Regresi web dijamin suite lain (AlurPenuhOrderTest dsb.) yang tetap
 *    berjalan pada jalur store() yang sama.
 */
class ApiCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $kasir;
    private string $token;
    private int $produk;
    private int $bahan;
    private int $kons;
    private int $gol;

    protected function setUp(): void
    {
        parent::setUp();

        // Menyala HANYA untuk pengujian terisolasi (tidak pernah lewat .env).
        config()->set('orion_api.enabled', true);
        config()->set('orion_api.checkout.enabled', true);

        // menus dituntaskan lewat seeder di produksi; di test disuntik manual
        // dengan id_menu yang dirujuk peta RBAC (pola MatriksIzinTest).
        DB::table('menus')->insertOrIgnore([
            ['id_menu' => 3, 'nama_menu' => 'Transaksi Baru', 'url_link' => 'penjualan', 'parent_id' => 0],
            ['id_menu' => 41, 'nama_menu' => 'Input Order', 'url_link' => 'penjualan', 'parent_id' => 0],
            ['id_menu' => 61, 'nama_menu' => 'Riwayat Nota', 'url_link' => 'penjualan/riwayat', 'parent_id' => 0],
        ]);

        // Aktor API: role kasir yang di web sah membuat order (Transaksi Baru).
        // RBAC POST /api/transaksi = menu [3,41] aksi add.
        $role = Role::factory()->create(['nama_role' => 'Kasir API Uji']);
        DB::table('role_permissions')->insert([
            ['id_role' => $role->id_role, 'id_menu' => 3, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 0, 'can_delete' => 0],
            ['id_role' => $role->id_role, 'id_menu' => 61, 'can_view' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0],
        ]);
        $this->kasir = User::factory()->create(['id_role' => $role->id_role]);
        $this->token = \Tests\Support\TokenApiPengujian::token($this->kasir);

        $this->buatFixtureProduk();
        $this->bukaSesiKas($this->kasir->id_user);
    }

    /* ================================================================
     | Fixture (sungguhan di DB testing; pola AlurPenuhOrderTest)
     * ================================================================ */
    private function buatFixtureProduk(int $stok = 100, float $harga = 10000): void
    {
        $mesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin API-3', 'tipe_mesin' => 'Cetak']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'API3', 'kode_kategori' => 'A3']);
        $this->bahan = DB::table('bahan_baku')->insertGetId([
            'kode_bahan' => 'API3-1', 'nama_bahan' => 'Kertas API-3', 'id_mesin' => $mesin, 'id_kategori' => $kat,
            'stok' => $stok, 'nilai_konversi' => 1, 'harga_rata_rata' => 1000, 'satuan_stok' => 'lembar', 'status' => 'aktif',
        ]);
        DB::table('kartu_stok')->insert([
            'id_bahan' => $this->bahan, 'tanggal' => now()->subDay(), 'tipe' => 'IN', 'qty' => $stok,
            'stok_sebelum' => 0, 'stok_sesudah' => $stok, 'referensi' => 'SALDO-AWAL', 'keterangan' => 'saldo awal',
            'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
        ]);
        AccountingService::postSaldoAwalPersediaan('SA-API3', 'Saldo awal Kertas API-3', $stok * 1000);

        $this->produk = DB::table('produk')->insertGetId([
            'nama_produk' => 'Brosur API-3', 'metode_hitung' => 'quantity', 'status' => 'aktif',
        ]);
        DB::table('bom_detail')->insert([
            'id_produk' => $this->produk, 'id_bahan' => $this->bahan,
            'cara_hitung_pemakaian' => 'Q', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail API3', 'keterangan' => '-']);
        $this->kons = DB::table('konsumen')->insertGetId([
            'nama_konsumen' => 'Pelanggan API-3', 'telepon' => '0812', 'alamat' => '-', 'id_golongan' => $this->gol,
        ]);
        DB::table('setting_harga')->insert([
            'id_produk' => $this->produk, 'id_golongan' => $this->gol, 'range_min' => 0, 'harga_jual' => $harga,
        ]);
    }

    private function bukaSesiKas(int $idUser): void
    {
        DB::table('kas_harian')->insert([
            'tgl_kas' => now()->toDateString(), 'id_user' => $idUser,
            'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()->subMinute(),
        ]);
    }

    private function hPost(string $sid, string $token = ''): array
    {
        return array_filter([
            'Authorization' => 'Bearer ' . ($token ?: $this->token),
            'X-Submit-Id' => $sid,
            'Accept' => 'application/json',
        ]);
    }

    private function payloadCheckout(array $items, array $tambahan = []): array
    {
        return array_merge([
            'id_konsumen' => $this->kons,
            'items' => $items,
        ], $tambahan);
    }

    private function itemSederhana(int $produk, float $qty, array $tambahan = []): array
    {
        return array_merge(['id_produk' => $produk, 'qty_input' => $qty], $tambahan);
    }

    private function nilaiStok(): float
    {
        return (float) DB::table('bahan_baku')->where('id_bahan', $this->bahan)->value('stok');
    }

    private function saldoAkun(string $kode): float
    {
        return round((float) DB::table('jurnal_umum')->where('kode_akun', $kode)->sum(DB::raw('debet - kredit')), 2);
    }

    private function sidikPayload(array $items): string
    {
        $items = array_map(fn ($i) => array_merge([
            'id_produk' => (int) $i['id_produk'],
            'qty_input' => (float) $i['qty_input'],
            'p' => isset($i['p']) ? (float) $i['p'] : 0.0,
            'l' => isset($i['l']) ? (float) $i['l'] : 0.0,
        ], array_intersect_key($i, array_flip(['nama_file', 'catatan_produksi', 'status_urgensi']))), $items);

        return hash('sha256', (string) json_encode([
            'tipe' => 'checkout-api-v1',
            'id_konsumen' => $this->kons,
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    /* ================================================================
     | 1-4. Gate & RBAC
     * ================================================================ */
    public function test_api_default_mati_503_dan_nol_efek(): void
    {
        config()->set('orion_api.enabled', false);

        $r = $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 5)]), $this->hPost('gate-1'));

        $r->assertStatus(503)->assertHeaderContains('Cache-Control', 'no-store');
        $this->assertSame(0, DB::table('api_checkout_idempotensi')->count());
        $this->assertSame(0, DB::table('transaksi')->count());
    }

    public function test_checkout_gate_tutup_503_walau_api_baca_menyala(): void
    {
        config()->set('orion_api.checkout.enabled', false);

        $r = $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 5)]), $this->hPost('gate-2'));

        $r->assertStatus(503)->assertHeaderContains('Cache-Control', 'no-store');
        $this->assertSame(0, DB::table('transaksi')->count());
    }

    public function test_tanpa_token_ditolak_401(): void
    {
        // API menyala, checkout gate menyala - otentikasi tetap wajib.
        $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 5)]))
            ->assertUnauthorized();
        $this->assertSame(0, DB::table('transaksi')->count());
    }

    public function test_role_tanpa_dan_owner_baca_saja_ditolak_403(): void
    {
        // (a) role tanpa baris hak apa pun.
        $roleKosong = Role::factory()->create(['nama_role' => 'Tanpa Hak Uji']);
        $uKosong = User::factory()->create(['id_role' => $roleKosong->id_role]);
        $tokKosong = \Tests\Support\TokenApiPengujian::token($uKosong);

        $r = $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 5)]), $this->hPost('rbac-1', $tokKosong));
        $r->assertForbidden();
        $this->assertSame(0, DB::table('transaksi')->count());

        // (b) profil Owner: dilarang tulis di RBAC API. Dua varian:
        //   - matrix baca saja (can_view, tanpa can_add);
        //   - meski baris add dipasang paksa, role bernama persis 'Owner'
        //     masuk config menu_access.roles_baca_saja → tetap 403.
        $roleOwner = Role::factory()->create(['nama_role' => 'Owner Uji']);
        DB::table('role_permissions')->insert([
            ['id_role' => $roleOwner->id_role, 'id_menu' => 3, 'can_view' => 1, 'can_add' => 0, 'can_edit' => 0, 'can_delete' => 0],
        ]);
        $uOwner = User::factory()->create(['id_role' => $roleOwner->id_role]);
        $tokOwner = \Tests\Support\TokenApiPengujian::token($uOwner);

        $r = $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 5)]), $this->hPost('rbac-2', $tokOwner));
        $r->assertForbidden();
        $this->assertSame(0, DB::table('transaksi')->count());

        $roleOwnerPaksa = Role::factory()->create(['nama_role' => 'Owner']);
        DB::table('role_permissions')->insert([
            ['id_role' => $roleOwnerPaksa->id_role, 'id_menu' => 3, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 0, 'can_delete' => 0],
        ]);
        $uOwnerPaksa = User::factory()->create(['id_role' => $roleOwnerPaksa->id_role]);
        $tokOwnerPaksa = \Tests\Support\TokenApiPengujian::token($uOwnerPaksa);

        $r2 = $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 5)]), $this->hPost('rbac-3', $tokOwnerPaksa));
        $r2->assertForbidden();
        $this->assertSame(0, DB::table('transaksi')->count());
    }

    /* ================================================================
     | 5-9. Penolakan whitelist & master data
     * ================================================================ */
    public function test_produk_nonaktif_ditolak_dengan_nol_efek_db(): void
    {
        $idNonaktif = DB::table('produk')->insertGetId(['nama_produk' => 'Produk Ditarik', 'metode_hitung' => 'quantity', 'status' => 'nonaktif']);

        $stokSebelum = $this->nilaiStok();
        $r = $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($idNonaktif, 5)]), $this->hPost('nonaktif-1'));

        $r->assertStatus(422);
        $this->assertSame(0, DB::table('transaksi')->count());
        $this->assertSame(0, DB::table('transaksi_detail')->count());
        $this->assertSame($stokSebelum, $this->nilaiStok());
        $this->assertSame(0, DB::table('jurnal_umum')->where('no_referensi', 'like', 'INV%')->count());
        $this->assertSame(0, DB::table('api_checkout_idempotensi')->count(), 'klaim gagal validasi tidak boleh menahan id');
    }

    public function test_produk_tak_kenal_ditolak(): void
    {
        $r = $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana(999999, 5)]), $this->hPost('hantu-1'));
        $r->assertStatus(422);
        $this->assertSame(0, DB::table('transaksi')->count());
    }

    public function test_biaya_yang_tidak_dibuka_lewat_api_ditolak(): void
    {
        // Finishing | id_jasa | susulan | koreksi | golongan kiriman |
        // tipe PO: semua kolom di luar whitelist → 422 sebelum jalur service.
        $stokSebelum = $this->nilaiStok();

        $r1 = $this->postJson('/api/transaksi', $this->payloadCheckout([
            ['id_produk' => $this->produk, 'qty_input' => 5, 'id_finishing' => 1, 'biaya_finishing' => 5000],
        ]), $this->hPost('wl-fin'));
        $r1->assertStatus(422);

        $r2 = $this->postJson('/api/transaksi', $this->payloadCheckout([
            ['id_produk' => $this->produk, 'qty_input' => 5, 'id_jasa' => 1],
        ]), $this->hPost('wl-jasa'));
        $r2->assertStatus(422);

        $r3 = $this->postJson('/api/transaksi', $this->payloadCheckout(
            [$this->itemSederhana($this->produk, 5)],
            ['susulan_id' => 99]
        ), $this->hPost('wl-susulan'));
        $r3->assertStatus(422);

        $r4 = $this->postJson('/api/transaksi', $this->payloadCheckout(
            [['id_produk' => $this->produk, 'qty_input' => 5, 'id_detail' => 12]]
        ), $this->hPost('wl-koreksi'));
        $r4->assertStatus(422);

        $r5 = $this->postJson('/api/transaksi', $this->payloadCheckout(
            [$this->itemSederhana($this->produk, 5)],
            ['id_golongan' => 77]
        ), $this->hPost('wl-gol'));
        $r5->assertStatus(422);

        $r6 = $this->postJson('/api/transaksi', $this->payloadCheckout(
            [$this->itemSederhana($this->produk, 5)],
            ['tipe_order' => 'PO', 'no_po' => 'PO-1']
        ), $this->hPost('wl-po'));
        $r6->assertStatus(422);

        // X-Submit-Id wajib (token tetap sah).
        $r7 = $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 5)]), array_filter([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ]));
        $r7->assertStatus(422);

        // Potong Manual A3 (dengan/sans tier) tidak pernah bisa masuk lewat
        // API: finishing ada di luar whitelist -> 422, jadi validasi tier A3
        // di service tidak dapat di-bypass dari kanal API.
        $idA3 = (int) DB::table('finishing')->where('nama_finishing', 'Potong Manual A3')->value('id_finishing');
        $r8 = $this->postJson('/api/transaksi', $this->payloadCheckout([
            ['id_produk' => $this->produk, 'qty_input' => 5, 'id_finishing' => $idA3, 'biaya_finishing' => 5000],
        ]), $this->hPost('wl-a3'));
        $r8->assertStatus(422);

        $this->assertSame(0, DB::table('transaksi')->count());
        $this->assertSame($stokSebelum, $this->nilaiStok());
        $this->assertSame(0, DB::table('api_checkout_idempotensi')->count());
    }

    public function test_konsumen_dan_golongan_tidak_sah_ditolak(): void
    {
        $r1 = $this->postJson('/api/transaksi', array_merge($this->payloadCheckout([$this->itemSederhana($this->produk, 5)]), ['id_konsumen' => 999999]), $this->hPost('kos-1'));
        $r1->assertStatus(422);

        // Golongan kacau tidak bisa dibuat di DB (FK RESTRICT, kolom wajib).
        // Padanannya di dunia nyata: konsumen sah tetapi TANPA price list
        // utk golongannya → service menolak; API membalas 422 tanpa efek.
        DB::table('setting_harga')->where('id_produk', $this->produk)->delete();

        $r2 = $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 5)]), $this->hPost('kos-2'));
        $r2->assertStatus(422);

        $this->assertSame(0, DB::table('transaksi')->count());
    }

    public function test_kas_belum_dibuka_ditolak(): void
    {
        // Tidak ada sesi kas open utk fixture ini? sesi dibuka di setUp untuk
        // kasir; tutup sesi agar jalurnya 422 (sama seperti guard web).
        DB::table('kas_harian')->update(['status_lock' => 'closed', 'waktu_tutup' => now()]);

        $r = $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 5)]), $this->hPost('kas-tutup'));
        $r->assertStatus(422);
        $this->assertSame(0, DB::table('transaksi')->count());
    }

    /* ================================================================
     | 10-13. Checkout sah: satu nota, stok, HPP, jurnal, deposit
     * ================================================================ */
    public function test_checkout_sah_satu_nota_stok_hpp_jurnal_balance(): void
    {
        $r = $this->postJson('/api/transaksi', $this->payloadCheckout([
            $this->itemSederhana($this->produk, 5, ['nama_file' => 'brosur-api.pdf']),
        ]), $this->hPost('sah-1'));

        $r->assertStatus(201)->assertHeaderContains('Cache-Control', 'no-store');
        $this->assertFalse($r->json('ulang'));
        $this->assertSame('success', $r->json('status'));
        $idTrx = $r->json('id_transaksi');
        $nomor = $r->json('nomor_nota');
        $this->assertEquals(50000.0, $r->json('total_bayar'));

        // Satu nota + satu detail; total & nilai server (harga dari master:
        // 10.000 x qty 5), BUKAN dari client.
        $this->assertSame(1, DB::table('transaksi')->count());
        $nota = DB::table('transaksi')->where('id_transaksi', $idTrx)->first();
        $this->assertEquals(50000.0, (float) $nota->total_bayar);
        $this->assertSame('Menunggu Kasir', $nota->status_order);
        $this->assertSame('Belum Bayar', $nota->status_pembayaran);
        $this->assertSame('Aktif', $nota->status_nota);
        $this->assertSame($this->kasir->id_user, (int) $nota->id_user_kasir);
        $this->assertMatchesRegularExpression('#^INV#', $nomor);

        $detail = DB::table('transaksi_detail')->where('id_transaksi', $idTrx)->get();
        $this->assertSame(1, $detail->count());
        $this->assertEquals(50000.0, (float) $detail[0]->biaya_cetak);
        $this->assertEquals(50000.0, (float) $detail[0]->subtotal);
        $this->assertSame('Antre', $detail[0]->status_spk);

        // BOM qty 5 lembar (cara_hitung 'Q', nilai_konversi 1): stok 100 → 95,
        // kartu OUT satu baris dgn sebelum/sesudah, snapshot bahan & HPP.
        $this->assertEquals(95.0, $this->nilaiStok());
        $kartu = DB::table('kartu_stok')->where('id_bahan', $this->bahan)->where('tipe', 'OUT')->first();
        $this->assertNotNull($kartu);
        $this->assertEquals(5.0, (float) $kartu->qty);
        $this->assertEquals(100.0, (float) $kartu->stok_sebelum);
        $this->assertEquals(95.0, (float) $kartu->stok_sesudah);
        $this->assertSame($this->kasir->id_user, (int) $kartu->created_by);
        $hpp = (float) DB::table('transaksi_detail')->where('id_detail', $detail[0]->id_detail)->value('hpp_snapshot');
        $this->assertEquals(5000.0, $hpp); // 5 lembar x 1.000
        $pakai = DB::table('transaksi_detail_bahan_pakai')->where('id_detail', $detail[0]->id_detail)->first();
        $this->assertNotNull($pakai);
        $this->assertEquals(5.0, (float) $pakai->qty_pakai);

        // Jurnal satu INSERT per sisi: penjualan (1100 Dr + 4000 Cr) & HPP
        // (5000 Dr + 1200 Cr) = 4 baris unt.sts referensi nota yang sama.
        $this->assertSame(4, DB::table('jurnal_umum')->where('no_referensi', $nomor)->count(), 'satu referensi nota: 2 entri penjualan + 2 entri HPP');
        $krJual = DB::table('jurnal_umum')->where('no_referensi', $nomor)->where('kode_akun', '4000')->sum('kredit');
        $drPiutang = DB::table('jurnal_umum')->where('no_referensi', $nomor)->where('kode_akun', '1100')->sum('debet');
        $this->assertEquals(50000.0, $krJual);
        $this->assertEquals(50000.0, $drPiutang);
        $this->assertEquals(5000.0, $this->saldoAkun('5000'));
        $this->assertEquals(95000.0, $this->saldoAkun('1200'));
        $this->assertEquals(
            (float) DB::table('jurnal_umum')->sum('debet'),
            (float) DB::table('jurnal_umum')->sum('kredit'),
            'buku besar harus seimbang'
        );
        $this->assertEquals(50000.0, $this->saldoAkun('1100'), 'piutang nota belum dibayar');
    }

    public function test_integritas_audit_tak_temukan_celah_setelah_checkout_api(): void
    {
        $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 5)]), $this->hPost('integ-1'))->assertStatus(201);

        Artisan::call('audit:integritas', ['--json' => true]);
        $this->assertSame([], json_decode(Artisan::output(), true)['temuan']);
    }

    public function test_deposit_dipakai_otomatis_hanya_pemilik_sesi_kas(): void
    {
        // Kasir menyimpan deposit 30.000 lalu checkout 50.000:
        // 30.000 dipakai (DP), sisa piutang 20.000.
        DB::table('konsumen_deposit')->insert([
            'id_konsumen' => $this->kons, 'nominal' => 30000, 'tipe' => 'Setoran',
            'metode_bayar' => 'Tunai', 'keterangan' => 'setoran awal', 'tgl_deposit' => now()->toDateString(),
            'id_user' => $this->kasir->id_user, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $r = $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 5)]), $this->hPost('dep-1'));
        $r->assertStatus(201);
        $idTrx = $r->json('id_transaksi');

        $pemakaian = DB::table('konsumen_deposit')->where('tipe', 'Pemakaian')->first();
        $this->assertNotNull($pemakaian);
        $this->assertEquals(30000.0, (float) $pemakaian->nominal);
        $this->assertSame($idTrx, (int) $pemakaian->id_transaksi);
        $this->assertSame($this->kasir->id_user, (int) $pemakaian->id_user);

        $bayar = DB::table('pembayaran_riwayat')->where('id_transaksi', $idTrx)->first();
        $this->assertNotNull($bayar);
        $this->assertEquals(30000.0, (float) $bayar->nominal);
        $this->assertSame('Deposit', $bayar->metode);

        $nota = DB::table('transaksi')->where('id_transaksi', $idTrx)->first();
        $this->assertSame('DP', $nota->status_pembayaran);
        $this->assertSame('Deposit', $nota->metode_pembayaran);

        // Jurnal deposit: Dr Hutang deposit 2100 / Cr Piutang 1100
        $drDeposit = DB::table('jurnal_umum')->where('kode_akun', '2100')->sum('debet');
        $crPiutangDeposit = DB::table('jurnal_umum')->where('kode_akun', '1100')->sum('kredit');
        $this->assertEquals(30000.0, $drDeposit);
        $this->assertEquals(30000.0, $crPiutangDeposit);
        $this->assertEquals(20000.0, $this->saldoAkun('1100'), 'piutang tersisa setelah deposit = 20.000');
        $this->assertCount(1, DB::table('konsumen_deposit')->where('tipe', 'Pemakaian')->get());
    }

    public function test_deposit_tak_dipakai_bila_sesi_milik_operator_lain(): void
    {
        DB::table('konsumen_deposit')->insert([
            'id_konsumen' => $this->kons, 'nominal' => 30000, 'tipe' => 'Setoran',
            'metode_bayar' => 'Tunai', 'keterangan' => 'setoran', 'tgl_deposit' => now()->toDateString(),
            'id_user' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Tutup sesi kasir, buka sesi milik "operator lain".
        DB::table('kas_harian')->update(['status_lock' => 'closed', 'waktu_tutup' => now()]);
        $admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
        $this->bukaSesiKas($admin->id_user);

        $r = $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 5)]), $this->hPost('dep-b-1'));
        $r->assertStatus(201);
        $idTrx = $r->json('id_transaksi');

        $nota = DB::table('transaksi')->where('id_transaksi', $idTrx)->first();
        $this->assertSame('Belum Bayar', $nota->status_pembayaran);
        $this->assertSame(0, DB::table('konsumen_deposit')->where('tipe', 'Pemakaian')->count());
        $this->assertSame(0, DB::table('pembayaran_riwayat')->count());
    }

    /* ================================================================
     | 14. Kegagalan jurnal → rollback total + retry sah
     * ================================================================ */
    public function test_gagal_jurnal_rollback_total_dan_retry_masih_sah(): void
    {
        // Tutup periode akuntansi hari ini → postJournal menolak.
        AccountingPeriodService::tutup(now()->year, now()->month, $this->kasir->id_user, 'uji');

        $stokSebelum = $this->nilaiStok();
        $r = $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 5)]), $this->hPost('jurnal-gagal'));
        $this->assertTrue($r->isServerError(), 'status: ' . $r->status());

        // TIDAK ada apa pun yang survivol: nota, detail, stok, kartu, jurnal.
        $this->assertSame(0, DB::table('transaksi')->count());
        $this->assertSame(0, DB::table('transaksi_detail')->count());
        $this->assertSame($stokSebelum, $this->nilaiStok());
        $this->assertSame(0, DB::table('kartu_stok')->where('tipe', 'OUT')->count());
        $this->assertSame(0, DB::table('jurnal_umum')->where('no_referensi', 'like', 'INV%')->count());
        // Klaim tidak menahan id - retry yang sah tetap mungkin.
        $this->assertSame(0, DB::table('api_checkout_idempotensi')->count());

        // Buka lagi periode → kiriman ulang dgn X-Submit-Id YANG SAMA → sah.
        AccountingPeriodService::buka(now()->year, now()->month);
        $r2 = $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 5)]), $this->hPost('jurnal-gagal'));
        $r2->assertStatus(201);
        $this->assertSame(1, DB::table('transaksi')->count());
        $this->assertEquals(95.0, $this->nilaiStok());
    }

    /* ================================================================
     | 15-17. Idempotensi
     * ================================================================ */
    public function test_bentuk_kirim_ulang_dengan_submit_yang_sama_tetap_satu_nota(): void
    {
        $payload = $this->payloadCheckout([$this->itemSederhana($this->produk, 5)]);

        $r1 = $this->postJson('/api/transaksi', $payload, $this->hPost('idem-1'));
        $r1->assertStatus(201);
        $idTrx = $r1->json('id_transaksi');
        $this->assertEquals(95.0, $this->nilaiStok());

        // Kiriman ulang identik → nota yang sama, 200, ulang=true.
        $r2 = $this->postJson('/api/transaksi', $payload, $this->hPost('idem-1'));
        $r2->assertStatus(200);
        $this->assertTrue($r2->json('ulang'));
        $this->assertSame($idTrx, $r2->json('id_transaksi'));
        $this->assertSame($r1->json('nomor_nota'), $r2->json('nomor_nota'));
        $this->assertEquals($r1->json('total_bayar'), $r2->json('total_bayar'));

        // Satu nota, satu kartu OUT, satu set jurnal.
        $this->assertSame(1, DB::table('transaksi')->count());
        $this->assertSame(1, DB::table('kartu_stok')->where('tipe', 'OUT')->count());
        $this->assertEquals(95.0, $this->nilaiStok());

        // Klaim terekam final.
        $row = DB::table('api_checkout_idempotensi')->where('id_user', $this->kasir->id_user)->where('submit_id', 'idem-1')->first();
        $this->assertNotNull($row);
        $this->assertSame('sah', $row->status);
        $this->assertSame($idTrx, (int) $row->id_transaksi);
        $this->assertSame($this->sidikPayload([['id_produk' => $this->produk, 'qty_input' => 5]]), $row->payload_hash);
    }

    public function test_submit_id_sama_payload_beda_ditolak_409_tanpa_nota_kedua(): void
    {
        $r1 = $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 5)]), $this->hPost('idem-2'));
        $r1->assertStatus(201);

        $r2 = $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 3)]), $this->hPost('idem-2'));
        $r2->assertStatus(409);
        $this->assertSame(1, DB::table('transaksi')->count(), 'payload beda tidak boleh melahirkan nota kedua');
        $this->assertEquals(95.0, $this->nilaiStok());
    }

    public function test_klaim_sedang_berjalan_ditunda_409_bukan_proses_kedua(): void
    {
        // Runner lain sedang memegang klaim (row 'diproses' masih muda).
        config()->set('orion_api.idempotensi_tunggu_detik', 0); // tunggu nol → cepat
        $items = [$this->itemSederhana($this->produk, 5)];
        DB::table('api_checkout_idempotensi')->insert([
            'id_user' => $this->kasir->id_user, 'submit_id' => 'idem-3',
            'payload_hash' => $this->sidikPayload($items),
            'status' => 'diproses', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $r = $this->postJson('/api/transaksi', $this->payloadCheckout($items), $this->hPost('idem-3'));
        $r->assertStatus(409);
        $this->assertSame(0, DB::table('transaksi')->count(), 'dilarang memproses checkout kedua di balik klaim lain');
        $this->assertEquals(100.0, $this->nilaiStok());
    }

    public function test_klaim_lebih_dari_lima_menit_tidak_boleh_direbut_otomatis(): void
    {
        // Updated_at tua tidak membuktikan proses pertama mati: request awal
        // mungkin tetap bekerja, termasuk menunggu lock database. Jika
        // klaim dihapus, request kedua berisiko membuat nota dan jurnal kedua.
        $items = [$this->itemSederhana($this->produk, 5)];
        DB::table('api_checkout_idempotensi')->insert([
            'id_user' => $this->kasir->id_user, 'submit_id' => 'idem-4',
            'payload_hash' => $this->sidikPayload($items),
            'status' => 'diproses', 'created_at' => now()->subMinutes(10), 'updated_at' => now()->subMinutes(10),
        ]);

        $r = $this->postJson('/api/transaksi', $this->payloadCheckout($items), $this->hPost('idem-4'));
        $r->assertStatus(409)->assertHeaderContains('Cache-Control', 'no-store');
        $this->assertSame(0, DB::table('transaksi')->count());
        $this->assertEquals(100.0, $this->nilaiStok());
        $this->assertSame(1, DB::table('api_checkout_idempotensi')->where('submit_id', 'idem-4')->count());
    }

    public function test_klaim_sah_tanpa_referensi_nota_tidak_berubah_jadi_checkout_baru(): void
    {
        $items = [$this->itemSederhana($this->produk, 5)];
        DB::table('api_checkout_idempotensi')->insert([
            'id_user' => $this->kasir->id_user, 'submit_id' => 'idem-5',
            'payload_hash' => $this->sidikPayload($items),
            'status' => 'sah', 'id_transaksi' => null,
            'created_at' => now()->subMinutes(10), 'updated_at' => now()->subMinutes(10),
        ]);

        $r = $this->postJson('/api/transaksi', $this->payloadCheckout($items), $this->hPost('idem-5'));
        $r->assertStatus(409)->assertHeaderContains('Cache-Control', 'no-store');
        $this->assertSame(0, DB::table('transaksi')->count());
        $this->assertEquals(100.0, $this->nilaiStok());
        $this->assertSame('sah', DB::table('api_checkout_idempotensi')->where('submit_id', 'idem-5')->value('status'));
    }

    /* ================================================================
     | 18-19. Regresi web & konsistensi alur existing
     * ================================================================ */
    public function test_web_store_tetap_bekerja_dgn_service_sama_konteks_web(): void
    {
        // Jalur web lewat controller yang men-delegasi service yang sama,
        // TANPA konteks API → harga dari input operator (biaya_cetak) tetap
        // dipakai seperti aturan web (hak menimpa harga hanya di kanal web
        // untuk role yang berhak). Konteks jalur 'api' tidak aktif di sini.
        $this->actingAs($this->kasir);

        $r = $this->postJson('/penjualan/store', [
            'id_konsumen' => $this->kons, 'id_golongan' => $this->gol, 'submit_id' => 'web-paritas-1',
            'items' => [['id_produk' => $this->produk, 'nama_file' => 'web.pdf', 'p' => 0, 'l' => 0, 'qty_input' => 5, 'biaya_cetak' => 50000]],
        ]);
        $r->assertOk();

        $nota = DB::table('transaksi')->first();
        $this->assertEquals(50000.0, (float) $nota->total_bayar);
        $this->assertMatchesRegularExpression('#^INV#', $nota->nomor_nota);
        $this->assertSame('Menunggu Kasir', $nota->status_order);
        $this->assertEquals(95.0, $this->nilaiStok());
        $this->assertSame(0, DB::table('api_checkout_idempotensi')->count(), 'web tidak menyentuh tabel idempotensi API');
    }

    public function test_alur_api_lalu_web_tidak_mengacaukan_satu_sama_lain(): void
    {
        // Nota dari API lalu nota dari WEB di sesi sama: keduanya memotong
        // stok berurutan tanpa saling mengunci (nomor nota atomik).
        $this->postJson('/api/transaksi', $this->payloadCheckout([$this->itemSederhana($this->produk, 2)]), $this->hPost('mix-1'))->assertStatus(201);

        $this->actingAs($this->kasir);
        $this->postJson('/penjualan/store', [
            'id_konsumen' => $this->kons, 'id_golongan' => $this->gol, 'submit_id' => 'mix-2',
            'items' => [['id_produk' => $this->produk, 'nama_file' => 'web2.pdf', 'p' => 0, 'l' => 0, 'qty_input' => 3, 'biaya_cetak' => 30000]],
        ])->assertOk();

        $this->assertSame(2, DB::table('transaksi')->count());
        $this->assertSame(2, (int) DB::table('transaksi')->selectRaw('COUNT(DISTINCT nomor_nota) AS k')->value('k'));
        $this->assertEquals(95.0, $this->nilaiStok()); // 2 + 3 dari 100
        $this->assertEquals(
            (float) DB::table('jurnal_umum')->sum('debet'),
            (float) DB::table('jurnal_umum')->sum('kredit')
        );
    }
}
