<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Karyawan;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    // Pemetaan bagian karyawan -> nama role yang sesuai (dibakukan saat
    // melengkapi role Operator Produksi & Gudang - lihat migrasi
    // 140000/150000). Dipakai di form Buat Akun User untuk auto-pilih
    // role begitu karyawan dipilih, supaya kejadian seperti "Operator
    // Desain" dulu ke-assign ke siapa saja tanpa mikir tidak terulang -
    // admin tetap bisa override manual kalau memang ada kasus khusus.
    private const BAGIAN_ROLE_MAP = [
        'Admin'    => 'Administrator',
        'Desain'   => 'Operator Desain',
        'Produksi' => 'Operator Produksi',
        'Kasir'    => 'Kasir',
        'Gudang'   => 'Gudang',
    ];

    public function index()
    {
        $users = User::with(['karyawan', 'role'])->get();
        $karyawan = Karyawan::whereDoesntHave('user')->get(); // Hanya tampilkan karyawan yang belum punya user
        $roles = Role::all();
        $bagianRoleMap = self::BAGIAN_ROLE_MAP;
        return view('master.user.index', compact('users', 'karyawan', 'roles', 'bagianRoleMap'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_karyawan' => 'required|unique:users,id_karyawan',
            'username' => 'required|unique:users|max:255',
            // P1: aplikasi keuangan - minimal 8 karakter (sebelumnya 5).
            'password' => 'required|min:8',
            'id_role' => 'required|exists:roles,id_role'
        ]);

        // Hanya Administrator yang boleh membuat akun berrole Administrator
        // (atau role yang sama dengan dirinya bila dia bukan admin).
        if (!\App\Support\Izin::bolehBerikanRole(Auth::user(), (int) $request->id_role)) {
            abort(403, 'Role itu hanya bisa diberikan oleh Administrator. Pilih role lain untuk akun ini.');
        }

        // password & id_role sengaja diisi lewat assignment eksplisit (bukan
        // mass-assignment) - keduanya bukan lagi $fillable di model User
        // supaya tidak bisa ke-mass-assign dari endpoint lain yang ceroboh.
        $user = new User([
            'id_karyawan' => $request->id_karyawan,
            'username' => $request->username,
        ]);
        $user->password = Hash::make($request->password);
        $user->id_role = $request->id_role;
        $user->save();

        \App\Services\AuditLogger::log('created', 'users', (int) $user->id_user, null, ['username' => $user->username, 'id_role' => (int) $user->id_role], 'Akun user baru dibuat');

        return back()->with('success', 'User berhasil dibuat!');
    }
    public function update(Request $request, $id)
{
    // P1: validasi form update diperbaiki - sebelumnya menyalin validation
    // store mentah-mentah: 'id_karyawan' required (field tidak ada di form
    // update) dan 'username' unique tanpa mengabaikan record sendiri, jadi
    // mengedit username user itu sendiri SELALU gagal "username sudah
    // dipakai". Password sekarang opsional (diisi = ganti).
    $request->validate([
        'username' => 'max:255|required|unique:users,username,' . (int) $id . ',id_user',
        'password' => 'nullable|min:8',
        'id_role' => 'required|exists:roles,id_role'
    ]);

    $user = User::findOrFail($id);

    // Akun Administrator dan akun sendiri tidak boleh diubah oleh non-admin;
    // role Administrator tidak boleh diberikan oleh non-admin.
    if (!\App\Support\Izin::bolehKelolaUser(Auth::user(), $user) || !\App\Support\Izin::bolehBerikanRole(Auth::user(), (int) $request->id_role)) {
        abort(403, 'Anda tidak berwenang mengubah akun atau role ini. Perubahan pada Administrator dan pada akun Anda sendiri dilakukan oleh Administrator.');
    }

    $roleLama = (int) $user->id_role;
    $user->username = $request->username;
    $user->id_role = $request->id_role;

    // Hanya update password jika diisi
    if ($request->filled('password')) {
        $user->password = Hash::make($request->password);
    }

    $user->save();

    // Nilai password tidak pernah dicatat; hanya fakta bahwa password diganti.
    \App\Services\AuditLogger::log('updated', 'users', (int) $id, ['id_role' => $roleLama], ['username' => $user->username, 'id_role' => (int) $user->id_role, 'password_diganti' => $request->filled('password')], $roleLama !== (int) $user->id_role ? 'Akun user diperbarui (role berubah)' : 'Akun user diperbarui');

    return back()->with('success', 'Akun user berhasil diperbarui!');
}

