<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Operasionalisasi pemeriksaan yang dilakukan manual saat audit Phase 6/7
 * (inventory & akuntansi) supaya bisa dijalankan berkala (mis. sebelum
 * closing bulanan, atau cron mingguan) - bukan cuma sekali pas audit.
 * Mendeteksi DINI kalau salah satu skenario yang jadi temuan awal
 * (jurnal timpang, akun orphan, saldo di arah yang salah, stok tidak
 * sinkron dengan kartu_stok) mulai terulang di data sungguhan.
 *
 * Exit code 1 kalau ada temuan (cocok dipakai di CI/cron untuk kirim
 * notifikasi kalau gagal), exit code 0 kalau semua bersih.
 */
class AuditIntegritasCommand extends Command
{
    protected $signature = 'audit:integritas {--json : Output JSON, bukan tabel}';
    protected $description = 'Cek integritas akuntansi & stok (jurnal balance, saldo akun, sinkronisasi kartu_stok)';

    public function handle(): int
    {
        $adaTemuan = false;
        $hasil = [];

        // 1. Jurnal balance per no_referensi
        $tidakBalance = DB::table('jurnal_umum')
            ->select('no_referensi', DB::raw('SUM(debet) as total_debet'), DB::raw('SUM(kredit) as total_kredit'))
            ->groupBy('no_referensi')
            ->get()
            ->filter(fn ($r) => abs((float) $r->total_debet - (float) $r->total_kredit) > 0.01);

        if ($tidakBalance->isNotEmpty()) {
            $adaTemuan = true;
            foreach ($tidakBalance as $r) {
                $hasil[] = ['jurnal_tidak_balance', $r->no_referensi, "debet={$r->total_debet} kredit={$r->total_kredit}"];
            }
        }

        // 2. kode_akun orphan (tidak ada di bag_akun)
        $orphanAkun = DB::table('jurnal_umum as j')
            ->leftJoin('bag_akun as a', 'j.kode_akun', '=', 'a.kode_akun')
            ->whereNull('a.kode_akun')
            ->distinct()
            ->pluck('j.kode_akun');

        foreach ($orphanAkun as $kode) {
            $adaTemuan = true;
            $hasil[] = ['kode_akun_orphan', $kode, 'kode_akun di jurnal_umum tidak ada di bag_akun'];
        }

        // 3. Saldo akun di arah yang salah (Aset/Beban normal Debet tapi
        // negatif, Kewajiban/Modal/Pendapatan normal Kredit tapi negatif).
        // Pengecualian: 5230 Selisih Persediaan - akun contra-beban yang
        // memang bisa negatif (kelebihan stok = kredit, mengurangi beban).
        $saldoPerAkun = DB::table('jurnal_umum as j')
            ->join('bag_akun as a', 'j.kode_akun', '=', 'a.kode_akun')
            ->select('a.kode_akun', 'a.nama_akun', 'a.saldo_normal',
                DB::raw('SUM(j.debet) as total_debet'), DB::raw('SUM(j.kredit) as total_kredit'))
            ->whereNotIn('a.kode_akun', ['5230'])
            ->groupBy('a.kode_akun', 'a.nama_akun', 'a.saldo_normal')
            ->get();

        foreach ($saldoPerAkun as $s) {
            $saldo = $s->saldo_normal === 'Debet'
                ? ((float) $s->total_debet - (float) $s->total_kredit)
                : ((float) $s->total_kredit - (float) $s->total_debet);
            if ($saldo < -0.01) {
                $adaTemuan = true;
                $hasil[] = ['saldo_arah_salah', "{$s->kode_akun} {$s->nama_akun}", 'saldo=' . round($saldo, 2) . " (normal {$s->saldo_normal})"];
            }
        }

        // 4. Stok bahan_baku vs entri kartu_stok terakhir
        $bahans = DB::table('bahan_baku')->get(['id_bahan', 'nama_bahan', 'stok']);
        foreach ($bahans as $b) {
            $lastKartu = DB::table('kartu_stok')->where('id_bahan', $b->id_bahan)->orderByDesc('id')->first();
            if (!$lastKartu) {
                if ((float) $b->stok != 0) {
                    $adaTemuan = true;
                    $hasil[] = ['stok_tanpa_kartu_stok', $b->nama_bahan, "stok={$b->stok} tapi 0 entri kartu_stok"];
                }
                continue;
            }
            $diff = round((float) $lastKartu->stok_sesudah - (float) $b->stok, 4);
            if (abs($diff) > 0.0001) {
                $adaTemuan = true;
                $hasil[] = ['stok_tidak_sinkron', $b->nama_bahan, "bahan_baku.stok={$b->stok} vs kartu_stok terakhir={$lastKartu->stok_sesudah}"];
            }
        }

        // 5. Uang yang diterima tidak boleh melebihi tagihan (kelebihan seharusnya kembalian, bukan pembayaran).
        $lebihNota = DB::table('transaksi as t')->where('t.status_nota', '!=', 'Dibatalkan')
            ->joinSub(DB::table('pembayaran_riwayat')->selectRaw('id_transaksi, SUM(nominal) s')->groupBy('id_transaksi'), 'b', 'b.id_transaksi', '=', 't.id_transaksi')
            ->whereRaw('b.s - t.total_bayar > 0.01')->get(['t.nomor_nota', 't.total_bayar', 'b.s']);
        foreach ($lebihNota as $r) {
            $adaTemuan = true;
            $hasil[] = ['bayar_melebihi_tagihan', $r->nomor_nota, "total={$r->total_bayar} dibayar={$r->s}"];
        }
        if (DB::getSchemaBuilder()->hasTable('jersey_orders')) {
            $lebihJersey = DB::table('jersey_orders as o')
                ->joinSub(DB::table('jersey_order_payments')->whereNull('deleted_at')->selectRaw('jersey_order_id, SUM(jumlah) s')->groupBy('jersey_order_id'), 'b', 'b.jersey_order_id', '=', 'o.id')
                ->whereRaw('b.s - o.total > 0.01')->get(['o.order_number', 'o.total', 'b.s']);
            foreach ($lebihJersey as $r) {
                $adaTemuan = true;
                $hasil[] = ['bayar_melebihi_tagihan', $r->order_number, "total={$r->total} dibayar={$r->s} (php artisan jersey:koreksi-pembukuan)"];
            }

            // 6. Pendapatan order jersey yang ditandai terjurnal harus benar-benar ada di jurnal (akun 4300).
            $pendapatan = DB::table('jurnal_umum')->where('kode_akun', '4300')->selectRaw('no_referensi, SUM(kredit) - SUM(debet) s')->groupBy('no_referensi')->pluck('s', 'no_referensi');
            foreach (DB::table('jersey_orders')->where('total_terjurnal', '>', 0)->get(['order_number', 'total_terjurnal']) as $o) {
                if (abs((float) $o->total_terjurnal - (float) ($pendapatan[$o->order_number] ?? 0)) > 0.01) {
                    $adaTemuan = true;
                    $hasil[] = ['pendapatan_jersey_tidak_terjurnal', $o->order_number, "total_terjurnal={$o->total_terjurnal} jurnal=" . (float) ($pendapatan[$o->order_number] ?? 0)];
                }
            }
        }

        // 7. Buku Kas Kecil (catatan tunai) harus sama dengan saldo akun 1005 di jurnal. Selisih biasanya berarti ada
        //    kategori tanpa akun (catatan tanpa jurnal) atau jurnal 1005 tanpa catatan buku.
        if (DB::getSchemaBuilder()->hasTable('kas_kecil')) {
            $buku = (float) DB::table('kas_kecil')->whereNull('deleted_at')->where('metode', 'Tunai')
                ->selectRaw("COALESCE(SUM(CASE WHEN jenis = 'Masuk' THEN nominal ELSE -nominal END), 0) s")->value('s');
            $jurnal = (float) DB::table('jurnal_umum')->where('kode_akun', '1005')->selectRaw('COALESCE(SUM(debet - kredit), 0) s')->value('s');
            if (abs($buku - $jurnal) > 0.01) {
                $adaTemuan = true;
                $hasil[] = ['kas_kecil_tidak_sinkron', 'Kas Kecil', 'buku=' . round($buku, 2) . ' jurnal 1005=' . round($jurnal, 2)];
            }
        }

        // 8. Piutang Usaha (1100) = sisa tagihan nota aktif + sisa tagihan order jersey yang sudah terjurnal.
        //    Hanya nota yang piutangnya pernah dijurnal: nota lama dari sebelum integrasi akuntansi tidak punya jurnal
        //    Piutang sama sekali, jadi menghitungnya hanya membuat alarm permanen yang menutupi temuan sungguhan.
        $sisaNota = (float) DB::table('transaksi as t')->where('t.status_nota', '!=', 'Dibatalkan')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('jurnal_umum as j')->whereColumn('j.no_referensi', 't.nomor_nota')->where('j.kode_akun', '1100'))
            ->leftJoinSub(DB::table('pembayaran_riwayat')->selectRaw('id_transaksi, SUM(nominal) s')->groupBy('id_transaksi'), 'b', 'b.id_transaksi', '=', 't.id_transaksi')
            ->selectRaw('COALESCE(SUM(t.total_bayar - COALESCE(b.s, 0)), 0) s')->value('s');
        $sisaJersey = 0.0;
        if (DB::getSchemaBuilder()->hasTable('jersey_orders')) {
            $sisaJersey = (float) DB::table('jersey_orders')->sum('total_terjurnal')
                - (float) DB::table('jersey_order_payments')->whereNull('deleted_at')->sum('jumlah');
        }
        $piutang = (float) DB::table('jurnal_umum')->where('kode_akun', '1100')->selectRaw('COALESCE(SUM(debet - kredit), 0) s')->value('s');
        if (abs($piutang - ($sisaNota + $sisaJersey)) > 0.01) {
            $adaTemuan = true;
            $hasil[] = ['piutang_tidak_sinkron', 'Piutang Usaha 1100', 'jurnal=' . round($piutang, 2) . ' sisa tagihan nota=' . round($sisaNota, 2) . ' jersey=' . round($sisaJersey, 2)];
        }

