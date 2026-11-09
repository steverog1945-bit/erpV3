@extends('layouts.app')

@section('content')
@php
    $n = fn ($v, $d = 2) => angka($v, $d);
    $rp = fn ($v) => 'Rp ' . number_format($v, 0, ',', '.');
@endphp
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1500px] mx-auto">

    <x-page-header title="Biaya Tinta">
        <x-slot:subtitle>
            <i class="fas fa-fill-drip text-amber-400"></i>
            Perkiraan pemakaian dan biaya tinta/toner dari pesanan, dihitung dari luas cetak dan pengaturan pemakaian per mesin
        </x-slot:subtitle>
    </x-page-header>

    <x-card class="mb-8">
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div><label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Dari</label><x-form.input type="date" name="tgl_dari" value="{{ $dari->toDateString() }}" /></div>
            <div><label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Sampai</label><x-form.input type="date" name="tgl_sampai" value="{{ $sampai->toDateString() }}" /></div>
            <x-button type="submit" variant="primary"><i class="fas fa-filter"></i> Tampilkan</x-button>
        </form>
    </x-card>

    @if(count($h['bahan_tanpa_harga']))
        <div class="mb-6 p-4 bg-amber-50 border border-amber-100 rounded-xl text-sm text-amber-700">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            Harga belum diisi untuk: <b>{{ implode(', ', $h['bahan_tanpa_harga']) }}</b>. Biayanya dihitung Rp 0 sampai harga diisi di Master Bahan Baku.
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <x-card><div class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Total Biaya Tinta</div><div class="text-2xl font-medium text-slate-700 mt-1">{{ $rp($h['total_biaya']) }}</div></x-card>
        <x-card><div class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Total Luas Cetak</div><div class="text-2xl font-medium text-slate-700 mt-1">{{ $n($h['total_m2']) }} m²</div></x-card>
        <x-card><div class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Biaya Tinta per m²</div><div class="text-2xl font-medium text-slate-700 mt-1">{{ $h['total_m2'] > 0 ? $rp($h['total_biaya'] / $h['total_m2']) : '-' }}</div></x-card>
    </div>

    <h3 class="text-[11px] font-medium text-slate-400 uppercase tracking-widest mb-3 px-1">Pemakaian per Bahan Tinta</h3>
    <x-table class="mb-8">
        <thead>
            <tr>
                <th class="px-8 py-4 text-left">Mesin</th>
                <th class="px-4 py-4 text-left">Bahan Tinta</th>
                <th class="px-4 py-4 text-right">Volume Cetak</th>
                <th class="px-4 py-4 text-right">Pemakaian</th>
                <th class="px-8 py-4 text-right">Biaya</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($h['per_bahan'] as $r)
            <tr class="hover:bg-slate-50/30">
                <td class="px-8 py-4 text-[13px] font-medium text-slate-700">{{ $r->mesin }}</td>
                <td class="px-4 py-4 text-sm text-slate-600">{{ $r->bahan }}</td>
                <td class="px-4 py-4 text-right text-sm">{{ $n($r->volume_dasar) }} {{ $r->dasar === 'm2' ? 'm²' : 'lembar' }}</td>
                <td class="px-4 py-4 text-right text-sm">{{ $n($r->pakai, 3) }} {{ $r->satuan_hitung }}</td>
                <td class="px-8 py-4 text-right text-sm font-medium {{ $r->harga_ada ? '' : 'text-amber-500' }}">{{ $r->harga_ada ? $rp($r->biaya) : 'Harga kosong' }}</td>
            </tr>
            @empty
            <tr><td colspan="5"><x-empty-state title="Tidak ada pesanan yang cocok dengan pengaturan pemakaian pada periode ini" /></td></tr>
            @endforelse
        </tbody>
    </x-table>

    <h3 class="text-[11px] font-medium text-slate-400 uppercase tracking-widest mb-3 px-1">Biaya Tinta per Produk</h3>
    <x-table class="mb-8">
        <thead>
            <tr>
                <th class="px-8 py-4 text-left">Produk</th>
                <th class="px-4 py-4 text-right">Luas Cetak</th>
                <th class="px-4 py-4 text-right">Omzet</th>
                <th class="px-4 py-4 text-right">Biaya Tinta</th>
                <th class="px-4 py-4 text-right">per m²</th>
                <th class="px-8 py-4 text-right">% Omzet</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($h['per_produk'] as $r)
            <tr class="hover:bg-slate-50/30">
                <td class="px-8 py-4 text-[13px] font-medium text-slate-700">{{ $r->nama }}</td>
                <td class="px-4 py-4 text-right text-sm">{{ $r->m2 > 0 ? $n($r->m2) . ' m²' : '-' }}</td>
                <td class="px-4 py-4 text-right text-sm">{{ $rp($r->omzet) }}</td>
                <td class="px-4 py-4 text-right text-sm font-medium">{{ $rp($r->biaya_tinta) }}</td>
                <td class="px-4 py-4 text-right text-sm">{{ $r->biaya_per_m2 !== null ? $rp($r->biaya_per_m2) : '-' }}</td>
                <td class="px-8 py-4 text-right text-sm font-medium {{ $r->persen_omzet >= 15 ? 'text-rose-600' : 'text-slate-600' }}">{{ $r->persen_omzet }}%</td>
            </tr>
            @empty
            <tr><td colspan="6"><x-empty-state title="Tidak ada pesanan produk pada periode ini" /></td></tr>
            @endforelse
        </tbody>
    </x-table>

    <div class="flex items-center justify-between mb-3 px-1">
        <h3 class="text-[11px] font-medium text-slate-400 uppercase tracking-widest">Pengaturan Pemakaian per Mesin</h3>
        <x-button size="sm" variant="primary" onclick="bukaPengaturanTinta()"><i class="fas fa-plus"></i> Tambah / Ubah</x-button>
    </div>
    <x-table>
        <thead>
            <tr>
                <th class="px-8 py-4 text-left">Mesin</th>
                <th class="px-4 py-4 text-left">Bahan Tinta</th>
                <th class="px-4 py-4 text-right">Pemakaian</th>
                <th class="px-4 py-4 text-left">Keterangan</th>
                <th class="px-8 py-4 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($pengaturan as $p)
            <tr class="hover:bg-slate-50/30">
                <td class="px-8 py-4 text-[13px] font-medium text-slate-700">{{ $p->nama_mesin }}</td>
                <td class="px-4 py-4 text-sm text-slate-600">{{ $p->nama_bahan }}</td>
                <td class="px-4 py-4 text-right text-sm">{{ $n($p->pemakaian, 6) }} per {{ $p->dasar === 'm2' ? 'm²' : 'lembar' }}</td>
                <td class="px-4 py-4 text-sm text-slate-400">{{ $p->keterangan }}</td>
                <td class="px-8 py-4 text-right">
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick='bukaPengaturanTinta(@json($p))' class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-all" title="Ubah"><i class="far fa-edit text-sm"></i></button>
                        <form method="POST" action="{{ route('laporan.biaya-tinta.hapus', $p->id) }}" data-konfirmasi="Hapus pengaturan ini?">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all" title="Hapus"><i class="far fa-trash-alt text-sm"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="5"><x-empty-state title="Belum ada pengaturan pemakaian tinta" /></td></tr>
            @endforelse
        </tbody>
    </x-table>
    <p class="text-[11px] text-slate-400 mt-3 px-1">Perkiraan, bukan hasil ukur riil: luas cetak = panjang × lebar × qty dari pesanan (nota dibatalkan tidak dihitung), dikalikan pemakaian per m² (atau per lembar), lalu dikali harga rata-rata bahan. Angka awal adalah acuan; ubah sesuai hasil ukur di lapangan.</p>

    <x-modal id="pengaturan-tinta" title="Pengaturan Pemakaian Tinta" maxWidth="md">
        <form method="POST" action="{{ route('laporan.biaya-tinta.simpan') }}" class="space-y-5">
            @csrf
            <x-form.group label="Mesin" required dalam>
                <x-form.select name="id_mesin" id="pt_id_mesin" required>
                    @foreach($mesin as $m)<option value="{{ $m->id_mesin }}">{{ $m->nama_mesin }}</option>@endforeach
                </x-form.select>
            </x-form.group>
            <x-form.group label="Bahan Tinta" required dalam>
                <x-form.select name="id_bahan" id="pt_id_bahan" required>
                    @foreach($bahanTinta as $b)<option value="{{ $b->id_bahan }}">{{ $b->nama_bahan }}</option>@endforeach
                </x-form.select>
            </x-form.group>
            <div class="grid grid-cols-2 gap-4">
                <x-form.group label="Dasar Hitung" required dalam>
                    <x-form.select name="dasar" id="pt_dasar" required>
                        <option value="m2">per m²</option>
                        <option value="lembar">per lembar</option>
                    </x-form.select>
                </x-form.group>
                <x-form.group label="Pemakaian" required help="Dalam satuan hitung bahan (ml/gram/pcs)." dalam>
                    <x-form.input type="number" step="0.000001" min="0" name="pemakaian" id="pt_pemakaian" required />
                </x-form.group>
            </div>
            <x-form.group label="Keterangan" dalam>
                <x-form.input type="text" name="keterangan" id="pt_keterangan" maxlength="255" />
            </x-form.group>
            <div class="pt-4 flex gap-3">
                <x-button type="button" variant="secondary" onclick="closeModal('pengaturan-tinta')" class="flex-1">Batalkan</x-button>
                <x-button type="submit" class="flex-1">Simpan</x-button>
            </div>
        </form>
    </x-modal>
</div>
@endsection

@push('scripts')
<script>
    function bukaPengaturanTinta(p) {
        p = p || {};
        document.getElementById('pt_id_mesin').value = p.id_mesin || document.getElementById('pt_id_mesin').options[0]?.value;
        document.getElementById('pt_id_bahan').value = p.id_bahan || document.getElementById('pt_id_bahan').options[0]?.value;
        document.getElementById('pt_dasar').value = p.dasar || 'm2';
        document.getElementById('pt_pemakaian').value = p.pemakaian !== undefined ? parseFloat(p.pemakaian) : '';
        document.getElementById('pt_keterangan').value = p.keterangan || '';
        openModal('pengaturan-tinta');
    }
</script>
@endpush
