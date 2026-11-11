<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * ORION GRAFIKA PREMIUM (gratis, tanpa API).
 *
 * Halaman-halaman di bawah ini adalah fitur premium yang sudah bisa dipakai
 * KEPADA SEMUA ROLE login tanpa biaya API apa pun. Tidak ada panggilan ke
 * layanan luar:
 *  - WhatsApp: tautan wa.me (klik -> aplikasi WA terbuka dengan pesan terisi).
 *  - Portal pelanggan: signed URL (HMAC APP_KEY) berumur 30 hari, tanpa data baru.
 *  - Asisten order: parsing teks sepenuhnya di browser.
 * Semua query READ-ONLY terhadap tabel yang sudah ada. Zero schema change.
 *
 * Template pesan WA (nota & piutang) bisa diubah operator sendiri dari
 * /premium/piutang-wa (kolom app_settings.wa_pesan_template).
 */
class PremiumController extends Controller
{
    public const WA_TEMPLATE_DEFAULT = "Halo {nama}, ini Orion Grafika.\nMengingatkan nota {nota} ({tanggal}) sebesar Rp {total} masih berstatus {status}.\nMohon konfirmasi pembayarannya. Terima kasih.";

    public function index()
    {
        $fitur = [
            ['nama' => 'Kirim WA Nota & Piutang', 'status' => 'Berjalan', 'route' => route('premium.piutang-wa'), 'tier' => 'Operator', 'ikon' => 'fa-whatsapp fab', 'desc' => 'Satu klik dari halaman piutang: WhatsApp terbuka dengan pesan pengingat terisi otomatis. Tidak perlu gateway, tidak ada biaya.'],
            ['nama' => 'Portal Lacak Pelanggan', 'status' => 'Berjalan', 'route' => route('premium.portal'), 'tier' => 'Operator', 'ikon' => 'fa-link fas', 'desc' => 'Tautan unik berumur 30 hari per nota. Pelanggan melihat progres produksi tanpa login dan tanpa menelepon kasir.'],
            ['nama' => 'Analisis 30 Hari + Margin', 'status' => 'Berjalan', 'route' => route('premium.analisis'), 'tier' => 'Insight', 'ikon' => 'fa-chart-simple fas', 'desc' => 'Omzet harian, jam sibuk, rata-rata nota, produk terlaris, dan margin realisasi dari HPP snapshot.'],
            ['nama' => 'Prediksi Restock Cerdas', 'status' => 'Berjalan', 'route' => route('premium.prediksi-stok'), 'tier' => 'Insight', 'ikon' => 'fa-boxes-stacked fas', 'desc' => 'Bahan baku diurutkan dari sisa hari pakai tersedikit (pemakaian 60 hari). Bahan kritis tampil paling atas.'],
            ['nama' => 'Asisten Order Lokal', 'status' => 'Berjalan', 'route' => route('premium.asisten'), 'tier' => 'Insight', 'ikon' => 'fa-wand-magic-sparkles fas', 'desc' => 'Tempel chat pelanggan, dapatkan ringkasan terstruktur (item, jumlah, ukuran, tenggat) tanpa API.'],
            ['nama' => 'Tema Premium (Obsidian Gold & Porcelain Bronze)', 'status' => 'Berjalan', 'route' => route('pengaturan-aplikasi.edit'), 'tier' => 'Enterprise', 'ikon' => 'fa-gem fas', 'desc' => 'Dua tema eksklusif: obsidian gelap aksen emas dan porcelain hangat aksen perunggu.'],
            ['nama' => 'Approval Berjenjang', 'status' => 'Sudah tersedia', 'route' => url('/approval'), 'tier' => 'Enterprise', 'ikon' => 'fa-stamp fas', 'desc' => 'Pembatalan nota, edit nota dan permintaan pembelian lewat antrean persetujuan berjenjang (sudah aktif).'],
            ['nama' => 'Ringkasan Cadangan', 'status' => 'Berjalan', 'route' => route('premium.cadangan'), 'tier' => 'Enterprise', 'ikon' => 'fa-database fas', 'desc' => 'Kesehatan backup harian/mingguan/bulanan: terakhir berjalan, ukuran, kegagalan, dan offsite.'],
            ['nama' => 'Multi-cabang & Konsolidasi', 'status' => 'Segera', 'route' => null, 'tier' => 'Enterprise', 'ikon' => 'fa-code-branch fas', 'desc' => 'Stok dan laporan gabungan antar cabang/gudang. Membutuhkan penambahan kolom/entitas gudang-stok.'],
            ['nama' => 'Premium Paywall & Lisensi', 'status' => 'Segera', 'route' => null, 'tier' => 'Lisensi', 'ikon' => 'fa-key fas', 'desc' => 'Kunci aktivasi per-fitur, masa aktif, dan halaman harga publik. Setelah paywall aktif, fitur di atas dikunci bertahap.'],
        ];

        $berjalan = count(array_filter($fitur, fn ($f) => $f['status'] === 'Berjalan'));

        return view('premium.index', compact('fitur', 'berjalan'));
    }

