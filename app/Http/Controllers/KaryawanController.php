<?php

namespace App\Http\Controllers;

use App\Models\Karyawan;
use App\Models\Mesin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KaryawanController extends Controller
{
    // Bagian yang benar-benar turun ke mesin fisik/lini per-mesin. Kasir,
    // Admin, dan Gudang tidak beroperasi di mesin tertentu - dicek di sini
    // juga (bukan cuma disembunyikan lewat JS di form) supaya request yang
    // dimanipulasi manual tidak bisa menitip penugasan mesin untuk bagian
    // yang tidak relevan.
    private const BAGIAN_BISA_MESIN = ['Produksi', 'Desain'];

    public function index(Request $request)
    {
        $query = Karyawan::with('mesin');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('nama_karyawan', 'like', '%' . $request->search . '%')
                  ->orWhere('telepon', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('bagian')) {
            $query->where('bagian', $request->bagian);
        }

        $karyawan = $query->orderBy('nama_karyawan')->paginate(20)->withQueryString();
        $daftarBagian = Karyawan::select('bagian')->distinct()->orderBy('bagian')->pluck('bagian');
        $daftarMesin = Mesin::orderBy('nama_mesin')->get();

        return view('master.karyawan.index', compact('karyawan', 'daftarBagian', 'daftarMesin'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_karyawan' => 'required|max:100',
            'bagian' => 'required',
            'telepon' => 'required|max:20',
            'mesin_ids' => 'nullable|array',
            'mesin_ids.*' => 'exists:mesin,id_mesin',
        ]);
        $karyawan = Karyawan::create($request->only(['nama_karyawan', 'bagian', 'telepon']));
        $mesinIds = in_array($request->bagian, self::BAGIAN_BISA_MESIN) ? $request->input('mesin_ids', []) : [];
        $karyawan->mesin()->sync($mesinIds);
        return back()->with('success', 'Data karyawan berhasil ditambahkan!');
    }
   public function update(Request $request, $id)
    {
        $request->validate([
            'mesin_ids' => 'nullable|array',
            'mesin_ids.*' => 'exists:mesin,id_mesin',
        ]);

        $karyawan = Karyawan::findOrFail($id);
        $karyawan->update([
            'nama_karyawan' => $request->nama_karyawan,
            'bagian'        => $request->bagian,
            'telepon'       => $request->telepon,
            'status_aktif'  => $request->status_aktif,
        ]);
        $mesinIds = in_array($request->bagian, self::BAGIAN_BISA_MESIN) ? $request->input('mesin_ids', []) : [];
        $karyawan->mesin()->sync($mesinIds);

        return back()->with('success', 'Data karyawan berhasil diperbarui!');
    }

    // P2 (audit): penghapusan karyawan + user terkait dibungkus transaksi -
    // sebelumnya user dihapus dulu tanpa pengaman, lalu kalau karyawan
    // gagal dihapus (masih tercatat di transaksi), AKUN LOGIN-nya sudah
    // hilang padahal karyawannya masih ada (data setengah terhapus).
    public function destroy($id)
    {
        // Penugasan ke mesin (karyawan_mesin) tidak punya foreign key,
        // jadi tidak ada error yang bisa ditangkap - tanpa cek ini,
        // penugasan itu akan tertinggal menunjuk karyawan yang sudah
        // dihapus. Akun login (users) justru SENGAJA ikut terhapus,
        // supaya tidak ada akun tanpa orang.
        $mesinDitugaskan = \DB::table('karyawan_mesin')->where('id_karyawan', $id)->count();
        if ($mesinDitugaskan > 0) {
            return back()->with('error',
                "Gagal hapus! Karyawan ini masih ditugaskan ke $mesinDitugaskan mesin. "
                . 'Lepas dulu penugasannya di menu Karyawan.'
            );
        }

        try {
            \DB::transaction(function () use ($id) {
                // 1. Hapus User yang terhubung dulu (jika ada)
                \DB::table('users')->where('id_karyawan', $id)->delete();

                // 2. Baru hapus Karyawannya (gagal di sini = user ikut roll-back)
                $deleted = \DB::table('karyawan')->where('id_karyawan', $id)->delete();

                if (!$deleted) {
                    throw new \RuntimeException('Data tidak ditemukan.');
                }
            });

            return back()->with('success', 'Karyawan dan akun loginnya berhasil dihapus.');
        } catch (\RuntimeException $e) {
            return back()->with('error', \App\Support\PesanError::untuk($e));
        } catch (\Exception $e) {
            // Jika masih gagal (mungkin sudah ada data Transaksi), tangkap errornya
            return back()->with('error', 'Gagal hapus! Karyawan ini sudah tercatat dalam data transaksi/kas.');
        }
    }
}