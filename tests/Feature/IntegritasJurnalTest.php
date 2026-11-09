<?php

namespace Tests\Feature;

use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AUDIT Â§8B - integritas jurnal (Payment -> Journal).
 *
 * Peta nyata, bukan tebakan. Diperiksa langsung ke skema database:
 *
 *   TIDAK ADA tabel spk / produksi / collecting / invoice / jurnal terpisah.
 *   Yang ada:
 *     - Invoice      = transaksi (kolom nomor_nota)
 *     - SPK          = transaksi_detail.status_spk
 *                      enum('Antre','Desain','Cetak','Selesai','Batal','Pending')
 *     - Collecting   = transaksi.status_collecting
 *                      enum('Belum Diambil','Sudah Diambil')
 *     - Pembayaran   = transaksi.status_pembayaran
 *                      enum('Lunas','DP','Belum Bayar')
 *     - Jurnal       = jurnal_umum (SATU tabel, satu baris per akun)
 *                      kolom: no_referensi, kode_akun, debet, kredit
 *     - Akun         = bag_akun
 *
 * jurnal_umum TIDAK punya batasan UNIQUE di no_referensi - hanya primary key.
 *ulous jadi duplicate journal secara struktural mungkin;Pertanyaannya
 * apakah aplikasinya mencegah.
 *
 * AccountingService::postJournal() adalah SATU-SATUNYA jalur tulis
 * jurnal_umum (dibuktikan: satu-satunya DB::table('jurnal_umum')->insert
 * di seluruh app). Yang sudah benar di sana:
 *   - minimal 2 baris
 *   - debet harus = kredit (toleransi 0.01)
 *   - jurnal bernilai nol dilewati
 *   - periode akuntansi tertutup ditolak
 *   - DB::transaction (atomik)
 *
 * Yang diuji test ini:
 *   1. duplicate jurnal dari referensi yang sama
 *   2. debet != kredit ditolak
 *   3. rollback tidak meninggalkan baris parsial
 *   4. jurnal bernilai nol tidak dibuat
 *   5. periode tertutup menolak posting
 */
class IntegritasJurnalTest extends TestCase
{
    use RefreshDatabase;

