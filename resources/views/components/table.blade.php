@props([
    // Wrapper luar SENGAJA dibuat polos (tanpa bg/radius/border sendiri).
    // Permukaan visual (background, border, radius 8px) dibawa oleh
    // .modern-table-wrapper di dalam. Sebelumnya wrapper luar juga punya
    // rounded-2xl + border + shadow, sehingga tabel punya dua lapis
    // radius (32px di luar, 8px di dalam) dan dua garis border bertumpuk
    // yang terlihat tidak senada dengan .card mana pun.
    'wrapperClass' => 'overflow-hidden',
    'bare'         => false, // true = skip wrapper card, dipakai saat tabel sudah ada di dalam card/panel lain
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
