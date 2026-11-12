@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">
    
    <x-page-header title="Manajemen Satuan" subtitle="Konfigurasi unit ukur & standarisasi konversi stok">
        <x-slot:actions>
            <x-button onclick="openTambahModal()">
                <i class="fas fa-plus-circle"></i>
                Tambah Satuan
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        {{-- INFO PANEL (Kiri) --}}
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl p-8 border border-slate-100 shadow-sm relative overflow-hidden">
                <div class="relative z-10">
                    <div class="w-14 h-14 bg-slate-100 text-slate-600 rounded-2xl flex items-center justify-center mb-6 shadow-sm border border-slate-200">
                        <i class="fas fa-exchange-alt text-xl"></i>
                    </div>
                    <h4 class="font-medium text-slate-800 mb-2 tracking-tight">Katalog Satuan</h4>
                    <p class="text-slate-500 text-[11px] leading-relaxed">
                        Daftar nama satuan yang dipakai di seluruh aplikasi (pilihan Satuan Stok/Satuan Hitung di Bahan Baku, satuan Jasa, dsb).
                    </p>

                    <div class="mt-8 p-4 bg-amber-50 rounded-2xl border border-amber-100">
                        <p class="text-[9px] font-medium text-amber-600 uppercase tracking-[0.15em] mb-2">
                            <i class="fas fa-info-circle mr-1"></i> Penting
                        </p>
                        <p class="text-[11px] text-amber-700 leading-relaxed">
                            Angka konversi di halaman ini <b>tidak dipakai otomatis</b> untuk menghitung stok. Konversi riil per bahan (mis. 1 Roll = 224 mÂ²) diatur di
                            <a href="{{ route('bahan.index') }}" class="underline font-medium">Master Bahan Baku</a> masing-masing bahan, karena tiap bahan bisa punya nilai konversi berbeda meski satuannya sama (contoh: dua bahan roll dengan lebar berbeda).
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- TABLE DATA (Kanan) --}}
        <div class="lg:col-span-3">
            {{-- FILTER & SEARCH --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-6">
                <form method="GET" action="{{ route('satuan.index') }}" class="flex flex-wrap items-end gap-4">
                    <div class="w-full md:w-72 lg:w-80">
                        <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Satuan</label>
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   class="w-full bg-white border border-slate-200 rounded-md py-2 pl-12 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "
                                   placeholder="Ketik nama satuan...">
                        </div>
                    </div>
                    <button type="submit" class="h-[38px] px-6 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700">Filter</button>
                    <a href="{{ route('satuan.index') }}" class="h-[38px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all" title="Reset Filter">
                        <i class="fas fa-undo-alt text-sm"></i>
                    </a>
                </form>
            </div>

            <x-table>
                <thead>
                    <tr>
                        <th class="px-4 py-5 text-center w-10">#</th>
                        <th class="px-8 py-5 text-left">Nama Satuan</th>
                        <th class="px-6 py-5 text-center">Catatan Konversi</th>
                        <th class="px-8 py-5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($satuan as $i => $s)
                    <tr class="group hover:bg-slate-50/30 transition-all">
                        <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $satuan->firstItem() + $i }}</td>
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400 font-medium text-[10px] group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-colors">
                                    {{ substr($s->nama_satuan, 0, 1) }}
                                </div>
                                <span class="text-sm font-medium text-slate-700 uppercase tracking-tight group-hover:text-indigo-600 transition-colors">
                                    {{ $s->nama_satuan }}
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-5 text-center">
                            <span class="text-[11px] font-medium text-slate-400">{{ angka($s->nilai_konversi, 2) }}</span>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <div class="flex justify-end gap-2">
                                <button onclick="openEditModal({{ $s->id_satuan }}, '{{ $s->nama_satuan }}', {{ angka($s->nilai_konversi, 4) }})"
                                        class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-xl transition-all">
                                    <i class="fas fa-edit text-sm"></i>
                                </button>
                                <form id="delete-form-satuan-{{ $s->id_satuan }}" action="{{ route('satuan.destroy', $s->id_satuan) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                </form>
                                <button type="button" onclick="confirmDeleteSatuan('{{ $s->id_satuan }}', {{ json_encode($s->nama_satuan) }})" class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition-all">
                                    <i class="fas fa-trash-alt text-sm"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-8 py-20 text-center text-slate-500  text-sm">Belum ada data satuan.</td>
                    </tr>
                    @endforelse
                </tbody>
            </x-table>

            <x-table.pagination :paginator="$satuan" />
        </div>
    </div>
</div>

{{-- MODAL (Unified)
     Pola yang sama seperti Kategori: dulu dibuka lewat
     classList.remove('hidden') + body.style.overflow inline, sehingga
     tidak terlihat dan halaman mengunci scroll permanen setelah ditutup.
     Sekarang lewat <x-modal> dan API global. --}}
<x-modal id="satuan" title="Satuan Baru" maxWidth="md">
    <form id="formSatuan" method="POST" class="space-y-4">
        @csrf
        <div id="methodField"></div>

        <x-form.group label="Label Satuan" name="nama_satuan" required dalam>
            <x-form.input type="text" name="nama_satuan" id="input_nama" maxlength="20"
                         placeholder="Contoh: RIM, PACK, LEMBAR" class="uppercase tracking-wide" />
        </x-form.group>

        <x-form.group label="Konversi (Opsional)" name="nilai_konversi" required
                      help="Catatan konversi, tidak dipakai otomatis oleh sistem. Isi 1 kalau tidak ada catatan khusus." dalam>
            <x-form.input type="number" step="0.01" name="nilai_konversi" id="input_nilai" placeholder="1.00" required />
        </x-form.group>

        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="closeModal('satuan')">Batal</x-button>
            <x-button type="submit">Simpan Satuan</x-button>
        </x-slot:footer>
    </form>
</x-modal>

<script>
    const form = document.getElementById('formSatuan');

    function isiFormSatuan({ judul, aksi, metode, nama, nilai }) {
        document.getElementById('modal-satuan-title').innerText = judul;
        form.action = aksi;
        document.getElementById('methodField').innerHTML = metode;
        document.getElementById('input_nama').value = nama || '';
        document.getElementById('input_nilai').value = nilai || '';
    }

    function openTambahModal() {
        isiFormSatuan({
            judul: 'Satuan Baru',
            aksi: '{{ route('satuan.store') }}',
            metode: '',
        });
        openModal('satuan');
    }

    function openEditModal(id, nama, nilai) {
        isiFormSatuan({
            judul: 'Edit Satuan',
            aksi: `{{ url('/satuan') }}/${id}`,
            metode: '@method("PUT")',
            nama: nama,
            nilai: nilai,
        });
        openModal('satuan');
    }

    function confirmDeleteSatuan(id, nama) {
        Swal.fire({
            title: 'Hapus Satuan?',
            html: `Satuan <b>${escHtml(nama)}</b> akan dihapus permanen.<br><small class="text-amber-600 font-medium">Perhitungan konversi stok yang menggunakan satuan ini mungkin akan kacau.</small>`,
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
                document.getElementById(`delete-form-satuan-${id}`).submit();
            }
        });
    }
</script>
@endsection
