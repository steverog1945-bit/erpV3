@props([
    'variant' => 'primary', // primary | secondary | success | warning | danger | outline
    'size'    => 'default',  // default | sm | icon
    'type'    => 'button',
    'href'    => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-xl font-medium uppercase tracking-wider transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100';

    $variants = [
        'primary'   => 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm',
        'secondary' => 'bg-slate-100 hover:bg-slate-200 text-slate-500',
        'success'   => 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm',
        'warning'   => 'bg-amber-500 hover:bg-amber-600 text-white shadow-sm',
        'danger'    => 'bg-rose-500 hover:bg-rose-600 text-white shadow-sm',
        'outline'   => 'bg-transparent border border-slate-200 text-slate-500 hover:bg-slate-50',
        // Tanpa hover bawaan - dipakai untuk icon button di tabel yang tiap
        // instance-nya butuh warna hover berbeda (edit=amber, hapus=rose,
        // dst) lewat prop class, tanpa risiko rebutan specificity dgn variant.
        'ghost'     => 'bg-transparent text-slate-400',
    ];

    $sizes = [
        'default' => 'text-xs px-6 py-3',
        'sm'      => 'text-[10px] px-4 py-2',
        // Icon-only: bentuk persegi, tanpa tracking/uppercase pada slot ikon
        'icon'    => '!p-0 w-9 h-9 !rounded-lg normal-case tracking-normal',
    ];

    $classes = $base . ' ' . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['default']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
