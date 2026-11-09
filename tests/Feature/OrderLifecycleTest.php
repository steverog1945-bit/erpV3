<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Order lifecycle: Menunggu Kasir -> Diterima Kasir (POST terimaTransaksi)
 * -> SPK -> Produksi -> Collecting -> Pembayaran.
 *
 * GET bayarForm() READ-only: tidak pernah mengubah status_order.
 */
class OrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idGol;
    private int $idKonsumen;
    private int $idProduk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $this->actingAs($this->admin);
        $this->idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $this->idKonsumen = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Uji Order', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $this->idGol]);
        $this->idProduk = DB::table('produk')->insertGetId(['nama_produk' => 'Stiker Uji', 'metode_hitung' => 'quantity', 'status' => 'Aktif']);
        DB::table('setting_harga')->insert(['id_produk' => $this->idProduk, 'id_golongan' => $this->idGol, 'range_min' => 0.01, 'harga_jual' => 50000]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 0, 'status_lock' => 'open']);
    }

    private function buatNota(int $jmlItem = 1, string $namaFile = 'order-uji'): object
    {
        $items = [];
        for ($i = 0; $i < $jmlItem; $i++) {
            $items[] = [
                'id_produk' => $this->idProduk, 'nama_file' => "{$namaFile}-{$i}.jpg",
                'p' => 0, 'l' => 0, 'qty_input' => 1, 'biaya_cetak' => 50000,
                'biaya_desain' => 0, 'siap_cetak' => false,
            ];
        }
        $this->postJson('/penjualan/store', [
            'id_konsumen' => $this->idKonsumen, 'id_golongan' => $this->idGol, 'items' => $items,
        ])->assertOk();

        return DB::table('transaksi')->latest('id_transaksi')->first();
    }

    private function terima(int $idTransaksi, string $sid): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('kasir.terimaTransaksi', $idTransaksi), ['submit_id' => $sid]);
    }

    public function test_order_baru_status_menunggu_kasir(): void
    {
        $nota = $this->buatNota();
        $this->assertSame('Menunggu Kasir', DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->value('status_order'));
    }

    public function test_order_baru_belum_membuat_spk(): void
    {
        $nota = $this->buatNota(1, 'belum-spk');
        $this->get(route('spk.cek-antrian-baru'))->assertJson(['total' => 0]);
        $this->get('/spk')->assertOk()->assertDontSee($nota->nomor_nota);
    }

    public function test_antrean_baru_hanya_menunggu_kasir(): void
    {
        $nota = $this->buatNota(1, 'antrean');
        $this->get('/kasir/waiting-list')->assertOk()->assertSee($nota->nomor_nota);

        $this->terima($nota->id_transaksi, 'antre-1')->assertSessionHas('success');

        $this->get('/kasir/waiting-list')->assertOk()->assertDontSee($nota->nomor_nota);
    }

    /**
     * Alur linear yang diminta kasir: nota baru -> Antrean Baru -> kasir
     * buka transaksi -> baru muncul di Monitoring Kasir.
     *
     * Yang diuji bukan cuma isi tiap halaman, tapi bahwa keduanya TIDAK
     * beririsan. KasirController::index() sebelumnya tidak menyaring
     * status_order sama sekali, jadi nota yang masih 'Menunggu Kasir' ikut
     * muncul di tabel Monitoring - nota yang sama tampil di dua tempat dan
     * alurnya jadi tidak jelas. Test ini gagal kalau syarat itu dilonggarkan.
     */
    public function test_monitoring_hanya_nota_yang_transaksinya_sudah_dibuka(): void
    {
        $nota = $this->buatNota(1, 'linear');

        $this->get('/kasir/waiting-list')->assertOk()->assertSee($nota->nomor_nota);
        $this->get('/kasir/monitoring')->assertOk()->assertDontSee($nota->nomor_nota);

        $this->terima($nota->id_transaksi, 'linear-1')->assertSessionHas('success');

        $this->get('/kasir/monitoring')->assertOk()->assertSee($nota->nomor_nota);
        $this->get('/kasir/waiting-list')->assertOk()->assertDontSee($nota->nomor_nota);
    }

    public function test_get_bayar_form_tidak_mengubah_status(): void
    {
        $nota = $this->buatNota(1, 'get-readonly');

        // GET 3x: ditolak (belum diterima) dan status tetap Menunggu Kasir.
        for ($i = 0; $i < 3; $i++) {
            $this->get(route('kasir.bayarForm', $nota->id_transaksi))->assertRedirect();
        }
        $this->assertSame('Menunggu Kasir', DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->value('status_order'));
        $this->assertSame(1, DB::table('transaksi_detail')->where('id_transaksi', $nota->id_transaksi)->count());
    }

    public function test_kasir_terima_transaksi_update_status(): void
    {
        $nota = $this->buatNota(1, 'terima-ok');
        $this->terima($nota->id_transaksi, 'terima-ok-1')->assertSessionHas('success');
        $this->assertSame('Diterima Kasir', DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->value('status_order'));
    }

    public function test_terima_transaksi_membuat_spk(): void
    {
        $nota = $this->buatNota(1, 'spk-aktif');
        $this->terima($nota->id_transaksi, 'spk-aktif-1');
        $this->get(route('spk.cek-antrian-baru'))->assertJson(['total' => 1]);
        $this->get('/spk')->assertOk()->assertSee($nota->nomor_nota);
    }

    public function test_spk_hanya_diterima_kasir(): void
    {
        $nota = $this->buatNota(1, 'spk-gate');
        $detail = DB::table('transaksi_detail')->where('id_transaksi', $nota->id_transaksi)->value('id_detail');

        // Bypass via URL sebelum diterima: ditolak.
        $this->patch("/spk/{$detail}/ambil")->assertSessionHas('error');
        $this->assertSame('Antre', DB::table('transaksi_detail')->where('id_detail', $detail)->value('status_spk'));

        $this->terima($nota->id_transaksi, 'spk-gate-1');
        $this->patch("/spk/{$detail}/ambil")->assertSessionHas('success');
        $this->assertSame('Desain', DB::table('transaksi_detail')->where('id_detail', $detail)->value('status_spk'));
    }

    public function test_produksi_hanya_diterima_kasir(): void
    {
        $nota = $this->buatNota(1, 'prod-gate');
        $detail = DB::table('transaksi_detail')->where('id_transaksi', $nota->id_transaksi)->value('id_detail');

        $this->patch("/produksi/{$detail}/simpan-qty", ['qty_cetak' => 1])->assertSessionHas('error');
        $this->assertSame('Antre', DB::table('transaksi_detail')->where('id_detail', $detail)->value('status_spk'));

        $this->terima($nota->id_transaksi, 'prod-gate-1');
        $this->patch("/spk/{$detail}/ambil")->assertSessionHas('success');
        $this->patch("/produksi/{$detail}/simpan-qty", ['qty_cetak' => 1])->assertSessionHas('success');
    }

    public function test_double_terima_transaksi_ditolak(): void
    {
        $nota = $this->buatNota(1, 'double');
        $this->terima($nota->id_transaksi, 'double-1')->assertSessionHas('success');
        $this->terima($nota->id_transaksi, 'double-2')->assertSessionHas('error');
        $this->assertSame('Diterima Kasir', DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->value('status_order'));
    }

    /**
     * Concurrency simulation (jujur): dua request terima untuk nota yang sama
     * dieksekusi berurutan - request B baru jalan setelah A commit, sehingga
     * B membaca state terbaru (Diterima Kasir) dan ditolak. Di production,
     * lockForUpdate() membuat request bersamaan serial dengan hasil sama:
     * hanya 1 transisi berhasil, 1 SPK, tanpa partial state. Framework test
     * tidak mendukung 2 HTTP request benar-benar paralel dalam 1 proses.
     */
    public function test_concurrent_terima_transaksi(): void
    {
        $nota = $this->buatNota(1, 'concurrent');
        $sebelum = DB::table('transaksi_detail')->where('id_transaksi', $nota->id_transaksi)->count();

        $this->terima($nota->id_transaksi, 'conc-A');
        $this->terima($nota->id_transaksi, 'conc-B')->assertSessionHas('error');

        $this->assertSame('Diterima Kasir', DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->value('status_order'));
        $this->assertSame($sebelum, DB::table('transaksi_detail')->where('id_transaksi', $nota->id_transaksi)->count());
        $this->get(route('spk.cek-antrian-baru'))->assertJson(['total' => 1]);
    }

    public function test_terima_transaksi_batal_ditolak(): void
    {
        $nota = $this->buatNota(1, 'batal-tolak');
        DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)
            ->update(['status_nota' => 'Dibatalkan', 'status_order' => 'Dibatalkan']);

        $this->terima($nota->id_transaksi, 'batal-tolak-1')->assertSessionHas('error');
        $this->assertSame('Dibatalkan', DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->value('status_order'));
    }

    public function test_gagal_membuat_spk_rollback_status_order(): void
    {
        // Terima nota yang tidak ada: exception -> rollBack, tidak ada baris berubah.
        $jumlahSebelum = DB::table('transaksi')->count();
        $this->terima(999999, 'gagal-1')->assertSessionHas('error');
        $this->assertSame($jumlahSebelum, DB::table('transaksi')->count());

        // Terima nota batal: exception -> rollBack, status_order & detail utuh.
        $nota = $this->buatNota(1, 'gagal-rollback');
        $detailSebelum = DB::table('transaksi_detail')->where('id_transaksi', $nota->id_transaksi)->get();
        DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)
            ->update(['status_nota' => 'Dibatalkan', 'status_order' => 'Dibatalkan']);
        $this->terima($nota->id_transaksi, 'gagal-2')->assertSessionHas('error');
        $this->assertSame('Dibatalkan', DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->value('status_order'));
        $this->assertSame($detailSebelum->count(), DB::table('transaksi_detail')->where('id_transaksi', $nota->id_transaksi)->count());
    }

    public function test_spk_idempotent(): void
    {
        $nota = $this->buatNota(2, 'idem');
        $sebelum = DB::table('transaksi_detail')->where('id_transaksi', $nota->id_transaksi)->count();

        $this->terima($nota->id_transaksi, 'idem-1')->assertSessionHas('success');
        $this->terima($nota->id_transaksi, 'idem-2')->assertSessionHas('error');

        $this->assertSame($sebelum, DB::table('transaksi_detail')->where('id_transaksi', $nota->id_transaksi)->count());
        $this->get(route('spk.cek-antrian-baru'))->assertJson(['total' => 2]);
    }

    public function test_item_batal_tidak_masuk_tagihan(): void
    {
        $nota = $this->buatNota(2, 'tagihan');
        $this->terima($nota->id_transaksi, 'tagihan-1');
        $details = DB::table('transaksi_detail')->where('id_transaksi', $nota->id_transaksi)->orderBy('id_detail')->get();
        $this->patch("/spk/{$details[0]->id_detail}/ambil");
        $this->patch("/produksi/{$details[0]->id_detail}/selesai");
        $this->patch("/produksi/{$details[1]->id_detail}/cancel", ['tindakan' => 'batal']);

        $res = $this->get(route('kasir.bayarForm', $nota->id_transaksi))->assertOk();
        $res->assertSee('Rp ' . number_format((float) $details[0]->subtotal, 0, ',', '.'));
    }

    public function test_item_batal_tidak_blokir_collecting(): void
    {
        $nota = $this->buatNota(2, 'collect');
        $this->terima($nota->id_transaksi, 'collect-1');
        $details = DB::table('transaksi_detail')->where('id_transaksi', $nota->id_transaksi)->orderBy('id_detail')->get();
        $this->patch("/spk/{$details[0]->id_detail}/ambil");
        $this->patch("/produksi/{$details[0]->id_detail}/selesai");
        $this->patch("/produksi/{$details[1]->id_detail}/cancel", ['tindakan' => 'batal']);

        $this->patch("/collecting/{$nota->id_transaksi}/proses", [
            'konfirmasi_belum_lunas' => true, 'alasan_belum_lunas' => 'Diambil dulu, bayar menyusul',
        ])->assertSessionHas('success');
        $this->assertSame('Sudah Diambil', DB::table('transaksi')->where('id_transaksi', $nota->id_transaksi)->value('status_collecting'));
    }
}
