<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Klien Ollama lokal yang dipakai BERSAMA oleh semua fitur AI (narator
 * asisten internal, nanti penerjemah Telegram). Satu tempat untuk:
 * URL, model, timeout, keep_alive, dan health check.
 *
 * keep_alive: sengaja mengirim param keep_alive di SETIAP panggilan supaya
 * model tetap tinggal di RAM setelah selesai (default 30 menit). Tanpa ini
 * Ollama membongkar model beberapa detik setelah dipakai, lalu panggilan
 * berikutnya menunggu cold start 5-10 detik lagi - itu penyebab asisten
 * terasa lambat tidak konsisten.
 *
 * Semua panggilan ke 127.0.0.1 - tidak ada data yang keluar ke internet.
 */
class OllamaService
{
    public static function url(string $path): string
    {
        return rtrim((string) config('asisten_ai.ollama_url', 'http://127.0.0.1:11434'), '/') . $path;
    }

    public static function model(): string
    {
        return (string) config('asisten_ai.model', 'qwen2.5:3b-instruct');
    }

    /**
     * Generate satu jawaban; gagal/tetap kosong -> null (pemanggil punya
     * fallback sendiri, tidak pernah gagal total karena AI).
     */
    public static function generate(string $prompt, array $options = []): ?string
    {
        try {
            $resp = Http::timeout((int) config('asisten_ai.timeout', 25))->post(self::url('/api/generate'), [
                'model' => self::model(),
                'prompt' => $prompt,
                'stream' => false,
                'keep_alive' => (string) config('asisten_ai.keep_alive', '30m'),
                'options' => $options ?: ['temperature' => 0.3],
            ]);
            $out = (string) ($resp->json('response') ?? '');
            return trim($out) !== '' ? trim($out) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Muat model ke RAM sekarang (panggilan kecil dengan num_predict 1).
     * Dipakai perintah ollama:warm supaya panggilan pertama pengguna tidak
     * menunggu cold start.
     */
    public static function warm(): bool
    {
        try {
            $resp = Http::timeout((int) config('asisten_ai.timeout', 25))->post(self::url('/api/generate'), [
                'model' => self::model(),
                'prompt' => 'Balas satu kata: siap.',
                'stream' => false,
                'keep_alive' => (string) config('asisten_ai.keep_alive', '30m'),
                'options' => ['num_predict' => 5, 'temperature' => 0],
            ]);
            return $resp->ok();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Health check: hidup + versi + model yang sedang tinggal di RAM +
     * latensi /api/version (ms). Tanpa data apa pun dari aplikasi.
     *
     * @return array{hidup: bool, versi: string, model_terload: string, latensi_ms: int}
     */
    public static function health(): array
    {
        $mulai = microtime(true);
        $hasil = ['hidup' => false, 'versi' => '', 'model_terload' => '', 'latensi_ms' => 0];
        try {
            $r = Http::timeout(3)->get(self::url('/api/version'));
            $hasil['latensi_ms'] = (int) round((microtime(true) - $mulai) * 1000);
            if (!$r->ok()) {
                return $hasil;
            }
            $hasil['hidup'] = true;
            $hasil['versi'] = (string) ($r->json('version') ?? '');

            try {
                $ps = Http::timeout(3)->get(self::url('/api/ps'));
                if ($ps->ok()) {
                    $models = (array) ($ps->json('models') ?? []);
                    if (!empty($models)) {
                        $hasil['model_terload'] = (string) ($models[0]['name'] ?? '');
                    }
                }
            } catch (\Throwable $e) {
                // ps gagal tidak membuat health gagal - server jelas hidup.
            }
        } catch (\Throwable $e) {
            $hasil['latensi_ms'] = (int) round((microtime(true) - $mulai) * 1000);
        }

        return $hasil;
    }
}
