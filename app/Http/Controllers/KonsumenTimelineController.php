<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Customer Timeline (LEVEL 2, bagian 15 dokumen audit): satu halaman yang
 * merangkum seluruh hubungan bisnis dengan satu pelanggan - penawaran, nota,
 * pembayaran, deposit, dan order jersey - urut waktu, plus ringkasan angka.
 * Murni baca; tidak mengubah data apa pun.
 */
class KonsumenTimelineController extends Controller
{
    private const BATAS_KEJADIAN = 300;

    public function show(int $id)
    {
        $konsumen = DB::table('konsumen as k')
            ->leftJoin('golongan_konsumen as g', 'g.id_golongan', '=', 'k.id_golongan')
            ->where('k.id_konsumen', $id)
            ->select('k.*', 'g.nama_golongan')
            ->first();

        abort_if(!$konsumen, 404);

        $nota = DB::table('transaksi')->where('id_konsumen', $id)->orderByDesc('tgl_order')->get();
        $notaAktif = $nota->where('status_nota', '!=', 'Dibatalkan');
        $idNotaAktif = $notaAktif->pluck('id_transaksi');
        $nomorNota = $nota->pluck('nomor_nota', 'id_transaksi');

        $pembayaran = DB::table('pembayaran_riwayat')->whereIn('id_transaksi', $nota->pluck('id_transaksi'))->get();
        $deposit = DB::table('konsumen_deposit')->where('id_konsumen', $id)->whereNull('deleted_at')->get();
        $penawaran = DB::table('quotations')->where('id_konsumen', $id)->get();
        $jersey = DB::table('jersey_orders')->where('id_konsumen', $id)->get();

        $omzet = (float) $notaAktif->sum('total_bayar');
        $dibayar = (float) $pembayaran->whereIn('id_transaksi', $idNotaAktif)->sum('nominal');

        $ringkasan = [
            'jumlah_nota' => $notaAktif->count(),
            'omzet' => $omzet,
            'dibayar' => $dibayar,
            'piutang' => max(0, round($omzet - $dibayar, 2)),
            'saldo_deposit' => (float) $deposit->where('tipe', 'Setoran')->sum('nominal') - (float) $deposit->whereIn('tipe', ['Pemakaian', 'Pengembalian'])->sum('nominal'),
            'order_terakhir' => $notaAktif->max('tgl_order'),
            'belum_diambil' => $notaAktif->where('status_collecting', 'Belum Diambil')->count(),
            'jumlah_penawaran' => $penawaran->count(),
            'penawaran_jadi_order' => $penawaran->where('status', 'Menjadi Order')->count(),
            'jumlah_jersey' => $jersey->count(),
            'omzet_jersey' => (float) $jersey->sum('total'),
        ];

        $kejadian = collect();

        foreach ($penawaran as $q) {
            $kejadian->push(['waktu' => Carbon::parse($q->tanggal), 'jenis' => 'Penawaran', 'ikon' => 'file-signature', 'warna' => 'amber',
                'judul' => "Penawaran {$q->nomor}", 'detail' => $q->status . ' - berlaku s/d ' . Carbon::parse($q->berlaku_sampai)->translatedFormat('d M Y'),
                'nominal' => (float) $q->total, 'url' => route('quotation.edit', $q->id)]);
        }

        foreach ($nota as $n) {
            $batal = $n->status_nota === 'Dibatalkan';
            $kejadian->push(['waktu' => Carbon::parse($n->tgl_order), 'jenis' => 'Order', 'ikon' => 'receipt', 'warna' => $batal ? 'rose' : 'indigo',
                'judul' => "Nota {$n->nomor_nota}", 'detail' => $batal ? 'Dibatalkan' : "{$n->status_pembayaran} - {$n->status_collecting}",
                'nominal' => (float) $n->total_bayar, 'url' => route('penjualan.show', $n->id_transaksi)]);
        }

        foreach ($pembayaran as $p) {
            $kejadian->push(['waktu' => Carbon::parse($p->tgl_bayar), 'jenis' => 'Pembayaran', 'ikon' => 'money-bill-wave', 'warna' => 'emerald',
                'judul' => 'Pembayaran ' . ($nomorNota[$p->id_transaksi] ?? ''), 'detail' => $p->metode . ($p->keterangan ? " - {$p->keterangan}" : ''),
                'nominal' => (float) $p->nominal, 'url' => null]);
        }

        foreach (DB::table('pengambilan')->whereIn('id_transaksi', $nota->pluck('id_transaksi'))->get() as $g) {
            $kejadian->push(['waktu' => Carbon::parse($g->created_at), 'jenis' => 'Pengambilan', 'ikon' => 'box-open', 'warna' => 'emerald',
                'judul' => 'Serah terima ' . ($nomorNota[$g->id_transaksi] ?? ''), 'detail' => $g->jenis . ($g->nama_penerima ? " - {$g->nama_penerima}" : ''),
                'nominal' => 0.0, 'url' => route('berkas-order.show', $g->id_transaksi)]);
        }

        foreach ($deposit as $d) {
            $kejadian->push(['waktu' => Carbon::parse($d->tgl_deposit), 'jenis' => 'Deposit', 'ikon' => 'wallet', 'warna' => 'sky',
                'judul' => $d->tipe . ' deposit', 'detail' => $d->keterangan ?: ($d->metode_bayar ?: ''),
                'nominal' => (float) $d->nominal * ($d->tipe === 'Setoran' ? 1 : -1), 'url' => null]);
        }

        foreach ($jersey as $j) {
            $kejadian->push(['waktu' => Carbon::parse($j->order_date), 'jenis' => 'Jersey', 'ikon' => 'tshirt', 'warna' => 'violet',
                'judul' => "Order Jersey {$j->order_number}", 'detail' => "{$j->status} - {$j->status_pembayaran}",
                'nominal' => (float) $j->total, 'url' => route('jersey.orders.show', $j->id)]);
        }

        $kejadian = $kejadian->sortByDesc(fn ($k) => $k['waktu']->timestamp)->take(self::BATAS_KEJADIAN)->values();

        return view('konsumen.timeline', compact('konsumen', 'ringkasan', 'kejadian'));
    }
}
