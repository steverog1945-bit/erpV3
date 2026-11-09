<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GudangController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('gudang');

        if ($request->filled('search')) {
            $query->where('nama_gudang', 'like', '%' . $request->search . '%');
        }

        $gudang = $query->orderBy('nama_gudang')->paginate(20)->withQueryString();
        return view('master.gudang.index', compact('gudang'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_gudang'       => 'required|max:50',
            'alamat'            => 'nullable',
            'penanggung_jawab'  => 'nullable|max:100',
            'telepon'           => 'nullable|max:30',
        ]);

        DB::table('gudang')->insert([
            'nama_gudang'       => $request->nama_gudang,
            'alamat'            => $request->alamat,
            'penanggung_jawab'  => $request->penanggung_jawab,
            'telepon'           => $request->telepon,
        ]);

        return redirect()->back()->with('success', 'Gudang berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_gudang' => 'required|max:50',
        ]);

        DB::table('gudang')->where('id_gudang', $id)->update([
            'nama_gudang'       => $request->nama_gudang,
            'alamat'            => $request->alamat,
            'penanggung_jawab'  => $request->penanggung_jawab,
            'telepon'           => $request->telepon,
        ]);

        return redirect()->back()->with('success', 'Gudang berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $cek = DB::table('kedatangan_bahan')->where('id_gudang', $id)->exists();
        if ($cek) {
            return redirect()->back()->with('error', 'Gagal hapus! Gudang ini masih digunakan oleh data kedatangan bahan.');
        }

        DB::table('gudang')->where('id_gudang', $id)->delete();
        return redirect()->back()->with('success', 'Gudang berhasil dihapus.');
    }
}
