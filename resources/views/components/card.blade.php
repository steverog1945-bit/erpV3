@props([
    'title'    => null,
    'subtitle' => null,
    'padding'  => true,
])

<div {{ $attributes->merge(['class' => 'x-card bg-white rounded-xl border border-slate-200']) }}>
    @if($title)
        <div class="px-5 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h3 class="text-sm font-medium text-slate-900">{{ $title }}</h3>
                @if($subtitle)
                    <p class="text-xs text-slate-500 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="{{ $padding ? 'p-5' : '' }}">
        {{ $slot }}
    </div>
</div>
