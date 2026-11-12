<?php

namespace App\Console\Commands;

use App\Services\OllamaService;
use Illuminate\Console\Command;

/**
 * Muat model Ollama ke RAM SEKARANG (keep_alive sesuai config) supaya
 * panggilan pertama asisten tidak menunggu cold start 5-10 detik.
 *
 * Jalankan: php artisan ollama:warm
 * Cocok juga dipasang di scheduler/task scheduler pagi hari, atau dipanggil
 * manual sebelum presentasi/demo.
 */
class OllamaWarmCommand extends Command
{
    protected $signature = 'ollama:warm';

    protected $description = 'Muat model Ollama lokal ke RAM (keep_alive) agar respons asisten tidak cold start';

    public function handle(): int
    {
        $model = OllamaService::model();
        $this->line("Menghangatkan model {$model} di " . OllamaService::url('') . ' ...');

        $mulai = microtime(true);
        $ok = OllamaService::warm();
        $detik = number_format(microtime(true) - $mulai, 1);

        if (!$ok) {
            $this->error("GAGAL ({$detik} s) - Ollama tidak merespons. Pastikan 'ollama serve' jalan.");
            return self::FAILURE;
        }

        $h = OllamaService::health();
        $this->info("OK ({$detik} s). Model di RAM: " . ($h['model_terload'] ?: $model)
            . " - keep_alive: " . (string) config('asisten_ai.keep_alive', '30m'));

        return self::SUCCESS;
    }
}
