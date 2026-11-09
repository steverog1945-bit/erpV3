<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterController extends Controller
{
    public function index()
    {
        // Mengambil data untuk ditampilkan di halaman master pendukung
        $mesin = DB::table('mesin')->get();
        $kategori = DB::table('kategori')->get();

        return view('master.pendukung.index', compact('mesin', 'kategori'));
    }

    public function storeMesin(Request $request)
    {
        $request->validate([
            'nama_mesin' => 'required|max:100',
            'tipe_mesin' => 'required|max:50',
        ]);

        DB::table('mesin')->insert([
            'nama_mesin' => $request->nama_mesin,
            'tipe_mesin' => $request->tipe_mesin,
            'biaya_per_meter' => $request->biaya_per_meter ?? 0,
        ]);

        return back()->with('success', 'Mesin berhasil didaftarkan!');
    }

    public function storeKategori(Request $request)
    {
        $request->validate([
            'nama_kategori' => 'required|unique:kategori,nama_kategori|max:50',
        ]);

        DB::table('kategori')->insert([
            'nama_kategori' => $request->nama_kategori
        ]);

        return back()->with('success', 'Kategori baru berhasil ditambahkan!');
    }
}