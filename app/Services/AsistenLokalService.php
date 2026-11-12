<?php

namespace App\Services;

use App\Models\User;
use App\Support\AksesHarga;
use Illuminate\Support\Facades\DB;

/**
 * Asisten Order Orion - jawaban LOKAL dari data ERP sendiri.
 *
 * Tidak ada API luar, tidak ada model eksternal: mesin ini menebak niat
 * pertanyaan dari pola Bahasa Indonesia, lalu menjalankan query yang sama
 * dengan halaman laporan. Jawaban narasi "rekap" disusun dari angka asli;
 * bila ASISTEN_AI_ENABLED=true dan Ollama lokal tersedia, fasilitas Ollama
 * hanya digunakan untuk MEMBASA angka menjadi kalimat, bukan menghitung.
 *
 * Harga/nominal hanya dibuka untuk role yang boleh melihat harga.
 */
class AsistenLokalService
{
    /**
     * @return array{reply: string, intent: string, meta?: array<string,mixed>}
     */
    public function jawab(string $pesan, ?User $user): array
    {
        $t = $this->normalisasi($pesan);
        if ($t === '') {
            return $this->balas('Halo! Saya Asisten Orion. Tanya apa saja seputar order, stok, kas, atau pilih "Rekap bulan ini" di kolom kanan. Ketik /bantuan untuk daftar perintah.', 'kosong');
        }
        if (in_array($t, ['/bantuan', 'bantuan', 'help', 'tolong'], true)) {
            return $this->balas($this->daftarPerintah(), 'bantuan');
        }

        // Rekap laporan (omzet/laba/piutang = uang, dibatasi hak lihat harga).
        if (preg_match('/(rekap|ringkasan|resume|rangkuman|laporan singkat)/u', $t)) {
            if (!AksesHarga::bolehLihat($user)) {
                return $this->balas('Maaf, ringkasan laporan keuangan (omzet, laba, piutang) hanya untuk role yang berhak melihat harga. Saya bisa jawab pertanyaan stok & antrean produksi, silakan ketik /bantuan.', 'ditolak-harga');
            }
            $periode = str_contains($t, 'minggu') ? 'minggu' : (str_contains($t, 'bulan') ? 'bulan' : 'hari');
            return $this->balas($this->rekapTeks($periode, $user), 'rekap');
        }

        // HPP internal (owner/role ber-harga): "hpp nota LAN-1", "hpp banner
        // per m2", "hpp FLS280 3x1". HARUS sebelum nota lookup umum, karena
        // "hpp nota X" juga cocok pola nota biasa.
        if (preg_match('/\bhpp\b|biaya produksi|modal produksi|bahan produksi/u', $t)) {
            if (!AksesHarga::bolehLihat($user)) {
                return $this->balas('Maaf, HPP (harga pokok produksi) adalah data internal - hanya untuk role yang berhak melihat harga.', 'ditolak-harga');
            }
            return $this->hppJawab($t, $user);
        }

        // Cek nota tertentu: "nota LAN-1" / "status nota 2026-0042" / "spk INV-88"
        if (preg_match('/(nota|invoice|inv|spk|pesanan|order)\s*(nomor|no\.?|#|:)?\s*[:=\-]?\s*([a-z0-9\-\/]*\d[a-z0-9\-\/]*)/u', $t, $m)) {
            return $this->notaLookup($m[3], $user);
        }

        // Omzet / pendapatan
        if (preg_match('/(omzet|pendapatan|penjualan kotor|pemasukan)/u', $t)) {
            if (!AksesHarga::bolehLihat($user)) {
                return $this->balas('Maaf, omzet hanya untuk role yang berhak melihat harga.', 'ditolak-harga');
            }
            $periode = str_contains($t, 'bulan') ? 'bulan' : (str_contains($t, 'minggu') ? 'minggu' : 'hari');
            [$mulai, $akhir, $label] = $this->rentang($periode);
            $omzet = (float) DB::table('transaksi')
                ->where('status_nota', '!=', 'Dibatalkan')
                ->whereBetween('tgl_order', [$mulai, $akhir])
                ->sum('total_bayar');
            $n = DB::table('transaksi')->where('status_nota', '!=', 'Dibatalkan')->whereBetween('tgl_order', [$mulai, $akhir])->count();
            return $this->balas("Omzet {$label}: **Rp " . number_format($omzet, 0, ',', '.') . "** dari {$n} nota (belum termasuk jersey).", 'omzet');
        }

        // Jumlah order / nota masuk
        if (preg_match('/(berapa (banyak )?(order|nota|pesanan|orderan|transaksi))/u', $t)) {
            $periode = str_contains($t, 'bulan') ? 'bulan' : (str_contains($t, 'minggu') ? 'minggu' : 'hari');
            [$mulai, $akhir, $label] = $this->rentang($periode);
            $n = DB::table('transaksi')->where('status_nota', '!=', 'Dibatalkan')->whereBetween('tgl_order', [$mulai, $akhir])->count();
            return $this->balas("Order masuk {$label}: **{$n} nota** (tidak termasuk yang dibatalkan & jersey).", 'jumlah-order');
        }

        // Piutang / belum lunas
        if (preg_match('/(piutang|belum lunas|belum bayar|nunggak)/u', $t)) {
            if (!AksesHarga::bolehLihat($user)) {
                return $this->balas('Maaf, angka piutang hanya untuk role yang berhak melihat harga.', 'ditolak-harga');
            }
            $row = $this->piutangResidual();
            return $this->balas("Piutang berjalan: **" . number_format($row['jumlah']) . " nota**, sisa tagihan **Rp " . number_format($row['total'], 0, ',', '.') . "**.", 'piutang');
        }

        // Antrean produksi
        if (preg_match('/(antre|antrian|antri|antrean produksi)/u', $t)) {
            $antre = DB::table('transaksi_detail as d')
                ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
                ->where('d.status_spk', 'Antre')
                ->where('t.status_nota', '!=', 'Dibatalkan')
                ->count();
            $proses = DB::table('transaksi_detail as d')
                ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
                ->whereIn('d.status_spk', ['Desain', 'Cetak'])
                ->where('t.status_nota', '!=', 'Dibatalkan')
                ->count();
            return $this->balas("Antrean produksi saat ini: **{$antre} item Antre** menunggu, **{$proses} item** sedang dikerjakan (Desain/Cetak).", 'antre');
        }

        // Stok menipis / habis
        if (preg_match('/(stok (menipis|hampir habis|minim|kurang|tipis)|bahan (habis|menipis|nyaris habis))/u', $t)) {
            $menipis = DB::table('bahan_baku')->where('status', 'aktif')->where('stok_minimal', '>', 0)->whereColumn('stok', '<=', 'stok_minimal')->count();
            $habis = DB::table('bahan_baku')->where('status', 'aktif')->where('stok', '<=', 0)->count();
            $top = DB::table('bahan_baku')->where('status', 'aktif')->where('stok_minimal', '>', 0)
                ->whereColumn('stok', '<=', 'stok_minimal')
                ->orderBy('stok')->limit(5)->get(['nama_bahan', 'stok', 'satuan_stok']);
            $daftar = $top->map(fn ($b) => "- {$b->nama_bahan} (sisa {$b->stok} {$b->satuan_stok})")->implode("\n");
            $bal = "Stok menipis: **{$menipis} bahan**, stok habis: **{$habis} bahan**.";
            if ($daftar) {
                $bal .= "\nPaling kritis:\n{$daftar}";
            }
            return $this->balas($bal, 'stok');
        }

        // Produk terlaris (30 hari)
        if (preg_match('/(produk|barang|item).*(terlaris|laku|terbanyak)|terlaris/u', $t)) {
            $top = DB::table('transaksi_detail as d')
                ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
                ->leftJoin('produk as p', 'p.id_produk', '=', 'd.id_produk')
                ->where('t.status_nota', '!=', 'Dibatalkan')
                ->where('t.tgl_order', '>=', now()->subDays(30))
                ->selectRaw("COALESCE(p.nama_produk, d.nama_file) as nama, SUM(d.qty) as qty")
                ->groupBy('d.id_produk', 'd.nama_file')
                ->orderByDesc('qty')
                ->limit(5)
                ->get();
            if ($top->isEmpty()) {
                return $this->balas('Belum ada data penjualan 30 hari terakhir.', 'terlaris');
            }
            $daftar = $top->map(fn ($p, $i) => ($i + 1) . ". {$p->nama} ({$p->qty} pcs)")->implode("\n");
            return $this->balas("Produk terlaris 30 hari terakhir:\n{$daftar}", 'terlaris');
        }

        // Saldo kas masuk hari ini
        if (preg_match('/(kas (masuk|hari ini|sekarang)|saldo kas|uang masuk)/u', $t)) {
            if (!AksesHarga::bolehLihat($user)) {
                return $this->balas('Maaf, angka kas/uang hanya untuk role yang berhak melihat harga.', 'ditolak-harga');
            }
            [$mulai, $akhir] = $this->rentang('hari');
            $masuk = (float) DB::table('pembayaran_riwayat')
                ->whereBetween('tgl_bayar', [$mulai, $akhir])
                ->where('metode', '!=', 'Deposit')
                ->sum('nominal');
            $nTrx = DB::table('pembayaran_riwayat')->whereBetween('tgl_bayar', [$mulai, $akhir])->count();
            return $this->balas("Kas masuk hari ini: **Rp " . number_format($masuk, 0, ',', '.') . "** ({$nTrx} pembayaran, tidak termasuk setoran deposit).", 'kas');
        }

        // Cari konsumen
        if (preg_match('/(konsumen|pelanggan|klien|customer)\s*[:\-]?\s*([a-z0-9 .]{2,})/u', $t, $m)) {
            $q = trim($m[2]);
            $rows = DB::table('konsumen')->where('nama_konsumen', 'like', '%' . $q . '%')->limit(5)->get();
            if ($rows->isEmpty()) {
                return $this->balas("Tidak ada konsumen dengan nama mirip \"{$q}\". Coba /nota <nomor> untuk cek pesanan tertentu.", 'konsumen');
            }
            $items = $rows->map(fn ($k) => "- {$k->nama_konsumen} ({$k->telepon})")->implode("\n");
            return $this->balas("Konsumen mirip \"{$q}\":\n{$items}", 'konsumen');
        }

        return $this->balas($this->daftarPerintah(), 'fallback');
    }

