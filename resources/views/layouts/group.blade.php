@props([
    'label'    => null,
    'name'     => null,
    'required' => false,
    'help'     => null,
])

<div {{ $attributes->merge(['class' => 'w-full']) }}>
    @if($label)
        <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">
            {{ $label }}
            @if($required)
                <span class="text-rose-500">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if($name)
        @error($name)
            <p class="mt-1.5 ml-2 text-xs font-medium text-rose-500">{{ $message }}</p>
        @enderror
    @endif

    @if($help)
        <p class="mt-1.5 ml-2 text-xs text-slate-400">{{ $help }}</p>
    @endif
</div>
