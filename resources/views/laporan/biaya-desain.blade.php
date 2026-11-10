@extends('layouts.app')

@section('content')
<style>
    @media print {
        aside, .no-print { display: none !important; }
        main { margin-left: 0 !important; padding: 28px 32px !important; }
        body { background: #fff !important; }
    }
</style>
@include('partials.print-report-header', ['docTitle' => 'Laporan Biaya & Komisi Desain'])

<div class="w-full px-4 sm:px-6 lg:px-8 py-10 print:pt-0 max-w-[1600px] mx-auto">

    <div class="print:hidden">
        <x-page-header title="Laporan Biaya & Komisi Desain">
            <x-slot:subtitle>
                Laporan <span class="mx-1 text-slate-500">/</span> Biaya desain yang diterapkan tiap operator &amp; bagi hasil (komisi) menurut kelasnya{{ $milikSendiri ? ' - hanya menampilkan milik Anda' : '' }}
            </x-slot:subtitle>
            <x-slot:actions>
                <x-button type="button" onclick="window.print()" variant="secondary" class="no-print" title="Cetak laporan sesuai filter yang tampil">
                    <i class="fas fa-print"></i>
                    Cetak
                </x-button>
            </x-slot:actions>
        </x-page-header>
    </div>

    @if (session('success'))
        <div class="bg-emerald-50 text-emerald-700 p-4 rounded-2xl mb-6 font-medium no-print">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="bg-rose-50 text-rose-600 p-4 rounded-2xl mb-6 font-medium no-print">{{ session('error') }}</div>
    @endif

    <div class="bg-amber-50 text-amber-700 p-4 rounded-2xl mb-6 text-xs font-medium flex items-start gap-2 no-print">
        <i class="fas fa-circle-info mt-0.5"></i>
        <span>Biaya desain tetap diakui <b>penuh sebagai pendapatan</b> di nota. Komisi desainer dihitung saat nota dibuat (persen kelasnya saat itu) dan dibayarkan berkala dari <b>Kas Kecil</b> sebagai beban komisi - jadi laba bersih desain = biaya desain - komisi. Item tanpa desainer tidak mendapat komisi (ditandai "Tanpa desainer" agar bisa dipantau).</span>
    </div>

    {{-- FILTER --}}
    <x-card class="mb-6 no-print">
        <form method="GET" action="{{ route('laporan.biaya-desain') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Dari Tanggal Order</label>
                <x-form.input type="date" name="tgl_dari" value="{{ $tglDari }}" />
            </div>
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Sampai Tanggal Order</label>
                <x-form.input type="date" name="tgl_sampai" value="{{ $tglSampai }}" />
            </div>
            @unless($milikSendiri)
            <div class="w-full md:w-52">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Desainer</label>
                <x-form.select name="id_desainer">
                    <option value="">Semua Desainer</option>
                    <option value="tanpa" {{ request('id_desainer') === 'tanpa' ? 'selected' : '' }}>Tanpa desainer</option>
                    @foreach($desainerList as $d)
                        <option value="{{ $d->id_user }}" {{ (string) request('id_desainer') === (string) $d->id_user ? 'selected' : '' }}>{{ $d->nama }}</option>
                    @endforeach
                </x-form.select>
            </div>
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Kelas</label>
                <x-form.select name="id_kelas">
                    <option value="">Semua Kelas</option>
                    @foreach($kelasList as $kl)
                        <option value="{{ $kl->id_kelas }}" {{ (string) request('id_kelas') === (string) $kl->id_kelas ? 'selected' : '' }}>{{ $kl->nama_kelas }}</option>
                    @endforeach
                </x-form.select>
            </div>
            @endunless
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status Komisi</label>
                <x-form.select name="status_komisi">
                    <option value="">Semua</option>
                    <option value="Belum" {{ request('status_komisi') === 'Belum' ? 'selected' : '' }}>Belum dibayar</option>
                    <option value="Dibayar" {{ request('status_komisi') === 'Dibayar' ? 'selected' : '' }}>Sudah dibayar</option>
                </x-form.select>
            </div>

            <x-button type="submit" title="Terapkan filter">
                <i class="fas fa-filter"></i>
                Filter
            </x-button>

            @if(request()->anyFilled(['tgl_dari', 'tgl_sampai', 'id_desainer', 'id_kelas', 'status_komisi']))
                <a href="{{ route('laporan.biaya-desain') }}" title="Kembalikan filter ke awal" class="text-[11px] font-medium text-rose-500 uppercase tracking-widest hover:underline ml-2">Reset</a>
            @endif
        </form>
    </x-card>

    {{-- RESUME --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-6 mb-6 print:hidden">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest" title="Jumlah item order yang memakai biaya desain">Item Berbiaya Desain</p>
            <h3 class="text-2xl font-medium text-slate-900 mt-1">{{ number_format($total->item, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest" title="Jumlah biaya desain yang ditagihkan ke konsumen">Total Biaya Desain</p>
            <h3 class="text-2xl font-medium text-slate-900 mt-1">Rp {{ number_format($total->biaya, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest" title="Bagian desainer = biaya desain x persen kelasnya">Total Komisi</p>
            <h3 class="text-2xl font-medium text-indigo-600 mt-1">Rp {{ number_format($total->komisi, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-amber-100 shadow-sm">
            <p class="text-[10px] font-medium text-amber-500 uppercase tracking-widest" title="Komisi yang masih harus dibayarkan ke desainer">Komisi Belum Dibayar</p>
            <h3 class="text-2xl font-medium text-amber-600 mt-1">Rp {{ number_format($total->belum, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-emerald-100 shadow-sm">
            <p class="text-[10px] font-medium text-emerald-500 uppercase tracking-widest" title="Biaya desain dikurangi komisi - bagian toko">Bagian Toko</p>
            <h3 class="text-2xl font-medium text-emerald-600 mt-1">Rp {{ number_format($total->bersih, 0, ',', '.') }}</h3>
        </div>
    </div>
    <div class="hidden print:block">
        <x-laporan.resume-table title="Resume" :rows="[
            'Periode Order' => \Carbon\Carbon::parse($tglDari)->format('d/m/Y') . ' - ' . \Carbon\Carbon::parse($tglSampai)->format('d/m/Y'),
            'Item Berbiaya Desain' => number_format($total->item, 0, ',', '.'),
            'Total Biaya Desain' => 'Rp ' . number_format($total->biaya, 0, ',', '.'),
            'Total Komisi' => 'Rp ' . number_format($total->komisi, 0, ',', '.'),
            'Komisi Belum Dibayar' => 'Rp ' . number_format($total->belum, 0, ',', '.'),
            'Bagian Toko' => 'Rp ' . number_format($total->bersih, 0, ',', '.'),
        ]" />
    </div>

    {{-- REKAP PER DESAINER --}}
    <x-card class="mb-6 print:hidden">
        <h3 class="text-sm font-medium text-slate-800 mb-4">Rekap per Desainer</h3>
        <x-table bare>
            <thead>
                <tr>
                    <th class="p-3 text-left">Desainer</th>
                    <th class="p-3 text-left">Kelas</th>
                    <th class="p-3 text-center">Nota</th>
                    <th class="p-3 text-center">Item</th>
                    <th class="p-3 text-right">Biaya Desain</th>
                    <th class="p-3 text-right">Komisi</th>
                    <th class="p-3 text-right">Belum Dibayar</th>
                    <th class="p-3 text-right">Dibayar</th>
                    @unless($milikSendiri)<th class="p-3 text-center no-print">Aksi</th>@endunless
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse($rekap as $r)
                <tr>
                    <td class="p-3 font-medium text-slate-700 text-xs">{{ $r->id_desainer ? $r->nama : 'Tanpa desainer' }}</td>
                    <td class="p-3 text-xs text-slate-500">{{ $r->nama_kelas ? $r->nama_kelas . ' (' . angka($r->persen_komisi, 2) . '%)' : '-' }}</td>
                    <td class="p-3 text-center text-xs">{{ $r->jumlah_nota }}</td>
                    <td class="p-3 text-center text-xs">{{ $r->jumlah_item }}</td>
                    <td class="p-3 text-right text-xs">Rp {{ number_format($r->total_biaya, 0, ',', '.') }}</td>
                    <td class="p-3 text-right text-xs font-medium text-indigo-600">Rp {{ number_format($r->total_komisi, 0, ',', '.') }}</td>
                    <td class="p-3 text-right text-xs font-medium text-amber-600">Rp {{ number_format($r->komisi_belum, 0, ',', '.') }}</td>
                    <td class="p-3 text-right text-xs text-emerald-600">Rp {{ number_format($r->komisi_dibayar, 0, ',', '.') }}</td>
                    @unless($milikSendiri)
                    <td class="p-3 text-center no-print">
                        @if($r->id_desainer && $r->komisi_belum > 0 && \App\Support\Izin::bolehKeluarkanKasKecil(auth()->user()))
                        <form method="POST" action="{{ route('laporan.biaya-desain.bayar') }}" onsubmit="return konfirmasiBayarKomisi(event, this, {{ json_encode($r->nama) }}, {{ (float) $r->komisi_belum }})">
                            @csrf
                            <input type="hidden" name="id_desainer" value="{{ $r->id_desainer }}">
                            <input type="hidden" name="tgl_dari" value="{{ $tglDari }}">
                            <input type="hidden" name="tgl_sampai" value="{{ $tglSampai }}">
                            <button type="submit" title="Bayar seluruh komisi belum dibayar desainer ini pada periode yang tampil - dicatat sebagai pengeluaran Kas Kecil" class="px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 text-[10px] font-medium uppercase tracking-widest">
                                <i class="fas fa-hand-holding-dollar"></i> Bayar
                            </button>
                        </form>
                        @else
                        <span class="text-slate-500" title="{{ $r->id_desainer ? 'Tidak ada komisi yang perlu dibayar' : 'Item tanpa desainer tidak mendapat komisi' }}">-</span>
                        @endif
                    </td>
                    @endunless
                </tr>
                @empty
                <tr><td colspan="9"><x-empty-state title="Belum ada biaya desain" description="Tidak ada item berbiaya desain pada filter ini." /></td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>
    <div class="hidden print:block break-inside-avoid">
        <x-laporan.print-table title="Rekap per Desainer" :headers="['Desainer', 'Kelas', 'Nota', 'Item', 'Biaya Desain', 'Komisi', 'Belum Dibayar', 'Dibayar']" :align="['left', 'left', 'center', 'center', 'right', 'right', 'right', 'right']">
            @forelse($rekap as $r)
            <tr>
                <td class="px-3 py-1.5 font-medium text-slate-700">{{ $r->id_desainer ? $r->nama : 'Tanpa desainer' }}</td>
                <td class="px-3 py-1.5 text-slate-600">{{ $r->nama_kelas ? $r->nama_kelas . ' (' . angka($r->persen_komisi, 2) . '%)' : '-' }}</td>
                <td class="px-3 py-1.5 text-center text-slate-600">{{ $r->jumlah_nota }}</td>
                <td class="px-3 py-1.5 text-center text-slate-600">{{ $r->jumlah_item }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($r->total_biaya, 0, ',', '.') }}</td>
                <td class="px-3 py-1.5 text-right text-slate-800">{{ number_format($r->total_komisi, 0, ',', '.') }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($r->komisi_belum, 0, ',', '.') }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($r->komisi_dibayar, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr><td class="px-3 py-1.5 text-slate-400 italic" colspan="8">Belum ada biaya desain.</td></tr>
            @endforelse
        </x-laporan.print-table>
    </div>

    <div class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-3 px-1 print:hidden">Detail Per Item</div>

    <div class="print:hidden">
    <x-table>
        <thead>
            <tr>
                <th class="px-6 py-5 text-left">Nota</th>
                <th class="px-4 py-5 text-left">Tanggal</th>
                <th class="px-4 py-5 text-left">Item / Konsumen</th>
                <th class="px-4 py-5 text-left">Desainer</th>
                <th class="px-4 py-5 text-right">Biaya Desain</th>
                <th class="px-4 py-5 text-center">%</th>
                <th class="px-4 py-5 text-right">Komisi</th>
                <th class="px-6 py-5 text-center">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($data as $row)
            <tr class="hover:bg-slate-50/50 transition-all">
                <td class="px-6 py-4 text-[12px] font-medium text-slate-700">{{ $row->nomor_nota }}</td>
                <td class="px-4 py-4 text-[12px] text-slate-500">{{ \Carbon\Carbon::parse($row->tgl_order)->translatedFormat('d M Y') }}</td>
                <td class="px-4 py-4 text-[12px] text-slate-600"><span class="font-medium">{{ $row->nama_file }}</span><div class="text-[10px] text-slate-400">{{ $row->nama_konsumen ?? '-' }}</div></td>
                <td class="px-4 py-4 text-[12px] {{ $row->id_desainer ? 'text-slate-700 font-medium' : 'text-amber-500 font-medium' }}">{{ $row->id_desainer ? $row->nama_desainer : 'Tanpa desainer' }}</td>
                <td class="px-4 py-4 text-[12px] text-right text-slate-600">Rp {{ number_format($row->biaya_desain, 0, ',', '.') }}</td>
                <td class="px-4 py-4 text-[12px] text-center text-slate-500">{{ angka($row->komisi_desain_persen, 2) }}%</td>
                <td class="px-4 py-4 text-[12px] text-right font-medium text-indigo-600">Rp {{ number_format($row->komisi_desain, 0, ',', '.') }}</td>
                <td class="px-6 py-4 text-center">
                    @if($row->komisi_status === 'Dibayar')
                        <span class="text-[10px] font-medium uppercase text-emerald-600 bg-emerald-50 rounded px-2 py-1" title="Dibayar {{ \Carbon\Carbon::parse($row->komisi_dibayar_at)->translatedFormat('d M Y H:i') }}">Dibayar</span>
                    @elseif($row->komisi_status === 'Belum')
                        <span class="text-[10px] font-medium uppercase text-amber-600 bg-amber-50 rounded px-2 py-1" title="Menunggu pembayaran komisi">Belum</span>
                    @else
                        <span class="text-slate-500" title="Tidak ada komisi (tanpa desainer atau persen kelas 0%)">-</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="8"><x-empty-state title="Belum ada data" description="Tidak ada item berbiaya desain pada filter ini." /></td></tr>
            @endforelse
        </tbody>
    </x-table>
    </div>

    <div class="hidden print:block">
        <x-laporan.print-table title="Detail Per Item" :headers="['Nota', 'Tanggal', 'Item', 'Konsumen', 'Desainer', 'Biaya Desain', '%', 'Komisi', 'Status']" :align="['left', 'left', 'left', 'left', 'left', 'right', 'center', 'right', 'center']">
            @forelse($data as $row)
            <tr>
                <td class="px-3 py-1.5 font-medium text-slate-700">{{ $row->nomor_nota }}</td>
                <td class="px-3 py-1.5 text-slate-600">{{ \Carbon\Carbon::parse($row->tgl_order)->format('d/m/Y') }}</td>
                <td class="px-3 py-1.5 text-slate-700">{{ $row->nama_file }}</td>
                <td class="px-3 py-1.5 text-slate-600">{{ $row->nama_konsumen ?? '-' }}</td>
                <td class="px-3 py-1.5 text-slate-600">{{ $row->id_desainer ? $row->nama_desainer : 'Tanpa desainer' }}</td>
                <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($row->biaya_desain, 0, ',', '.') }}</td>
                <td class="px-3 py-1.5 text-center text-slate-600">{{ angka($row->komisi_desain_persen, 2) }}%</td>
                <td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ number_format($row->komisi_desain, 0, ',', '.') }}</td>
                <td class="px-3 py-1.5 text-center text-slate-600">{{ $row->komisi_status ?? '-' }}</td>
            </tr>
            @empty
            <tr><td class="px-3 py-1.5 text-slate-400 italic" colspan="9">Belum ada data.</td></tr>
            @endforelse
        </x-laporan.print-table>
    </div>

    <div class="no-print">
        <x-table.pagination :paginator="$data" />
    </div>
</div>

@push('scripts')
<script>
    function konfirmasiBayarKomisi(e, form, nama, nominal) {
        e.preventDefault();
        Swal.fire({
            title: 'Bayar komisi desain?',
            html: `Komisi belum dibayar untuk <b>${escHtml(nama)}</b> pada periode ini sebesar <b>Rp ${Math.round(nominal).toLocaleString('id-ID')}</b> akan dicatat sebagai pengeluaran Kas Kecil (kategori Komisi Desainer).`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563EB',
            confirmButtonText: 'Ya, Bayar',
            cancelButtonText: 'Batal',
            customClass: { confirmButton: 'rounded-lg px-6 py-2.5', cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600' },
        }).then((r) => { if (r.isConfirmed) form.submit(); });
        return false;
    }
</script>
@endpush
@endsection