    private function normalisasi(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = preg_replace('/\s+/u', ' ', $s);
        return $s;
    }

    private function balas(string $reply, string $intent): array
    {
        return ['reply' => $reply, 'intent' => $intent];
    }

    private function rentang(string $periode): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        return match ($periode) {
            'minggu' => [now($tz)->startOfWeek(), now($tz)->endOfWeek(), 'minggu ini'],
            'bulan' => [now($tz)->startOfMonth(), now($tz)->endOfMonth(), 'bulan ini'],
            default => [now($tz)->startOfDay(), now($tz)->endOfDay(), 'hari ini'],
        };
    }

    private function piutangResidual(): array
    {
        return (array) DB::table('transaksi')
            ->where('status_nota', '!=', 'Dibatalkan')
            ->where('status_pembayaran', '!=', 'Lunas')
            ->selectRaw("COUNT(*) as jumlah, COALESCE(SUM(GREATEST(0, total_bayar - COALESCE((SELECT SUM(pr.nominal) FROM pembayaran_riwayat pr WHERE pr.id_transaksi = transaksi.id_transaksi), 0))), 0) as total")
            ->first() ?: ['jumlah' => 0, 'total' => 0];
    }

    /**
     * Jawab pertanyaan HPP internal. Dua bentuk:
     *   1. "hpp nota <nomor>" - HPP nyata dari hpp_snapshot nota (jangan
     *      dihitung ulang dari BOM live - nilai saat order yang benar).
     *   2. "hpp <produk> [ukuran PxL]" - estimasi bahan dari service yang
     *      SAMA dengan checkout (BomCalculationService::komponen): qty pakai
     *      per rumus BOM, dikonversi ke satuan stok, dikali harga_rata_rata.
     *      Tanpa ukuran → per m² (ukuran 100 x 100 cm).
     * Finishing TIDAK ikut dihitung di sini - dijawab jujur di teks.
     */
    private function hppJawab(string $t, ?User $user): array
    {
        // 1) HPP nota nyata (snapshot saat order, bukan ramalan).
        if (preg_match('/(nota|inv|invoice|spk)\s*(nomor|no\.?|#|:)?\s*[:=\-]?\s*([a-z0-9\-\/]*\d[a-z0-9\-\/]*)/u', $t, $m)) {
            $row = DB::table('transaksi')
                ->whereRaw('LOWER(nomor_nota) LIKE ?', ['%' . mb_strtolower($m[3]) . '%'])
                ->where('status_nota', '!=', 'Dibatalkan')
                ->orderByDesc('tgl_order')
                ->first(['id_transaksi', 'nomor_nota', 'total_bayar']);
            if (!$row) {
                return $this->balas("Nota \"{$m[3]}\" tidak ditemukan.", 'hpp-nota');
            }
            $agg = DB::table('transaksi_detail')
                ->where('id_transaksi', $row->id_transaksi)
                ->selectRaw('SUM(hpp_snapshot) as hpp, SUM(subtotal) as jual')
                ->first();
            $hpp = (float) ($agg->hpp ?? 0);
            $jual = (float) ($agg->jual ?? 0);
            $margin = $jual > 0 ? round(($jual - $hpp) / $jual * 100, 1) : null;
            $teks = "HPP **{$row->nomor_nota}**: Rp " . number_format($hpp, 0, ',', '.')
                . " (bahan sesuai BOM saat order, dari hpp_snapshot)\n"
                . "Jual: Rp " . number_format($jual, 0, ',', '.')
                . " - Laba kotor: Rp " . number_format($jual - $hpp, 0, ',', '.')
                . ($margin !== null ? " ({$margin}%)" : '')
                . "\nBelum termasuk biaya operasional lain (listrik, gaji, sewa).";

            return $this->balas($teks, 'hpp-nota');
        }

        // 2) Estimasi HPP produk dari BOM (service yang sama dengan checkout).
        [$p, $l] = $this->parseUkuranCm($t);
        $perM2 = $p === null; // tanpa ukuran → per m² standar (100 x 100 cm)
        $p = $p ?? 100.0;
        $l = $l ?? 100.0;

        $produk = $this->cariProdukBom($t);
        if (!$produk) {
            return $this->balas("Produk tidak ditemukan di BOM. Sebut nama produk, mis. \"hpp FLS280 1.1m\" atau \"hpp nota LAN-1\" untuk HPP nota nyata.", 'hpp');
        }

        try {
            $boms = \App\Services\BomCalculationService::komponen((int) $produk->id_produk, $p, $l, 1);
        } catch (\Throwable $e) {
            return $this->balas("Gagal menghitung BOM {$produk->nama_produk}.", 'hpp');
        }

        // Idem checkout: qty pakai (satuan hitung) ÷ nilai_konversi × harga
        // rata-rata (satuan stok). Satu query bahan untuk semua baris BOM.
        $bahanIds = collect($boms)->pluck('id_bahan')->unique()->all();
        $bahanMap = $bahanIds
            ? DB::table('bahan_baku')->whereIn('id_bahan', $bahanIds)
                ->get(['id_bahan', 'nama_bahan', 'harga_rata_rata', 'nilai_konversi'])
                ->keyBy('id_bahan')
            : collect();

        $hpp = 0.0;
        $rincian = [];
        foreach ($boms as $bom) {
            $b = $bahanMap[$bom->id_bahan] ?? null;
            $harga = $b ? (float) $b->harga_rata_rata : 0.0;
            $konversi = $b && (float) $b->nilai_konversi > 0 ? (float) $b->nilai_konversi : 1.0;
            $biaya = ($bom->qty_pakai > 0 ? $bom->qty_pakai : 0) / $konversi * $harga;
            $hpp += $biaya;
            if ($biaya > 0) {
                $rincian[] = '- ' . ($b->nama_bahan ?? 'Bahan #' . $bom->id_bahan) . ': Rp ' . number_format($biaya, 0, ',', '.');
            }
        }

        $luas = ($p * $l) / 10000;
        $label = $perM2 ? 'per m² (ukuran 100 x 100 cm)' : "ukuran " . rtrim(rtrim(number_format($p / 100, 2, ',', '.'), '0'), ',') . " x " . rtrim(rtrim(number_format($l / 100, 2, ',', '.'), '0'), ',') . " m";
        $teks = "Estimasi HPP {$produk->nama_produk} {$label}: Rp " . number_format($hpp, 0, ',', '.');
        if (!$perM2 && $luas > 0) {
            $teks .= " (Rp " . number_format($hpp / $luas, 0, ',', '.') . "/m²)";
        }
        if (!empty($rincian)) {
            $teks .= "\nRincian bahan (harga rata-rata):\n" . implode("\n", array_slice($rincian, 0, 6));
        }
        $teks .= "\nBahan saja dari BOM - belum termasuk finishing & biaya operasional. Angka final di nota tetap dari kasir.";

        return $this->balas($teks, 'hpp');
    }

    /** Cari produk untuk HPP: token nama (>=4 huruf) atau kamus produk_alias. */
    private function cariProdukBom(string $t): ?object
    {
        $token = [];
        foreach (preg_split('/\s+/u', preg_replace('/\bhpp\b|biaya produksi|modal produksi|per m2|per m²|ukuran|berapa|harga|\d+(?:[.,]\d+)?\s*[x×]\s*\d+(?:[.,]\d+)?/u', ' ', $t) ?: []) ?: [] as $w) {
            if (mb_strlen($w) >= 4 && !is_numeric($w)) {
                $token[] = $w;
            }
        }
        if (empty($token)) {
            return null;
        }

        // Nama produk dulu, lalu kamus alias (data owner, tanpa hardcode).
        $produk = DB::table('produk')->where('status', 'aktif')->get(['id_produk', 'nama_produk']);
        foreach ($produk as $p) {
            foreach ($token as $w) {
                if (str_contains($this->normalisasi($p->nama_produk), $w)) {
                    return $p;
                }
            }
        }
        $aliasIds = DB::table('produk_alias')->whereIn('alias', $token)->orderBy('id_produk')->pluck('id_produk')->unique()->values();
        if ($aliasIds->isNotEmpty()) {
            $p = DB::table('produk')->whereIn('id_produk', $aliasIds)->orderBy('id_produk')->first(['id_produk', 'nama_produk']);
            if ($p) {
                return $p;
            }
        }

        return null;
    }

    /** Ukuran dari teks: "3x1", "3,5 x 2", "300x100" (cm default? meter) → cm. */
    private function parseUkuranCm(string $t): array
    {
        if (!preg_match('/(\d+(?:[.,]\d+)?)\s*[x×]\s*(\d+(?:[.,]\d+)?)/u', $t, $m)) {
            return [null, null];
        }
        $a = (float) str_replace(',', '.', $m[1]);
        $b = (float) str_replace(',', '.', $m[2]);
        if ($a <= 0 || $b <= 0 || $a > 10000 || $b > 10000) {
            return [null, null];
        }
        // Konvensi percetakan: angka <= 25 dianggap METER (banner 3 x 1),
        // lebih dari itu dianggap cm (A3 = 30 x 42).
        if ($a <= 25 && $b <= 25) {
            $a *= 100;
            $b *= 100;
        }

        return [$a, $b];
    }

    private function notaLookup(string $token, ?User $user): array
    {
        $rows = DB::table('transaksi')
            ->leftJoin('konsumen', 'konsumen.id_konsumen', '=', 'transaksi.id_konsumen')
            ->whereRaw('LOWER(transaksi.nomor_nota) LIKE ?', ['%' . mb_strtolower($token) . '%'])
            ->orderByDesc('transaksi.tgl_order')
            ->limit(3)
            ->get(['transaksi.nomor_nota', 'konsumen.nama_konsumen', 'transaksi.tgl_order', 'transaksi.total_bayar', 'transaksi.status_pembayaran', 'transaksi.status_order']);
        if ($rows->isEmpty()) {
            return $this->balas("Nota \"{$token}\" tidak ditemukan. Cek penulisan nomornya (tanpa spasi), atau buka menu Riwayat Nota.", 'nota');
        }
        $uangan = AksesHarga::bolehLihat($user);
        $lines = $rows->map(function ($r) use ($uangan) {
            $tanggal = \Carbon\Carbon::parse($r->tgl_order)->translatedFormat('d M Y H:i');
            $uang = $uangan ? "Rp " . number_format($r->total_bayar, 0, ',', '.') : '(harga disembunyikan)';
            return "**{$r->nomor_nota}** - {$r->nama_konsumen} - {$tanggal}\n  Total: {$uang} - {$r->status_pembayaran} - {$r->status_order}";
        })->implode("\n\n");
        return $this->balas($lines, 'nota');
    }

    private function rekapTeks(string $periode, ?User $user): string
    {
        [$mulai, $akhir, $label] = $this->rentang($periode);

        $nota = DB::table('transaksi')->where('status_nota', '!=', 'Dibatalkan')->whereBetween('tgl_order', [$mulai, $akhir])->count();
        $omzet = (float) DB::table('transaksi')->where('status_nota', '!=', 'Dibatalkan')->whereBetween('tgl_order', [$mulai, $akhir])->sum('total_bayar');
        $hpp = (float) DB::table('transaksi_detail as d')->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->where('t.status_nota', '!=', 'Dibatalkan')->whereBetween('t.tgl_order', [$mulai, $akhir])
            ->sum('d.hpp_snapshot');
        $piutang = $this->piutangResidual();
        $laba = $omzet - $hpp;
        $margin = $omzet > 0 ? round(($laba / $omzet) * 100, 1) : 0;
        $spkAntre = DB::table('transaksi_detail')->where('status_spk', 'Antre')->count();
        $belumLunas = DB::table('transaksi')->where('status_nota', '!=', 'Dibatalkan')->where('status_pembayaran', '!=', 'Lunas')->count();

        $tanpaAi = (bool) config('asisten_ai.enabled', false) ? '' : " (mode tanpa AI - aktifkan ASISTEN_AI_ENABLED + Ollama lokal untuk narasi lengkap)";
        $teks = "Rekap {$label}{$tanpaAi}:\n"
            . "- {$nota} nota tercatat, omzet Rp " . number_format($omzet, 0, ',', '.') . "\n"
            . "- HPP (bahan baku sesuai BOM) Rp " . number_format($hpp, 0, ',', '.') . "\n"
            . "- Laba kotor Rp " . number_format($laba, 0, ',', '.') . " ({$margin}%)\n"
            . "- Piutang berjalan: {$piutang['jumlah']} nota, sisa Rp " . number_format($piutang['total'], 0, ',', '.') . "\n"
            . "- Nota belum lunas (semua waktu): {$belumLunas}, antrean Antre: {$spkAntre} item";

        if (!config('asisten_ai.enabled', false)) {
            return $teks;
        }

        return $this->narasiOllama($teks, $periode) ?? ($teks . "\n(Ollama tidak merespons - menampilkan ringkasan angka mentah.)");
    }

    /** AI narator opsional: Ollama lokal; gagal = null, pemanggil memakai teks mentah. */
    private function narasiOllama(string $teksAngka, string $periode): ?string
    {
        $prompt = "Anda asisten manajer percetakan. Ubah angka berikut menjadi ringkasan eksekutif Bahasa Indonesia 4-6 kalimat, natural seperti laporan pemilik usaha, tanpa mengarang angka baru:\n\n{$teksAngka}";

        // Delegasi ke klien bersama (keep_alive + timeout + config satu pintu).
        return \App\Services\OllamaService::generate($prompt);
    }

    private function daftarPerintah(): string
    {
        return "Yang bisa saya jawab (lokal, dari data ERP):\n"
            . "- \"rekap bulan ini\" / \"rekap mingguan\" - ringkasan omzet, HPP, laba, piutang\n"
            . "- \"berapa order hari ini\" / \"omzet hari ini\" / \"rekap minggu ini\"\n"
            . "- \"piutang belum lunas\" / \"stok menipis\" / \"produk terlaris\"\n"
            . "- \"antrean produksi\" / \"kas masuk hari ini\"\n"
            . "- \"nota <nomor>\" mis. nota LAN-1 - cek status nota\n"
            . "- \"hpp nota <nomor>\" - HPP nota dari snapshot saat order\n"
            . "- \"hpp <produk>\" - estimasi HPP bahan per m² dari BOM; dengan ukuran mis. \"hpp banner 3x1\"\n"
            . "- \"konsumen <nama>\" - cari data pelanggan\n\n"
            . "Jawaban uang hanya untuk role yang berhak melihat harga. AI narator (opsional, lokal via Ollama) diaktifkan dengan ASISTEN_AI_ENABLED=true.";
    }
}
