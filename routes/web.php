<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\{
    AuthController,
    AksesController,
    KaryawanController,
    UserController,
    MesinController,
    BahanBakuController,
    KategoriController,
    KategoriKasKecilController,
    KonsumenController,
    GolonganController,
    SatuanController,
    SupplierController,
    BankController,
    ProdukController,
    SettingHargaController,
    PembelianBahanController,
    KedatanganBahanController,
    KartuStokController,
    FinishingController
};

use App\Http\Controllers\PenjualanController;
use App\Http\Controllers\Api\PenjualanApiController;
use App\Http\Controllers\Kasir\KasirController;
use App\Http\Controllers\SpkController;
use App\Http\Controllers\ProduksiController;
use App\Http\Controllers\CollectingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\HargaPromoController;
use App\Http\Controllers\SettingAplikasiController;
use App\Http\Controllers\BrandThemeController;



/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

// Homepage publik. Dulu route ini bernama 'login', tapi itu rapuh:
// route('login') berarti '/' , dan ketika Landing Page Builder aktif '/' bisa
// menyajikan halaman builder yang tidak memuat form login -> staf terkunci.
// Nama 'login' sekarang menunjuk /masuk (lihat baris berikutnya) yang selalu
// menyediakan form masuk. Homepage tetap di '/' seperti sebelumnya.
Route::get('/', [AuthController::class, 'showLogin'])->name('beranda');

