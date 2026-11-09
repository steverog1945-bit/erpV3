@extends('layouts.app')

@section('content')
@php
    $warnaSampul = ['bg-indigo-500', 'bg-emerald-500', 'bg-amber-500', 'bg-sky-500', 'bg-rose-500'][$post->id % 5];
    $izinEdit = \App\Support\Izin::boleh(auth()->user(), [67], 'edit');
    $izinHapus = \App\Support\Izin::boleh(auth()->user(), [67], 'delete');
@endphp
{{-- Font gaya majalah (Nunito, Lora, Playfair) dilayani sendiri.
     Hanya halaman Postingan yang memakainya, jadi CSS-nya dimuat di
     sini dan bukan di layout - lihat catatan lengkap di
     public/vendor/fonts/majalah.css. --}}
<link href="{{ asset('vendor/fonts/majalah.css') }}" rel="stylesheet">
<div class="cetak-halaman w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1100px] mx-auto">

    <a href="{{ $pengelola ? route('postingan.index') : route('dashboard', ['tab' => 'info']) }}" class="no-print inline-flex items-center gap-2 text-[11px] font-medium uppercase tracking-widest text-slate-400 hover:text-indigo-600 mb-5" title="Kembali ke daftar postingan"><i class="fas fa-arrow-left"></i> Info &amp; Panduan</a>

    {{-- ================= HERO ================= --}}
    <header class="hero-postingan relative overflow-hidden rounded-2xl {{ $warnaSampul }} min-h-[300px] sm:min-h-[400px] flex items-end shadow-xl">
        @if($post->url_sampul)<img src="{{ $post->url_sampul }}" alt="{{ $post->judul }}" class="absolute inset-0 w-full h-full object-cover">@endif
        <div class="absolute inset-0 bg-slate-900/55"></div>
        <div class="relative p-6 sm:p-10 w-full">
            <div class="flex flex-wrap items-center gap-2 mb-3">
                @if($post->kategori)<a href="{{ route('postingan.index', ['kategori' => $post->kategori->slug]) }}" class="rounded-full bg-white/90 px-3 py-1 text-[10px] font-medium uppercase tracking-widest text-indigo-600">{{ $post->kategori->nama }}</a>@endif
                @if($post->disematkan)<span class="rounded-full bg-amber-400/90 px-3 py-1 text-[10px] font-medium uppercase tracking-widest text-amber-950"><i class="fas fa-thumbtack"></i> Disematkan</span>@endif
                @if($post->status_tampil !== 'Terbit')<span class="rounded-full bg-slate-900/70 px-3 py-1 text-[10px] font-medium uppercase tracking-widest text-white">{{ $post->status_tampil }}</span>@endif
            </div>
            <h1 class="text-2xl sm:text-4xl font-medium text-white leading-tight tracking-tight max-w-3xl">{{ $post->judul }}</h1>
            <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-1 text-[12px] font-medium text-white/80">
                <span><i class="far fa-user mr-1.5"></i>{{ $post->nama_penulis }}</span>
                <span><i class="far fa-calendar mr-1.5"></i>{{ optional($post->terbit_at)->translatedFormat('d F Y') ?? 'Belum terbit' }}</span>
                <span><i class="far fa-clock mr-1.5"></i>{{ $post->menit_baca }} menit baca</span>
                <span><i class="far fa-eye mr-1.5"></i>{{ number_format($post->dibaca, 0, ',', '.') }} dibaca</span>
            </div>
        </div>
    </header>

    {{-- Kop khusus kertas (hanya muncul saat dicetak) --}}
    <div class="cetak-kop">
        <div class="kop-atas"><span>{{ $appSetting->nama_aplikasi ?? config('app.name') }} &middot; Info &amp; Panduan</span><span class="kanan">{{ optional($post->terbit_at)->translatedFormat('d F Y') ?? now()->translatedFormat('d F Y') }}</span></div>
        @if($post->kategori)<div class="kop-kategori">{{ $post->kategori->nama }}</div>@endif
        <h1>{{ $post->judul }}</h1>
        @if($post->ringkasan)<p class="kop-dek">{{ $post->ringkasan }}</p>@endif
        <div class="kop-meta">Oleh {{ $post->nama_penulis }} &nbsp;&bull;&nbsp; {{ $post->menit_baca }} menit baca</div>
        @if($post->url_sampul)<img src="{{ $post->url_sampul }}" alt="" class="kop-sampul">@endif
    </div>

    {{-- ================= AKSI ================= --}}
    <div class="no-print flex flex-wrap items-center justify-between gap-3 mt-6">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="salinTautanPostingan()" title="Salin tautan postingan ini" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-100 text-[11px] font-medium uppercase tracking-widest text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-all"><i class="far fa-copy"></i> Salin Tautan</button>
            <a href="https://wa.me/?text={{ urlencode($post->judul . ' - ' . route('postingan.show', $post->slug)) }}" target="_blank" rel="noopener" title="Bagikan lewat WhatsApp" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-100 text-[11px] font-medium uppercase tracking-widest text-slate-500 hover:bg-emerald-50 hover:text-emerald-600 transition-all"><i class="fab fa-whatsapp"></i> WhatsApp</a>
            <button type="button" onclick="window.print()" title="Cetak postingan" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white border border-slate-100 text-[11px] font-medium uppercase tracking-widest text-slate-500 hover:bg-slate-50 transition-all"><i class="fas fa-print"></i> Cetak</button>
        </div>
        <div class="flex items-center gap-2">
            @if($izinEdit)<x-button href="{{ route('postingan.edit', $post->id) }}" variant="warning" size="sm"><i class="far fa-edit"></i> Edit</x-button>@endif
            @if($izinHapus)
            <form method="POST" action="{{ route('postingan.destroy', $post->id) }}" onsubmit="return konfirmasiHapusPostingan(event, this)">@csrf @method('DELETE')
                <x-button type="submit" variant="danger" size="sm"><i class="far fa-trash-alt"></i> Hapus</x-button>
            </form>
            @endif
        </div>
    </div>

    {{-- ================= ISI ================= --}}
    <article class="cetak-artikel bg-white rounded-2xl border border-slate-100 shadow-sm mt-6 px-6 sm:px-12 py-10">
        @if($post->ringkasan)<p class="ringkasan-post text-lg text-slate-500 leading-relaxed font-medium border-l-4 border-indigo-400 pl-5 mb-8">{{ $post->ringkasan }}</p>@endif
        <div class="prose-post" lang="id">{!! $post->isi_html !!}</div>

        @if(count($post->daftar_tag))
        <div class="no-print mt-10 pt-6 border-t border-slate-100 flex flex-wrap items-center gap-2">
            @foreach($post->daftar_tag as $tg)
                <a href="{{ route('postingan.index', ['tag' => $tg]) }}" class="tag-post px-3 py-1.5 rounded-full bg-slate-50 text-[11px] font-medium text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-all">#{{ $tg }}</a>
            @endforeach
        </div>
        @endif
    </article>
    <div class="cetak-kaki"><span>{{ $appSetting->nama_aplikasi ?? config('app.name') }}</span><span>{{ route('postingan.show', $post->slug) }}</span><span>Dicetak {{ now()->translatedFormat('d F Y') }}</span></div>

    {{-- ================= SEBELUM / BERIKUT ================= --}}
    @if($sebelumnya || $berikutnya)
    <div class="postingan-navigasi grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
        @if($sebelumnya)<a href="{{ route('postingan.show', $sebelumnya->slug) }}" class="rounded-2xl bg-white border border-slate-100 p-5 hover:shadow-md transition-all"><p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest"><i class="fas fa-arrow-left mr-1"></i> Sebelumnya</p><p class="text-sm font-medium text-slate-700 mt-1 line-clamp-2">{{ $sebelumnya->judul }}</p></a>@else<span></span>@endif
        @if($berikutnya)<a href="{{ route('postingan.show', $berikutnya->slug) }}" class="rounded-2xl bg-white border border-slate-100 p-5 text-right hover:shadow-md transition-all"><p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Berikutnya <i class="fas fa-arrow-right ml-1"></i></p><p class="text-sm font-medium text-slate-700 mt-1 line-clamp-2">{{ $berikutnya->judul }}</p></a>@endif
    </div>
    @endif

    {{-- ================= TERKAIT ================= --}}
    @if($terkait->isNotEmpty())
    <section class="postingan-terkait mt-10">
        <h2 class="text-base font-medium text-slate-800 mb-4">Baca juga</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @foreach($terkait as $t)
            <a href="{{ route('postingan.show', $t->slug) }}" class="group rounded-2xl bg-white border border-slate-100 overflow-hidden hover:shadow-md transition-all">
                <div class="aspect-[16/9] bg-indigo-500 relative">@if($t->url_sampul)<img src="{{ $t->url_sampul }}" alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover">@endif</div>
                <p class="p-4 text-[13px] font-medium text-slate-700 group-hover:text-indigo-600 line-clamp-2">{{ $t->judul }}</p>
            </a>
            @endforeach
        </div>
    </section>
    @endif
</div>

@push('scripts')
<script>
    function salinTautanPostingan() {
        navigator.clipboard.writeText(@json(route('postingan.show', $post->slug))).then(() => Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Tautan disalin', timer: 1800, showConfirmButton: false }));
    }
    function konfirmasiHapusPostingan(e, form) {
        e.preventDefault();
        Swal.fire({ title: 'Hapus postingan ini?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#93481A', confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal', customClass: { popup: 'rounded-2xl' } })
            .then(r => { if (r.isConfirmed) form.submit(); });
        return false;
    }
</script>
@endpush
@endsection
