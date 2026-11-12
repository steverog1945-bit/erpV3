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

    /** Reply keyboard: menu tombol menyertai balasan - owner tap, tak perlu hafal. */
    public function test_balasan_menyertakan_menu_tombol(): void
    {
        app(TelegramAsistenService::class)->prosesUpdate($this->pesanDari(999888777, '/help'));
        Http::assertSent(function ($req) {
            $markup = $req->data()['reply_markup'] ?? '';
            if (!is_string($markup)) {
                return false;
            }
            $menu = json_decode($markup, true);

            return ($menu['keyboard'][0][0] ?? '') === '/status'
                && in_array('/stok', $menu['keyboard'][1] ?? [], true)
                && ($menu['resize_keyboard'] ?? false) === true;
        });
    }

    public function test_antrian_adalah_alias_status(): void
    {
        app(TelegramAsistenService::class)->prosesUpdate($this->pesanDari(999888777, '/antrian'));
        Http::assertSent(fn ($req) => str_contains($req->data()['text'] ?? '', 'Antrean cetak'));
    }

    public function test_kasir_tanpa_sesi_melapor_kosong(): void
    {
        app(TelegramAsistenService::class)->prosesUpdate($this->pesanDari(999888777, '/kasir'));
        Http::assertSent(fn ($req) => str_contains($req->data()['text'] ?? '', 'TIDAK ADA yang terbuka'));
    }

    public function test_kasir_sesi_terbuka_dihitung_tanpa_nilai_uang(): void
    {
        $user = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Kasir'])->id_role]);
        DB::table('kas_harian')->insert([
            'tgl_kas' => today(), 'id_user' => $user->id_user,
            'saldo_awal' => 500000, 'status_lock' => 'open',
        ]);

        app(TelegramAsistenService::class)->prosesUpdate($this->pesanDari(999888777, '/kasir'));
        Http::assertSent(function ($req) {
            $text = $req->data()['text'] ?? '';
            return str_contains($text, 'Sesi kas TERBUKA: 1 sesi')
                && !str_contains($text, 'Rp')
                && !str_contains($text, '500.000') // saldo awal tidak boleh bocor
                && !str_contains($text, '500000');
        });
    }

    public function test_stok_menyebut_bahan_di_bawah_minimum_tanpa_harga(): void
    {
        // Migrasi testing men-seed beberapa bahan - reset stoknya jauh di atas
        // minimum supaya hitungan "di bawah minimum" deterministik (delete
        // diblokir FK dari bom_detail), lalu isi satu bahan yang di bawah minimum.
        DB::table('bahan_baku')->update(['stok' => 999]);
        $mst = DB::table('mesin')->insertGetId(['nama_mesin' => 'M1', 'tipe_mesin' => 'Cetak']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K', 'kode_kategori' => 'K']);
        DB::table('bahan_baku')->insert([
            'kode_bahan' => 'B01', 'nama_bahan' => 'Frontlite 280gr', 'id_mesin' => $mst,
            'id_kategori' => $kat, 'stok' => 1.5, 'stok_minimal' => 3,
            'satuan_stok' => 'm', 'status' => 'Aktif',
        ]);

        app(TelegramAsistenService::class)->prosesUpdate($this->pesanDari(999888777, '/stok'));
        Http::assertSent(function ($req) {
            $text = $req->data()['text'] ?? '';
            return str_contains($text, 'di bawah minimum: 1 item')
                && str_contains($text, 'Frontlite 280gr')
                && str_contains($text, 'sisa 1,5 m (min 3)')
                && !str_contains($text, 'Rp');
        });
    }

    public function test_stok_aman_melapor_tanpa_item(): void
    {
        DB::table('bahan_baku')->update(['stok' => 999]); // seed migrasi diangkat di atas minimum
        app(TelegramAsistenService::class)->prosesUpdate($this->pesanDari(999888777, '/stok'));
        Http::assertSent(fn ($req) => str_contains($req->data()['text'] ?? '', 'SEMUA AMAN'));
    }

    public function test_rekap_hari_ini_hitung_nota_tanpa_rupiah(): void
    {
        $mst = DB::table('mesin')->insertGetId(['nama_mesin' => 'M1', 'tipe_mesin' => 'Cetak']);
        $kat = DB::table('kategori')->insertGetId(['nama_kategori' => 'K', 'kode_kategori' => 'K']);
        $gol = DB::table('golongan_konsumen')->insertGetId(['nama_golongan' => 'Retail', 'keterangan' => '-']);
        $kons = DB::table('konsumen')->insertGetId(['nama_konsumen' => 'X', 'telepon' => '1', 'alamat' => '-', 'id_golongan' => $gol]);
        $user = User::factory()->create(['id_role' => Role::factory()->create(['nama_role' => 'Administrator'])->id_role]);
        DB::table('transaksi')->insertGetId([
            'nomor_nota' => 'N1', 'tipe_order' => 'Reguler', 'id_konsumen' => $kons, 'id_user_kasir' => $user->id_user,
            'tgl_order' => now(), 'created_at' => now(), 'updated_at' => now(),
            'total_bayar' => 777000, 'status_pembayaran' => 'Lunas',
            'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir',
        ]);

        app(TelegramAsistenService::class)->prosesUpdate($this->pesanDari(999888777, '/rekap'));
        Http::assertSent(function ($req) {
            $text = $req->data()['text'] ?? '';
            return str_contains($text, 'Nota baru: 1')
                && str_contains($text, 'Sudah lunas: 1')
                && !str_contains($text, 'Rp')
                && !str_contains($text, '777');
        });
    }

    public function test_verify_melaporkan_status_verifikasi(): void
    {
        app(TelegramAsistenService::class)->prosesUpdate($this->pesanDari(999888777, '/verify'));
        Http::assertSent(function ($req) {
            $text = $req->data()['text'] ?? '';
            // Belum ada backup di DB test -> pesan kosong; bukan crash, bukan rupiah.
            return (str_contains($text, 'Verifikasi backup') || str_contains($text, 'belum ada backup'))
                && !str_contains($text, 'Rp');
        });
    }

    public function test_help_menyebut_semua_perintah_baru(): void
    {
        app(TelegramAsistenService::class)->prosesUpdate($this->pesanDari(999888777, '/help'));
        Http::assertSent(function ($req) {
            $text = $req->data()['text'] ?? '';
            return str_contains($text, '/kasir')
                && str_contains($text, '/stok')
                && str_contains($text, '/rekap')
                && str_contains($text, '/verify');
        });
    }
}
