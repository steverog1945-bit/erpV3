@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-8 max-w-[1280px] mx-auto">

    {{-- HERO PREMIUM --}}
    <div class="relative overflow-hidden rounded-2xl border border-[#E7D9B6] bg-gradient-to-br from-[#1B1437] via-[#241A3F] to-[#2E2140] px-6 py-7 sm:px-10 sm:py-9 mb-6 shadow-[0_18px_48px_rgba(27,20,55,.35)]">
        <div aria-hidden="true" style="position:absolute; inset:0; background: radial-gradient(600px 260px at 82% -20%, rgba(247,205,107,.22), transparent 60%), radial-gradient(480px 300px at -8% 120%, rgba(227,168,59,.16), transparent 55%)"></div>
        <div class="relative flex flex-col md:flex-row md:items-center gap-6">
            <div class="flex-1 min-w-0">
                <span class="inline-flex items-center gap-2 rounded-full border border-[#F7CD6B]/40 bg-[#F7CD6B]/10 px-3 py-1 text-[10px] font-bold tracking-[.18em] text-[#F7CD6B] uppercase mb-4">Akses awal - semua gratis</span>
                <h1 class="text-3xl sm:text-4xl font-semibold text-[#FDF6E3] tracking-tight leading-[1.12]" style="letter-spacing:-.02em">Orion <span class="text-[#F7CD6B]">Premium</span></h1>
                <p class="mt-3 text-[14.5px] leading-relaxed text-[#C9BFA6] max-w-xl">Kemampuan ekstra untuk percetakan yang sedang tumbuh: pengingat bayar otomatis, pelacakan pelanggan tanpa telepon, analisis 30 hari, sampai prediksi bahan baku - semuanya tanpa biaya API dan tanpa perlu kartu kredit.</p>
                <div class="mt-5 flex flex-wrap items-center gap-2 text-[10.5px] font-semibold uppercase tracking-[.14em] text-[#A99F86]">
                    <span class="inline-flex items-center gap-1.5 rounded-md border border-white/10 bg-white/5 px-2.5 py-1.5"><i class="fas fa-check text-[#F7CD6B]"></i> Tanpa API</span>
                    <span class="inline-flex items-center gap-1.5 rounded-md border border-white/10 bg-white/5 px-2.5 py-1.5"><i class="fas fa-check text-[#F7CD6B]"></i> Tanpa biaya</span>
                    <span class="inline-flex items-center gap-1.5 rounded-md border border-white/10 bg-white/5 px-2.5 py-1.5"><i class="fas fa-check text-[#F7CD6B]"></i> Data tetap di server Anda</span>
                </div>
            </div>
            <div class="shrink-0 flex flex-col items-center md:items-end gap-3">
                <div style="width:96px;height:96px;border-radius:28px;background:linear-gradient(150deg,#F7CD6B,#C98A1B);display:grid;place-items:center;box-shadow:0 14px 34px rgba(201,138,27,.4), inset 0 2px 0 rgba(255,255,255,.6)">
                    <svg viewBox="0 0 24 24" width="46" height="46" fill="none" aria-hidden="true"><path d="M3 8l4.5 3.5L12 4l4.5 7.5L21 8l-1.6 9.2a1.2 1.2 0 0 1-1.18 1.02H5.78A1.2 1.2 0 0 1 4.6 17.2L3 8z" stroke="#422A05" stroke-width="1.7" stroke-linejoin="round" stroke-linecap="round" fill="rgba(66,42,5,.16)"/></svg>
                </div>
                <span class="text-[10px] font-bold tracking-[.22em] text-[#8f8467] uppercase">Orion Grafika ERP</span>
            </div>
        </div>
    </div>

    {{-- STATUS RINGKAS --}}
    <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-sm font-semibold text-slate-800 tracking-tight">{{ $berjalan }} dari {{ count($fitur) }} fitur premium berjalan</h2>
            <p class="text-[11.5px] text-slate-500 mt-0.5">Semuanya bisa Anda coba sekarang. Penguncian (premium pay) menyusul setelah alur aktivasi siap.</p>
        </div>
        <a href="{{ route('premium.analisis') }}" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg bg-slate-900 text-white text-[12px] font-semibold tracking-wide hover:bg-slate-800 transition-colors">
            <i class="fas fa-chart-simple"></i> Buka Analisis Premium
        </a>
    </div>

    {{-- DAFTAR FITUR PER TIER --}}
    @php
        $tierUrutan = ['Operator', 'Insight', 'Enterprise', 'Lisensi'];
        $tierMeta = [
            'Operator'   => ['Untuk kasir & operator', 'Aksi harian yang menghemat waktu tiap shift.', '#2563EB'],
            'Insight'    => ['Untuk pemilik & manajer', 'Angka yang membantu keputusan, bukan hiasan.', '#0E9F6E'],
            'Enterprise' => ['Untuk operasional penuh', 'Kontrol, keamanan data, dan pelaporan tingkat pabrik.', '#7C3AED'],
            'Lisensi'    => ['Aktivasi & paywall', 'Bagaimana premium akan dikunci nanti.', '#C98A1B'],
        ];
    @endphp

    @foreach($tierUrutan as $tier)
        @php $items = collect($fitur)->where('tier', $tier)->values(); @endphp
        @if($items->isEmpty()) @continue @endif
        <section class="mb-7">
            <div class="flex items-center gap-3 mb-3">
                <span class="h-px flex-1 bg-slate-200"></span>
                <div class="text-center">
                    <span class="text-[10px] font-bold uppercase tracking-[.2em]" style="color: {{ $tierMeta[$tier][2] }}">{{ $tier }}</span>
                    <p class="text-[11px] text-slate-500">{{ $tierMeta[$tier][0] }} &middot; {{ $tierMeta[$tier][1] }}</p>
                </div>
                <span class="h-px flex-1 bg-slate-200"></span>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                @foreach($items as $f)
                    <div class="group flex items-start gap-4 rounded-2xl border border-slate-200/70 bg-white p-5 shadow-[0_1px_3px_rgba(15,30,62,.05)] hover:shadow-[0_10px_24px_rgba(15,30,62,.08)] hover:border-slate-300/80 transition-all duration-200">
                        <span class="shrink-0 w-11 h-11 rounded-xl grid place-items-center text-[15px]" style="background: color-mix(in srgb, {{ $tierMeta[$tier][2] }} 12%, white); color: {{ $tierMeta[$tier][2] }}">
                            <i class="{{ $f['ikon'] }}"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="text-[13.5px] font-semibold text-slate-800 leading-snug">{{ $f['nama'] }}</h3>
                                <span class="shrink-0 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-[.1em] {{ $f['status'] === 'Berjalan' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($f['status'] === 'Sudah tersedia' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-amber-50 text-amber-800 border border-amber-200') }}">{{ $f['status'] }}</span>
                            </div>
                            <p class="mt-1.5 text-[12px] leading-relaxed text-slate-500">{{ $f['desc'] }}</p>
                            @if($f['route'])
                                @if(\App\Support\Izin::bolehPath(auth()->user(), $f['route']))
                                    <a href="{{ $f['route'] }}" class="mt-3 inline-flex items-center gap-1.5 text-[11.5px] font-semibold text-indigo-600 hover:text-indigo-700 transition-colors">Buka fitur <i class="fas fa-arrow-right text-[10px] transition-transform group-hover:translate-x-0.5"></i></a>
                                @else
                                    <span class="mt-3 inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-400"><i class="fas fa-lock text-[10px]"></i> Perlu izin akses</span>
                                @endif
                            @else
                                <span class="mt-3 inline-flex items-center gap-1.5 text-[11.5px] font-semibold text-slate-400">Segera - butuh penambahan struktur data</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    {{-- CATATAN PAYWALL --}}
    <div class="rounded-2xl border border-slate-200/70 bg-slate-50 px-6 py-5 mb-6">
        <div class="flex items-start gap-4">
            <span class="shrink-0 w-9 h-9 rounded-lg bg-slate-900 grid place-items-center text-[#F7CD6B]"><i class="fas fa-lock text-sm"></i></span>
            <div>
                <h3 class="text-[13px] font-semibold text-slate-800">Tentang penguncian (premium pay) nanti</h3>
                <p class="mt-1 text-[12px] leading-relaxed text-slate-500">Urutan yang disepakati: fitur diterapkan dulu dan dipakai sampai stabil &rarr; baru sebagian fitur dikunci di balik aktivasi. Yang dikunci adalah nilai tambahnya (Insight &amp; Enterprise), bukan pekerjaan harian dasar (kasir, produksi, collecting). Tidak ada data pelanggan yang dikirim ke luar; semua tetap di server Anda.</p>
            </div>
        </div>
    </div>
</div>
@endsection
