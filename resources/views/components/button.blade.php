@props([
    'variant' => 'primary', // primary | secondary | success | warning | danger | outline
    'size'    => 'default',  // default | sm | icon
    'type'    => 'button',
    'href'    => null,
])

@php
    // OpenCode Desktop Aesthetic: compact, consistent, no excessive styling
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg font-medium text-[13px] tracking-normal transition-transform transition-background-color transition-border-color active:scale-[0.97] disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100';

    $variants = [
        'primary'   => 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm',
        'secondary' => 'bg-[var(--surface-secondary,#EFF3FB)] hover:bg-[var(--surface-tertiary,#E3E9F6)] text-[var(--text-primary,#0F1E3E)] border border-[var(--border-primary,#B9C3DD)]',
        'success'   => 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm',
        'warning'   => 'bg-amber-500 hover:bg-amber-600 text-white shadow-sm',
        'danger'    => 'bg-rose-500 hover:bg-rose-600 text-white shadow-sm',
        'outline'   => 'bg-transparent border border-[var(--border-primary,#CBD6EE)] text-[var(--text-primary,#0F1E3E)] hover:bg-[var(--surface-secondary,#F8FAFC)]',
        'ghost'     => 'bg-transparent text-[var(--text-secondary,#475569)] hover:text-[var(--text-primary,#0F172A)] hover:bg-[var(--surface-secondary,#EFF3FB)]',
    ];

    $sizes = [
        'default' => 'px-4 py-2',
        'sm'      => 'px-3 py-1.5 text-xs',
        'icon'    => '!p-0 w-8 h-8 !rounded-md',
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
