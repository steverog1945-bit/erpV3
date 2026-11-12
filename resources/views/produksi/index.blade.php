@extends('layouts.app')

@section('content')
@php $bisaUbah = \App\Support\Izin::boleh(auth()->user(), [23], 'edit'); @endphp
<x-polling-notifikasi :url="route('produksi.cek-antrian-baru')" :jumlah-awal="$jumlahAntreAwal" pesan="Ada item baru masuk Produksi." />
<div class="produksi-page w-full px-4 sm:px-6 lg:px-8 py-6 max-w-[1600px] mx-auto">

    <x-page-header title="Produksi">
        <x-slot:subtitle>
            Produksi &amp; Logistik <span class="mx-1 text-slate-500">/</span> Progres Pengerjaan SPK
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button href="{{ route('spk.index') }}" variant="secondary">
                <i class="fas fa-list-check"></i>
                Lihat SPK
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- DIVISI: otomatis dari master mesin --}}
    @include('partials.divisi-tabs', ['routeName' => 'produksi.index'])

    {{-- FILTER --}}
    <x-card class="produksi-filter-card p-4 mb-4">
        <form method="GET" action="{{ route('produksi.index') }}" class="produksi-filter-form flex flex-wrap items-end gap-3">
            <input type="hidden" name="divisi" value="{{ request('divisi') }}">
            <div class="produksi-filter-search w-full md:w-64 lg:w-72">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Konsumen</label>
                <x-form.input type="text" name="konsumen" value="{{ request('konsumen') }}" placeholder="Nama konsumen..." />
            </div>

            <div class="produksi-filter-id w-full md:w-36">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5 ml-1">ID SPK</label>
                <x-form.input type="text" name="id_spk" value="{{ request('id_spk') }}" placeholder="#" />
            </div>

            <div class="produksi-filter-date w-full md:w-40">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Order Dari</label>
                <x-form.input type="date" name="tgl_dari" value="{{ request('tgl_dari') }}" />
            </div>

            <div class="produksi-filter-date w-full md:w-40">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Order Sampai</label>
                <x-form.input type="date" name="tgl_sampai" value="{{ request('tgl_sampai') }}" />
            </div>

            <div class="produksi-filter-status w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5 ml-1">Status Produksi</label>
                <x-form.select name="status_produksi">
                    {{-- Opsi "semua" harus ada supaya nilai yang dikirim lewat
                         link "Lihat di Produksi" / "Lihat progres di Produksi"
                         (status_produksi=semua) terbaca oleh dropdown. Kalau
                         tidak ada, dropdown menampilkan "On Progress" dan
                         operator mengira filter-nya progress - saat menekan
                         "Filter" daftar jadi kosong karena statusnya
                         ternyata Selesai/Batal. --}}
                    <option value="semua" {{ request('status_produksi') === 'semua' ? 'selected' : '' }}>Semua Status</option>
                    <option value="progress" {{ request('status_produksi', 'progress') === 'progress' ? 'selected' : '' }}>On Progress (Desain + Cetak)</option>
                    <option value="selesai" {{ request('status_produksi') === 'selesai' ? 'selected' : '' }}>Selesai</option>
                    <option value="pending" {{ request('status_produksi') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="batal" {{ request('status_produksi') === 'batal' ? 'selected' : '' }}>Dibatalkan</option>
                </x-form.select>
            </div>

            <x-button type="submit">
                <i class="fas fa-filter"></i>
                Filter
            </x-button>

            @if(request()->anyFilled(['konsumen', 'id_spk', 'tgl_dari', 'tgl_sampai']) || (request()->filled('status_produksi') && request('status_produksi') !== 'progress'))
                <a href="{{ route('produksi.index') }}" class="text-[11px] font-medium text-rose-500 uppercase tracking-widest hover:underline ml-2">
                    Reset
                </a>
            @endif
        </form>
    </x-card>

    <x-table wrapperClass="produksi-workqueue-shell overflow-hidden" class="produksi-workqueue-table">
        <thead>
            <tr>
                <th class="px-4 py-3 text-center w-10">#</th>
                <th class="px-4 py-3 text-left">Nama File</th>
                <th class="px-4 py-3 text-left">Konsumen</th>
                <th class="px-4 py-3 text-left">Tanggal</th>
                <th class="px-4 py-3 text-center">Urgent</th>
                <th class="px-4 py-3 text-center">Qty Order</th>
                <th class="px-4 py-3 text-center">Qty Cetak</th>
                <th class="px-4 py-3 text-center">Status</th>
                <th class="px-4 py-3 text-center whitespace-nowrap">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($produksi as $i => $item)
            <tr class="group hover:bg-slate-50/50 transition-all {{ $item->status_urgent ? 'bg-rose-50/40' : '' }}">
                <td data-label="#" class="px-4 py-3 text-center text-[11px] text-slate-400">{{ $produksi->firstItem() + $i }}</td>
                <td data-label="Pekerjaan" class="px-4 py-3">
                    <div class="produksi-work-name text-[13px] font-medium text-slate-700 uppercase">{{ $item->nama_file }}</div>
                    <div class="produksi-work-number text-[10px] text-slate-400 mt-0.5">{{ $item->nama_produk }} &middot; SPK #{{ $item->id_detail }}</div>
                </td>
                <td data-label="Konsumen" class="px-4 py-3">
                    <span class="text-[12px] font-medium text-slate-700 uppercase">{{ $item->nama_konsumen }}</span>
                    @if($item->divisi)
                        <div class="produksi-division text-[10px] text-slate-400">{{ $item->divisi }}</div>
                    @endif
                </td>
                <td data-label="Tanggal" class="px-4 py-3">
                    <span class="text-[12px] font-medium text-slate-600">{{ \Carbon\Carbon::parse($item->tgl_order)->translatedFormat('d M Y') }}</span>
                </td>
                <td data-label="Urgent" class="px-4 py-3 text-center">
                    @if($item->status_urgent)
                        <i class="fas fa-fire text-rose-500" title="Urgent"></i>
                    @else
                        <span class="text-slate-200">-</span>
                    @endif
                </td>
                <td data-label="Qty Order" class="px-4 py-3 text-center">
                    <span class="text-[13px] font-medium text-slate-700">{{ angka($item->qty, 4) }}</span>
                </td>
                <td data-label="Qty Cetak" class="px-4 py-3 text-center">
                    @if(in_array($item->status_spk, ['Selesai', 'Batal', 'Pending']) || !$bisaUbah)
                        <span class="text-[13px] font-medium text-slate-700">{{ $item->qty_cetak ?? $item->qty }}</span>
                    @else
                        <form method="POST" action="{{ route('produksi.simpan-qty', $item->id_detail) }}" class="flex items-center justify-center gap-1">
                            @csrf @method('PATCH')
                            <input type="number" name="qty_cetak" min="0" value="{{ $item->qty_cetak ?? $item->qty }}"
                                class="w-20 bg-slate-50 border-none rounded-lg py-1.5 px-2 text-sm font-medium text-slate-700 text-center focus:ring-2 focus:ring-indigo-500/20">
                            <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all" title="Simpan jumlah produksi">
                                <i class="fas fa-save text-xs"></i>
                            </button>
                        </form>
                    @endif
                </td>
                <td data-label="Status" class="px-4 py-3 text-center">
                    <x-badge :status="$item->status_spk" />
                </td>
                <td data-label="Aksi" class="px-8 py-5 text-center">
                    <div class="produksi-row-actions flex items-center justify-center gap-2">
                        <x-button type="button" size="icon" variant="ghost" onclick="showCatatan({{ $item->id_detail }}, {{ json_encode($item->catatan_produksi) }})" class="border border-slate-100 hover:text-indigo-600" title="Detail / Catatan">
                            <i class="fas fa-eye text-xs"></i>
                        </x-button>

                        <x-button href="{{ route('spk.print', ['id' => $item->id_detail, 'format' => 'thermal']) }}" target="_blank" size="icon" variant="ghost" class="border border-slate-100 hover:text-slate-800" title="Cetak SPK (Thermal)">
                            <i class="fas fa-print text-xs"></i>
                        </x-button>
                        @if($item->spk_lengkap && in_array(optional(auth()->user()->role)->nama_role, ['Operator Desain', 'Administrator'], true))
                        <x-button href="{{ route('spk.desain', $item->id_detail) }}" target="_blank" size="icon" variant="ghost" class="border border-indigo-100 text-indigo-500 hover:text-indigo-700" title="Cetak SPK Desain (gambar, konsumen, bahan, finishing)">
                            <i class="fas fa-file-image text-xs"></i>
                        </x-button>
                        @endif

                        @if($item->status_spk === 'Pending')
                            <x-button href="{{ route('spk.index', ['status_spk' => 'Pending']) }}" size="icon" variant="ghost" class="border border-slate-100 hover:text-amber-600" title="Selesaikan keputusan di halaman SPK">
                                <i class="fas fa-clipboard-list text-xs"></i>
                            </x-button>
                        @elseif($bisaUbah && !in_array($item->status_spk, ['Selesai', 'Batal']))
                            <x-button type="button" size="icon" variant="ghost" onclick="selesaiProduksi({{ $item->id_detail }})" class="border border-slate-100 hover:text-emerald-600" title="Tandai Selesai">
                                <i class="fas fa-check text-xs"></i>
                            </x-button>
                            <x-button type="button" size="icon" variant="ghost" onclick="cancelSpk({{ $item->id_detail }})" class="border border-slate-100 hover:text-rose-600" title="Batalkan SPK">
                                <i class="fas fa-ban text-xs"></i>
                            </x-button>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9">
                    <x-empty-state title="Tidak ada pekerjaan produksi" description="Sesuaikan filter atau tunggu order baru dari Kasir." />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$produksi" />

</div>
@endsection

@push('scripts')
<script>
    function selesaiProduksi(id) {
        Swal.fire({
            title: 'Tandai Selesai?',
            text: 'Item ini akan ditandai sudah selesai diproduksi.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Ya, Selesai',
            cancelButtonText: 'Batal',
            customClass: { popup: 'rounded-2xl' }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `{{ url('produksi') }}/${id}/selesai`;
                form.innerHTML = `@csrf @method('PATCH')`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    function cancelSpk(id) {
        Swal.fire({
            title: 'Batalkan Produksi Item Ini?',
            html: `
                <div class="text-left">
                    <p class="text-sm text-slate-500 mb-3">Pilih tindakan selanjutnya untuk item ini:</p>
                    <label class="flex items-start gap-2 text-sm text-slate-700 mb-2 cursor-pointer">
                        <input type="radio" name="swal-tindakan" value="antre" class="mt-1 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                        <span><b>Kembalikan ke antrian SPK</b><br><small class="text-slate-400">Siap diambil ulang siapa saja, sama seperti SPK baru.</small></span>
                    </label>
                    <label class="flex items-start gap-2 text-sm text-slate-700 mb-2 cursor-pointer">
                        <input type="radio" name="swal-tindakan" value="pending" class="mt-1 text-amber-600 focus:ring-amber-500 cursor-pointer">
                        <span><b>Pending (belum diputuskan)</b><br><small class="text-slate-400">Diparkir di halaman SPK, tidak ikut antrian aktif sampai ada keputusan lanjutan.</small></span>
                    </label>
                    <label class="flex items-start gap-2 text-sm text-slate-700 mb-3 cursor-pointer">
                        <input type="radio" name="swal-tindakan" value="batal" class="mt-1 text-rose-600 focus:ring-rose-500 cursor-pointer" checked>
                        <span><b>Batal permanen</b><br><small class="text-slate-400">Tidak akan diambil/diproses lagi.</small></span>
                    </label>
                    <input id="swal-alasan-batal" class="swal2-input" placeholder="Alasan pembatalan (opsional)">
                    <label class="flex items-start gap-2 text-sm text-slate-600 mt-3 px-1 cursor-pointer">
                        <input type="checkbox" id="swal-kembalikan-stok" class="mt-0.5 w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                        <span>Kembalikan bahan baku yang sudah dipotong ke stok<br><small class="text-slate-400">Hanya untuk Batal permanen, dan HANYA kalau materialnya belum dipakai/rusak.</small></span>
                    </label>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            confirmButtonText: 'Ya, Lanjutkan',
            cancelButtonText: 'Kembali',
            customClass: { popup: 'rounded-2xl' },
            preConfirm: () => ({
                tindakan: document.querySelector('input[name="swal-tindakan"]:checked')?.value || 'batal',
                alasan: document.getElementById('swal-alasan-batal').value,
                kembalikanStok: document.getElementById('swal-kembalikan-stok').checked,
            })
        }).then((result) => {
            if (result.isConfirmed) {
                const { tindakan, alasan, kembalikanStok } = result.value;
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `{{ url('produksi') }}/${id}/cancel`;
                form.innerHTML = `@csrf @method('PATCH')<input type="hidden" name="tindakan" value="${tindakan}"><input type="hidden" name="alasan_batal" value="${(alasan || '').replace(/"/g, '&quot;')}"><input type="hidden" name="kembalikan_stok" value="${kembalikanStok ? '1' : '0'}">`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // Escape HTML sebelum ditaruh di Swal.fire({html:...}). catatan berasal
    // dari input bebas operator (alasan pembatalan SPK) yang disimpan apa
    // adanya ke database - tanpa ini, siapa pun yang bisa membatalkan SPK bisa
    // menaruh <img onerror=...> atau <script> di alasannya, yang lalu
    // dieksekusi di browser siapa pun yang membuka popup "Detail / Catatan"
    // item itu (stored XSS).
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function showCatatan(id, catatan) {
        Swal.fire({
            title: 'SPK #' + id,
            html: catatan ? `<div class="text-left text-sm">${escapeHtml(catatan)}</div>` : '<div class="text-slate-400 italic text-sm">Belum ada catatan produksi.</div>',
            icon: 'info',
            customClass: { popup: 'rounded-2xl' }
        });
    }
</script>
@endpush
