<?php
/**
 * Kontroller sederhana untuk halaman katalog produk publik.
 * hanya menampilkan nama produk tanpa data sensitif (harga, BOM, stok, dll).
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KatalogProdukController extends Controller
{
    public function index()
    {
        // Ambil produk yang status-nya 'aktif'
        $produk = DB::table('produk')
            ->where('produk.status', 'aktif')
            ->select('produk.id_produk', 'produk.nama_produk', 'produk.keterangan')
            ->latest('produk.created_at')
            ->paginate(20);

        return view('publik.katalog-produk', compact('produk'));
    }
}