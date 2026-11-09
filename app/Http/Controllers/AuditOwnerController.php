<?php

namespace App\Http\Controllers;

use App\Services\AuditOwnerService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Audit Bulanan Owner - halaman baca-saja: ringkasan seluruh input bulan terpilih
 * (keuangan, permintaan bahan, pemakaian & salah cetak, penyesuaian stok, approval, jejak aktivitas).
 */
class AuditOwnerController extends Controller
{
    private function bulan(Request $request): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m', (string) $request->query('bulan', now()->format('Y-m')))->startOfMonth();
        } catch (\Throwable $e) {
            return now()->startOfMonth();
        }
    }

    public function index(Request $request)
    {
        $bulan = $this->bulan($request);

        return view('audit-owner.index', ['bulan' => $bulan, 'a' => AuditOwnerService::bulan($bulan)]);
    }

    public function csv(Request $request)
    {
        $bulan = $this->bulan($request);
        $a = AuditOwnerService::bulan($bulan);
        $k = $a['keuangan'];

        $baris = [['AUDIT BULANAN - ' . $bulan->translatedFormat('F Y')], []];
        $seksi = function (string $judul, array $kepala, iterable $data) use (&$baris) {
            $baris[] = [$judul];
            $baris[] = $kepala;
            foreach ($data as $r) {
                $baris[] = $r;
            }
            $baris[] = [];
        };

        $seksi('1. RINGKASAN KEUANGAN', ['Uraian', 'Nilai'], [
            ['Omzet nota (tidak termasuk batal)', $k['omzet']], ['Jumlah nota', $k['jumlah_nota']], ['HPP material', $k['hpp']],
            ['Rework (biaya material)', $k['rework']], ['Waste diterapkan', $k['waste']], ['Laba kotor', $k['laba_kotor']], ['Margin %', $k['margin']],
            ['Omzet jersey', $k['omzet_jersey']], ['Kas masuk (pembayaran nota, tanpa saldo deposit)', $k['kas_masuk_total']], ['Setoran deposit pelanggan', $k['setoran_deposit']],
            ['Nota dibayar dari saldo deposit', $k['pemakaian_deposit']], ['Pembayaran jersey', $k['pembayaran_jersey']],
            ['Pembelian bahan', $k['pembelian_total']], ['Pembayaran hutang (dana pemilik)', $k['pembayaran_hutang']], ['Kas kecil keluar', $k['kas_kecil_total']],
            ['Biaya dibayar pemilik', $k['kas_kecil_pemilik']],
            ['Nota dibatalkan (jumlah)', $k['nota_dibatalkan_jumlah']], ['Nota dibatalkan (nilai)', $k['nota_dibatalkan_nilai']],
            ['Saldo kas (buku besar)', $k['saldo_kas']], ['Saldo bank', $k['saldo_bank']], ['Piutang', $k['piutang']], ['Hutang', $k['hutang']],
        ]);
        $seksi('2. KAS HARIAN / CLOSING', ['Tanggal', 'Kasir', 'Saldo Awal', 'Saldo Akhir Sistem', 'Saldo Fisik', 'Selisih', 'Status', 'Keterangan'],
            $a['kas_harian']->map(fn ($r) => [$r->tgl_kas, $r->kasir, $r->saldo_awal, $r->saldo_akhir, $r->saldo_fisik, $r->selisih, $r->status_lock, $r->keterangan_closing]));
        $seksi('3. PERMINTAAN BAHAN (PR)', ['Nomor', 'Tanggal', 'Status', 'Pembuat', 'Item', 'Catatan'],
            $a['permintaan']['pr']->map(fn ($r) => [$r->nomor, $r->tanggal, $r->status, $r->pembuat, $r->item, $r->catatan]));
        $seksi('4. PURCHASE ORDER', ['Nomor', 'Tanggal', 'Supplier', 'Status', 'Total'],
            $a['permintaan']['po']->map(fn ($r) => [$r->nomor, $r->tanggal, $r->nama_supplier, $r->status, $r->total]));
        $seksi('5. MUTASI STOK PER BAHAN', ['Bahan', 'Satuan', 'Stok Awal', 'Masuk', 'Keluar Cetak', 'Keluar Waste', 'Keluar Rework', 'Keluar Lain', 'Penyesuaian', 'Stok Akhir', 'Nilai Salah Cetak', 'Selisih Kontrol'],
            $a['stok']->map(fn ($r) => [$r->nama_bahan, $r->satuan, $r->stok_awal, $r->masuk, $r->keluar_cetak, $r->keluar_waste, $r->keluar_rework, $r->keluar_lain, $r->penyesuaian, $r->stok_akhir, $r->nilai_salah_cetak, $r->selisih_kontrol]));
        $seksi('6. WASTE', ['Nomor', 'Tanggal', 'Bahan', 'Satuan', 'Qty', 'Jenis', 'Status', 'Biaya', 'Alasan', 'Petugas'],
            $a['salah_cetak']['waste']->map(fn ($r) => [$r->nomor, $r->tanggal, $r->nama_bahan, $r->satuan_stok, $r->qty, $r->jenis, $r->status, $r->biaya, $r->alasan, $r->petugas]));
        $seksi('7. REWORK', ['Nomor', 'Tanggal', 'Qty', 'Status', 'Biaya Material', 'Catatan', 'Petugas'],
            $a['salah_cetak']['rework']->map(fn ($r) => [$r->nomor, $r->tanggal, $r->qty_rework, $r->status, $r->biaya_material, $r->catatan, $r->petugas]));
        $seksi('8. PENYESUAIAN STOK / OPNAME', ['Tanggal', 'Bahan', 'Satuan', 'Stok Sebelum', 'Stok Sesudah', 'Selisih', 'Nilai', 'Referensi', 'Keterangan', 'Petugas'],
            $a['penyesuaian']->map(fn ($r) => [$r->tanggal, $r->nama_bahan, $r->satuan_stok, $r->stok_sebelum, $r->stok_sesudah, $r->selisih, $r->nilai, $r->referensi, $r->keterangan, $r->petugas]));
        $seksi('9. APPROVAL', ['Dibuat', 'Jenis', 'Ringkasan', 'Pengaju', 'Alasan', 'Status', 'Pemutus', 'Catatan'],
            $a['approval']['daftar']->map(fn ($r) => [$r->created_at, $r->tipe_aksi, $r->ringkasan, $r->pengaju, $r->alasan_pengajuan, $r->status, $r->pemutus, $r->catatan_keputusan]));
        $seksi('10. NOTA DIBATALKAN', ['Nomor', 'Nilai', 'Tanggal Batal', 'Alasan'],
            $k['nota_dibatalkan']->map(fn ($r) => [$r->nomor_nota, $r->total_bayar, $r->tgl_dibatalkan_nota, $r->alasan_batal_nota]));
        $seksi('11. INPUT PER PENGGUNA', array_merge(['Pengguna'], $a['input_user']['kolom']),
            $a['input_user']['baris']->map(fn ($r) => array_merge([$r['nama']], array_map(fn ($c) => $r[$c] ?? 0, $a['input_user']['kolom']))));
        $seksi('12. JEJAK AKTIVITAS PENTING', ['Waktu', 'Pengguna', 'Event', 'Objek', 'Keterangan'],
            $a['jejak']['penting']->map(fn ($r) => [$r->created_at, $r->user_label, $r->event, class_basename($r->auditable_type), $r->keterangan]));

        $fn = 'audit-bulanan-' . $bulan->format('Y-m') . '.csv';

        return response()->streamDownload(function () use ($baris) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($baris as $b) {
                fputcsv($out, \App\Support\CsvAman::baris($b), ';');
            }
            fclose($out);
        }, $fn, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