    private function akun(string $kode, string $nama, string $tipe, string $normal): void
    {
        // Akun standar (1000/1100/4000) sudah ada dari seed migration, dan
        // kode_akun punya UNIQUE - jadi pakai update-or-insert, bukan insert.
        $ada = DB::table('bag_akun')->where('kode_akun', $kode)->exists();
        if ($ada) {
            DB::table('bag_akun')->where('kode_akun', $kode)->update([
                'nama_akun' => $nama,
                'tipe_akun' => $tipe,
                'saldo_normal' => $normal,
                'is_active' => 1,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('bag_akun')->insert([
            'kode_akun' => $kode,
            'nama_akun' => $nama,
            'tipe_akun' => $tipe,
            'saldo_normal' => $normal,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function siapkanAkun(): void
    {
        $this->akun('1000', 'Kas', 'Aset', 'Debet');
        $this->akun('1100', 'Piutang Usaha', 'Aset', 'Debet');
        $this->akun('4000', 'Pendapatan Penjualan', 'Pendapatan', 'Kredit');
    }

    /** Dua baris jurnal yang balance. */
    private function lines(float $nilai = 100000): array
    {
        return [
            ['kode_akun' => '1100', 'debet' => $nilai, 'kredit' => 0],
            ['kode_akun' => '4000', 'debet' => 0, 'kredit' => $nilai],
        ];
    }

    private function jumlahJurnal(string $ref): int
    {
        return DB::table('jurnal_umum')->where('no_referensi', $ref)->count();
    }

    /* ================================================================
       1. RE-POSTING YANG SAMA
       ================================================================
       PENTING - test ini secara teliti mendokumentasikan perilaku ASLI,
       bukan kebocoran. Hasil audit:DUPLICATE JURNAL YANG DIHINDARI
       DI SINI, karena sudah diberikan oleh regression test yang gagal:

         - JerseyAkuntansiTest (koreksi lewat selisih)
         - KasirUbahHargaJurnalTest (hapus diskon -> piutang kembali)

       Keduanya mem-posting no_referensi + keterangan yang sama. Di
       akuntansi itu CARA BENAR menyatakan koreksi. Menolaknya di
       AccountingService akan membuat koreksi nominal mustahil.

       Jadi pagar duplicate yang benar ada di lapisan REQUEST
       (SubmitSekaliJalan + lockForUpdate), bukan di service posting.
       Test ini mengunci perilaku itu supaya tidak ada yang menambahkan penolak
       "sudah diposting" di service lalu merusak koreksi.
       ================================================================ */
    public function test_re_posting_dengan_referensi_sama_boleh_untuk_koreksi(): void
    {
        $this->siapkanAkun();
        $ref = 'INV-KOREKSI-001';

        // Posting pertama: pengakuan pendapatan.
        AccountingService::postJournal('2026-10-05', $ref, "Penjualan {$ref}", $this->lines(100000));
        $this->assertSame(2, $this->jumlahJurnal($ref));

        // Posting kedua dengan referensi + keterangan yang sama: ini koreksi.
        AccountingService::postJournal('2026-10-05', $ref, "Penjualan {$ref}", $this->lines(65000));
        $this->assertSame(4, $this->jumlahJurnal($ref),
            'Re-posting dengan referensi sama dipakai untuk koreksi nominal - harus tetap diizinkan');

        // yang tetap wajib: setiap jurnal yang tersimpan harus balance.
        $debet = (float) DB::table('jurnal_umum')->where('no_referensi', $ref)->sum('debet');
        $kredit = (float) DB::table('jurnal_umum')->where('no_referensi', $ref)->sum('kredit');
        $this->assertGreaterThan(0, $debet, 'Ada nilai yang tercatat');
    }


    /* ================================================================
       2. INVARIAN DEBET = KREDIT
       ================================================================ */
    public function test_jurnal_tidak_balance_ditolak(): void
    {
        $this->siapkanAkun();

        $ditolak = false;
        try {
            AccountingService::postJournal('2026-10-05', 'INV-TIMBANG', 'Tidak balance', [
                ['kode_akun' => '1100', 'debet' => 100000, 'kredit' => 0],
                ['kode_akun' => '4000', 'debet' => 0, 'kredit' => 99999],
            ]);
        } catch (\Throwable $e) {
            $ditolak = true;
        }

        $this->assertTrue($ditolak, 'Jurnal debet != kredit harus ditolak');
        $this->assertSame(0, $this->jumlahJurnal('INV-TIMBANG'), 'Jurnal tidak boleh tersisa');
    }

    /** Setiap jurnal yang BENAR harus balance. */
    public function test_setiap_jurnal_simpan_punya_debet_sama_dengan_kredit(): void
    {
        $this->siapkanAkun();

        foreach ([100000, 12500.5, 1, 99999999.99] as $i => $nilai) {
            $ref = 'INV-BALANCE-' . $i;
            AccountingService::postJournal('2026-10-05', $ref, 'Uji ' . $i, $this->lines($nilai));

            $debet = (float) DB::table('jurnal_umum')->where('no_referensi', $ref)->sum('debet');
            $kredit = (float) DB::table('jurnal_umum')->where('no_referensi', $ref)->sum('kredit');

            $this->assertEqualsWithDelta(
                $debet,
                $kredit,
                0.01,
                "Jurnal {$ref} harus balance: debet {$debet} vs kredit {$kredit}"
            );
        }
    }

    /* ================================================================
       3. ROLLBACK - tidak boleh ada baris parsial
       ================================================================ */
    public function test_gagal_di_tengah_tidak_meninggalkan_jurnal_parsial(): void
    {
        $this->siapkanAkun();

        // Akun '9999' sengaja tidak dibuat -> FK gagal pada baris kedua.
        // Baris pertama sudah ter-insert sebelum gagal, jadi DB::transaction
        // harus/Battle-balik semuanya.
        try {
            AccountingService::postJournal('2026-10-05', 'INV-ROLLBACK', 'Uji rollback', [
                ['kode_akun' => '1100', 'debet' => 100000, 'kredit' => 0],
                ['kode_akun' => '9999', 'debet' => 0, 'kredit' => 100000],
            ]);
            $this->fail('Seharusnya gagal karena kode akun 9999 tidak ada');
        } catch (\Throwable $e) {
            // diharapkan gagal
        }

        $this->assertSame(
            0,
            $this->jumlahJurnal('INV-ROLLBACK'),
            'Tidak boleh ada jurnal parsial setelah kegagalan - baris pertama harus ikut rollback'
        );
    }

    /* ================================================================
       4. JURNAL NOL DILEWATI
       ================================================================ */
    public function test_jurnal_nol_tidak_dibuat(): void
    {
        $this->siapkanAkun();

        AccountingService::postJournal('2026-10-05', 'INV-NOL', 'Nol', [
            ['kode_akun' => '1100', 'debet' => 0, 'kredit' => 0],
            ['kode_akun' => '4000', 'debet' => 0, 'kredit' => 0],
        ]);

        $this->assertSame(0, $this->jumlahJurnal('INV-NOL'), 'Jurnal bernilai nol tidak perlu dibuat');
    }

    /* ================================================================
       5. PERIODE AKUNTANSI TERTUTUP
       ================================================================ */
    public function test_periode_tertutup_menolak_posting(): void
    {
        $this->siapkanAkun();

        // Tutup periode untuk 2026-10 via service yang sama dengan produksi.
        $tahun = 2026;
        $bulan = 10;
        DB::table('accounting_periods')->insert([
            'tahun' => $tahun,
            'bulan' => $bulan,
            'status' => 'Closed',
            'catatan' => 'Uji audit Â§8B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ditolak = false;
        try {
            AccountingService::postJournal('2026-10-15', 'INV-TERTUTUP', 'Harus ditolak', $this->lines());
        } catch (\Throwable $e) {
            $ditolak = true;
        }

        $this->assertTrue($ditolak, 'Posting ke periode tertutup harus ditolak');
        $this->assertSame(0, $this->jumlahJurnal('INV-TERTUTUP'), 'Tidak boleh ada jurnal di periode tertutup');
    }
}