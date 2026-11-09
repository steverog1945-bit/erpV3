@extends('publik.layout')

@section('judul', 'Katalog Produk - Orion Grafika')
@section('deskripsi', 'Lihat katalog produk percetakan Orion Grafika: berbagai pilihan cetak untuk kebutuhan bisnis Anda.')

@push('font')
    <link rel="stylesheet" href="{{ asset('vendor/fonts/inter.css') }}">
    @stack('font')
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/all.min.css') }}">
@endpush

@section('gaya')
    /* ---- Brand ORION (Royal Indigo) ---- */
    :root {
        --kertas: #faf9f7;
        --putih: #ffffff;
        --garis: #e9e5df;
        --garis-kuat: #d6d0c8;
        --tinta: #1b1a18;
        --tinta-2: #55514b;
        --tinta-3: #6e6962;
        --deep-night: #15143F;
        --royal-indigo: #2E318E;
        --electric-violet: #4D55F8;
        --cta-orange: #FF6B17;
        --cyan-light: #B8EEFF;
        --sans: 'Inter', ui-sans-serif, system-ui, sans-serif;
    }

    * { box-sizing: border-box; }
    body {
        background: var(--kertas);
        color: var(--tinta);
        font-family: var(--sans);
        font-size: 16px;
        line-height: 1.6;
    }
    a { color: inherit; text-decoration: none; }
    img { display: block; max-width: 100%; }
    .bl-lebar { max-width: 1120px; margin: 0 auto; padding-left: 1.25rem; padding-right: 1.25rem; }

    /* ---- Sticky Header ---- */
    .bl-kepala {
        position: sticky;
        top: 0;
        z-index: 100;
        background: var(--kertas);
        border-bottom: 1px solid var(--garis-kuat);
    }
    .bl-kepala .bl-lebar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 64px;
        gap: 1rem;
    }
    .bl-merek { display: flex; align-items: center; gap: 0.75rem; font-weight: 600; letter-spacing: -0.01em; }
    .bl-merek img { height: 32px; width: auto; }
    .bl-kepala nav { display: flex; align-items: center; gap: 1.5rem; font-size: 0.9375rem; }
    .bl-taut { color: var(--tinta-2); transition: color 150ms ease; }
    @media (max-width: 900px) { .bl-nav-lebar { display: none; } }
    .bl-tombol {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        height: 40px;
        padding: 0 1.125rem;
        background: var(--royal-indigo);
        color: #fff !important;
        border: 0;
        font: 500 0.9375rem var(--sans);
        cursor: pointer;
        transition: transform 140ms var(--keluar), background-color 150ms ease;
    }
    .bl-tombol:active { transform: scale(0.97); }
    .bl-tombol-garis { background: transparent; color: var(--tinta) !important; border: 1px solid var(--garis-kuat); }
    @media (hover: hover) {
        .bl-taut:hover { color: var(--tinta); }
        .bl-tombol:hover { background: var(--royal-indigo); }
        .bl-tombol-garis:hover { background: putih; border-color: var(--tinta); }
    }

    /* ---- Content ---- */
    .bl-heading { font-size: 2rem; font-weight: 700; margin-bottom 1rem; color: var(--deep-night); }
    .bl-subheading { color: var(--tinta-2); margin-bottom 1.5rem; font-size: 1.1rem; }
    .bl-card { background: var(--kertas); border: 1px solid var(--garis); border-radius: 12px; padding 2rem; margin-bottom 1.5rem; }
    .bl-card h3 { margin: 0 0 0.5rem; font-size: 1.25rem; font-weight: 600; color: var(--deep-night); }
    .bl-card p { margin: 0; color: var(--tinta-2); font-size: 0.9375rem; }
    .bl-empty { padding: 3rem 1rem; text-align: center; color: var(--tinta-3); }
    .bl-empty i { font-size: 3rem; color: var(--royal-indigo); margin-bottom 1rem; display: block; }
    .bl-tag { display: inline-flex; align-items: center; gap 0.25rem; background: var(--royal-indigo); color: #fff; padding 0.25rem 0.5rem; border-radius 4px; font-size 0.75rem; font-weight 600; margin-top 0.5rem; }
    @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }

    @yield('gaya')
@endsection

@section('isi')

<div class="bl-lebar">

    <!-- Header section dengan navigasi -->
    <header class="bl-kepala">
        <div class="bl-lebar">
            <a class="bl-merek" href="{{ url('/') }}">
                <span>{{ \App\Models\AppSetting::current()->nama_aplikasi }}</span>
            </a>
            <nav aria-label="Utama">
                <a class="bl-taut" href="{{ url('/') }}#layanan">Layanan</a>
                <a class="bl-taut" href="{{ url('/') }}#tentang">Tentang</a>
                <a class="bl-taut" href="{{ url('/katalog-produk') }}">Produk</a>
                <a class="bl-taut" href="{{ url('/') }}#portofolio">Portofolio</a>
                <a class="bl-taut" href="{{ url('/') }}#kontak">Kontak</a>
                <a class="bl-taut" href="{{ url('/') }}#faq">FAQ</a>
                <button type="button" class="bl-tombol" data-buka-masuk>Masuk Karyawan</button>
            </nav>
        </div>
    </header>

    <main>
        <section class="bl-heading">
            <h1>Katalog Produk Percetakan</h1>
            <p class="bl-subheading">Semua kebutuhan cetak, satu tempat</p>
        </section>

        @if($produk->isEmpty())
        <div class="bl-empty">
            <i class="fas fa-box-open"></i>
            <p>Belum ada produk publik yang tersedia</p>
            <p>Silakan hubungi tim Orion Grafika untuk daftar lengkap produk.</p>
        </div>
        @else
        <div class="grid grid-3" style="gap: 1.5rem;">
            @foreach($produk as $p)
            <article class="bl-card">
                <h3>{{ e($p->nama_produk) }}</h3>
                @if($p->keterangan)
                <p>{{ e($p->keterangan) }}</p>
                @endif
                <div style="margin-top: 1rem;">
                    <a href="{{ url('/katalog-produk/' . $p->id_produk) }}" class="bl-tombol-blur">Lihat Detail</a>
                </div>
            </article>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($produk->hasMorePages())
        <div class="bl-lebar" style="margin-top: 2rem; border-top: 1px solid var(--garis); padding-top: 2rem;">
            @if($produk->onFirstPage())
                <a href="{{ url('/katalog-produk') }}">Awal</a>
            @elseif ($produk->currentPage() > 1)
                <a href="?page=1">&laquo; Awal</a>
                <a href="?page={{ $produk->previousPageUrl() }}">{{ $produk->previousPageUrl() }}</a>
            @endif
            @if ($produk->hasMorePages())
                <a href="?page={{ $produk->nextPageUrl() }}">Selanjutnya &raquo;</a>
                <a href="?page={{ $produk->lastPage() }}">Akhir</a>
            @endif
            <span style="margin-left: 1rem; color: var(--tinta-3);">
                Halaman {{ $produk->currentPage() }} dari {{ $produk->lastPage() }}
            </span>
        </div>
        @endif
        @endif
    </main>
</div>

@push('skrip')
<script>
(function () {
    'use strict';
    // Smooth scroll untuk anchor navigasi
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            var targetId = this.getAttribute('href').substring(1);
            var targetElement = document.getElementById(targetId);
            if (!targetElement) return;
            var headerHeight = 64;
            var elementPosition = targetElement.getBoundingClientRect().top + window.pageYOffset;
            var offsetPosition = elementPosition - headerHeight;
            window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
        });
    });
})();
</script>
@endpush