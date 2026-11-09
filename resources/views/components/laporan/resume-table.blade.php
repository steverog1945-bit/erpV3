@props([
    'title' => 'Resume',
    'rows'  => [], // [label => value string, ...] - value sudah diformat pemanggil
])

{{--
    Tabel ringkasan standar untuk laporan (khusus cetak - dipasang di
    dalam pembungkus "hidden print:block" oleh pemanggil). Border tipis,
    padding rapat, tanpa rounded/shadow. Ukuran font mengikuti skala cetak
    yang sama di semua bagian laporan: 10px untuk label section, 11px
    untuk isi data - supaya tidak ada campuran ukuran font yang beda-beda
    dalam satu dokumen ("font size wajib sama" - laporan profesional).
--}}
<div class="mb-6 border border-slate-200 overflow-hidden">
    <p class="text-[10px] font-medium text-slate-700 uppercase tracking-wide px-3 py-2 bg-slate-50 border-b border-slate-200">{{ $title }}</p>
    <table class="w-full text-[11px]">
        <tbody class="divide-y divide-slate-100">
            @foreach($rows as $label => $value)
            <tr>
                <td class="px-3 py-1.5 text-slate-500 w-1/2 sm:w-2/5">{{ $label }}</td>
                <td class="px-3 py-1.5 font-medium text-slate-800 text-right sm:text-left">{!! $value !!}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
