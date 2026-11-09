<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AkunController extends Controller
{
    public function index()
    {
        $akun = DB::table('bag_akun')->orderBy('kode_akun')->get();
        return view('master.akun.index', compact('akun'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode_akun'    => 'required|max:20|unique:bag_akun,kode_akun',
            'nama_akun'    => 'required|max:100',
            'tipe_akun'    => 'required|in:Aset,Kewajiban,Modal,Pendapatan,Beban',
            'saldo_normal' => 'required|in:Debet,Kredit',
        ]);

        DB::table('bag_akun')->insert([
            'kode_akun'    => $request->kode_akun,
            'nama_akun'    => $request->nama_akun,
            'tipe_akun'    => $request->tipe_akun,
            'saldo_normal' => $request->saldo_normal,
            'is_active'    => 1,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return redirect()->back()->with('success', 'Akun berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'kode_akun'    => 'required|max:20|unique:bag_akun,kode_akun,'.$id.',id_akun',
            'nama_akun'    => 'required|max:100',
            'tipe_akun'    => 'required|in:Aset,Kewajiban,Modal,Pendapatan,Beban',
            'saldo_normal' => 'required|in:Debet,Kredit',
        ]);

        $akun = DB::table('bag_akun')->where('id_akun', $id)->first();

        // kode_akun dipakai sebagai FK (kode_akun) di jurnal_umum - kalau akun
        // ini sudah pernah dipakai transaksi, kode-nya tidak boleh diubah lagi
        // supaya histori jurnal lama tidak jadi yatim (orphan reference).
        if ($akun && $akun->kode_akun !== $request->kode_akun) {
            $terpakai = DB::table('jurnal_umum')->where('kode_akun', $akun->kode_akun)->exists();
            if ($terpakai) {
                return redirect()->back()->with('error', 'Kode akun tidak bisa diubah karena sudah punya riwayat jurnal. Ubah nama/tipe saja, atau nonaktifkan lalu buat akun baru.');
            }
        }

        DB::table('bag_akun')->where('id_akun', $id)->update([
            'kode_akun'    => $request->kode_akun,
            'nama_akun'    => $request->nama_akun,
            'tipe_akun'    => $request->tipe_akun,
            'saldo_normal' => $request->saldo_normal,
            'updated_at'   => now(),
        ]);

        return redirect()->back()->with('success', 'Akun berhasil diperbarui.');
    }

    public function toggleActive($id)
    {
        $akun = DB::table('bag_akun')->where('id_akun', $id)->first();
        if (!$akun) {
            return redirect()->back()->with('error', 'Akun tidak ditemukan.');
        }

        DB::table('bag_akun')->where('id_akun', $id)->update([
            'is_active'  => $akun->is_active ? 0 : 1,
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', $akun->is_active ? 'Akun dinonaktifkan.' : 'Akun diaktifkan kembali.');
    }

    public function destroy($id)
    {
        $akun = DB::table('bag_akun')->where('id_akun', $id)->first();
        if (!$akun) {
            return redirect()->back()->with('error', 'Akun tidak ditemukan.');
        }

        $terpakai = DB::table('jurnal_umum')->where('kode_akun', $akun->kode_akun)->exists();
        if ($terpakai) {
            return redirect()->back()->with('error', 'Gagal hapus! Akun ini sudah punya riwayat jurnal. Nonaktifkan saja kalau tidak dipakai lagi.');
        }

        DB::table('bag_akun')->where('id_akun', $id)->delete();
        return redirect()->back()->with('success', 'Akun berhasil dihapus.');
    }
}
