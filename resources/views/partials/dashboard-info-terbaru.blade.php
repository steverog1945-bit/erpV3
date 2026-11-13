{{-- Kartu "Info Terbaru" (postingan Info & Panduan) di dashboard: yang disematkan dulu, lalu terbaru. --}}
@php
    $bolehInfo = \App\Support\Izin::boleh(auth()->user(), [67], 'view');
    $infoTerbaru = $bolehInfo && \Illuminate\Support\Facades\Schema::hasTable('posts')
        ? \App\Models\Post::with('kategori')->terbit()->orderByDesc('disematkan')->orderByDesc('terbit_at')->limit(3)->get()
        : collect();
    $warnaInfo = ['bg-indigo-500', 'bg-emerald-500', 'bg-amber-500', 'bg-sky-500', 'bg-rose-500'];
@endphp
@if($infoTerbaru->isNotEmpty())
<section class="mt-8" id="info-terbaru">
    <div class="flex items-end justify-between mb-4">
        <div>
            <p class="text-[10px] font-medium text-indigo-500 uppercase tracking-[0.2em]">Info &amp; Panduan</p>
            <h2 class="text-lg font-medium text-slate-800">Info Terbaru</h2>
        </div>
        <a href="{{ $lihatSemuaUrl ?? route('dashboard', ['tab' => 'info']) }}" title="Lihat semua postingan" class="text-[11px] font-medium text-indigo-600 uppercase tracking-widest hover:underline">Lihat semua</a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        @foreach($infoTerbaru as $ip)
        <a href="{{ route('postingan.show', $ip->slug) }}" class="group flex gap-4 bg-white rounded-lg border border-slate-100 shadow-sm p-4 hover:shadow-md transition-all">
            <div class="relative w-24 h-24 shrink-0 rounded-lg overflow-hidden {{ $warnaInfo[$ip->id % count($warnaInfo)] }}">
                @if($ip->url_sampul)<img src="{{ $ip->url_sampul }}" alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover">@endif
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-medium uppercase tracking-widest text-indigo-500">@if($ip->disematkan)<i class="fas fa-thumbtack text-amber-500"></i> @endif{{ optional($ip->kategori)->nama ?? 'Info' }}</p>
                <h3 class="text-sm font-medium text-slate-800 leading-snug mt-1 line-clamp-2 group-hover:text-indigo-600 transition-colors">{{ $ip->judul }}</h3>
                <p class="text-[11px] text-slate-400 mt-1.5">{{ optional($ip->terbit_at)->diffForHumans() }} &middot; {{ $ip->menit_baca }} mnt baca</p>
            </div>
        </a>
        @endforeach
    </div>
</section>
@endif
