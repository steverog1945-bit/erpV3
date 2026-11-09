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
 * Enam pelunasan Rp 999,60 yang sama untuk satu faktur bersisa Rp 999,60
 * dikirim bersamaan dari proses PHP terpisah: tepat satu tercatat (lock
 * faktur + hitung ulang sisa di PembayaranHutangService::bayar).
 *
 * Tanpa RefreshDatabase: data harus ter-commit agar terlihat proses lain.
 */
class PelunasanHutangParalelTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = [];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
    }

    protected function tearDown(): void
    {
        $this->artisan('migrate:fresh');
        parent::tearDown();
    }

    public function test_enam_pelunasan_faktur_bersamaan_hanya_satu_yang_tercatat(): void
    {
        $idSup = DB::table('supplier')->insertGetId(['nama_supplier' => 'PT Par', 'kontak' => '-']);
        $gudang = DB::table('gudang')->insertGetId(['nama_gudang' => 'Utama']);
        $ked = DB::table('kedatangan_bahan')->insertGetId(['no_surat_jalan' => 'SJ-PARH', 'id_supplier' => $idSup, 'id_gudang' => $gudang, 'tgl_datang' => now()->toDateString(), 'status' => 'Selesai']);
        $id = DB::table('pembelian_bahan')->insertGetId([
            'id_kedatangan' => $ked, 'no_faktur' => 'FAK-PARH', 'tgl_transaksi' => now()->toDateString(),
            'tipe_pembelian' => 'Tempo', 'status_pembayaran' => 'Hutang', 'jatuh_tempo_tgl' => now()->addDays(30)->toDateString(),
            'grand_total' => 999.60,
        ]);
        AccountingService::postPembelianBahan('FAK-PARH', 999.60, 'Tempo');

        $db = config('database.connections.mysql');
        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_HOST' => $db['host'], 'DB_PORT' => (string) $db['port'],
            'DB_DATABASE' => $db['database'], 'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => (string) $db['password'],
            'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'APP_KEY' => config('app.key'),
        ];
        $mulai = sprintf('%.4f', microtime(true) + 3.0);
        $proses = [];
        for ($i = 0; $i < 6; $i++) {
            $muatan = base64_encode(json_encode(['uri' => "/pembelian-bahan/pelunasan/{$id}", 'metode' => 'PATCH',
                'data' => ['nominal' => 999.60, 'metode' => 'Transfer', 'tanggal' => now()->toDateString()]]));
            $p = new Process([PHP_BINARY, base_path('tests/paralel/permintaan.php'), (string) $this->admin->id_user, $mulai, $muatan], base_path(), $env);
            $p->setTimeout(60);
            $p->start();
            $proses[] = $p;
        }
        $hasil = array_map(function (Process $p) {
            $p->wait();
            $this->assertSame(0, $p->getExitCode(), 'Proses anak gagal: ' . $p->getErrorOutput() . $p->getOutput());

            return json_decode($p->getOutput(), true);
        }, $proses);

        $this->assertSame(1, DB::table('pembayaran_hutang')->where('id_pembelian', $id)->whereNull('voided_at')->count(), json_encode($hasil));
        $this->assertEquals(999.60, (float) DB::table('pembayaran_hutang')->where('id_pembelian', $id)->sum('nominal'));
        $this->assertSame('Lunas', DB::table('pembelian_bahan')->where('id_pembelian', $id)->value('status_pembayaran'));
        $this->assertEquals((float) DB::table('jurnal_umum')->sum('debet'), (float) DB::table('jurnal_umum')->sum('kredit'));
    }
}
