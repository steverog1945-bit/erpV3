@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-6 max-w-[1600px] mx-auto">
    <x-page-header title="Prediksi Restock Cerdas" subtitle="Sisa hari pakai per bahan baku, dari pemakaian 60 hari terakhir. Diurutkan dari yang paling kritis.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#E7D9B6] bg-[#FDF6E3] px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.16em] text-[#C98A1B]"><i class="fas fa-gem"></i> Premium</span>
            @if(\App\Support\Izin::bolehPath(auth()->user(), '/purchase-request/create'))
            <a href="{{ url('/purchase-request/create') }}" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg bg-slate-900 text-white text-[12px] font-semibold hover:bg-slate-800 transition-colors"><i class="fas fa-cart-plus"></i> Buat Permintaan Pembelian</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="rounded-2xl border border-slate-200/70 bg-white overflow-hidden shadow-[0_1px_3px_rgba(15,30,62,.05)]">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[13px] min-w-[880px]">
                <thead>
                    <tr class="text-[10px] uppercase tracking-widest text-slate-400 bg-[#F7F9FE]">
                        <th class="px-6 py-3">#</th>
                        <th class="px-6 py-3">Bahan Baku</th>
                        <th class="px-6 py-3 text-right">Stok</th>
                        <th class="px-6 py-3 text-right">Minimal</th>
                        <th class="px-6 py-3 text-right">Pakai 60 Hr</th>
                        <th class="px-6 py-3 text-right">Per Hari</th>
                        <th class="px-6 py-3">Sisa Hari</th>
                        <th class="px-6 py-3 text-right">Saran Beli</th>
                        <th class="px-6 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($bahan as $i => $b)
                        @php
                            $badge = ['kritis' => ['Kritis', 'bg-rose-50 text-rose-600 border-rose-200', 'bg-rose-500'], 'perhatian' => ['Perhatian', 'bg-amber-50 text-amber-800 border-amber-200', 'bg-amber-500'], 'aman' => ['Aman', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'bg-emerald-500']][$b->level];
                        @endphp
                    <tr class="hover:bg-[#FAFCFF] transition-colors {{ $b->level === 'kritis' ? 'bg-rose-50/40' : '' }}">
                        <td class="px-6 py-3 text-slate-400 tabular-nums">{{ $i + 1 }}</td>
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $b->nama_bahan }}</td>
                        <td class="px-6 py-3 text-right tabular-nums text-slate-700">{{ number_format((float) $b->stok, 2, ',', '.') }} <span class="text-[10px] text-slate-400">{{ $b->satuan_stok }}</span></td>
                        <td class="px-6 py-3 text-right tabular-nums text-slate-500">{{ number_format((float) $b->stok_minimal, 2, ',', '.') }}</td>
                        <td class="px-6 py-3 text-right tabular-nums text-slate-700">{{ number_format((float) $b->pakai_60d, 2, ',', '.') }}</td>
                        <td class="px-6 py-3 text-right tabular-nums text-slate-700">{{ number_format((float) $b->per_hari, 2, ',', '.') }}<span class="text-[10px] text-slate-400">/hari</span></td>
                        <td class="px-6 py-3">
                            @if($b->sisa_hari === null)
                                <span class="text-[11px] text-slate-400">tidak ada pemakaian</span>
                            @else
                                <div class="flex items-center gap-2">
                                    <div class="w-20 h-1.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full rounded-full {{ $b->level === 'kritis' ? 'bg-rose-500' : ($b->level === 'perhatian' ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ min(100, round($b->sisa_hari / 30 * 100)) }}%"></div></div>
                                    <span class="text-[11.5px] font-semibold tabular-nums {{ $b->level === 'kritis' ? 'text-rose-600' : ($b->level === 'perhatian' ? 'text-amber-700' : 'text-slate-600') }}">{{ $b->sisa_hari }} hr</span>
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            @if($b->saran_beli > 0)
                                <span class="text-[12px] font-bold tabular-nums text-indigo-700">{{ number_format((float) $b->saran_beli, 0, ',', '.') }} <span class="text-[10px] font-medium text-slate-400">{{ $b->satuan_stok }}</span></span>
                            @else
                                <span class="text-[11px] text-slate-400">cukup</span>
                            @endif
                        </td>
                        <td class="px-6 py-3"><span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[9.5px] font-bold uppercase tracking-wide {{ $badge[1] }}"><span class="w-1.5 h-1.5 rounded-full {{ $badge[2] }}"></span>{{ $badge[0] }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-4 text-[11px] text-slate-400">Prediksi memakai rata-rata pakai 60 hari (kartu stok, tipe keluar). Saran beli = gap ke stok minimal atau cadangan 14 hari pemakaian, yang lebih besar. Bahan tanpa pemakaian dan tanpa stok minimal tidak diramal. Angka adalah panduan — keputusan beli final ada di Anda.</p>
</div>
@endsection