Route::post('/client-log', [\App\Http\Controllers\ClientLogController::class, 'store'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('client-log.store');

// Endpoint login khusus staf. Ini yang jadi tujuan route('login'), jadi
// middleware auth, HanyaAdministrator, dan seluruh kode yang memanggil
// route('login') otomatis mendarat di halaman yang benar-benar punya form
// login - apa pun nilai feature flag.
//
// URL memakai /masuk (bukan /login) supaya tidak pernah bertabrakan dengan
// POST /login di baris bawah, dan tetap backward-compatible karena tidak ada
// kode yang meng-hardcode URL /login sebagai GET.
// Method: formLogin() sengaja MELOMPATI resolver builder, jadi halaman ini
// mustahil menyajikan konten builder.
Route::get('/masuk', [AuthController::class, 'formLogin'])->name('login');

// Tulisan yang ditandai publik bisa dibaca tanpa login.
Route::get('/baca/{slug}', [\App\Http\Controllers\PublikController::class, 'baca'])->where('slug', '[a-z0-9-]+')->name('publik.baca');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Berkas PWA (manifest + service worker) - publik supaya bisa dipasang bahkan
// sebelum masuk. Sengaja memakai ekstensi .webmanifest/.js (bukan .json) karena
// .htaccess root memblokir .json di luar folder public/.
Route::get('/manifest.webmanifest', [\App\Http\Controllers\PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js', [\App\Http\Controllers\PwaController::class, 'serviceWorker'])->name('pwa.sw');

/*
| Katalog Produk PUBLIK - DINONAKTIFKAN (keputusan operator final).
|
| Rute publik /katalog-produk beserta route detailnya DIHAPUS dari daftar
| route. Keputusan: "Katalog Produk Publik tidak digunakan lagi."
|
| Yang SENGAJA TIDAK disentuh:
|  - Route internal ERP  : Route::resource('produk', ...) dan /produk/{id}
|  - Tabel & data produk  : tidak ada perubahan skema maupun data
|  - app/Http/Controllers/KatalogProdukController.php : file tetap ada
|  - resources/views/publik/katalog-produk.blade.php     : file tetap ada
|
| Controller dan view sengaja dibiarkan (bukan dihapus) supaya fitur ini
| bisa dihidupkan lagi tanpa menulis ulang dari nol bila suatu saat
| dibutuhkan. Yang dimatikan adalah PINTU MASUKNYA, bukan datanya.
|
| Tamu tidak lagi bisa membuka /katalog-produk - URL tersebut sekarang 404.
| Toda navigasi publik (Landing Page, artikel, footer) memakai anchor
| #produk atau WhatsApp resmi, bukan /katalog-produk.
*/

// Landing Page publik - menampilkan published revision jika ada, tanpa bergantung pada Gate B
Route::get('/landing-page', [\App\Http\Controllers\LandingPageBuilderController::class, 'publicLandingPage'])
    ->name('landing-page.public');



/*
|--------------------------------------------------------------------------
| AUTHENTICATED AREA
|--------------------------------------------------------------------------
*/

// Berkas privat (desain, bukti bayar): cukup login. Tidak lewat menu.access karena dipakai gambar di banyak halaman.
Route::middleware(['auth'])->get('/storage/{folder}/{path}', [\App\Http\Controllers\BerkasPrivatController::class, 'tampil'])
    ->where('path', '.*')->name('berkas.privat');

Route::middleware(['auth'])->get('/sinyal', [\App\Http\Controllers\SinyalController::class, 'index'])->name('sinyal');

// Profil: upload/hapus foto profil sendiri (real-time, per pengguna).
Route::middleware(['auth'])->group(function () {
    Route::post('/profil/foto', [\App\Http\Controllers\UserController::class, 'uploadFoto'])->name('profil.foto.upload');
    Route::delete('/profil/foto', [\App\Http\Controllers\UserController::class, 'hapusFoto'])->name('profil.foto.hapus');
});

// throttle.write hanya menghitung POST/PUT/PATCH/DELETE (lihat
// App\Http\Middleware\ThrottleWriteRequests), jadi aman dipasang di
// sini tanpa menyentuh halaman yang memanggil endpoint baca berulang,
// seperti /sinyal di kasir.
Route::middleware(['auth', 'menu.access', 'throttle.write'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/api/kpi', [DashboardController::class, 'kpiApi'])->name('dashboard.kpi-api');



    /*
    |--------------------------------------------------------------------------
    | MASTER DATA
    |--------------------------------------------------------------------------
    */

    Route::resource('kategori', KategoriController::class)->except(['create', 'edit', 'show']); // tambah/ubah lewat modal; rute tanpa method membalas 500
    Route::resource('kategori-kas-kecil', KategoriKasKecilController::class)->except(['show', 'create', 'edit']);
    // Harus SEBELUM resource('supplier') agar tidak tertangkap {supplier}.
    Route::post('/supplier/store-ajax', [SupplierController::class, 'storeAjax'])->name('supplier.store-ajax');
    Route::resource('supplier', SupplierController::class)->except(['create', 'edit', 'show']); // tambah/ubah lewat modal; rute tanpa method membalas 500

    // Platform belanja online (Tokopedia, Shopee, ...) - dikelola pengguna, satu akses dengan Supplier.
    Route::get('/platform-belanja', [\App\Http\Controllers\PlatformBelanjaController::class, 'index'])->name('platform-belanja.index');
    Route::post('/platform-belanja', [\App\Http\Controllers\PlatformBelanjaController::class, 'store'])->name('platform-belanja.store');
    Route::put('/platform-belanja/{id}', [\App\Http\Controllers\PlatformBelanjaController::class, 'update'])->whereNumber('id')->name('platform-belanja.update');
    Route::patch('/platform-belanja/{id}/toggle', [\App\Http\Controllers\PlatformBelanjaController::class, 'toggle'])->whereNumber('id')->name('platform-belanja.toggle');
    Route::delete('/platform-belanja/{id}', [\App\Http\Controllers\PlatformBelanjaController::class, 'destroy'])->whereNumber('id')->name('platform-belanja.destroy');
    Route::resource('bank', BankController::class)->except(['create', 'edit', 'show']); // tambah/ubah lewat modal; rute tanpa method membalas 500
    // Pencarian konsumen AJAX untuk dropdown POS (dataset konsumen bisa
    // puluhan ribu - jangan menyeret semuanya ke browser tiap buka POS).
    // HARUS terdaftar sebelum resource('konsumen') agar tidak tertangkap
    // wildcard GET /konsumen/{konsumen}.
    Route::get('/konsumen/search-ajax', [KonsumenController::class, 'searchAjax'])
        ->name('konsumen.search-ajax');
    // Cek nama mirip saat mengetik konsumen BARU (daftar duplikat sebelum simpan)
    Route::get('/konsumen/cek-nama', [KonsumenController::class, 'cekNama'])
        ->name('konsumen.cek-nama');
    Route::resource('konsumen', KonsumenController::class)->except(['create', 'edit', 'show']); // tambah/ubah lewat modal; rute tanpa method membalas 500
    Route::resource('golongan', GolonganController::class)->except(['create', 'edit', 'show']); // tambah/ubah lewat modal; rute tanpa method membalas 500
    Route::resource('satuan', SatuanController::class)->except(['create', 'edit', 'show']); // tambah/ubah lewat modal; rute tanpa method membalas 500
    Route::resource('mesin', MesinController::class)->except(['create', 'edit', 'show']); // tambah/ubah lewat modal; rute tanpa method membalas 500
    Route::resource('produk', ProdukController::class)->except(['create', 'edit']);
    Route::patch('/produk/{id}/toggle-status', [ProdukController::class, 'toggleStatus'])
        ->name('produk.toggle-status');
    Route::resource('finishing', FinishingController::class)->except(['create', 'edit', 'show']);
    Route::post('/finishing/{id}/harga', [\App\Http\Controllers\FinishingHargaController::class, 'store'])->name('finishing.harga.store');
    Route::patch('/finishing-harga/{id}', [\App\Http\Controllers\FinishingHargaController::class, 'update'])->name('finishing.harga.update');
    Route::delete('/finishing-harga/{id}', [\App\Http\Controllers\FinishingHargaController::class, 'destroy'])->name('finishing.harga.destroy');

    Route::resource('merk', \App\Http\Controllers\MerkController::class)->except(['create', 'edit', 'show']);
    Route::resource('jenis-bahan', \App\Http\Controllers\JenisBahanController::class)->except(['create', 'edit', 'show']);
    Route::resource('jasa', \App\Http\Controllers\JasaController::class)->except(['create', 'edit', 'show']);
    Route::post('/jasa/{id}/harga', [\App\Http\Controllers\JasaHargaController::class, 'store'])->name('jasa.harga.store');
    Route::patch('/jasa-harga/{id}', [\App\Http\Controllers\JasaHargaController::class, 'update'])->name('jasa.harga.update');
    Route::delete('/jasa-harga/{id}', [\App\Http\Controllers\JasaHargaController::class, 'destroy'])->name('jasa.harga.destroy');

    // Jasa Jahit Borongan (PENGELUARAN) - katalog jenis jahit + harga
    // borongan, dan catatan pengeluaran yang diambil dari orderan yang
    // finishing-nya ditandai jahit.
    //
    // Catatan TIDAK punya route update, hanya store dan destroy. Sifatnya
    // seperti koreksi buku: kalau salah, hapus lalu catat ulang. Kalau
    // boleh diedit, tidak ada jejak bahwa angka yang pernah dibayar ke
    // penjahit berbeda dari yang tercatat.
    Route::get('/jasa-jahit', [\App\Http\Controllers\JasaJahitController::class, 'index'])
        ->name('jasa-jahit.index');
    Route::post('/jasa-jahit', [\App\Http\Controllers\JasaJahitController::class, 'store'])
        ->name('jasa-jahit.store');
    Route::put('/jasa-jahit/{id}', [\App\Http\Controllers\JasaJahitController::class, 'update'])
        ->name('jasa-jahit.update');
    Route::delete('/jasa-jahit/{id}', [\App\Http\Controllers\JasaJahitController::class, 'destroy'])
        ->name('jasa-jahit.destroy');
    Route::post('/jasa-jahit/penjahit', [\App\Http\Controllers\JasaJahitController::class, 'storePenjahit'])
        ->name('jasa-jahit.penjahit.store');
    Route::put('/jasa-jahit/penjahit/{id}', [\App\Http\Controllers\JasaJahitController::class, 'updatePenjahit'])
        ->whereNumber('id')->name('jasa-jahit.penjahit.update');
    Route::delete('/jasa-jahit/penjahit/{id}', [\App\Http\Controllers\JasaJahitController::class, 'destroyPenjahit'])
        ->whereNumber('id')->name('jasa-jahit.penjahit.destroy');
    Route::get('/jasa-jahit/jersey/{id}/rincian', [\App\Http\Controllers\JasaJahitController::class, 'rincianJersey'])
        ->whereNumber('id')->name('jasa-jahit.jersey.rincian');
    Route::post('/jasa-jahit/bayar', [\App\Http\Controllers\JasaJahitController::class, 'bayar'])
        ->name('jasa-jahit.bayar');
    Route::post('/jasa-jahit/catat-jersey', [\App\Http\Controllers\JasaJahitController::class, 'storeCatatJersey'])
        ->name('jasa-jahit.catat-jersey.store');
    Route::post('/jasa-jahit/catat', [\App\Http\Controllers\JasaJahitController::class, 'storeCatat'])
        ->name('jasa-jahit.catat.store');
    Route::delete('/jasa-jahit/catat/{id}', [\App\Http\Controllers\JasaJahitController::class, 'destroyCatat'])
        ->name('jasa-jahit.catat.destroy');

    Route::resource('gudang', \App\Http\Controllers\GudangController::class)->except(['create', 'edit', 'show']);
    // Customer Timeline (LEVEL 2) - akses mengikuti menu Data Konsumen [7].
    Route::get('/konsumen/{id}/timeline', [\App\Http\Controllers\KonsumenTimelineController::class, 'show'])
        ->whereNumber('id')->name('konsumen.timeline');
    Route::get('/konsumen-deposit',[\App\Http\Controllers\KonsumenDepositController::class, 'index'])->name('konsumen-deposit.index');
    Route::post('/konsumen-deposit', [\App\Http\Controllers\KonsumenDepositController::class, 'store'])->name('konsumen-deposit.store');
    Route::post('/konsumen-deposit/kembalikan', [\App\Http\Controllers\KonsumenDepositController::class, 'kembalikan'])
        ->middleware('hanya.admin')->name('konsumen-deposit.kembalikan');

    Route::get('/kas-kecil', [\App\Http\Controllers\KasKecilController::class, 'index'])->name('kas-kecil.index');
    Route::post('/kas-kecil', [\App\Http\Controllers\KasKecilController::class, 'store'])->name('kas-kecil.store');
    Route::post('/kas-kecil/saldo-awal', [\App\Http\Controllers\KasKecilController::class, 'saldoAwal'])
        ->middleware('hanya.admin')->name('kas-kecil.saldo-awal');
    Route::delete('/kas-kecil/{kasKecil}', [\App\Http\Controllers\KasKecilController::class, 'destroy'])->name('kas-kecil.destroy');
    Route::post('/kas-kecil/upload-bukti', [\App\Http\Controllers\KasKecilController::class, 'uploadBukti'])->name('kas-kecil.upload-bukti');

    Route::get('/laporan/belanja-bahan', [LaporanController::class, 'belanjaBahan'])->name('laporan.belanja-bahan');
    Route::get('/laporan/order-jersey', [LaporanController::class, 'orderJersey'])->name('laporan.order-jersey');



    /*
    |--------------------------------------------------------------------------
    | PENJUALAN
    |--------------------------------------------------------------------------
    */

    Route::get('/penjualan/kasir', [PenjualanController::class, 'create'])
        ->name('penjualan.create');

    // Satu submit_id hanya membuat satu nota di server (SubmitSekaliJalan terpasang di grup web).
    Route::post('/penjualan/store', [PenjualanController::class, 'store'])
        ->name('penjualan.store');

    Route::get('/penjualan/riwayat', [PenjualanController::class, 'riwayat'])
        ->name('penjualan.riwayat');

    Route::get('/penjualan/{id}', [PenjualanController::class, 'show'])
        ->whereNumber('id')->name('penjualan.show');

    // Tambah item susulan ke nota yang sudah berjalan. Dipisah dari store() supaya
    // alur Input Order yang ada tidak disentuh sama sekali. Hanya boleh dilakukan
    // oleh operator yang membuat nota itu, di hari yang sama, dan hanya pada nota
    // yang transaksinya sudah dibuka kasir (Diterima Kasir) dan masih Aktif.
    Route::post('/penjualan/{id}/tambah-item', [PenjualanController::class, 'tambahItemSusulan'])
        ->whereNumber('id')->name('penjualan.tambah-item');

    Route::patch('/penjualan/{id}/batalkan-nota', [PenjualanController::class, 'batalkanNota'])
        ->middleware('restrict.input-only')
        ->name('penjualan.batalkan-nota');

    // EDIT NOTA (selisih) - layar Input Pesanan yang sama, terisi item
    // nota yang mau dikoreksi. Penyimpanan memakai SELISIH per item:
    // item yang tidak berubah tidak menyentuh stok sama sekali, item yang
    // berubah mengembalikan pemakaian bahan lamanya lalu memotong yang
    // baru, item yang dihapus mengembalikan stoknya, item baru memotong
    // stok. Nota TIDAK dibatalkan, pembayaran tidak diubah, jurnal
    // hanya disesuaikan sebesar selisihnya.
    //
    // Hanya boleh oleh operator yang membuat nota itu (sama seperti
    // tambah item susulan). Prefix path-nya sama dengan '/penjualan'
    // supaya Operator Desain (menu 41) ikut boleh akses.
    Route::post('/penjualan/{id}/update-item', [PenjualanController::class, 'updateItemNota'])
        ->whereNumber('id')
        ->name('penjualan.update-item');

    // Alur Edit Nota LAMA (batal + buat ulang lewat persetujuan
    // Administrator). Dibiarkan tetap hidup supaya permintaan lama di
    // nota_edit_request masih bisa dibaca & ditindaklanjuti, tapi TIDAK
    // lagi dipakai tombol Edit di layar - alur yang aktif sekarang
    // adalah edit selisih di atas.
    Route::post('/penjualan/{id}/request-edit', [PenjualanController::class, 'requestEditNota'])
        ->middleware('restrict.input-only')
        ->name('penjualan.request-edit');
    Route::get('/penjualan/edit-nota/approval', [PenjualanController::class, 'editApprovalIndex'])
        ->name('penjualan.edit-approval');
    Route::patch('/penjualan/edit-nota/{id}/approve', [PenjualanController::class, 'approveEditNota'])
        ->name('penjualan.edit-approve');
    Route::patch('/penjualan/edit-nota/{id}/reject', [PenjualanController::class, 'rejectEditNota'])
        ->name('penjualan.edit-reject');
Route::post('/penjualan/{id}/edit-langsung', [PenjualanController::class, 'editLangsung'])
    ->name('penjualan.edit-langsung');

    Route::post('/penjualan/edit-nota/{id}/apply', [PenjualanController::class, 'applyApprovedEdit'])
        ->name('penjualan.edit-apply');

    // Penjualan Barang - jual bahan baku langsung, terintegrasi penuh ke
    // transaksi/nota yang sama (lihat catatan di PenjualanBarangController).
    // Path '/penjualan-barang' SENGAJA beda dari '/penjualan/...' di atas -
    // tidak boleh ikut tertangkap wildcard penjualan.show ({id}).
    Route::get('/penjualan-barang', [\App\Http\Controllers\PenjualanBarangController::class, 'create'])
        ->name('penjualan-barang.create');
    Route::post('/penjualan-barang/store', [\App\Http\Controllers\PenjualanBarangController::class, 'store'])
        ->name('penjualan-barang.store');

    Route::post('/penjualan/upload-thumbnail', [PenjualanController::class, 'uploadThumbnail'])
        ->name('penjualan.upload-thumbnail');

    Route::post('/konsumen/store-ajax', [KonsumenController::class, 'storeAjax']);



    /*
    |--------------------------------------------------------------------------
    | API PENJUALAN
    |--------------------------------------------------------------------------
    */

    Route::prefix('api/penjualan')->group(function () {

        Route::get('/produk/{id}',
            [PenjualanApiController::class, 'getDetailProduk']);

        Route::get('/get-harga/{id_produk}/{id_golongan}/{qty}',
            [PenjualanApiController::class, 'getHarga']);

        Route::get('/get-harga-finishing/{id_finishing}/{qty}',
            [PenjualanApiController::class, 'getHargaFinishing']);

        Route::get('/get-harga-jasa/{id_jasa}/{qty}',
            [PenjualanApiController::class, 'getHargaJasa']);

        Route::get('/check-stok',
            [PenjualanApiController::class, 'checkStok']);
    });



    /*
    |--------------------------------------------------------------------------
    | KARTU STOK
    |--------------------------------------------------------------------------
    */

    Route::get('/kartu-stok',
        [KartuStokController::class, 'index'])->name('kartu-stok.index');

    Route::post('/kartu-stok/adjustment',
        [KartuStokController::class, 'storeAdjustment'])->name('kartu-stok.adjustment');

    Route::get('/kartu-stok/{id}',
        [KartuStokController::class, 'detail']);



    /*
    |--------------------------------------------------------------------------
    | KASIR
    |--------------------------------------------------------------------------
    */

    Route::prefix('kasir')->name('kasir.')->group(function () {

        Route::get('/monitoring', [KasirController::class, 'index'])->name('index');

        Route::get('/check-pembayaran',
            [KasirController::class, 'checkNewTransaction']);

        Route::get('/bayar-form/{id}',
            [KasirController::class, 'bayarForm'])->name('bayarForm');

        Route::post('/terima/{id}',
            [KasirController::class, 'terimaTransaksi'])->name('terimaTransaksi');

        Route::post('/proses-bayar',
            [KasirController::class, 'prosesBayar'])->name('prosesBayar');

        Route::get('/waiting-list',
            [KasirController::class, 'waitingList'])->name('waiting');

        Route::get('/kelola-kas',
            [KasirController::class, 'kelolaKas'])->name('kelola');

        // P0-1: Buka kas = POST (bukan GET). Sebelumnya GET .../store-kas?jumlah=...
        // dieksekusi tanpa verifikasi CSRF (GET tidak dicek VerifyCsrfToken) -
        // celah CSRF nyata: halaman eksternal bisa memaksa browser kasir yang
        // sedang login membuka sesi kas dengan nominal sembarang. Sekarang POST
        // + @csrf di blade (kelola_kas.blade.php), nominal divalidasi di
        // KasirController::storeKas().
        Route::post('/store-kas',
            [KasirController::class, 'storeKas'])->name('store-kas');

        Route::post('/kas-manual',
            [KasirController::class, 'storeKasManual'])->name('kas-manual.store');

        Route::get('/closing-preview',
            [KasirController::class, 'previewClosing'])->name('preview-closing');

        Route::post('/closing-simpan',
            [KasirController::class, 'simpanClosing'])->name('simpan-closing');

        Route::get('/print-opsi/{id}',
            [KasirController::class, 'printOpsi'])->name('print-opsi');

        Route::get('/print-closing/{id}/{format}',
            [KasirController::class, 'printReport'])->name('print-closing');

        Route::get('/history/{id}',
            [KasirController::class, 'getHistory'])->name('history');
    });

        /*
        |--------------------------------------------------------------------------
        | PEMBELIAN BAHAN
        |--------------------------------------------------------------------------
        */
        // Pastikan strukturnya seperti ini:
    Route::patch('/kedatangan-bahan/validate/{id}', [KedatanganBahanController::class, 'validateStatus'])
     ->name('kedatangan-bahan.validate');
     Route::post('pembelian-bahan/bulk-delete', [PembelianBahanController::class, 'bulkDelete'])
            ->name('pembelian-bahan.bulk-delete');
        // Contoh Route Pelunasan
        // Menggunakan match agar bisa POST maupun PATCH
Route::match(['post', 'patch'], '/pembelian-bahan/pelunasan/{id}', [PembelianBahanController::class, 'pelunasan'])
      ->name('pembelian.pelunasan');
        Route::post('/pembelian-bahan/pembayaran/{idPembayaran}/void', [PembelianBahanController::class, 'ajukanVoidPembayaran'])
            ->name('pembelian.void-pembayaran');
        Route::get('/laporan/hutang', [LaporanController::class, 'hutang'])->name('laporan.hutang');
        Route::get('/laporan/job-costing', [LaporanController::class, 'jobCosting'])->name('laporan.job-costing');
        Route::get('pembelian-bahan/pelunasan-info', [PembelianBahanController::class, 'pelunasanInfo'])->name('pembelian.pelunasan-info');
        Route::post('pembelian-bahan/bayar-supplier/{idSupplier}', [PembelianBahanController::class, 'bayarSupplier'])->whereNumber('idSupplier')->name('pembelian.bayar-supplier');
        Route::get('pembelian-bahan/{id}/cetak/{format?}', [PembelianBahanController::class, 'cetak'])->whereNumber('id')->name('pembelian-bahan.cetak');
        // HANYA GUNAKAN SATU ROUTE INI UNTUK AJAX
        Route::get('pembelian-bahan/kedatangan-detail', [PembelianBahanController::class, 'kedatanganDetail'])
            ->name('pembelian-bahan.kedatangan-detail');
            
        Route::resource('pembelian-bahan', PembelianBahanController::class);
        // PENTING: Hapus atau komentari bagian "AJAX PEMBELIAN" yang mengarah ke KedatanganBahanController 
        // agar tidak terjadi tabrakan URL.
    /*
    |--------------------------------------------------------------------------
    | KEDATANGAN BAHAN
    |--------------------------------------------------------------------------
    */

    Route::resource('kedatangan-bahan', KedatanganBahanController::class)->except(['show']);

    Route::post('kedatangan-bahan/bulk/delete',
        [KedatanganBahanController::class, 'bulkDelete'])
        ->name('kedatangan-bahan.bulk-delete');

    Route::get('/ajax/bahan',
        [KedatanganBahanController::class, 'ajaxBahan'])
        ->name('ajax.bahan');



    /*
    |--------------------------------------------------------------------------
    | SETTING HARGA
    |--------------------------------------------------------------------------
    */

    // Harga File Siap Cetak (harga tersendiri per produk)
    Route::get('/harga-siap-cetak', [\App\Http\Controllers\HargaSiapCetakController::class, 'index'])->name('harga-siap-cetak.index');
    Route::get('/harga-siap-cetak/{idProduk}', [\App\Http\Controllers\HargaSiapCetakController::class, 'tampil'])->whereNumber('idProduk')->name('harga-siap-cetak.tampil');
    Route::post('/harga-siap-cetak/{idProduk}', [\App\Http\Controllers\HargaSiapCetakController::class, 'simpan'])->whereNumber('idProduk')->name('harga-siap-cetak.simpan');

    Route::get('/setting-harga',
        [SettingHargaController::class, 'index'])
        ->name('setting-harga.index');

    // Cetak Daftar Harga + Tier per Golongan. Path mengikuti prefix
    // 'setting-harga' supaya izinnya SAMA dengan halaman daftar harga
    // (config/menu_access.php: 'setting-harga' => [14]) tanpa ubah config.
    Route::get('/setting-harga/cetak',
        [SettingHargaController::class, 'cetak'])
        ->name('setting-harga.cetak');

    Route::post('/setting-harga/{id_produk}/tier',
        [SettingHargaController::class, 'storeRow'])
        ->name('setting-harga.tier.store');

    Route::patch('/setting-harga-tier/{id}',
        [SettingHargaController::class, 'updateRow'])
        ->name('setting-harga.tier.update');

    Route::delete('/setting-harga-tier/{id}',
        [SettingHargaController::class, 'destroyRow'])
        ->name('setting-harga.tier.destroy');

    Route::post('/setting-harga/{id_produk}/copy-golongan',
        [SettingHargaController::class, 'copyKeSemuaGolongan'])
        ->name('setting-harga.copy-golongan');



    /*
    |--------------------------------------------------------------------------
    | KARYAWAN
    |--------------------------------------------------------------------------
    */

    Route::get('/karyawan',
        [KaryawanController::class, 'index']);

    Route::post('/karyawan/store',
        [KaryawanController::class, 'store'])
        ->name('karyawan.store');

    Route::put('/karyawan/update/{id}',
        [KaryawanController::class, 'update'])
        ->name('karyawan.update');

    Route::delete('/karyawan/delete/{id}',
        [KaryawanController::class, 'destroy'])->whereNumber('id');



    /*
    |--------------------------------------------------------------------------
    | USERS
    |--------------------------------------------------------------------------
    */

    Route::get('/users',
        [UserController::class, 'index']);

    Route::post('/users/store',
        [UserController::class, 'store'])
        ->name('users.store');

    Route::put('/users/update/{id}',
        [UserController::class, 'update'])->whereNumber('id');

    Route::delete('/users/delete/{id}',
        [UserController::class, 'destroy'])->whereNumber('id');



    /*
    |--------------------------------------------------------------------------
    | BAHAN BAKU
    |--------------------------------------------------------------------------
    */

    Route::get('/bahan-baku',
        [BahanBakuController::class, 'index'])
        ->name('bahan.index');

    Route::post('/bahan-baku',
        [BahanBakuController::class, 'store'])
        ->name('bahan.store');

    Route::put('/bahan-baku/{id_bahan}',
        [BahanBakuController::class, 'update'])
        ->name('bahan.update');

    Route::delete('/bahan-baku/{id_bahan}',
        [BahanBakuController::class, 'destroy'])
        ->name('bahan.destroy');

    Route::post('/bahan-baku/bulk-delete',
        [BahanBakuController::class, 'bulkDelete'])
        ->name('bahan.bulk-delete');

    Route::patch('/bahan-baku/{id_bahan}/harga',
        [BahanBakuController::class, 'updateHarga'])
        ->name('bahan.update-harga');

    Route::patch('/bahan-baku/{id_bahan}/toggle-status',
        [BahanBakuController::class, 'toggleStatus'])
        ->name('bahan.toggle-status');

    Route::get('/produk/{id}', [ProdukController::class, 'show'])->whereNumber('id');

    /*
    |--------------------------------------------------------------------------
    | SPK & PRODUKSI
    |--------------------------------------------------------------------------
    */

    Route::get('/spk', [SpkController::class, 'index'])->name('spk.index');
    Route::patch('/spk/{id}/urgent', [SpkController::class, 'toggleUrgent'])->name('spk.toggle-urgent');
    Route::post('/spk/bulk-urgent', [SpkController::class, 'bulkUrgent'])->name('spk.bulk-urgent');
    Route::post('/spk/bulk-ambil', [SpkController::class, 'bulkAmbil'])->name('spk.bulk-ambil');
    Route::patch('/spk/{id}/ambil', [SpkController::class, 'ambilPekerjaan'])->name('spk.ambil');
    Route::patch('/spk/{id}/resolve-pending', [SpkController::class, 'resolvePending'])->name('spk.resolve-pending');
    // CATATAN: method-nya bernama print(), bukan printSpk(). Method printSpk()
// tidak pernah ada -> route lama ini memanggil method yang tidak ada dan
// berakhir "Call to undefined method SpkController::printSpk()" (HTTP 500)
// saat Kasir/Desainer menekan tombol cetak SPK.
    Route::get('/spk/{id}/print/{format?}', [SpkController::class, 'print'])->name('spk.print');
    // SPK lengkap untuk DESAINER (gambar, konsumen, bahan, finishing) - tim produksi tetap memakai SPK thermal di atas.
    Route::get('/spk-desain/{id}', [SpkController::class, 'printDesain'])->whereNumber('id')->name('spk.desain');
    Route::get('/spk/cek-antrian-baru', [SpkController::class, 'cekAntrianBaru'])->name('spk.cek-antrian-baru');

    Route::get('/produksi', [ProduksiController::class, 'index'])->name('produksi.index');
    Route::patch('/produksi/{id}/simpan-qty', [ProduksiController::class, 'simpanQty'])->name('produksi.simpan-qty');
    Route::patch('/produksi/{id}/selesai', [ProduksiController::class, 'selesai'])->name('produksi.selesai');
    Route::patch('/produksi/{id}/cancel', [ProduksiController::class, 'cancel'])->name('produksi.cancel');
    Route::get('/produksi/cek-antrian-baru', [ProduksiController::class, 'cekAntrianBaru'])->name('produksi.cek-antrian-baru');

    Route::get('/collecting', [CollectingController::class, 'index'])->name('collecting.index');
    Route::patch('/collecting/{id}/proses', [CollectingController::class, 'proses'])->name('collecting.proses');
    Route::get('/collecting/cek-antrian-baru', [CollectingController::class, 'cekAntrianBaru'])->name('collecting.cek-antrian-baru');

    Route::get('/laporan/order', [LaporanController::class, 'order'])->name('laporan.order');
    Route::get('/laporan/produksi', [LaporanController::class, 'produksi'])->name('laporan.produksi');
    Route::get('/laporan/kas', [LaporanController::class, 'kas'])->name('laporan.kas');
    Route::get('/laporan/piutang', [LaporanController::class, 'piutang'])->name('laporan.piutang');
    Route::get('/laporan/stok-bahan', [LaporanController::class, 'stokBahan'])->name('laporan.stok-bahan');
    Route::get('/laporan/hpp', [LaporanController::class, 'hpp'])->name('laporan.hpp');
// Biaya Desain per operator + komisi/bagi hasil (Operator Desain hanya melihat miliknya).
    Route::get('/laporan/biaya-desain', [\App\Http\Controllers\DesainerKomisiController::class, 'laporan'])->name('laporan.biaya-desain');
    Route::post('/laporan/biaya-desain/bayar', [\App\Http\Controllers\DesainerKomisiController::class, 'bayar'])->name('laporan.biaya-desain.bayar');

    // Pengaturan bagi hasil desainer dikunci hanya.admin, sama seperti
    // /setting-akses dan /menu-editor.
    //
    // Alasannya sama dengan catatan di blok HanyaAdministrator di bawah:
    // menu.access membaca izin dari role_permissions, dan halaman ini
    // MENULIS data yang menjadi sumber Cookbook biaya (persen komisi, kelas
    // desainer, keterkaitan kelas ke user). Tanpa gembok tambahan, role
    // non-Administrator yang kebetulan memegang izin menu "Bagi Hasil
    // Desainer" bisa menaikkan komisi desainer dari 5% menjadi 100% dan
    // mengaitkan kelas miliknya ke user lain - terbukti lewat
    // tests/Feature/SecAuditTest.php.
    //
    // Yang TIDAK dikunci: /laporan/biaya-desain dan pembayarannya. Operator
    // Desain harus tetap bisa melihat dan ditransfer komisinya sendiri.
    Route::middleware('hanya.admin')->group(function () {
        Route::get('/pengaturan-desainer', [\App\Http\Controllers\DesainerKomisiController::class, 'pengaturan'])->name('desainer.pengaturan');
        Route::post('/pengaturan-desainer/kelas', [\App\Http\Controllers\DesainerKomisiController::class, 'simpanKelas'])->name('desainer.kelas.store');
        Route::put('/pengaturan-desainer/kelas/{id}', [\App\Http\Controllers\DesainerKomisiController::class, 'updateKelas'])->whereNumber('id')->name('desainer.kelas.update');
        Route::delete('/pengaturan-desainer/kelas/{id}', [\App\Http\Controllers\DesainerKomisiController::class, 'hapusKelas'])->whereNumber('id')->name('desainer.kelas.destroy');
        Route::post('/pengaturan-desainer/anggota/{idUser}', [\App\Http\Controllers\DesainerKomisiController::class, 'tetapkanKelas'])->whereNumber('idUser')->name('desainer.anggota');
    });

    // Log Aktivitas (Audit Trail, roadmap LEVEL 1) - baca-saja, khusus
    // Administrator (lihat config/menu_access.php).
    Route::get('/log-aktivitas', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit-log.index');

    // Approval Workflow (roadmap LEVEL 1) - khusus Administrator.
    Route::get('/approval', [\App\Http\Controllers\ApprovalController::class, 'index'])->name('approval.index');
    Route::patch('/approval/{approvalRequest}/approve', [\App\Http\Controllers\ApprovalController::class, 'approve'])->name('approval.approve');
    Route::patch('/approval/{approvalRequest}/reject', [\App\Http\Controllers\ApprovalController::class, 'reject'])->name('approval.reject');

    // Accounting Period (roadmap LEVEL 1) - khusus Administrator, kunci
    // periode supaya jurnal bulan lalu tidak bisa diposting/diubah lagi.
    Route::get('/accounting-period', [\App\Http\Controllers\AccountingPeriodController::class, 'index'])->name('accounting-period.index');
    Route::patch('/accounting-period/tutup', [\App\Http\Controllers\AccountingPeriodController::class, 'tutup'])->name('accounting-period.tutup');
    Route::patch('/accounting-period/buka', [\App\Http\Controllers\AccountingPeriodController::class, 'buka'])->name('accounting-period.buka');

    // Backup & Recovery (roadmap LEVEL 1) - khusus Administrator.
    Route::get('/backup', [\App\Http\Controllers\BackupController::class, 'index'])->name('backup.index');
    Route::post('/backup/jalankan', [\App\Http\Controllers\BackupController::class, 'jalankan'])->name('backup.jalankan');

    /*
    |--------------------------------------------------------------------------
    | ORION PREMIUM (gratis, tanpa API) - showcase + fitur premium yang sudah
    | berjalan. Seluruh role login boleh membuka (lihat always_allow). Tidak
    | ada panggilan keluar; saat paywall diaktifkan nanti, gemboknya pindah ke
    | middleware premium:* - route di bawah ini tidak diubah.
    |--------------------------------------------------------------------------
    */
    Route::prefix('premium')->name('premium.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PremiumController::class, 'index'])->name('index');
        Route::get('/analisis', [\App\Http\Controllers\PremiumController::class, 'analisis'])->name('analisis');
        Route::get('/piutang-wa', [\App\Http\Controllers\PremiumController::class, 'piutangWa'])->name('piutang-wa');
        Route::post('/wa-template-save', [\App\Http\Controllers\PremiumController::class, 'simpanWaTemplate'])->name('wa-template-save');
        Route::get('/prediksi-stok', [\App\Http\Controllers\PremiumController::class, 'prediksiStok'])->name('prediksi-stok');
        Route::get('/portal', [\App\Http\Controllers\PremiumController::class, 'portal'])->name('portal');
        Route::get('/asisten', [\App\Http\Controllers\PremiumController::class, 'asisten'])->name('asisten');
        Route::get('/cadangan', [\App\Http\Controllers\PremiumController::class, 'cadangan'])->name('cadangan');
    });

    // Stock Opname (roadmap LEVEL 1) - eksekusi adjustment sungguhan lewat
    // Approval Workflow (lihat ApprovalController), bukan lewat route di sini.
    Route::get('/stock-opname', [\App\Http\Controllers\StockOpnameController::class, 'index'])->name('stock-opname.index');
    Route::get('/stock-opname/create', [\App\Http\Controllers\StockOpnameController::class, 'create'])->name('stock-opname.create');
    Route::post('/stock-opname', [\App\Http\Controllers\StockOpnameController::class, 'store'])->name('stock-opname.store');
    Route::get('/stock-opname/{stockOpname}/edit', [\App\Http\Controllers\StockOpnameController::class, 'edit'])->name('stock-opname.edit');
    Route::patch('/stock-opname/{stockOpname}', [\App\Http\Controllers\StockOpnameController::class, 'updateDetails'])->name('stock-opname.update-details');
    Route::patch('/stock-opname/{stockOpname}/ajukan', [\App\Http\Controllers\StockOpnameController::class, 'ajukan'])->name('stock-opname.ajukan');
    Route::delete('/stock-opname/{stockOpname}', [\App\Http\Controllers\StockOpnameController::class, 'destroy'])->name('stock-opname.destroy');

    // Siklus Purchasing (roadmap LEVEL 1): Purchase Request -> Purchase Order.
    // Persetujuan PR lewat /approval (tipe_aksi purchase_request).
    Route::get('/purchase-request', [\App\Http\Controllers\PurchaseRequestController::class, 'index'])->name('purchase-request.index');
    Route::get('/purchase-request/create', [\App\Http\Controllers\PurchaseRequestController::class, 'create'])->name('purchase-request.create');
    Route::post('/purchase-request', [\App\Http\Controllers\PurchaseRequestController::class, 'store'])->name('purchase-request.store');
    Route::get('/purchase-request/{purchaseRequest}/edit', [\App\Http\Controllers\PurchaseRequestController::class, 'edit'])->name('purchase-request.edit');
    Route::patch('/purchase-request/{purchaseRequest}', [\App\Http\Controllers\PurchaseRequestController::class, 'update'])->name('purchase-request.update');
    Route::patch('/purchase-request/{purchaseRequest}/ajukan', [\App\Http\Controllers\PurchaseRequestController::class, 'ajukan'])->name('purchase-request.ajukan');
    Route::delete('/purchase-request/{purchaseRequest}', [\App\Http\Controllers\PurchaseRequestController::class, 'destroy'])->name('purchase-request.destroy');

    Route::get('/purchase-order', [\App\Http\Controllers\PurchaseOrderController::class, 'index'])->name('purchase-order.index');
    Route::get('/purchase-order/create', [\App\Http\Controllers\PurchaseOrderController::class, 'create'])->name('purchase-order.create');
    Route::post('/purchase-order', [\App\Http\Controllers\PurchaseOrderController::class, 'store'])->name('purchase-order.store');
    Route::get('/purchase-order/{purchaseOrder}/edit', [\App\Http\Controllers\PurchaseOrderController::class, 'edit'])->name('purchase-order.edit');
    Route::patch('/purchase-order/{purchaseOrder}', [\App\Http\Controllers\PurchaseOrderController::class, 'update'])->name('purchase-order.update');
    Route::patch('/purchase-order/{purchaseOrder}/terbitkan', [\App\Http\Controllers\PurchaseOrderController::class, 'terbitkan'])->name('purchase-order.terbitkan');
    // QC & Rework (LEVEL 2) - operator produksi / desain (menu produksi [23]).
    Route::get('/qc', [\App\Http\Controllers\QcController::class, 'index'])->name('qc.index');
    Route::get('/qc/create', [\App\Http\Controllers\QcController::class, 'create'])->name('qc.create');
    Route::post('/qc', [\App\Http\Controllers\QcController::class, 'store'])->name('qc.store');
    // LEVEL 4: Dashboard Eksekutif (khusus Administrator), Profitabilitas, Pemakaian Bahan.
    Route::get('/audit-owner', [\App\Http\Controllers\AuditOwnerController::class, 'index'])->name('audit-owner.index');
    Route::get('/audit-owner/csv', [\App\Http\Controllers\AuditOwnerController::class, 'csv'])->name('audit-owner.csv');
    Route::get('/eksekutif', [\App\Http\Controllers\AnalisisController::class, 'dashboard'])->name('eksekutif.dashboard');
    Route::get('/laporan/profitabilitas', [\App\Http\Controllers\AnalisisController::class, 'profitabilitas'])->name('laporan.profitabilitas');
    Route::get('/laporan/pemakaian-bahan', [\App\Http\Controllers\AnalisisController::class, 'pemakaianBahan'])->name('laporan.pemakaian-bahan');
    Route::get('/laporan/evaluasi-master', [\App\Http\Controllers\LembarEvaluasiController::class, 'index'])->name('laporan.evaluasi-master');
    route::get('/laporan/biaya-tinta', [\App\Http\Controllers\AnalisisController::class, 'biayaTinta'])->name('laporan.biaya-tinta');
    Route::post('/laporan/biaya-tinta/pengaturan', [\App\Http\Controllers\AnalisisController::class, 'simpanPemakaianTinta'])->name('laporan.biaya-tinta.simpan');
    Route::delete('/laporan/biaya-tinta/pengaturan/{id}', [\App\Http\Controllers\AnalisisController::class, 'hapusPemakaianTinta'])->whereNumber('id')->name('laporan.biaya-tinta.hapus');

    // Berkas Order (LEVEL 3) - kumpulan berkas per nota; unggah Production File/QC Photo/Lainnya.
    Route::get('/berkas-order/{idTransaksi}', [\App\Http\Controllers\BerkasOrderController::class, 'show'])->whereNumber('idTransaksi')->name('berkas-order.show');
    Route::post('/berkas-order/{idTransaksi}/unggah', [\App\Http\Controllers\BerkasOrderController::class, 'unggah'])->whereNumber('idTransaksi')->name('berkas-order.unggah');


    // Quotation / Penawaran (LEVEL 2) - akses seperti Input Order.
    // Info & Panduan (postingan internal ala blog)
    Route::get('/postingan', [\App\Http\Controllers\PostinganController::class, 'index'])->name('postingan.index');
    Route::get('/postingan/create', [\App\Http\Controllers\PostinganController::class, 'create'])->name('postingan.create');
    Route::post('/postingan', [\App\Http\Controllers\PostinganController::class, 'store'])->name('postingan.store');
    Route::post('/postingan/unggah-gambar', [\App\Http\Controllers\PostinganController::class, 'unggahGambar'])->name('postingan.unggah');
    Route::get('/postingan/{id}/edit', [\App\Http\Controllers\PostinganController::class, 'edit'])->whereNumber('id')->name('postingan.edit');
    Route::put('/postingan/{id}', [\App\Http\Controllers\PostinganController::class, 'update'])->whereNumber('id')->name('postingan.update');
    Route::delete('/postingan/{id}', [\App\Http\Controllers\PostinganController::class, 'destroy'])->whereNumber('id')->name('postingan.destroy');
    Route::get('/postingan/{slug}', [\App\Http\Controllers\PostinganController::class, 'show'])->where('slug', '^(?!create$)[a-z0-9-]+$')->name('postingan.show');

    Route::get('/quotation', [\App\Http\Controllers\QuotationController::class, 'index'])->name('quotation.index');
    Route::get('/quotation/create', [\App\Http\Controllers\QuotationController::class, 'create'])->name('quotation.create');
    Route::post('/quotation', [\App\Http\Controllers\QuotationController::class, 'store'])->name('quotation.store');
    Route::get('/quotation/{quotation}/edit', [\App\Http\Controllers\QuotationController::class, 'edit'])->name('quotation.edit');
    Route::get('/quotation/{quotation}/cetak', [\App\Http\Controllers\QuotationController::class, 'cetak'])->name('quotation.cetak');
    Route::patch('/quotation/{quotation}', [\App\Http\Controllers\QuotationController::class, 'update'])->name('quotation.update');
    Route::patch('/quotation/{quotation}/kirim', [\App\Http\Controllers\QuotationController::class, 'kirim'])->name('quotation.kirim');
    Route::patch('/quotation/{quotation}/tanggapi', [\App\Http\Controllers\QuotationController::class, 'tanggapi'])->name('quotation.tanggapi');
    Route::post('/quotation/{quotation}/buat-nota', [\App\Http\Controllers\QuotationController::class, 'buatNota'])->name('quotation.buat-nota');
    Route::delete('/quotation/{quotation}', [\App\Http\Controllers\QuotationController::class, 'destroy'])->name('quotation.destroy');

    // Waste Management (LEVEL 2) - abnormal waste lewat Approval Workflow.
    Route::get('/waste', [\App\Http\Controllers\WasteController::class, 'index'])->name('waste.index');
    Route::get('/waste/create', [\App\Http\Controllers\WasteController::class, 'create'])->name('waste.create');
    Route::post('/waste', [\App\Http\Controllers\WasteController::class, 'store'])->name('waste.store');

    Route::post('/qc/rework/{id}/proses', [\App\Http\Controllers\QcController::class, 'prosesRework'])->name('qc.rework.proses');
    Route::post('/qc/rework/{id}/tutup', [\App\Http\Controllers\QcController::class, 'tutupRework'])->name('qc.rework.tutup');

    Route::post('/purchase-order/{purchaseOrder}/kedatangan', [\App\Http\Controllers\PurchaseOrderController::class, 'catatKedatangan'])->name('purchase-order.kedatangan');
    Route::patch('/purchase-order/{purchaseOrder}/batalkan', [\App\Http\Controllers\PurchaseOrderController::class, 'batalkan'])->name('purchase-order.batalkan');
    Route::delete('/purchase-order/{purchaseOrder}', [\App\Http\Controllers\PurchaseOrderController::class, 'destroy'])->name('purchase-order.destroy');

    Route::get('/harga-promo', [HargaPromoController::class, 'index'])->name('harga-promo.index');
    Route::post('/harga-promo', [HargaPromoController::class, 'store'])->name('harga-promo.store');
    Route::put('/harga-promo/{id}', [HargaPromoController::class, 'update'])->name('harga-promo.update');
    Route::patch('/harga-promo/{id}/toggle', [HargaPromoController::class, 'toggleStatus'])->name('harga-promo.toggle');
    Route::delete('/harga-promo/{id}', [HargaPromoController::class, 'destroy'])->name('harga-promo.destroy');



    /*
    |--------------------------------------------------------------------------
    | AKUNTANSI
    |--------------------------------------------------------------------------
    */

    Route::resource('akun', \App\Http\Controllers\AkunController::class)->except(['create', 'edit', 'show']);
    Route::patch('/akun/{id}/toggle-active', [\App\Http\Controllers\AkunController::class, 'toggleActive'])->name('akun.toggle-active');

    Route::prefix('akuntansi')->name('akuntansi.')->group(function () {
        Route::get('/jurnal', [\App\Http\Controllers\AkuntansiController::class, 'jurnal'])->name('jurnal');
        Route::get('/buku-besar', [\App\Http\Controllers\AkuntansiController::class, 'bukuBesar'])->name('buku-besar');
        Route::get('/neraca-saldo', [\App\Http\Controllers\AkuntansiController::class, 'neracaSaldo'])->name('neraca-saldo');
        Route::get('/laba-rugi', [\App\Http\Controllers\AkuntansiController::class, 'labaRugi'])->name('laba-rugi');
        Route::get('/neraca', [\App\Http\Controllers\AkuntansiController::class, 'neraca'])->name('neraca');
        Route::get('/perbandingan', [\App\Http\Controllers\AkuntansiController::class, 'perbandingan'])->name('perbandingan');
    });



    /*
    |--------------------------------------------------------------------------
    | AKSES
    |--------------------------------------------------------------------------
    */

    // Halaman hak akses dikunci hanya.admin, bukan hanya menu.access.
    // Alasannya ada di App\Http\Middleware\HanyaAdministrator: menu.access
    // membaca izin dari tabel role_permissions, dan tabel itu bisa ditulis
    // oleh halaman ini sendiri. Kalau sebuah role diberi izin "Pengaturan
    // Akses", tanpa gembok tambahan role itu bisa langsung mencentang semua
    // modul untuk dirinya sendiri.
    Route::middleware('hanya.admin')->group(function () {
        Route::get('/setting-akses',
            [AksesController::class, 'index'])->name('akses.index');

        Route::post('/setting-akses/update',
            [AksesController::class, 'update'])
            ->name('akses.update');

        // Pengecualian hak akses per user (di bawah prefix users = menu Data User).
        Route::get('/users/{id}/akses', [AksesController::class, 'userIndex'])->whereNumber('id')->name('akses.user');
        Route::post('/users/{id}/akses', [AksesController::class, 'userUpdate'])->whereNumber('id')->name('akses.user.update');
        Route::post('/users/{id}/akses/reset', [AksesController::class, 'userReset'])->whereNumber('id')->name('akses.user.reset');
    });

    Route::get('/pengaturan-jam-kerja', [\App\Http\Controllers\JamKerjaController::class, 'index'])->name('jam-kerja.index');
    Route::post('/pengaturan-jam-kerja', [\App\Http\Controllers\JamKerjaController::class, 'update'])->name('jam-kerja.update');

    // Menu Editor bisa menyembunyikan menu dan menambah tautan ke luar di sidebar semua orang: hanya Administrator.
    Route::middleware('hanya.admin')->group(function () {
    Route::get('/menu-editor', [\App\Http\Controllers\MenuEditorController::class, 'index'])->name('menu-editor.index');
    Route::post('/menu-editor', [\App\Http\Controllers\MenuEditorController::class, 'update'])->name('menu-editor.update');
    Route::post('/menu-editor/tambah', [\App\Http\Controllers\MenuEditorController::class, 'store'])->name('menu-editor.store');
    Route::delete('/menu-editor/{id}', [\App\Http\Controllers\MenuEditorController::class, 'destroy'])->whereNumber('id')->name('menu-editor.destroy');
    });

    // Landing Page Builder - Draft Persistence API (Checkpoint 3C).
    // Admin authorization via hanya.admin middleware.
    Route::middleware('hanya.admin')->group(function () {
        Route::get('/setting/landing-page-builder', [\App\Http\Controllers\LandingPageBuilderController::class, 'index'])->name('landing-page-builder.index');

        // Draft API
        Route::get('/setting/landing-page-builder/draft', [\App\Http\Controllers\LandingPageBuilderController::class, 'showDraft'])->name('landing-page-builder.draft.show');
        Route::put('/setting/landing-page-builder/draft', [\App\Http\Controllers\LandingPageBuilderController::class, 'saveDraft'])->name('landing-page-builder.draft.save');

        // Revisions
        Route::get('/setting/landing-page-builder/revisions', [\App\Http\Controllers\LandingPageBuilderController::class, 'listRevisions'])->name('landing-page-builder.revisions.list');
        Route::get('/setting/landing-page-builder/revisions/{revisionNumber}', [\App\Http\Controllers\LandingPageBuilderController::class, 'showRevision'])->name('landing-page-builder.revisions.show');

        // Publish
        Route::post('/setting/landing-page-builder/publish', [\App\Http\Controllers\LandingPageBuilderController::class, 'publish'])->name('landing-page-builder.publish');

        // Restore
        Route::post('/setting/landing-page-builder/revisions/{revisionNumber}/restore', [\App\Http\Controllers\LandingPageBuilderController::class, 'restoreRevision'])->name('landing-page-builder.revisions.restore');

        // Media Manager
        Route::post('/setting/landing-page-builder/media', [\App\Http\Controllers\LandingPageBuilderController::class, 'uploadMedia'])->name('landing-page-builder.media.upload');
        Route::get('/setting/landing-page-builder/media', [\App\Http\Controllers\LandingPageBuilderController::class, 'listMedia'])->name('landing-page-builder.media.list');
        Route::delete('/setting/landing-page-builder/media/{id}', [\App\Http\Controllers\LandingPageBuilderController::class, 'deleteMedia'])->name('landing-page-builder.media.delete');

        // Preview Renderer
        Route::get('/setting/landing-page-builder/preview/draft', [\App\Http\Controllers\LandingPageBuilderController::class, 'previewDraft'])->name('landing-page-builder.preview.draft');
        Route::get('/setting/landing-page-builder/preview/draft/iframe', [\App\Http\Controllers\LandingPageBuilderController::class, 'previewDraftIframe'])->name('landing-page-builder.preview.draft.iframe');
        Route::get('/setting/landing-page-builder/preview/published', [\App\Http\Controllers\LandingPageBuilderController::class, 'previewPublished'])->name('landing-page-builder.preview.published');
        Route::get('/setting/landing-page-builder/preview/revision/{revisionNumber}', [\App\Http\Controllers\LandingPageBuilderController::class, 'previewRevision'])->name('landing-page-builder.preview.revision');
    });

    /*
    |--------------------------------------------------------------------------
    | PENGATURAN APLIKASI
    |--------------------------------------------------------------------------
    */

    Route::get('/pengaturan-aplikasi',
        [SettingAplikasiController::class, 'edit'])
        ->name('pengaturan-aplikasi.edit');

    Route::post('/pengaturan-aplikasi',
        [SettingAplikasiController::class, 'update'])
        ->name('pengaturan-aplikasi.update');

    Route::get('/pengaturan-aplikasi/preview-nota',
        [SettingAplikasiController::class, 'previewNota'])
        ->name('pengaturan-aplikasi.preview-nota');

    Route::middleware('hanya.admin')->prefix('pengaturan-aplikasi/tema')->name('pengaturan-aplikasi.tema.')->group(function () {
        Route::get('/', [BrandThemeController::class, 'index'])->name('index');
        Route::post('/', [BrandThemeController::class, 'update'])->name('update');
    });

    Route::middleware('hanya.admin')->prefix('pengaturan-aplikasi/topbar')->name('pengaturan-aplikasi.topbar.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PengaturanTopbarController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\PengaturanTopbarController::class, 'update'])->name('update');
        Route::post('/reset', [\App\Http\Controllers\PengaturanTopbarController::class, 'reset'])->name('reset');
    });

    /*
    |--------------------------------------------------------------------------
    | MODUL ORDER JERSEY (terpisah dari transaksi/kasir/BOM percetakan -
    | lihat catatan keputusan di migrasi 2026_09_12_160000)
    |--------------------------------------------------------------------------
    */

    Route::prefix('jersey')->name('jersey.')->group(function () {
        // restrict.input-only di sini WALAU config/menu_access.php mengizinkan
        // id_menu 42 untuk prefix 'jersey' (dibutuhkan supaya orders.show di
        // bawah tetap bisa diakses sebagai halaman redirect sukses setelah
        // submit order baru) - tanpa middleware ini, role yang cuma dikasih
        // 42 otomatis ikut bisa browse SELURUH daftar order lewat URL
        // langsung juga (index & show berbagi prefix path yang sama,
        // config tidak bisa membedakan "/jersey" vs "/jersey/{order}").
        Route::get('/', [\App\Http\Controllers\Jersey\JerseyOrderController::class, 'index'])->middleware('restrict.input-only')->name('orders.index');
        Route::get('/create', [\App\Http\Controllers\Jersey\JerseyOrderController::class, 'create'])->name('orders.create');
        // Sengaja '/store' (bukan '/' polos) - GET /jersey (index, browse
        // semua order) & POST ke sini (submit order baru) dulu berbagi path
        // string identik ('jersey'), sehingga CheckMenuAccess (yang cuma
        // cocokkan path, tidak pernah cek method) tidak pernah bisa
        // membedakan keduanya untuk role yang sengaja dibatasi hanya boleh
        // input order baru tanpa boleh membuka daftar SEMUA order.
        Route::post('/store', [\App\Http\Controllers\Jersey\JerseyOrderController::class, 'store'])->name('orders.store');
        Route::post('/cek-kelengkapan', [\App\Http\Controllers\Jersey\JerseyOrderController::class, 'cekKelengkapan'])->name('orders.cek-kelengkapan');
        Route::post('/preview-harga', [\App\Http\Controllers\Jersey\JerseyOrderController::class, 'previewHarga'])->name('orders.preview-harga');
        Route::get('/master', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'index'])->name('master.index');

        Route::get('/keuangan', [\App\Http\Controllers\Jersey\JerseyFinanceController::class, 'index'])->name('keuangan.index');
        Route::post('/keuangan/{order}/bayar', [\App\Http\Controllers\Jersey\JerseyFinanceController::class, 'storePayment'])->name('keuangan.bayar.store');
        Route::delete('/keuangan/bayar/{payment}', [\App\Http\Controllers\Jersey\JerseyFinanceController::class, 'destroyPayment'])->name('keuangan.bayar.destroy');
        Route::post('/keuangan/upload-bukti', [\App\Http\Controllers\Jersey\JerseyFinanceController::class, 'uploadBukti'])->name('keuangan.upload-bukti');

        Route::get('/{order}', [\App\Http\Controllers\Jersey\JerseyOrderController::class, 'show'])->name('orders.show');
        Route::put('/{order}', [\App\Http\Controllers\Jersey\JerseyOrderController::class, 'update'])->middleware('restrict.input-only')->name('orders.update');
        Route::delete('/{order}', [\App\Http\Controllers\Jersey\JerseyOrderController::class, 'destroy'])->middleware('restrict.input-only')->name('orders.destroy');
        Route::get('/{order}/edit-lines', [\App\Http\Controllers\Jersey\JerseyOrderController::class, 'editLines'])->middleware('restrict.input-only')->name('orders.edit-lines');
        Route::put('/{order}/lines', [\App\Http\Controllers\Jersey\JerseyOrderController::class, 'updateLines'])->middleware('restrict.input-only')->name('orders.update-lines');
        Route::post('/{order}/lines/susulan', [\App\Http\Controllers\Jersey\JerseyOrderController::class, 'storeLineSusulan'])->middleware('restrict.input-only')->name('orders.lines.susulan');
        Route::get('/{order}/print/{format?}', [\App\Http\Controllers\Jersey\JerseyOrderController::class, 'print'])->name('orders.print');
        Route::get('/{order}/lines/{line}/spk', [\App\Http\Controllers\Jersey\JerseyOrderController::class, 'printLine'])->name('orders.lines.spk');

        Route::post('/master/designers', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'storeDesigner'])->name('master.designers.store');
        Route::put('/master/designers/{designer}', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'updateDesigner'])->name('master.designers.update');
        Route::patch('/master/designers/{designer}/toggle', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'toggleDesigner'])->name('master.designers.toggle');

        Route::post('/master/product-types', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'storeProductType'])->name('master.product-types.store');
        Route::put('/master/product-types/{productType}', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'updateProductType'])->name('master.product-types.update');
        Route::patch('/master/product-types/{productType}/toggle', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'toggleProductType'])->name('master.product-types.toggle');

        Route::post('/master/categories', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'storeCategory'])->name('master.categories.store');
        Route::put('/master/categories/{category}', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'updateCategory'])->name('master.categories.update');
        Route::patch('/master/categories/{category}/toggle', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'toggleCategory'])->name('master.categories.toggle');

        Route::post('/master/sizes', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'storeSize'])->name('master.sizes.store');
        Route::put('/master/sizes/{size}', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'updateSize'])->name('master.sizes.update');
        Route::patch('/master/sizes/{size}/toggle', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'toggleSize'])->name('master.sizes.toggle');

        Route::post('/master/price-settings/bulk', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'bulkPriceSettings'])->name('master.price-settings.bulk');
        Route::post('/master/price-settings', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'storePriceSetting'])->name('master.price-settings.store');
        Route::put('/master/price-settings/{priceSetting}', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'updatePriceSetting'])->name('master.price-settings.update');
        Route::delete('/master/price-settings/{priceSetting}', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'destroyPriceSetting'])->name('master.price-settings.destroy');

        Route::post('/master/materials', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'storeMaterial'])->name('master.materials.store');
        Route::patch('/master/materials/{material}/bahan', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'sinkronMaterial'])->name('master.materials.sinkron');
        Route::put('/master/materials/{material}', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'updateMaterial'])->name('master.materials.update');
        Route::patch('/master/materials/{material}/toggle', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'toggleMaterial'])->name('master.materials.toggle');

        Route::post('/master/components', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'storeComponent'])->name('master.components.store');
        Route::put('/master/components/{component}/scope', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'updateComponentScope'])->name('master.components.scope');
        Route::patch('/master/components/{component}/toggle', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'toggleComponent'])->name('master.components.toggle');

        Route::post('/master/usage', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'saveUsage'])->name('master.usage.save');

        Route::post('/master/sleeve-types', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'storeSleeveType'])->name('master.sleeve-types.store');
        Route::put('/master/sleeve-types/{sleeveType}', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'updateSleeveType'])->name('master.sleeve-types.update');
        Route::patch('/master/sleeve-types/{sleeveType}/toggle', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'toggleSleeveType'])->name('master.sleeve-types.toggle');
        Route::post('/master/sleeve-usage', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'saveSleeveUsage'])->name('master.sleeve-usage.save');

        Route::post('/master/collar-types', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'storeCollarType'])->name('master.collar-types.store');
        Route::put('/master/collar-types/{collarType}', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'updateCollarType'])->name('master.collar-types.update');
        Route::patch('/master/collar-types/{collarType}/toggle', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'toggleCollarType'])->name('master.collar-types.toggle');
        Route::post('/master/collar-usage', [\App\Http\Controllers\Jersey\JerseyMasterController::class, 'saveCollarUsage'])->name('master.collar-usage.save');

        Route::post('/upload-thumbnail', [\App\Http\Controllers\Jersey\JerseyOrderController::class, 'uploadThumbnail'])->name('orders.upload-thumbnail');
    });

});
/*
|--------------------------------------------------------------------------
| LACAK PESANAN - Publik (tanpa login). Hanya bisa dibuka lewat signed URL
| 30 hari yang dibuat di /premium/portal. Menampilkan progres AGREGAT item;
| tanpa HPP, komisi, atau id internal.
|--------------------------------------------------------------------------
*/
Route::get('/lacak/{id}', [\App\Http\Controllers\PremiumController::class, 'lacak'])
    ->whereNumber('id')
    ->name('lacak.nota');