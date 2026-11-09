<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaldoAwalCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_saldo_awal_berhasil_dicatat_dan_balance(): void
    {
        $this->artisan('akuntansi:saldo-awal')
            ->expectsQuestion('Kas (1000)', '5000000')
            ->expectsQuestion('Kas Kecil (1005)', '0')
            ->expectsQuestion('Bank (1010)', '10000000')
            ->expectsQuestion('Piutang Usaha (1100)', '0')
            ->expectsQuestion('Persediaan Bahan Baku (1200)', '2000000')
            ->expectsQuestion('Hutang Usaha (2000)', '1000000')
            ->expectsQuestion('Hutang Deposit Konsumen (2100)', '0')
            ->expectsConfirmation('Simpan sebagai saldo awal? Tindakan ini akan mengunci baris pertama jurnal_umum.', 'yes')
            ->assertExitCode(0);

        $rows = DB::table('jurnal_umum')->where('no_referensi', 'SALDO-AWAL')->get();

        // Kas+Bank+Persediaan (3 debet) + Hutang Usaha (1 kredit) + Modal (1 kredit) = 5 baris.
        // Piutang Usaha & Hutang Deposit Konsumen bernilai 0, dilewati.
        $this->assertCount(5, $rows);

        $totalDebet = $rows->sum('debet');
        $totalKredit = $rows->sum('kredit');
        $this->assertEquals($totalDebet, $totalKredit, 'Saldo awal harus balance');
        $this->assertEquals(17000000, $totalDebet); // 5jt+10jt+2jt

        // Modal = 17jt aset - 1jt hutang = 16jt, harus ikut tercatat di akun 3000
        $this->assertDatabaseHas('jurnal_umum', [
            'no_referensi' => 'SALDO-AWAL',
            'kode_akun' => '3000',
            'kredit' => 16000000,
        ]);
    }

    public function test_saldo_awal_ditolak_kalau_jurnal_sudah_ada_isinya(): void
    {
        DB::table('bag_akun')->insert([
            'kode_akun' => '9999', 'nama_akun' => 'Test', 'tipe_akun' => 'Aset',
            'saldo_normal' => 'Debet', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('jurnal_umum')->insert([
            'tgl_jurnal' => now()->toDateString(), 'no_referensi' => 'EXISTING', 'keterangan' => 'x',
            'kode_akun' => '9999', 'debet' => 1000, 'kredit' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('akuntansi:saldo-awal')->assertExitCode(1);

        $this->assertDatabaseMissing('jurnal_umum', ['no_referensi' => 'SALDO-AWAL']);
    }

    public function test_saldo_awal_menolak_hutang_lebih_besar_dari_aset(): void
    {
        $this->artisan('akuntansi:saldo-awal')
            ->expectsQuestion('Kas (1000)', '1000000')
            ->expectsQuestion('Kas Kecil (1005)', '0')
            ->expectsQuestion('Bank (1010)', '0')
            ->expectsQuestion('Piutang Usaha (1100)', '0')
            ->expectsQuestion('Persediaan Bahan Baku (1200)', '0')
            ->expectsQuestion('Hutang Usaha (2000)', '5000000')
            ->expectsQuestion('Hutang Deposit Konsumen (2100)', '0')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('jurnal_umum', ['no_referensi' => 'SALDO-AWAL']);
    }
}
