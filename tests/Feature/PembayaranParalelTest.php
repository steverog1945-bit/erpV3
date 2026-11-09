<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Audit konkurensi dengan proses SUNGGUHAN (bukan simulasi dalam satu proses): beberapa pembayaran untuk
 * nota yang sama dikirim pada saat yang sama dari proses PHP terpisah, seperti dua layar kasir yang
 * menekan Bayar bersamaan. Yang dijaga: uang yang tercatat tidak pernah melebihi tagihan, dan nomor
 * referensi jurnal pembayaran tidak pernah kembar.
 *
 * Tanpa RefreshDatabase: data harus benar-benar ter-commit supaya terlihat oleh proses lain.
 */
class PembayaranParalelTest extends TestCase
{
    use RefreshDatabase;

    /** Tanpa transaksi pembungkus: data harus ter-commit agar terlihat proses lain. Dibersihkan di tearDown. */
    protected $connectionsToTransact = [];

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kasir = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        DB::table('kas_harian')->insert(['tgl_kas' => now()->toDateString(), 'id_user' => $this->kasir->id_user, 'saldo_awal' => 0, 'status_lock' => 'open', 'created_at' => now()]);
    }

    protected function tearDown(): void
    {
        // Kembalikan database uji ke keadaan kosong untuk tes berikutnya.
        $this->artisan('migrate:fresh');
        parent::tearDown();
    }

    private function nota(string $nomor, float $total): int
    {
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'G' . $nomor]);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'U', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        $id = DB::table('transaksi')->insertGetId([
            'nomor_nota' => $nomor, 'tipe_order' => 'Reguler', 'id_konsumen' => $kons, 'id_user_kasir' => $this->kasir->id_user,
            'tgl_order' => now(), 'total_bayar' => $total, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir', 'status_collecting' => 'Belum Diambil',
        ]);
        DB::table('transaksi_detail')->insert([
            'id_transaksi' => $id, 'nama_file' => 'a.pdf', 'p_ukuran' => 1, 'l_ukuran' => 1, 'qty' => 1, 'status_urgent' => 0,
            'status_urgensi' => 'Normal', 'status_finishing' => 'Standar', 'subtotal' => $total, 'potongan' => 0, 'hpp_snapshot' => 0,
            'harga_jual_snapshot' => $total, 'status_spk' => 'Antre', 'biaya_cetak' => $total, 'biaya_desain' => 0,
        ]);
        AccountingService::postPenjualan($nomor, $total, 0);

        return $id;
    }

    /** @return array<int, array{status:int, ok:bool, error:?string}> */
    private function tembak(int|array $idTrx, string $nominal, int $jumlah, string $metode = 'Tunai'): array
    {
        $daftarNota = (array) $idTrx;
        $db = config('database.connections.mysql');
        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => (string) $db['password'],
            'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'APP_KEY' => config('app.key'),
        ];
        $mulai = sprintf('%.4f', microtime(true) + 3.0);
        $proses = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $p = new Process([PHP_BINARY, base_path('tests/paralel/bayar.php'), (string) $daftarNota[$i % count($daftarNota)], (string) $this->kasir->id_user, $nominal, "paralel-{$i}-" . uniqid(), $mulai, $metode], base_path(), $env);
            $p->setTimeout(60);
            $p->start();
            $proses[] = $p;
        }

        return array_map(function (Process $p) {
            $p->wait();
            $this->assertSame(0, $p->getExitCode(), 'Proses anak gagal: ' . $p->getErrorOutput() . $p->getOutput());

            return json_decode($p->getOutput(), true);
        }, $proses);
    }

    public function test_enam_pelunasan_bersamaan_hanya_satu_yang_tercatat(): void
    {
        $id = $this->nota('INV-PAR-1', 50000);

        $hasil = $this->tembak($id, '50.000', 6);

        $this->assertSame(1, DB::table('pembayaran_riwayat')->where('id_transaksi', $id)->count(), json_encode($hasil));
        $this->assertEquals(50000, (float) DB::table('pembayaran_riwayat')->where('id_transaksi', $id)->sum('nominal'));
        $this->assertSame(1, count(array_filter($hasil, fn ($h) => $h['ok'])));
        $this->assertSame('Lunas', DB::table('transaksi')->where('id_transaksi', $id)->value('status_pembayaran'));
        $this->assertJurnalBersih('INV-PAR-1', 50000);
    }

    public function test_enam_cicilan_bersamaan_tidak_melebihi_tagihan_dan_referensi_jurnal_tidak_kembar(): void
    {
        $id = $this->nota('INV-PAR-2', 50000);

        $hasil = $this->tembak($id, '10.000', 6); // 6 x 10.000 = 60.000 untuk tagihan 50.000

        $this->assertSame(5, DB::table('pembayaran_riwayat')->where('id_transaksi', $id)->count(), json_encode($hasil));
        $this->assertEquals(50000, (float) DB::table('pembayaran_riwayat')->where('id_transaksi', $id)->sum('nominal'));
        $this->assertSame(1, count(array_filter($hasil, fn ($h) => !$h['ok'])), 'Tepat satu cicilan harus ditolak');
        $this->assertJurnalBersih('INV-PAR-2', 50000);
    }

    public function test_deposit_tidak_bisa_dipakai_dua_kali_lewat_dua_nota_bersamaan(): void
    {
        // Satu pelanggan, saldo deposit 50.000, dua nota 50.000. Kunci nota berbeda, jadi yang menjaga hanya kunci pelanggan.
        $a = $this->nota('INV-PAR-3', 50000);
        $kons = DB::table('transaksi')->where('id_transaksi', $a)->value('id_konsumen');
        $b = $this->nota('INV-PAR-4', 50000);
        DB::table('transaksi')->where('id_transaksi', $b)->update(['id_konsumen' => $kons]);
        DB::table('konsumen_deposit')->insert(['id_konsumen' => $kons, 'tipe' => 'Setoran', 'nominal' => 50000, 'metode_bayar' => 'Tunai', 'tgl_deposit' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        AccountingService::postSetoranDeposit('DEP-PAR', 50000, 'Tunai');

        $hasil = $this->tembak([$a, $b], '50.000', 6, 'Deposit');

        $terpakai = (float) DB::table('konsumen_deposit')->where('id_konsumen', $kons)->where('tipe', 'Pemakaian')->sum('nominal');
        $this->assertEquals(50000, $terpakai, 'Deposit terpakai melebihi saldo: ' . json_encode($hasil));
        $this->assertSame(1, DB::table('pembayaran_riwayat')->whereIn('id_transaksi', [$a, $b])->count());
    }

    private function assertJurnalBersih(string $nomor, float $terbayar): void
    {
        $bayar = DB::table('jurnal_umum')->where('no_referensi', 'like', $nomor . '-BYR-%');
        // Tiap pembayaran = 2 baris jurnal dengan referensi sendiri; referensi kembar berarti penomoran balapan.
        $perRef = (clone $bayar)->selectRaw('no_referensi, count(*) c')->groupBy('no_referensi')->pluck('c');
        $this->assertSame([2], $perRef->unique()->values()->all(), 'Referensi jurnal pembayaran kembar: ' . $perRef->toJson());
        $this->assertEquals($terbayar, (float) (clone $bayar)->sum('debet'));
        $this->assertEquals((float) DB::table('jurnal_umum')->sum('debet'), (float) DB::table('jurnal_umum')->sum('kredit'));
        $piutang = DB::table('jurnal_umum')->where('kode_akun', '1100')->selectRaw('SUM(debet) - SUM(kredit) s')->value('s');
        $this->assertEquals(0, (float) $piutang, 'Piutang nota lunas harus nol');
    }
}
