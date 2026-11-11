<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Izin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Hak akses per modul dengan 4 aksi (lihat / tambah / kelola / hapus): per role di sini,
 * dan pengecualian per user di userIndex/userUpdate (menang atas role).
 */
class AksesController extends Controller
{
    public function __construct()
    {
        // Gembok kedua (selain middleware hanya.admin & khusus_administrator
        // di config/menu_access.php): halaman ini bisa MENULIS matriks hak
        // akses - tidak boleh bisa dipegang non-Administrator.
        abort_unless(\App\Support\Izin::adalahAdmin(request()->user()), 403, 'Halaman ini khusus Administrator.');
    }

    /** @return array<int, array{view:bool,add:bool,edit:bool,delete:bool}> */
    private function izinRole(int $idRole): array
    {
        $izin = [];
        foreach (DB::table('role_permissions')->where('id_role', $idRole)->get() as $r) {
            $izin[(int) $r->id_menu] = ['view' => (bool) $r->can_view, 'add' => (bool) $r->can_add, 'edit' => (bool) $r->can_edit, 'delete' => (bool) $r->can_delete];
        }

        return $izin;
    }

    /** Sidik jari hak akses satu role / satu user (dipakai sebagai "versi" - optimistic locking, tanpa kolom updated_at). */
    public function versiRole(int $idRole): string
    {
        $baris = DB::table('role_permissions')->where('id_role', $idRole)->orderBy('id_menu')->get()->map(fn ($r) => (array) $r)->all();

        return md5(json_encode([$baris, (int) DB::table('roles')->where('id_role', $idRole)->value('boleh_lihat_harga')]));
    }

    public function versiUser(int $idUser): string
    {
        return md5(json_encode(DB::table('user_permissions')->where('id_user', $idUser)->orderBy('id_menu')->get(['id_menu', 'can_view', 'can_add', 'can_edit', 'can_delete'])->map(fn ($r) => (array) $r)->all()));
    }

    /** Baca isian matriks izin[menu][aksi] -> array id_menu => flag (add/edit/delete otomatis menyalakan view). */
    private function bacaMatriks(Request $request): array
    {
        $hasil = [];
        foreach (Menu::pluck('id_menu') as $idMenu) {
            $in = (array) $request->input("izin.{$idMenu}", []);
            $f = ['view' => isset($in['view']), 'add' => isset($in['add']), 'edit' => isset($in['edit']), 'delete' => isset($in['delete'])];
            if ($f['add'] || $f['edit'] || $f['delete']) {
                $f['view'] = true;
            }
            $hasil[(int) $idMenu] = $f;
        }

        return $hasil;
    }

    public function index(Request $request)
    {
        $roles = Role::orderBy('id_role')->get();
        $role = $roles->firstWhere('id_role', (int) $request->query('role'))
            ?? $roles->first(fn ($r) => $r->nama_role !== 'Administrator')
            ?? $roles->first();

        return view('akses.index', [
            'roles' => $roles,
            'role' => $role,
            'menus' => Menu::orderBy('urutan')->orderBy('id_menu')->get(),
            'izin' => $this->izinRole((int) $role->id_role),
            'versi' => $this->versiRole((int) $role->id_role),
        ]);
    }