        // 9. Collation kolom teks harus seragam; campuran membuat kueri gabungan gagal hanya di database ini.
        if (DB::getDriverName() === 'mysql') {
            $campur = DB::table('information_schema.columns')->whereRaw('table_schema = DATABASE()')
                ->whereNotNull('collation_name')->whereNotIn('collation_name', ['utf8mb4_unicode_ci', 'utf8mb4_bin'])
                ->selectRaw('table_name, column_name, collation_name')->get();
            foreach ($campur as $c) {
                $adaTemuan = true;
                $hasil[] = ['collation_campuran', "{$c->table_name}.{$c->column_name}", $c->collation_name . ' (php artisan migrate)'];
            }
        }

        // 10. Bahan aktif yang masih punya stok wajib punya harga rata-rata: tanpa itu setiap pemakaian/penjualannya
        //     tercatat HPP 0 (laba terlihat lebih besar) dan selisih opname tidak bernilai. Isi lewat faktur pembelian
        //     atau harga beli di master bahan.
        foreach (DB::table('bahan_baku')->where('status', 'aktif')->where('stok', '>', 0)
            ->where(fn ($q) => $q->whereNull('harga_rata_rata')->orWhere('harga_rata_rata', '<=', 0))
            ->orderBy('nama_bahan')->get(['nama_bahan', 'stok']) as $b) {
            $adaTemuan = true;
            $hasil[] = ['bahan_tanpa_harga', $b->nama_bahan, 'stok ' . round((float) $b->stok, 2) . ' tanpa harga rata-rata (HPP tercatat 0)'];
        }

        // Hasil terakhir disimpan untuk lonceng notifikasi dan halaman Audit Bulanan (dijalankan terjadwal tiap hari).
        \Illuminate\Support\Facades\Cache::forever('audit_integritas_terakhir', [
            'waktu' => now()->toDateTimeString(),
            'jumlah' => count($hasil),
            'temuan' => array_slice($hasil, 0, 50),
        ]);
        \Illuminate\Support\Facades\Cache::forget('notif:integritas');

        if ($this->option('json')) {
            $this->line(json_encode(['ok' => !$adaTemuan, 'temuan' => $hasil], JSON_PRETTY_PRINT));
        } elseif ($adaTemuan) {
            $this->error('Ditemukan ' . count($hasil) . ' masalah integritas:');
            $this->table(['Jenis', 'Target', 'Detail'], $hasil);
        } else {
            $this->info('Semua pemeriksaan integritas LULUS - tidak ada temuan.');
        }

        return $adaTemuan ? self::FAILURE : self::SUCCESS;
    }
}
