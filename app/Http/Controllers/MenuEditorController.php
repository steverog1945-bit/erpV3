<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Services\AuditLogger;
use App\Support\Izin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Menu Editor: tampilkan/sembunyikan modul di sidebar, beri nama tampilan & urutan (dipakai di daftar hak akses),
 * dan tambah "menu kustom" (pintasan ke halaman internal atau tautan luar) yang muncul di grup Menu Tambahan.
 */
class MenuEditorController extends Controller
{
    /** Modul yang tidak boleh disembunyikan agar admin tidak terkunci: Data User, Setting Akses, Pengaturan Aplikasi. */
    private const WAJIB = [4, 6, 37];

    public function index()
    {
        return view('pengaturan.menu-editor', ['menus' => Menu::orderBy('urutan')->orderBy('id_menu')->get(), 'wajib' => self::WAJIB]);
    }

    public function update(Request $request)
    {
        $request->validate(['menu' => 'array', 'menu.*.label' => 'nullable|string|max:100', 'menu.*.urutan' => 'nullable|integer|min:0|max:9999']);

        DB::transaction(function () use ($request) {
            foreach (Menu::all() as $m) {
                $in = (array) $request->input("menu.{$m->id_menu}", []);
                $m->aktif = in_array((int) $m->id_menu, self::WAJIB, true) ? true : isset($in['aktif']);
                $m->label_kustom = trim((string) ($in['label'] ?? '')) ?: null;
                $m->urutan = (int) ($in['urutan'] ?? 0);
                $m->save();
            }
        });

        AuditLogger::log('updated', 'menus', null, null, null, 'Menu Editor: pengaturan menu diperbarui');

        return redirect()->route('menu-editor.index')->with('success', 'Pengaturan menu disimpan.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => 'required|string|max:100',
            'url' => ['required', 'string', 'max:255', 'regex:#^(https?://|/)[^\s]*$#i'],
        ]);

        $id = max(100, (int) DB::table('menus')->max('id_menu') + 1);
        DB::table('menus')->insert([
            'id_menu' => $id, 'nama_menu' => $data['label'], 'url_link' => $data['url'], 'parent_id' => 0,
            'aktif' => true, 'label_kustom' => null, 'urutan' => 900 + $id, 'jenis' => 'kustom',
        ]);
        AuditLogger::log('created', 'menus', $id, null, $data, 'Menu kustom ' . $data['label'] . ' ditambahkan');

        return redirect()->route('menu-editor.index')->with('success', 'Menu kustom ditambahkan. Atur siapa yang boleh melihatnya di Setting Akses.');
    }

    public function destroy(int $id)
    {
        $menu = Menu::where('id_menu', $id)->where('jenis', 'kustom')->firstOrFail();
        DB::transaction(function () use ($menu) {
            DB::table('role_permissions')->where('id_menu', $menu->id_menu)->delete();
            DB::table('user_permissions')->where('id_menu', $menu->id_menu)->delete();
            $menu->delete();
        });
        Izin::lupakan();
        AuditLogger::log('deleted', 'menus', $id, ['nama' => $menu->nama_menu], null, 'Menu kustom ' . $menu->nama_menu . ' dihapus');

        return redirect()->route('menu-editor.index')->with('success', 'Menu kustom dihapus.');
    }
}
