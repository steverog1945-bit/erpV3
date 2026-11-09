<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController as ApiDashboardController;
use App\Http\Controllers\Api\KonsumenController as ApiKonsumenController;
use App\Http\Controllers\Api\LaporanController as ApiLaporanController;
use App\Http\Controllers\Api\PembayaranController as ApiPembayaranController;
use App\Http\Controllers\Api\ProdukController as ApiProdukController;
use App\Http\Controllers\Api\SpkController as ApiSpkController;
use App\Http\Controllers\Api\StokController as ApiStokController;
use App\Http\Controllers\Api\TransaksiController as ApiTransaksiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Publik Routes
|--------------------------------------------------------------------------
|
| Rute ini diakses oleh aplikasi eksternal (mobile app, e-commerce,
| integrasi pihak ketiga). Autentikasi HANYA lewat header
| `Authorization: Bearer <api_token>` - parameter query `?api_token=`
| DITOLAK 401 (token di URL bocor ke access log/Referer). Token di-
| hasilkan via POST /api/auth/token (hash SHA-256 + kedaluwarsa 24 jam).
|
| Semua respons dalam format JSON.
*/

Route::prefix('auth')->group(function () {
    Route::post('/token', [AuthController::class, 'generateToken'])->name('api.auth.token')
        ->middleware('throttle:api-token');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout')->middleware('api.auth');
});

Route::middleware(['throttle:api', 'api.auth', 'api.rbac'])->group(function () {
    // Master data (read-only untuk API publik)
    Route::get('/konsumen', [ApiKonsumenController::class, 'index'])->name('api.konsumen.index');
    Route::get('/konsumen/{id}', [ApiKonsumenController::class, 'show'])->name('api.konsumen.show');
    Route::get('/produk', [ApiProdukController::class, 'index'])->name('api.produk.index');
    Route::get('/produk/{id}', [ApiProdukController::class, 'show'])->name('api.produk.show');

    // Transaksi
    Route::get('/transaksi', [ApiTransaksiController::class, 'index'])->name('api.transaksi.index');
    Route::get('/transaksi/{id}', [ApiTransaksiController::class, 'show'])->name('api.transaksi.show');
    // API-3: checkout via PenjualanCheckoutService (jalur bisnis web yang sama)
    // + idempotensi X-Submit-Id tahan-crash. Gate dua lapis di adapter:
    // orion_api.enabled sekali lagi orion_api.checkout.enabled, keduanya default
    // OFF → 503 no-store (pengganti hard-disable ini, perilaku default tetap
    // tertutup sama persis).
    Route::post('/transaksi', [ApiTransaksiController::class, 'store'])->name('api.transaksi.store');

    // Dashboard KPI
    Route::get('/dashboard/kpi', [ApiDashboardController::class, 'kpi'])->name('api.dashboard.kpi');

    // Pembayaran (read-only)
    Route::get('/pembayaran', [ApiPembayaranController::class, 'index'])->name('api.pembayaran.index');
    Route::get('/transaksi/{id}/pembayaran', [ApiPembayaranController::class, 'untukTransaksi'])->name('api.transaksi.pembayaran');

    // SPK / Produksi (read-only)
    Route::get('/spk', [ApiSpkController::class, 'index'])->name('api.spk.index');

    // Stok bahan baku (read-only)
    Route::get('/stok', [ApiStokController::class, 'index'])->name('api.stok.index');
    Route::get('/stok/{id}/kartu', [ApiStokController::class, 'kartu'])->name('api.stok.kartu');

    // Laporan (read-only)
    Route::get('/laporan/penjualan', [ApiLaporanController::class, 'penjualan'])->name('api.laporan.penjualan');
    Route::get('/laporan/piutang', [ApiLaporanController::class, 'piutang'])->name('api.laporan.piutang');
    Route::get('/laporan/kas', [ApiLaporanController::class, 'kas'])->name('api.laporan.kas');
});
