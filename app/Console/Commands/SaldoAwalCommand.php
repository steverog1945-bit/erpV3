<?php

namespace App\Console\Commands;

use App\Services\AccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mekanisme resmi untuk menetapkan SALDO AWAL akuntansi (Kas, Bank,
 * Piutang, Persediaan, Hutang) sebelum aplikasi mulai dipakai dengan
 * data transaksi sungguhan.
 *
 * LATAR BELAKANG (audit Phase 7): ditemukan akun Kas & Piutang Usaha
 * bersaldo NEGATIF di data pengembangan - bukan karena bug pencatatan
 * (tiap jurnal tetap balance), tapi karena TIDAK ADA entri saldo awal
 * sama sekali sebelum transaksi pertama dicatat (mis. pembelian bahan
 * baku tunai Rp7,5 juta tercatat "keluar dari Kas" padahal Kas belum
 * pernah "diisi" modal awal). Ini murni data uji coba dan akan direset
 * saat aplikasi siap dipakai massal - command ini adalah PENGAMANNYA:
 * dengan mewajibkan saldo awal ditetapkan lebih dulu (dan menolak
 * berjalan kalau jurnal_umum sudah terisi transaksi), skenario "saldo
 * negatif karena lupa modal awal" tidak akan bisa terulang lagi begitu
 * data sungguhan mulai dipakai.
 */
class SaldoAwalCommand extends Command
{
    protected $signature = 'akuntansi:saldo-awal';
    protected $description = 'Tetapkan saldo awal akuntansi (Kas/Bank/Piutang/Persediaan/Hutang) sebelum data transaksi real dimulai';

    private const AKUN_ASET = [
        '1000' => 'Kas',
        '1005' => 'Kas Kecil',
        '1010' => 'Bank',
        '1100' => 'Piutang Usaha',
        '1200' => 'Persediaan Bahan Baku',
    ];

    private const AKUN_KEWAJIBAN = [
        '2000' => 'Hutang Usaha',
        '2100' => 'Hutang Deposit Konsumen',
    ];

    private const AKUN_MODAL = '3000';

    public function handle(): int
    {
        $this->info('=== Penetapan Saldo Awal Akuntansi ===');
        $this->line('Ini HANYA boleh dijalankan SATU KALI, sebelum ada transaksi apapun tercatat.');
        $this->newLine();

        // Guard utama: menolak berjalan kalau jurnal_umum sudah terisi -
        // saldo awal secara definisi harus jadi entri PERTAMA, bukan
        // ditambahkan di tengah histori transaksi yang sudah berjalan
        // (kalau itu terjadi, saldo yang dihasilkan tidak akan
        // merepresentasikan apapun secara akurat).
        $jumlahJurnalAda = DB::table('jurnal_umum')->count();
        if ($jumlahJurnalAda > 0) {
            $this->error("Ditolak: tabel jurnal_umum sudah punya {$jumlahJurnalAda} baris transaksi.");
            $this->error('Saldo awal wajib jadi entri PERTAMA. Kalau data yang ada sekarang cuma data uji coba, kosongkan dulu jurnal_umum (dan tabel transaksi terkait) sebelum menjalankan perintah ini.');
            return self::FAILURE;
        }

        if (DB::table('bag_akun')->count() === 0) {
            $this->error('Tabel bag_akun (daftar akun) masih kosong - migrasi belum lengkap.');
            return self::FAILURE;
        }

        $totalAset = 0.0;
        $totalKewajiban = 0.0;
        $lines = [];

        $this->line('--- Saldo Awal Aset (uang/piutang/stok yang SUDAH ADA saat ini) ---');
        foreach (self::AKUN_ASET as $kode => $nama) {
            $nilai = (float) $this->ask("{$nama} ({$kode})", '0');
            if ($nilai < 0) {
                $this->error('Nilai tidak boleh negatif.');
                return self::FAILURE;
            }
            if ($nilai > 0) {
                $lines[] = ['kode_akun' => $kode, 'debet' => $nilai, 'kredit' => 0];
                $totalAset += $nilai;
            }
        }

        $this->newLine();
        $this->line('--- Saldo Awal Kewajiban (hutang yang SUDAH ADA saat ini) ---');
        foreach (self::AKUN_KEWAJIBAN as $kode => $nama) {
            $nilai = (float) $this->ask("{$nama} ({$kode})", '0');
            if ($nilai < 0) {
                $this->error('Nilai tidak boleh negatif.');
                return self::FAILURE;
            }
            if ($nilai > 0) {
                $lines[] = ['kode_akun' => $kode, 'debet' => 0, 'kredit' => $nilai];
                $totalKewajiban += $nilai;
            }
        }

        $modal = $totalAset - $totalKewajiban;
        if ($modal < 0) {
            $this->error("Total kewajiban (Rp" . number_format($totalKewajiban, 0, ',', '.') . ") tidak boleh lebih besar dari total aset (Rp" . number_format($totalAset, 0, ',', '.') . "). Modal tidak boleh negatif.");
            return self::FAILURE;
        }

        if ($modal > 0) {
            $lines[] = ['kode_akun' => self::AKUN_MODAL, 'debet' => 0, 'kredit' => $modal];
        }

        if (count($lines) < 2) {
            $this->warn('Semua nilai nol - tidak ada yang perlu dicatat.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('=== Ringkasan ===');
        $this->line('Total Aset    : Rp' . number_format($totalAset, 0, ',', '.'));
        $this->line('Total Kewajiban: Rp' . number_format($totalKewajiban, 0, ',', '.'));
        $this->line('Modal (selisih): Rp' . number_format($modal, 0, ',', '.'));

        if (!$this->confirm('Simpan sebagai saldo awal? Tindakan ini akan mengunci baris pertama jurnal_umum.', true)) {
            $this->warn('Dibatalkan.');
            return self::SUCCESS;
        }

        AccountingService::postJournal(now()->toDateString(), 'SALDO-AWAL', 'Saldo awal akuntansi', $lines);

        $this->info('Saldo awal berhasil dicatat.');
        return self::SUCCESS;
    }
}
