<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Header keamanan dipasang global, bukan per route. Alasannya
        // ada di docblock HeaderKeamanan: header ini harus ikut pada
        // respons 403/404/422 dan redirect juga, bukan cuma halaman sukses.
        $middleware->append(\App\Http\Middleware\HeaderKeamanan::class);

        // Satu submit_id = satu transaksi untuk SEMUA permintaan tulis (klik ganda, coba ulang, dua tab).
        // Tidak melakukan apa pun bila permintaan tidak membawa submit_id. Lihat docblock SubmitSekaliJalan.
        $middleware->appendToGroup('web', \App\Http\Middleware\SubmitSekaliJalan::class);
        // Akun nonaktif (resign dsb.) langsung dikeluarkan di request berikutnya -
        // bukan menunggu sesi lama kedaluwarsa sendiri.
        $middleware->appendToGroup('web', \App\Http\Middleware\PastikanUserAktif::class);

        $middleware->alias([
            'menu.access' => \App\Http\Middleware\CheckMenuAccess::class,
            'restrict.input-only' => \App\Http\Middleware\RestrictInputOnlyRoles::class,
            'throttle.write' => \App\Http\Middleware\ThrottleWriteRequests::class,
            'hanya.admin' => \App\Http\Middleware\HanyaAdministrator::class,
            'role.only' => \App\Http\Middleware\HanyaRole::class,
            'sekali.jalan' => \App\Http\Middleware\SubmitSekaliJalan::class,
            'api.auth' => \App\Http\Middleware\ApiAuth::class,
            'api.rbac' => \App\Http\Middleware\ApiRbac::class,
        ]);

        // API eksternal masih tahap persiapan. Gate fail-closed hanya untuk
        // routes/api.php; semua route ERP web tetap utuh.
        // Aktifkan hanya setelah RBAC, limiter dan checkout API diperbaiki.
        $middleware->group('api', [
            \App\Http\Middleware\ApiPreparationGate::class,
        ]);

        // Otorisasi menu WAJIB berjalan sebelum route-model binding. Bawaan
        // Laravel menaruh SubstituteBindings lebih dulu, sehingga pengguna
        // tanpa izin mendapat 404 untuk id yang tidak ada dan 403 untuk id
        // yang ada (keberadaan data bisa ditebak), dan query pencarian model
        // berjalan sebelum izin diperiksa.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\CheckMenuAccess::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Database: terjemahkan kelas error umum menjadi respons yang ramah,
        // bukan halaman 500 mentah. Rute tidak diketahui / bug tetap masuk
        // ke halaman error bermerek (errors/500) oleh handler default.
        $exceptions->render(function (\Illuminate\Database\QueryException $e, \Illuminate\Http\Request $request) {
            $msg = (string) $e->getMessage();

            // Koneksi database terputus / hilang / habis waktu
            $connLost = preg_match('/\b(2002|2006|2013)\b/', $e->getCode())
                || str_contains($msg, 'server has gone away')
                || str_contains($msg, 'Connection refused')
                || str_contains($msg, 'Lost connection')
                || str_contains($msg, 'No connection could be made');
            if ($connLost) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Koneksi database terputus. Coba beberapa saat lagi.'], 503);
                }

                return response()->view('errors.503', [], 503);
            }

            // Deadlock / bentrok proses bersamaan (kasir & produksi terjadi bersamaan)
            if (stripos($msg, 'Deadlock') !== false || str_contains($e->getCode(), '1213') || str_contains($e->getCode(), '1205') || $e->getCode() === '40001') {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Terjadi bentrokan proses bersamaan. Coba lagi dalam beberapa detik.'], 503);
                }

                return response()->view('errors.503', [], 503);
            }

            // Duplikat / pelanggaran integritas pada penulisan -> balikkan ke form dengan pesan
            $isTulis = !$request->isMethodSafe();
            $integrity = str_contains($msg, 'Integrity constraint violation') || str_contains($msg, 'Duplicate entry') || $e->getCode() === '23000';
            if ($isTulis && $integrity) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Data duplikat atau masih terkait dengan data lain.'], 409);
                }

                return back()->withInput()->with('error', 'Data duplikat atau masih terkait dengan data lain. Periksa isian Anda.');
            }

            // Sisanya: handler default (halaman error bermerek 500)
            return null;
        });

        // Periode akuntansi ditutup: tampilkan pesan jelas, bukan halaman 500.
        // Transaksi database yang membungkus sudah di-rollback oleh Laravel
        // sebelum exception sampai di sini.
        $exceptions->render(function (\App\Exceptions\PeriodeAkuntansiTertutupException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });
    $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
        // Sesi berakhir / token CSRF kadaluwarsa (419): jangan tampilkan halaman
        // bawaan "Page Expired" yang tidak jelas bagi pengguna. CATATAN Laravel
        // 12: prepareException() mengubah TokenMismatchException menjadi
        // HttpException(419) SEBELUM render callbacks berjalan, sehingga
        // type-hint TokenMismatchException tidak akan pernah cocok di sini.
        $sesiBerakhir = $e instanceof \Illuminate\Session\TokenMismatchException
            || ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface && $e->getStatusCode() === 419);
        if (! $sesiBerakhir) {
            return null;
        }

        // Fetch dengan header X-Requested-With=XMLHttpRequest (upload foto
        // profil, dsb.) dien oleh expectsJson(); permintaan tulis biasa
        // (form Keluar Sistem, dsb.) diarahkan ke halaman masuk dgn pesan jelas.
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Sesi Anda telah berakhir. Muat ulang halaman (F5), masuk kembali, lalu coba lagi.',
                'sesi_berakhir' => true,
            ], 419);
        }

        return redirect()->route('login')->withErrors([
            'sesi' => 'Sesi Anda berakhir karena lama tidak aktif. Silakan masuk kembali.',
        ]);
    });
})->create();
