<?php

namespace App\Console\Commands;

use App\Services\TelegramAsistenService;
use Illuminate\Console\Command;

/**
 * Jalankan remote-ops Telegram di terminal ini:
 *   php artisan telegram:bot
 * Long polling - koneksi keluar saja, tanpa port publik terbuka.
 * Berhenti dengan Ctrl+C kapan pun.
 */
class TelegramBotCommand extends Command
{
    protected $signature = 'telegram:bot
                            {--once : proses satu putaran lalu berhenti (untuk coba)}
                            {--interval=2 : jeda antar poll (detik)}';

    protected $description = 'Jalankan remote-ops Telegram (owner-only, perintah whitelist)';

    public function handle(): int
    {
        /** @var TelegramAsistenService $svc */
        $svc = app(TelegramAsistenService::class);
        if (!$svc->aktif()) {
            $this->error('Asisten Telegram NONAKTIF. Setel di .env: ASISTEN_TELEGRAM_ENABLED=true, ASISTEN_TELEGRAM_TOKEN=..., ASISTEN_TELEGRAM_CHAT_ID=...');
            return self::FAILURE;
        }
        $this->info('Asisten Telegram hidup (owner-only). Ctrl+C untuk berhenti.');
        $offsetFile = storage_path('framework/telegram-offset.txt');
        $offset = (int) @file_get_contents($offsetFile);
        $sekali = (bool) $this->option('once');
        $interval = max(1, (int) $this->option('interval'));
        do {
            $res = $svc->poll($offset);
            if (!$res['ok']) {
                $this->warn('Poll gagal: ' . ($res['error'] ?? 'tidak diketahui'));
            }
            foreach ($res['updates'] as $u) {
                $svc->prosesUpdate($u);
                $offset = max($offset, ((int) ($u['update_id'] ?? 0)) + 1);
                @file_put_contents($offsetFile, (string) $offset);
            }
            if (!$sekali) {
                sleep($interval);
            }
        } while (!$sekali);
        return self::SUCCESS;
    }
}
