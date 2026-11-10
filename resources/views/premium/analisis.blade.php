@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-6 max-w-[1600px] mx-auto">
    <x-page-header title="Analisis Premium · 30 Hari Terakhir" subtitle="Omzet, jam sibuk, produk terlaris, dan margin realisasi. Dihitung langsung dari data nyata, tanpa API.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#E7D9B6] bg-[#FDF6E3] px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.16em] text-[#C98A1B]"><i class="fas fa-gem"></i> Premium</span>
        </x-slot:actions>
    </x-page-header>

    {{-- KPI --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @php
            $omzet = (float) ($kpi->omzet ?? 0);
            $rata = (float) ($kpi->rata_nota ?? 0);
        @endphp
        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-[0_1px_3px_rgba(15,30,62,.05)]">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Omzet 30 Hari</p>
            <p class="text-2xl font-semibold text-slate-900 mt-2 tracking-tight">Rp {{ number_format($omzet, 0, ',', '.') }}</p>
            <p class="text-[11px] text-slate-400 mt-1">{{ (int) ($kpi->nota ?? 0) }} nota aktif</p>
        </div>
        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-[0_1px_3px_rgba(15,30,62,.05)]">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Rata-rata / Nota</p>
            <p class="text-2xl font-semibold text-slate-900 mt-2 tracking-tight">Rp {{ number_format($rata, 0, ',', '.') }}</p>
            <p class="text-[11px] text-slate-400 mt-1">nilai transaksi rata-rata</p>
        </div>
        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-[0_1px_3px_rgba(15,30,62,.05)]">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Belum Lunas</p>
            <p class="text-2xl font-semibold {{ (int)($kpi->belum_lunas ?? 0) > 0 ? 'text-orange-600' : 'text-slate-900' }} mt-2 tracking-tight">{{ (int) ($kpi->belum_lunas ?? 0) }} nota</p>
            <p class="text-[11px] text-slate-400 mt-1">butuh follow-up - lihat <a class="text-indigo-600 font-semibold" href="{{ route('premium.piutang-wa') }}">Piutang WA →</a></p>
        </div>
        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-[0_1px_3px_rgba(15,30,62,.05)]">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Margin Realisasi</p>
            <p class="text-2xl font-semibold {{ $marginPersen === null ? 'text-slate-400' : ($marginPersen >= 40 ? 'text-emerald-600' : ($marginPersen >= 20 ? 'text-amber-600' : 'text-rose-600')) }} mt-2 tracking-tight">{{ $marginPersen === null ? '—' : $marginPersen . '%' }}</p>
            <p class="text-[11px] text-slate-400 mt-1">(subtotal − HPP) ÷ subtotal, nota aktif</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">
        {{-- GRAFIK OMZET HARIAN --}}
        <div class="xl:col-span-2 rounded-2xl border border-slate-200/70 bg-white p-6 shadow-[0_1px_3px_rgba(15,30,62,.05)]">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800">Omzet per Hari</h3>
                    <p class="text-[11px] text-slate-400">Arahkan kursor ke batang untuk nilai pasti</p>
                </div>
                <span class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">30 HARI</span>
            </div>
            <div class="flex items-end gap-[3px] h-40 border-b border-slate-100 pb-0" role="img" aria-label="Grafik omzet harian 30 hari terakhir">
                @foreach($hari as $h)
                    @php $tinggi = max(2, round($h['omzet'] / $maksOmzet * 100)); @endphp
                    <div class="flex-1 group relative rounded-t-[3px] bg-gradient-to-t from-indigo-600/85 to-indigo-400/85 hover:from-indigo-700 hover:to-indigo-500 transition-colors" style="height: {{ $tinggi }}%" title="{{ $h['label'] }} · Rp {{ number_format($h['omzet'], 0, ',', '.') }} · {{ $h['nota'] }} nota"></div>
                @endforeach
            </div>
            <div class="flex justify-between mt-2 text-[9px] font-medium text-slate-400">
                <span>{{ $hari[0]['label'] }}</span>
                <span>{{ $hari[15]['label'] }}</span>
                <span>{{ $hari[29]['label'] }}</span>
            </div>
        </div>

        {{-- JAM SIBUK --}}
        <div class="rounded-2xl border border-slate-200/70 bg-white p-6 shadow-[0_1px_3px_rgba(15,30,62,.05)]">
            <h3 class="text-sm font-semibold text-slate-800">Jam Sibuk</h3>
            <p class="text-[11px] text-slate-400 mb-4">Jumlah nota per jam (30 hari)</p>
            @php $maksJam = max(1, (int) $jam->max()); @endphp
            <div class="grid grid-cols-6 gap-1.5">
                @for($h = 7; $h <= 22; $h++)
                    @php
                        $n = (int) ($jam[$h] ?? 0);
                        $a = $n ? round(0.18 + 0.72 * ($n / $maksJam), 2) : 0;
                    @endphp
                    <div class="rounded-lg border border-slate-100 px-1.5 py-2 text-center" style="background: rgba(37,99,235,{{ $a }}); {{ $n ? 'border-color: rgba(37,99,235,.35)' : '' }}" title="{{ sprintf('%02d:00', $h) }} · {{ $n }} nota">
                        <div class="text-[9px] font-semibold {{ $n ? 'text-white' : 'text-slate-400' }}">{{ sprintf('%02d', $h) }}</div>
                        <div class="text-[11px] font-bold {{ $n ? 'text-white' : 'text-slate-500' }}">{{ $n }}</div>
                    </div>
                @endfor
            </div>
            <p class="mt-3 text-[10.5px] text-slate-400 leading-relaxed">Jam puncak berguna untuk mengatur jadwal operator &amp; kapan mengaktifkan notifikasi pengingat.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- PRODUK TERLARIS --}}
        <div class="rounded-2xl border border-slate-200/70 bg-white overflow-hidden shadow-[0_1px_3px_rgba(15,30,62,.05)]">
            <div class="px-6 py-4 border-b border-slate-100"><h3 class="text-sm font-semibold text-slate-800">Produk Terlaris (qty)</h3></div>
            <div class="divide-y divide-slate-50">
                @forelse($topProduk as $i => $p)
                    <div class="flex items-center gap-3 px-6 py-3">
                        <span class="w-6 h-6 rounded-md bg-indigo-50 text-indigo-600 text-[10.5px] font-bold grid place-items-center">{{ $i + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[12px] font-medium text-slate-700 truncate">{{ $p->nama_produk }}</p>
                            <p class="text-[10px] text-slate-400">Rp {{ number_format((float) $p->omzet, 0, ',', '.') }}</p>
                        </div>
                        <span class="text-[12.5px] font-semibold text-slate-800 tabular-nums">{{ number_format((float) $p->qty, 0, ',', '.') }}<span class="text-[10px] text-slate-400 font-medium">&nbsp;pcs</span></span>
                    </div>
                @empty
                    <p class="px-6 py-8 text-center text-sm text-slate-400">Belum ada transaksi produk dalam 30 hari.</p>
                @endforelse
            </div>
        </div>

        {{-- RINGKASAN CADANGAN --}}
        <div class="xl:col-span-2 rounded-2xl border border-slate-200/70 bg-white overflow-hidden shadow-[0_1px_3px_rgba(15,30,62,.05)]">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between"><h3 class="text-sm font-semibold text-slate-800">Cadangan Terakhir</h3><a href="{{ route('premium.cadangan') }}" class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-700">Lihat semua →</a></div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-[12px]">
                    <thead>
                        <tr class="text-[9.5px] uppercase tracking-widest text-slate-400 bg-[#F7F9FE]">
                            <th class="px-6 py-2.5">Waktu</th><th class="px-6 py-2.5">Tipe</th><th class="px-6 py-2.5">Status</th><th class="px-6 py-2.5 text-right">Ukuran</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($backup as $b)
                            <tr class="hover:bg-[#FAFCFF] transition-colors">
                                <td class="px-6 py-2.5 text-slate-600">{{ $b->finished_at ? \Carbon\Carbon::parse($b->finished_at)->translatedFormat('d M Y H:i') : '—' }}</td>
                                <td class="px-6 py-2.5 text-slate-600">{{ ucfirst($b->tipe) }}</td>
                                <td class="px-6 py-2.5"><span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[9.5px] font-bold uppercase tracking-wide {{ $b->status === 'Sukses' ? 'bg-emerald-50 text-emerald-700' : ($b->status === 'Gagal' ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-700') }}"><span class="w-1.5 h-1.5 rounded-full {{ $b->status === 'Sukses' ? 'bg-emerald-500' : ($b->status === 'Gagal' ? 'bg-rose-500' : 'bg-amber-500') }}"></span>{{ $b->status }}</span></td>
                                <td class="px-6 py-2.5 text-right tabular-nums text-slate-700">{{ $b->ukuran_bytes ? number_format($b->ukuran_bytes / 1048576, 1) . ' MB' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
