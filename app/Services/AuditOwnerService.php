<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bahan audit bulanan untuk Owner: semua sumber input (nota, kas, pembelian, permintaan bahan,
 * pemakaian & salah cetak, opname/penyesuaian, approval, jejak aktivitas) dirangkum per bulan.
 * Murni membaca data yang sudah ada - tidak ada tabel baru.
 */
class AuditOwnerService
{
    public static function bulan(Carbon $awal): array
    {
        $dari = $awal->copy()->startOfMonth();
        $sampai = $awal->copy()->endOfMonth();

        return [
            'dari' => $dari,
            'sampai' => $sampai,
            'keuangan' => self::keuangan($dari, $sampai),
            'kas_harian' => self::kasHarian($dari, $sampai),
            'permintaan' => self::permintaanBahan($dari, $sampai),
            'stok' => self::mutasiStok($dari, $sampai),
            'salah_cetak' => self::salahCetak($dari, $sampai),
            'penyesuaian' => self::penyesuaian($dari, $sampai),
            'approval' => self::approval($dari, $sampai),
            'input_user' => self::inputPerUser($dari, $sampai),
            'jejak' => self::jejak($dari, $sampai),
        ];
    }

    private static function tgl(Carbon $d, Carbon $s): array
    {
        return [$d->toDateString(), $s->toDateString()];
    }

    private static function keuangan(Carbon $d, Carbon $s): array
    {
        $tgl = self::tgl($d, $s);
        $r = DashboardEksekutifService::ringkasan($d, $s);

        $nota = DB::table('transaksi')->whereTanggalAntara('tgl_order', $tgl);
        $batal = (clone $nota)->where('status_nota', 'Dibatalkan');

        // Pembayaran dari saldo deposit bukan uang baru (uangnya sudah masuk saat setoran), jadi dipisah dari kas masuk.
        $kasMasuk = DB::table('pembayaran_riwayat')->whereTanggalAntara('tgl_bayar', $tgl)->where('metode', '!=', 'Deposit')
            ->selectRaw('metode, COUNT(*) jumlah, SUM(nominal) total')->groupBy('metode')->orderBy('metode')->get();
        $pemakaianDeposit = (float) DB::table('pembayaran_riwayat')->whereTanggalAntara('tgl_bayar', $tgl)->where('metode', 'Deposit')->sum('nominal');
        $setoranDeposit = (float) DB::table('konsumen_deposit')->where('tipe', 'Setoran')->whereIn('metode_bayar', ['Tunai', 'Transfer'])
            ->whereBetween('tgl_deposit', $tgl)->sum('nominal');

        $pembayaranJersey = (float) DB::table('jersey_order_payments')->whereNull('deleted_at')->whereBetween('tanggal_bayar', $tgl)->sum('jumlah');

        $pembelian = DB::table('pembelian_bahan')->whereNull('deleted_at')->whereBetween('tgl_transaksi', $tgl);
        // Cicilan/pelunasan hutang dengan uang pemilik. Pembayaran awal faktur Cash sudah masuk nilai faktur, dan hutang
        // lama yang dibayar dari Kas Kecil sudah tampil sebagai baris Kas Kecil.
        $bayarHutang = (float) DB::table('pembayaran_hutang')->whereNull('voided_at')->where('bagian_faktur', false)->where('metode', '!=', 'Kas Kecil')
            ->whereBetween('tanggal', $tgl)->sum('nominal');

        $kasKecil = DB::table('kas_kecil as k')->leftJoin('kategori_kas_kecil as c', 'c.id_kategori_kas_kecil', '=', 'k.id_kategori')
            ->whereNull('k.deleted_at')->where('k.jenis', 'Keluar')->whereBetween('k.tanggal', $tgl)
            ->selectRaw("COALESCE(c.nama_kategori, 'Tanpa kategori') as kategori, k.metode = 'Dibayar Pemilik' as pemilik, COUNT(*) as jumlah, SUM(k.nominal) as total")
            ->groupBy('kategori', 'pemilik')->orderBy('pemilik')->orderByDesc('total')->get();

        $kasManual = DB::table('kas_manual')->whereTanggalAntara('waktu', $tgl)
            ->selectRaw('status, COUNT(*) jumlah, SUM(nominal) total')->groupBy('status')->get();

        return $r + [
            'nota_dibatalkan_jumlah' => (clone $batal)->count(),
            'nota_dibatalkan_nilai' => (float) (clone $batal)->sum('total_bayar'),
            'nota_dibatalkan' => (clone $batal)->orderBy('tgl_dibatalkan_nota')->get(['nomor_nota', 'total_bayar', 'alasan_batal_nota', 'tgl_dibatalkan_nota']),
            'kas_masuk' => $kasMasuk,
            'kas_masuk_total' => (float) $kasMasuk->sum('total'),
            'setoran_deposit' => $setoranDeposit,
            'pemakaian_deposit' => $pemakaianDeposit,
            'pembayaran_jersey' => $pembayaranJersey,
            'pembelian_jumlah' => (clone $pembelian)->count(),
            'pembelian_total' => (float) (clone $pembelian)->sum('grand_total'),
            'pembayaran_hutang' => $bayarHutang,
            'kas_kecil' => $kasKecil,
            'kas_kecil_total' => (float) $kasKecil->where('pemilik', 0)->sum('total'),
            'kas_kecil_pemilik' => (float) $kasKecil->where('pemilik', 1)->sum('total'),
            'kas_manual' => $kasManual,
            'nota_belum_lunas' => DB::table('transaksi')->where('status_nota', '!=', 'Dibatalkan')->where('status_pembayaran', '!=', 'Lunas')->count(),
        ];
    }

