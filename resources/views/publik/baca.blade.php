@extends('publik.layout-artikel')

@section('judul', $post->judul . ' - ' . \App\Models\AppSetting::current()->nama_aplikasi)
@section('deskripsi', $post->ringkasan)

@push('font')
    {{-- Font untuk isi artikel (prose) --}}
    <link rel="stylesheet" href="{{ asset('vendor/fonts/majalah.css') }}">
@endpush

@section('gaya')
    /* Scroll progress bar sudah ada di layout, gunakan id lp-progress */
    /* Article-specific tweaks */
    .lp-article .prose-post { color: var(--orion-brand-navy); line-height: 1.8; }
    .lp-article .prose-post img { border-radius: 8px; }
    /* Pastikan progress bar di header terlihat */
    .lp-progress { background: var(--orion-electric-violet); }
@endsection

@section('isi')
@php
    // "Semua tulisan" menuju daftar artikel di Landing Page Published.
    //
    // route('landing-page.public') dipakai, BUKAN url('/')+'#artikel':
    //   - Gate B ON  : '/' menampilkan Builder, tapi pada fallback (revisi
    //                  belum valid) '/' bisa jadi halaman lain.
    //   - Gate B OFF : '/' memakai homepage existing yang anchor-nya
    //                  'tulisan', bukan 'artikel' -> tautan mati.
    //
    // route('landing-page.public') selalu menampilkan snapshot PUBLISHED,
    // dan section 'posts' milik Builder memang ber-anchor 'artikel'.
    $lpBerandaArtikel = route('landing-page.public') . '#artikel';
@endphp
<div class="lp-article">
    <article>
        <header>
            <a class="lp-back" href="{{ $lpBerandaArtikel }}"><i class="fas fa-arrow-left" aria-hidden="true"></i> Semua tulisan</a>
            <p class="lp-meta">
                @if($post->kategori)<b>{{ $post->kategori->nama }}</b>@endif
                <time datetime="{{ optional($post->terbit_at)->toDateString() }}">{{ optional($post->terbit_at)->locale('id')->translatedFormat('d F Y') }}</time>
                <span>{{ $post->menit_baca }} menit baca</span>
            </p>
            <h1>{{ $post->judul }}</h1>
            @if($post->ringkasan)<p class="lp-dek">{{ $post->ringkasan }}</p>@endif
        </header>

        @if($post->url_sampul)
        <div class="lp-cover"><img src="{{ $post->url_sampul }}" alt=""></div>
        @endif

        <div class="lp-body"><div class="prose-post" lang="id">{!! $post->isi_html !!}</div></div>

        <div class="lp-ekor">
            <div class="lp-tags">
                @foreach($post->daftar_tag as $tg)<span class="lp-tag">#{{ $tg }}</span>@endforeach
            </div>
            <div class="lp-share">
                <button type="button" class="lp-share-btn" id="lp-salin"><i class="far fa-copy" aria-hidden="true"></i> <span>Salin tautan</span></button>
                <a class="lp-share-btn" target="_blank" rel="noopener noreferrer" href="https://wa.me/?text={{ urlencode($post->judul . ' ' . route('publik.baca', $post->slug)) }}"><i class="fab fa-whatsapp" aria-hidden="true"></i> WhatsApp</a>
            </div>
        </div>
    </article>

    @if($lain->isNotEmpty())
    <section class="lp-related">
        <h2>Tulisan lain</h2>
        <div class="lp-related-grid">
            @foreach($lain as $lp)
            <a class="lp-related-card lp-kartu-taut" href="{{ route('publik.baca', $lp->slug) }}">
                <div class="lp-related-media">
                    @if($lp->url_sampul)
                        <img src="{{ $lp->url_sampul }}" alt="">
                    @else
                        <div class="lp-portfolio-placeholder" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--orion-brand-light);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity:.8"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                        </div>
                    @endif
                </div>
                <div class="lp-related-body">
                    @if($lp->kategori)<span class="lp-related-kategori">{{ $lp->kategori->nama }}</span>@endif
                    <h3 class="lp-related-title">{{ $lp->judul }}</h3>
                    <p class="lp-related-meta">{{ optional($lp->terbit_at)->locale('id')->translatedFormat('d F Y') }} · {{ $lp->menit_baca }} menit</p>
                </div>
            </a>
            @endforeach
        </div>
    </section>
    @endif
</div>
@endsection

@push('skrip')
<script>
(function () {
    'use strict';

    // --- Scroll progress bar (menggunakan lp-progress di header) ---
    var progress = document.getElementById('lp-progress');
    var article = document.querySelector('.lp-body');
    function updateProgress() {
        if (!progress || !article) return;
        var r = article.getBoundingClientRect();
        var total = r.height - window.innerHeight * 0.6;
        var maju = total > 0 ? Math.min(1, Math.max(0, -r.top / total)) : (r.top < 0 ? 1 : 0);
        progress.style.transform = 'scaleX(' + maju + ')';
    }
    window.addEventListener('scroll', function () { requestAnimationFrame(updateProgress); }, { passive: true });
    updateProgress();

    // --- Copy link ---
    var salin = document.getElementById('lp-salin'), teks = salin ? salin.querySelector('span') : null;
    if (salin && teks) {
        salin.addEventListener('click', function () {
            var selesai = function () { teks.textContent = 'Tautan tersalin'; setTimeout(function () { teks.textContent = 'Salin tautan'; }, 1800); };
            if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(location.href).then(selesai); return; }
            var t = document.createElement('textarea'); t.value = location.href; document.body.appendChild(t); t.select();
            try { document.execCommand('copy'); selesai(); } finally { t.remove(); }
        });
    }

    // Smooth scroll anchor ditangani partial bersama di layout
    // (pengaturan.partials.smooth-scroll) - offset header sticky dinamis,
    // idempoten, dan menghormati prefers-reduced-motion. Handler di sini
    // sebelumnya membuat listener klik ganda pada elemen yang sama.
})();
</script>
@endpush