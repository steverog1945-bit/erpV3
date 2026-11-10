@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 bg-slate-50 min-h-screen max-w-[1600px] mx-auto">

    <x-page-header title="Master Karyawan">
        <x-slot:subtitle>
            <i class="fas fa-users text-slate-500"></i>
            SDM <span class="mx-1 text-slate-500">/</span> Manajemen Profil &amp; Produksi
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button onclick="openModal('karyawan-tambah')">
                <i class="fas fa-plus-circle"></i>
                Tambah Karyawan
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER & SEARCH --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-8">
        <form method="GET" action="{{ url('/karyawan') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[260px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Karyawan</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full bg-white border border-slate-200 rounded-md py-2 pl-12 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "
                           placeholder="Ketik nama atau no. telepon...">
                </div>
            </div>
            <div class="w-full md:w-56">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Bagian</label>
                <select name="bagian" class="select2-filter w-full">
                    <option value="">Semua Bagian</option>
                    @foreach($daftarBagian as $b)
                        <option value="{{ $b }}" {{ request('bagian') == $b ? 'selected' : '' }}>{{ $b }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="h-[38px] px-6 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700">Filter</button>
            <a href="{{ url('/karyawan') }}" class="h-[38px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all" title="Reset Filter">
                <i class="fas fa-undo-alt text-sm"></i>
            </a>
        </form>
    </div>

    {{-- TABLE CONTAINER --}}
    <x-table>
        <thead>
            <tr>
                <th class="px-6 py-5 w-20 text-center">#</th>
                <th class="px-4 py-5 text-left">Profil Karyawan</th>
                <th class="px-4 py-5 text-left">Penempatan</th>
                <th class="px-4 py-5 text-center">Status Kepegawaian</th>
                <th class="px-8 py-5 text-center">Aksi Kendali</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @foreach($karyawan as $i => $k)
            <tr class="group hover:bg-slate-50/30 transition-all">
                {{-- Nomor Urut (lintas halaman) --}}
                <td class="px-6 py-5 text-center">
                    <span class="text-[11px] font-medium text-slate-400">{{ $karyawan->firstItem() + $i }}</span>
                </td>

                {{-- Name & Phone --}}
                <td class="px-4 py-5">
                    <div class="font-medium text-slate-800 uppercase tracking-tight text-[14px]">{{ $k->nama_karyawan }}</div>
                    <div class="text-[12px] text-slate-400 font-medium mt-1 flex items-center gap-1.5">
                        <i class="fas fa-phone-alt text-[10px] text-slate-500"></i> {{ $k->telepon }}
                    </div>
                </td>

                {{-- Division --}}
                <td class="px-4 py-5">
                    <span class="inline-flex items-center px-4 py-1.5 rounded-xl text-[11px] font-medium uppercase tracking-widest bg-slate-100 text-slate-600 border border-slate-200">
                        {{ $k->bagian }}
                    </span>
                    @if($k->mesin->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-1">
                            @foreach($k->mesin as $m)
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-[9px] font-medium uppercase tracking-wide bg-slate-100 text-slate-500">
                                    <i class="fas fa-cog text-[8px] mr-1"></i>{{ $m->nama_mesin }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </td>

                {{-- Status --}}
                <td class="px-4 py-5 text-center">
                    @if($k->status_aktif)
                        <span class="inline-flex items-center px-4 py-2 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 text-[11px] font-medium uppercase tracking-widest shadow-sm">
                            <span class="w-2 h-2 mr-2 rounded-full bg-emerald-500 animate-pulse"></span> Aktif
                        </span>
                    @else
                        <span class="inline-flex items-center px-4 py-2 rounded-xl bg-slate-100 text-slate-400 border border-slate-200 text-[11px] font-medium uppercase tracking-widest">
                            <span class="w-2 h-2 mr-2 rounded-full bg-slate-300"></span> Non-Aktif
                        </span>
                    @endif
                </td>

                {{-- Actions --}}
                <td class="px-8 py-5 text-center">
                    <div class="flex items-center justify-center gap-3">
                        <button onclick="openModal('karyawan-edit-{{ $k->id_karyawan }}')"
                                class="w-10 h-10 flex items-center justify-center bg-white border border-slate-100 text-slate-400 hover:text-indigo-600 hover:border-indigo-200 rounded-xl transition-all shadow-sm active:scale-90">
                            <i class="fas fa-edit text-sm"></i>
                        </button>

                        <form action="{{ url('karyawan/delete/'.$k->id_karyawan) }}" method="POST" class="inline" id="delete-form-{{ $k->id_karyawan }}">
                            @csrf @method('DELETE')
                            <button type="button" onclick="confirmDelete('{{ $k->id_karyawan }}', '{{ $k->nama_karyawan }}')"
                                    class="w-10 h-10 flex items-center justify-center bg-white border border-slate-100 text-slate-400 hover:text-rose-600 hover:border-rose-200 rounded-xl transition-all shadow-sm active:scale-90">
                                <i class="fas fa-trash text-sm"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>

                    {{-- MODAL EDIT (dalam loop) --}}
                    <x-modal id="karyawan-edit-{{ $k->id_karyawan }}" title="Edit Profil"
                             subtitle="ID Karyawan: #{{ $k->id_karyawan }}"
                             icon="fa-user-pen" maxWidth="md">
                        <form action="{{ url('karyawan/update/'.$k->id_karyawan) }}" method="POST" class="space-y-4">
                            @csrf @method('PUT')

                            <x-form.group label="Nama Lengkap" name="nama_karyawan" required dalam>
                                <x-form.input type="text" name="nama_karyawan" value="{{ $k->nama_karyawan }}" />
                            </x-form.group>

                            <x-form.group label="Divisi / Bagian" name="bagian" dalam>
                                <x-form.select name="bagian" onchange="toggleMesinField(this, 'mesin_wrapper_edit{{ $k->id_karyawan }}')">
                                    @foreach(['Admin', 'Desain', 'Produksi', 'Kasir', 'Gudang'] as $div)
                                        <option value="{{ $div }}" {{ $k->bagian == $div ? 'selected' : '' }}>{{ $div }}</option>
                                    @endforeach
                                </x-form.select>
                            </x-form.group>

                            <x-form.group label="Status Karyawan" name="status_aktif" dalam>
                                <x-form.select name="status_aktif">
                                    <option value="1" {{ $k->status_aktif == 1 ? 'selected' : '' }}>Aktif</option>
                                    <option value="0" {{ $k->status_aktif == 0 ? 'selected' : '' }}>Non-Aktif / Resign</option>
                                </x-form.select>
                            </x-form.group>

                            <x-form.group label="Nomor Telepon" name="telepon" dalam>
                                <x-form.input type="text" name="telepon" value="{{ $k->telepon }}" />
                            </x-form.group>

                            {{-- Field ini ditampilkan/disembunyikan oleh toggleMesinField(),
                                 jadi pembungkusnya harus tetap memakai id itu. --}}
                            <div id="mesin_wrapper_edit{{ $k->id_karyawan }}" class="{{ in_array($k->bagian, ['Produksi', 'Desain']) ? '' : 'hidden' }}">
                                <x-form.group label="Mesin / Divisi yang Ditangani"
                                              help="Kosongkan berarti tidak dibatasi, tetap melihat semua divisi di SPK. Hanya berlaku untuk bagian Produksi dan Desain." dalam>
                                    <div class="grid grid-cols-2 gap-2 bg-slate-50 rounded-md p-3">
                                        @foreach($daftarMesin as $m)
                                            <label class="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                                                <input type="checkbox" name="mesin_ids[]" value="{{ $m->id_mesin }}"
                                                       {{ $k->mesin->contains('id_mesin', $m->id_mesin) ? 'checked' : '' }}
                                                       class="w-4 h-4">
                                                {{ $m->nama_mesin }}
                                            </label>
                                        @endforeach
                                    </div>
                                </x-form.group>
                            </div>

                            <x-slot:footer>
                                <x-button type="button" variant="secondary" onclick="closeModal('karyawan-edit-{{ $k->id_karyawan }}')">Batal</x-button>
                                <x-button type="submit">Perbarui Profil</x-button>
                            </x-slot:footer>
                        </form>
                    </x-modal>
            @endforeach
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$karyawan" />
</div>

{{-- MODAL TAMBAH
     Acuan bentuk untuk modal sederhana di seluruh aplikasi: kepala
     berisi ikon, judul, dan satu baris subjudul yang menjelaskan
     apa akibatnya. Yang tidak diambil dari bentuk lamanya adalah
     radius 2.5rem, tata letak terpusat, dan huruf besar - ketiganya
     menyimpang dari 110 modal lain yang memakai <x-modal>. --}}
<x-modal id="karyawan-tambah" title="Karyawan Baru"
         subtitle="Data personil produksi, desain, kasir, dan gudang"
         icon="fa-user-plus" maxWidth="md">
    <form action="{{ route('karyawan.store') }}" method="POST" class="space-y-4">
        @csrf

        <x-form.group label="Nama Lengkap" name="nama_karyawan" required dalam>
            <x-form.input type="text" name="nama_karyawan" placeholder="Contoh: Budi Santoso" />
        </x-form.group>

        <x-form.group label="Pilih Bagian" name="bagian" required dalam>
            <x-form.select name="bagian" onchange="toggleMesinField(this, 'mesin_wrapper_tambah')">
                <option value="Admin">Admin</option>
                <option value="Desain">Desain</option>
                <option value="Produksi">Produksi</option>
                <option value="Kasir">Kasir</option>
                <option value="Gudang">Gudang</option>
            </x-form.select>
        </x-form.group>

        <x-form.group label="Kontak Telepon" name="telepon" required dalam>
            <x-form.input type="text" name="telepon" placeholder="0812xxxx" />
        </x-form.group>

        {{-- Wrapper ini ditampilkan/disembunyikan oleh toggleMesinField(),
             jadi id-nya wajib dipertahankan. --}}
        <div id="mesin_wrapper_tambah" class="hidden">
            <x-form.group label="Mesin / Divisi yang Ditangani"
                          help="Kosongkan berarti tidak dibatasi, tetap melihat semua divisi di SPK. Hanya berlaku untuk bagian Produksi dan Desain." dalam>
                <div class="grid grid-cols-2 gap-2 bg-slate-50 rounded-md p-3">
                    @foreach($daftarMesin as $m)
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                            <input type="checkbox" name="mesin_ids[]" value="{{ $m->id_mesin }}" class="w-4 h-4">
                            {{ $m->nama_mesin }}
                        </label>
                    @endforeach
                </div>
            </x-form.group>
        </div>

        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="closeModal('karyawan-tambah')">Batal</x-button>
            <x-button type="submit">Simpan Data</x-button>
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

    // Mesin/divisi cuma relevan untuk bagian yang benar-benar turun ke
    // mesin fisik (Produksi) atau lini desain per-mesin (Desain) - Kasir/
    // Admin/Gudang tidak beroperasi di mesin tertentu, jadi field-nya
    // disembunyikan & checkbox yang mungkin masih tercentang dari state
    // sebelumnya ikut dikosongkan supaya tidak ada data nyasar terkirim.
    const BAGIAN_BISA_MESIN = ['Produksi', 'Desain'];

    function toggleMesinField(selectEl, wrapperId) {
        const wrapper = document.getElementById(wrapperId);
        if (!wrapper) return;

        if (BAGIAN_BISA_MESIN.includes(selectEl.value)) {
            wrapper.classList.remove('hidden');
        } else {
            wrapper.classList.add('hidden');
            wrapper.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        }
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function confirmDelete(id, name) {
        Swal.fire({
            title: 'Hapus Karyawan?',
            html: `Anda akan menghapus data <b>${escapeHtml(name)}</b>.<br><small class="text-slate-400 uppercase font-medium tracking-widest">Tindakan ini permanen!</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal',
            customClass: { popup: 'rounded-2xl' }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById(`delete-form-${id}`).submit();
            }
        });
    }
</script>
@endpush
@endsection