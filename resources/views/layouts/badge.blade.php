@props([
    'status'  => null,   // teks status mentah, mis. "Selesai", "Hutang", "Draft"
    'variant' => null,   // override manual: success | warning | danger | info
])

@php
    $map = [
        // success
        'aktif'      => 'success', 'active'    => 'success', 'selesai'  => 'success',
        'completed'  => 'success', 'lunas'     => 'success', 'paid'     => 'success',
        'ok'         => 'success',

        // warning
        'draft'      => 'warning', 'pending'   => 'warning', 'proses'   => 'warning',
        'processing' => 'warning', 'dp'        => 'warning', 'partial'  => 'warning',
        'antre'      => 'warning', 'desain'    => 'warning', 'cetak'    => 'warning',

        // danger
        'nonaktif'   => 'danger',  'inactive'  => 'danger',  'batal'    => 'danger',
        'cancelled'  => 'danger',  'canceled'  => 'danger',  'hutang'   => 'danger',
        'unpaid'     => 'danger',  'belum bayar' => 'danger',
    ];

    $resolved = $variant ?? $map[strtolower(trim((string) $status))] ?? 'info';
@endphp

<span {{ $attributes->merge(['class' => 'chip chip-' . $resolved]) }}>
    {{ $slot->isEmpty() ? $status : $slot }}
</span>
