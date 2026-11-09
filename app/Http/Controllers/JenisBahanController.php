<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JenisBahanController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('jenis_bahan');

        if ($request->filled('search')) {
            $query->where('nama_jenis', 'like', '%' . $request->search . '%');
        }

        $jenisBahan = $query->orderBy('nama_jenis')->paginate(20)->withQueryString();
        return view('master.jenis-bahan.index', compact('jenisBahan'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_jenis' => 'required|max:100',
            'keterangan' => 'nullable',
        ]);

        DB::table('jenis_bahan')->insert([
            'nama_jenis' => $request->nama_jenis,
            'keterangan' => $request->keterangan,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Jenis bahan berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_jenis' => 'required|max:100',
        ]);

        DB::table('jenis_bahan')->where('id_jenis_bahan', $id)->update([
            'nama_jenis' => $request->nama_jenis,
            'keterangan' => $request->keterangan,
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Jenis bahan berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $cek = DB::table('bahan_baku')->where('id_jenis_bahan', $id)->exists();
        if ($cek) {
            return redirect()->back()->with('error', 'Gagal hapus! Jenis bahan ini masih digunakan oleh data bahan baku.');
        }

        DB::table('jenis_bahan')->where('id_jenis_bahan', $id)->delete();
        return redirect()->back()->with('success', 'Jenis bahan berhasil dihapus.');
    }
}