    public function update(Request $request)
    {
        // Jalur lama (satu form untuk semua role): permissions[id_role][] = id_menu -> semua aksi diberikan.
        // Dicek SEBELUM validasi role_id: jalur lama memang tidak membawa role_id, sehingga bila validasi
        // dijalankan lebih dulu, jalur ini selalu gagal "role id wajib diisi" dan tidak pernah tercapai.
        if ($request->has('permissions') || !$request->has('role_id')) {
            return $this->updateLama($request);
        }

        $validated = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id_role'],
            'boleh_lihat_harga' => ['nullable', 'boolean'],
            // Hak menginput Biaya Desain - SENGAJA terpisah dari boleh_lihat_harga.
            // Role Operator Desain bekerja di desain tapi tidak boleh melihat harga,
            // jadi memakai centang "boleh melihat harga" hanya demi satu field itu
            // akan memberi izin jauh melebihi kebutuhan (buka semua harga produk,
            // cetak, finishing, dan halaman harga). Nilai baru TIDAK ikut masuk
            // ke versiRole() di bawah - mengubah centang ini tidak mengubah isi
            // matriks hak akses, jadi tidak boleh menggagalkan optimistic locking.
            'boleh_input_biaya_desain' => ['nullable', 'boolean'],
        ]);

        $role = Role::findOrFail((int) $validated['role_id']);
        if (!Izin::bolehKelolaRole(auth()->user(), (int) $role->id_role)) {
            abort(403, 'Hak akses role Administrator dan role Anda sendiri hanya bisa diubah oleh Administrator.');
        }
        // Optimistic locking: hak role ini sudah diubah admin lain sejak halaman dibuka -> jangan menimpa.
        if ($request->filled('_versi') && $request->input('_versi') !== $this->versiRole((int) $role->id_role)) {
            return redirect()->route('akses.index', ['role' => $role->id_role])->with('error', 'Hak akses role ini sudah diubah oleh admin lain sejak Anda membukanya, jadi perubahan Anda TIDAK disimpan. Halaman sudah dimuat ulang dengan data terbaru - ulangi perubahan Anda.');
        }
        $matriks = $this->bacaMatriks($request);

        // $validated WAJIB ikut ditangkap. Closure PHP tidak mewarisi variabel lokal
        // secara otomatis: tanpa baris ini, $validated di dalam closure
        // undefined -> null -> `?? false` selalu jatuh ke false, jadi kedua
        // centang (boleh_lihat_harga DAN boleh_input_biaya_desain) selalu
        // tersimpan 0 apa pun yang diklik. Bug lama: baris boleh_lihat_harga
        // sudah ters seperti ini sejak awal, hanya saja tidak ada test yang
        // menyentuh jalur simpan form ini - nilainya di database selalu diisi
        // langsung. Baris boleh_input_biaya_desain mewarisi bug yang sama.
        DB::transaction(function () use ($role, $matriks, $validated) {
            DB::table('role_permissions')->where('id_role', $role->id_role)->delete();
            $baris = [];
            foreach ($matriks as $idMenu => $f) {
                if ($f['view']) {
                    $baris[] = ['id_role' => $role->id_role, 'id_menu' => $idMenu, 'can_view' => 1, 'can_add' => (int) $f['add'], 'can_edit' => (int) $f['edit'], 'can_delete' => (int) $f['delete']];
                }
            }
            if ($baris) {
                DB::table('role_permissions')->insert($baris);
            }
            $role->boleh_lihat_harga = $role->nama_role === 'Administrator' || (bool) ($validated['boleh_lihat_harga'] ?? false);
            // Administrator tetap selalu boleh; role lain mengikuti centang.
            // Tidak dikunci oleh $hargaTerkunci: role yang tidak boleh melihat
            // harga pun boleh menginput Biaya Desain kalau dicentang.
            $role->boleh_input_biaya_desain = $role->nama_role === 'Administrator' || (bool) ($validated['boleh_input_biaya_desain'] ?? false);
            $role->save();
        });

        Izin::lupakan();
        AuditLogger::log('updated', 'role_permissions', (int) $role->id_role, null, ['role' => $role->nama_role], "Hak akses role {$role->nama_role} diperbarui (matriks lihat/tambah/kelola/hapus)");

        return redirect()->route('akses.index', ['role' => $role->id_role])->with('success', "Hak akses role {$role->nama_role} disimpan.");
    }

    private function updateLama(Request $request)
    {
        // Jalur ini menulis id_role dan id_menu LANGSUNG dari request ke
        // tabel role_permissions. Sebelumnya keduanya dibiarkan mentah:
        // request buatan bisa menyisipkan id_role atau id_menu yang tidak
        // ada, dan foreach di bawahnya akan iterate karakter demi
        // karakter kalau nilainya string, bukan array.
        $valid = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['integer', 'min:1'],
            'lihat_harga' => ['nullable', 'array'],
            'lihat_harga.*' => ['integer', 'min:1'],
        ]);

        $idRoleValid = Role::pluck('id_role')->map(fn ($x) => (int) $x)->all();
        $idMenuValid = Menu::pluck('id_menu')->map(fn ($x) => (int) $x)->all();

        $permissions = $valid['permissions'] ?? [];
        $dilewati = 0;
        foreach ($permissions as $roleId => $menuIds) {
            if (! in_array((int) $roleId, $idRoleValid, true)) { $dilewati++; continue; }
            $permissions[(int) $roleId] = array_values(array_filter(
                array_map('intval', (array) $menuIds),
                fn ($m) => in_array($m, $idMenuValid, true)
            ));
        }

        // Non-admin hanya boleh menyentuh role yang memang boleh dikelolanya;
        // role lain (Administrator, role sendiri) dibiarkan persis seperti adanya.
        $aktor = auth()->user();
        $boleh = fn ($idRole) => Izin::bolehKelolaRole($aktor, (int) $idRole);

        DB::transaction(function () use ($permissions, $valid, $idRoleValid, $boleh) {
            $terkelola = Role::all()->filter(fn ($r) => $boleh($r->id_role))->pluck('id_role')->all();
            DB::table('role_permissions')->whereIn('id_role', $terkelola)->delete();
            $dataToInsert = [];
            foreach ($permissions as $roleId => $menuIds) {
                if (!$boleh($roleId)) {
                    continue;
                }
                foreach ($menuIds as $menuId) {
                    $dataToInsert[] = ['id_role' => $roleId, 'id_menu' => $menuId, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1];
                }
            }
            if ($dataToInsert) {
                DB::table('role_permissions')->insert($dataToInsert);
            }

            $bolehLihat = collect($valid['lihat_harga'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => in_array($id, $idRoleValid, true))
                ->all();
            foreach (Role::all() as $role) {
                if (!$boleh($role->id_role)) {
                    continue;
                }
                $role->boleh_lihat_harga = $role->nama_role === 'Administrator' || in_array((int) $role->id_role, $bolehLihat, true);
                $role->save();
            }
        });
        Izin::lupakan();

        // Jalur lama ini sebelumnya TIDAK menulis audit log, padahal ia
        // menghapus SELURUH tabel role_permissions dan menulis ulang
        // semua hak akses. Perubahan sedah dramatik tanpa jejak.
        AuditLogger::log('updated', 'role_permissions', 0, null,
            ['jumlah_role' => count($permissions), 'dilewati' => $dilewati],
            'Hak akses seluruh role disinkronkan lewat formulir lama' . ($dilewati ? " ({$dilewati} role diabaikan karena tidak dikenal)" : '')
        );

        return back()->with('success', 'Hak akses seluruh role berhasil disinkronkan!');
    }

    // ---------- pengecualian per user ----------

    public function userIndex(int $id)
    {
        $user = User::with('role')->findOrFail($id);
        $izinRole = $this->izinRole((int) $user->id_role);
        $override = DB::table('user_permissions')->where('id_user', $user->id_user)->get()->keyBy('id_menu');

        $izin = $izinRole;
        foreach ($override as $idMenu => $o) {
            $izin[(int) $idMenu] = ['view' => (bool) $o->can_view, 'add' => (bool) $o->can_add, 'edit' => (bool) $o->can_edit, 'delete' => (bool) $o->can_delete];
        }

        return view('akses.user', [
            'user' => $user, 'izin' => $izin, 'izinRole' => $izinRole, 'override' => $override,
            'menus' => Menu::orderBy('urutan')->orderBy('id_menu')->get(),
            'versi' => $this->versiUser((int) $user->id_user),
        ]);
    }

    public function userUpdate(Request $request, int $id)
    {
        $request->validate([
            'izin' => ['nullable', 'array'],
            'izin.*' => ['nullable', 'array'],
            'izin.*.view' => ['nullable', 'boolean'],
            'izin.*.add' => ['nullable', 'boolean'],
            'izin.*.edit' => ['nullable', 'boolean'],
            'izin.*.delete' => ['nullable', 'boolean'],
        ]);

        $user = User::findOrFail($id);
        $this->pastikanBolehKelolaUser($user);
        if ($request->filled('_versi') && $request->input('_versi') !== $this->versiUser((int) $user->id_user)) {
            return redirect()->route('akses.user', $user->id_user)->with('error', 'Hak akses user ini sudah diubah admin lain sejak Anda membukanya, jadi perubahan Anda TIDAK disimpan. Halaman sudah dimuat ulang dengan data terbaru.');
        }
        $izinRole = $this->izinRole((int) $user->id_role);
        $matriks = $this->bacaMatriks($request);
        $kosong = ['view' => false, 'add' => false, 'edit' => false, 'delete' => false];

        DB::transaction(function () use ($user, $matriks, $izinRole, $kosong) {
            DB::table('user_permissions')->where('id_user', $user->id_user)->delete();
            $baris = [];
            foreach ($matriks as $idMenu => $f) {
                // Simpan HANYA yang berbeda dari hak role-nya; yang sama tetap "ikut role".
                if (($izinRole[$idMenu] ?? $kosong) === $f) {
                    continue;
                }
                $baris[] = ['id_user' => $user->id_user, 'id_menu' => $idMenu, 'can_view' => (int) $f['view'], 'can_add' => (int) $f['add'], 'can_edit' => (int) $f['edit'], 'can_delete' => (int) $f['delete'], 'created_at' => now(), 'updated_at' => now()];
            }
            if ($baris) {
                DB::table('user_permissions')->insert($baris);
            }
        });

        Izin::lupakan();
        AuditLogger::log('updated', 'user_permissions', (int) $user->id_user, null, ['user' => $user->username], "Pengecualian hak akses user {$user->username} diperbarui");

        return redirect()->route('akses.user', $user->id_user)->with('success', 'Hak akses khusus user disimpan.');
    }

    private function pastikanBolehKelolaUser(User $user): void
    {
        if (!Izin::bolehKelolaUser(auth()->user(), $user)) {
            abort(403, 'Hak khusus akun Administrator dan akun Anda sendiri hanya bisa diubah oleh Administrator.');
        }
    }

    public function userReset(int $id)
    {
        $user = User::findOrFail($id);
        $this->pastikanBolehKelolaUser($user);
        DB::table('user_permissions')->where('id_user', $user->id_user)->delete();
        Izin::lupakan();
        AuditLogger::log('updated', 'user_permissions', (int) $user->id_user, null, ['user' => $user->username], "Pengecualian hak akses user {$user->username} dihapus (kembali ikut role)");

        return redirect()->route('akses.user', $user->id_user)->with('success', 'Hak akses user dikembalikan mengikuti role.');
    }
}
