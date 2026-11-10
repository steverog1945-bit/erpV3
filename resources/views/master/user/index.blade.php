@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Manajemen Pengguna" subtitle="Kelola akun login dan sinkronisasi hak akses karyawan.">
        <x-slot:actions>
            <x-button onclick="toggleModal('user-tambah')">
                <i class="fas fa-user-plus"></i>
                Buat Akun User
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-table>
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-6 py-5 text-left">Username</th>
                <th class="px-4 py-5 text-left">Nama Karyawan</th>
                <th class="px-4 py-5 text-left">Role / Akses</th>
                <th class="px-8 py-5 text-right">Keamanan</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @foreach($users as $i => $u)
            <tr class="hover:bg-slate-50/50 transition-all">
                <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $i + 1 }}</td>
                <td class="px-6 py-5">
                    <span class="text-[13px] text-indigo-600 font-medium">{{ '@' . $u->username }}</span>
                </td>
                <td class="px-4 py-5">
                    <div class="text-[13px] font-medium text-slate-700 uppercase">{{ $u->karyawan->nama_karyawan }}</div>
                    <div class="text-[10px] text-slate-400">{{ $u->karyawan->bagian }}</div>
                </td>
                <td class="px-4 py-5">
                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-[10px] font-medium uppercase tracking-wider bg-slate-100 text-slate-600 border border-slate-200">
                        <i class="fas fa-shield-alt mr-1.5"></i> {{ $u->role->nama_role }}
                    </span>
                </td>
                <td class="px-8 py-5 text-right">
                    <div class="flex justify-end gap-2">
                        @if(optional(auth()->user()->role)->nama_role === 'Administrator')
                        <x-button href="{{ route('akses.user', $u->id_user) }}" size="icon" variant="ghost" class="border border-slate-100 hover:text-indigo-600" title="Hak Akses per modul (lihat/tambah/kelola/hapus)">
                            <i class="fas fa-user-lock text-sm"></i>
                        </x-button>
                        @endif
                        {{-- SEC-USER-001/002: payload edit hanya 3 field yang benar-benar dipakai
                             openEditUser + flag JSON_HEX_* supaya JSON tidak pernah
                             mengandung ' < > & di dalam nilai (kutip ganda dari
                             json_encode tetap di-escape e() saat render).
                             DELIBERAT: atribut double-quoted - kunci array memakai
                             single-quote, dan parser tag komponen Blade menggalat nilai
                             pada quote - delimiter ganda itu yang membuat tag valid.
                             JANGAN diganti @json mentah (unescaped). --}}
                        <x-button type="button" size="icon" variant="ghost" onclick="openEditUser({{ json_encode(['id_user' => $u->id_user, 'username' => $u->username, 'id_role' => $u->id_role], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }})" class="border border-slate-100 hover:text-amber-500" title="Edit">
                            <i class="far fa-edit text-sm"></i>
                        </x-button>
                        <form action="{{ url('/users/delete/'.$u->id_user) }}" method="POST" class="inline" id="delete-form-user-{{ $u->id_user }}">
                            @csrf @method('DELETE')
                            {{-- SEC-USER-002: id selalu int, username di-encode JSON_HEX_* -
                                 apostrof/quote di username tidak lagi bisa memutus string JS
                                 di dalam atribut HTML (dahulu: username dimasukkan mentah). --}}
                            <x-button type="button" size="icon" variant="ghost" onclick="confirmDeleteUser({{ (int) $u->id_user }}, {{ json_encode($u->username, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }})" class="border border-slate-100 hover:text-rose-500" title="Hapus">
                                <i class="far fa-trash-alt text-sm"></i>
                            </x-button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </x-table>

</div>

<x-modal id="user-tambah" title="Tambah Pengguna"
         subtitle="Akun login beserta bagian dan akses menu"
         icon="fa-user-gear" maxWidth="md">
