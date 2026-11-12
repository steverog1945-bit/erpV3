@extends('layouts.app')

@section('content')
@php
    $bisaUbah = \App\Support\Izin::boleh(auth()->user(), [24], 'edit');
    $adaFilterLanjutan = request()->anyFilled(['nomor_nota', 'tgl_dari', 'tgl_sampai', 'status_pengerjaan', 'status_pengambilan']);
@endphp
<x-polling-notifikasi :url="route('collecting.cek-antrian-baru')" :jumlah-awal="$jumlahSiapDiambilAwal" pesan="Ada nota baru siap diambil." />
<div class="collecting-mobile-page w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Pengambilan">
        <x-slot:subtitle>
            Produksi &amp; Logistik <span class="mx-1 text-slate-500">/</span> Kelengkapan Pesanan Sebelum Diambil
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button href="{{ route('produksi.index') }}" variant="secondary">
                <i class="fas fa-industry"></i>
                Lihat Produksi
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER --}}
    <x-card class="collecting-mobile-filter-card mb-6">
        <form method="GET" action="{{ route('collecting.index') }}" class="collecting-mobile-filter-form flex flex-wrap items-end gap-4">
            <div class="collecting-mobile-search w-full md:w-64 lg:w-80">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Nama Konsumen</label>
                <x-form.input type="text" name="nama_konsumen" value="{{ request('nama_konsumen') }}" placeholder="Cari konsumen / ID (CST-000042)..." />
            </div>

            <button type="button" class="collecting-mobile-filter-toggle" aria-controls="collecting-filter-panel" aria-expanded="{{ $adaFilterLanjutan ? 'true' : 'false' }}" onclick="toggleCollectingFilter(this)">
                <i class="fas fa-sliders-h" aria-hidden="true"></i> Filter Lanjutan
                @if($adaFilterLanjutan)<span class="collecting-mobile-filter-active-dot" aria-label="Filter lanjutan aktif"></span>@endif
                <i class="fas fa-chevron-down collecting-mobile-chevron" aria-hidden="true"></i>
            </button>
            <div id="collecting-filter-panel" class="collecting-mobile-filter-panel {{ $adaFilterLanjutan ? 'collecting-filter-open' : '' }}">
            <div class="w-full md:w-52">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Nomor Faktur</label>
                <x-form.input type="text" name="nomor_nota" value="{{ request('nomor_nota') }}" placeholder="INV-..." />
            </div>

            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Order Dari</label>
                <x-form.input type="date" name="tgl_dari" value="{{ request('tgl_dari') }}" />
                @error('tgl_dari')<p class="text-[11px] text-rose-500 font-medium mt-1 ml-2">{{ $message }}</p>@enderror
            </div>

            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Order Sampai</label>
                <x-form.input type="date" name="tgl_sampai" value="{{ request('tgl_sampai') }}" />
                @error('tgl_sampai')<p class="text-[11px] text-rose-500 font-medium mt-1 ml-2">{{ $message }}</p>@enderror
            </div>

            <div class="w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status Pengerjaan</label>
                <x-form.select name="status_pengerjaan">
                    <option value="">-- Semua --</option>
                    <option value="belum" {{ request('status_pengerjaan') === 'belum' ? 'selected' : '' }}>Belum Lengkap</option>
                    <option value="lengkap" {{ request('status_pengerjaan') === 'lengkap' ? 'selected' : '' }}>Lengkap</option>
                </x-form.select>
            </div>

            <div class="w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Pengambilan</label>
                <x-form.select name="status_pengambilan">
                    <option value="">-- Semua --</option>
                    <option value="Belum Diambil" {{ request('status_pengambilan') === 'Belum Diambil' ? 'selected' : '' }}>Belum Diambil</option>
                    <option value="Sudah Diambil" {{ request('status_pengambilan') === 'Sudah Diambil' ? 'selected' : '' }}>Sudah Diambil</option>
                </x-form.select>
            </div>

            </div>{{-- /#collecting-filter-panel --}}
            <x-button type="submit" class="collecting-mobile-filter-submit">
                <i class="fas fa-filter"></i>
                Filter
            </x-button>

            @if(request()->anyFilled(['nama_konsumen', 'nomor_nota', 'tgl_dari', 'tgl_sampai', 'status_pengerjaan', 'status_pengambilan']))
                <a href="{{ route('collecting.index') }}" class="text-[11px] font-medium text-rose-500 uppercase tracking-widest hover:underline ml-2">
                    Reset
                </a>
            @endif
        </form>
    </x-card>

    <x-table wrapperClass="collecting-mobile-table-shell overflow-hidden" class="collecting-mobile-table">
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-6 py-5 text-left">Nomor Faktur</th>
                <th class="px-4 py-5 text-left">Konsumen</th>
                <th class="px-4 py-5 text-left">Tanggal Order</th>
                <th class="px-4 py-5 text-center">Progres Item</th>
                <th class="px-4 py-5 text-center">Status Bayar</th>
                <th class="px-4 py-5 text-center">Status Pengerjaan</th>
                <th class="px-4 py-5 text-center">Pengambilan</th>
                <th class="px-8 py-5 text-center">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($collecting as $i => $item)
            @php $lengkap = $item->total_item > 0 && $item->item_kelar == $item->total_item; @endphp
            <tr class="collecting-order-row group hover:bg-slate-50/50 transition-all">
                <td data-label="Urutan" class="collecting-sequence px-4 py-5 text-center text-[11px] text-slate-400">{{ $collecting->firstItem() + $i }}</td>
                <td data-label="Nomor faktur" class="collecting-invoice px-6 py-5">
                    <span class="text-[13px] font-medium text-slate-700 uppercase group-hover:text-indigo-600 transition-colors">{{ $item->nomor_nota }}</span>
                </td>
                <td data-label="Konsumen" class="collecting-consumer px-4 py-5">
                    <span class="text-[12px] font-medium text-slate-700 uppercase">{{ $item->nama_konsumen }}</span>
                </td>
                <td data-label="Tanggal order" class="collecting-date px-4 py-5">
                    <span class="text-[12px] font-medium text-slate-600">{{ \Carbon\Carbon::parse($item->tgl_order)->translatedFormat('d M Y') }}</span>
                </td>
                <td data-label="Progres item" class="collecting-progress px-4 py-5 text-center">
                    <div class="inline-flex items-center gap-2">
                        <div class="w-20 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full {{ $lengkap ? 'bg-emerald-500' : 'bg-amber-400' }}" style="width: {{ $item->total_item > 0 ? round(($item->item_kelar / $item->total_item) * 100) : 0 }}%"></div>
                        </div>
                        <span class="text-[11px] font-medium text-slate-500">{{ $item->item_kelar }}/{{ $item->total_item }}</span>
                    </div>
                    @if($item->item_batal > 0)
                        <div class="text-[9px] text-rose-400 mt-1">{{ $item->item_batal }} item dibatalkan</div>
                    @endif
                </td>
                <td data-label="Status bayar" class="collecting-payment px-4 py-5 text-center">
                    <x-badge :status="$item->status_pembayaran" />
                </td>
                <td data-label="Status pekerjaan" class="collecting-work px-4 py-5 text-center">
                    @if($item->status_nota === 'Dibatalkan')
                        <x-badge status="Dibatalkan" variant="danger" />
                    @elseif($lengkap)
                        <x-badge variant="success">Lengkap</x-badge>
                    @else
                        <x-badge variant="warning">Belum Lengkap</x-badge>
                    @endif
                </td>
                <td data-label="Pengambilan" class="collecting-pickup px-4 py-5 text-center">
                    @if($item->status_collecting === 'Sudah Diambil')
                        <x-badge variant="success">Sudah Diambil</x-badge>
                        <div class="text-[9px] text-slate-400 mt-1">{{ \Carbon\Carbon::parse($item->tgl_collecting)->translatedFormat('d M Y H:i') }} &middot; {{ $item->diambil_oleh ?? '-' }}</div>
                    @else
                        <x-badge variant="info">Belum Diambil</x-badge>
                    @endif
                </td>
                <td data-label="Aksi" class="collecting-actions px-8 py-5 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <x-button href="{{ route('produksi.index', ['konsumen' => $item->nama_konsumen, 'status_produksi' => 'semua']) }}" size="icon" variant="ghost" class="border border-slate-100 hover:text-indigo-600" title="Lihat progres di Produksi">
                            <i class="fas fa-eye text-xs"></i><span class="collecting-mobile-action-label">Produksi</span>
                        </x-button>
                        @if($bisaUbah && $item->status_collecting !== 'Sudah Diambil' && $item->status_nota !== 'Dibatalkan')
                            <x-button type="button" size="icon" variant="ghost"
                                onclick="prosesCollecting('{{ $item->id_transaksi }}', {{ json_encode($item->nomor_nota) }}, {{ $lengkap ? 'true' : 'false' }}, {{ json_encode($item->status_pembayaran) }})"
                                class="border border-slate-100 {{ $lengkap ? 'hover:text-emerald-600' : 'text-slate-200 cursor-not-allowed' }}"
                                title="{{ $lengkap ? 'Tandai Sudah Diambil' : 'Belum bisa diproses - masih ada item yang belum selesai' }}">
                                <i class="fas fa-hand-holding text-xs"></i><span class="collecting-mobile-action-label">Serahkan</span>
                            </x-button>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr class="collecting-empty-row">
                <td colspan="9">
                    <x-empty-state title="Tidak ada data" description="Sesuaikan filter atau tunggu order baru." />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$collecting" />

</div>

@push('scripts')
<script>
    function toggleCollectingFilter(button) {
        const panel = document.getElementById("collecting-filter-panel");
        if (!panel) return;
        const opened = panel.classList.toggle("collecting-filter-open");
        button.setAttribute("aria-expanded", opened ? "true" : "false");
    }

    function prosesCollecting(idTransaksi, nomorNota, sudahLengkap, statusBayar) {
        const belumLunas = statusBayar !== 'Lunas';
        if (!sudahLengkap) {
            Swal.fire({
                title: 'Belum Bisa Diproses',
                html: `Masih ada item pada nota <b>${escHtml(nomorNota)}</b> yang belum selesai dikerjakan. Semua item harus Selesai/Batal dulu sebelum bisa ditandai diambil.`,
                icon: 'warning',
                confirmButtonColor: '#2563EB',
                customClass: { popup: 'rounded-2xl' }
            });
            return;
        }

        // Serah terima dicatat: siapa penerima & (opsional) foto bukti.
        Swal.fire({
            title: 'Serah Terima Barang',
            html: `
                <div style="text-align:left;font-size:13px">
                    <p style="margin-bottom:10px">Nota <b>${nomorNota}</b> akan ditandai sudah diserahkan. Status ini tidak dimaksudkan untuk dibatalkan sembarangan.</p>
                    <label style="font-weight:700">Jenis</label>
                    <select id="sw-jenis" class="swal2-input" style="margin:6px 0 12px;width:100%">
                        <option value="Diambil Sendiri">Diambil sendiri oleh pelanggan</option>
                        <option value="Diambil Perwakilan">Diambil perwakilan</option>
                        <option value="Dikirim">Dikirim</option>
                    </select>
                    <label style="font-weight:700">Nama penerima (wajib untuk perwakilan/dikirim)</label>
                    <input id="sw-nama" class="swal2-input" style="margin:6px 0 12px;width:100%" maxlength="150">
                    <label style="font-weight:700">Catatan</label>
                    <input id="sw-catatan" class="swal2-input" style="margin:6px 0 12px;width:100%" maxlength="1000">
                    <label style="font-weight:700">Foto bukti (opsional)</label>
                    <input id="sw-bukti" type="file" accept="image/*" style="margin-top:6px;width:100%">
                    ${belumLunas ? `
                    <div style="margin-top:14px;padding:10px 12px;border:1px solid #F2B565;background:#FEF6EA;border-radius:6px">
                        <b style="color:#92400e">Nota ini belum lunas (${statusBayar}).</b>
                        <div style="margin:4px 0 8px;color:#78350f">Barang boleh diserahkan hanya dengan sengaja. Tulis alasannya, misalnya pelanggan tetap dengan jatuh tempo atau ada persetujuan pemilik.</div>
                        <input id="sw-alasan-belum-lunas" class="swal2-input" style="margin:0;width:100%" maxlength="500" placeholder="Alasan menyerahkan sebelum lunas">
                    </div>` : ''}
                </div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#F5F8FF',
            confirmButtonText: 'Ya, Sudah Diserahkan',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'rounded-lg px-6 py-2.5',
                cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600'
            },
            preConfirm: () => {
                const jenis = document.getElementById('sw-jenis').value;
                const nama = document.getElementById('sw-nama').value.trim();
                if (jenis !== 'Diambil Sendiri' && !nama) {
                    Swal.showValidationMessage('Nama penerima wajib diisi untuk perwakilan/dikirim.');
                    return false;
                }
                const alasan = belumLunas ? document.getElementById('sw-alasan-belum-lunas').value.trim() : '';
                if (belumLunas && alasan.length < 5) {
                    Swal.showValidationMessage('Nota belum lunas: tulis alasan menyerahkan barang (minimal 5 huruf).');
                    return false;
                }
                return { jenis, nama, catatan: document.getElementById('sw-catatan').value, bukti: document.getElementById('sw-bukti'), alasan };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.enctype = 'multipart/form-data';
                form.action = `{{ url('/collecting') }}/${idTransaksi}/proses`;
                form.innerHTML = `@csrf @method('PATCH')`;
                const isian = [['jenis', result.value.jenis], ['nama_penerima', result.value.nama], ['catatan_serah', result.value.catatan]];
                if (belumLunas) { isian.push(['konfirmasi_belum_lunas', '1'], ['alasan_belum_lunas', result.value.alasan]); }
                isian.forEach(([n, v]) => {
                    const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; form.appendChild(i);
                });
                if (result.value.bukti.files.length) { result.value.bukti.name = 'bukti'; form.appendChild(result.value.bukti); }
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>
@endpush
@endsection