    private static function kasHarian(Carbon $d, Carbon $s)
    {
        return DB::table('kas_harian as k')->leftJoin('users as u', 'u.id_user', '=', 'k.id_user')
            ->leftJoin('karyawan as kw', 'kw.id_karyawan', '=', 'u.id_karyawan')
            ->whereBetween('k.tgl_kas', self::tgl($d, $s))->orderBy('k.tgl_kas')
            ->get(['k.tgl_kas', 'k.waktu_tutup', 'k.saldo_awal', 'k.saldo_akhir', 'k.saldo_fisik', 'k.selisih', 'k.status_lock', 'k.keterangan_closing', DB::raw('COALESCE(kw.nama_karyawan, u.username) as kasir')]);
    }

    private static function permintaanBahan(Carbon $d, Carbon $s): array
    {
        $tgl = self::tgl($d, $s);

        $pr = DB::table('purchase_requests as p')->leftJoin('users as u', 'u.id_user', '=', 'p.id_user_pembuat')
            ->whereBetween('p.tanggal', $tgl)->orderBy('p.tanggal')
            ->get(['p.id', 'p.nomor', 'p.tanggal', 'p.status', 'p.catatan', 'u.username as pembuat']);
        $item = DB::table('purchase_request_details as d')->join('bahan_baku as b', 'b.id_bahan', '=', 'd.id_bahan')
            ->whereIn('d.id_purchase_request', $pr->pluck('id'))->get(['d.id_purchase_request', 'b.nama_bahan', 'b.satuan_stok', 'd.qty'])->groupBy('id_purchase_request');
        $pr->each(fn ($p) => $p->item = ($item[$p->id] ?? collect())->map(fn ($i) => $i->nama_bahan . ' ' . angka($i->qty, 4) . ' ' . $i->satuan_stok)->implode('; '));

        $po = DB::table('purchase_orders as o')->leftJoin('supplier as s', 's.id_supplier', '=', 'o.id_supplier')
            ->whereBetween('o.tanggal', $tgl)->orderBy('o.tanggal')
            ->get(['o.nomor', 'o.tanggal', 'o.status', 'o.total', 's.nama_supplier']);

        return [
            'pr' => $pr,
            'pr_per_status' => $pr->groupBy('status')->map->count(),
            'po' => $po,
            'po_total' => (float) $po->where('status', '!=', 'Dibatalkan')->sum('total'),
        ];
    }

