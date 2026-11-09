{{-- Satu kartu per tulisan. Dipakai saat halaman dimuat dan saat saring/cari/muat-lagi (dikirim sebagai HTML). --}}
@foreach($posts as $p)
<article class="bl-kartu">
    <a href="{{ route('publik.baca', $p->slug) }}" class="bl-kartu-taut">
        @if($p->url_sampul)
            <div class="bl-gambar"><img src="{{ $p->url_sampul }}" alt="" loading="lazy" decoding="async"></div>
        @endif
        <p class="bl-meta">
            @if($p->kategori)<span>{{ $p->kategori->nama }}</span>@endif
            <time datetime="{{ optional($p->terbit_at)->toDateString() }}">{{ optional($p->terbit_at)->locale('id')->translatedFormat('d F Y') }}</time>
        </p>
        <h3>{{ $p->judul }}</h3>
        @if($p->ringkasan)<p class="bl-ringkas">{{ $p->ringkasan }}</p>@endif
    </a>
</article>
@endforeach
