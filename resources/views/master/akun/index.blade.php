@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">
    <x-page-header title="Daftar Akun" subtitle="Daftar akun (bagan akun) yang dipakai untuk mencatat semua jurnal transaksi.">
        <x-slot:actions>
            <a href="{{ route('akuntansi.jurnal') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-2xl text-[11px] font-medium uppercase tracking-widest text-slate-500 border border-slate-200 hover:bg-slate-50 transition-all">
                <i class="fas fa-book"></i> Lihat Jurnal
            </a>
            <x-button onclick="toggleModal('akun-tambah')">
                <i class="fas fa-plus"></i>
                Tambah Akun
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @php
        $urutanTipe = ['Aset', 'Kewajiban', 'Modal', 'Pendapatan', 'Beban'];
        $warnaTipe = [
            'Aset'       => 'bg-sky-50 text-sky-700 border-sky-100',
            'Kewajiban'  => 'bg-rose-50 text-rose-700 border-rose-100',
            'Modal'      => 'bg-violet-50 text-violet-700 border-violet-100',
            'Pendapatan' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
            'Beban'      => 'bg-amber-50 text-amber-700 border-amber-100',
        ];
        $grouped = $akun->groupBy('tipe_akun');
    @endphp

    <div class="space-y-8">
        @foreach($urutanTipe as $tipe)
            @if($grouped->has($tipe))
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-8 py-5 border-b border-slate-100 flex items-center gap-3">
                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium border uppercase {{ $warnaTipe[$tipe] }}">{{ $tipe }}</span>
                    <span class="text-[11px] text-slate-400">{{ $grouped[$tipe]->count() }} akun</span>
                </div>
                <x-table bare>
                    <thead>
                        <tr>
                            <th class="px-8 py-4 text-left w-32">Kode Akun</th>
                            <th class="px-6 py-4 text-left">Nama Akun</th>
                            <th class="px-6 py-4 text-left w-32">Saldo Normal</th>
                            <th class="px-6 py-4 text-left w-28">Status</th>
                            <th class="px-8 py-4 text-right">Manajemen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($grouped[$tipe] as $a)
                        <tr class="group hover:bg-slate-50/30 transition-all {{ !$a->is_active ? 'opacity-50' : '' }}">
                            <td class="px-8 py-4 text-sm font-medium text-slate-600">{{ $a->kode_akun }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-slate-700">{{ $a->nama_akun }}</td>
                            <td class="px-6 py-4 text-xs text-slate-500">{{ $a->saldo_normal }}</td>
                            <td class="px-6 py-4">
                                @if($a->is_active)
                                    <span class="text-[10px] font-medium text-emerald-600 uppercase">Aktif</span>
                                @else
                                    <span class="text-[10px] font-medium text-slate-400 uppercase">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-8 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button onclick="openEditAkun({{ json_encode($a) }})"
                                            class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-all" aria-label="Ubah" title="Ubah">
                                        <i class="far fa-edit text-sm"></i>
                                    </button>
                                    <button type="button" onclick="confirmToggleAktif('{{ $a->id_akun }}', {{ json_encode($a->nama_akun) }}, {{ $a->is_active ? 'true' : 'false' }})" class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-indigo-500 hover:bg-indigo-50 rounded-lg transition-all" title="{{ $a->is_active ? 'Nonaktifkan' : 'Aktifkan' }}">
                                        <i class="fas {{ $a->is_active ? 'fa-toggle-on' : 'fa-toggle-off' }} text-sm"></i>
                                    </button>
                                    <button type="button" onclick="confirmDeleteAkun('{{ $a->id_akun }}', {{ json_encode($a->nama_akun) }})" class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all">
                                        <i class="far fa-trash-alt text-sm"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </x-table>
            </div>
            @endif
        @endforeach
    </div>
</div>

{{-- MODAL TAMBAH --}}
<x-modal id="akun-tambah" title="Tambah Akun"
         subtitle="Kode akun untuk jurnal, dipakai di semua transaksi keuangan"
         icon="fa-chart-pie" maxWidth="md">
<form action="{{ route('akun.store') }}" method="POST" class="space-y-4">
                @csrf
                <div class="ld">
                    <input id="ld_kode_akun_0" type="text" name="kode_akun" placeholder="Contoh: 1300" required
                           class="w-full text-sm font-medium text-slate-700">
                    <label class="ld-label" for="ld_kode_akun_0">Kode Akun<span class="ld-wajib" aria-hidden="true">*</span></label>
                </div>
                <div class="ld">
                    <input id="ld_nama_akun_1" type="text" name="nama_akun" placeholder="Contoh: Perlengkapan Kantor" required
                           class="w-full text-sm font-medium text-slate-700">
                    <label class="ld-label" for="ld_nama_akun_1">Nama Akun<span class="ld-wajib" aria-hidden="true">*</span></label>
                </div>
                <div class="ld">
                    <select id="ld_tipe_akun_2" name="tipe_akun" required class="w-full text-sm font-medium text-slate-700">
                        <option value="Aset">Aset</option>
                        <option value="Kewajiban">Kewajiban</option>
                        <option value="Modal">Modal</option>
                        <option value="Pendapatan">Pendapatan</option>
                        <option value="Beban">Beban</option>
                    </select>
                    <label class="ld-label" for="ld_tipe_akun_2">Tipe Akun<span class="ld-wajib" aria-hidden="true">*</span></label>
                </div>
                <div class="ld">
                    <select id="ld_saldo_normal_3" name="saldo_normal" required class="w-full text-sm font-medium text-slate-700">
                        <option value="Debet">Debet</option>
                        <option value="Kredit">Kredit</option>
                    </select>
                    <label class="ld-label" for="ld_saldo_normal_3">Saldo Normal<span class="ld-wajib" aria-hidden="true">*</span></label>
                </div>

                <x-slot:footer>
                    <x-button type="button" variant="secondary" onclick="toggleModal('akun-tambah')">Batal</x-button>
                    <x-button type="submit">Simpan Akun</x-button>
                </x-slot:footer>
            </form>
</x-modal>
{{-- MODAL EDIT --}}
<x-modal id="akun-edit" title="Edit Akun"
         subtitle="Perubahan berlaku untuk jurnal berikutnya"
         icon="fa-pen" maxWidth="md">
<form id="formEditAkun" method="POST" class="space-y-4">
                @csrf @method('PUT')
                <div class="ld">
                    <input placeholder=" " type="text" name="kode_akun" id="edit_kode_akun" required
                           class="w-full text-sm font-medium text-slate-700">
                    <label class="ld-label" for="edit_kode_akun">Kode Akun<span class="ld-wajib" aria-hidden="true">*</span></label>
                </div>
                <div class="ld">
                    <input placeholder=" " type="text" name="nama_akun" id="edit_nama_akun" required
                           class="w-full text-sm font-medium text-slate-700">
                    <label class="ld-label" for="edit_nama_akun">Nama Akun<span class="ld-wajib" aria-hidden="true">*</span></label>
                </div>
                <div class="ld">
                    <select name="tipe_akun" id="edit_tipe_akun" required class="w-full text-sm font-medium text-slate-700">
                        <option value="Aset">Aset</option>
                        <option value="Kewajiban">Kewajiban</option>
                        <option value="Modal">Modal</option>
                        <option value="Pendapatan">Pendapatan</option>
                        <option value="Beban">Beban</option>
                    </select>
                    <label class="ld-label" for="edit_tipe_akun">Tipe Akun<span class="ld-wajib" aria-hidden="true">*</span></label>
                </div>
                <div class="ld">
                    <select name="saldo_normal" id="edit_saldo_normal" required class="w-full text-sm font-medium text-slate-700">
                        <option value="Debet">Debet</option>
                        <option value="Kredit">Kredit</option>
                    </select>
                    <label class="ld-label" for="edit_saldo_normal">Saldo Normal<span class="ld-wajib" aria-hidden="true">*</span></label>
                </div>

                <x-slot:footer>
                    <x-button type="button" variant="secondary" onclick="toggleModal('akun-edit')">Batal</x-button>
                    <x-button type="submit">Simpan Perubahan</x-button>
                </x-slot:footer>
            </form>
</x-modal>
<script>

    function openEditAkun(data) {
        const form = document.getElementById('formEditAkun');
        form.action = `{{ url('/akun') }}/${data.id_akun}`;
        document.getElementById('edit_kode_akun').value = data.kode_akun;
        document.getElementById('edit_nama_akun').value = data.nama_akun;
        document.getElementById('edit_tipe_akun').value = data.tipe_akun;
        document.getElementById('edit_saldo_normal').value = data.saldo_normal;
        toggleModal('akun-edit');
    }

    function submitForm(action, method) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = action;
        form.innerHTML = `@csrf @method('${method}')`;
        document.body.appendChild(form);
        form.submit();
    }

    function confirmToggleAktif(id, namaAkun, sedangAktif) {
        Swal.fire({
            title: sedangAktif ? 'Nonaktifkan Akun?' : 'Aktifkan Akun?',
            html: `Akun <b>${escHtml(namaAkun)}</b> akan di${sedangAktif ? 'nonaktifkan' : 'aktifkan'}.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#006C7B',
            cancelButtonColor: '#FAFBF9',
            confirmButtonText: 'Ya, Lanjutkan',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'rounded-lg px-6 py-2.5',
                cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                submitForm(`/akun/${id}/toggle-active`, 'PATCH');
            }
        });
    }

    function confirmDeleteAkun(id, namaAkun) {
        Swal.fire({
            title: 'Hapus Akun?',
            html: `Akun <b>${escHtml(namaAkun)}</b> akan dihapus permanen. Hanya bisa dihapus kalau belum pernah dipakai di jurnal.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            cancelButtonColor: '#FAFBF9',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'rounded-lg px-6 py-2.5',
                cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                submitForm(`/akun/${id}`, 'DELETE');
            }
        });
    }
</script>
@endsection
