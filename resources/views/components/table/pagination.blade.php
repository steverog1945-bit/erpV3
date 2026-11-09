@props(['paginator'])

@if($paginator instanceof \Illuminate\Contracts\Pagination\Paginator || $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 px-6 py-4 border-t border-slate-100">
        <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">
            @if($paginator->total() > 0)
                Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} data
            @else
                Tidak ada data
            @endif
        </p>

        <div class="flex flex-col sm:flex-row flex-wrap items-center justify-center gap-3 max-w-full">
            {{-- Lompat langsung ke halaman berapa pun - link bernomor bawaan
                 Laravel cuma menampilkan JENDELA halaman di sekitar posisi
                 sekarang (+ awal/akhir), jadi kalau data banyak (puluhan
                 halaman) tetap harus klik "Next" berkali-kali untuk sampai
                 ke halaman yang jauh. Form GET biasa (bukan JS) - query
                 string lain (filter/search/tanggal) dipertahankan lewat
                 hidden input supaya lompat halaman tidak mereset filter
                 yang sedang aktif. --}}
            @if($paginator->lastPage() > 1)
            <form method="GET" class="flex items-center gap-1.5">
                @foreach(request()->except('page') as $namaField => $nilai)
                    @foreach((array) $nilai as $v)
                        <input type="hidden" name="{{ is_array($nilai) ? $namaField . '[]' : $namaField }}" value="{{ $v }}">
                    @endforeach
                @endforeach
                <label class="text-[10px] font-medium text-slate-400 uppercase tracking-widest whitespace-nowrap">Ke Halaman</label>
                <input type="number" name="page" min="1" max="{{ $paginator->lastPage() }}" value="{{ $paginator->currentPage() }}"
                    class="w-16 text-center text-xs bg-slate-50 border-none rounded-lg py-1.5 px-2 font-medium focus:ring-2 focus:ring-indigo-500/20">
                <span class="text-[10px] text-slate-400 whitespace-nowrap">/ {{ $paginator->lastPage() }}</span>
                <button type="submit" class="text-[10px] font-medium text-indigo-600 hover:text-indigo-700 uppercase px-2 py-1.5 rounded-lg hover:bg-indigo-50 transition-all" aria-label="Ke halaman" title="Ke halaman">
                    <i class="fas fa-arrow-right-to-bracket"></i>
                </button>
            </form>
            @endif

            {{-- Nomor halaman dirender manual dari UrlWindow (first/slider/
                 last) - MENGGANTI $paginator->links() bawaan Laravel, yang
                 (di view default tailwind) merender DUA blok pagination
                 sekaligus (tombol Previous/Next mobile + "Showing X to Y
                 of Z results" berbahasa Inggris dengan nomor halaman
                 versi desktop) dan tampil DOBEL/tumpang tindih dengan
                 baris ringkasan Indonesia di atas. Dibangun langsung dari
                 UrlWindow (bukan string-matching label "Previous"/"Next"
                 yang rapuh terhadap locale/terjemahan) supaya cuma satu
                 baris nomor halaman, gaya & bahasa konsisten dengan
                 seluruh aplikasi.  --}}
            @if($paginator->lastPage() > 1)
            @php
                // UrlWindow::make() mengembalikan 'first'/'slider'/'last',
                // MASING-MASING array [nomor_halaman => url] (bukan
                // [url => label]) - dirakit di sini jadi daftar list biasa
                // [{tipe, nomor, url}, ...] supaya tidak ada risiko key
                // array bentrok (nomor halaman vs key auto-index "...").
                $paginatorBerQuery = $paginator->appends(request()->query());
                $jendela = \Illuminate\Pagination\UrlWindow::make($paginatorBerQuery);
                $segmenHalaman = collect();
                foreach (($jendela['first'] ?? []) as $nomor => $url) {
                    $segmenHalaman->push(['tipe' => 'halaman', 'nomor' => $nomor, 'url' => $url]);
                }
                if ($jendela['slider'] ?? null) {
                    $segmenHalaman->push(['tipe' => 'gap']);
                    foreach ($jendela['slider'] as $nomor => $url) {
                        $segmenHalaman->push(['tipe' => 'halaman', 'nomor' => $nomor, 'url' => $url]);
                    }
                }
                if ($jendela['last'] ?? null) {
                    $segmenHalaman->push(['tipe' => 'gap']);
                    foreach ($jendela['last'] as $nomor => $url) {
                        $segmenHalaman->push(['tipe' => 'halaman', 'nomor' => $nomor, 'url' => $url]);
                    }
                }
            @endphp
            {{-- flex-wrap: di layar sempit nomor halaman turun ke baris berikut, tidak melebar keluar layar. --}}
            <div class="flex flex-wrap items-center justify-center gap-1 max-w-full">
                <a href="{{ $paginatorBerQuery->previousPageUrl() ?? '#' }}" @class(['w-8 h-8 flex items-center justify-center rounded-lg text-xs', 'text-slate-500 pointer-events-none' => $paginatorBerQuery->onFirstPage(), 'text-slate-500 hover:bg-slate-50' => !$paginatorBerQuery->onFirstPage()])>
                    <i class="fas fa-chevron-left text-[10px]"></i>
                </a>
                @foreach($segmenHalaman as $segmen)
                    @if($segmen['tipe'] === 'gap')
                        <span class="w-8 h-8 flex items-center justify-center text-xs text-slate-500">&hellip;</span>
                    @else
                        <a href="{{ $segmen['url'] }}" @class(['w-8 h-8 flex items-center justify-center rounded-lg text-xs font-medium', 'bg-indigo-600 text-white' => $segmen['nomor'] == $paginatorBerQuery->currentPage(), 'text-slate-500 hover:bg-slate-50' => $segmen['nomor'] != $paginatorBerQuery->currentPage()])>
                            {{ $segmen['nomor'] }}
                        </a>
                    @endif
                @endforeach
                <a href="{{ $paginatorBerQuery->nextPageUrl() ?? '#' }}" @class(['w-8 h-8 flex items-center justify-center rounded-lg text-xs', 'text-slate-500 pointer-events-none' => !$paginatorBerQuery->hasMorePages(), 'text-slate-500 hover:bg-slate-50' => $paginatorBerQuery->hasMorePages()])>
                    <i class="fas fa-chevron-right text-[10px]"></i>
                </a>
            </div>
            @endif
        </div>
    </div>
@endif
