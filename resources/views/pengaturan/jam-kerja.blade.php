@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-3xl mx-auto">
    <x-page-header title="Jam Kerja" subtitle="Atur hari buka/tutup dan jam kerja. Hari tutup memblokir transaksi kas baru (closing kas tetap boleh)." />

    @if (session('error'))<div class="bg-rose-50 text-rose-600 p-4 rounded-2xl mb-6 font-medium">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('jam-kerja.update') }}">
        @csrf
        <x-card class="space-y-2">
            @foreach(\App\Services\JamKerjaService::HARI as $no => $nama)
            @php $j = $jam[$no] ?? null; @endphp
            <div class="flex flex-wrap items-center gap-4 py-3 border-b border-slate-100 last:border-0">
                <label class="flex items-center gap-3 w-40 cursor-pointer">
                    <input type="checkbox" name="jam[{{ $no }}][buka]" value="1" {{ old("jam.$no.buka", $j?->buka) ? 'checked' : '' }} class="w-5 h-5 rounded accent-indigo-600">
                    <span class="font-medium text-slate-700">{{ $nama }}</span>
                </label>
                <div class="flex items-center gap-2">
                    <x-form.input type="time" name="jam[{{ $no }}][mulai]" value="{{ old("jam.$no.mulai", substr($j->jam_mulai ?? '08:00:00', 0, 5)) }}" data-no-autosubmit />
                    <span class="text-slate-400 text-sm">s/d</span>
                    <x-form.input type="time" name="jam[{{ $no }}][selesai]" value="{{ old("jam.$no.selesai", substr($j->jam_selesai ?? '17:00:00', 0, 5)) }}" data-no-autosubmit />
                </div>
                <span class="text-xs font-medium {{ ($j?->buka) ? 'text-emerald-600' : 'text-slate-400' }}">{{ ($j?->buka) ? 'Buka' : 'Libur' }}</span>
            </div>
            @endforeach

            <label class="flex items-start gap-3 pt-4 cursor-pointer">
                <input type="checkbox" name="batasi_jam_kerja" value="1" {{ old('batasi_jam_kerja', $batasi) ? 'checked' : '' }} class="mt-1 w-5 h-5 rounded accent-indigo-600">
                <span>
                    <span class="block text-sm font-medium text-slate-700">Batasi transaksi di luar jam kerja</span>
                    <span class="block text-xs text-slate-400 mt-0.5">Bila aktif, transaksi kas baru (bayar, open saldo, kas manual) di luar jam di atas ditolak. Bila tidak, hanya hari libur yang memblokir.</span>
                </span>
            </label>

            <div class="pt-4 flex justify-end border-t border-slate-100"><x-button type="submit" class="!px-10">Simpan Jam Kerja</x-button></div>
        </x-card>
    </form>
</div>
@endsection
