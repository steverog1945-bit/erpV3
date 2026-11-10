@extends('layouts.app')

@section('content')
<style>
    @media print {
        @page { size: A4 landscape; margin: 10mm; }
        aside, .no-print, .topbar { display: none !important; }
        {{-- padding 0 membuat kop laporan menempel ke tepi kertas (keluhan
             user: "mepet kanan kiri"). @page sudah memberi 10mm; 8mm lagi di
             sini menghasilkan ~18mm dari tepi - senada dengan halaman
             portrait yang beri ruang lebih longgar. --}}
        main { margin-left: 0 !important; padding: 0 8mm !important; }
        body { background: #fff !important; }
        .eval-section { break-inside: auto; }
        .eval-block { break-inside: avoid; }
        .eval-table th, .eval-table td { padding: 3px 6px !important; font-size: 10px !important; }
        .page-break { break-before: page; }
    }
    .eval-table { width: 100%; border-collapse: collapse; }
    .eval-table th { background: #F1F5F1; color: #33415C; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: 8px 10px; border-bottom: 1px solid #B9C3DD; }
    .eval-table td { padding: 7px 10px; border-bottom: 1px solid #B9C3DD; font-size: 12.5px; color: #33415C; }
    .eval-table td.num, .eval-table th.num { text-align: right; font-variant-numeric: tabular-nums; }
    .eval-table td.tengah, .eval-table th.tengah { text-align: center; }
    .isian { min-width: 70px; border-bottom: 1px dotted #94a3b8; }
</style>

@php
    $n = fn ($v, $d = 2) => angka((float) $v, $d);
    $rp = fn ($v) => number_format((float) $v, 0, ',', '.');
    $dicetak = now()->translatedFormat('d F Y, H:i');
    $judul = 'Lembar Evaluasi ' . $periode;
@endphp

@include('partials.print-report-header', ['docTitle' => $judul])

<div class="w-full px-4 sm:px-6 lg:px-8 py-10 print:p-0 max-w-[1600px] mx-auto">

    <div class="print:hidden no-print">
        <x-page-header title="Lembar Evaluasi Master">
            <x-slot:subtitle>
                <i class="fas fa-clipboard-check text-amber-400"></i>
                Cetak bahan baku, BOM, dan seluruh tier harga produk untuk evaluasi harian atau bulanan
            </x-slot:subtitle>
            <x-slot:actions>
                <a href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 text-xs font-medium uppercase tracking-wider">
                    <i class="fas fa-file-csv"></i> Unduh CSV
                </a>
                <x-button type="button" onclick="window.print()"><i class="fas fa-print"></i> Cetak</x-button>
            </x-slot:actions>
        </x-page-header>

        <x-card class="mb-8">
            <form method="GET" class="grid grid-cols-2 lg:grid-cols-6 gap-4 items-end">
                <x-form.group label="Isi Lembar">
                    <x-form.select name="jenis">
                        <option value="semua" @selected($jenis === 'semua')>Semua (bahan, BOM, harga)</option>
                        <option value="bahan" @selected($jenis === 'bahan')>Bahan Baku saja</option>
                        <option value="bom" @selected($jenis === 'bom')>BOM saja</option>
                        <option value="harga" @selected($jenis === 'harga')>Harga Produk saja</option>
                    </x-form.select>
                </x-form.group>
                <x-form.group label="Untuk Evaluasi">
                    <x-form.select name="periode">
                        <option value="harian" @selected($periode === 'Harian')>Harian</option>
                        <option value="bulanan" @selected($periode === 'Bulanan')>Bulanan</option>
                    </x-form.select>
                </x-form.group>
                <x-form.group label="Mesin">
                    <x-form.select name="id_mesin">
                        <option value="">Semua Mesin</option>
                        @foreach($mesin as $m)<option value="{{ $m->id_mesin }}" @selected($idMesin === (int) $m->id_mesin)>{{ $m->nama_mesin }}</option>@endforeach
                    </x-form.select>
                </x-form.group>
                <x-form.group label="Kategori Bahan">
                    <x-form.select name="id_kategori">
                        <option value="">Semua Kategori</option>
                        @foreach($kategori as $k)<option value="{{ $k->id_kategori }}" @selected($idKategori === (int) $k->id_kategori)>{{ $k->nama_kategori }}</option>@endforeach
                    </x-form.select>
                </x-form.group>
                <x-form.group label="Status Bahan">
                    <x-form.select name="status">
                        <option value="aktif" @selected($status === 'aktif')>Aktif saja</option>
                        <option value="semua" @selected($status === 'semua')>Semua</option>
                    </x-form.select>
                </x-form.group>
                <label class="flex items-center gap-2 text-xs font-medium text-slate-500 pb-3">
                    <input type="hidden" name="cek_fisik" value="0">
                    <input type="checkbox" name="cek_fisik" value="1" @checked($cekFisik) onchange="this.form.requestSubmit()" class="rounded border-slate-300 text-indigo-600">
                    Kolom cek fisik
                </label>
            </form>
        </x-card>
    </div>

    <div class="hidden print:flex justify-between text-[11px] text-slate-600 mb-4">
        <span>Evaluasi {{ $periode }} &middot; dicetak {{ $dicetak }} oleh {{ optional(Auth::user()->karyawan)->nama_karyawan ?? Auth::user()->username }}</span>
        <span>Data per saat cetak</span>
    </div>

    {{-- ===================== BAHAN BAKU ===================== --}}
    @if(in_array($jenis, ['semua', 'bahan'], true))
    <section class="eval-section mb-10">
        <h2 class="text-[13px] font-medium text-slate-500 uppercase tracking-widest mb-3"><i class="fas fa-boxes-stacked mr-1.5 text-slate-500 print:hidden"></i>A. Bahan Baku</h2>
        <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden print:border-0 print:rounded-none">
            <table class="eval-table">
                <thead>
                    <tr>
                        <th class="tengah" style="width:36px">No</th>
                        <th style="text-align:left">Kode</th>
                        <th style="text-align:left">Bahan</th>
                        <th style="text-align:left">Mesin / Kategori</th>
                        <th class="num">Stok</th>
                        <th class="num">Stok Min</th>
                        <th class="num">Harga Rata-rata</th>
                        <th class="num">Nilai Persediaan</th>
                        @if($cekFisik)<th class="tengah">Stok Fisik</th><th class="tengah">Selisih</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($bahan as $i => $b)
                    <tr>
                        <td class="tengah">{{ $i + 1 }}</td>
                        <td>{{ $b->kode_bahan }}</td>
                        <td style="font-weight:600">{{ $b->nama_bahan }}@if($b->status === 'nonaktif') <span style="color:#94a3b8">(nonaktif)</span>@endif</td>
                        <td>{{ $b->nama_mesin ?? '-' }} <span style="color:#94a3b8">/ {{ $b->nama_kategori ?? '-' }}</span></td>
                        <td class="num" style="{{ $b->menipis ? 'color:#93481A;font-weight:700' : '' }}">{{ $n($b->stok) }} {{ $b->satuan_stok }}</td>
                        <td class="num">{{ $n($b->stok_minimal) }}</td>
                        <td class="num">@if((float) $b->harga_rata_rata > 0)Rp {{ $rp($b->harga_rata_rata) }}@else<span style="color:#d97706">belum ada</span>@endif</td>
                        <td class="num">{{ $b->nilai > 0 ? 'Rp ' . $rp($b->nilai) : '-' }}</td>
                        @if($cekFisik)<td class="tengah"><div class="isian">&nbsp;</div></td><td class="tengah"><div class="isian">&nbsp;</div></td>@endif
                    </tr>
                    @empty
                    <tr><td colspan="{{ $cekFisik ? 10 : 8 }}" class="tengah" style="padding:24px;color:#94a3b8">Tidak ada bahan baku.</td></tr>
                    @endforelse
                </tbody>
                @if($bahan->isNotEmpty())
                <tfoot>
                    <tr style="background:#F5F8FF;font-weight:700">
                        <td colspan="4" style="padding:8px 10px">Total {{ $bahan->count() }} bahan &middot; {{ $bahan->where('menipis', true)->count() }} menipis &middot; {{ $bahan->filter(fn ($b) => (float) $b->harga_rata_rata <= 0)->count() }} belum ada harga</td>
                        <td colspan="3"></td>
                        <td class="num">Rp {{ $rp($bahan->sum('nilai')) }}</td>
                        @if($cekFisik)<td colspan="2"></td>@endif
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </section>
    @endif

    {{-- ===================== BOM ===================== --}}
    @if(in_array($jenis, ['semua', 'bom'], true))
    <section class="eval-section mb-10 {{ $jenis === 'semua' ? 'page-break' : '' }}">
        <h2 class="text-[13px] font-medium text-slate-500 uppercase tracking-widest mb-1"><i class="fas fa-sitemap mr-1.5 text-slate-500 print:hidden"></i>B. BOM per Produk</h2>
        <p class="text-[11px] text-slate-400 mb-3">Pemakaian dan biaya bahan untuk 1 satuan jual (1 m² untuk produk ukuran, 1 pcs untuk produk qty), memakai harga rata-rata bahan saat ini.</p>
        <div class="grid grid-cols-1 xl:grid-cols-2 print:grid-cols-2 gap-4">
            @forelse($bom as $p)
            <div class="eval-block bg-white rounded-2xl border border-slate-100 overflow-hidden print:rounded-none print:border-slate-300">
                <div class="px-4 py-3 bg-slate-50 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-[13px] font-medium text-slate-800">{{ $p->nama_produk }}</div>
                        <div class="text-[11px] text-slate-400">{{ $p->nama_mesin ?? '-' }} &middot; per {{ $p->satuan_jual }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-medium text-slate-400 uppercase tracking-wider">HPP bahan</div>
                        <div class="text-[14px] font-medium text-slate-800">Rp {{ $rp($p->hpp) }}</div>
                    </div>
                </div>
                <table class="eval-table">
                    <thead><tr><th style="text-align:left">Bahan</th><th class="num">Pemakaian</th><th class="num">Biaya</th></tr></thead>
                    <tbody>
                        @forelse($p->baris as $r)
                        <tr>
                            <td>{{ $r->nama_bahan }}</td>
                            <td class="num">{{ $n($r->pakai, 4) }} {{ $r->satuan_hitung }}</td>
                            <td class="num">@if($r->tanpa_harga)<span style="color:#d97706">harga kosong</span>@else Rp {{ $rp($r->biaya) }}@endif</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="tengah" style="color:#d97706">Belum ada BOM</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @empty
            <div class="text-slate-400 text-sm">Tidak ada produk aktif.</div>
            @endforelse
        </div>
    </section>
    @endif

    {{-- ===================== HARGA PRODUK ===================== --}}
    @if(in_array($jenis, ['semua', 'harga'], true))
    <section class="eval-section mb-10 {{ $jenis === 'semua' ? 'page-break' : '' }}">
        <h2 class="text-[13px] font-medium text-slate-500 uppercase tracking-widest mb-1"><i class="fas fa-tags mr-1.5 text-slate-500 print:hidden"></i>C. Harga Produk per Tier &amp; Golongan</h2>
        <p class="text-[11px] text-slate-400 mb-3">Harga jual per satuan jual, per golongan dengan rentang tiernya masing-masing. Persen di samping harga = margin terhadap HPP bahan (merah bila di bawah 20%, tidak tampil bila HPP belum lengkap).</p>
        <div class="space-y-4">
            @forelse($harga['produk'] as $p)
            <div class="eval-block bg-white rounded-2xl border border-slate-100 overflow-hidden print:rounded-none print:border-slate-300">
                <div class="px-4 py-3 bg-slate-50 flex items-center justify-between gap-3">
                    <div>
                        <span class="text-[13px] font-medium text-slate-800">{{ $p->nama_produk }}</span>
                        <span class="text-[11px] text-slate-400 ml-2">{{ $p->nama_mesin ?? '-' }} &middot; per {{ $p->satuan_jual }}</span>
                        @if($p->promo)<span class="ml-2 text-[10px] font-medium text-amber-600 bg-amber-50 px-2 py-0.5 rounded">Promo Rp {{ $rp($p->promo) }}</span>@endif
                    </div>
                    <div class="text-[11px] text-slate-500">HPP bahan <b>Rp {{ $rp($p->hpp) }}</b>@if($p->ada_harga_kosong || $p->tanpa_bom) <span style="color:#d97706">(belum lengkap)</span>@endif</div>
                </div>
                <table class="eval-table">
                    <thead><tr><th style="text-align:left;width:170px">Golongan</th><th style="text-align:left">Tier harga (qty &rarr; harga jual &middot; margin)</th></tr></thead>
                    <tbody>
                        @forelse($harga['golongan'] as $g)
                            @php $daftar = $p->per_gol[$g->id_golongan] ?? collect(); @endphp
                            @continue($daftar->isEmpty() && $p->tier->isNotEmpty())
                            <tr>
                                <td style="font-weight:600">{{ $g->nama_golongan }}</td>
                                <td>
                                    <div style="display:flex;flex-wrap:wrap;gap:6px 10px">
                                        @foreach($daftar as $t)
                                            @php $mg = \App\Services\LembarEvaluasiService::margin($t->harga, $p->hpp); @endphp
                                            <span style="display:inline-flex;align-items:baseline;gap:6px;padding:3px 10px;border:1px solid #B9C3DD;border-radius:8px;background:#F5F8FF">
                                                <span style="font-size:10.5px;color:#5D6A85">{{ $n($t->min) }}&ndash;{{ $t->max === null || $t->max >= 9999999 ? '∞' : $n($t->max) }}</span>
                                                <b style="font-variant-numeric:tabular-nums">{{ $rp($t->harga) }}</b>
                                                @if($mg !== null)<span style="font-size:10px;{{ $mg < 20 ? 'color:#93481A;font-weight:700' : 'color:#94a3b8' }}">{{ $n($mg, 1) }}%</span>@endif
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                        @endforelse
                        @if($p->tier->isEmpty())
                            <tr><td colspan="2" class="tengah" style="color:#d97706">Belum ada tier harga</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
            @empty
            <div class="text-slate-400 text-sm">Tidak ada produk aktif.</div>
            @endforelse
        </div>
    </section>
    @endif

    <div class="hidden print:grid grid-cols-3 gap-10 mt-12 text-center text-[11px] text-slate-600">
        <div>Dibuat oleh<div style="height:48px"></div><div style="border-top:1px solid #5D6A85;padding-top:4px">(............................)</div></div>
        <div>Diperiksa oleh<div style="height:48px"></div><div style="border-top:1px solid #5D6A85;padding-top:4px">(............................)</div></div>
        <div>Disetujui oleh<div style="height:48px"></div><div style="border-top:1px solid #5D6A85;padding-top:4px">(............................)</div></div>
    </div>
</div>
@endsection
