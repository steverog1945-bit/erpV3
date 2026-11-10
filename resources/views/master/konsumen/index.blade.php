@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Database Konsumen" subtitle="Kelola pelanggan tetap dan temporary percetakan Anda.">
        <x-slot:actions>
            <x-button onclick="openModal('tambah-konsumen')">
                <i class="fas fa-user-plus"></i>
                Konsumen Baru
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER & SEARCH --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-8">
        <form method="GET" action="{{ route('konsumen.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[260px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Konsumen</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full bg-white border border-slate-200 rounded-md py-2 pl-12 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "
                           placeholder="Ketik nama atau no. telepon...">
                </div>
            </div>
            <div class="w-full md:w-56">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Golongan</label>
                <select name="id_golongan" class="select2-filter w-full">
                    <option value="">Semua Golongan</option>
                    @foreach($golongan as $g)
                        <option value="{{ $g->id_golongan }}" {{ request('id_golongan') == $g->id_golongan ? 'selected' : '' }}>{{ $g->nama_golongan }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="h-[38px] px-6 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700">Filter</button>
            <a href="{{ route('konsumen.index') }}" class="h-[38px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all" title="Reset Filter">
                <i class="fas fa-undo-alt text-sm"></i>
            </a>
        </form>
    </div>

    <x-table>
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-8 py-5 text-left">Profil Pelanggan</th>
                <th class="px-6 py-5 text-left">Kontak & Alamat</th>
                <th class="px-6 py-5 text-center">Golongan</th>
                <th class="px-6 py-5 text-center">Tipe</th>
                <th class="px-8 py-5 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($konsumen as $i => $k)
            <tr class="group hover:bg-slate-50/30 transition-all">
                <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $konsumen->firstItem() + $i }}</td>
                <td class="px-8 py-5">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600 font-medium text-xs">
                            {{ substr($k->nama_konsumen, 0, 1) }}
                        </div>
                        <div>
                            <div class="text-[14px] font-medium text-slate-700 group-hover:text-indigo-600 transition-colors uppercase">
                                {{ $k->nama_konsumen }}
                            </div>
                            <div class="text-[11px] text-slate-400 mt-0.5">ID: #CST-{{ $k->id_konsumen }}</div>
                        </div>
                    </div>
                </td>

                <td class="px-6 py-5 text-left">
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-indigo-600 mb-1"><i class="fab fa-whatsapp mr-1"></i> {{ $k->telepon }}</span>
                        <span class="text-[11px] text-slate-400 truncate max-w-[200px]">{{ $k->alamat ?? 'Tanpa alamat' }}</span>
                    </div>
                </td>

                <td class="px-6 py-5 text-center">
                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-[10px] font-medium uppercase tracking-wider bg-slate-100 text-slate-600 border border-slate-200">
                        {{ $k->nama_golongan }}
                    </span>
                </td>

                <td class="px-6 py-5 text-center">
                    @if($k->tipe_konsumen == 'permanent')
                        <x-badge variant="success">Member</x-badge>
                    @else
                        <x-badge variant="info">Guest</x-badge>
                    @endif
                </td>

                <td class="px-8 py-5 text-right">
                    <div class="flex justify-end gap-2">
                        <x-button href="{{ route('penjualan.riwayat', ['id_konsumen' => $k->id_konsumen]) }}" size="icon" variant="ghost" class="hover:text-indigo-500 hover:bg-indigo-50" title="Riwayat Transaksi">
                            <i class="fas fa-history text-sm"></i>
                        </x-button>
                        <x-button href="{{ route('konsumen.timeline', $k->id_konsumen) }}" size="icon" variant="ghost" class="hover:text-emerald-500 hover:bg-emerald-50" title="Timeline Pelanggan">
                            <i class="fas fa-stream text-sm"></i>
                        </x-button>
                        <x-button size="icon" variant="ghost" onclick="openEditKonsumen({{ json_encode($k) }})" class="hover:text-amber-500 hover:bg-amber-50" aria-label="Ubah" title="Ubah">
                            <i class="far fa-edit text-sm"></i>
                        </x-button>

                        <form action="{{ route('konsumen.destroy', $k->id_konsumen) }}" method="POST" class="inline" id="delete-form-konsumen-{{ $k->id_konsumen }}">
                            @csrf @method('DELETE')
                            <x-button type="button" size="icon" variant="ghost" onclick="confirmDeleteKonsumen('{{ $k->id_konsumen }}', '{{ $k->nama_konsumen }}')" class="hover:text-rose-500 hover:bg-rose-50">
                                <i class="far fa-trash-alt text-sm"></i>
                            </x-button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6">
                    <x-empty-state title="Belum ada konsumen yang terdaftar" />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$konsumen" />

    {{-- MODAL TAMBAH --}}
    <x-modal id="tambah-konsumen" title="Input Konsumen Baru" maxWidth="md">
        <form action="{{ route('konsumen.store') }}" method="POST" class="space-y-5">
            @csrf
            @include('master.konsumen._field', ['awalan' => ''])

            <div class="pt-4 flex gap-3">
                <x-button type="button" variant="secondary" onclick="closeModal('tambah-konsumen')" class="flex-1">Batalkan</x-button>
                <x-button type="submit" class="flex-1">Simpan Data</x-button>
            </div>
        </form>
    </x-modal>

    {{-- MODAL EDIT --}}
    <x-modal id="edit-konsumen" title="Perbarui Data Konsumen" maxWidth="md">
        <form id="formEditKonsumen" method="POST" class="space-y-5">
            @csrf @method('PUT')
            @include('master.konsumen._field', ['awalan' => 'edit_'])

            <div class="pt-4 flex gap-3">
                <x-button type="button" variant="secondary" onclick="closeModal('edit-konsumen')" class="flex-1">Batalkan</x-button>
                <x-button type="submit" variant="warning" class="flex-1">Simpan Perubahan</x-button>
            </div>
        </form>
    </x-modal>

</div>

@push('scripts')
<script>
    $(document).ready(function() {
        $('.select2-filter').select2({ width: '100%' }).on('change', function() {
            $(this).closest('form').submit();
        });
    });

    function openEditKonsumen(data) {
        const form = document.getElementById('formEditKonsumen');
        form.action = `{{ url('/konsumen') }}/${data.id_konsumen}`;

        document.getElementById('edit_nama').value = data.nama_konsumen;
        document.getElementById('edit_telepon').value = data.telepon;
        document.getElementById('edit_golongan').value = data.id_golongan;
        document.getElementById('edit_tipe_konsumen').value = data.tipe_konsumen || 'temporary';
        document.getElementById('edit_alamat').value = data.alamat || '';

        openModal('edit-konsumen');
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function confirmDeleteKonsumen(id, name) {
        Swal.fire({
            title: 'Hapus Konsumen?',
            html: `Menghapus <b>${escapeHtml(name)}</b>? Data yang dihapus tidak dapat dikembalikan.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            cancelButtonColor: '#F1F5F1',
            cancelButtonText: '<span style="color: #5D6A85">Batal</span>',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete-form-konsumen-' + id).submit();
            }
        });
    }
</script>
@endpush
@endsection
