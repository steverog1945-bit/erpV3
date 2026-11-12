<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Remote-ops via Telegram - dibatasi TOTAL:
 *   - Hanya chat_id owner di .env yang dilayani; chat asing diabaikan diam +
 *     dicatat di audit_logs.
 *   - Perintah whitelist: /help /status(/antrian) /kasir /stok /rekap
 *     /health /verify /backup. Tidak ada perintah lain.
 *   - Output: angka COUNT, nama bahan/username, dan status hidup/mati saja.
 *     Tidak ada rupiah, nota, nama pelanggan, harga, omzet/laba, atau data
 *     keuangan apa pun.
 *
 * Long polling (bukan webhook): koneksi keluar HTTPS saja, tidak ada port
 * publik yang terbuka di mesin ini.
 */
class TelegramAsistenService
{
    public function aktif(): bool
    {
        return (bool) config('asisten_telegram.enabled', false)
            && (bool) config('asisten_telegram.token')
            && (bool) config('asisten_telegram.chat_id');
    }

    /** Ambil update dengan long polling. @return array{ok:bool, error?:string, updates:array} */
    public function poll(int $offset = 0): array
    {
        $token = (string) config('asisten_telegram.token');
        try {
            $r = Http::timeout((int) config('asisten_telegram.long_poll_timeout', 25) + 10)
                ->get("https://api.telegram.org/bot{$token}/getUpdates", [
                    'offset' => $offset,
                    'timeout' => (int) config('asisten_telegram.long_poll_timeout', 25),
                ]);
            if (!$r->ok()) {
                return ['ok' => false, 'error' => 'Telegram API HTTP ' . $r->status(), 'updates' => []];
            }
            $d = $r->json();
            return ['ok' => true, 'updates' => $d['result'] ?? []];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'updates' => []];
        }
    }

    public function kirim(string $text, bool $denganMenu = true): bool
    {
        $token = (string) config('asisten_telegram.token');
        $chatId = (string) config('asisten_telegram.chat_id');
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];
        // Reply keyboard persisten: owner tap, tidak perlu ingat-ingat
        // perintah. Parameter $denganMenu tersedia bila suatu hari perlu
        // mengirim balasan tanpa keyboard.
        if ($denganMenu) {
            $payload['reply_markup'] = json_encode([
                'keyboard' => [
                    ['/status', '/kasir'],
                    ['/stok', '/rekap'],
                    ['/health', '/verify'],
                    ['/backup', '/help'],
                ],
                'resize_keyboard' => true,
                'is_persistent' => true,
            ]);
        }
        try {
            $r = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
            return $r->ok();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Proses satu update. Dipanggil dari command & diuji langsung di test. */
    public function prosesUpdate(array $update): void
    {
        $msg = $update['message'] ?? null;
        if (!is_array($msg)) {
            return;
        }
        $dari = (string) ($msg['from']['id'] ?? '');
        if ($dari !== (string) config('asisten_telegram.chat_id')) {
            \App\Services\AuditLogger::log('telegram_asing', 'telegram_bot', null, null, null, "Pesan ditolak dari chat_id tidak dikenal (id: " . (int) $dari . ")");
            return;
        }
        $text = trim((string) ($msg['text'] ?? ''));
        if ($text === '') {
            return;
        }
        $perintah = strtolower(strtok($text, ' '));
        $reply = match ($perintah) {
            '/help', '/bantuan', '/start', '/menu' => $this->bantuan(),
            '/status', '/antrian', '/antrean' => $this->status(),
            '/kasir' => $this->kasir(),
            '/stok' => $this->stok(),
            '/rekap' => $this->rekap(),
            '/health' => $this->health(),
            '/verify' => $this->verifikasi(),
            '/backup' => $this->backup(),
            default => 'Perintah tidak dikenal. Ketik /help untuk daftar yang tersedia.',
        };
        $this->kirim($reply);
        \App\Services\AuditLogger::log('telegram_perintah', 'telegram_bot', null, null, null, $perintah . ' -> dijawab');
    }

    private function bantuan(): string
    {
        return "Remote-ops Orion Grafika (pemilik only)\n"
            . "/status (/antrian) - antrean cetak & nota menunggu (angka saja)\n"
            . "/kasir - sesi kas yang terbuka (jumlah saja)\n"
            . "/stok - bahan di bawah minimum (nama & sisa stok)\n"
            . "/rekap - order hari ini (angka saja)\n"
            . "/health - status hidup: DB, storage, disk, Ollama\n"
            . "/verify - hasil verifikasi backup terakhir\n"
            . "/backup - jalankan backup manual sekarang\n"
            . "Tidur? Kirim /help lagi.";
    }

    /** Sesi kas terbuka - jumlah & nama kasir, TANPA saldo/uang. */
    private function kasir(): string
    {
        $sesi = DB::table('kas_harian as k')
            ->leftJoin('users as u', 'u.id_user', '=', 'k.id_user')
            ->leftJoin('karyawan as kw', 'kw.id_karyawan', '=', 'u.id_karyawan')
            ->where('k.status_lock', 'open')
            ->orderByDesc('k.id_kas')
            ->get(['k.id_user', 'kw.nama_karyawan', 'u.username']);

        if ($sesi->isEmpty()) {
            return "Sesi kas: TIDAK ADA yang terbuka.\nKasir belum buka sesi - nota tidak bisa masuk sampai sesi dibuka.";
        }

        $lines = ['Sesi kas TERBUKA: ' . $sesi->count() . ' sesi'];
        foreach ($sesi as $s) {
            $lines[] = '- ' . ($s->nama_karyawan ?: $s->username ?: 'User #' . $s->id_user);
        }
        $lines[] = '(saldo & nilai uang tidak dikirim di sini)';

        return implode("\n", $lines);
    }

    /** Bahan di bawah stok minimum - nama + sisa stok (operasional, bukan uang). */
    private function stok(): string
    {
        $rows = DB::table('bahan_baku')
            ->whereRaw('stok <= stok_minimal')
            ->orderBy('nama_bahan')
            ->limit(15)
            ->get(['nama_bahan', 'stok', 'stok_minimal', 'satuan_stok']);

        $total = (int) DB::table('bahan_baku')->whereRaw('stok <= stok_minimal')->count();

        if ($total === 0) {
            return "Stok bahan: SEMUA AMAN - tidak ada yang di bawah minimum.";
        }

        $lines = ["Bahan di bawah minimum: {$total} item"];
        foreach ($rows as $r) {
            $sisa = rtrim(rtrim(number_format((float) $r->stok, 2, ',', '.'), '0'), ',');
            $min = rtrim(rtrim(number_format((float) $r->stok_minimal, 2, ',', '.'), '0'), ',');
            $lines[] = '- ' . $r->nama_bahan . ': sisa ' . $sisa . ' ' . $r->satuan_stok . ' (min ' . $min . ')';
        }
        if ($total > $rows->count()) {
            $lines[] = '- dan ' . ($total - $rows->count()) . ' item lainnya (detail di modul Bahan Baku)';
        }
        $lines[] = '(nilai rupiah tidak dikirim di sini)';

        return implode("\n", $lines);
    }

    /** Rekap order hari ini - COUNT saja, tanpa rupiah. */
    private function rekap(): string
    {
        $hariIni = today();

        $nota = (int) DB::table('transaksi')
            ->whereDate('created_at', $hariIni)
            ->where('status_nota', '!=', 'Dibatalkan')
            ->count();
        $menungguKasir = (int) DB::table('transaksi')
            ->whereDate('created_at', $hariIni)
            ->where('status_nota', '!=', 'Dibatalkan')
            ->where('status_order', 'Menunggu Kasir')
            ->count();
        $belumLunas = (int) DB::table('transaksi')
            ->whereDate('created_at', $hariIni)
            ->where('status_nota', '!=', 'Dibatalkan')
            ->where('status_pembayaran', '!=', 'Lunas')
            ->count();
        $selesai = (int) DB::table('transaksi')
            ->whereDate('created_at', $hariIni)
            ->where('status_nota', '!=', 'Dibatalkan')
            ->where('status_pembayaran', 'Lunas')
            ->count();

        return "Rekap order " . $hariIni->translatedFormat('d M Y') . " (angka saja):\n"
            . "- Nota baru: {$nota}\n"
            . "- Sudah lunas: {$selesai}\n"
            . "- Belum lunas: {$belumLunas}\n"
            . "- Menunggu kasir: {$menungguKasir}\n"
            . "(omzet & nilai uang tidak dikirim di sini)";
    }

    /** Verifikasi backup terakhir per tipe - LULUS/GAGAL saja. */
    private function verifikasi(): string
    {
        try {
            $hasil = \App\Services\BackupService::verifikasiTerbaru('semua');
            if (empty($hasil)) {
                return 'Verifikasi backup: belum ada backup untuk diperiksa.';
            }
            $lines = ['Verifikasi backup terakhir:'];
            $lulus = 0;
            foreach ($hasil as $h) {
                $ok = (bool) ($h['ok'] ?? false);
                if ($ok) {
                    $lulus++;
                }
                $lines[] = '- ' . $h['tipe'] . ': ' . ($ok ? 'LULUS' : 'GAGAL') . ' (' . ($h['pesan'] ?? '') . ')';
            }
            $lines[] = "Skor: {$lulus}/" . count($hasil) . " LULUS";

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'Verifikasi backup GAGAL dijalankan: ' . $e->getMessage();
        }
    }

    private function status(): string
    {
        $antre = DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->where('d.status_spk', 'Antre')
            ->where('t.status_nota', '!=', 'Dibatalkan')
            ->count();
        $dikerjakan = DB::table('transaksi_detail as d')
            ->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->whereIn('d.status_spk', ['Desain', 'Cetak'])
            ->where('t.status_nota', '!=', 'Dibatalkan')
            ->count();
        $belumLunas = DB::table('transaksi')
            ->where('status_nota', '!=', 'Dibatalkan')
            ->where('status_pembayaran', '!=', 'Lunas')
            ->count();
        $belumDiproses = DB::table('transaksi')
            ->where('status_nota', '!=', 'Dibatalkan')
            ->where('status_order', 'Menunggu Kasir')
            ->count();
        return "Antrean cetak:\n- Antre: {$antre} item\n- Dikerjakan: {$dikerjakan} item\n- Menunggu kasir: {$belumDiproses} nota\n- Belum lunas: {$belumLunas} nota\n\n(detail transaksi & nilai uang tidak dikirim di sini)";
    }

    private function health(): string
    {
        $db = 'mati';
        try {
            DB::select('select 1');
            $db = 'ok';
        } catch (\Throwable $e) {
            $db = 'GAGAL';
        }
        $storage = is_writable(storage_path()) ? 'ok' : 'GAGAL';
        $freeBytes = @disk_free_space(storage_path()) ?: 0;
        $diskGB = number_format($freeBytes / 1073741824, 1);
        $ollama = \App\Services\OllamaService::health();
        $ollamaLine = $ollama['hidup']
            ? 'ok v' . $ollama['versi'] . ' (' . $ollama['latensi_ms'] . ' ms)'
                . ($ollama['model_terload'] !== '' ? ' - model di RAM: ' . $ollama['model_terload'] : ' - model BELUM di RAM (jalankan: php artisan ollama:warm)')
            : 'mati (asisten narasi memakai ringkasan tanpa AI)';
        $waktu = now(config('app.timezone', 'Asia/Jakarta'))->translatedFormat('d M Y H:i');
        return "Health {$waktu}\n- Database: {$db}\n- Storage tulis: {$storage}\n- Disk bebas: {$diskGB} GB\n- Ollama AI lokal: {$ollamaLine}\n\n(Batas aman: tidak ada data transaksi yang dibagikan)";
    }

    private function backup(): string
    {
        try {
            $log = \App\Services\BackupService::run('manual');
            $kb = number_format($log->ukuran_bytes / 1024, 1);
            return "Backup manual selesai.\nFile tersimpan di server ({$kb} KB). Detail file lihat modul Cadangan & Pemulihan.";
        } catch (\Throwable $e) {
            return "Backup GAGAL: " . $e->getMessage();
        }
    }
}
