@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">
    <x-page-header title="Master Aset Mesin" subtitle="Monitor dan kelola armada mesin cetak produksi real-time">
        <x-slot:actions>
            <x-button onclick="openModal('mesin-tambah')">
                <i class="fas fa-plus-circle"></i>
                Registrasi Mesin
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER & SEARCH --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-8">
        <form method="GET" action="{{ route('mesin.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[260px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Mesin</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full bg-slate-50 border-none rounded-xl py-3 pl-12 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 shadow-inner"
                           placeholder="Ketik nama mesin...">
                </div>
            </div>
            <div class="w-full md:w-56">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Tipe Operasional</label>
                <select name="tipe_mesin" class="select2-filter w-full">
                    <option value="">Semua Tipe</option>
                    @foreach($daftarTipe as $t)
                        <option value="{{ $t }}" {{ request('tipe_mesin') == $t ? 'selected' : '' }}>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="h-[38px] px-6 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700">Filter</button>
            <a href="{{ route('mesin.index') }}" class="h-[38px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all" title="Reset Filter">
                <i class="fas fa-undo-alt text-sm"></i>
            </a>
        </form>
    </div>

    {{-- TABLE CONTAINER --}}
    <x-table>
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-8 py-5 text-left">Identitas Mesin</th>
                <th class="px-6 py-5 text-center">Tipe Operasional</th>
                <th class="px-6 py-5 text-center">Status</th>
                <th class="px-8 py-5 text-right">Manajemen</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($mesin as $i => $m)
                    <tr class="group hover:bg-slate-50/30 transition-all">
                        <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $mesin->firstItem() + $i }}</td>
                        {{-- Identitas Mesin --}}
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600 font-medium text-xs shadow-sm">
                                    <i class="fas fa-print"></i>
                                </div>
                                <div>
                                    <div class="text-[14px] font-medium text-slate-700 group-hover:text-indigo-600 transition-colors uppercase">
                                        {{ $m->nama_mesin }}
                                    </div>
                                    <div class="text-[12px] text-slate-400 mt-0.5">ID: #{{ $m->id_mesin }}</div>
                                </div>
                            </div>
                        </td>

                        {{-- Tipe Mesin --}}
                        <td class="px-6 py-5 text-center">
                            <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-[10px] font-medium uppercase tracking-wider bg-blue-50 text-blue-600 border border-blue-100">
                                <i class="fas fa-print mr-1.5 text-blue-400"></i> {{ $m->tipe_mesin }}
                            </span>
                        </td>

                        {{-- Status (Placeholder Visual) --}}
                        <td class="px-6 py-5 text-center">
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-medium text-emerald-500 bg-emerald-50 px-3 py-1.5 rounded-lg uppercase border border-emerald-100">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Active
                            </span>
                        </td>

                        {{-- Action Buttons --}}
                        <td class="px-8 py-5 text-right">
                            <div class="flex justify-end gap-2">
                                <button onclick="openModal('mesin-edit-{{ $m->id_mesin }}')"
                                        class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-all border border-transparent hover:border-amber-100">
                                    <i class="far fa-edit text-sm"></i>
                                </button>
                                
                                <form id="delete-form-mesin-{{ $m->id_mesin }}" action="{{ url('mesin/'.$m->id_mesin) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                </form>
                                <button type="button" onclick="confirmDeleteMesin('{{ $m->id_mesin }}', {{ json_encode($m->nama_mesin) }})"
                                        class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all border border-transparent hover:border-rose-100">
                                    <i class="far fa-trash-alt text-sm"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
            <tr><td colspan="5"><x-empty-state title="Belum ada mesin terdaftar." /></td></tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$mesin" />

    {{-- Modal edit: satu per baris. Dulu ditulis di dalam <tbody>, yang
         tidak valid - peramban memindahkan div keluar dari tabel dengan
         cara yang tidak ditentukan, sehingga posisinya tidak bisa
         diandalkan. Dipindah ke sini, dan lewat <x-modal> supaya
         Escape, klik-luar, dan animasinya seragam dengan modal lain. --}}
    @foreach($mesin as $m)
        <x-modal id="mesin-edit-{{ $m->id_mesin }}" title="Edit Mesin" maxWidth="md">
            <form action="{{ url('mesin/'.$m->id_mesin) }}" method="POST" class="space-y-4">
                @csrf @method('PUT')

                <div>
                    <label for="nama-mesin-{{ $m->id_mesin }}" class="block text-xs font-medium text-slate-700 mb-1.5">Nama Mesin Produksi</label>
                    <input type="text" name="nama_mesin" id="nama-mesin-{{ $m->id_mesin }}" value="{{ $m->nama_mesin }}" required>
                </div>

                <div>
                    <label for="tipe-mesin-{{ $m->id_mesin }}" class="block text-xs font-medium text-slate-700 mb-1.5">Tipe / Teknologi Mesin</label>
                    <input type="text" name="tipe_mesin" id="tipe-mesin-{{ $m->id_mesin }}" value="{{ $m->tipe_mesin }}" required>
                </div>

                <label class="flex items-start gap-3 cursor-pointer rounded-md bg-slate-50 px-4 py-3">
                    <input type="checkbox" name="tampil_divisi" value="1" {{ $m->tampil_divisi ? 'checked' : '' }} class="mt-0.5 w-4 h-4">
                    <span>
                        <span class="block text-xs font-medium text-slate-700">Tampil sebagai divisi produksi</span>
                        <span class="block text-xs text-slate-500">Hilangkan centang untuk mesin pendukung (mis. cutter) agar tidak jadi tab divisi di SPK dan Produksi.</span>
                    </span>
                </label>

                <x-slot:footer>
                    <x-button type="button" variant="secondary" onclick="closeModal('mesin-edit-{{ $m->id_mesin }}')">Batal</x-button>
                    <x-button type="submit">Simpan Perubahan</x-button>
                </x-slot:footer>
            </form>
        </x-modal>
    @endforeach