    public function analisis()
    {
        $from = now()->subDays(29)->startOfDay();

        $harian = DB::table('transaksi')
            ->where('tgl_order', '>=', $from)
            ->where('status_nota', 'Aktif')
            ->selectRaw('DATE(tgl_order) as d, SUM(total_bayar) as omzet, COUNT(*) as nota')
            ->groupByRaw('DATE(tgl_order)')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        // Isi 30 hari penuh (hari tanpa transaksi tetap tampil sebagai 0)
        $hari = [];
        for ($i = 29; $i >= 0; $i--) {
            $tgl = now()->subDays($i)->format('Y-m-d');
            $row = $harian->get($tgl);
            $hari[] = [
                'label' => now()->subDays($i)->translatedFormat('d M'),
                'omzet' => (float) ($row->omzet ?? 0),
                'nota' => (int) ($row->nota ?? 0),
            ];
        }
        $maksOmzet = max(1, max(array_column($hari, 'omzet')));

        $jam = DB::table('transaksi')
            ->where('tgl_order', '>=', $from)
            ->where('status_nota', 'Aktif')
            ->selectRaw('HOUR(tgl_order) as h, COUNT(*) as n')
            ->groupByRaw('HOUR(tgl_order)')
            ->pluck('n', 'h');

        $kpi = DB::table('transaksi')
            ->where('tgl_order', '>=', $from)
            ->where('status_nota', 'Aktif')
            ->selectRaw('SUM(total_bayar) as omzet, AVG(total_bayar) as rata_nota, COUNT(*) as nota,
                         SUM(CASE WHEN status_pembayaran != \'Lunas\' THEN 1 ELSE 0 END) as belum_lunas')
            ->first();

        $margin = DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->where('t.tgl_order', '>=', $from)
            ->where('t.status_nota', 'Aktif')
            ->selectRaw('SUM(d.subtotal) as jual, SUM(d.subtotal - COALESCE(d.hpp_snapshot, 0)) as margin')
            ->first();

        $marginPersen = ($margin->jual ?? 0) > 0 ? round(($margin->margin ?? 0) / $margin->jual * 100, 1) : null;

        $topProduk = DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->join('produk as p', 'p.id_produk', '=', 'd.id_produk')
            ->where('t.tgl_order', '>=', $from)
            ->where('t.status_nota', 'Aktif')
            ->selectRaw('p.nama_produk, SUM(d.qty) as qty, SUM(d.subtotal) as omzet')
            ->groupBy('p.nama_produk')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();

        $backup = DB::table('backup_logs')
            ->orderByDesc('finished_at')
            ->limit(8)
            ->get();

        return view('premium.analisis', compact('hari', 'maksOmzet', 'jam', 'kpi', 'marginPersen', 'topProduk', 'backup'));
    }

    public function piutangWa()
    {
        $template = trim((string) (\App\Models\AppSetting::current()->wa_pesan_template ?? ''));
        if ($template === '') {
            $template = self::WA_TEMPLATE_DEFAULT;
        }

        $isiPesan = function ($n, string $nama) use ($template) {
            return strtr($template, [
                '{nama}' => $nama,
                '{nota}' => (string) $n->nomor_nota,
                '{tanggal}' => optional(\Carbon\Carbon::parse($n->tgl_order))->translatedFormat('d M Y'),
                '{total}' => number_format((float) $n->total_bayar, 0, ',', '.'),
                '{status}' => (string) $n->status_pembayaran,
                '{wa}' => (string) (\App\Models\AppSetting::current()->telepon ?? ''),
            ]);
        };

        $nota = DB::table('transaksi as t')
            ->leftJoin('konsumen as k', 'k.id_konsumen', '=', 't.id_konsumen')
            ->where('t.status_nota', 'Aktif')
            ->whereIn('t.status_pembayaran', ['Belum Bayar', 'DP'])
            ->where('t.tgl_order', '>=', now()->subDays(60))
            ->selectRaw('t.id_transaksi, t.nomor_nota, t.tgl_order, t.total_bayar, t.status_pembayaran,
                         COALESCE(NULLIF(k.telepon, \'\'), \'\') as telepon,
                         COALESCE(NULLIF(k.nama_konsumen, \'\'), t.nama_konsumen_manual, \'Pelanggan\') as nama')
            ->orderByDesc('t.tgl_order')
            ->limit(40)
            ->get();

        $rows = $nota->map(function ($n) use ($isiPesan) {
            $tel = $this->nomorWa($n->telepon);
            $pesan = $isiPesan($n, $n->nama);
            $n->wa_link = $tel ? 'https://wa.me/' . $tel . '?text=' .rawurlencode($pesan) : null;
            $n->pesan = $pesan;
            return $n;
        });

        $waTemplate = \App\Models\AppSetting::current()->wa_pesan_template ?? '';

        return view('premium.piutang-wa', compact('rows', 'waTemplate', 'template'));
    }

    /**
     * Simpan template WA milik operator sendiri. Placeholder: {nama} {nota}
     * {tanggal} {total} {status} {wa}. Kosong = kembali ke bawaan aplikasi.
     */
    public function simpanWaTemplate(\Illuminate\Http\Request $request)
    {
        $request->validate(['wa_pesan_template' => 'nullable|string|max:2000']);

        \App\Models\AppSetting::current()->update([
            'wa_pesan_template' => trim((string) $request->input('wa_pesan_template')) ?: null,
        ]);
        \App\Models\AppSetting::clearCache();

        return back()->with('status', 'Template pesan WhatsApp tersimpan.');
    }

    public function prediksiStok()
    {
        $from = now()->subDays(60);

        $pakai = DB::table('kartu_stok')
            ->where('tipe', 'keluar')
            ->where('tanggal', '>=', $from)
            ->selectRaw('id_bahan, SUM(qty) as s')
            ->groupBy('id_bahan')
            ->pluck('s', 'id_bahan');

        $bahan = DB::table('bahan_baku')
            ->selectRaw('id_bahan, nama_bahan, stok, stok_minimal, satuan_stok')
            ->orderBy('stok', 'asc')
            ->limit(200)
            ->get()
            ->map(function ($b) use ($pakai) {
                $p60 = (float) ($pakai[$b->id_bahan] ?? 0);
                $perHari = $p60 > 0 ? $p60 / 60 : 0.0;
                $b->pakai_60d = $p60;
                $b->per_hari = $perHari;
                $b->sisa_hari = $perHari > 0 ? floor((float) $b->stok / $perHari) : null;
                if ((float) $b->stok <= (float) $b->stok_minimal) {
                    $b->level = 'kritis';
                } elseif ($b->sisa_hari !== null && $b->sisa_hari < 14) {
                    $b->level = 'perhatian';
                } else {
                    $b->level = 'aman';
                }
                return $b;
            });

        $urut = $bahan->sortBy(function ($b) {
            return $b->sisa_hari === null ? PHP_INT_MAX : $b->sisa_hari;
        })->values()->take(30);

        return view('premium.prediksi-stok', ['bahan' => $urut]);
    }

    public function portal()
    {
        $notas = DB::table('transaksi as t')
            ->leftJoin('konsumen as k', 'k.id_konsumen', '=', 't.id_konsumen')
            ->where('t.status_nota', 'Aktif')
            ->selectRaw('t.id_transaksi, t.nomor_nota, t.tgl_order,
                         COALESCE(NULLIF(k.nama_konsumen, \'\'), t.nama_konsumen_manual, \'Pelanggan\') as nama')
            ->orderByDesc('t.tgl_order')
            ->limit(14)
            ->get()
            ->map(function ($n) {
                $n->lacak_url = URL::signedRoute('lacak.nota', ['id' => $n->id_transaksi], now()->addDays(30));
                return $n;
            });

        return view('premium.portal', compact('notas'));
    }

    public function asisten()
    {
        return view('premium.asisten');
    }

    public function cadangan()
    {
        $logs = DB::table('backup_logs')->orderByDesc('started_at')->limit(30)->get();
        $sukses = $logs->where('status', 'Sukses');
        $gagal = $logs->where('status', 'Gagal');
        $terakhir = $sukses->first();
        $totalUkuran = (int) $sukses->sum('ukuran_bytes');

        return view('premium.cadangan', compact('logs', 'sukses', 'gagal', 'terakhir', 'totalUkuran'));
    }

    /**
     * Publik (tanpa login) - signed URL berumur 30 hari, dibuat di /premium/portal.
     * Menampilkan progres AGREGAT item saja; tanpa HPP, komisi, atau id internal.
     */
    public function lacak(Request $request, int $id)
    {
        if (!URL::hasValidSignature($request)) {
            abort(403, 'Tautan pelacakan tidak valid atau sudah kedaluwarsa.');
        }

        $t = DB::table('transaksi as t')
            ->leftJoin('konsumen as k', 'k.id_konsumen', '=', 't.id_konsumen')
            ->where('t.id_transaksi', $id)
            ->where('t.status_nota', 'Aktif')
            ->selectRaw('t.nomor_nota, t.tgl_order, t.status_pembayaran, t.status_collecting,
                         COALESCE(NULLIF(k.nama_konsumen, \'\'), t.nama_konsumen_manual, \'Pelanggan\') as nama')
            ->first();

        $agg = $t ? DB::table('transaksi_detail')
            ->where('id_transaksi', $id)
            ->selectRaw("COUNT(*) as total,
                         SUM(CASE WHEN status_spk = 'Antre' THEN 1 ELSE 0 END) as antre,
                         SUM(CASE WHEN status_spk IN ('Desain','Pending') THEN 1 ELSE 0 END) as proses,
                         SUM(CASE WHEN status_spk = 'Cetak' THEN 1 ELSE 0 END) as cetak,
                         SUM(CASE WHEN status_spk = 'Selesai' THEN 1 ELSE 0 END) as selesai,
                         SUM(CASE WHEN status_spk = 'Batal' THEN 1 ELSE 0 END) as batal")
            ->first() : null;

        return view('premium.lacak', ['t' => $t, 'agg' => $agg]);
    }

    private function nomorWa(?string $telepon): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $telepon);
        if ($d === '') {
            return null;
        }
        if (str_starts_with($d, '0')) {
            $d = '62' . substr($d, 1);
        }
        if (!str_starts_with($d, '62')) {
            return null;
        }
        return $d;
    }
}
