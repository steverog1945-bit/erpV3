@props([
    'title'    => '',
    'subtitle' => null,
])

<div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
    <div>
        <h1 class="text-3xl font-medium text-slate-800 tracking-tight">{{ $title }}</h1>
        @isset($subtitle)
            <p class="text-slate-400 font-medium mt-1 text-xs uppercase tracking-wider flex items-center gap-2">{{ $subtitle }}</p>
        @endisset
    </div>

    @isset($actions)
        <div class="flex items-center gap-3">{{ $actions }}</div>
    @endisset
</div>
