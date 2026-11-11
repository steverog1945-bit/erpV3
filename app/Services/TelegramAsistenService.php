<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Remote-ops via Telegram - dibatasi TOTAL:
 *   - Hanya chat_id owner di .env yang dilayani; chat asing diabaikan diam +
 *     dicatat di audit_logs.
 *   - Perintah: /help, /status, /health, /backup. Tidak ada perintah lain.
 *   - Output: angka COUNT dan status hidup/mati saja. Tidak ada rupiah,
 *     nota, nama pelanggan, harga, atau data pembelian apa pun.
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

    public function kirim(string $text): bool
    {
        $token = (string) config('asisten_telegram.token');
        $chatId = (string) config('asisten_telegram.chat_id');
        try {
            $r = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'disable_web_page_preview' => true,
            ]);
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
            '/help', '/bantuan', '/start' => $this->bantuan(),
            '/status' => $this->status(),
            '/health' => $this->health(),
            '/backup' => $this->backup(),
            default => 'Perintah tidak dikenal. Ketik /help untuk daftar yang tersedia.',
        };
        $this->kirim($reply);
        \App\Services\AuditLogger::log('telegram_perintah', 'telegram_bot', null, null, null, $perintah . ' -> dijawab');
    }

    private function bantuan(): string
    {
        return "Remote-ops Orion Grafika (pemilik only)\n"
            . "/status - antrean cetak & nota menunggu (angka saja)\n"
            . "/health - status hidup: DB, storage, disk, Ollama\n"
            . "/backup - jalankan backup manual sekarang\n"
            . "Tidur? Kirim /help lagi.";
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
        $ollama = 'mati';
        try {
            $r = Http::timeout(3)->get('http://127.0.0.1:11434/api/version');
            $ollama = $r->ok() ? 'ok' : 'mati';
        } catch (\Throwable $e) {
            $ollama = 'mati';
        }
        $waktu = now(config('app.timezone', 'Asia/Jakarta'))->translatedFormat('d M Y H:i');
        return "Health {$waktu}\n- Database: {$db}\n- Storage tulis: {$storage}\n- Disk bebas: {$diskGB} GB\n- Ollama AI lokal: {$ollama}\n\n(Batas aman: tidak ada data transaksi yang dibagikan)";
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
