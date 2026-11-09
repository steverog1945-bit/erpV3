<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PembelianEditTanggalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idKedatangan;
    private int $idPembelian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role,
        ]);
        DB::table('roles')->where('nama_role', 'Administrator')->update(['boleh_lihat_harga' => true]);

        DB::table('menus')->insertOrIgnore(['id_menu' => 20, 'nama_menu' => 'Pembelian Bahan', 'url_link' => 'pembelian-bahan', 'parent_id' => 0]);
        DB::table('role_permissions')->insertOrIgnore(['id_role' => $this->admin->id_role, 'id_menu' => 20, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1]);

        $idSupplier = DB::table('supplier')->insertGetId(['nama_supplier' => 'Supplier Uji', 'kontak' => '08123456789']);

        $this->idKedatangan = DB::table('kedatangan_bahan')->insertGetId([
            'no_surat_jalan' => 'SJ-UJI-001',
            'id_supplier' => $idSupplier,
            'tgl_datang' => now()->toDateString(),
            'status' => 'Selesai',
        ]);

        $idMesin = DB::table('mesin')->insertGetId(['nama_mesin' => 'Mesin Uji', 'tipe_mesin' => 'Cetak']);
        $idKat = DB::table('kategori')->insertGetId(['nama_kategori' => 'Kategori Uji', 'kode_kategori' => 'KU']);
        $idBahan = DB::table('bahan_baku')->insertGetId(['kode_bahan' => 'BU-001', 'nama_bahan' => 'Bahan Uji', 'id_mesin' => $idMesin, 'id_kategori' => $idKat, 'stok' => 10, 'nilai_konversi' => 1, 'harga_rata_rata' => 10000, 'satuan_stok' => 'lembar']);
        DB::table('kedatangan_bahan_detail')->insert([
            'id_kedatangan' => $this->idKedatangan,
            'id_bahan' => $idBahan,
            'qty' => 10,
            'satuan' => 'Pcs',
            'status' => 'Selesai',
        ]);

        $this->idPembelian = DB::table('pembelian_bahan')->insertGetId([
            'id_kedatangan' => $this->idKedatangan,
            'no_faktur' => 'FAK-UJI-001',
            'tgl_transaksi' => now()->subDays(10)->toDateString(),
            'tipe_pembelian' => 'Tempo',
            'jatuh_tempo_hari' => 30,
            'jatuh_tempo_tgl' => now()->subDays(10)->addDays(30)->toDateString(),
            'status_pembayaran' => 'Hutang',
            'grand_total' => 100000,
        ]);
    }

    public function test_ubah_tanggal_index_berubah(): void
    {
        $tglBaru = now()->subDays(5)->toDateString();
        $r = $this->actingAs($this->admin)->put("/pembelian-bahan/{$this->idPembelian}", [
            'tgl_transaksi' => $tglBaru,
            '_token' => csrf_token(),
        ]);
        $r->assertRedirect(route('pembelian-bahan.index'));
        $this->assertSame($tglBaru, DB::table('pembelian_bahan')->where('id_pembelian', $this->idPembelian)->value('tgl_transaksi'));
    }

    public function test_jatuh_tempo_ikut_berubah(): void
    {
        $tglBaru = now()->subDays(5)->toDateString();
        $jtBaru = \Carbon\Carbon::parse($tglBaru)->addDays(30)->toDateString();
        $this->actingAs($this->admin)->put("/pembelian-bahan/{$this->idPembelian}", [
            'tgl_transaksi' => $tglBaru,
            '_token' => csrf_token(),
        ]);
        $this->assertSame($jtBaru, DB::table('pembelian_bahan')->where('id_pembelian', $this->idPembelian)->value('jatuh_tempo_tgl'));
    }

    public function test_tunai_tidak_diisi_jatuh_tempo(): void
    {
        DB::table('pembelian_bahan')->where('id_pembelian', $this->idPembelian)->update([
            'tipe_pembelian' => 'Cash',
            'jatuh_tempo_hari' => null,
            'jatuh_tempo_tgl' => null,
        ]);
        $tglBaru = now()->subDays(5)->toDateString();
        $this->actingAs($this->admin)->put("/pembelian-bahan/{$this->idPembelian}", [
            'tgl_transaksi' => $tglBaru,
            '_token' => csrf_token(),
        ]);
        $this->assertNull(DB::table('pembelian_bahan')->where('id_pembelian', $this->idPembelian)->value('jatuh_tempo_tgl'));
    }

    public function test_periode_tertutup_lama_ditolak(): void
    {
        $tglLama = DB::table('pembelian_bahan')->where('id_pembelian', $this->idPembelian)->value('tgl_transaksi');
        $carbon = \Carbon\Carbon::parse($tglLama);
        DB::table('accounting_periods')->insert([
            'tahun' => $carbon->year,
            'bulan' => $carbon->month,
            'status' => 'Closed',
            'closed_by' => $this->admin->id_user,
            'closed_at' => now(),
        ]);

        // Tanggal baru di bulan berbeda (September, masih terbuka) supaya validasi lolos,
        // lalu controller menolak karena periode tanggal LAMA tertutup.
        $tglBaru = '2026-09-20';
        $r = $this->actingAs($this->admin)->put("/pembelian-bahan/{$this->idPembelian}", [
            'tgl_transaksi' => $tglBaru,
            '_token' => csrf_token(),
        ]);
        $r->assertSessionHas('error');
        $this->assertSame($tglLama, DB::table('pembelian_bahan')->where('id_pembelian', $this->idPembelian)->value('tgl_transaksi'), 'Tanggal tidak boleh berubah');
    }

    public function test_periode_tertutup_baru_ditolak(): void
    {
        // Tanggal baru di September (ditutup); tanggal lama Oktober (terbuka).
        $tglBaru = '2026-09-20';
        $carbon = \Carbon\Carbon::parse($tglBaru);
        DB::table('accounting_periods')->insert([
            'tahun' => $carbon->year,
            'bulan' => $carbon->month,
            'status' => 'Closed',
            'closed_by' => $this->admin->id_user,
            'closed_at' => now(),
        ]);

        $tglLama = DB::table('pembelian_bahan')->where('id_pembelian', $this->idPembelian)->value('tgl_transaksi');
        $r = $this->actingAs($this->admin)->put("/pembelian-bahan/{$this->idPembelian}", [
            'tgl_transaksi' => $tglBaru,
            '_token' => csrf_token(),
        ]);
        // Validasi TanggalTransaksi menolak periode tertutup -> redirect dengan errors.
        $r->assertSessionHasErrors('tgl_transaksi');
        $this->assertSame($tglLama, DB::table('pembelian_bahan')->where('id_pembelian', $this->idPembelian)->value('tgl_transaksi'), 'Tanggal tidak boleh berubah');
    }

    public function test_tanggal_sama_tidak_berubah(): void
    {
        $tglLama = DB::table('pembelian_bahan')->where('id_pembelian', $this->idPembelian)->value('tgl_transaksi');
        $auditBefore = DB::table('audit_logs')->where('auditable_type', 'pembelian_bahan')->count();

        $this->actingAs($this->admin)->put("/pembelian-bahan/{$this->idPembelian}", [
            'tgl_transaksi' => $tglLama,
            '_token' => csrf_token(),
        ]);

        $auditAfter = DB::table('audit_logs')->where('auditable_type', 'pembelian_bahan')->count();
        $this->assertSame($auditBefore, $auditAfter, 'Tidak boleh ada audit baru jika tanggal sama');
    }

    public function test_audit_tercatat(): void
    {
        $tglBaru = now()->subDays(5)->toDateString();
        $auditBefore = DB::table('audit_logs')->where('auditable_type', 'pembelian_bahan')->count();

        $this->actingAs($this->admin)->put("/pembelian-bahan/{$this->idPembelian}", [
            'tgl_transaksi' => $tglBaru,
            '_token' => csrf_token(),
        ]);

        $auditAfter = DB::table('audit_logs')->where('auditable_type', 'pembelian_bahan')->count();
        $this->assertGreaterThan($auditBefore, $auditAfter, 'Audit log harus bertambah');
    }
}



