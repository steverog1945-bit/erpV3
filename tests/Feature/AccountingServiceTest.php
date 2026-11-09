<?php

namespace Tests\Feature;

use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Bukan Unit test murni (butuh tabel jurnal_umum & bag_akun sungguhan,
 * bukan cuma logika PHP terisolasi) - ditaruh di Feature karena perlu
 * RefreshDatabase. Menguji invarian inti yang audit Phase 5/7 temukan
 * rawan: jurnal harus SELALU balance (debet=kredit), tidak boleh ada
 * baris timpang tersimpan.
 */
class AccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function buatAkun(string $kode, string $nama, string $tipe, string $saldoNormal): void
    {
        // Idempoten - migration bag_akun sudah menyeed akun standar
        // (termasuk 1000 Kas & 4000 Pendapatan Penjualan yang dipakai test
        // ini), jadi insert polos akan bentrok unique constraint.
        if (DB::table('bag_akun')->where('kode_akun', $kode)->exists()) {
            return;
        }

        DB::table('bag_akun')->insert([
            'kode_akun' => $kode,
            'nama_akun' => $nama,
            'tipe_akun' => $tipe,
            'saldo_normal' => $saldoNormal,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_jurnal_balance_tersimpan_dengan_benar(): void
    {
        $this->buatAkun('1000', 'Kas', 'Aset', 'Debet');
        $this->buatAkun('4000', 'Pendapatan Penjualan', 'Pendapatan', 'Kredit');

        AccountingService::postJournal(now()->toDateString(), 'TEST-001', 'Test penjualan tunai', [
            ['kode_akun' => '1000', 'debet' => 100000, 'kredit' => 0],
            ['kode_akun' => '4000', 'debet' => 0, 'kredit' => 100000],
        ]);

        $this->assertDatabaseHas('jurnal_umum', [
            'no_referensi' => 'TEST-001',
            'kode_akun' => '1000',
            'debet' => 100000,
        ]);
        $this->assertDatabaseHas('jurnal_umum', [
            'no_referensi' => 'TEST-001',
            'kode_akun' => '4000',
            'kredit' => 100000,
        ]);

        $totalDebet = DB::table('jurnal_umum')->where('no_referensi', 'TEST-001')->sum('debet');
        $totalKredit = DB::table('jurnal_umum')->where('no_referensi', 'TEST-001')->sum('kredit');
        $this->assertEquals($totalDebet, $totalKredit, 'Jurnal harus selalu balance debet=kredit');
    }

    public function test_jurnal_tidak_balance_ditolak_dan_tidak_tersimpan(): void
    {
        $this->buatAkun('1000', 'Kas', 'Aset', 'Debet');
        $this->buatAkun('4000', 'Pendapatan Penjualan', 'Pendapatan', 'Kredit');

        $this->expectException(\RuntimeException::class);

        try {
            AccountingService::postJournal(now()->toDateString(), 'TEST-002', 'Test tidak balance', [
                ['kode_akun' => '1000', 'debet' => 100000, 'kredit' => 0],
                ['kode_akun' => '4000', 'debet' => 0, 'kredit' => 50000],
            ]);
        } finally {
            // Harus TIDAK ada baris apapun tersimpan - baik gagal total
            // maupun (yang jauh lebih buruk) cuma separuh tersimpan.
            $this->assertDatabaseMissing('jurnal_umum', ['no_referensi' => 'TEST-002']);
        }
    }

    public function test_jurnal_dengan_nilai_nol_dilewati_tanpa_error(): void
    {
        $this->buatAkun('1000', 'Kas', 'Aset', 'Debet');
        $this->buatAkun('4000', 'Pendapatan Penjualan', 'Pendapatan', 'Kredit');

        // Tidak melempar exception, dan tidak menyimpan apapun (nilai
        // ekonomis nol, bukan transaksi sungguhan).
        AccountingService::postJournal(now()->toDateString(), 'TEST-003', 'Transaksi nol rupiah', [
            ['kode_akun' => '1000', 'debet' => 0, 'kredit' => 0],
            ['kode_akun' => '4000', 'debet' => 0, 'kredit' => 0],
        ]);

        $this->assertDatabaseMissing('jurnal_umum', ['no_referensi' => 'TEST-003']);
    }

    public function test_postjournal_aman_dipanggil_di_dalam_transaction_luar(): void
    {
        $this->buatAkun('1000', 'Kas', 'Aset', 'Debet');
        $this->buatAkun('4000', 'Pendapatan Penjualan', 'Pendapatan', 'Kredit');

        // Audit Phase 5 menemukan postJournal() sebelumnya rawan kalau
        // dipanggil standalone; sekarang dibungkus DB::transaction()
        // internal sendiri (pakai SAVEPOINT). Pastikan tetap berfungsi
        // normal saat dipanggil dari dalam transaction controller yang
        // sudah aktif (skenario nyata di semua controller pemanggilnya).
        DB::transaction(function () {
            AccountingService::postJournal(now()->toDateString(), 'TEST-004', 'Test nested transaction', [
                ['kode_akun' => '1000', 'debet' => 50000, 'kredit' => 0],
                ['kode_akun' => '4000', 'debet' => 0, 'kredit' => 50000],
            ]);
        });

        $this->assertDatabaseHas('jurnal_umum', ['no_referensi' => 'TEST-004']);
    }
}
