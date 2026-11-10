@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">
    <x-page-header title="Klasifikasi Kategori" subtitle="Pengelompokan Inventaris & Material Produksi">
        <x-slot:actions>
            <x-button onclick="openTambahModal()">
                <i class="fas fa-tags"></i>
                Kategori Baru
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER & SEARCH --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-8">
        <form method="GET" action="{{ route('kategori.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[260px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Kategori</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full bg-white border border-slate-200 rounded-md py-2 pl-12 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "
                           placeholder="Ketik nama atau kode kategori...">
                </div>
            </div>
            <button type="submit" class="h-[38px] px-6 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700">Filter</button>
            <a href="{{ route('kategori.index') }}" class="h-[38px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all" title="Reset Filter">
                <i class="fas fa-undo-alt text-sm"></i>
            </a>
        </form>
    </div>

    {{-- TABLE CONTAINER --}}
    <x-table>
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-8 py-5 text-left w-48">Kode Identitas</th>
                <th class="px-8 py-5 text-left">Nama Kategori</th>
                <th class="px-8 py-5 text-right">Manajemen</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($kategori as $i => $kat)
            <tr class="group hover:bg-slate-50/30 transition-all">
                <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $kategori->firstItem() + $i }}</td>
                <td class="px-8 py-5">
                    <span class="inline-flex items-center justify-center px-4 py-1.5 bg-slate-100 text-slate-600 text-[11px] font-medium rounded-lg uppercase border border-slate-200/50 ">
                        {{ $kat->kode_kategori }}
                    </span>
                </td>
                <td class="px-8 py-5">
                    <div class="text-[14px] font-medium text-slate-700 group-hover:text-indigo-600 transition-colors uppercase  tracking-tight">
                        {{ $kat->nama_kategori }}
                    </div>
                </td>
                <td class="px-8 py-5 text-right">
                    <div class="flex justify-end gap-2">
                        <button onclick="editKategori({{ json_encode($kat) }})"
                            class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-xl transition-all" aria-label="Ubah" title="Ubah">
                            <i class="far fa-edit text-sm"></i>
                        </button>

                        <form id="delete-form-kategori-{{ $kat->id_kategori }}" action="{{ route('kategori.destroy', $kat->id_kategori) }}" method="POST" class="inline">
                            @csrf @method('DELETE')
                        </form>
                        <button type="button" onclick="confirmDeleteKategori('{{ $kat->id_kategori }}', {{ json_encode($kat->nama_kategori) }})"
                            class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-xl transition-all">
                            <i class="far fa-trash-alt text-sm"></i>
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" class="py-24 text-center">
                    <div class="flex flex-col items-center">
                        <i class="fas fa-folder-open text-slate-100 text-6xl mb-4"></i>
                        <p class="text-slate-500 font-medium  text-sm">Belum ada kategori yang terdaftar.</p>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$kategori" />
</div>

{{-- MODAL CRUD (Unified)
     Sebelumnya ditulis manual dan rusak total:
       - dibuka lewat modal.classList.remove('hidden'), bukan lewat API,
         sehingga data-state tidak pernah jadi "open" dan aturan
         CSS opacity:0 tetap berlaku -> modal tidak terlihat sama sekali
       - ditutup lewat body.style.overflow = 'hidden' yang di-set inline,
         sementara API global hanya menghapus kelas overflow-hidden.
         Nilai inline menang atas kelas, jadi begitu modal ditutup
         halaman tidak bisa digulir lagi: stuck, hanya Refresh yang
         menolong.
     Sekarang memakai <x-modal> dan API global. --}}
<x-modal id="kategori" title="Tambah Kategori" maxWidth="md">
    <form id="formKategori" action="{{ route('kategori.store') }}" method="POST" class="space-y-4">
        @csrf
        <div id="methodField"></div>

        <x-form.group label="Nama Kategori" name="nama_kategori" required dalam>
            <x-form.input type="text" name="nama_kategori" id="input_nama"
                         placeholder="Contoh: Kertas Art Paper" class="uppercase" />
        </x-form.group>

        <x-form.group label="Kode Singkatan" name="kode_kategori" required
                      help="Maksimal 5 huruf, dipakai sebagai penanda cepat di tabel." dalam>
            <x-form.input type="text" name="kode_kategori" id="input_kode" maxlength="5"
                         placeholder="Contoh: AP" class="uppercase tracking-widest" />
        </x-form.group>

        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="closeModal('kategori')">Batal</x-button>
            <x-button type="submit">Simpan Kategori</x-button>
        </x-slot:footer>
    </form>
</x-modal>

<script>
    const form = document.getElementById('formKategori');

    function isiFormKategori({ judul, aksi, metode, nama, kode }) {
        document.getElementById('modal-kategori-title').innerText = judul;
        form.action = aksi;
        document.getElementById('methodField').innerHTML = metode;
        document.getElementById('input_nama').value = nama || '';
        document.getElementById('input_kode').value = kode || '';
    }

    function openTambahModal() {
        isiFormKategori({
            judul: 'Tambah Kategori',
            aksi: '{{ route('kategori.store') }}',
            metode: '',
        });
        openModal('kategori');
    }

    function editKategori(data) {
        isiFormKategori({
            judul: 'Edit Kategori',
            aksi: `{{ url('/kategori') }}/${data.id_kategori}`,
            metode: '@method("PUT")',
            nama: data.nama_kategori,
            kode: data.kode_kategori,
        });
        openModal('kategori');
    }

    function confirmDeleteKategori(id, nama) {
        Swal.fire({
            title: 'Hapus Kategori?',
            html: `Kategori <b>${escHtml(nama)}</b> akan dihapus permanen.`,
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
                document.getElementById(`delete-form-kategori-${id}`).submit();
            }
        });
    }
</script>
@endsection