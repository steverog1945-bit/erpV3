<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MerkController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('merk');

        if ($request->filled('search')) {
            $query->where('nama_merk', 'like', '%' . $request->search . '%');
        }

        $merk = $query->orderBy('nama_merk')->paginate(20)->withQueryString();
        return view('master.merk.index', compact('merk'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_merk'  => 'required|max:100',
            'keterangan' => 'nullable',
        ]);

        DB::table('merk')->insert([
            'nama_merk'  => $request->nama_merk,
            'keterangan' => $request->keterangan,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Merk berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_merk' => 'required|max:100',
        ]);

        DB::table('merk')->where('id_merk', $id)->update([
            'nama_merk'  => $request->nama_merk,
            'keterangan' => $request->keterangan,
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Merk berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $cek = DB::table('bahan_baku')->where('id_merk', $id)->exists();
        if ($cek) {
            return redirect()->back()->with('error', 'Gagal hapus! Merk ini masih digunakan oleh data bahan baku.');
        }

        DB::table('merk')->where('id_merk', $id)->delete();
        return redirect()->back()->with('success', 'Merk berhasil dihapus.');
    }
}
