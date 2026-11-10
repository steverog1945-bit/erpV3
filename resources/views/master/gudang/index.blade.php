@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">
    <x-page-header title="Master Gudang" subtitle="Lokasi penyimpanan bahan baku dan penanggung jawabnya.">
        <x-slot:actions>
            <x-button onclick="toggleModal('gudang-tambah')">
                <i class="fas fa-warehouse"></i>
                Tambah Gudang
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER & SEARCH --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-8">
        <form method="GET" action="{{ route('gudang.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[260px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Gudang</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full bg-white border border-slate-200 rounded-md py-2 pl-12 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "
                           placeholder="Ketik nama gudang...">
                </div>
            </div>
            <button type="submit" class="h-[38px] px-6 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700">Filter</button>
            <a href="{{ route('gudang.index') }}" class="h-[38px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all" title="Reset Filter">
                <i class="fas fa-undo-alt text-sm"></i>
            </a>
        </form>
    </div>

    {{-- TABLE CONTAINER --}}
    <x-table>
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-8 py-5 text-left">Nama Gudang</th>
                <th class="px-6 py-5 text-left">Alamat</th>
                <th class="px-6 py-5 text-left">Penanggung Jawab</th>
                <th class="px-6 py-5 text-left">Telepon</th>
                <th class="px-8 py-5 text-right">Manajemen</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($gudang as $i => $g)
            <tr class="group hover:bg-slate-50/30 transition-all">
                <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $gudang->firstItem() + $i }}</td>
                <td class="px-8 py-5">
                    <span class="text-sm font-medium text-slate-700 uppercase">{{ $g->nama_gudang }}</span>
                    <div class="text-[10px] text-slate-400 mt-0.5">ID: #{{ $g->id_gudang }}</div>
                </td>
                <td class="px-6 py-5">
                    <p class="text-sm text-slate-500 leading-relaxed truncate max-w-xs">{{ $g->alamat ?? '-' }}</p>
                </td>
                <td class="px-6 py-5 text-sm text-slate-600 font-medium">{{ $g->penanggung_jawab ?? '-' }}</td>
                <td class="px-6 py-5 text-sm text-slate-500">{{ $g->telepon ?? '-' }}</td>
                <td class="px-8 py-5 text-right">
                    <div class="flex justify-end gap-2">
                        <button onclick="openEditGudang({{ json_encode($g) }})"
                                class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-all" aria-label="Ubah" title="Ubah">
                            <i class="far fa-edit text-sm"></i>
                        </button>

                        <form id="delete-form-gudang-{{ $g->id_gudang }}" action="{{ route('gudang.destroy', $g->id_gudang) }}" method="POST" class="inline">
                            @csrf @method('DELETE')
                        </form>
                        <button type="button" onclick="confirmDeleteGudang('{{ $g->id_gudang }}', {{ json_encode($g->nama_gudang) }})"
                                class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all">
                            <i class="far fa-trash-alt text-sm"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="py-20 text-center text-slate-400 font-medium italic">Belum ada gudang yang terdaftar.</td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$gudang" />
</div>

{{-- MODAL TAMBAH

     Tanpa py-10 sendiri dan tanpa max-w + mx-auto. Padding p-5 sudah
     ada di <x-modal>, dan pembungkus max-w-[85%] mx-auto membuat tepi
     kiri isinya tidak rata dengan modal lain.
--}}
<x-modal id="gudang-tambah" title="Tambah Gudang"
         subtitle="Lokasi penyimpanan bahan dan produk"
         icon="fa-warehouse" maxWidth="md">
    <form action="{{ route('gudang.store') }}" method="POST" class="space-y-4">
        @csrf
        <div class="ld">
            <input id="ld_nama_gudang_0" type="text" name="nama_gudang" placeholder="Contoh: Gudang Utama" required
                   class="w-full py-3.5 text-sm font-medium text-slate-700">
            <label class="ld-label" for="ld_nama_gudang_0">Nama Gudang<span class="ld-wajib" aria-hidden="true">*</span></label>
        </div>
        <div class="ld">
            <textarea id="ld_alamat_1" name="alamat" placeholder="Alamat gudang..."
                      class="w-full py-3.5 text-sm font-medium text-slate-700"></textarea>
            <label class="ld-label" for="ld_alamat_1">Alamat</label>
        </div>
        <div class="ld">
            <input id="ld_penanggung_jawab_2" type="text" name="penanggung_jawab" placeholder="Nama PIC gudang"
                   class="w-full py-3.5 text-sm font-medium text-slate-700">
            <label class="ld-label" for="ld_penanggung_jawab_2">Penanggung Jawab</label>
        </div>
        <div class="ld">
            <input id="ld_telepon_3" type="text" name="telepon" placeholder="08xxxxxxxxxx"
                   class="w-full py-3.5 text-sm font-medium text-slate-700">
            <label class="ld-label" for="ld_telepon_3">Telepon</label>
        </div>

        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="toggleModal('gudang-tambah')">Batal</x-button>
            <x-button type="submit">Simpan Gudang</x-button>
        </x-slot:footer>
    </form>
</x-modal>

{{-- MODAL EDIT --}}
<x-modal id="gudang-edit" title="Edit Gudang"
         subtitle="Perubahan berlaku untuk penerimaan bahan berikutnya"
         icon="fa-pen" maxWidth="md">
    <form id="formEditGudang" method="POST" class="space-y-4">
        @csrf @method('PUT')
        <div class="ld">
            <input placeholder=" " type="text" name="nama_gudang" id="edit_nama_gudang" required
                   class="w-full py-3.5 text-sm font-medium text-slate-700">
            <label class="ld-label" for="edit_nama_gudang">Nama Gudang<span class="ld-wajib" aria-hidden="true">*</span></label>
        </div>
        <div class="ld">
            <textarea placeholder=" " name="alamat" id="edit_alamat"
                      class="w-full py-3.5 text-sm font-medium text-slate-700"></textarea>
            <label class="ld-label" for="edit_alamat">Alamat</label>
        </div>
        <div class="ld">
            <input placeholder=" " type="text" name="penanggung_jawab" id="edit_penanggung_jawab"
                   class="w-full py-3.5 text-sm font-medium text-slate-700">
            <label class="ld-label" for="edit_penanggung_jawab">Penanggung Jawab</label>
        </div>
        <div class="ld">
            <input placeholder=" " type="text" name="telepon" id="edit_telepon"
                   class="w-full py-3.5 text-sm font-medium text-slate-700">
            <label class="ld-label" for="edit_telepon">Telepon</label>
        </div>

        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="toggleModal('gudang-edit')">Batal</x-button>
            <x-button type="submit">Simpan Perubahan</x-button>
        </x-slot:footer>
    </form>
</x-modal>
<script>

    function openEditGudang(data) {
        const form = document.getElementById('formEditGudang');
        form.action = `{{ url('/gudang') }}/${data.id_gudang}`;
        document.getElementById('edit_nama_gudang').value = data.nama_gudang;
        document.getElementById('edit_alamat').value = data.alamat || '';
        document.getElementById('edit_penanggung_jawab').value = data.penanggung_jawab || '';
        document.getElementById('edit_telepon').value = data.telepon || '';
        toggleModal('gudang-edit');
    }

    function confirmDeleteGudang(id, nama) {
        Swal.fire({
            title: 'Hapus Gudang?',
            html: `Gudang <b>${escHtml(nama)}</b> akan dihapus permanen.`,
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
                document.getElementById(`delete-form-gudang-${id}`).submit();
            }
        });
    }
</script>
@endsection