</div>

{{-- MODAL TAMBAH
     ID-nya dulu 'tambahMesin', tidak berawalan "modal-", jadi tidak
     bisa dikenali pendeteksian seragam dan tidak pernah dapat Escape
     maupun klik-luar. Sekarang lewat <x-modal>. --}}
<x-modal id="mesin-tambah" title="Registrasi Mesin Baru" maxWidth="md">
    <form action="{{ url('mesin') }}" method="POST" class="space-y-4">
        @csrf

        <div class="ld">
            <input type="text" name="nama_mesin" id="mesin-nama" placeholder="Contoh: Roland RF-640" required>
            <label class="ld-label" for="mesin-nama">Nama Mesin Produksi<span class="ld-wajib" aria-hidden="true">*</span></label>
        </div>

        <div class="ld">
            <input type="text" name="tipe_mesin" id="mesin-tipe" placeholder="Contoh: Eco-Solvent" required>
            <label class="ld-label" for="mesin-tipe">Tipe / Teknologi Mesin<span class="ld-wajib" aria-hidden="true">*</span></label>
        </div>

        <label class="flex items-start gap-3 cursor-pointer rounded-md bg-slate-50 px-4 py-3">
            <input type="checkbox" name="tampil_divisi" value="1" checked class="mt-0.5 w-4 h-4">
            <span>
                <span class="block text-xs font-medium text-slate-700">Tampil sebagai divisi produksi</span>
                <span class="block text-xs text-slate-500">Hilangkan centang untuk mesin pendukung (mis. cutter).</span>
            </span>
        </label>

        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="closeModal('mesin-tambah')">Batal</x-button>
            <x-button type="submit">Daftarkan Mesin</x-button>
        </x-slot:footer>
    </form>
</x-modal>

@push('scripts')
<script>
    $(document).ready(function() {
        $('.select2-filter').select2({ width: '100%' }).on('change', function() {
            $(this).closest('form').submit();
        });
    });
</script>
@endpush

<script>

    function confirmDeleteMesin(id, nama) {
        Swal.fire({
            title: 'Hapus Mesin?',
            html: `Mesin <b>${escHtml(nama)}</b> akan dihapus permanen.`,
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
                document.getElementById(`delete-form-mesin-${id}`).submit();
            }
        });
    }
</script>
@endsection