@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header :title="'Kartu Stok: ' . $bahan->nama_bahan">
        <x-slot:subtitle>
            Bahan Baku &amp; Logistik <span class="mx-1 text-slate-500">/</span> Riwayat Pergerakan Stok
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button href="{{ url('/kartu-stok') }}" variant="secondary">
                <i class="fas fa-arrow-left"></i>
                Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-6">
        <form method="GET" action="{{ url('/kartu-stok/'.$bahan->id_bahan) }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Dari Tanggal</label>
                <input type="date" name="awal" value="{{ $awal }}" class="bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
            </div>
            <div>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Sampai Tanggal</label>
                <input type="date" name="akhir" value="{{ $akhir }}" class="bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 ">
            </div>
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Tipe</label>
                <select name="tipe" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500  appearance-none cursor-pointer">
                    <option value="">Semua Tipe</option>
                    <option value="IN" {{ $tipe == 'IN' ? 'selected' : '' }}>IN (Masuk)</option>
                    <option value="OUT" {{ $tipe == 'OUT' ? 'selected' : '' }}>OUT (Keluar)</option>
                    <option value="ADJUSTMENT" {{ $tipe == 'ADJUSTMENT' ? 'selected' : '' }}>Penyesuaian</option>
                </select>
            </div>
            <div class="w-full md:w-56">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Referensi / Keterangan</label>
                <x-form.input type="text" name="cari" value="{{ $cari }}" placeholder="No. SJ, nota, catatan..." />
            </div>
            <div class="w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Dicatat Oleh</label>
                <x-form.select name="oleh">
                    <option value="">Semua Pencatat</option>
                    @foreach($pencatatList as $pc)<option value="{{ $pc->id_user }}" {{ (string) $oleh === (string) $pc->id_user ? 'selected' : '' }}>{{ $pc->nama }}</option>@endforeach
                </x-form.select>
            </div>
            <div class="w-full md:w-40">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Urutan</label>
                <x-form.select name="urut">
                    <option value="lama" {{ $urutDetail === 'lama' ? 'selected' : '' }}>Terlama dulu</option>
                    <option value="baru" {{ $urutDetail === 'baru' ? 'selected' : '' }}>Terbaru dulu</option>
                </x-form.select>
            </div>
            <div class="w-full flex flex-wrap items-center gap-2" title="Pilihan cepat rentang tanggal">
                <span class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mr-1">Rentang cepat:</span>
                @foreach([['Hari ini', now()->toDateString(), now()->toDateString()], ['7 hari', now()->subDays(6)->toDateString(), now()->toDateString()], ['Bulan ini', now()->startOfMonth()->toDateString(), now()->toDateString()], ['Bulan lalu', now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()], ['Tahun ini', now()->startOfYear()->toDateString(), now()->toDateString()], ['Semua', '2000-01-01', now()->toDateString()]] as [$lbl, $a1, $a2])
                    <a href="{{ url('/kartu-stok/'.$bahan->id_bahan) }}?{{ http_build_query(array_merge(request()->except(['awal', 'akhir']), ['awal' => $a1, 'akhir' => $a2])) }}" class="px-3 py-1.5 rounded-lg text-[10px] font-medium uppercase tracking-widest {{ $awal === $a1 && $akhir === $a2 ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600' }}">{{ $lbl }}</a>
                @endforeach
            </div>
            <button type="submit" class="h-[38px] px-6 flex items-center justify-center bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700 transition-all">
                <i class="fas fa-filter mr-1"></i> Tampilkan
            </button>
            <a href="{{ url('/kartu-stok/'.$bahan->id_bahan) }}" class="h-[38px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all" title="Reset Filter">
                <i class="fas fa-undo-alt text-sm"></i>
            </a>
        </form>
    </div>

    @php
        $adaEstimasi = $bahan->nama_satuan_hitung
            && $bahan->id_satuan_stok !== $bahan->id_satuan_hitung
            && $bahan->nilai_konversi > 1;
    @endphp
    <div class="grid {{ $adaEstimasi ? 'grid-cols-2' : 'grid-cols-1' }} gap-4 mb-6">
        <x-card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Stok Saat Ini</p>
                    <h3 class="text-3xl font-medium text-slate-900 mt-1">{{ angka($bahan->stok, 2) }} <span class="text-base font-medium text-slate-400">{{ $bahan->nama_satuan_stok ?? $bahan->satuan_stok }}</span></h3>
                </div>
                <div class="w-14 h-14 bg-slate-100 text-slate-600 rounded-2xl flex items-center justify-center text-2xl">
                    <i class="fas fa-box"></i>
                </div>
            </div>
        </x-card>
        @if($adaEstimasi)
        <x-card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Estimasi dalam {{ $bahan->nama_satuan_hitung }}</p>
                    <h3 class="text-3xl font-medium text-slate-900 mt-1">≈ {{ number_format($bahan->stok * $bahan->nilai_konversi, 0, ',', '.') }} <span class="text-base font-medium text-slate-400">{{ $bahan->nama_satuan_hitung }}</span></h3>
                    <p class="text-[10px] text-slate-400 mt-1">Dihitung dari {{ angka($bahan->stok, 2) }} {{ $bahan->nama_satuan_stok }} × {{ number_format($bahan->nilai_konversi, 0, ',', '.') }} {{ $bahan->nama_satuan_hitung }}/{{ $bahan->nama_satuan_stok }}</p>
                </div>
                <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl">
                    <i class="fas fa-ruler-combined"></i>
                </div>
            </div>
        </x-card>
        @endif
    </div>

    {{-- RINGKASAN PERIODE (mengikuti filter yang aktif) --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-5 py-4"><p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Saldo Awal Periode</p><p class="text-xl font-medium text-slate-800 mt-1">{{ angka($saldoAwal, 2) }}</p></div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-5 py-4"><p class="text-[10px] font-medium text-emerald-500 uppercase tracking-widest">Total Masuk (IN)</p><p class="text-xl font-medium text-emerald-600 mt-1">+ {{ angka($totalMasuk, 2) }}</p></div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-5 py-4"><p class="text-[10px] font-medium text-rose-500 uppercase tracking-widest">Total Keluar (OUT)</p><p class="text-xl font-medium text-rose-600 mt-1">- {{ angka($totalKeluar, 2) }}</p></div>
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-5 py-4"><p class="text-[10px] font-medium text-amber-500 uppercase tracking-widest">Selisih Penyesuaian</p><p class="text-xl font-medium text-amber-600 mt-1">{{ $totalAdj >= 0 ? '+' : '-' }} {{ angka(abs($totalAdj), 2) }}</p></div>
    </div>

    <x-table>
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-6 py-5 text-left">Tanggal</th>
                <th class="px-4 py-5 text-center">Tipe</th>
                <th class="px-4 py-5 text-right">Qty</th>
                <th class="px-4 py-5 text-right">Stok Sebelum</th>
                <th class="px-4 py-5 text-right">Stok Sesudah</th>
                <th class="px-4 py-5 text-left">Satuan</th>
                <th class="px-4 py-5 text-left">Referensi</th>
                <th class="px-4 py-5 text-left">Dicatat Oleh</th>
                <th class="px-6 py-5 text-left">Keterangan</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            <tr class="bg-slate-50/40">
                <td colspan="5" class="px-6 py-3 text-[11px] font-medium text-slate-500 uppercase tracking-widest">Saldo Awal ({{ \Carbon\Carbon::parse($awal)->translatedFormat('d M Y') }})</td>
                <td class="px-4 py-3 text-right text-sm font-medium text-slate-700">{{ angka($saldoAwal, 2) }}</td>
                <td colspan="4"></td>
            </tr>
            @forelse($kartu as $i => $k)
            <tr class="hover:bg-slate-50/50 transition-all">
                <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $i + 1 }}</td>
                <td class="px-6 py-5">
                    <span class="text-[12px] font-medium text-slate-600">{{ \Carbon\Carbon::parse($k->tanggal)->translatedFormat('d M Y H:i') }}</span>
                </td>
                <td class="px-4 py-5 text-center">
                    @if($k->tipe === 'IN')
                        <x-badge variant="success">IN</x-badge>
                    @elseif($k->tipe === 'OUT')
                        <x-badge variant="danger">OUT</x-badge>
                    @else
                        <x-badge variant="warning">ADJ</x-badge>
                    @endif
                </td>
                <td class="px-4 py-5 text-right">
                    <span class="text-[13px] font-medium text-slate-800">{{ angka($k->qty, 2) }}</span>
                </td>
                <td class="px-4 py-5 text-right">
                    <span class="text-[12px] text-slate-500 font-medium">{{ angka($k->stok_sebelum, 2) }}</span>
                </td>
                <td class="px-4 py-5 text-right">
                    <span class="text-[12px] text-slate-700 font-medium">{{ angka($k->stok_sesudah, 2) }}</span>
                </td>
                <td class="px-4 py-5"><span class="inline-flex px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[10px] font-medium uppercase">{{ $bahan->nama_satuan_stok ?? $bahan->satuan_stok }}</span></td>
                <td class="px-4 py-5">
                    <span class="text-[12px] font-medium text-slate-600">{{ $k->referensi ?? '-' }}</span>
                </td>
                <td class="px-4 py-5">
                    <span class="text-[12px] text-slate-500">{{ $k->nama_pencatat ?? '-' }}</span>
                </td>
                <td class="px-6 py-5">
                    <span class="text-[12px] text-slate-500">{{ $k->keterangan ?? '-' }}</span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10">
                    <x-empty-state title="Tidak ada transaksi pada rentang filter ini" description="Coba perlebar rentang tanggal atau reset filter." />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

</div>
@endsection
