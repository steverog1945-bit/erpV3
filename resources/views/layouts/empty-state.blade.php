@props([
    'title'       => 'Belum ada data',
    'description' => null,
])

<div class="empty-state">
    @isset($icon)
        <div class="flex justify-center mb-3 text-slate-500">{{ $icon }}</div>
    @endisset

    <p class="font-medium text-slate-400">{{ $title }}</p>

    @if($description)
        <p class="text-xs text-slate-500 mt-1">{{ $description }}</p>
    @endif
</div>
