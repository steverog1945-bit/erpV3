@extends('layouts.app')

@section('content')
@include('partials.rupiah-format-script')
<style>
    @media print {
        aside, .no-print { display: none !important; }
        main { margin-left: 0 !important; padding: 28px 32px !important; }
        body { background: #fff !important; }
    }
</style>
@include('partials.print-report-header', ['docTitle' => 'Kas Kecil'])

<div class="w-full px-4 sm:px-6 lg:px-8 py-10 print:pt-0 max-w-[1600px] mx-auto">

    <div class="print:hidden">
        <x-page-header title="Kas Kecil">
            <x-slot:subtitle>
                Pembukuan terpisah dari laci kasir. Masuk: saldo awal, pembayaran order jersey (otomatis), setoran pemilik. Keluar: belanja kecil kebutuhan toko seperti ATK, komisi desainer, upah jahit.
            </x-slot:subtitle>
            <x-slot:actions>
                <x-button type="button" onclick="window.print()" variant="secondary" class="no-print">
                    <i class="fas fa-print"></i>
                    Cetak
                </x-button>
            </x-slot:actions>
        </x-page-header>
    </div>

    @if (session('success'))
        <div class="bg-emerald-50 text-emerald-700 p-4 rounded-2xl mb-6 font-medium no-print">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="bg-rose-50 text-rose-600 p-4 rounded-2xl mb-6 font-medium no-print">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- SALDO AWAL: sekali, oleh Administrator, sebelum pemakaian Kas Kecil dihitung --}}
    @unless($adaSaldoAwal)
        <x-card class="mb-6 no-print">
            @if($bolehSaldoAwal)
                <form method="POST" action="{{ route('kas-kecil.saldo-awal') }}" class="flex flex-wrap items-center gap-4"
                      data-konfirmasi="Tetapkan saldo awal Kas Kecil? Nominal ini dicatat sebagai modal yang sudah ada di Kas Kecil.">
                    @csrf
                    <div class="w-full md:w-64 lg:w-72">
                        <p class="text-sm font-medium text-slate-900">Saldo awal Kas Kecil belum ditetapkan</p>
                        <p class="text-xs text-slate-500 mt-0.5">Isi uang yang sudah ada di Kas Kecil sebelum pencatatan dimulai. Hanya sekali.</p>
                    </div>
                    <div class="w-full md:w-44">
                        <x-form.group label="Tanggal" name="tanggal" required dalam>
                            <x-form.input type="date" name="tanggal" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required />
                        </x-form.group>
                    </div>
                    <div class="w-full md:w-56">
                        <x-form.group label="Saldo awal (Rp)" name="nominal" required dalam>
                            <x-form.input type="text" inputmode="numeric" name="nominal" required class="format-ribuan" />
                        </x-form.group>
                    </div>
                    <x-button type="submit">Tetapkan Saldo Awal</x-button>
                </form>
            @else
                <p class="text-sm text-amber-700"><i class="fas fa-circle-info text-xs"></i> Saldo awal Kas Kecil belum ditetapkan. Minta Administrator menetapkannya.</p>
            @endif
        </x-card>
    @endunless

    {{-- FORM TAMBAH --}}
    <x-card class="mb-6 no-print">
        <form method="POST" action="{{ route('kas-kecil.store') }}" id="form-kas-kecil">
            @csrf
            <style>.kk-label-tersembunyi > label { visibility: hidden; }</style>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-start">
                <x-form.group label="Jenis" required>
                    <x-form.select name="jenis" id="kk-jenis" required>
                        <option value="Keluar" {{ old('jenis', 'Keluar') === 'Keluar' ? 'selected' : '' }}>Pengeluaran</option>
                        <option value="Masuk" {{ old('jenis') === 'Masuk' ? 'selected' : '' }}>Pemasukan</option>
                    </x-form.select>
                </x-form.group>
                <x-form.group label="Tanggal" required>
                    <x-form.input type="date" name="tanggal" value="{{ old('tanggal', date('Y-m-d')) }}" required />
                </x-form.group>
                <x-form.group label="Kategori" required>
                    <x-form.select name="id_kategori" id="kk-kategori" required>
                        <option value="">-- Pilih Kategori --</option>
                        @foreach($kategoriList as $kat)
                            <option value="{{ $kat->id_kategori_kas_kecil }}" data-jenis="{{ $kat->jenis }}" {{ (string) old('id_kategori') === (string) $kat->id_kategori_kas_kecil ? 'selected' : '' }}>{{ $kat->nama_kategori }}</option>
                        @endforeach
                    </x-form.select>
                    <a href="{{ route('kategori-kas-kecil.index') }}" class="text-[10px] font-medium text-indigo-500 uppercase tracking-widest hover:underline ml-2 mt-1 inline-block">
                        <i class="fas fa-cog"></i> Kelola Kategori
                    </a>
                </x-form.group>
                <x-form.group label="Nominal (Rp)" required>
                    <x-form.input type="text" inputmode="numeric" name="nominal" value="{{ old('nominal') }}" required placeholder="0" class="format-ribuan" />
                </x-form.group>
                {{-- Label tak terlihat dengan gaya yang sama membuat tombol sejajar tepat dengan kolom isian, tidak terdorong
                     oleh tautan "Kelola Kategori" di bawah kolom kategori. --}}
                <x-form.group label="Catat" class="kk-label-tersembunyi">
                    <x-button type="submit" class="w-full" style="height: 38px">
                        <i class="fas fa-plus"></i> Catat
                    </x-button>
                </x-form.group>
            </div>
            <x-form.group label="Keterangan" class="mt-4" required>
                <x-form.input type="text" name="keterangan" value="{{ old('keterangan') }}" required placeholder="mis. Beli tinta printer & kertas HVS untuk admin" />
            </x-form.group>
        </form>
    </x-card>

    {{-- FILTER --}}
    <x-card class="mb-6 no-print">
        <form method="GET" action="{{ route('kas-kecil.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Dari Tanggal</label>
                <x-form.input type="date" name="tgl_dari" value="{{ $tglDari }}" />
            </div>
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Sampai Tanggal</label>
                <x-form.input type="date" name="tgl_sampai" value="{{ $tglSampai }}" />
            </div>
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Jenis</label>
                <x-form.select name="jenis">
                    <option value="">-- Semua --</option>
                    <option value="Masuk" {{ request('jenis') === 'Masuk' ? 'selected' : '' }}>Pemasukan</option>
                    <option value="Keluar" {{ request('jenis') === 'Keluar' ? 'selected' : '' }}>Pengeluaran</option>
                </x-form.select>
            </div>
            <div class="w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Kategori</label>
                <x-form.select name="id_kategori">
                    <option value="">-- Semua --</option>
                    @foreach($kategoriList as $kat)
                        <option value="{{ $kat->id_kategori_kas_kecil }}" {{ (string) request('id_kategori') === (string) $kat->id_kategori_kas_kecil ? 'selected' : '' }}>{{ $kat->nama_kategori }}</option>
                    @endforeach
                </x-form.select>
            </div>

            <x-button type="submit">
                <i class="fas fa-filter"></i>
                Filter
            </x-button>

            @if(request()->anyFilled(['tgl_dari', 'tgl_sampai', 'id_kategori', 'jenis']))
                <a href="{{ route('kas-kecil.index') }}" class="text-[11px] font-medium text-rose-500 uppercase tracking-widest hover:underline ml-2">
                    Reset
                </a>
            @endif
        </form>
    </x-card>

    {{-- RESUME - kartu berwarna di layar (tidak diubah), tabel standar
         polos khusus cetak. --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6 print:hidden">
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm min-w-0">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Pemasukan Tunai</p>
            <h3 class="text-xl font-medium break-words text-emerald-600 mt-1">Rp {{ number_format($masukTunai, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm min-w-0">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Pemasukan Non-Tunai</p>
            <h3 class="text-xl font-medium break-words text-slate-700 mt-1">Rp {{ number_format($masukNonTunai, 0, ',', '.') }}</h3>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm min-w-0">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Pengeluaran dari Kas Kecil</p>
            <h3 class="text-xl font-medium break-words text-rose-500 mt-1">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</h3>
            @if($pengeluaranPemilik > 0)
                <p class="text-[11px] text-slate-400 mt-1">Dibayar langsung pemilik: Rp {{ number_format($pengeluaranPemilik, 0, ',', '.') }}</p>
            @endif
        </div>
        <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm min-w-0">
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Saldo Kas Kecil Saat Ini</p>
            <h3 class="text-xl font-medium break-words {{ $saldoKasKecil < 0 ? 'text-rose-600' : 'text-slate-900' }} mt-1">Rp {{ number_format($saldoKasKecil, 0, ',', '.') }}</h3>
            <p class="text-[11px] text-slate-400 mt-1">Seluruh catatan, tidak terpengaruh filter</p>
        </div>
    </div>
    <div class="hidden print:block">
        <x-laporan.resume-table title="Resume" :rows="[
            'Jumlah Catatan' => number_format($data->total(), 0, ',', '.'),
            'Pemasukan Tunai' => 'Rp ' . number_format($masukTunai, 0, ',', '.'),
            'Pemasukan Non-Tunai' => 'Rp ' . number_format($masukNonTunai, 0, ',', '.'),
            'Pengeluaran dari Kas Kecil' => 'Rp ' . number_format($totalPengeluaran, 0, ',', '.'),
            'Dibayar langsung pemilik' => 'Rp ' . number_format($pengeluaranPemilik, 0, ',', '.'),
            'Saldo Kas Kecil saat ini' => 'Rp ' . number_format($saldoKasKecil, 0, ',', '.'),
        ]" />
    </div>

    <div class="print:hidden">
    <x-table>
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-6 py-5 text-left">Tanggal</th>
                <th class="px-4 py-5 text-left">Jenis</th>
                <th class="px-4 py-5 text-left">Kategori</th>
                <th class="px-4 py-5 text-left">Keterangan</th>
                <th class="px-4 py-5 text-left">Dicatat Oleh</th>
                <th class="px-4 py-5 text-right">Nominal</th>
                <th class="px-4 py-5 text-center w-20 no-print">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($data as $i => $k)
            <tr class="hover:bg-slate-50/50 transition-all">
                <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $data->firstItem() + $i }}</td>
                <td class="px-6 py-5">
                    <span class="text-[13px] font-medium text-slate-700">{{ \Carbon\Carbon::parse($k->tanggal)->translatedFormat('d M Y') }}</span>
                </td>
                <td class="px-4 py-5">
                    <span class="text-[10px] font-medium uppercase rounded px-2 py-1 {{ $k->jenis === 'Masuk' ? 'text-emerald-600 bg-emerald-50' : 'text-rose-500 bg-rose-50' }}">{{ $k->jenis === 'Masuk' ? 'Masuk' : 'Keluar' }}</span>
                    @if($k->metode !== 'Tunai')<span class="text-[10px] font-medium uppercase text-slate-500 bg-slate-100 rounded px-2 py-1 ml-1">{{ $k->metode }}</span>@endif
                </td>
                <td class="px-4 py-5">
                    <span class="text-[11px] font-medium uppercase text-slate-600 bg-slate-100 rounded px-2 py-1">{{ $k->sumber === 'jersey' ? 'Pesanan Jersey' : ($k->sumber === 'hutang' ? 'Bayar Hutang Pemasok' : ($k->sumber === 'saldo_awal' ? 'Saldo Awal' : ($k->kategori->nama_kategori ?? '-'))) }}</span>
                </td>
                <td class="px-4 py-5">
                    <span class="text-[12px] text-slate-600">{{ $k->keterangan }}</span>
                </td>
                <td class="px-4 py-5">
                    <span class="text-[12px] text-slate-400">{{ $k->user->username ?? '-' }}</span>
                </td>
                <td class="px-4 py-5 text-right">
                    <span class="text-[12px] font-medium {{ $k->jenis === 'Masuk' ? 'text-emerald-600' : 'text-rose-500' }}">{{ $k->jenis === 'Masuk' ? '+' : '-' }} Rp {{ number_format($k->nominal, 0, ',', '.') }}</span>
                </td>
                <td class="px-4 py-5 text-center no-print">
                    @if(! in_array($k->sumber, ['manual', 'saldo_awal'], true))
                    <span class="text-slate-500" title="Otomatis dari {{ $k->sumber === 'hutang' ? 'pembayaran hutang supplier' : 'pembayaran order jersey' }}"><i class="fas fa-link text-xs"></i></span>
                    @else
                    <form method="POST" action="{{ route('kas-kecil.destroy', $k->id) }}" onsubmit="return konfirmasiHapusKasKecil(event, this)">
                        @csrf @method('DELETE')
                        <button type="submit" class="w-8 h-8 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50" title="Hapus"><i class="far fa-trash-alt text-xs"></i></button>
                    </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8">
                    <x-empty-state title="Belum ada catatan kas kecil" description="Sesuaikan filter tanggal, atau catat pengeluaran baru lewat form di atas." />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>
    </div>

    {{-- Versi cetak: tabel polos sederhana, gaya visual sama dengan
         laporan lain (font 10/11px konsisten, tanpa padding lega UI). --}}
    <div class="hidden print:block">
        <x-laporan.print-table title="Detail Kas Kecil" :headers="['#', 'Tanggal', 'Jenis', 'Kategori', 'Keterangan', 'Dicatat Oleh', 'Nominal']" :align="['center', 'left', 'left', 'left', 'left', 'left', 'right']">
            @forelse($data as $i => $k)
            <tr>
                <td class="px-3 py-1.5 text-center text-slate-400">{{ $data->firstItem() + $i }}</td>
                <td class="px-3 py-1.5 font-medium text-slate-700">{{ \Carbon\Carbon::parse($k->tanggal)->format('d/m/Y') }}</td>
                <td class="px-3 py-1.5 text-slate-600">{{ $k->jenis === 'Masuk' ? 'Masuk' : 'Keluar' }}{{ $k->metode !== 'Tunai' ? ' (' . $k->metode . ')' : '' }}</td>
                <td class="px-3 py-1.5 text-slate-600">{{ $k->sumber === 'jersey' ? 'Pesanan Jersey' : ($k->sumber === 'hutang' ? 'Bayar Hutang Pemasok' : ($k->sumber === 'saldo_awal' ? 'Saldo Awal' : ($k->kategori->nama_kategori ?? '-'))) }}</td>
                <td class="px-3 py-1.5 text-slate-600">{{ $k->keterangan }}</td>
                <td class="px-3 py-1.5 text-slate-600">{{ $k->user->username ?? '-' }}</td>
                <td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ $k->jenis === 'Masuk' ? '' : '-' }}{{ number_format($k->nominal, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td class="px-3 py-1.5 text-slate-400 italic" colspan="7">Belum ada catatan kas kecil.</td>
            </tr>
            @endforelse
            @if($data->isNotEmpty())
            <tr class="bg-slate-50 border-t border-slate-200">
                <td class="px-3 py-1.5 font-medium text-slate-700 uppercase" colspan="6">Selisih Masuk - Keluar (halaman ini)</td>
                <td class="px-3 py-1.5 text-right font-medium text-slate-800">{{ number_format($data->where('jenis', 'Masuk')->sum('nominal') - $data->where('jenis', 'Keluar')->sum('nominal'), 0, ',', '.') }}</td>
            </tr>
            @endif
        </x-laporan.print-table>
    </div>

    <div class="no-print">
        <x-table.pagination :paginator="$data" />
    </div>

</div>

@push('scripts')
<script>
    // Pilihan kategori pada form mengikuti jenis (pemasukan / pengeluaran).
    function saringKategoriKasKecil() {
        const jenis = document.getElementById('kk-jenis').value;
        const sel = document.getElementById('kk-kategori');
        [...sel.options].forEach(o => { if (o.value) { const cocok = o.dataset.jenis === jenis; o.hidden = !cocok; o.disabled = !cocok; } });
        if (sel.selectedOptions[0] && sel.selectedOptions[0].disabled) sel.value = '';
        if (window.jQuery && jQuery(sel).data('select2')) jQuery(sel).trigger('change.select2');
    }
    document.addEventListener('DOMContentLoaded', () => {
        const j = document.getElementById('kk-jenis');
        if (j) { j.addEventListener('change', saringKategoriKasKecil); saringKategoriKasKecil(); }
    });
    function konfirmasiHapusKasKecil(e, form) {
        e.preventDefault();
        Swal.fire({
            title: 'Ajukan pembatalan catatan kas kecil?',
            text: 'Catatan baru dibatalkan setelah disetujui Administrator; jurnalnya akan dibalik.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            confirmButtonText: 'Ya, Ajukan',
            cancelButtonText: 'Batal',
            customClass: { confirmButton: 'rounded-lg px-6 py-2.5', cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600' },
        }).then((result) => {
            if (result.isConfirmed) form.submit();
        });
        return false;
    }
</script>
@endpush
@endsection

