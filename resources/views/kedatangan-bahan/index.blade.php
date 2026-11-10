@extends('layouts.app')

@section('content')
<div class="pwa-ops-page w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Kedatangan Bahan">
        <x-slot:subtitle>
            <i class="fas fa-truck-loading text-slate-500"></i>
            Logistik <span class="text-slate-500">/</span> Manajemen Surat Jalan
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button href="{{ route('kedatangan-bahan.create') }}">
                <i class="fas fa-plus-circle"></i>
                Tambah Kedatangan
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER --}}
    <x-card class="mb-6">
        <form method="GET" action="{{ route('kedatangan-bahan.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[260px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Pencarian</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <x-form.input type="text" name="search" value="{{ request('search') }}" class="!pl-11" placeholder="Nomor SJ atau nama supplier..." />
                </div>
            </div>
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Dari Tanggal</label>
                <x-form.input type="date" name="from" value="{{ request('from') }}" />
            </div>
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Sampai Tanggal</label>
                <x-form.input type="date" name="to" value="{{ request('to') }}" />
            </div>
            <x-button type="submit" title="Terapkan filter">
                <i class="fas fa-filter"></i>
                Filter
            </x-button>
            @if(request()->anyFilled(['search', 'from', 'to']))
                <a href="{{ route('kedatangan-bahan.index') }}" title="Kembalikan filter ke awal" class="text-[11px] font-medium text-rose-500 uppercase tracking-widest hover:underline ml-2">Reset</a>
            @endif
        </form>
    </x-card>

    {{-- TABLE --}}
    <form id="bulk-delete-form" method="POST" action="{{ route('kedatangan-bahan.bulk-delete') }}">
        @csrf
        <x-table wrapperClass="pwa-ops-shell overflow-hidden" class="pwa-ops-table pwa-ops-table--receiving">
            <thead>
                <tr>
                    <th class="px-6 py-5 w-12 text-center">
                        <input type="checkbox" id="checkAll" class="rounded-md border-slate-200 text-indigo-600 focus:ring-indigo-500"><span class="pwa-ops-mobile-only">Pilih semua</span>
                    </th>
                    <th class="px-4 py-5 text-center w-10">#</th>
                    <th class="px-4 py-5 text-left">Dokumen SJ</th>
                    <th class="px-4 py-5 text-left">Entitas</th>
                    <th class="px-4 py-5 text-left">Keterangan</th>
                    <th class="px-4 py-5 text-center">Status</th>
                    <th class="px-8 py-5 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse($kedatangans as $i => $k)
                <tr class="pwa-ops-row group hover:bg-slate-50/50 transition-all">
                    <td class="pwa-ops-cell pwa-ops-select px-6 py-5 text-center" data-label="Pilih Surat Jalan">
                        @if($k->status == 'Draft')
                            <input type="checkbox" name="ids[]" value="{{ $k->id_kedatangan }}" class="row-checkbox rounded-md border-slate-200 text-indigo-600 focus:ring-indigo-500">
                        @else
                            <i class="fas fa-lock text-slate-500 text-xs"></i>
                        @endif
                    </td>
                    <td class="pwa-ops-cell pwa-ops-sequence px-4 py-5 text-center text-[11px] text-slate-400" data-label="Urutan">{{ $kedatangans->firstItem() + $i }}</td>

                    <td class="pwa-ops-cell pwa-ops-doc px-4 py-5" data-label="Surat Jalan">
                        <span class="text-[13px] font-medium text-slate-700 uppercase block">{{ $k->no_surat_jalan }}</span>
                        <span class="text-[11px] text-slate-400 font-medium mt-0.5 block">{{ optional($k->tgl_datang)->translatedFormat('d M Y') }}</span>
                    </td>

                    <td class="pwa-ops-cell pwa-ops-entity px-4 py-5" data-label="Pemasok dan Gudang">
                        <span class="text-[12px] font-medium text-slate-700 uppercase block leading-tight">{{ $k->supplier->nama_supplier ?? 'VENDOR UMUM' }}</span>
                        @if($k->platform)<span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-md bg-orange-50 text-orange-600 text-[10px] font-medium uppercase"><i class="fas fa-shopping-bag"></i> {{ $k->platform->nama }}@if($k->no_pesanan_online) &middot; {{ $k->no_pesanan_online }}@endif</span>@endif
                        <span class="text-[10px] font-medium text-indigo-500 uppercase tracking-wider flex items-center gap-1 mt-0.5">
                            <i class="fas fa-warehouse text-[9px]"></i> {{ $k->gudang->nama_gudang ?? 'UTAMA' }}
                        </span>
                    </td>

                    {{-- KETERANGAN: tampil utuh (Enter/baris baru dipertahankan); catatan panjang dilipat dengan tombol "Selengkapnya" --}}
                    <td class="pwa-ops-cell pwa-ops-note px-4 py-5 min-w-[280px] max-w-md" data-label="Keterangan">
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <p class="catatan-isi text-[12px] text-slate-600 leading-relaxed whitespace-pre-line break-words" data-lipat="1">{{ $k->keterangan ?? 'Tidak ada catatan...' }}</p>
                            <button type="button" class="catatan-toggle hidden mt-1.5 text-[10px] font-medium uppercase tracking-widest text-indigo-500 hover:text-indigo-700" title="Tampilkan seluruh catatan">Selengkapnya <i class="fas fa-chevron-down text-[8px]"></i></button>
                        </div>
                    </td>

                    <td class="pwa-ops-cell pwa-ops-status px-4 py-5 text-center" data-label="Status">
                        <span class="px-3 py-1.5 rounded-lg border {{ $k->status == 'Selesai' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-amber-50 text-amber-600 border-amber-100' }} text-[10px] font-medium uppercase tracking-widest">
                            {{ $k->status }}
                        </span>
                    </td>

                    <td class="pwa-ops-cell pwa-ops-actions px-8 py-5 text-center" data-label="Aksi">
                        <div class="flex items-center justify-center gap-2">
                            @if($k->status == 'Draft')
                                <x-button type="button" onclick="confirmApprove('{{ $k->id_kedatangan }}', '{{ $k->no_surat_jalan }}')" size="sm" class="!px-4">
                                    Validasi
                                </x-button>
                                <x-button href="{{ route('kedatangan-bahan.edit', $k->id_kedatangan) }}" size="icon" variant="ghost" class="border border-slate-100 hover:text-indigo-600" title="Edit">
                                    <i class="fas fa-edit text-xs"></i>
                                </x-button>
                                <x-button type="button" size="icon" variant="ghost" onclick="singleDelete('{{ $k->id_kedatangan }}')" class="border border-slate-100 hover:text-rose-600" title="Hapus">
                                    <i class="fas fa-trash text-xs"></i>
                                </x-button>
                            @else
                                <span class="text-[11px] font-medium text-slate-500 uppercase italic">
                                    <i class="fas fa-check-double mr-1"></i> Terverifikasi
                                </span>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <x-empty-state title="Belum ada data kedatangan" description="Klik 'Tambah Kedatangan' untuk mencatat surat jalan pertama." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </x-table>

        {{-- BULK ACTIONS FOOTER --}}
        <div class="mt-4">
            <x-button type="button" id="btn-bulk-delete" variant="danger" class="opacity-0 pointer-events-none" disabled>
                <i class="fas fa-trash-alt"></i> Hapus Terpilih (<span id="count-checked">0</span>)
            </x-button>
        </div>
    </form>

    <x-table.pagination :paginator="$kedatangans" />
