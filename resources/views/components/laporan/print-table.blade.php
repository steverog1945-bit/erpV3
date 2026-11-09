@props([
    'title'   => null,
    'headers' => [], // ['Kolom 1', 'Kolom 2', ...]
    'align'   => [], // opsional, per kolom: 'left' (default) | 'right' | 'center'
])

{{--
    Tabel data standar khusus cetak (dipasang oleh pemanggil di dalam
    "hidden print:block") - dipakai untuk rincian/breakdown yang di layar
    tampil sebagai <x-card> berwarna tapi saat dicetak perlu tabel polos
    yang sederhana. Ukuran font mengikuti skala cetak yang sama di semua
    laporan: 10px label/header kolom, 11px isi data.
--}}
<div class="mb-6 border border-slate-200 overflow-hidden">
    @if($title)
        <p class="text-[10px] font-medium text-slate-700 uppercase tracking-wide px-3 py-2 bg-slate-50 border-b border-slate-200">{{ $title }}</p>
    @endif
    <table class="w-full text-[11px] border-collapse">
        @if(count($headers))
        <thead>
            <tr class="bg-slate-50 border-b border-slate-200">
                @foreach($headers as $i => $h)
                <th class="px-3 py-1.5 text-[10px] font-medium text-slate-500 uppercase text-{{ $align[$i] ?? 'left' }}">{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        @endif
        <tbody class="divide-y divide-slate-100">
            {{ $slot }}
        </tbody>
    </table>
</div>
