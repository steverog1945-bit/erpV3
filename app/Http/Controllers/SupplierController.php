<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    public function index(Request $request) {
        $query = DB::table('supplier');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_supplier', 'like', '%' . $request->search . '%')
                  ->orWhere('kontak', 'like', '%' . $request->search . '%');
            });
        }

        $supplier = $query->orderBy('nama_supplier')->paginate(20)->withQueryString();
        return view('master.supplier.index', compact('supplier'));
    }

    public function store(Request $request) {
        $request->validate([
            'nama_supplier' => 'required|max:255',
            'kontak' => 'required|max:255'
        ]);

        $idBaru = DB::table('supplier')->insertGetId([
            'nama_supplier' => $request->nama_supplier,
            'alamat' => $request->alamat,
            'kontak' => $request->kontak
        ]);
        \App\Services\AuditLogger::master('created', 'supplier', 'id_supplier', (int) $idBaru, null, 'Supplier baru');

        return redirect()->back()->with('success', 'Supplier berhasil ditambahkan.');
    }

    /** Tambah supplier dari dalam form lain (Kedatangan, PO) lewat modal - pola sama dengan konsumen/store-ajax. */
    public function storeAjax(Request $request)
    {
        $request->validate([
            'nama_supplier' => 'required|max:255',
            'kontak' => 'required|max:255',
            'alamat' => 'nullable|max:1000',
        ]);

        $nama = trim($request->nama_supplier);
        if (DB::table('supplier')->whereRaw('LOWER(nama_supplier) = ?', [mb_strtolower($nama)])->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Supplier dengan nama ini sudah terdaftar - pilih dari daftar.'], 422);
        }

        $id = DB::table('supplier')->insertGetId(['nama_supplier' => $nama, 'alamat' => $request->alamat, 'kontak' => $request->kontak]);
        \App\Services\AuditLogger::master('created', 'supplier', 'id_supplier', (int) $id, null, 'Supplier baru (dari form lain)');

        return response()->json(['status' => 'success', 'data' => ['id_supplier' => $id, 'nama_supplier' => $nama]]);
    }

    public function update(Request $request, $id) {
        $request->validate([
            'nama_supplier' => 'required|max:255',
            'kontak' => 'required|max:255'
        ]);

        $lama = DB::table('supplier')->where('id_supplier', $id)->first();

        DB::table('supplier')->where('id_supplier', $id)->update([
            'nama_supplier' => $request->nama_supplier,
            'alamat' => $request->alamat,
            'kontak' => $request->kontak
        ]);
        \App\Services\AuditLogger::master('updated', 'supplier', 'id_supplier', (int) $id, $lama, 'Data supplier diubah');

        return redirect()->back()->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy($id)
    {
        // Sama seperti konsumen: try/catch saja tidak apa-apa karena
        // kedatangan_bahan TIDAK punya FK ke supplier. purchase_orders
        // memang punya FK, jadi yang itu akan tertangkap - yang
        // kedatangan tidak.
        if (DB::table('kedatangan_bahan')->where('id_supplier', $id)->exists()) {
            return back()->with('error', 'Gagal hapus! Supplier ini masih dipakai pada riwayat kedatangan bahan.');
        }

        try {
            $lama = DB::table('supplier')->where('id_supplier', $id)->first();
            DB::table('supplier')->where('id_supplier', $id)->delete();
            if ($lama) {
                \App\Services\AuditLogger::master('deleted', 'supplier', 'id_supplier', (int) $id, $lama, 'Supplier dihapus');
            }
            return redirect()->back()->with('success', 'Supplier dihapus.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal hapus! Supplier ini masih punya purchase order.');
        }
    }
}