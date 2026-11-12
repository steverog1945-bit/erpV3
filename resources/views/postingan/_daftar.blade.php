{{-- Komponen daftar Info & Panduan (banner, kategori, unggulan, grid, pagination). Dipakai di /postingan dan tab Info pada Dashboard.
     Variabel: $basisUrl (alamat halaman), $paramTetap (mis. ['tab' => 'info']), dan data dari PostinganController::dataDaftar(). --}}
@php
    $basisUrl = $basisUrl ?? route('postingan.index');
    $paramTetap = $paramTetap ?? [];
    $u = fn (array $p = []) => $basisUrl . '?' . http_build_query(array_filter(array_merge($paramTetap, $p), fn ($v) => $v !== null && $v !== ''));

    $warnaKartu = ['bg-indigo-500', 'bg-emerald-500', 'bg-amber-500', 'bg-sky-500', 'bg-rose-500'];
    $tanpaFilter = !$kategoriSlug && $tag === '' && $cari === '' && !$status;
    $labelStatus = ['Terbit' => 'bg-emerald-50 text-emerald-600', 'Terjadwal' => 'bg-sky-50 text-sky-600', 'Draft' => 'bg-amber-50 text-amber-600'];
@endphp

    {{-- ================= HERO ================= --}}
    <div class="relative overflow-hidden rounded-2xl bg-indigo-600 px-6 sm:px-12 py-12 mb-8 shadow-sm hero-postingan">
        <div class="absolute -right-16 -top-16 w-72 h-72 rounded-full bg-white/10"></div>
        <div class="absolute right-24 -bottom-24 w-64 h-64 rounded-full bg-white/10"></div>
        <div class="relative max-w-3xl">
            <p class="text-[11px] font-medium uppercase tracking-[0.25em] text-indigo-100">Papan Informasi Toko</p>
            <h1 class="text-3xl sm:text-4xl font-medium text-white tracking-tight mt-2 leading-tight">Info &amp; Panduan</h1>
            <p class="text-indigo-100 mt-3 text-sm sm:text-base leading-relaxed">Pengumuman, SOP kerja, tips finishing, dan hasil karya terbaik. Semua tim membaca dari satu tempat.</p>

            <form method="GET" action="{{ $basisUrl }}" class="mt-7 flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-3" data-no-autosubmit>
                @foreach($paramTetap as $pn => $pv)<input type="hidden" name="{{ $pn }}" value="{{ $pv }}">@endforeach
                @if($kategoriSlug)<input type="hidden" name="kategori" value="{{ $kategoriSlug }}">@endif
                <div class="relative w-full md:w-64 lg:w-80">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="cari" value="{{ $cari }}" placeholder="Cari judul, isi, atau tag..." class="w-full rounded-2xl border-none bg-white py-3.5 pl-12 pr-4 text-sm font-medium text-slate-700 shadow-lg focus:ring-4 focus:ring-white/30">
                </div>
                <button type="submit" class="rounded-2xl bg-slate-900/80 hover:bg-slate-900 text-white px-7 py-3.5 text-xs font-medium uppercase tracking-widest transition-all" title="Cari postingan">Cari</button>
                {{-- Satu baris dengan pencarian (bukan ditempel absolut di pojok) supaya tidak menabrak kolom cari
                     saat layar menyempit; di layar kecil turun ke baris sendiri. --}}
                @if($pengelola)
                <a href="{{ route('postingan.create') }}" title="Tulis postingan baru" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-white text-indigo-600 px-6 py-3.5 text-xs font-medium uppercase tracking-widest shadow-lg hover:bg-indigo-50 transition-all whitespace-nowrap">
                    <i class="fas fa-pen-nib"></i> Tulis Postingan
                </a>
                @endif
            </form>
        </div>
    </div>

    {{-- ================= KATEGORI & FILTER ================= --}}
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-8">
        <div class="flex flex-wrap gap-2">
            <a href="{{ $u(['cari' => $cari, 'status' => $status]) }}" class="px-4 py-2 rounded-xl text-[11px] font-medium uppercase tracking-widest transition-all {{ !$kategoriSlug ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white border border-slate-100 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600' }}">Semua</a>
            @foreach($kategoriList as $k)
                <a href="{{ $u(['kategori' => $k->slug, 'cari' => $cari, 'status' => $status]) }}" title="{{ $k->posts_count }} postingan" class="px-4 py-2 rounded-xl text-[11px] font-medium uppercase tracking-widest transition-all {{ $kategoriSlug === $k->slug ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white border border-slate-100 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600' }}">{{ $k->nama }} <span class="opacity-60">{{ $k->posts_count }}</span></a>
            @endforeach
        </div>
        @if($pengelola)
        <div class="flex flex-wrap gap-2 items-center">
            <span class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Status:</span>
            @foreach([null => 'Semua', 'terbit' => 'Terbit', 'terjadwal' => 'Terjadwal', 'draft' => 'Draft'] as $sv => $sl)
                <a href="{{ $u(['kategori' => $kategoriSlug, 'cari' => $cari, 'status' => $sv]) }}" class="px-3 py-1.5 rounded-lg text-[10px] font-medium uppercase tracking-widest {{ $status === $sv ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}">{{ $sl }}</a>
            @endforeach
        </div>
        @endif
    </div>

    @if($tag !== '' || $cari !== '' || $kategoriSlug)
        <p class="text-xs font-medium text-slate-500 mb-5">Menyaring:
            @if($cari !== '')<span class="px-2 py-1 rounded-md bg-indigo-50 text-indigo-600">cari &ldquo;{{ $cari }}&rdquo;</span>@endif
            @if($tag !== '')<span class="px-2 py-1 rounded-md bg-indigo-50 text-indigo-600">tag #{{ $tag }}</span>@endif
            @if($kategoriSlug)<span class="px-2 py-1 rounded-md bg-indigo-50 text-indigo-600">kategori {{ $kategoriSlug }}</span>@endif
            <a href="{{ $u() }}" class="ml-2 text-rose-500 font-medium uppercase tracking-widest hover:underline" title="Hapus semua penyaring">Reset</a>
        </p>
    @endif

    {{-- ================= UNGGULAN ================= --}}
    @if($unggulan)
    <a href="{{ route('postingan.show', $unggulan->slug) }}" class="group grid grid-cols-1 lg:grid-cols-2 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-8 hover:shadow-xl transition-all">
        <div class="relative min-h-[240px] lg:min-h-[340px] {{ $warnaKartu[$unggulan->id % count($warnaKartu)] }}">
            @if($unggulan->url_sampul)<img src="{{ $unggulan->url_sampul }}" alt="{{ $unggulan->judul }}" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">@endif
            @if($unggulan->disematkan)<span class="absolute left-5 top-5 inline-flex items-center gap-1.5 rounded-full bg-amber-400/90 px-3 py-1.5 text-[10px] font-medium uppercase tracking-widest text-amber-950"><i class="fas fa-thumbtack"></i> Disematkan</span>@endif
        </div>
        <div class="p-8 lg:p-12 flex flex-col justify-center">
            <p class="text-[11px] font-medium uppercase tracking-[0.2em] text-indigo-500">Terbaru{{ $unggulan->kategori ? ' Â· ' . $unggulan->kategori->nama : '' }}</p>
            <h2 class="text-2xl lg:text-3xl font-medium text-slate-800 mt-3 leading-snug group-hover:text-indigo-600 transition-colors">{{ $unggulan->judul }}</h2>
            <p class="text-sm text-slate-500 mt-3 leading-relaxed line-clamp-3">{{ $unggulan->ringkasan }}</p>
            <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] font-medium text-slate-400">
                <span><i class="far fa-user mr-1"></i>{{ $unggulan->nama_penulis }}</span>
                <span><i class="far fa-calendar mr-1"></i>{{ optional($unggulan->terbit_at)->translatedFormat('d M Y') }}</span>
                <span><i class="far fa-clock mr-1"></i>{{ $unggulan->menit_baca }} menit baca</span>
            </div>
            <span class="mt-6 inline-flex items-center gap-2 text-[11px] font-medium uppercase tracking-widest text-indigo-600">Baca selengkapnya</span>
        </div>
    </a>
    @endif

    {{-- ================= GRID KARTU ================= --}}
    @if($posts->count())
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @foreach($posts as $p)
        <article class="group relative flex flex-col bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all">
            <a href="{{ route('postingan.show', $p->slug) }}" class="block relative aspect-[16/9] {{ $warnaKartu[$p->id % count($warnaKartu)] }} overflow-hidden">
                @if($p->url_sampul)
                    <img src="{{ $p->url_sampul }}" alt="{{ $p->judul }}" loading="lazy" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                @else
                    <span class="absolute inset-0 flex items-center justify-center text-6xl font-medium text-white/30">{{ mb_strtoupper(mb_substr($p->judul, 0, 1)) }}</span>
                @endif
                @if($p->kategori)<span class="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-[10px] font-medium uppercase tracking-widest text-indigo-600">{{ $p->kategori->nama }}</span>@endif
                @if($p->disematkan)<span class="absolute right-4 top-4 w-8 h-8 rounded-full bg-white/90 text-amber-500 flex items-center justify-center" title="Disematkan"><i class="fas fa-thumbtack text-xs"></i></span>@endif
            </a>
            <div class="p-6 flex flex-col flex-1">
                @if($pengelola && $p->status_tampil !== 'Terbit')
                    <span class="self-start mb-2 px-2 py-0.5 rounded-md text-[10px] font-medium uppercase tracking-widest {{ $labelStatus[$p->status_tampil] }}">{{ $p->status_tampil }}{{ $p->status_tampil === 'Terjadwal' ? ' Â· ' . $p->terbit_at->translatedFormat('d M H:i') : '' }}</span>
                @endif
                <h3 class="text-base font-medium text-slate-800 leading-snug group-hover:text-indigo-600 transition-colors line-clamp-2"><a href="{{ route('postingan.show', $p->slug) }}">{{ $p->judul }}</a></h3>
                <p class="text-[13px] text-slate-500 mt-2 leading-relaxed line-clamp-3">{{ $p->ringkasan }}</p>
                <div class="mt-auto pt-5 flex items-center justify-between text-[11px] font-medium text-slate-400">
                    <span class="truncate"><i class="far fa-user mr-1"></i>{{ $p->nama_penulis }}</span>
                    <span class="shrink-0"><i class="far fa-calendar mr-1"></i>{{ optional($p->terbit_at)->translatedFormat('d M Y') ?? 'Draft' }} Â· {{ $p->menit_baca }} mnt</span>
                </div>
                @if($pengelola)
                <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-end gap-2">
                    <a href="{{ route('postingan.edit', $p->id) }}" title="Edit postingan" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-amber-500 hover:bg-amber-50 transition-all"><i class="far fa-edit text-sm"></i></a>
                    <form method="POST" action="{{ route('postingan.destroy', $p->id) }}" onsubmit="return konfirmasiHapusPostingan(event, this, {{ json_encode($p->judul) }})">@csrf @method('DELETE')
                        <button type="submit" title="Hapus postingan" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-all"><i class="far fa-trash-alt text-sm"></i></button>
                    </form>
                </div>
                @endif
            </div>
        </article>
        @endforeach
    </div>

    <div class="mt-8 bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <x-table.pagination :paginator="$posts" />
    </div>
    @elseif(!$unggulan)
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm py-12">
        <x-empty-state :title="$tanpaFilter ? 'Belum ada postingan' : 'Tidak ada postingan yang cocok'" :description="$tanpaFilter ? ($pengelola ? 'Klik Tulis Postingan untuk membuat pengumuman pertama.' : 'Postingan akan muncul di sini.') : 'Ubah atau reset penyaring.'" />
    </div>
    @endif

    {{-- ================= TAG POPULER ================= --}}
    @if($tagPopuler->isNotEmpty())
    <div class="mt-10 flex flex-wrap items-center gap-2">
        <span class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mr-1">Tag populer:</span>
        @foreach($tagPopuler as $tg)
            <a href="{{ $u(['tag' => $tg]) }}" class="px-3 py-1.5 rounded-full bg-white border border-slate-100 text-[11px] font-medium text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-all">#{{ $tg }}</a>
        @endforeach
    </div>
    @endif

@once
@push('scripts')
<script>
    function konfirmasiHapusPostingan(e, form, judul) {
        e.preventDefault();
        Swal.fire({ title: 'Hapus postingan?', html: `<b>${judul.replace(/[<>&]/g, '')}</b> akan dihapus dari daftar.`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#93481A', confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal', customClass: { popup: 'rounded-2xl' } })
            .then(r => { if (r.isConfirmed) form.submit(); });
        return false;
    }
</script>
@endpush
@endonce

