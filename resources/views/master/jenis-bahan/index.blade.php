@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">
    <x-page-header title="Master Jenis Bahan" subtitle="Taksonomi jenis material bahan baku (Vinyl, Kertas, Kain, dsb).">
        <x-slot:actions>
            <x-button onclick="openModal('jenis-bahan-tambah')">
                <i class="fas fa-layer-group"></i>
                Tambah Jenis Bahan
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER & SEARCH --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-8">
        <form method="GET" action="{{ route('jenis-bahan.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-64 lg:w-72">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Jenis Bahan</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full bg-white border border-slate-200 rounded-md py-2 pl-12 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "
                           placeholder="Ketik nama jenis bahan...">
                </div>
            </div>
            <button type="submit" class="h-[38px] px-6 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700">Filter</button>
            <a href="{{ route('jenis-bahan.index') }}" class="h-[38px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all" title="Reset Filter">
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
                        <th class="px-6 py-5 text-left">Nama Jenis</th>
                        <th class="px-6 py-5 text-left">Keterangan</th>
                        <th class="px-8 py-5 text-right">Manajemen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($jenisBahan as $i => $j)
                    <tr class="group hover:bg-slate-50/30 transition-all">
                        <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $jenisBahan->firstItem() + $i }}</td>
                        <td class="px-8 py-5 text-xs text-slate-400">#{{ $j->id_jenis_bahan }}</td>
                        <td class="px-6 py-5">
                            <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200 uppercase">
                                {{ $j->nama_jenis }}
                            </span>
                        </td>
                        <td class="px-6 py-5">
                            <p class="text-sm text-slate-500 leading-relaxed truncate max-w-xs">
                                {{ $j->keterangan ?? 'Tidak ada deskripsi tambahan' }}
                            </p>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <div class="flex justify-end gap-2">
                                <button onclick="openEditJenisBahan({{ json_encode($j) }})"
                                        class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-all" aria-label="Ubah" title="Ubah">
                                    <i class="far fa-edit text-sm"></i>
                                </button>

                                <form id="delete-form-jenis-bahan-{{ $j->id_jenis_bahan }}" action="{{ route('jenis-bahan.destroy', $j->id_jenis_bahan) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                </form>
                                <button type="button" onclick="confirmDeleteJenisBahan('{{ $j->id_jenis_bahan }}', {{ json_encode($j->nama_jenis) }})"
                                        class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all">
                                    <i class="far fa-trash-alt text-sm"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-20 text-center text-slate-400 font-medium italic">Belum ada jenis bahan yang terdaftar.</td>
                    </tr>
                    @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$jenisBahan" />
</div>

{{-- MODAL TAMBAH
     Dua lapis perbaikan:
       1. Dulu modal ini ditulis manual: header berwarna, radius 3rem
          (48px), judul 20px bold, form ber-padding 48px, isian
          text-center, tombol huruf besar. Semuanya menyimpang.
       2. Setelah pindah ke <x-modal>, labelnya masih ditulis tangan
          dengan gaya `text-xs font-medium` - gaya yang cuma dipakai
          13 kali di seluruh aplikasi. Yang dominan justru micro-label
          10px uppercase, dan aplikasi sudah menyediakannya sebagai
          <x-form.group dalam>. Modal ini sekarang memakai komponen itu,
          bersama x-form.input, sehingga label, isian, tanda wajib,
          pesan error, dan teks bantuannya sama persis dengan form
          lain - bukan ditiru ulang. --}}
<x-modal id="jenis-bahan-tambah" title="Tambah Jenis Bahan" maxWidth="md">
    <form action="{{ route('jenis-bahan.store') }}" method="POST" class="space-y-4">
        @csrf
        <x-form.group label="Nama Jenis" name="nama_jenis" required dalam>
            <x-form.input type="text" name="nama_jenis" id="nama_jenis"
                         placeholder="Contoh: Vinyl, Kertas, Kain" />
        </x-form.group>
        <x-form.group label="Keterangan Singkat" name="keterangan"
                      help="Dipakai sebagai petunjuk saat memilih bahan. Boleh dikosongkan." dalam>
            <textarea name="keterangan" id="keterangan" rows="3"
                      placeholder="Contoh: Bahan deterrent untuk cetak outdoor"
                      class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-normal text-slate-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></textarea>
        </x-form.group>
        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="closeModal('jenis-bahan-tambah')">Batal</x-button>
            <x-button type="submit">Simpan Jenis Bahan</x-button>
        </x-slot:footer>
    </form>
</x-modal>

{{-- MODAL EDIT
     Sepadan dengan modal tambah. Sebelumnya header amber dan tombolnya
     oranye, sementara modal tambah biru - tiga perbedaan visual untuk
     aksi yang sebenarnya sama. Menambah dan mengubah bukan dua jenis
     kejadian yang berbeda, jadi tidak pantas diberi warna berbeda. --}}
<x-modal id="jenis-bahan-edit" title="Edit Jenis Bahan" maxWidth="md">
    <form id="formEditJenisBahan" method="POST" class="space-y-4">
        @csrf @method('PUT')
        <x-form.group label="Nama Jenis" name="nama_jenis" required dalam>
            <x-form.input type="text" name="nama_jenis" id="edit_nama_jenis" />
        </x-form.group>
        <x-form.group label="Keterangan Singkat" name="keterangan"
                      help="Dipakai sebagai petunjuk saat memilih bahan. Boleh dikosongkan." dalam>
            <textarea name="keterangan" id="edit_keterangan" rows="3"
                      class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-normal text-slate-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"></textarea>
        </x-form.group>
        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="closeModal('jenis-bahan-edit')">Batal</x-button>
            <x-button type="submit">Simpan Perubahan</x-button>
        </x-slot:footer>
    </form>
</x-modal>

<script>
    function openEditJenisBahan(data) {
        const form = document.getElementById('formEditJenisBahan');
        form.action = `{{ url('/jenis-bahan') }}/${data.id_jenis_bahan}`;
        document.getElementById('edit_nama_jenis').value = data.nama_jenis;
        document.getElementById('edit_keterangan').value = data.keterangan || '';
        openModal('jenis-bahan-edit');
    }

    function confirmDeleteJenisBahan(id, nama) {
        Swal.fire({
            title: 'Hapus Jenis Bahan?',
            html: `Jenis bahan <b>${escHtml(nama)}</b> akan dihapus permanen.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            cancelButtonColor: '#F5F8FF',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'rounded-lg px-6 py-2.5',
                cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById(`delete-form-jenis-bahan-${id}`).submit();
            }
        });
    }
</script>
@endsection
