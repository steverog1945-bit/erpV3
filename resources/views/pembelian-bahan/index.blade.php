@extends('layouts.app')

@section('content')
<div class="pwa-ops-page w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Pembelian Bahan">
        <x-slot:subtitle>
            Logistik <span class="mx-1 text-slate-500">/</span> Manajemen Faktur Pembelian
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button href="{{ route('pembelian-bahan.create') }}">
                <i class="fas fa-plus-circle"></i>
                Tambah Pembelian
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER & SEARCH BAR --}}
    <x-card class="mb-6">
        <form method="GET" action="{{ route('pembelian-bahan.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-64 lg:w-72">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Data</label>
                <x-form.input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Nomor Surat Jalan atau Nama Pemasok..." />
            </div>

            <div class="w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Dari Tanggal</label>
                <x-form.input type="date" name="from" value="{{ request('from') }}" />
            </div>

            <div class="w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Sampai Tanggal</label>
                <x-form.input type="date" name="to" value="{{ request('to') }}" />
            </div>

            <div class="w-full md:w-52">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status Jatuh Tempo</label>
                <x-form.select name="jt">
                    <option value="">Tampilkan semua</option>
                    <option value="telat" {{ request('jt') === 'telat' ? 'selected' : '' }}>Telat jatuh tempo</option>
                    <option value="7hari" {{ request('jt') === '7hari' ? 'selected' : '' }}>Jatuh tempo &le; 7 hari</option>
                    <option value="aman" {{ request('jt') === 'aman' ? 'selected' : '' }}>Belum jatuh tempo (&gt; 7 hari)</option>
                </x-form.select>
            </div>

            <x-button type="submit">
                <i class="fas fa-filter"></i>
                Filter
            </x-button>

            @if(request()->anyFilled(['search', 'from', 'to', 'jt']))
                <a href="{{ route('pembelian-bahan.index') }}" class="text-[11px] font-medium text-rose-500 uppercase tracking-widest hover:underline ml-2">
                    Reset Filter
                </a>
            @endif
        </form>
    </x-card>

    {{-- HITUNGAN JATUH TEMPO (seluruh faktur berhutang) - klik untuk menyaring --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <a href="{{ route('pembelian-bahan.index', ['jt' => 'telat']) }}" title="Faktur yang sudah lewat jatuh tempo dan belum lunas" class="rounded-2xl border p-5 transition-all hover:shadow-md {{ request('jt') === 'telat' ? 'bg-rose-50 border-rose-200' : 'bg-white border-slate-100' }}">
            <p class="text-[10px] font-medium text-rose-500 uppercase tracking-widest">Telat Jatuh Tempo</p>
            <p class="text-2xl font-medium text-rose-600 mt-1">{{ $ringkasJt['telat']['n'] }} <span class="text-xs font-medium text-slate-400">faktur</span></p>
            <p class="text-xs font-medium text-slate-500 mt-0.5">Rp {{ angka($ringkasJt['telat']['nilai']) }}</p>
        </a>
        <a href="{{ route('pembelian-bahan.index', ['jt' => '7hari']) }}" title="Faktur yang jatuh tempo dalam 7 hari ke depan" class="rounded-2xl border p-5 transition-all hover:shadow-md {{ request('jt') === '7hari' ? 'bg-amber-50 border-amber-200' : 'bg-white border-slate-100' }}">
            <p class="text-[10px] font-medium text-amber-500 uppercase tracking-widest">Jatuh Tempo &le; 7 Hari</p>
            <p class="text-2xl font-medium text-amber-600 mt-1">{{ $ringkasJt['minggu']['n'] }} <span class="text-xs font-medium text-slate-400">faktur</span></p>
            <p class="text-xs font-medium text-slate-500 mt-0.5">Rp {{ angka($ringkasJt['minggu']['nilai']) }}</p>
        </a>
        <a href="{{ route('pembelian-bahan.index', ['jt' => 'aman']) }}" title="Faktur berhutang dengan jatuh tempo lebih dari 7 hari lagi" class="rounded-2xl border p-5 transition-all hover:shadow-md {{ request('jt') === 'aman' ? 'bg-emerald-50 border-emerald-200' : 'bg-white border-slate-100' }}">
            <p class="text-[10px] font-medium text-emerald-500 uppercase tracking-widest">Belum Jatuh Tempo</p>
            <p class="text-2xl font-medium text-emerald-600 mt-1">{{ $ringkasJt['aman']['n'] }} <span class="text-xs font-medium text-slate-400">faktur</span></p>
            <p class="text-xs font-medium text-slate-500 mt-0.5">Rp {{ angka($ringkasJt['aman']['nilai']) }}</p>
        </a>
    </div>
    @if(request()->filled('jt'))
        <div class="mb-4 text-xs font-medium text-slate-500">Menyaring: <b>{{ ['telat' => 'faktur telat jatuh tempo', '7hari' => 'faktur jatuh tempo dalam 7 hari', 'aman' => 'faktur belum jatuh tempo (lebih dari 7 hari)'][request('jt')] ?? '' }}</b>
            <a href="{{ route('pembelian-bahan.index', request()->except(['jt', 'page'])) }}" title="Kembali menampilkan semua faktur" class="text-indigo-600 font-medium uppercase tracking-widest ml-2 hover:underline">Tampilkan semua</a></div>
    @endif

    <form id="bulk-delete-form" method="POST" action="{{ route('pembelian-bahan.bulk-delete') }}">
        @csrf

        <x-table wrapperClass="pwa-ops-shell overflow-hidden" class="pwa-ops-table pwa-ops-table--purchase">
            <thead>
                <tr>
                    <th class="px-6 py-5 w-12 text-center">
                        <input type="checkbox" id="checkAll" class="rounded-md border-slate-200 text-indigo-600 focus:ring-indigo-500"><span class="pwa-ops-mobile-only">Pilih semua</span>
                    </th>
                    <th class="px-4 py-5 text-center w-10">#</th>
                    <th class="px-4 py-5 text-left min-w-[150px]">No. Faktur</th>
                    <th class="px-4 py-5 text-left">Tanggal</th>
                    <th class="px-4 py-5 text-left">Pemasok</th>
                    <th class="px-4 py-5 text-center">Status</th>
                    <th class="px-4 py-5 text-center">Jatuh Tempo</th>
                    <th class="px-4 py-5 text-right">Total Nominal</th>
                    <th class="px-8 py-5 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse($data as $i => $item)
                <tr class="pwa-ops-row group hover:bg-slate-50/50 transition-all">
                    <td class="pwa-ops-cell pwa-ops-select px-6 py-5 text-center" data-label="Pilih Faktur">
                        <input type="checkbox" name="ids[]" value="{{ $item->id_pembelian }}" class="row-checkbox rounded-md border-slate-200 text-indigo-600 focus:ring-indigo-500">
                    </td>
                    <td class="pwa-ops-cell pwa-ops-sequence px-4 py-5 text-center text-[11px] text-slate-400" data-label="Urutan">{{ $data->firstItem() + $i }}</td>

                    <td class="pwa-ops-cell pwa-ops-invoice px-4 py-5" data-label="Faktur">
                        <span class="text-[13px] font-medium text-slate-700 uppercase group-hover:text-indigo-600 transition-colors">
                            {{ $item->no_faktur }}
                        </span>
                    </td>

                    <td class="pwa-ops-cell pwa-ops-date px-4 py-5" data-label="Tanggal">
                        <span class="text-[12px] font-medium text-slate-600">
                            {{ \Carbon\Carbon::parse($item->tgl_transaksi)->translatedFormat('d M Y') }}
                        </span>
                    </td>
                    <td class="pwa-ops-cell pwa-ops-supplier px-4 py-5" data-label="Pemasok">
                        <span class="text-[12px] font-medium text-slate-700 uppercase">
                            {{ $item->kedatangan->supplier->nama_supplier ?? 'Umum' }}
                        </span>
                    </td>
                    <td class="pwa-ops-cell pwa-ops-status px-4 py-5 text-center" data-label="Status">
                        @if($item->status_pembayaran == 'Lunas')
                            <x-badge variant="success">Lunas</x-badge>
                            <div class="text-[9px] text-slate-400 font-medium italic mt-1">{{ \Carbon\Carbon::parse($item->tgl_pelunasan)->format('d/m/y') }}</div>
                        @else
                            <x-badge variant="warning">Hutang</x-badge>
                            @if($item->sisa_hutang < $item->grand_total)
                                <div class="text-[9px] text-rose-500 font-medium mt-1">Sisa Rp {{ angka($item->sisa_hutang) }}</div>
                            @endif
                        @endif
                    </td>
                    <td class="pwa-ops-cell pwa-ops-due px-4 py-5 text-center text-sm" data-label="Jatuh Tempo">
                        @if($item->status_pembayaran == 'Hutang')
                            @php
                                $jt = \Carbon\Carbon::parse($item->jatuh_tempo_tgl ?: $item->tgl_transaksi)->startOfDay();
                                $selisihHari = (int) now()->startOfDay()->diffInDays($jt, false); // negatif = sudah lewat
                            @endphp
                            <span class="block text-xs font-medium text-slate-600">{{ $jt->format('d/m/Y') }}</span>
                            @if($selisihHari < 0)
                                <span class="inline-block mt-1 px-2 py-0.5 rounded-md bg-rose-50 text-rose-600 text-[10px] font-medium uppercase" title="Sudah lewat jatuh tempo">Telat {{ abs($selisihHari) }} hari</span>
                            @elseif($selisihHari === 0)
                                <span class="inline-block mt-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-600 text-[10px] font-medium uppercase" title="Jatuh tempo hari ini">Hari ini</span>
                            @elseif($selisihHari <= 7)
                                <span class="inline-block mt-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-600 text-[10px] font-medium uppercase" title="Segera jatuh tempo">{{ $selisihHari }} hari lagi</span>
                            @else
                                <span class="inline-block mt-1 px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-600 text-[10px] font-medium uppercase" title="Masih ada waktu">{{ $selisihHari }} hari lagi</span>
                            @endif
                        @else
                            <span class="text-slate-400 italic text-xs">Lunas</span>
                        @endif
                    </td>

                    <td class="pwa-ops-cell pwa-ops-total px-4 py-5 text-right" data-label="Total Nominal">
                        <span class="text-[14px] font-medium text-indigo-600">
                            Rp {{ angka($item->grand_total) }}
                        </span>
                    </td>

                    <td class="pwa-ops-cell pwa-ops-actions px-8 py-5 text-center" data-label="Aksi">
                        <div class="flex items-center justify-center gap-2">
                            @if($item->status_pembayaran == 'Hutang')
                                <x-button type="button" size="icon" variant="ghost" onclick="bukaPelunasan('faktur', {{ $item->id_pembelian }})" class="bg-amber-50 text-amber-500 hover:bg-amber-500 hover:text-white" title="Bayar / cicil hutang faktur ini">
                                    <i class="fas fa-money-bill-wave text-xs"></i>
                                </x-button>
                            @endif

                            <x-button href="{{ route('pembelian-bahan.edit', $item->id_pembelian) }}" size="icon" variant="ghost" class="border border-slate-100 hover:text-indigo-600" title="Edit">
                                <i class="fas fa-edit text-xs"></i>
                            </x-button>
                            <x-button href="{{ route('pembelian-bahan.show', $item->id_pembelian) }}" size="icon" variant="ghost" class="border border-slate-100 hover:text-indigo-600" title="Detail">
                                <i class="fas fa-eye text-xs"></i>
                            </x-button>
                            <x-button type="button" size="icon" variant="ghost" onclick="singleDelete('{{ $item->id_pembelian }}')" class="border border-slate-100 hover:text-rose-600" title="Hapus">
                                <i class="fas fa-trash text-xs"></i>
                            </x-button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9">
                        <x-empty-state title="Data tidak ditemukan" />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </x-table>

        {{-- BULK ACTIONS FOOTER --}}
        <div class="mt-4">
            <x-button type="button" id="btn-bulk-delete" variant="danger" class="opacity-0 pointer-events-none" disabled>
                Hapus Terpilih (<span id="count-checked">0</span>)
            </x-button>
        </div>
    </form>

    <x-table.pagination :paginator="$data" />

    @include('partials.modal-pelunasan-hutang')

</div>
@endsection

@push('scripts')
<script>
    const checkAll = document.getElementById('checkAll');
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const bulkBtn = document.getElementById('btn-bulk-delete');
    const countSpan = document.getElementById('count-checked');

    checkAll?.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateBulkButton();
    });

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkButton);
    });

    function updateBulkButton() {
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

    // Bayar hutang: nominal default = sisa (pelunasan penuh), bisa dikurangi
    // untuk cicilan. Server tetap memvalidasi nominal <= sisa.
    function confirmPayment(id, sisa) {
        Swal.fire({
            title: 'Bayar Hutang Faktur',
            html: `
                <div style="text-align:left;font-size:13px">
                    <label style="font-weight:700">Nominal (sisa Rp ${angkaId(sisa, 2)})</label>
                    <input id="swal-nominal" type="text" inputmode="decimal" autocomplete="off" value="${formatUangDariAngka(sisa)}" class="swal2-input format-ribuan-desimal" style="margin:6px 0 14px;width:100%">
                    <label style="font-weight:700">Metode</label>
                    <select id="swal-metode" class="swal2-input" style="margin:6px 0 0;width:100%"><option value="Tunai">Tunai (Kas)</option><option value="Transfer">Transfer (Bank)</option></select>
                </div>`,
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Catat Pembayaran',
            customClass: { popup: 'rounded-2xl' },
            preConfirm: () => {
                const nominal = cleanUangDesimal(document.getElementById('swal-nominal').value);
                if (!nominal || nominal <= 0 || nominal > sisa + 0.01) {
                    Swal.showValidationMessage('Nominal harus lebih dari 0 dan tidak melebihi sisa hutang.');
                    return false;
                }
                return { nominal, metode: document.getElementById('swal-metode').value };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `{{ url('pembelian-bahan/pelunasan') }}/${id}`;
                form.innerHTML = `@csrf @method('PATCH')
                    <input type="hidden" name="nominal" value="${result.value.nominal}">
                    <input type="hidden" name="metode" value="${result.value.metode}">`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // Menghapus faktur = MENGAJUKAN pembatalan (jurnal dibalik setelah
    // Administrator menyetujui lewat menu Alur Persetujuan).
    function singleDelete(id) {
        Swal.fire({
            title: 'Ajukan pembatalan faktur?',
            text: 'Faktur baru dibatalkan setelah disetujui Administrator; jurnal akuntansinya akan dibalik.',
            input: 'textarea',
            inputPlaceholder: 'Alasan pembatalan (wajib)',
            inputValidator: (v) => !v ? 'Alasan wajib diisi' : null,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            confirmButtonText: 'Ajukan Pembatalan',
            customClass: { popup: 'rounded-2xl' }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `{{ url('pembelian-bahan') }}/${id}`;
                form.innerHTML = `@csrf @method('DELETE')<input type="hidden" name="alasan">`;
                form.querySelector('[name=alasan]').value = result.value;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    bulkBtn?.addEventListener('click', function() {
        Swal.fire({
            title: 'Ajukan pembatalan semua faktur terpilih?',
            text: 'Tiap faktur diajukan ke Alur Persetujuan; tidak ada yang langsung dihapus.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            confirmButtonText: 'Ya, Ajukan Semua',
            customClass: { popup: 'rounded-2xl' }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('bulk-delete-form').submit();
            }
        });
    });
</script>
@endpush

