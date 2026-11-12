<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Fungsi format angka global (angka, rupiah, angka_polos, angka_input) - pengaman bila autoload composer belum diperbarui.
        require_once app_path('helpers.php');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // CATATAN instalasi subdirektori (XAMPP -> http://localhost/grafikaorion/public)
        //
        // SENGAJA TIDAK memakai URL::forceRootUrl() di sini.
        //
        // forceRootUrl() memaksa SEMUA URL - termasuk yang dipakai untuk menyusun
        // request di dalam test client Laravel (MakesHttpRequests::prepareUrlForRequest
        // memanggil url($uri)). Akibatnya $this->get('/') dijalankan sebagai
        // "http://localhost/grafikaorion/public/" sehingga path info-nya jadi
        // "/grafikaorion/public/" - tidak ada route yang cocok, dan SEMUA test
        // yang memanggil route apa pun akan 404.
        //
        // Installer subdirektori sebenarnya sudah ditangani Laravel dengan sendirinya:
        // UrlGenerator::root() memakai request()->root(), dan request itu dibangun
        // dari SCRIPT_NAME/SERVER_NAME Apache, yang pada XAMPP sudah bernilai
        // "/grafikaorion/public/index.php". Jadi route(), url(), dan asset()
        // otomatis benar tanpa perlu dipaksa.

        Paginator::useTailwind();

        // Hardening P0-4: batasi percobaan login (per kombinasi username+IP,
        // BUKAN per IP saja - supaya satu IP kantor/warnet dengan banyak user
        // tidak saling mengunci) - sebelumnya endpoint /login sama sekali
        // tidak dibatasi, terbuka untuk brute-force password tanpa batas
        // begitu aplikasi online.
        RateLimiter::for('login', function (Request $request) {
            $kunci = \Illuminate\Support\Str::lower((string) $request->input('username')) . '|' . $request->ip();

            return Limit::perMinute(5)->by($kunci)->response(function (Request $request, array $headers) {
                $headers['Retry-After'] = 300;
                // Browser: pengguna melihat pesan di halaman login seperti
                // semua penolakan form lain di aplikasi ini. Klient API/AJAX:
                // tetap JSON 429 supaya bisa dirapikan secara terprogram.
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'Terlalu banyak percobaan login. Coba lagi dalam beberapa saat.',
                        'retry_after' => 300,
                    ], 429, $headers);
                }

                return redirect()->route('login')
                    ->withErrors(['username' => 'Terlalu banyak percobaan login. Coba lagi dalam beberapa saat.']);
            });
        });

        // Batasi endpoint yang menulis data.
        //
        // Sebelumnya HANYA /login yang dibatasi. Semua operasi tulis
        // lain - 158 route POST/PUT/PATCH/DELETE termasuk simpan nota,
        // approve, dan Ajax polling kasir - bisa dipanggil tanpa batas.
        // Untuk sistem keuangan dampaknya bukan hanya penyalahgunaan:
        // satu script yang salah mengulang POST bisa menulis ratusan
        // baris jurnal sebelum ada yang menyadari.
        //
        // Batasnya longgar dengan sengaja. Ini aplikasi internal dengan
        // user yang sah; kalau modul ini terlalu sempit, pekerjaan sah
        // justru ikut terblokir. Yang dicegah adalah pengulangan tak
        // terbatas, bukan pemakaian wajar.
        //
        // Kunci memakai user, bukan IP, supaya beberapa orang yang
        // berbagi satu IP kantor tidak saling mengunci.
        RateLimiter::for('tulis', function (Request $request) {
            $id = $request->user()?->getAuthIdentifier();
            $kunci = $id ? 'u' . $id : 'ip' . $request->ip();

            return Limit::perMinute(180)->by($kunci)->response(
                fn (Request $r, array $headers) => $r->expectsJson()
                    ? response()->json(['message' => 'Terlalu banyak permintaan. Coba lagi sebentar.'], 429)
                    : back()->withInput($r->except('_token'))->with('error', 'Terlalu banyak permintaan. Coba lagi sebentar.')
            );
        });

        // Endpoint AJAX yang dipanggil halaman kasir terus-menerus
        // (sinyal notifikasi, pengecekan transaksi baru). Halaman ini
        // terbuka berjam-jam di layar kasir, jadi JANGAN ikut limiter
        // 'tulis' di atas: 180 permintaan per menit akan mengunci kasir
        // yang sedang menghitung. Batasnya longgar tapi tetap ada,
        // supaya satu tab yang rusak tidak bisa membanjiri server.
        RateLimiter::for('polling', function (Request $request) {
            $id = $request->user()?->getAuthIdentifier();
            return Limit::perMinute(120)->by('poll' . ($id ?: $request->ip()));
        });

        // API publik: penyerahan token (/api/auth/token) itu "login kedua" -
        // brute-force password lewat jalur API harus digembok seperti /login
        // web. Kuncinya username+IP (bukan IP saja) supaya satu IP kantor
        // dengan banyak user tidak saling mengunci.
        RateLimiter::for('api-token', function (Request $request) {
            $kunci = \Illuminate\Support\Str::lower((string) $request->input('username')) . '|' . $request->ip();

            return Limit::perMinute(5)->by($kunci)->response(
                fn (Request $r, array $headers) => response()->json(
                    ['message' => 'Terlalu banyak percobaan token. Coba lagi dalam beberapa saat.'],
                    429
                )
            );
        });

        // Seluruh endpoint API ber-token: 60 permintaan/menit per klien.
        // Pemanggilannya SEBELUM api.auth (lihat routes/api.php) - pada lapis
        // ini user belum ada, jadi kuncinya IP: menyapu juga banjir permintaan
        // dengan token salah/berbahaya yang belum tersentuh limiter token.
        // Batas longgar untuk satu IP integrasi sah; bila satu IP kantor
        // memakai beberapa aplikasi sekaligus dan terasa sempit, naikkan di
        // sini - bukan membuang limiter.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip())->response(
                fn (Request $r, array $headers) => response()->json(
                    ['message' => 'Terlalu banyak permintaan API. Coba lagi sebentar.'],
                    429
                )
            );
        });

        // whereTanggalAntara('kolom', [dari, sampai]): rentang tanggal inklusif yang tetap memakai indeks.
        // Pengganti whereBetween(DB::raw("DATE(kolom)"), ...): DATE() di kolom membuat MySQL memindai seluruh
        // tabel (terukur 16 sampai 59 detik per query pada 700 ribu baris audit dan kartu stok).
        \Illuminate\Database\Query\Builder::macro('whereTanggalAntara', function (string $kolom, array $rentang) {
            [$dari, $sampai] = array_values($rentang);
            $dari = \Illuminate\Support\Carbon::parse($dari)->startOfDay();
            $besok = \Illuminate\Support\Carbon::parse($sampai)->startOfDay()->addDay();

            return $this->where($kolom, '>=', $dari->toDateTimeString())->where($kolom, '<', $besok->toDateTimeString());
        });

        // @bolehHarga ... @endbolehHarga : tampilkan hanya untuk role yang
        // berhak melihat harga/nilai uang. Penentu tunggal: AksesHarga::bolehLihat().
        // Blade::if dievaluasi saat VIEW dirender — auth() pasti sudah siap,
        // jadi tidak perlu ada aturan terpisah soal "boot time".
        \Illuminate\Support\Facades\Blade::if('bolehHarga', fn () => \App\Support\AksesHarga::bolehLihat());

        // @bolehDesain ... @endbolehDesain : tampilkan hanya untuk role yang
        // berhak MENGINPUT nilai Biaya Desain (AksesHarga::bolehInputBiayaDesain()).
        \Illuminate\Support\Facades\Blade::if('bolehDesain', fn () => \App\Support\AksesHarga::bolehInputBiayaDesain());

        // @unlessbolehHarga ... @endunlessbolehHarga : kebalikan bolehHarga,
        // dipakai untuk isian readonly/sembunyi.
        \Illuminate\Support\Facades\Blade::if('unlessbolehHarga', fn () => ! \App\Support\AksesHarga::bolehLihat());

        // @unlessbolehDesain ... @endunlessbolehDesain : kebalikan bolehDesain.
        \Illuminate\Support\Facades\Blade::if('unlessbolehDesain', fn () => ! \App\Support\AksesHarga::bolehInputBiayaDesain());

        // @versi($row) : kolom tersembunyi versi baris untuk optimistic locking (lihat App\Support\VersiBaris).
        \Illuminate\Support\Facades\Blade::directive('versi', fn ($e) => "<?php echo \App\Support\VersiBaris::field({$e}); ?>");

        // Berbagi status kas ke semua View
    view()->composer('*', function ($view) {
        if (auth()->check()) {
            // Mengambil kas harian yang dibuka oleh user manapun hari ini yang masih 'open'
            // Composer '*' terpanggil untuk SETIAP view dan komponen Blade. Tanpa ingatan per-permintaan,
            // query kas ini berulang ratusan kali dalam satu halaman (terukur: 316 kali di Price List).
            // Disimpan di atribut request, jadi tetap segar di permintaan berikutnya.
            $req = request();
            if (!$req->attributes->has('kas_aktif_tampilan')) {
                $req->attributes->set('kas_aktif_tampilan', \App\Services\KasSesiService::aktif() ?? false);
            }
            $kas = $req->attributes->get('kas_aktif_tampilan') ?: null;
            $view->with('kasAktif', $kas);
            $view->with('kasBasi', \App\Services\KasSesiService::basi($kas));
        }
    });
    }
}
