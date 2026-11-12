@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-6 max-w-[1600px] mx-auto">
    <x-page-header title="Ringkasan Cadangan" subtitle="Kesehatan backup harian / mingguan / bulanan dari backup_logs. Read-only dari halaman Pengaturan → Cadangan.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#E7D9B6] bg-[#FDF6E3] px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.16em] text-[#C98A1B]"><i class="fas fa-gem"></i> Premium</span>
            @if(\App\Support\Izin::bolehPath(auth()->user(), '/backup'))
            <a href="{{ url('/backup') }}" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg bg-slate-900 text-white text-[12px] font-semibold hover:bg-slate-800 transition-colors"><i class="fas fa-gear"></i> Kelola Backup</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-[0_1px_3px_rgba(15,30,62,.05)]">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Backup Sukses</p>
            <p class="text-2xl font-semibold text-emerald-600 mt-2">{{ $sukses->count() }}<span class="text-sm text-slate-400 font-medium"> / {{ $logs->count() }} entri</span></p>
        </div>
        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-[0_1px_3px_rgba(15,30,62,.05)]">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Gagal</p>
            <p class="text-2xl font-semibold {{ $gagal->count() ? 'text-rose-600' : 'text-slate-900' }} mt-2">{{ $gagal->count() }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-[0_1px_3px_rgba(15,30,62,.05)]">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Terakhir Berhasil</p>
            <p class="text-sm font-semibold text-slate-800 mt-2 leading-snug">{{ $terakhir && $terakhir->finished_at ? \Carbon\Carbon::parse($terakhir->finished_at)->translatedFormat('d M Y H:i') : 'Belum pernah' }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/70 bg-white p-5 shadow-[0_1px_3px_rgba(15,30,62,.05)]">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Total Ukuran</p>
            <p class="text-2xl font-semibold text-slate-900 mt-2 tabular-nums">{{ number_format($totalUkuran / 1073741824, 2) }}<span class="text-sm text-slate-400 font-medium"> GB</span></p>
        </div>
    </div>

    {{-- Verifikasi isi backup terakhir (dulu hanya via command backup:verify) --}}
    <div class="rounded-2xl border border-slate-200/70 bg-white overflow-hidden shadow-[0_1px_3px_rgba(15,30,62,.05)] mb-6">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="text-sm font-semibold text-slate-800">Verifikasi Backup Terakhir</h3>
        </div>
        <div class="divide-y divide-slate-50">
            @forelse($verifikasi as $v)
                <div class="flex items-center gap-3 px-6 py-3">
                    <span class="text-[12px] font-semibold text-slate-700 w-20 capitalize">{{ $v['tipe'] }}</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[9.5px] font-bold uppercase tracking-wide {{ ($v['ok'] ?? false) ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-600' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ ($v['ok'] ?? false) ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>{{ ($v['ok'] ?? false) ? 'LULUS' : 'GAGAL' }}
                    </span>
                    <span class="text-[11.5px] text-slate-500 truncate">{{ $v['pesan'] ?? '' }}</span>
                </div>
            @empty
                <p class="px-6 py-5 text-center text-[12px] text-slate-400">Belum ada backup untuk diverifikasi - jalankan backup manual dulu.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200/70 bg-white overflow-hidden shadow-[0_1px_3px_rgba(15,30,62,.05)]">
        <div class="px-6 py-4 border-b border-slate-100"><h3 class="text-sm font-semibold text-slate-800">30 Entri Terakhir</h3></div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[12.5px] min-w-[860px]">
                <thead>
                    <tr class="text-[10px] uppercase tracking-widest text-slate-400 bg-[#F7F9FE]">
                        <th class="px-6 py-2.5">Mulai</th><th class="px-6 py-2.5">Tipe</th><th class="px-6 py-2.5">Status</th><th class="px-6 py-2.5">Offsite</th><th class="px-6 py-2.5 text-right">Ukuran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($logs as $b)
                        <tr class="hover:bg-[#FAFCFF] transition-colors">
                            <td class="px-6 py-2.5 text-slate-600">{{ $b->started_at ? \Carbon\Carbon::parse($b->started_at)->translatedFormat('d M Y H:i') : '—' }}</td>
                            <td class="px-6 py-2.5 text-slate-600">{{ ucfirst($b->tipe) }}</td>
                            <td class="px-6 py-2.5"><span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide {{ $b->status === 'Sukses' ? 'bg-emerald-50 text-emerald-700' : ($b->status === 'Gagal' ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-700') }}">{{ $b->status }}</span></td>
                            <td class="px-6 py-2.5 text-slate-500 text-[11px]">{{ $b->offsite_status ?: '—' }}</td>
                            <td class="px-6 py-2.5 text-right tabular-nums text-slate-700">{{ $b->ukuran_bytes ? number_format($b->ukuran_bytes / 1048576, 1) . ' MB' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-slate-400">Belum ada log backup.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
