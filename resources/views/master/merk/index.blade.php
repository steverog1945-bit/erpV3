@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">
    <x-page-header title="Master Merk" subtitle="Klasifikasi merk/brand bahan baku.">
        <x-slot:actions>
            <x-button onclick="toggleModal('merk-tambah')">
                <i class="fas fa-tag"></i>
                Tambah Merk
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER & SEARCH --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-8">
        <form method="GET" action="{{ route('merk.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[260px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Merk</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full bg-slate-50 border-none rounded-xl py-3 pl-12 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 shadow-inner"
                           placeholder="Ketik nama merk...">
                </div>
            </div>
            <button type="submit" class="h-[38px] px-6 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700">Filter</button>
            <a href="{{ route('merk.index') }}" class="h-[38px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all" title="Reset Filter">
                <i class="fas fa-undo-alt text-sm"></i>
            </a>
        </form>
    </div>

    {{-- TABLE CONTAINER --}}
    <x-table>
        <thead>
            <tr>
                        <th class="px-4 py-5 text-center w-10">#</th>
                        <th class="px-8 py-5 text-left w-24">ID</th>
                        <th class="px-6 py-5 text-left">Nama Merk</th>
                        <th class="px-6 py-5 text-left">Keterangan</th>
                        <th class="px-8 py-5 text-right">Manajemen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($merk as $i => $m)
                    <tr class="group hover:bg-slate-50/30 transition-all">
                        <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $merk->firstItem() + $i }}</td>
                        <td class="px-8 py-5 text-xs text-slate-400">#{{ $m->id_merk }}</td>
                        <td class="px-6 py-5">
                            <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200 uppercase">
                                {{ $m->nama_merk }}
                            </span>
                        </td>
                        <td class="px-6 py-5">
                            <p class="text-sm text-slate-500 leading-relaxed truncate max-w-xs">
                                {{ $m->keterangan ?? 'Tidak ada deskripsi tambahan' }}
                            </p>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <div class="flex justify-end gap-2">
                                <button onclick="openEditMerk({{ json_encode($m) }})"
                                        class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-all" aria-label="Ubah" title="Ubah">
                                    <i class="far fa-edit text-sm"></i>
                                </button>

                                <form id="delete-form-merk-{{ $m->id_merk }}" action="{{ route('merk.destroy', $m->id_merk) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                </form>
                                <button type="button" onclick="confirmDeleteMerk('{{ $m->id_merk }}', {{ json_encode($m->nama_merk) }})"
                                        class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all">
                                    <i class="far fa-trash-alt text-sm"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-20 text-center text-slate-400 font-medium italic">Belum ada merk yang terdaftar.</td>
                    </tr>
                    @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$merk" />
</div>

{{-- MODAL TAMBAH

     Bentuk badannya mengikuti modal lain: tanpa max-w + mx-auto, tanpa
     padding sendiri. Sebelumnya tiap field dibungkus max-w-[85%]
     mx-auto, jadi isinya sempit dan bergeser ke tengah - tepi kirinya
     tidak rata dengan modal lain yang tidak pakai pembungkus itu.
     Padding p-5 sudah ada di <x-modal>, dan jarak antar field
     diambil dari komponen form, jadi tidak perlu tambahan apa pun.
--}}
<x-modal id="merk-tambah" title="Tambah Merek"
         subtitle="Merek yang dipakai pada registrasi bahan baku"
         icon="fa-tag" maxWidth="md">
    <form action="{{ route('merk.store') }}" method="POST" class="space-y-4">
        @csrf
        <div class="ld">
            <input id="ld_nama_merk_0" type="text" name="nama_merk" placeholder="Contoh: 3M, Fujifilm" required
                   class="w-full text-sm font-medium text-slate-700">
            <label class="ld-label" for="ld_nama_merk_0">Nama Merk<span class="ld-wajib" aria-hidden="true">*</span></label>
        </div>

        <div class="ld">
            <textarea id="ld_keterangan_1" name="keterangan" placeholder="Keterangan merk..."
                      class="w-full text-sm font-medium text-slate-700"></textarea>
            <label class="ld-label" for="ld_keterangan_1">Keterangan Singkat</label>
        </div>

        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="toggleModal('merk-tambah')">Batal</x-button>
            <x-button type="submit">Simpan Merek</x-button>
        </x-slot:footer>
    </form>
</x-modal>

{{-- MODAL EDIT --}}
<x-modal id="merk-edit" title="Edit Merek"
         subtitle="Perubahan berlaku untuk bahan yang memakai merek ini"
         icon="fa-pen" maxWidth="md">
    <form id="formEditMerk" method="POST" class="space-y-4">
        @csrf @method('PUT')
        <div class="ld">
            <input placeholder=" " type="text" name="nama_merk" id="edit_nama_merk" required
                   class="w-full text-sm font-medium text-slate-700">
            <label class="ld-label" for="edit_nama_merk">Nama Merk<span class="ld-wajib" aria-hidden="true">*</span></label>
        </div>

        <div class="ld">
            <textarea placeholder=" " name="keterangan" id="edit_keterangan"
                      class="w-full text-sm font-medium text-slate-700"></textarea>
            <label class="ld-label" for="edit_keterangan">Keterangan Singkat</label>
        </div>

        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="toggleModal('merk-edit')">Batal</x-button>
            <x-button type="submit">Simpan Perubahan</x-button>
        </x-slot:footer>
    </form>
</x-modal>
<script>

    function openEditMerk(data) {
        const form = document.getElementById('formEditMerk');
        form.action = `{{ url('/merk') }}/${data.id_merk}`;
        document.getElementById('edit_nama_merk').value = data.nama_merk;
        document.getElementById('edit_keterangan').value = data.keterangan || '';
        toggleModal('merk-edit');
    }

    function confirmDeleteMerk(id, nama) {
        Swal.fire({
            title: 'Hapus Merk?',
            html: `Merk <b>${escHtml(nama)}</b> akan dihapus permanen.`,
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
                document.getElementById(`delete-form-merk-${id}`).submit();
            }
        });
    }
</script>
@endsection
