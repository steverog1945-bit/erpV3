{{-- Badge naik/turun vs periode sebelumnya. $nilai = persen (null = periode
     sebelumnya tanpa data, mis. toko baru) - hijau naik, merah turun, netral
     abu bila 0 atau tidak ada pembanding. --}}
<span class="inline-flex items-center gap-1 font-semibold">
@if($nilai === null)
    <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] text-slate-400">—</span>
@elseif($nilai > 0)
    <span class="rounded-full bg-emerald-50 px-1.5 py-0.5 text-[9px] text-emerald-700"><i class="fas fa-arrow-trend-up"></i> +{{ rtrim(rtrim(number_format($nilai, 1, ',', '.'), '0'), ',') }}%</span>
@elseif($nilai < 0)
    <span class="rounded-full bg-rose-50 px-1.5 py-0.5 text-[9px] text-rose-600"><i class="fas fa-arrow-trend-down"></i> {{ rtrim(rtrim(number_format($nilai, 1, ',', '.'), '0'), ',') }}%</span>
@else
    <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] text-slate-400">tetap</span>
@endif
</span>