    /** Mutasi stok per bahan dari Kartu Stok: masuk, keluar (cetak / salah cetak / lain), penyesuaian, stok awal & akhir. */
    private static function mutasiStok(Carbon $d, Carbon $s)
    {
        $rows = DB::table('kartu_stok as ks')->join('bahan_baku as b', 'b.id_bahan', '=', 'ks.id_bahan')
            ->whereTanggalAntara('ks.tanggal', self::tgl($d, $s))
            ->orderBy('ks.tanggal')->orderBy('ks.id')
            ->get(['ks.*', 'b.nama_bahan', 'b.satuan_stok', 'b.harga_rata_rata']);

        $notaSet = DB::table('transaksi')->whereIn('nomor_nota', $rows->where('tipe', 'OUT')->pluck('referensi')->unique()->filter()->values())
            ->pluck('nomor_nota')->flip();

        return $rows->groupBy('id_bahan')->map(function ($g) use ($notaSet) {
            $pertama = $g->first();
            $terakhir = $g->last();
            $o = (object) [
                'nama_bahan' => $pertama->nama_bahan, 'satuan' => $pertama->satuan_stok, 'harga' => (float) $pertama->harga_rata_rata,
                'stok_awal' => (float) $pertama->stok_sebelum, 'stok_akhir' => (float) $terakhir->stok_sesudah,
                'masuk' => 0.0, 'keluar_cetak' => 0.0, 'keluar_waste' => 0.0, 'keluar_rework' => 0.0, 'keluar_lain' => 0.0, 'penyesuaian' => 0.0,
            ];
            foreach ($g as $r) {
                $ref = (string) $r->referensi;
                if ($r->tipe === 'IN') {
                    $o->masuk += (float) $r->qty;
                } elseif ($r->tipe === 'ADJUSTMENT') {
                    $o->penyesuaian += (float) $r->stok_sesudah - (float) $r->stok_sebelum;
                } elseif (str_starts_with($ref, 'WS-')) {
                    $o->keluar_waste += (float) $r->qty;
                } elseif (str_starts_with($ref, 'RW-')) {
                    $o->keluar_rework += (float) $r->qty;
                } elseif (isset($notaSet[$ref])) {
                    $o->keluar_cetak += (float) $r->qty;
                } else {
                    $o->keluar_lain += (float) $r->qty;
                }
            }
            $o->nilai_salah_cetak = round(($o->keluar_waste + $o->keluar_rework) * $o->harga, 2);
            // Kontrol: stok awal + masuk - keluar + penyesuaian harus = stok akhir.
            $o->selisih_kontrol = round($o->stok_awal + $o->masuk - $o->keluar_cetak - $o->keluar_waste - $o->keluar_rework - $o->keluar_lain + $o->penyesuaian - $o->stok_akhir, 4);

            return $o;
        })->sortBy('nama_bahan')->values();
    }

    private static function salahCetak(Carbon $d, Carbon $s): array
    {
        $tgl = self::tgl($d, $s);

        $waste = DB::table('waste_log as w')->join('bahan_baku as b', 'b.id_bahan', '=', 'w.id_bahan')->leftJoin('users as u', 'u.id_user', '=', 'w.id_user')
            ->whereBetween('w.tanggal', $tgl)->orderBy('w.tanggal')
            ->get(['w.nomor', 'w.tanggal', 'b.nama_bahan', 'b.satuan_stok', 'w.qty', 'w.jenis', 'w.alasan', 'w.status', 'w.biaya', 'u.username as petugas']);

        $rework = DB::table('rework as r')->leftJoin('users as u', 'u.id_user', '=', 'r.id_user')
            ->whereTanggalAntara('r.created_at', $tgl)->orderBy('r.created_at')
            ->get(['r.nomor', 'r.created_at as tanggal', 'r.qty_rework', 'r.status', 'r.biaya_material', 'r.catatan', 'u.username as petugas']);

        $qc = DB::table('qc_inspeksi')->whereBetween('tanggal', $tgl);
        $diperiksa = (float) (clone $qc)->sum('qty_diperiksa');
        $reject = (float) (clone $qc)->sum('qty_reject');

        return [
            'waste' => $waste,
            'waste_biaya' => (float) $waste->where('status', 'Diterapkan')->sum('biaya'),
            'waste_abnormal' => $waste->where('jenis', 'Abnormal')->count(),
            'rework' => $rework,
            'rework_biaya' => (float) $rework->where('status', 'Selesai')->sum('biaya_material'),
            'qc_diperiksa' => $diperiksa,
            'qc_reject' => $reject,
            'qc_persen_reject' => $diperiksa > 0 ? round($reject / $diperiksa * 100, 2) : 0.0,
        ];
    }

