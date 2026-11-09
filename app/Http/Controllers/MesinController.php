<?php

namespace App\Http\Controllers;

use App\Models\Mesin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MesinController extends Controller
{
    public function index(Request $request)
    {
        $query = Mesin::query();

        if ($request->filled('search')) {
            $query->where('nama_mesin', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('tipe_mesin')) {
            $query->where('tipe_mesin', $request->tipe_mesin);
        }

        $mesin = $query->orderBy('nama_mesin')->paginate(20)->withQueryString();
        $daftarTipe = Mesin::select('tipe_mesin')->distinct()->orderBy('tipe_mesin')->pluck('tipe_mesin');

        return view('master.mesin.index', compact('mesin', 'daftarTipe'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_mesin' => 'required|string|max:100',
            'tipe_mesin' => 'required|string|max:50',
        ]);
        $data['tampil_divisi'] = $request->boolean('tampil_divisi');
        Mesin::create($data);
        return back()->with('success', 'Mesin berhasil ditambah!');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'nama_mesin' => 'required|string|max:100',
            'tipe_mesin' => 'required|string|max:50',
        ]);
        $data['tampil_divisi'] = $request->boolean('tampil_divisi');
        $mesin = Mesin::findOrFail($id);
        $mesin->update($data);
        return back()->with('success', 'Mesin berhasil diubah!');
    }

    public function destroy($id)
    {
        $mesin = Mesin::findOrFail($id);

        // Dulu method ini hanya berisi try/catch dengan komentar "mencegah
        // error constraint". Tapi tabel-tabel ini TIDAK punya foreign key
        // constraint sama sekali (lihat blok FK di repo ini), jadi tidak
        // ada error yang bisa ditangkap - penghapusan mesin yang masih
        // dipakai akan BERHASIL dan meninggalkan produk, bahan baku, dan
        // pemakaian tinta menunjuk mesin yang sudah tidak ada.
        // Karena itu pemakaiannya dicek lebih dulu, satu per satu.
        $pemakaian = [
            'Produk'        => DB::table('produk')->where('id_mesin', $id)->exists(),
            'Bahan Baku'    => DB::table('bahan_baku')->where('id_mesin', $id)->exists(),
            'Finishing'     => DB::table('finishing')->where('id_mesin', $id)->exists(),
            'Pemakaian Tinta' => DB::table('pemakaian_tinta')->where('id_mesin', $id)->exists(),
            'Karyawan'      => DB::table('karyawan_mesin')->where('id_mesin', $id)->exists(),
        ];

        $dipakai = array_keys(array_filter($pemakaian));
        if ($dipakai) {
            return back()->with('error',
                'Gagal hapus! Mesin ini masih dipakai pada: ' . implode(', ', $dipakai) . '.'
            );
        }

        DB::transaction(function () use ($id) {
            DB::table('karyawan_mesin')->where('id_mesin', $id)->delete();
            Mesin::where('id_mesin', $id)->delete();
        });

        return back()->with('success', 'Mesin berhasil dihapus!');
    }
}