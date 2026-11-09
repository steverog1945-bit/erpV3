<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HargaPromoController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('harga_promo')
            ->join('produk', 'harga_promo.id_produk', '=', 'produk.id_produk')
            ->select('harga_promo.*', 'produk.nama_produk');

        if ($request->filled('search')) {
            $query->where('produk.nama_produk', 'like', '%' . $request->search . '%');
        }

        $promo = $query->orderByDesc('harga_promo.id_promo')->paginate(20)->withQueryString();
        $produk = DB::table('produk')->orderBy('nama_produk')->get();

        return view('master.harga-promo.index', compact('promo', 'produk'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_produk' => 'required|exists:produk,id_produk',
            'harga_promo' => 'required|numeric|min:1',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
        ]);

        $idBaru = DB::table('harga_promo')->insertGetId([
            'id_produk' => $request->id_produk,
            'harga_promo' => $request->harga_promo,
            'tgl_mulai' => $request->tgl_mulai,
            'tgl_selesai' => $request->tgl_selesai,
            'status_promo' => 'aktif',
        ]);
        \App\Services\AuditLogger::master('created', 'harga_promo', 'id_promo', (int) $idBaru, null, 'Harga promo baru');

        return back()->with('success', 'Harga promo berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'id_produk' => 'required|exists:produk,id_produk',
            'harga_promo' => 'required|numeric|min:1',
            'tgl_mulai' => 'required|date',
            'tgl_selesai' => 'required|date|after_or_equal:tgl_mulai',
        ]);

        $lama = DB::table('harga_promo')->where('id_promo', $id)->first();

        DB::table('harga_promo')->where('id_promo', $id)->update([
            'id_produk' => $request->id_produk,
            'harga_promo' => $request->harga_promo,
            'tgl_mulai' => $request->tgl_mulai,
            'tgl_selesai' => $request->tgl_selesai,
        ]);
        \App\Services\AuditLogger::master('updated', 'harga_promo', 'id_promo', (int) $id, $lama, 'Harga promo diubah');

        return back()->with('success', 'Harga promo berhasil diperbarui.');
    }

    public function toggleStatus($id)
    {
        $promo = DB::table('harga_promo')->where('id_promo', $id)->first();

        if (!$promo) {
            return back()->with('error', 'Promo tidak ditemukan.');
        }

        DB::table('harga_promo')->where('id_promo', $id)->update([
            'status_promo' => $promo->status_promo === 'aktif' ? 'non-aktif' : 'aktif',
        ]);
        \App\Services\AuditLogger::master('updated', 'harga_promo', 'id_promo', (int) $id, $promo, 'Status promo diubah');

        return back()->with('success', 'Status promo diperbarui.');
    }

    public function destroy($id)
    {
        $lama = DB::table('harga_promo')->where('id_promo', $id)->first();
        DB::table('harga_promo')->where('id_promo', $id)->delete();
        if ($lama) {
            \App\Services\AuditLogger::master('deleted', 'harga_promo', 'id_promo', (int) $id, $lama, 'Harga promo dihapus');
        }

        return back()->with('success', 'Harga promo dihapus.');
    }
}