    private static function penyesuaian(Carbon $d, Carbon $s)
    {
        return DB::table('kartu_stok as ks')->join('bahan_baku as b', 'b.id_bahan', '=', 'ks.id_bahan')->leftJoin('users as u', 'u.id_user', '=', 'ks.created_by')
            ->where('ks.tipe', 'ADJUSTMENT')->whereTanggalAntara('ks.tanggal', self::tgl($d, $s))->orderBy('ks.tanggal')
            ->get(['ks.tanggal', 'b.nama_bahan', 'b.satuan_stok', 'ks.stok_sebelum', 'ks.stok_sesudah', 'ks.referensi', 'ks.keterangan', 'u.username as petugas', 'b.harga_rata_rata'])
            ->map(function ($r) {
                $r->selisih = (float) $r->stok_sesudah - (float) $r->stok_sebelum;
                $r->nilai = round($r->selisih * (float) $r->harga_rata_rata, 2);

                return $r;
            });
    }

    private static function approval(Carbon $d, Carbon $s): array
    {
        $rows = DB::table('approval_requests as a')->leftJoin('users as p', 'p.id_user', '=', 'a.requested_by')->leftJoin('users as k', 'k.id_user', '=', 'a.decided_by')
            ->whereTanggalAntara('a.created_at', self::tgl($d, $s))->orderBy('a.created_at')
            ->get(['a.tipe_aksi', 'a.ringkasan', 'a.alasan_pengajuan', 'a.status', 'a.catatan_keputusan', 'a.created_at', 'a.decided_at', 'p.username as pengaju', 'k.username as pemutus']);

        return ['daftar' => $rows, 'per_status' => $rows->groupBy('status')->map->count()];
    }

    /** Jumlah input per pengguna: nota, waste, QC, kas kecil, PR - untuk melihat siapa mengerjakan apa. */
    private static function inputPerUser(Carbon $d, Carbon $s): array
    {
        $tgl = self::tgl($d, $s);
        $sumber = [
            'Nota' => DB::table('transaksi')->whereTanggalAntara('tgl_order', $tgl)->selectRaw('id_user_kasir uid, COUNT(*) c')->groupBy('id_user_kasir'),
            'Waste' => DB::table('waste_log')->whereBetween('tanggal', $tgl)->selectRaw('id_user uid, COUNT(*) c')->groupBy('id_user'),
            'QC' => DB::table('qc_inspeksi')->whereBetween('tanggal', $tgl)->selectRaw('id_user uid, COUNT(*) c')->groupBy('id_user'),
            'Kas Kecil' => DB::table('kas_kecil')->whereNull('deleted_at')->whereBetween('tanggal', $tgl)->selectRaw('id_user uid, COUNT(*) c')->groupBy('id_user'),
            'Purchase Request' => DB::table('purchase_requests')->whereBetween('tanggal', $tgl)->selectRaw('id_user_pembuat uid, COUNT(*) c')->groupBy('id_user_pembuat'),
        ];
        $nama = DB::table('users as u')->join('roles as r', 'r.id_role', '=', 'u.id_role')->get(['u.id_user', 'u.username', 'r.nama_role'])->keyBy('id_user');

        $hasil = [];
        foreach ($sumber as $label => $q) {
            foreach ($q->get() as $r) {
                if (!$r->uid) {
                    continue;
                }
                $hasil[$r->uid]['nama'] = ($nama[$r->uid]->username ?? "user#{$r->uid}") . ' (' . ($nama[$r->uid]->nama_role ?? '-') . ')';
                $hasil[$r->uid][$label] = (int) $r->c;
            }
        }

        return ['kolom' => array_keys($sumber), 'baris' => collect($hasil)->sortBy('nama')->values()];
    }

    private static function jejak(Carbon $d, Carbon $s): array
    {
        $q = DB::table('audit_logs')->whereTanggalAntara('created_at', self::tgl($d, $s));

        return [
            'total' => (clone $q)->count(),
            'per_event' => (clone $q)->selectRaw('event, COUNT(*) c')->groupBy('event')->pluck('c', 'event'),
            'per_tipe' => (clone $q)->selectRaw('auditable_type t, COUNT(*) c')->groupBy('t')->orderByDesc('c')->limit(15)->get(),
            'penting' => (clone $q)->where(function ($w) {
                $w->where('keterangan', 'like', '%harga%')->orWhere('keterangan', 'like', '%batal%')->orWhere('keterangan', 'like', '%tutup paksa%')->orWhere('keterangan', 'like', '%koreksi%')->orWhere('keterangan', 'like', '%void%');
            })->orderBy('created_at')->limit(200)->get(['created_at', 'user_label', 'event', 'auditable_type', 'keterangan']),
        ];
    }
}
