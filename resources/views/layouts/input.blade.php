@props(['error' => null])

@php
    $base = 'w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 shadow-inner';
    $errorClass = $error ? ' ring-2 ring-rose-200 bg-rose-50/50' : '';
@endphp

<input {{ $attributes->merge(['class' => $base . $errorClass]) }}>
