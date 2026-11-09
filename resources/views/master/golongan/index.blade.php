@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">
    <x-page-header title="Master Golongan" subtitle="Klasifikasi pelanggan untuk pembedaan level harga dan layanan.">
        <x-slot:actions>
            <x-button onclick="openModal('golongan-tambah')">
                <i class="fas fa-layer-group"></i>
                Tambah Golongan
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER & SEARCH --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-8">
        <form method="GET" action="{{ route('golongan.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[260px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Golongan</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full bg-slate-50 border-none rounded-xl py-3 pl-12 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 shadow-inner"
                           placeholder="Ketik nama golongan...">
                </div>
            </div>
            <button type="submit" class="h-[38px] px-6 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700">Filter</button>
            <a href="{{ route('golongan.index') }}" class="h-[38px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all" title="Reset Filter">
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
                <th class="px-6 py-5 text-left">Nama Golongan</th>
                <th class="px-6 py-5 text-left">Keterangan</th>
                <th class="px-8 py-5 text-right">Manajemen</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($golongan as $i => $g)
            <tr class="group hover:bg-slate-50/30 transition-all">
                <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $golongan->firstItem() + $i }}</td>
                <td class="px-8 py-5 text-xs text-slate-400">#{{ $g->id_golongan }}</td>
                <td class="px-6 py-5">
                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200 uppercase ">
                        {{ $g->nama_golongan }}
                    </span>
                </td>
                <td class="px-6 py-5">
                    <p class="text-sm text-slate-500  leading-relaxed truncate max-w-xs">
                        {{ $g->keterangan ?? 'Tidak ada deskripsi tambahan' }}
                    </p>
                </td>
                <td class="px-8 py-5 text-right">
                    <div class="flex justify-end gap-2">
                        <button onclick="openEditGolongan({{ json_encode($g) }})"
                                class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-all" aria-label="Ubah" title="Ubah">
                            <i class="far fa-edit text-sm"></i>
                        </button>

                        <form id="delete-form-golongan-{{ $g->id_golongan }}" action="{{ route('golongan.destroy', $g->id_golongan) }}" method="POST" class="inline">
                            @csrf @method('DELETE')
                        </form>
                        <button type="button" onclick="confirmDeleteGolongan('{{ $g->id_golongan }}', {{ json_encode($g->nama_golongan) }})"
                                class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all">
                            <i class="far fa-trash-alt text-sm"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="5"><x-empty-state title="Belum ada golongan pelanggan. Tambahkan lewat tombol di atas." /></td></tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$golongan" />
</div>

{{-- MODAL TAMBAH
     Dulu ditulis manual: header biru penuh, radius 3rem (48px), judul
     20px, isian terpusat, tombol huruf besar, form ber-padding 48px.
     Lima perbedaan dari 110 modal lain di aplikasi ini. Sekarang
     memakai <x-modal>: kepala ikon + judul + subjudul, radius 8px,
     tinggi dan huruf yang sama dengan semuanya. --}}
<x-modal id="golongan-tambah" title="Tambah Golongan"
         subtitle="Klasifikasi harga untuk konsumen, memengaruhi tier harga jual"
         icon="fa-layer-group" maxWidth="md">
    <form action="{{ route('golongan.store') }}" method="POST" class="space-y-4">
        @csrf
        <x-form.group label="Nama Golongan" name="nama_golongan" required dalam>
            <x-form.input type="text" name="nama_golongan"
                         placeholder="Contoh: Reseller, Member, Umum" />
        </x-form.group>
        <x-form.group label="Keterangan Singkat" name="keterangan"
                      help="Dipakai sebagai catatan saat memilih golongan. Boleh dikosongkan." dalam>
            <textarea name="keterangan" placeholder="Contoh: Pelanggan dengan harga khusus"
                      class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-normal text-slate-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"></textarea>
        </x-form.group>
        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="closeModal('golongan-tambah')">Batal</x-button>
            <x-button type="submit">Simpan Golongan</x-button>
        </x-slot:footer>
    </form>
</x-modal>

{{-- MODAL EDIT
     Warna amber dan tombol oranye dulu dipakai supaya "ubah" terlihat
     berbeda dari "tambah". Menambah dan mengubah bukan dua kejadian
     yang berbeda jenisnya, jadi warna tidak lagi berubah; yang
     membedakan cukup kata pada judul. --}}
<x-modal id="golongan-edit" title="Edit Golongan"
         subtitle="Perubahan langsung berlaku pada tier harga yang memakai golongan ini"
         icon="fa-pen" maxWidth="md">
    <form id="formEditGolongan" method="POST" class="space-y-4">
        @csrf @method('PUT')
        <x-form.group label="Nama Golongan" name="nama_golongan" required dalam>
            <x-form.input type="text" name="nama_golongan" id="edit_nama_golongan" />
        </x-form.group>
        <x-form.group label="Keterangan Singkat" name="keterangan" dalam>
            <textarea name="keterangan" id="edit_keterangan"
                      class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-normal text-slate-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"></textarea>
        </x-form.group>
        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="closeModal('golongan-edit')">Batal</x-button>
            <x-button type="submit">Simpan Perubahan</x-button>
        </x-slot:footer>
    </form>
</x-modal>

<script>

    function openEditGolongan(data) {
        const form = document.getElementById('formEditGolongan');
        form.action = `{{ url('/golongan') }}/${data.id_golongan}`;
        document.getElementById('edit_nama_golongan').value = data.nama_golongan;
        document.getElementById('edit_keterangan').value = data.keterangan || '';
        openModal('golongan-edit');
    }

    function confirmDeleteGolongan(id, nama) {
        Swal.fire({
            title: 'Hapus Golongan?',
            html: `Golongan <b>${escHtml(nama)}</b> akan dihapus permanen.`,
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
                document.getElementById(`delete-form-golongan-${id}`).submit();
            }
        });
    }
</script>
@endsection