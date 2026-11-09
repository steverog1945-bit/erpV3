@props(['error' => null])

@php
    $base = 'w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-normal text-slate-900 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20';
    $errorClass = $error ? ' border-rose-300 bg-rose-50/50' : '';
@endphp

<input {{ $attributes->merge(['class' => $base . $errorClass]) }}>
