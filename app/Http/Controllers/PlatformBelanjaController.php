<?php

namespace App\Http\Controllers;

use App\Models\PlatformBelanja;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Master platform belanja online: tambah, ubah nama/catatan, aktif/nonaktif, hapus (bila belum dipakai). */
class PlatformBelanjaController extends Controller
{
    public function index()
    {
        $platform = PlatformBelanja::orderBy('urutan')->orderBy('nama')->get();
        $dipakai = DB::table('kedatangan_bahan')->whereNotNull('id_platform')->selectRaw('id_platform, COUNT(*) c')->groupBy('id_platform')->pluck('c', 'id_platform');

        return view('master.platform-belanja.index', compact('platform', 'dipakai'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['nama' => 'required|string|max:100|unique:platform_belanja,nama', 'catatan' => 'nullable|string|max:255']);
        $p = PlatformBelanja::create($data + ['urutan' => (int) PlatformBelanja::max('urutan') + 1]);
        AuditLogger::log('created', 'platform_belanja', $p->id, null, $data, 'Platform belanja ' . $p->nama . ' ditambahkan');

        return back()->with('success', 'Platform ' . $p->nama . ' ditambahkan.');
    }

    public function update(Request $request, int $id)
    {
        $p = PlatformBelanja::findOrFail($id);
        $data = $request->validate(['nama' => 'required|string|max:100|unique:platform_belanja,nama,' . $p->id, 'catatan' => 'nullable|string|max:255']);
        $p->update($data);

        return back()->with('success', 'Platform diperbarui.');
    }

    public function toggle(int $id)
    {
        $p = PlatformBelanja::findOrFail($id);
        $p->update(['aktif' => !$p->aktif]);

        return back()->with('success', 'Platform ' . $p->nama . ($p->aktif ? ' diaktifkan.' : ' dinonaktifkan (tidak muncul di pilihan baru, riwayat tetap).'));
    }

    public function destroy(int $id)
    {
        $p = PlatformBelanja::findOrFail($id);
        if (DB::table('kedatangan_bahan')->where('id_platform', $p->id)->exists()) {
            return back()->with('error', 'Platform sudah dipakai di riwayat pembelian - nonaktifkan saja, jangan dihapus.');
        }
        $p->delete();
        AuditLogger::log('deleted', 'platform_belanja', $id, ['nama' => $p->nama], null, 'Platform belanja ' . $p->nama . ' dihapus');

        return back()->with('success', 'Platform dihapus.');
    }
}
