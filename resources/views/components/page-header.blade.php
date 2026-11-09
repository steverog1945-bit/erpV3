@props([
    'title'    => '',
    'subtitle' => null,
])

<div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
    <div>
        <h1 class="text-3xl font-medium text-slate-800 tracking-tight">{{ $title }}</h1>
        @isset($subtitle)
            {{-- Rombak visual (tahap 2): subtitle kalimat biasa, bukan ALL-CAPS tembok - pesan lebih hangat & mudah dibaca. --}}
            <p class="text-slate-500 font-medium mt-1.5 text-[13px] leading-relaxed flex items-center gap-2">{{ $subtitle }}</p>
        @endisset
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-3">{{ $actions }}</div>
    @endisset
</div>
