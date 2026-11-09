<?php

namespace App\Console\Commands;

use App\Models\JerseyOrder;
use App\Services\AccountingService;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi pembukuan Order Jersey yang tercatat sebelum aturan kembalian berlaku:
 *  1. Pembayaran tunai yang lebih besar dari total order: yang dicatat seharusnya sebesar tagihan, selebihnya
 *     kembalian yang sudah diserahkan ke pelanggan. Pembayaran terakhir dikurangi, cermin Kas Kecil ikut
 *     dikurangi, dan jurnal koreksi Dr Piutang / Cr Kas Kecil diposting (jurnal lama tidak dihapus).
 *  2. Pendapatan order yang ditandai terjurnal tetapi jurnalnya tidak ada: diposting Dr Piutang / Cr 4300.
 *
 * Tanpa --terapkan hanya menampilkan apa yang akan dikoreksi.
 */
class KoreksiJerseyCommand extends Command
{
    protected $signature = 'jersey:koreksi-pembukuan {--terapkan : Jalankan koreksi (tanpa ini hanya pratinjau)}';

    protected $description = 'Koreksi kelebihan bayar tunai (kembalian) dan pendapatan jersey yang tidak terjurnal';

    public function handle(): int
    {
        $terapkan = (bool) $this->option('terapkan');
        $baris = [];

        foreach (JerseyOrder::orderBy('id')->get() as $o) {
            $pendapatanTerjurnal = (float) DB::table('jurnal_umum')->where('no_referensi', $o->order_number)->where('kode_akun', '4300')
                ->selectRaw('COALESCE(SUM(kredit) - SUM(debet), 0) s')->value('s');
            $kurangJurnal = round((float) $o->total_terjurnal - $pendapatanTerjurnal, 2);

            $dibayar = (float) $o->payments()->sum('jumlah');
            $lebih = round($dibayar - (float) $o->total, 2);
            $bayarTunai = $o->payments()->where('metode_bayar', 'Tunai')->orderByDesc('id')->first();

            if (abs($kurangJurnal) < 0.01 && $lebih < 0.01) {
                continue;
            }
            $baris[] = [$o->order_number, number_format((float) $o->total, 0, ',', '.'), number_format($dibayar, 0, ',', '.'),
                $lebih > 0.01 ? number_format($lebih, 0, ',', '.') : '-', abs($kurangJurnal) >= 0.01 ? number_format($kurangJurnal, 0, ',', '.') : '-',
                $lebih > 0.01 && (!$bayarTunai || (float) $bayarTunai->jumlah < $lebih) ? 'LEWATI: kelebihan bukan dari satu pembayaran tunai, periksa manual' : ''];

            if (!$terapkan) {
                continue;
            }

            DB::transaction(function () use ($o, $kurangJurnal, $lebih, $bayarTunai) {
                if (abs($kurangJurnal) >= 0.01) {
                    AccountingService::postPendapatanJersey($o->order_number, $kurangJurnal);
                    AuditLogger::log('updated', 'jersey_orders', $o->id, null, ['pendapatan_diposting' => $kurangJurnal],
                        "Koreksi: pendapatan order {$o->order_number} yang ditandai terjurnal tetapi jurnalnya tidak ada");
                }

                if ($lebih > 0.01 && $bayarTunai && (float) $bayarTunai->jumlah >= $lebih) {
                    $lama = (float) $bayarTunai->jumlah;
                    $baru = round($lama - $lebih, 2);
                    DB::table('jersey_order_payments')->where('id', $bayarTunai->id)->update(['jumlah' => $baru, 'updated_at' => now()]);
                    DB::table('kas_kecil')->where('sumber', 'jersey')->where('id_referensi', $bayarTunai->id)->update(['nominal' => $baru, 'updated_at' => now()]);
                    AccountingService::postJournal(now()->toDateString(), "{$o->order_number}/BYR-{$bayarTunai->id}-KMB",
                        "Koreksi kembalian pembayaran order jersey {$o->order_number}", [
                            ['kode_akun' => '1100', 'debet' => $lebih],
                            ['kode_akun' => '1005', 'kredit' => $lebih],
                        ]);
                    AuditLogger::log('updated', 'jersey_order_payments', $bayarTunai->id, ['jumlah' => $lama], ['jumlah' => $baru, 'kembalian' => $lebih],
                        "Koreksi: pembayaran {$o->order_number} dicatat sebesar tagihan; Rp " . number_format($lebih, 0, ',', '.') . ' adalah kembalian');
                    $o->recalculatePembayaran();
                }
            });
        }

        if (!$baris) {
            $this->info('Tidak ada yang perlu dikoreksi.');

            return self::SUCCESS;
        }

        $this->table(['Order', 'Total', 'Dibayar', 'Kelebihan (kembalian)', 'Pendapatan belum terjurnal', 'Catatan'], $baris);
        $this->line($terapkan ? 'Koreksi diterapkan.' : 'Pratinjau saja. Jalankan dengan --terapkan untuk mengoreksi.');

        return self::SUCCESS;
    }
}
