<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * §8C — AUDIT GATE PRODUKSI → COLLECTING.
 *
 * Rule bisnis yang diuji (ditemukan dari kode aktual, bukan asumsi):
 *
 * CollectingController::proses()   app/Http/Controllers/CollectingController.php:101
 *
 *   1. lockForUpdate dulu (L120)                       <- tahan race
 *   2. tidak ditemukan            -> tolak             <- L122
 *   3. status_collecting sudah 'Sudah Diambil'
 *                                 -> tolak             <- L126 (idempoten)
 *   4. status_nota === 'Dibatalkan'-> tolak             <- L130
 *   5. status_pembayaran !== 'Lunas' DAN tidak ada
 *      konfirmasi+alasan            -> tolak             <- L138-146
 *   6. DETAIL BELUM SEMUA SELESAI  -> tolak             <- L148-157
 *        item_kelar = SUM(CASE WHEN status_spk IN
 *        ('Selesai','Batal') THEN 1 ELSE 0 END)
 *        syarat: item_kelar >= total_item
 *   7. kalau lolos: status_collecting='Sudah Diambil'  <- L159-164
 *
 * Implikasi penting: status 'Batal' dan 'Selesai' KEDUANYA dianggap selesai.
 * Detail dengan status_spk 'Antre'/'Desain'/'Cetak'/'Pending' menahan
 * collecting.
 *
 * Semua tabel diambil dari skema aktual:
 *   transaksi NOT NULL tanpa default: nomor_nota, id_konsumen,
 *     id_user_kasir, total_bayar, status_pembayaran
 *   transaksi_detail NOT NULL tanpa default: id_transaksi, nama_file,
 *     p_ukuran, l_ukuran, qty, subtotal
 */
class GateCollectingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private int $idKonsumen;

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
            'nama_konsumen' => 'Pelanggan Uji', 'telepon' => '08123', 'alamat' => '-', 'id_golongan' => $gol,
        ]);
    }

    /* ---------- helper ---------- */

    private function nota(int $total = 100000, string $pembayaran = 'Lunas', string $nota = 'Aktif', string $statusPengambilan = 'Belum Diambil'): int
    {
        return (int) DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'NOTA-' . uniqid(),
            'id_konsumen' => $this->idKonsumen,
            'id_user_kasir' => $this->admin->id_user,
            'total_bayar' => $total,
            'status_pembayaran' => $pembayaran,
            'status_nota' => $nota,
            'status_collecting' => $statusPengambilan,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function detail(int $idTransaksi, string $statusSpk, int $urut = 1): void
    {
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $idTransaksi,
            'id_produk' => null,
            'nama_file' => 'uji-' . $urut,
            'p_ukuran' => 10,
            'l_ukuran' => 10,
            'qty' => 1,
            'status_spk' => $statusSpk,
            'subtotal' => 10000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function statusCollecting(int $id): ?string
    {
        return DB::table('transaksi')->where('id_transaksi', $id)->value('status_collecting');
    }

    private function proses(int $id, array $ekstra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)
            ->patch(route('collecting.proses', $id), array_merge([], $ekstra));
    }

    /* ================================================================
       A. Produksi belum selesai -> HARUS ditolak, status TIDAK berubah
       ================================================================ */
    public function test_collecting_ditolak_bila_masih_ada_item_belum_selesai(): void
    {
        $id = $this->nota();
        $this->detail($id, 'Selesai', 1);
        $this->detail($id, 'Cetak', 2);   // <- masih dikerjakan
        $this->detail($id, 'Desain', 3);  // <- masih dikerjakan

        $r = $this->proses($id);

        $r->assertSessionHas('error');
        $this->assertSame(
            'Belum Diambil',
            $this->statusCollecting($id),
            'Status collecting TIDAK boleh berubah saat masih ada item belum selesai'
        );
        $this->assertStringContainsString(
            'belum selesai',
            (string) session('error'),
            'Pesan harus menjelaskan alasan penolakan'
        );
    }

    /* ================================================================
       B. Semua detail selesai (Selesai) -> SUCCESS, status berubah sekali
       ================================================================ */
    public function test_collecting_berhasil_bila_semua_detail_selesai(): void
    {
        $id = $this->nota();
        $this->detail($id, 'Selesai', 1);
        $this->detail($id, 'Selesai', 2);
        $this->detail($id, 'Selesai', 3);

        $r = $this->proses($id);

        $r->assertSessionHas('success');
        $this->assertSame('Sudah Diambil', $this->statusCollecting($id));

        // Tidak ada perubahan samping ke kolom lain.
        $this->assertSame('Lunas', DB::table('transaksi')->where('id_transaksi', $id)->value('status_pembayaran'));
        $this->assertSame('Aktif', DB::table('transaksi')->where('id_transaksi', $id)->value('status_nota'));
        $this->assertSame(3, DB::table('transaksi_detail')->where('id_transaksi', $id)->count());
    }

    /* ================================================================
       C. Batal dianggap selesai (tidak menahan collecting)
       ================================================================ */
    public function test_detail_batal_dihitung_selesai_untuk_collecting(): void
    {
        $id = $this->nota();
        $this->detail($id, 'Selesai', 1);
        $this->detail($id, 'Batal', 2);   // batal = tidak perlu diambil

        $r = $this->proses($id);

        $r->assertSessionHas('success');
        $this->assertSame('Sudah Diambil', $this->statusCollecting($id));
    }

    /* ================================================================
       D. Antre/Desain/Cetak/Pending menahan collecting
       ================================================================ */
    public function test_status_belum_selesai_menahan_collecting(): void
    {
        foreach (['Antre', 'Desain', 'Cetak', 'Pending'] as $status) {
            $id = $this->nota();
            $this->detail($id, 'Selesai', 1);
            $this->detail($id, $status, 2);

            $r = $this->proses($id);

            $r->assertSessionHas('error');
            $this->assertSame(
                'Belum Diambil',
                $this->statusCollecting($id),
                "Status_spk '{$status}' harus menahan collecting"
            );
        }
    }

    /* ================================================================
       E. Detail campuran: sebagian selesai, sebagian belum -> DITOLAK
       ================================================================ */
    public function test_detail_campuran_sebagian_belum_selesai_ditolak(): void
    {
        $id = $this->nota();
        $this->detail($id, 'Selesai', 1);
        $this->detail($id, 'Selesai', 2);
        $this->detail($id, 'Cetak', 3);

        $r = $this->proses($id);

        $r->assertSessionHas('error');
        $this->assertSame('Belum Diambil', $this->statusCollecting($id));
        $this->assertStringContainsString('1 item yang belum selesai', (string) session('error'));
    }

    /* ================================================================
       F. Nota belum lunas tanpa konfirmasi -> ditolak
       ================================================================ */
    public function test_nota_belum_lunas_tanpa_konfirmasi_ditolak(): void
    {
        $id = $this->nota(total: 100000, pembayaran: 'Belum Bayar');
        $this->detail($id, 'Selesai', 1);

        $r = $this->proses($id);

        $r->assertSessionHas('error');
        $this->assertSame('Belum Diambil', $this->statusCollecting($id));
        $this->assertStringContainsString('belum lunas', (string) session('error'));
    }

    /** Nota belum lunas DENGAN konfirmasi+alasan yang memadai -> boleh. */
    public function test_nota_belum_lunas_dengan_konfirmasi_dan_alasan_boleh_diambil(): void
    {
        $id = $this->nota(total: 100000, pembayaran: 'Belum Bayar');
        $this->detail($id, 'Selesai', 1);

        $r = $this->proses($id, [
            'konfirmasi_belum_lunas' => '1',
            'alasan_belum_lunas' => 'Pelanggan janji bayar minggu depan',
        ]);

        $r->assertSessionHas('success');
        $this->assertSame('Sudah Diambil', $this->statusCollecting($id));
    }

    /** Alasan terlalu pendek (< 5 huruf) harus tetap ditolak. */
    public function test_alasan_belum_lunas_terlalu_pendek_ditolak(): void
    {
        $id = $this->nota(total: 100000, pembayaran: 'Belum Bayar');
        $this->detail($id, 'Selesai', 1);

        $r = $this->proses($id, [
            'konfirmasi_belum_lunas' => '1',
            'alasan_belum_lunas' => 'ok',  // 2 huruf
        ]);

        $r->assertSessionHas('error');
        $this->assertSame('Belum Diambil', $this->statusCollecting($id));
    }

    /* ================================================================
       G. Nota dibatalkan -> ditolak
       ================================================================ */
    public function test_nota_dibatalkan_tidak_bisa_di_collecting(): void
    {
        $id = $this->nota(nota: 'Dibatalkan');
        $this->detail($id, 'Selesai', 1);

        $r = $this->proses($id);

        $r->assertSessionHas('error');
        $this->assertSame('Belum Diambil', $this->statusCollecting($id));
        $this->assertStringContainsString('dibatalkan', (string) session('error'));
    }

    /* ================================================================
       H. Idempotensi: sudah diambil tidak boleh berubah lagi
       ================================================================ */
    public function test_collecting_kedua_ditolak(): void
    {
        $id = $this->nota();
        $this->detail($id, 'Selesai', 1);

        $this->proses($id)->assertSessionHas('success');
        $this->assertSame('Sudah Diambil', $this->statusCollecting($id));

        $r = $this->proses($id);

        $r->assertSessionHas('error');
        $this->assertStringContainsString('sudah ditandai', (string) session('error'));
        $this->assertSame('Sudah Diambil', $this->statusCollecting($id), 'Status tidak boleh berubah pada percobaan kedua');
    }

    /* ================================================================
       I. Manipulasi status dari client tidak dipakai
       ================================================================ */
    public function test_klien_mengirim_status_tidak_memengaruhi_gate(): void
    {
        // Detail belum selesai, tapi client coba kirim status_spk='Selesai'
        // dan status_collecting='Sudah Diambil' untuk memaksa lolos.
        $id = $this->nota();
        $this->detail($id, 'Cetak', 1);

        $r = $this->proses($id, [
            'status_collecting' => 'Sudah Diambil',
            'status_spk' => 'Selesai',
            'status' => 'Selesai',
        ]);

        $r->assertSessionHas('error');
        $this->assertSame(
            'Belum Diambil',
            $this->statusCollecting($id),
            'Nilai dari field form tidak boleh menimpa gate server'
        );

        // status_spk detail juga harus TIDAK ikut berubah.
        $this->assertSame(
            'Cetak',
            DB::table('transaksi_detail')->where('id_transaksi', $id)->value('status_spk'),
            'status_spk detail tidak boleh berubah dari request collecting'
        );
    }

    /* ================================================================
       J. Transaksi tanpa detail -> ditolak
       ================================================================ */
    public function test_transaksi_tanpa_detail_tidak_bisa_di_collecting(): void
    {
        $id = $this->nota();   // TIDAK ada detail

        $r = $this->proses($id);

        $r->assertSessionHas('error');
        $this->assertSame('Belum Diambil', $this->statusCollecting($id));
    }

    /* ================================================================
       K. Proses adalah PATCH - GET harus ditolak
       ================================================================ */
    public function test_get_ke_endpoint_proses_ditolak(): void
    {
        $id = $this->nota();
        $this->detail($id, 'Selesai', 1);

        $r = $this->actingAs($this->admin)->get("/collecting/{$id}/proses");

        $this->assertContains($r->status(), [405, 404], 'GET tidak boleh memproses collecting');
        $this->assertSame('Belum Diambil', $this->statusCollecting($id));
    }

    /* ================================================================
       L. Tanpa login ditolak
       ================================================================ */
    public function test_proses_ditolak_tanpa_login(): void
    {
        $id = $this->nota();
        $this->detail($id, 'Selesai', 1);

        $r = $this->patch(route('collecting.proses', $id));

        $this->assertTrue($r->isRedirect() || $r->status() === 401 || $r->status() === 403);
        $this->assertSame('Belum Diambil', $this->statusCollecting($id));
    }
}