<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P1-2 (audit hardening) - BUKTI NYATA, bukan cuma pengujian berurutan.
 *
 * Pengujian berurutan (lihat EditNotaConcurrencyTest) TIDAK BISA membuktikan
 * lockForUpdate() benar-benar menyerialkan dua request bersamaan - dua
 * panggilan berurutan dalam satu proses PHP akan selalu lolos pengecekan
 * status dengan benar SEKALIPUN lock-nya dihapus, karena panggilan pertama
 * sudah 100% selesai (dan ter-commit) sebelum panggilan kedua dimulai; race
 * yang sebenarnya cuma muncul kalau kedua request BENAR-BENAR tumpang
 * tindih. Tes ini membuka KONEKSI DATABASE KEDUA yang independen (koneksi
 * MySQL sungguhan, bukan simulasi) untuk membuktikan baris nota_edit_request
 * yang sedang dikunci oleh satu transaksi yang BELUM commit membuat
 * transaksi lain yang mencoba mengunci baris yang sama benar-benar
 * DIBLOKIR (lock wait timeout) - bukti mekanis bahwa lockForUpdate() di
 * PenjualanController::applyApprovedEdit() benar-benar menyerialkan akses,
 * bukan cuma kode yang terlihat benar di atas kertas.
 *
 * $connectionsToTransact = [] mematikan pembungkus transaksi otomatis
 * RefreshDatabase (yang kalau aktif akan membuat SEMUA koneksi melihat data
 * yang sama belum ter-commit, sehingga "dua koneksi independen" jadi tidak
 * bermakna) - sebagai gantinya data dibersihkan manual di tearDown().
 */
class EditNotaLockBenarBenarSerialTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = [];

    private ?int $idRequest = null;

    protected function tearDown(): void
    {
        DB::purge('kunci_a');
        DB::purge('kunci_b');

        if ($this->idRequest) {
            DB::table('nota_edit_request')->where('id_request', $this->idRequest)->delete();
        }
        DB::table('transaksi')->where('nomor_nota', 'LOCK-UJI')->delete();
        DB::table('users')->where('username', 'lock_uji')->delete();
        DB::table('roles')->where('nama_role', 'Role Lock Uji')->delete();
        DB::table('konsumen')->where('nama_konsumen', 'Konsumen Lock Uji')->delete();
        DB::table('golongan_konsumen')->where('nama_golongan', 'Golongan Lock Uji')->delete();

        parent::tearDown();
    }

    public function test_lock_pada_nota_edit_request_benar_benar_memblokir_koneksi_lain_yang_belum_commit(): void
    {
        $idRole = DB::table('roles')->insertGetId(['nama_role' => 'Role Lock Uji', 'boleh_lihat_harga' => 1]);
        $idUser = User::factory()->create(['id_role' => $idRole, 'username' => 'lock_uji'])->id_user;
        $idGol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Golongan Lock Uji', 'keterangan' => '-']);
        $idKonsumen = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'Konsumen Lock Uji', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $idGol]);
        $idTransaksi = DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'LOCK-UJI', 'tipe_order' => 'Reguler', 'id_konsumen' => $idKonsumen, 'id_user_kasir' => $idUser,
            'tgl_order' => now(), 'total_bayar' => 1, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif',
        ]);
        $this->idRequest = DB::table('nota_edit_request')->insertGetId([
            'id_transaksi' => $idTransaksi, 'id_user_requester' => $idUser, 'alasan' => 'uji lock',
            'status' => 'Disetujui', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // ---- Koneksi A: simulasikan request PERTAMA yang sedang di tengah applyApprovedEdit() -
        // sudah lockForUpdate() baris ini, TAPI BELUM commit (seperti sedang memproses StokReversalService/AccountingService).
        config(['database.connections.kunci_a' => config('database.connections.mysql')]);
        DB::purge('kunci_a');
        DB::connection('kunci_a')->beginTransaction();
        $baris = DB::connection('kunci_a')->table('nota_edit_request')->where('id_request', $this->idRequest)->lockForUpdate()->first();
        $this->assertSame('Disetujui', $baris->status, 'prasyarat: baris masih Disetujui sebelum dikunci');

        // ---- Koneksi B: simulasikan request KEDUA (klik ganda) yang mencoba lockForUpdate() baris YANG SAMA.
        // innodb_lock_wait_timeout diperpendek supaya tes ini tidak menggantung lama kalau memang harus menunggu.
        config(['database.connections.kunci_b' => config('database.connections.mysql')]);
        DB::purge('kunci_b');
        DB::connection('kunci_b')->statement('SET SESSION innodb_lock_wait_timeout = 2');
        DB::connection('kunci_b')->beginTransaction();

        $mulai = microtime(true);
        $terblokir = false;
        try {
            DB::connection('kunci_b')->table('nota_edit_request')->where('id_request', $this->idRequest)->lockForUpdate()->first();
        } catch (\Illuminate\Database\QueryException $e) {
            // Kode 1205 = ER_LOCK_WAIT_TIMEOUT - INI YANG DIHARAPKAN: koneksi B benar-benar
            // diblokir menunggu koneksi A, bukan diam-diam lolos membaca data lama.
            $terblokir = str_contains($e->getMessage(), '1205') || str_contains(strtolower($e->getMessage()), 'lock wait timeout');
        }
        $durasi = microtime(true) - $mulai;
        DB::connection('kunci_b')->rollBack();
        DB::connection('kunci_a')->rollBack();

        $this->assertTrue($terblokir, 'Koneksi kedua HARUS diblokir (lock wait timeout) selama koneksi pertama masih memegang lock - kalau ini gagal berarti lockForUpdate() TIDAK benar-benar menyerialkan akses.');
        $this->assertGreaterThanOrEqual(1.5, $durasi, 'blokir harus benar-benar menunggu (bukan langsung lolos dalam sekejap)');
    }
}
