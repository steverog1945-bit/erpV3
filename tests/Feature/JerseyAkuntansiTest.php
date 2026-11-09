<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\JerseyOrder;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountingPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JerseyAkuntansiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->create(['nama_role' => 'Administrator']);
        $this->admin = User::factory()->create(['id_role' => $role->id_role]);
        $this->actingAs($this->admin);
    }

    /** Order tanpa line: total = biaya tambahan - diskon (rumus recalculateTotal). */
    private function order(float $biayaTambahan = 500000): JerseyOrder
    {
        $o = JerseyOrder::create([
            'order_number' => 'JO-' . uniqid(), 'order_date' => now()->toDateString(), 'customer_name' => 'Tim Garuda',
            'additional_charge' => $biayaTambahan,
        ]);
        $o->recalculateTotal();

        return $o->fresh();
    }

    private function saldo(string $kodeAkun): float
    {
        return (float) DB::table('jurnal_umum')->where('kode_akun', $kodeAkun)->sum(DB::raw('debet - kredit'));
    }

    private function bayar(JerseyOrder $o, float $jumlah, string $metode = 'Tunai')
    {
        return $this->post("/jersey/keuangan/{$o->id}/bayar", [
            'tanggal_bayar' => now()->toDateString(), 'jumlah' => $jumlah, 'metode_bayar' => $metode,
        ]);
    }

    private function approve(string $tipe): void
    {
        $req = ApprovalRequest::where('tipe_aksi', $tipe)->where('status', 'Menunggu')->firstOrFail();
        $this->patch("/approval/{$req->id}/approve", ['catatan' => 'OK']);
    }

    public function test_total_order_diakui_sebagai_pendapatan_jersey_dan_dikoreksi_lewat_selisih(): void
    {
        $o = $this->order(500000);
        $this->assertEquals(500000, $this->saldo('1100'));
        $this->assertEquals(-500000, $this->saldo('4300'));
        $this->assertEquals(500000, (float) $o->total_terjurnal);

        $o->update(['additional_charge' => 700000]);
        $o->recalculateTotal();
        $this->assertEquals(-700000, $this->saldo('4300'));

        $o->update(['additional_charge' => 300000]);
        $o->recalculateTotal();
        $this->assertEquals(-300000, $this->saldo('4300'));
        $this->assertEquals(300000, $this->saldo('1100'));
        $this->assertEquals(300000, (float) $o->fresh()->total_terjurnal);
    }

    public function test_recalculate_tanpa_perubahan_total_tidak_menambah_jurnal(): void
    {
        $o = $this->order(500000);
        $jumlahBaris = DB::table('jurnal_umum')->count();

        $o->recalculateTotal();
        $o->recalculateTotal();

        $this->assertEquals($jumlahBaris, DB::table('jurnal_umum')->count());
    }

    public function test_pembayaran_tunai_ke_kas_dan_transfer_ke_bank_mengurangi_piutang(): void
    {
        $o = $this->order(1000000);

        $this->bayar($o, 400000, 'Tunai')->assertSessionHas('success');
        $this->bayar($o, 600000, 'QRIS')->assertSessionHas('success');

        $this->assertEquals(400000, $this->saldo('1005'));
        $this->assertEquals(600000, $this->saldo('1010'));
        $this->assertEquals(0.0, $this->saldo('1100') + 0);
        $this->assertEquals('Lunas', $o->fresh()->status_pembayaran);
        $this->assertNotNull(DB::table('jersey_order_payments')->value('no_referensi_jurnal'));
    }

    public function test_hapus_pembayaran_hanya_mengajukan_lalu_disetujui_membalik_jurnal(): void
    {
        $o = $this->order(1000000);
        $this->bayar($o, 1000000, 'Tunai');
        $idBayar = DB::table('jersey_order_payments')->value('id');

        $this->delete("/jersey/keuangan/bayar/{$idBayar}", ['alasan' => 'Salah input'])->assertSessionHas('success');
        $this->assertNull(DB::table('jersey_order_payments')->where('id', $idBayar)->value('deleted_at'));
        $this->assertEquals(1000000, $this->saldo('1005'));

        $this->approve('void_pembayaran_jersey');

        $this->assertNotNull(DB::table('jersey_order_payments')->where('id', $idBayar)->value('deleted_at'));
        $this->assertEquals(0.0, $this->saldo('1005') + 0);
        $this->assertEquals(1000000, $this->saldo('1100'));
        $this->assertEquals('Belum Bayar', $o->fresh()->status_pembayaran);
    }

    public function test_order_yang_sudah_diakui_hanya_bisa_dibatalkan_lewat_approval_dan_membalik_pendapatan(): void
    {
        $o = $this->order(800000);

        $this->delete("/jersey/{$o->id}", ['alasan' => 'Batal dipesan'])->assertSessionHas('success');
        $this->assertNotNull(JerseyOrder::find($o->id), 'tidak boleh langsung terhapus');

        $this->approve('batalkan_order_jersey');

        $this->assertNull(JerseyOrder::find($o->id));
        $this->assertEquals(0.0, $this->saldo('4300') + 0);
        $this->assertEquals(0.0, $this->saldo('1100') + 0);
    }

    public function test_order_dengan_pembayaran_tidak_bisa_dibatalkan_sebelum_pembayaran_di_void(): void
    {
        $o = $this->order(800000);
        $this->bayar($o, 100000);

        $this->delete("/jersey/{$o->id}", ['alasan' => 'Batal']);
        $this->approve('batalkan_order_jersey');

        $this->assertNotNull(JerseyOrder::find($o->id));
        $this->assertEquals('Menunggu', ApprovalRequest::where('tipe_aksi', 'batalkan_order_jersey')->first()->status);
    }

    public function test_order_kosong_tanpa_nilai_boleh_dihapus_langsung(): void
    {
        $o = $this->order(0);

        $this->delete("/jersey/{$o->id}")->assertRedirect(route('jersey.orders.index'));

        $this->assertNull(JerseyOrder::find($o->id));
        $this->assertEquals(0, ApprovalRequest::count());
    }

    public function test_periode_tertutup_menolak_pembayaran_jersey_tanpa_meninggalkan_baris(): void
    {
        $o = $this->order(500000);
        AccountingPeriodService::tutup((int) now()->year, (int) now()->month, $this->admin->id_user);

        $this->bayar($o, 100000)->assertSessionHasErrors('tanggal_bayar');

        $this->assertEquals(0, DB::table('jersey_order_payments')->count());
        $this->assertEquals('Belum Bayar', $o->fresh()->status_pembayaran);
    }

    // ---- Audit H-1: kelebihan bayar & pembayaran ganda ----

    public function test_pembayaran_melebihi_sisa_tagihan_ditolak_tanpa_jurnal_dan_tanpa_baris(): void
    {
        $o = $this->order(500000);

        // Non-tunai tidak punya kembalian: lebih dari sisa ditolak.
        $this->bayar($o, 500001, 'Transfer')->assertSessionHas('error');

        $this->assertEquals(0, DB::table('jersey_order_payments')->count());
        $this->assertEquals(500000, $this->saldo('1100'));
        $this->assertEquals('Belum Bayar', $o->fresh()->status_pembayaran);
    }

    public function test_tunai_lebih_dari_sisa_dicatat_sebesar_sisa_dan_kembalian_diberitahukan(): void
    {
        $o = $this->order(500000);

        $r = $this->bayar($o, 600000);

        $r->assertSessionHas('success');
        $this->assertStringContainsString('Kembalian untuk pelanggan: Rp 100.000', session('success'));
        $this->assertEquals(500000, (float) DB::table('jersey_order_payments')->sum('jumlah'));
        $this->assertEquals(0.0, $this->saldo('1100') + 0);
        $this->assertEquals('Lunas', $o->fresh()->status_pembayaran);
    }

    public function test_pembayaran_kedua_setelah_lunas_ditolak_bukan_menjadi_pembayaran_ganda(): void
    {
        $o = $this->order(500000);

        $this->bayar($o, 500000)->assertSessionHas('success');
        // Simulasi klik ganda / kasir kedua yang submit form lama dengan angka yang sama.
        $this->bayar($o, 500000)->assertSessionHas('error');

        $this->assertEquals(1, DB::table('jersey_order_payments')->count());
        $this->assertEquals(500000, (float) DB::table('jersey_orders')->where('id', $o->id)->value('deposit'));
        $this->assertEquals(0.0, $this->saldo('1100') + 0);
        $this->assertEquals(1, DB::table('kas_kecil')->where('sumber', 'jersey')->count());
    }

    public function test_pembayaran_pas_sisa_setelah_cicilan_diterima(): void
    {
        $o = $this->order(1000000);

        $this->bayar($o, 300000)->assertSessionHas('success');
        $this->bayar($o, 700000.01, 'Transfer')->assertSessionHas('error');
        $this->bayar($o, 700000)->assertSessionHas('success');

        $this->assertEquals('Lunas', $o->fresh()->status_pembayaran);
    }

    public function test_bukti_path_bebas_dari_klien_ditolak(): void
    {
        $o = $this->order(500000);

        foreach (['../../.env', 'jersey_bukti_bayar/../../.env', 'lain/berkas.jpg', 'jersey_bukti_bayar/tidak-ada.jpg'] as $path) {
            $this->post("/jersey/keuangan/{$o->id}/bayar", [
                'tanggal_bayar' => now()->toDateString(), 'jumlah' => 100000, 'metode_bayar' => 'Tunai', 'bukti_path' => $path,
            ]);
        }

        $this->assertEquals(0, DB::table('jersey_order_payments')->count());
    }

    /** Ringkasan Keuangan Jersey dihitung SQL - harus identik dengan aturan per-model (piutang, jatuh tempo = deadline hari ini/lewat & belum lunas). */
    public function test_ringkasan_keuangan_jersey_dari_sql_sama_dengan_aturan_per_order(): void
    {
        $buat = fn (string $no, ?string $deadline, float $total, float $bayar = 0) => JerseyOrder::create([
            'order_number' => $no, 'order_date' => now()->toDateString(), 'customer_name' => $no, 'deadline' => $deadline,
            'additional_charge' => $total,
        ]);
        $kemarin = $buat('JO-KMR', now()->subDay()->toDateString(), 100000);
        $hariIni = $buat('JO-INI', now()->toDateString(), 200000);
        $besok = $buat('JO-BSK', now()->addDay()->toDateString(), 300000);
        $tanpa = $buat('JO-NUL', null, 400000);
        $lunasTelat = $buat('JO-LNS', now()->subDays(3)->toDateString(), 50000);
        foreach ([$kemarin, $hariIni, $besok, $tanpa, $lunasTelat] as $o) {
            $o->recalculateTotal();
        }
        $this->bayar($lunasTelat->fresh(), 50000)->assertSessionHas('success');
        $this->bayar($hariIni->fresh(), 50000)->assertSessionHas('success'); // DP: sisa 150.000

        $r = $this->get('/jersey/keuangan')->assertOk();

        // Belum lunas: kemarin 100k + hari ini (200k-50k) + besok 300k + tanpa deadline 400k = 950k; order lunas tidak dihitung.
        $this->assertEquals(950000, (float) $r->viewData('totalPiutang'));
        // Telat: kemarin + hari ini (lunas tidak dihitung, besok & tanpa deadline bukan telat) = 2.
        $this->assertSame(2, $r->viewData('jumlahJatuhTempo'));
        // Konsisten dengan aturan per-model.
        $perModel = JerseyOrder::whereIn('status_pembayaran', ['Belum Bayar', 'DP'])->get()->filter(fn ($o) => $o->jatuh_tempo)->count();
        $this->assertSame($perModel, $r->viewData('jumlahJatuhTempo'));
    }
}
