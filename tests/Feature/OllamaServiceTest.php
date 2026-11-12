<?php

namespace Tests\Feature;

use App\Services\OllamaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Klien Ollama bersama: keep_alive terkirim di setiap generate, warm
 * memuat model, health mengukur hidup/versi/latensi. Server dipalsukan -
 * tidak ada panggilan nyata dari test.
 */
class OllamaServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'asisten_ai.enabled' => true,
            'asisten_ai.ollama_url' => 'http://127.0.0.1:11434',
            'asisten_ai.model' => 'qwen2.5:3b-instruct',
            'asisten_ai.keep_alive' => '30m',
        ]);
    }

    public function test_generate_mengirim_model_prompt_dan_keep_alive(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/generate' => Http::response(['response' => '  Ringkasan narasi.  '], 200),
        ]);

        $out = OllamaService::generate('Ubah angka ini jadi narasi.');

        $this->assertSame('Ringkasan narasi.', $out); // trim, tanpa spasi sisa
        Http::assertSent(function ($req) {
            return $req->url() === 'http://127.0.0.1:11434/api/generate'
                && $req['model'] === 'qwen2.5:3b-instruct'
                && $req['prompt'] === 'Ubah angka ini jadi narasi.'
                && $req['stream'] === false
                && $req['keep_alive'] === '30m'; // inti optimasi: model tetap di RAM
        });
    }

    public function test_generate_gagal_atau_kosong_mengembalikan_null(): void
    {
        // Server mati (connection error) -> null, TIDAK throw.
        Http::fake(function () {
            throw new \Exception('connection refused');
        });
        $this->assertNull(OllamaService::generate('apa pun'));

        // Server hidup tapi jawaban kosong -> null juga.
        Http::fake(['127.0.0.1:11434/*' => Http::response(['response' => '   '], 200)]);
        $this->assertNull(OllamaService::generate('apa pun'));
    }

    public function test_warm_mengirim_permintaan_kecil_dengan_keep_alive(): void
    {
        Http::fake(['127.0.0.1:11434/api/generate' => Http::response(['response' => 'siap.'], 200)]);

        $this->assertTrue(OllamaService::warm());
        Http::assertSent(function ($req) {
            return $req['model'] === 'qwen2.5:3b-instruct'
                && $req['keep_alive'] === '30m'
                && ($req['options']['num_predict'] ?? 0) <= 10; // panggilan kecil, bukan generasi penuh
        });
    }

    public function test_warm_gagal_mengembalikan_false_tanpa_throw(): void
    {
        Http::fake(function () {
            throw new \Exception('down');
        });
        $this->assertFalse(OllamaService::warm());
    }

    public function test_health_melaporkan_hidup_versi_model_dan_latensi(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/version' => Http::response(['version' => '0.40.16'], 200),
            '127.0.0.1:11434/api/ps' => Http::response([
                'models' => [['name' => 'qwen2.5:3b-instruct', 'size' => 2000000000]],
            ], 200),
        ]);

        $h = OllamaService::health();

        $this->assertTrue($h['hidup']);
        $this->assertSame('0.40.16', $h['versi']);
        $this->assertSame('qwen2.5:3b-instruct', $h['model_terload']);
        $this->assertGreaterThanOrEqual(0, $h['latensi_ms']);
    }

    public function test_health_server_mati_tetap_terstruktur_tanpa_throw(): void
    {
        Http::fake(function () {
            throw new \Exception('connection refused');
        });

        $h = OllamaService::health();

        $this->assertFalse($h['hidup']);
        $this->assertSame('', $h['versi']);
        $this->assertSame('', $h['model_terload']);
    }

    public function test_health_model_tidak_berada_di_ram_laporkan_kosong(): void
    {
        // Server hidup, tapi tidak ada model resident (kasus cold start).
        Http::fake([
            '127.0.0.1:11434/api/version' => Http::response(['version' => '0.40.16'], 200),
            '127.0.0.1:11434/api/ps' => Http::response(['models' => []], 200),
        ]);

        $h = OllamaService::health();

        $this->assertTrue($h['hidup']);
        $this->assertSame('', $h['model_terload']);
    }

    /** /health Telegram menampilkan status Ollama dari service bersama. */
    public function test_telegram_health_memakai_status_ollama_service(): void
    {
        config([
            'asisten_telegram.enabled' => true,
            'asisten_telegram.token' => 'TOKEN123',
            'asisten_telegram.chat_id' => '999888777',
        ]);
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
            '127.0.0.1:11434/api/version' => Http::response(['version' => '0.40.16'], 200),
            '127.0.0.1:11434/api/ps' => Http::response(['models' => [['name' => 'qwen2.5:3b-instruct']]], 200),
        ]);

        $svc = new \App\Services\TelegramAsistenService();
        $ref = new \ReflectionMethod($svc, 'health');
        $ref->setAccessible(true);
        $teks = $ref->invoke($svc);

        $this->assertStringContainsString('Ollama AI lokal: ok v0.40.16', $teks);
        $this->assertStringContainsString('model di RAM: qwen2.5:3b-instruct', $teks);
    }

    public function test_telegram_health_ollama_mati_tetap_berfungsi(): void
    {
        config([
            'asisten_telegram.enabled' => true,
            'asisten_telegram.token' => 'TOKEN123',
            'asisten_telegram.chat_id' => '999888777',
        ]);
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
            '127.0.0.1:11434/*' => function () {
                throw new \Exception('connection refused');
            },
        ]);

        $svc = new \App\Services\TelegramAsistenService();
        $ref = new \ReflectionMethod($svc, 'health');
        $ref->setAccessible(true);
        $teks = $ref->invoke($svc);

        $this->assertStringContainsString('Ollama AI lokal: mati', $teks);
        $this->assertStringContainsString('ringkasan tanpa AI', $teks);
    }
}
