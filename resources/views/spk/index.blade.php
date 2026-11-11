@extends('layouts.app')

@section('content')
@php $bisaUbah = \App\Support\Izin::boleh(auth()->user(), [22], 'edit'); @endphp
@php
    // Panel filter terbuka otomatis bila ada filter non-bawaan aktif
    // (supaya tidak terasa hilang setelah reload).
    $adaFilterAktif = request('status_spk') || request('urgent_only') || request('tunggakan_aktif')
        || request('tgl_dari') || request('tgl_sampai');
@endphp
<x-polling-notifikasi :url="route('spk.cek-antrian-baru')" :jumlah-awal="$jumlahAntreAwal" pesan="Ada SPK baru masuk antrian." />
<div class="spk-page w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Antrean Produksi">
        <x-slot:subtitle>
            Produksi &amp; Logistik <span class="mx-1 text-slate-500">/</span> Daftar Pekerjaan dari Order
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button href="{{ route('produksi.index') }}" variant="secondary">
                <i class="fas fa-industry"></i>
                Lihat Produksi
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @unless ($tunggakanAktif)
    <div class="spk-tabs flex flex-wrap items-center gap-1.5 border-b border-slate-100 mb-5 pb-3" role="tablist" aria-label="Tahap antrean produksi">
        @php $tabItems = [
            ['tab' => 'antre', 'label' => 'Antre (' . $hitunganAntre . ')', 'count' => $hitunganAntre, 'cls' => 'bg-amber-50 text-amber-700 border-amber-200'],
            ['tab' => 'dikerjakan', 'label' => 'Dikerjakan (' . $hitunganDikerjakan . ')', 'count' => $hitunganDikerjakan, 'cls' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
            ['tab' => 'selesai', 'label' => 'Selesai (' . $hitunganSelesai . ')', 'count' => $hitunganSelesai, 'cls' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
            ['tab' => 'semua', 'label' => 'Semua (' . $hitunganSemua . ')', 'count' => $hitunganSemua, 'cls' => 'bg-slate-50 text-slate-700 border-slate-200'],
        ]; @endphp
        @foreach ($tabItems as $t)
            <a href="{{ route('spk.index', array_merge(request()->except(['page', 'tab']), ['tab' => $t['tab']])) }}"
               role="tab"
               aria-selected="{{ $aktifTab === $t['tab'] ? 'true' : 'false' }}"
               class="inline-flex items-center gap-2 h-8 pl-3 pr-2 rounded-lg border text-[11px] font-semibold transition-all {{ $aktifTab === $t['tab'] ? $t['cls'] . ' shadow-sm' : 'bg-white text-slate-500 border-slate-200 hover:border-slate-300' }}">
                {{ $t['label'] }}
            </a>
        @endforeach
    </div>
    @endunless

    {{-- DIVISI: otomatis dari master mesin --}}
    @include('partials.divisi-tabs', ['routeName' => 'spk.index'])

    {{-- FILTER & SEARCH --}}
    <x-card class="spk-filter-card mb-6">
        <form method="GET" action="{{ route('spk.index') }}" class="spk-filter-form flex flex-wrap items-end gap-4">
            <input type="hidden" name="divisi" value="{{ request('divisi') }}">

            <div class="spk-filter-search flex-1 min-w-[200px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari</label>
                <x-form.input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Nama file, konsumen, atau nomor nota..." />
            </div>

            <button type="button" id="spk-filter-toggle" class="spk-filter-btn inline-flex items-center gap-2 min-h-[44px] px-4 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors" onclick="toggleSpkFilter(this)" aria-expanded="{{ $adaFilterAktif ? 'true' : 'false' }}" aria-controls="spk-filter-panel">
                <i class="fas fa-sliders-h"></i>
                <span>Filter</span>
                @if($adaFilterAktif)
                    <span class="spk-filter-active-dot inline-block w-2 h-2 rounded-full bg-indigo-500"></span>
                @endif
                <i class="fas fa-chevron-down spk-filter-chevron text-[10px] opacity-70" style="{{ $adaFilterAktif ? 'transform: rotate(180deg);' : '' }}"></i>
            </button>

            <div id="spk-filter-panel" class="spk-filter-panel w-full {{ $adaFilterAktif ? 'spk-filter-open' : '' }}">
            {{-- Kolom tanggal memakai nilai default dari controller ketika form belum diisi,
                 sama seperti halaman laporan dan akuntansi. Sebelumnya view
                 menulis request('tgl_dari') ?? '' sehingga server menyaring
                 HARI INI sementara kolomnya tampil kosong: operator mengira
                 tabelnya kosong padahal order-nya cuma tersaring.
                 Kosongkan kembali saat operator memilih mode yang tidak memakai
                 rentang tanggal ("Tunggakan Aktif" atau "Tampilkan semua"), agar
                 tidak menampilkan filter yang diam-diam diabaikan. --}}
            <div class="spk-filter-date w-full md:w-44" data-spk-date>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Tanggal Order Dari</label>
                <input type="date" name="tgl_dari"
                       value="{{ $tunggakanAktif ? '' : (request('tgl_dari') ?? $tglDari->toDateString()) }}"
                       @if($tunggakanAktif) disabled @endif
                       class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
            </div>

            <div class="spk-filter-date w-full md:w-44" data-spk-date>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Sampai</label>
                <input type="date" name="tgl_sampai"
                       value="{{ $tunggakanAktif ? '' : (request('tgl_sampai') ?? $tglSampai->toDateString()) }}"
                       @if($tunggakanAktif) disabled @endif
                       class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
            </div>

            <div class="spk-filter-status w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status SPK</label>
                <x-form.select name="status_spk">
                    <option value="">-- Semua Status --</option>
                    @foreach(['Antre', 'Desain', 'Cetak', 'Selesai', 'Pending', 'Batal'] as $s)
                        <option value="{{ $s }}" {{ request('status_spk') === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </x-form.select>
            </div>

            <label class="spk-filter-toggle flex items-center gap-2 pb-3 cursor-pointer">
                <input type="checkbox" name="urgent_only" value="1" {{ request('urgent_only') ? 'checked' : '' }}
                    class="rounded-md border-slate-200 text-rose-600 focus:ring-rose-500">
                <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Urgent saja</span>
            </label>

            <div class="spk-filter-toggle flex items-center gap-2 pb-3">
                <input type="checkbox" id="spk-tunggakan-aktif" name="tunggakan_aktif" value="1" {{ request('tunggakan_aktif') ? 'checked' : '' }}
                    class="rounded-md border-slate-200 text-indigo-600 focus:ring-indigo-500">
                <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Tunggakan Aktif</span>
            </div>
            </div>{{-- /#spk-filter-panel --}}

            <x-button type="submit" class="spk-filter-submit">
                <i class="fas fa-filter spk-desktop-icon"></i>
                <svg class="spk-mobile-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M7 12h10m-7 7h4" /></svg>
                Filter
            </x-button>

            @if(request()->anyFilled(['search', 'status_spk', 'urgent_only', 'tgl_dari', 'tgl_sampai', 'tunggakan_aktif']))
                <a href="{{ route('spk.index', ['divisi' => request('divisi')]) }}" class="spk-filter-reset text-[11px] font-medium text-rose-500 uppercase tracking-widest hover:underline ml-2">
                    Reset
                </a>
            @endif
        </form>
    </x-card>
    {{-- Pekerjaan yang masih antre tapi tgl_order-nya di luar rentang tanggal
         yang sedang aktif. Tanpa pengumuman ini, lonceng notifikasi berbunyi
         sementara tabel di bawah kosong, lalu operator menyimpulkan SPK-nya
         hilang padahal masih menggantung.

         Latar ambernya dulu hanya 4.41:1 di mode gelap, di bawah ambang AA.
         Permukaan peringatan di app.css sudah digelapkan menjadi
         var(--surface-warning), jadi pasangan ini kini 6.24:1 di gelap dan
         10.85:1 di terang.

         Sengaja tanpa tautan. Tombol dan tautan di dalam kotak sewarna ini
         memakai transition, dan ketika tema diganti lewat tombol, warnanya
         tidak selalu ikut berubah: tautan sempat terukur 1.51:1 di mode terang
         sampai 1,2 detik setelah tema diganti, dan tetap begitu walau diberi
         kelas warna eksplisit. Karena tidak bisa dibuktikan aman, pengumuman ini
         memakai teks saja, yang terukur benar di kedua mode: 6.24:1 di gelap
         dan 10.85:1 di terang. Jalur membuka pekerjaan tertinggal sudah ada di
         form filter tepat di atas, lewat checkbox "Tunggakan Aktif". --}}
    @if ($tertinggal > 0)
        <div role="status" class="mb-6 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3">
            <i class="fas fa-triangle-exclamation text-amber-600 shrink-0" aria-hidden="true"></i>
            <p class="text-sm text-amber-900 m-0">
                {{ $tertinggal }} SPK masih antre di luar rentang tanggal ini.
                Centang <span class="font-medium">Tunggakan Aktif</span> di form filter di atas untuk melihatnya.
            </p>
        </div>
    @endif

    <form id="bulk-action-form" method="POST" action="{{ route('spk.bulk-urgent') }}">
        @csrf
        <x-table wrapperClass="spk-workqueue-shell overflow-hidden" class="spk-workqueue-table">
            <thead>
                <tr>
                    <th class="px-6 py-5 w-12 text-center">
                        @if($bisaUbah)<input type="checkbox" id="checkAll" class="rounded-md border-slate-200 text-indigo-600 focus:ring-indigo-500">@endif
                    </th>
                    <th class="px-4 py-5 text-center w-10">#</th>
                    <th class="px-4 py-5 text-left">Nama File</th>
                    <th class="px-4 py-5 text-left">Konsumen</th>
                    <th class="px-4 py-5 text-left">Tanggal Order</th>
                    <th class="px-4 py-5 text-center">Qty</th>
                    <th class="px-4 py-5 text-center">Urgent</th>
                    <th class="px-4 py-5 text-left">Pembuat Nota</th>
                    <th class="px-4 py-5 text-center">Status Bayar</th>
                    <th class="px-4 py-5 text-center">Status SPK</th>
                    <th class="px-8 py-5 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse($spk as $i => $item)
                <tr class="group hover:bg-slate-50/50 transition-all {{ $item->status_urgent ? 'bg-rose-50/40' : '' }}">
                    <td class="px-6 py-5 text-center">
                        <input type="checkbox" name="ids[]" value="{{ $item->id_detail }}" class="row-checkbox rounded-md border-slate-200 text-indigo-600 focus:ring-indigo-500">
                    </td>
                    <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $spk->firstItem() + $i }}</td>
                    <td data-label="Pekerjaan" class="px-4 py-5">
                        <div class="spk-work-name text-[13px] font-medium text-slate-700 uppercase group-hover:text-indigo-600 transition-colors">{{ $item->nama_file }}</div>
                        <div class="spk-work-number text-[10px] text-slate-400 mt-0.5">Nota: {{ $item->nomor_nota }} &middot; SPK #{{ $item->id_detail }}</div>
                    </td>
                    <td data-label="Konsumen" class="px-4 py-5">
                        <span class="text-[12px] font-medium text-slate-700 uppercase">{{ $item->nama_konsumen }}</span>
                        @if($item->divisi)
                            <div class="spk-division text-[10px] text-slate-400">{{ $item->divisi }}</div>
                        @endif
                    </td>
                    <td data-label="Tanggal order" class="px-4 py-5">
                        <span class="text-[12px] font-medium text-slate-600">{{ \Carbon\Carbon::parse($item->tgl_order)->translatedFormat('d M Y') }}</span>
                    </td>
                    <td data-label="Qty" class="px-4 py-5 text-center">
                        <span class="text-[13px] font-medium text-slate-700">{{ angka($item->qty, 4) }}</span>
                    </td>
                    <td data-label="Prioritas" class="px-4 py-5 text-center">
                        <button type="button" onclick="toggleUrgent({{ $item->id_detail }})"
                            class="spk-priority-button text-lg {{ $item->status_urgent ? 'text-rose-500' : 'text-slate-400 hover:text-rose-500' }}" title="Tandai urgent">
                            <i class="fas fa-fire spk-desktop-icon"></i>
                            <svg class="spk-mobile-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12.8 3.2c.5 3.3-1.2 4.6-2.7 6.1-1.2 1.2-2.1 2.6-2.1 4.5a6 6 0 0 0 12 0c0-3.4-2-6.7-7.2-10.6Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M10.4 14.2c-.3 2 1 3.8 2.9 3.8s3-1.5 2.5-3.3c-.3-1.1-1.1-1.9-2-2.8-.1 1.3-.8 2-1.6 2.5-.6-.4-1-.9-1.2-1.6-.3.4-.5.9-.6 1.4Z" /></svg>
                            <span class="spk-mobile-action-label">{{ $item->status_urgent ? 'Urgent' : 'Normal' }}</span>
                        </button>
                    </td>
                    <td data-label="Pembuat nota" class="px-4 py-5">
                        <span class="text-[12px] font-medium text-slate-600">{{ $item->pembuat_nota }}</span>
                    </td>
                    <td data-label="Status bayar" class="px-4 py-5 text-center">
                        <x-badge :status="$item->status_pembayaran" />
                    </td>
                    <td data-label="Tahap saat ini" class="px-4 py-5 text-center">
                        <x-badge :status="$item->status_spk" />
                    </td>
                    <td class="px-8 py-5 text-center">
                        <div class="spk-row-actions flex items-center justify-center gap-2">
                            @if(!$bisaUbah)
                                <span class="text-[10px] font-medium text-slate-500 uppercase tracking-wider">Hanya lihat</span>
                            @elseif($item->status_spk === 'Antre')
                                <x-button type="button" size="icon" variant="ghost" onclick="ambilPekerjaan({{ $item->id_detail }})" class="border border-slate-100 hover:text-emerald-600" title="Ambil Pekerjaan (pindah ke Produksi)">
                                    <i class="fas fa-hand-paper text-xs spk-desktop-icon"></i>
                                    <svg class="spk-mobile-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12V6a2 2 0 0 1 4 0v5-6a2 2 0 0 1 4 0v7-5a2 2 0 0 1 4 0v8a6 6 0 0 1-6 6h-2a6 6 0 0 1-5-2.7l-3-4.1a2 2 0 0 1 3-2.6L8 14" /></svg>
                                    <span class="spk-mobile-action-label">Ambil</span>
                                </x-button>
                            @elseif($item->status_spk === 'Pending')
                                <x-button type="button" size="icon" variant="ghost" onclick="resolvePending({{ $item->id_detail }}, 'antre')" class="border border-slate-100 hover:text-indigo-600" title="Kembalikan ke Antrian">
                                    <i class="fas fa-undo text-xs spk-desktop-icon"></i>
                                    <svg class="spk-mobile-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 10h10a6 6 0 1 1-5.7 8M4 10l4-4m-4 4 4 4" /></svg>
                                    <span class="spk-mobile-action-label">Kembali</span>
                                </x-button>
                                <x-button type="button" size="icon" variant="ghost" onclick="resolvePending({{ $item->id_detail }}, 'batal')" class="border border-slate-100 hover:text-rose-600" title="Batalkan Permanen">
                                    <i class="fas fa-ban text-xs spk-desktop-icon"></i>
                                    <svg class="spk-mobile-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path stroke-linecap="round" d="m5.6 5.6 12.8 12.8" /></svg>
                                    <span class="spk-mobile-action-label">Batalkan</span>
                                </x-button>
                            @endif
                            <x-button href="{{ route('spk.print', ['id' => $item->id_detail, 'format' => 'thermal']) }}" target="_blank" size="icon" variant="ghost" class="border border-slate-100 hover:text-slate-800" title="Cetak SPK (Thermal)">
                                <i class="fas fa-print text-xs spk-desktop-icon"></i>
                                <svg class="spk-mobile-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8V4h10v4M7 17H5a2 2 0 0 1-2-2v-4a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v4a2 2 0 0 1-2 2h-2" /><path stroke-linecap="round" stroke-linejoin="round" d="M7 14h10v6H7z" /><path stroke-linecap="round" d="M17 11h.01" /></svg>
                                <span class="spk-mobile-action-label">Cetak</span>
                            </x-button>
                            @if($item->spk_lengkap && in_array(optional(auth()->user()->role)->nama_role, ['Operator Desain', 'Administrator'], true))
                            <x-button href="{{ route('spk.desain', $item->id_detail) }}" target="_blank" size="icon" variant="ghost" class="border border-indigo-100 text-indigo-500 hover:text-indigo-700" title="Cetak SPK Desain (gambar, konsumen, bahan, finishing)">
                                <i class="fas fa-file-image text-xs spk-desktop-icon"></i>
                                <svg class="spk-mobile-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3h8l4 4v14H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5M8 16l2.5-2.5L13 16l2-2 2 2M9 10h.01" /></svg>
                                <span class="spk-mobile-action-label">Desain</span>
                            </x-button>
                            @endif
                            <x-button href="{{ route('produksi.index', ['id_spk' => $item->id_detail, 'status_produksi' => 'semua']) }}" size="icon" variant="ghost" class="border border-slate-100 hover:text-indigo-600" title="Lihat di Produksi">
                                <i class="fas fa-industry text-xs spk-desktop-icon"></i>
                                <svg class="spk-mobile-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 20V9l6 4V9l6 4V6h5v14H3Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M7 17h2m3 0h2m3 0h2" /></svg>
                                <span class="spk-mobile-action-label">Produksi</span>
                            </x-button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr class="spk-empty-row">
                    <td colspan="11">
                        <x-empty-state title="Belum ada SPK" description="Sesuaikan filter atau tunggu order baru dari Kasir." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </x-table>

        @if($bisaUbah)
        <div class="mt-4 flex gap-3">
            <x-button type="button" id="btn-bulk-ambil" variant="success" class="opacity-0 pointer-events-none" disabled>
                <i class="fas fa-hand-paper"></i>
                Ambil Semua Terpilih (<span id="count-checked-ambil">0</span>)
            </x-button>
            <x-button type="button" id="btn-bulk-urgent" variant="danger" class="opacity-0 pointer-events-none" disabled>
                <i class="fas fa-fire"></i>
                Tandai Urgent (<span id="count-checked">0</span>)
            </x-button>
        </div>
        @endif
    </form>

    <x-table.pagination :paginator="$spk" />

</div>
@endsection

@push('scripts')
<script>
    // Presentasi-saja: buka/tutup panel filter di HP (SPK Phase 3 polish).
    function toggleSpkFilter(tombol) {
        const panel = document.getElementById('spk-filter-panel');
        if (!panel) return;
        const nowVisible = panel.classList.toggle('spk-filter-open');
        tombol.classList.toggle('spk-filter-open', nowVisible);
        tombol.setAttribute('aria-expanded', nowVisible ? 'true' : 'false');
        const chevron = tombol.querySelector('.spk-filter-chevron');
        if (chevron) chevron.style.transform = nowVisible ? 'rotate(180deg)' : '';
    }

    // "Tunggakan Aktif" = mode yang MENGGANTIKAN rentang tanggal (server: kedua
    // field tanggal wajib kosong). Sebelumnya: centang tidak terjadi apa-apa
    // karena field tgl_dari/tgl_sampai tetap terkirim dalam keadaan terisi
    // (default hari ini) dan server menganggap mode rentang lagi; operator
    // juga wajib menekan tombol Filter sendiri. Sekarang: auto-submit +
    // field tanggal dibersihkan saat mode aktif (input disabled = tidak
    // dikirim), dikembalikan saat mode dilepas.
    const tunggakanBox = document.getElementById('spk-tunggakan-aktif');
    if (tunggakanBox) {
        const filterForm = tunggakanBox.closest('form');
        tunggakanBox.addEventListener('change', function () {
            if (!filterForm) return;
            const tanggal = filterForm.querySelectorAll('[data-spk-date] input');
            if (this.checked) {
                tanggal.forEach((inp) => { inp.value = ''; inp.disabled = true; });
            } else {
                tanggal.forEach((inp) => { inp.disabled = false; });
            }
            filterForm.submit();
        });
    }

    const checkAll = document.getElementById('checkAll');
    const checkboxes = document.querySelectorAll('.row-checkbox');
    const bulkBtn = document.getElementById('btn-bulk-urgent');
    const bulkAmbilBtn = document.getElementById('btn-bulk-ambil');
    const countSpan = document.getElementById('count-checked');
    const countSpanAmbil = document.getElementById('count-checked-ambil');

    checkAll?.addEventListener('change', function() {
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateBulkButton();
    });

    checkboxes.forEach(cb => cb.addEventListener('change', updateBulkButton));

    function updateBulkButton() {
        const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
        countSpan.textContent = checkedCount;
        countSpanAmbil.textContent = checkedCount;
        [bulkBtn, bulkAmbilBtn].forEach(btn => {
            btn.disabled = checkedCount === 0;
            btn.classList.toggle('opacity-0', checkedCount === 0);
            btn.classList.toggle('pointer-events-none', checkedCount === 0);
        });
    }

    bulkBtn?.addEventListener('click', function() {
        Swal.fire({
            title: 'Tandai Urgent Semua Terpilih?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            confirmButtonText: 'Ya, Tandai',
            cancelButtonText: 'Batal',
            customClass: { popup: 'rounded-2xl' }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById('bulk-action-form');
                form.action = "{{ route('spk.bulk-urgent') }}";
                form.submit();
            }
        });
    });

    bulkAmbilBtn?.addEventListener('click', function() {
        const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
        Swal.fire({
            title: 'Ambil Semua Pekerjaan Terpilih?',
            text: `${checkedCount} item akan pindah dari antrean SPK ke halaman Produksi. Item yang sudah diambil sebelumnya otomatis dilewati.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563EB',
            confirmButtonText: 'Ya, Ambil Semua',
            cancelButtonText: 'Batal',
            customClass: { popup: 'rounded-2xl' }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.getElementById('bulk-action-form');
                form.action = "{{ route('spk.bulk-ambil') }}";
                form.submit();
            }
        });
    });

    function toggleUrgent(id) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `{{ url('spk') }}/${id}/urgent`;
        form.innerHTML = `@csrf @method('PATCH')`;
        document.body.appendChild(form);
        form.submit();
    }

    function resolvePending(id, tindakan) {
        const isAntre = tindakan === 'antre';
        Swal.fire({
            title: isAntre ? 'Kembalikan ke Antrian?' : 'Batalkan Item Ini Permanen?',
            text: isAntre
                ? 'Item akan kembali ke antrian SPK biasa dan bisa diambil siapa saja.'
                : 'Item akan ditandai Batal permanen dan tidak akan diproses lagi.',
            icon: isAntre ? 'question' : 'warning',
            showCancelButton: true,
            confirmButtonColor: isAntre ? '#2563EB' : '#93481A',
            confirmButtonText: isAntre ? 'Ya, Kembalikan' : 'Ya, Batalkan',
            cancelButtonText: 'Kembali',
            customClass: { popup: 'rounded-2xl' }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `{{ url('spk') }}/${id}/resolve-pending`;
                form.innerHTML = `@csrf @method('PATCH')<input type="hidden" name="tindakan" value="${tindakan}">`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    function ambilPekerjaan(id) {
        Swal.fire({
            title: 'Ambil Pekerjaan Ini?',
            text: 'Item akan pindah dari antrean SPK ke halaman Produksi.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563EB',
            confirmButtonText: 'Ya, Ambil',
            cancelButtonText: 'Batal',
            customClass: { popup: 'rounded-2xl' }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `{{ url('spk') }}/${id}/ambil`;
                form.innerHTML = `@csrf @method('PATCH')`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>
@endpush
