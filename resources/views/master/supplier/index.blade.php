@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Daftar Pemasok" subtitle="Kelola vendor penyedia bahan baku percetakan Anda.">
        <x-slot:actions>
            <x-button onclick="openModal('tambah-supplier')">
                <i class="fas fa-truck-loading"></i>
                Tambah Supplier
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER & SEARCH --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-8">
        <form method="GET" action="{{ route('supplier.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-72 lg:w-80">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Supplier</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full bg-white border border-slate-200 rounded-md py-2 pl-12 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "
                           placeholder="Ketik nama atau kontak...">
                </div>
            </div>
            <button type="submit" class="h-[38px] px-6 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700">Filter</button>
            <a href="{{ route('supplier.index') }}" class="h-[38px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all" title="Reset Filter">
                <i class="fas fa-undo-alt text-sm"></i>
            </a>
        </form>
    </div>

    <x-table>
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-8 py-5 text-left">Nama Perusahaan</th>
                <th class="px-6 py-5 text-left">Kontak Sales</th>
                <th class="px-6 py-5 text-left">Alamat Kantor</th>
                <th class="px-8 py-5 text-right">Manajemen</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($supplier as $i => $s)
            <tr class="group hover:bg-slate-50/30 transition-all">
                <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $supplier->firstItem() + $i }}</td>
                <td class="px-8 py-5">
                    <div class="text-[14px] font-medium text-slate-700 group-hover:text-indigo-600 transition-colors uppercase">
                        {{ $s->nama_supplier }}
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">SUP-ID: #{{ str_pad($s->id_supplier, 4, '0', STR_PAD_LEFT) }}</div>
                </td>
                <td class="px-6 py-5">
                    <span class="text-sm font-medium text-indigo-600">
                        <i class="fab fa-whatsapp mr-1 text-xs"></i> {{ $s->kontak }}
                    </span>
                </td>
                <td class="px-6 py-5">
                    <p class="text-sm text-slate-500 leading-relaxed truncate max-w-xs">
                        {{ $s->alamat ?? 'Lokasi tidak terdaftar' }}
                    </p>
                </td>
                <td class="px-8 py-5 text-right">
                    <div class="flex justify-end gap-2">
                        <x-button href="{{ route('kedatangan-bahan.index', ['search' => $s->nama_supplier]) }}" size="icon" variant="ghost" class="hover:text-indigo-500 hover:bg-indigo-50" title="Riwayat Kedatangan Bahan">
                            <i class="fas fa-history text-sm"></i>
                        </x-button>
                        <x-button size="icon" variant="ghost" onclick="openEditSupplier({{ json_encode($s) }})" class="hover:text-amber-500 hover:bg-amber-50" aria-label="Ubah" title="Ubah">
                            <i class="far fa-edit text-sm"></i>
                        </x-button>

                        <form action="{{ route('supplier.destroy', $s->id_supplier) }}" method="POST" class="inline"
                              data-konfirmasi="Hapus pemasok {{ $s->nama_supplier }}? Data yang dihapus tidak bisa dikembalikan.">
                            @csrf @method('DELETE')
                            <x-button type="submit" size="icon" variant="ghost" class="hover:text-rose-500 hover:bg-rose-50" aria-label="Hapus" title="Hapus">
                                <i class="far fa-trash-alt text-sm"></i>
                            </x-button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5">
                    <x-empty-state title="Belum ada supplier terdaftar" />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$supplier" />

    {{-- MODAL TAMBAH --}}
    <x-modal id="tambah-supplier" title="Input Pemasok Baru" maxWidth="md">
        <form action="{{ route('supplier.store') }}" method="POST" class="space-y-5">
            @csrf
            <x-form.group label="Nama Perusahaan/Vendor" name="nama_supplier" required dalam>
                <x-form.input type="text" name="nama_supplier" placeholder="Contoh: PT. Sumber Kertas" required />
            </x-form.group>

            <x-form.group label="No. Telepon / Sales" name="kontak" required dalam>
                <x-form.input type="text" name="kontak" placeholder="08xx xxxx xxxx" required />
            </x-form.group>

            <x-form.group label="Alamat Lengkap" name="alamat" dalam>
                <textarea name="alamat" placeholder="Jl. Raya Percetakan No. 1..."
                          class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "></textarea>
            </x-form.group>

            <div class="pt-4 flex gap-3">
                <x-button type="button" variant="secondary" onclick="closeModal('tambah-supplier')" class="flex-1">Batalkan</x-button>
                <x-button type="submit" class="flex-1">Simpan Supplier</x-button>
            </div>
        </form>
    </x-modal>

    {{-- MODAL EDIT --}}
    <x-modal id="edit-supplier" title="Perbarui Data Pemasok" maxWidth="md">
        <form id="formEditSupplier" method="POST" class="space-y-5">
            @csrf @method('PUT')
            <x-form.group label="Nama Perusahaan/Vendor" dalam>
                <x-form.input type="text" name="nama_supplier" id="edit_nama_supplier" required />
            </x-form.group>

            <x-form.group label="No. Telepon / Sales" dalam>
                <x-form.input type="text" name="kontak" id="edit_kontak" required />
            </x-form.group>

            <x-form.group label="Alamat Lengkap" dalam>
                <textarea name="alamat" id="edit_alamat"
                          class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "></textarea>
            </x-form.group>

            <div class="pt-4 flex gap-3">
                <x-button type="button" variant="secondary" onclick="closeModal('edit-supplier')" class="flex-1">Kembali</x-button>
                <x-button type="submit" variant="warning" class="flex-1">Simpan Perubahan</x-button>
            </div>
        </form>
    </x-modal>

</div>

@push('scripts')
<script>
    function openEditSupplier(data) {
        const form = document.getElementById('formEditSupplier');
        form.action = `{{ url('/supplier') }}/${data.id_supplier}`;
        document.getElementById('edit_nama_supplier').value = data.nama_supplier;
        document.getElementById('edit_kontak').value = data.kontak;
        document.getElementById('edit_alamat').value = data.alamat || '';
        openModal('edit-supplier');
    }
</script>
@endpush
@endsection