public function destroy($id)
{
    $user = User::findOrFail($id);
    if ($user->id_user == Auth::id()) {
        return back()->with('error', 'Anda tidak bisa menghapus akun sendiri!');
    }
    if (!\App\Support\Izin::bolehKelolaUser(Auth::user(), $user)) {
        abort(403, 'Akun Administrator hanya bisa dihapus oleh Administrator.');
    }

    // P1: cek relasi dulu - user yang masih tercatat sebagai kasir di nota
    // TIDAK boleh dihapus (FK transaksi.id_user_kasir menolaknya dengan
    // SQL error mentah sebelumnya). Nonaktifkan lewat role/ganti data
    // karyawan, bukan hapus - riwayat transaksi harus tetap bisa dipertanggung-
    // jawabkan.
    $tercatatKasir = DB::table('transaksi')->where('id_user_kasir', $id)->exists();
    $punyaPembatalan = DB::table('transaksi')->where('id_user_pembatal_nota', $id)->exists();
    if ($tercatatKasir || $punyaPembatalan) {
        return back()->with('error', 'Akun ini masih tercatat pada data transaksi (sebagai kasir/pembatal nota) dan tidak bisa dihapus. Nonaktifkan akunnya lewat Setting Akses atau ganti rolenya, agar riwayat tetap utuh.');
    }

    // Audit Fase 2: purchase_orders/purchase_requests/quotations/stock_opnames
    // punya FK ON DELETE CASCADE ke users - menghapus user MEMBUANG seluruh
    // dokumen yang dia buat (data bisnis hilang diam-diam). Tolak di sini.
    $dokumen = [];
    foreach (['purchase order' => 'purchase_orders', 'purchase request' => 'purchase_requests', 'quotation' => 'quotations', 'stock opname' => 'stock_opnames'] as $label => $tabel) {
        if (DB::table($tabel)->where('id_user_pembuat', $id)->exists()) {
            $dokumen[] = $label;
        }
    }
    if ($dokumen !== []) {
        return back()->with('error', 'Akun ini tercatat sebagai pembuat ' . implode(', ', $dokumen) . ' dan tidak bisa dihapus (dokumennya ikut terhapus). Ganti role/nonaktifkan akunnya saja.');
    }

    $user->delete();
    \App\Services\AuditLogger::log('deleted', 'users', (int) $id, ['username' => $user->username, 'id_role' => (int) $user->id_role], null, 'Akun user dihapus');

    return back()->with('success', 'Akun user berhasil dihapus!');
}

    /**
     * Upload foto profil sendiri (real-time). Menyimpan file di
     * storage/app/public/profil/ dan mencatat nama file di users.foto_profil.
     * Mengembalikan URL yang bisa langsung dipakai untuk preview.
     */
    public function uploadFoto(Request $request)
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $user = Auth::user();
        $disk = Storage::disk('public');

        if ($user->foto_profil) {
            $disk->delete('profil/' . $user->foto_profil);
        }

        // Ekstensi disimpan dari MIME yang SUDAH tervalidasi Laravel, bukan
        // dari nama asal klien (sebelumnya PNG poliglot bisa tersimpan
        // sebagai .php). Pola sama dengan LandingPageBuilderController.
        $ext = match ($request->file('foto')->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $nama = 'u' . $user->id_user . '_' . time() . '.' . $ext;
        $request->file('foto')->storeAs('profil', $nama, 'public');

        $user->foto_profil = $nama;
        $user->save();

        return response()->json([
            'ok' => true,
            'url' => url('/storage/profil/' . $nama),
            'nama' => $nama,
        ]);
    }

    /**
     * Hapus foto profil sendiri (kembali ke inisial).
     */
    public function hapusFoto()
    {
        $user = Auth::user();
        $disk = Storage::disk('public');
        if ($user->foto_profil) {
            $disk->delete('profil/' . $user->foto_profil);
            $user->foto_profil = null;
            $user->save();
        }
        return response()->json(['ok' => true]);
    }
}