</div>
<style>
    .catatan-isi[data-lipat="1"].terlipat { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
</style>
@endsection

@push('scripts')
<script>
    // Catatan panjang: dilipat 3 baris, tombol muncul hanya bila memang terpotong.
    document.querySelectorAll('.catatan-isi').forEach(p => {
        p.classList.add('terlipat');
        const btn = p.parentElement.querySelector('.catatan-toggle');
        if (p.scrollHeight > p.clientHeight + 2) {
            btn.classList.remove('hidden');
            btn.addEventListener('click', () => {
                const lipat = p.classList.toggle('terlipat');
                btn.innerHTML = lipat ? 'Selengkapnya <i class="fas fa-chevron-down text-[8px]"></i>' : 'Ringkas <i class="fas fa-chevron-up text-[8px]"></i>';
                btn.title = lipat ? 'Tampilkan seluruh catatan' : 'Ringkas catatan';
            });
        } else {
            p.classList.remove('terlipat');
        }
    });

    const checkAll = document.getElementById('checkAll');
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const bulkBtn = document.getElementById('btn-bulk-delete');
    const countSpan = document.getElementById('count-checked');

    // Fungsi Update Bulk Button Visibility - toggle langsung di tombolnya
    // sendiri (pola sama dengan pembelian-bahan/index.blade.php), bukan
    // wrapper footer terpisah.
    function updateBulkUI() {
        const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
        countSpan.textContent = checkedCount;
        bulkBtn.disabled = checkedCount === 0;

        if (checkedCount > 0) {
            bulkBtn.classList.remove('opacity-0', 'pointer-events-none');
            bulkBtn.classList.add('opacity-100');
        } else {
            bulkBtn.classList.add('opacity-0', 'pointer-events-none');
            bulkBtn.classList.remove('opacity-100');
        }
    }

    checkAll?.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateBulkUI();
    });

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkUI);
    });

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Validasi Approval
    function confirmApprove(id, noSj) {
        Swal.fire({
            title: 'Validasi Kedatangan?',
            html: `Konfirmasi barang <b>${escapeHtml(noSj)}</b> telah diterima.<br><small class="text-rose-500 font-medium uppercase underline">Stok bertambah otomatis!</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563EB',
            confirmButtonText: 'Ya, Selesaikan!',
            customClass: { popup: 'rounded-2xl' }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `{{ url('kedatangan-bahan/validate') }}/${id}`;
                form.innerHTML = `@csrf @method('PATCH')`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // Delete Satuan
    function singleDelete(id) {
        Swal.fire({
            title: 'Hapus data ini?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            confirmButtonText: 'Ya, Hapus',
            customClass: { popup: 'rounded-2xl' }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `{{ url('kedatangan-bahan') }}/${id}`;
                form.innerHTML = `@csrf @method('DELETE')`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // Bulk Delete Action
    bulkBtn.addEventListener('click', function() {
        Swal.fire({
            title: 'Hapus Massal?',
            text: "Data draft yang terpilih akan dihapus selamanya.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            confirmButtonText: 'Ya, Hapus Semua',
            customClass: { popup: 'rounded-2xl' }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('bulk-delete-form').submit();
            }
        });
    });
</script>
@endpush