<form action="{{ route('users.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="ld">
                <select name="id_karyawan" id="pilih_karyawan_baru" onchange="autoPilihRole(this, 'id_role_baru')" class="w-full border-slate-200 px-4 outline-none focus:ring-indigo-500 transition" required>
                    <option value="">-- Pilih Nama Karyawan --</option>
                    @foreach($karyawan as $k)
                        <option value="{{ $k->id_karyawan }}" data-bagian="{{ $k->bagian }}">{{ $k->nama_karyawan }} ({{ $k->bagian }})</option>
                    @endforeach
                </select>
                <label class="ld-label" for="pilih_karyawan_baru">Tautkan Karyawan<span class="ld-wajib" aria-hidden="true">*</span></label>
            </div>

            <div class="grid grid-cols-1 gap-4">
                <div class="ld">
                    <input id="ld_username_1" type="text" name="username" class="w-full border-slate-200 px-4 outline-none focus:ring-indigo-500 transition" placeholder="contoh: admin_cetak" required>
                    <label class="ld-label" for="ld_username_1">Username<span class="ld-wajib" aria-hidden="true">*</span></label>
                </div>
                <div class="ld">
                    <input id="ld_password_2" type="password" name="password" minlength="8" class="w-full border-slate-200 px-4 outline-none focus:ring-indigo-500 transition" placeholder="••••••••" required>
                    <label class="ld-label" for="ld_password_2">Password Awal<span class="ld-wajib" aria-hidden="true">*</span></label>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Role / Tingkat Akses</label>
                <select name="id_role" id="id_role_baru" class="w-full border-slate-200 px-4 outline-none focus:ring-indigo-500 transition" required>
                    @foreach($roles as $r)
                        <option value="{{ $r->id_role }}" data-nama-role="{{ $r->nama_role }}">{{ $r->nama_role }}</option>
                    @endforeach
                </select>
                <p class="text-[11px] mt-1.5">Otomatis tersaran sesuai bagian karyawan - bisa diubah manual bila perlu.</p>
            </div>

            <div class="flex justify-end gap-3 mt-8">
                <x-button type="button" variant="secondary" onclick="toggleModal('user-tambah')">Batal</x-button>
                <x-button type="submit">Aktifkan Akun</x-button>
            </div>
        </form>
</x-modal>
{{-- MODAL EDIT: dulunya tombol ini cuma bertuliskan "Reset Password" tanpa
     onclick apapun - UserController::update() sudah ada dan bekerja (bisa
     ganti username/role, dan password kalau diisi), tapi tidak ada satupun
     elemen UI yang memanggilnya. --}}
<x-modal id="user-edit" title="Edit Pengguna"
         subtitle="Perubahan akses berlaku setelah pengguna logout"
         icon="fa-pen" maxWidth="md">
<form id="formEditUser" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="ld">
                <input placeholder=" " type="text" name="username" id="edit_username" class="w-full border-slate-200 px-4 outline-none focus:ring-amber-500 transition" required>
                <label class="ld-label" for="edit_username">Username<span class="ld-wajib" aria-hidden="true">*</span></label>
            </div>

            <div class="ld">
                <select name="id_role" id="edit_id_role" class="w-full border-slate-200 px-4 outline-none focus:ring-amber-500 transition" required>
                    @foreach($roles as $r)
                        <option value="{{ $r->id_role }}">{{ $r->nama_role }}</option>
                    @endforeach
                </select>
                <label class="ld-label" for="edit_id_role">Role / Tingkat Akses<span class="ld-wajib" aria-hidden="true">*</span></label>
            </div>

            <div class="ld">
                <input id="ld_password_5" type="password" name="password" minlength="8" class="w-full border-slate-200 px-4 outline-none focus:ring-amber-500 transition" placeholder="Kosongkan jika tidak diubah">
                <label class="ld-label" for="ld_password_5">Password Baru (opsional)</label>
            </div>

            <div class="flex justify-end gap-3 mt-8">
                <x-button type="button" variant="secondary" onclick="toggleModal('user-edit')">Batal</x-button>
                <x-button type="submit">Simpan Perubahan</x-button>
            </div>
        </form>
</x-modal>
<script>
    const BAGIAN_ROLE_MAP = @json($bagianRoleMap);

    // Auto-pilih role begitu karyawan dipilih, mengikuti pemetaan
    // bagian->role yang dibakukan (lihat UserController::BAGIAN_ROLE_MAP).
    // Tetap bisa diubah manual sesudahnya kalau memang ada kasus khusus -
    // ini cuma saran awal, bukan penguncian.
    function autoPilihRole(karyawanSelect, roleSelectId) {
        const bagian = karyawanSelect.selectedOptions[0]?.dataset.bagian;
        const namaRoleDisaran = BAGIAN_ROLE_MAP[bagian];
        if (!namaRoleDisaran) return;

        const roleSelect = document.getElementById(roleSelectId);
        for (const opt of roleSelect.options) {
            if (opt.dataset.namaRole === namaRoleDisaran) {
                roleSelect.value = opt.value;
                break;
            }
        }
    }

    function openEditUser(user) {
        document.getElementById('formEditUser').action = `{{ url('/users/update') }}/${user.id_user}`;
        document.getElementById('edit_username').value = user.username;
        document.getElementById('edit_id_role').value = user.id_role;
        toggleModal('user-edit');
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function confirmDeleteUser(id, username) {
        Swal.fire({
            title: 'Hapus Akun User?',
            html: `Menghapus akun <b>@${escapeHtml(username)}</b>? Karyawan terkait tidak akan bisa login lagi sampai dibuatkan akun baru.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            cancelButtonColor: '#F1F5F1',
            cancelButtonText: '<span style="color: #5D6A85">Batal</span>',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete-form-user-' + id).submit();
            }
        });
    }
</script>
@endsection