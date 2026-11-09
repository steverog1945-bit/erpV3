@props([
    // Wrapper luar polos, permukaan visual dibawa .modern-table-wrapper
    // (lihat catatan senada di components/table.blade.php).
    'wrapperClass' => 'overflow-hidden',
    'bare'         => false,
])

@if($bare)
    <div class="modern-table-wrapper !shadow-none !border-0">
        <table {{ $attributes->merge(['class' => 'modern-table']) }}>
            {{ $slot }}
        </table>
    </div>
@else
    <div class="{{ $wrapperClass }}">
        <div class="modern-table-wrapper">
            <table {{ $attributes->merge(['class' => 'modern-table']) }}>
                {{ $slot }}
            </table>
        </div>
    </div>
@endif
