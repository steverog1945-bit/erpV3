<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\TelegramAsistenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Remote-ops Telegram: owner-only, perintah whitelist, tanpa data rupiah.
 */
class TelegramAsistenTest extends TestCase
{
    use RefreshDatabase;

    private function pesanDari(int $chatId, string $teks): array
    {
        return ['message' => ['message_id' => 1, 'from' => ['id' => $chatId, 'is_bot' => false, 'first_name' => 'Owner'], 'chat' => ['id' => $chatId, 'type' => 'private'], 'date' => time(), 'text' => $teks]];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'asisten_telegram.enabled' => true,
            'asisten_telegram.token' => 'TOKEN123',
            'asisten_telegram.chat_id' => '999888777',
        ]);
        // Hermetic: selain Telegram, panggilan Ollama (dipakai /health) juga
        // dipalsukan - test tidak pernah menyentuh server lokal nyata.
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200),
            '127.0.0.1:11434/*' => Http::response(['version' => '0.0.0-test', 'models' => []], 200),
        ]);
    }

    public function test_help_pemilik_dijawab(): void
    {
        app(TelegramAsistenService::class)->prosesUpdate($this->pesanDari(999888777, '/help'));
        Http::assertSent(fn ($req) => $req->url() === 'https://api.telegram.org/botTOKEN123/sendMessage'
            && str_contains($req->data()['text'] ?? '', '/status'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'telegram_perintah']);
    }

    public function test_chat_asing_diabaikan_dan_dicatat(): void
    {
        app(TelegramAsistenService::class)->prosesUpdate($this->pesanDari(123456, '/status'));
        Http::assertNotSent(fn ($req) => str_ends_with($req->url(), '/sendMessage'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'telegram_asing']);
    }

    public function test_status_tidak_menyebut_rupiah(): void
    {
        $mst = DB::table('mesin')->insertGetId(['nama_mesin' => 'M1', 'tipe_mesin' => 'Cetak']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K', 'kode_kategori' => 'K']);
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'X', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        $user = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        $idTrx = DB::table('transaksi')->insertGetId(['nomor_nota' => 'N1', 'tipe_order' => 'Reguler', 'id_konsumen' => $kons, 'id_user_kasir' => $user->id_user, 'tgl_order' => now(), 'total_bayar' => 123456, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir']);
        DB::table('transaksi_detail')->insert(['id_transaksi' => $idTrx, 'id_produk' => 1, 'nama_file' => 'F', 'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 1, 'subtotal' => 123456, 'status_spk' => 'Antre']);

        app(TelegramAsistenService::class)->prosesUpdate($this->pesanDari(999888777, '/status'));
        Http::assertSent(function ($req) {
            $text = $req->data()['text'] ?? '';
            return str_ends_with($req->url(), '/sendMessage')
                && str_contains($text, 'Antre: 1')
                && !str_contains($text, 'Rp')
                && !str_contains($text, '123456');
        });
    }

    public function test_perintah_tidak_dikenal(): void
    {
        app(TelegramAsistenService::class)->prosesUpdate($this->pesanDari(999888777, '/hapus-semua'));
        Http::assertSent(fn ($req) => str_contains($req->data()['text'] ?? '', 'tidak dikenal'));
    }

    public function test_health_berjalan(): void
    {
        app(TelegramAsistenService::class)->prosesUpdate($this->pesanDari(999888777, '/health'));
        Http::assertSent(fn ($req) => str_contains($req->data()['text'] ?? '', 'Database: ok'));
    }
}
