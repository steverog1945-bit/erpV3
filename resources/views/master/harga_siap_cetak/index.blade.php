@extends('layouts.app')

@section('content')
@include('partials.rupiah-format-script')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Harga File Siap Cetak">
        <x-slot:subtitle>
            Master <span class="mx-1 text-slate-500">/</span> Harga khusus order dengan file dari pelanggan (tanpa desain tim) - terpisah dari Price List biasa
        </x-slot:subtitle>
    </x-page-header>

    <div class="bg-slate-100 text-slate-600 p-4 rounded-2xl mb-6 text-xs font-medium flex items-start gap-2">
        <i class="fas fa-circle-info mt-0.5"></i>
        <span>Harga di sini <b>berdiri sendiri</b> (bukan potongan persen). Struktur sama dengan Price List: rentang qty/ukuran &times; golongan konsumen. Produk yang <b>belum diberi harga</b> tidak menawarkan opsi "File Siap Cetak" di Input Order. Order siap cetak otomatis tanpa biaya desain &amp; komisi desainer.</span>
    </div>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('harga-siap-cetak.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[240px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Produk</label>
                <x-form.input type="text" name="cari" value="{{ request('cari') }}" placeholder="Nama produk..." />
            </div>
            <div class="w-full md:w-52">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Mesin / Divisi</label>
                <x-form.select name="mesin">
                    <option value="">Semua Mesin</option>
                    @foreach($mesinList as $m)<option value="{{ $m->id_mesin }}" {{ (string) request('mesin') === (string) $m->id_mesin ? 'selected' : '' }}>{{ $m->nama_mesin }}</option>@endforeach
                </x-form.select>
            </div>
            <div class="w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status Harga</label>
                <x-form.select name="status">
                    <option value="">Semua</option>
                    <option value="sudah" {{ request('status') === 'sudah' ? 'selected' : '' }}>Sudah diatur</option>
                    <option value="belum" {{ request('status') === 'belum' ? 'selected' : '' }}>Belum diatur</option>
                </x-form.select>
            </div>
            <x-button type="submit" title="Terapkan filter"><i class="fas fa-filter"></i> Filter</x-button>
            @if(request()->anyFilled(['cari', 'mesin', 'status']))<a href="{{ route('harga-siap-cetak.index') }}" class="text-[11px] font-medium text-rose-500 uppercase tracking-widest hover:underline" title="Kembalikan filter">Reset</a>@endif
        </form>
    </x-card>

    <p class="text-xs font-medium text-slate-400 mb-3 ml-1">{{ $totalSudah }} produk sudah punya harga siap cetak.</p>

    <x-table>
        <thead>
            <tr>
                <th class="px-6 py-5 text-left">Produk</th>
                <th class="px-4 py-5 text-left">Mesin / Divisi</th>
                <th class="px-4 py-5 text-center">Satuan Tier</th>
                <th class="px-4 py-5 text-center">Status</th>
                <th class="px-8 py-5 text-center">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($produk as $p)
            <tr class="hover:bg-slate-50/50 transition-all">
                <td class="px-6 py-4 text-[13px] font-medium text-slate-700 uppercase">{{ $p->nama_produk }}</td>
                <td class="px-4 py-4 text-[12px] font-medium text-slate-500">{{ $p->nama_mesin ?? '-' }}</td>
                <td class="px-4 py-4 text-center text-[12px] text-slate-500">{{ $p->metode_hitung === 'ukuran' ? 'm²' : 'pcs' }}</td>
                <td class="px-4 py-4 text-center">
                    @if($p->jumlah_tier > 0)<x-badge variant="success">Sudah diatur</x-badge>@else<x-badge variant="warning">Belum diatur</x-badge>@endif
                </td>
                <td class="px-8 py-4 text-center">
                    <button type="button" onclick="bukaHargaSiapCetak({{ $p->id_produk }})" title="Atur harga file siap cetak produk ini" class="px-4 py-2 rounded-xl bg-indigo-50 text-indigo-600 hover:bg-indigo-100 text-[10px] font-medium uppercase tracking-widest"><i class="fas fa-tags"></i> Atur Harga</button>
                </td>
            </tr>
            @empty
            <tr><td colspan="5"><x-empty-state title="Tidak ada produk" description="Ubah filter untuk melihat produk lain." /></td></tr>
            @endforelse
        </tbody>
    </x-table>
    <x-table.pagination :paginator="$produk" />
</div>

{{-- MODAL GRID HARGA --}}
<x-modal id="harga-sc" title="Harga File Siap Cetak"
         subtitle="Harga per ukuran file yang sudah siap cetak, tanpa proses desain"
         icon="fa-file-lines" maxWidth="6xl">
    <div class="flex items-center justify-between mb-4">
        <div>
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Produk</p>
            <p class="text-base font-medium text-slate-800" id="hsc-nama">-</p>
        </div>
        <span class="text-[11px] font-medium text-slate-600 bg-slate-100 rounded-lg px-3 py-1.5">Rentang dalam <span id="hsc-satuan">-</span></span>
    </div>
    <div class="overflow-x-auto rounded-2xl border border-slate-100">
        <table class="w-full text-left" id="hsc-tabel">
            <thead class="bg-slate-50"><tr id="hsc-kepala"></tr></thead>
            <tbody id="hsc-isi" class="divide-y divide-slate-50"></tbody>
        </table>
    </div>
    <p class="text-[11px] text-slate-400 mt-3">Kosongkan harga golongan yang tidak dipakai. Kolom "Sampai" kosong = tanpa batas atas (hanya boleh di baris terakhir). Rentang tidak boleh tumpang tindih.</p>
    <div class="flex items-center justify-between mt-5">
        <button type="button" onclick="tambahBarisHsc()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-[10px] font-medium uppercase tracking-widest" title="Tambah rentang baru"><i class="fas fa-plus"></i> Tambah Rentang</button>
        <div class="flex gap-3">
            <button type="button" onclick="closeModal('harga-sc')" class="px-5 py-2.5 rounded-xl text-[11px] font-medium uppercase tracking-widest text-slate-400 hover:bg-slate-50">Batal</button>
            <x-button type="button" onclick="simpanHsc()" id="hsc-simpan"><i class="fas fa-check"></i> Simpan Harga</x-button>
        </div>
    </div>
</x-modal>

@push('scripts')
<script>
    let hscProduk = null, hscGolongan = [];
    const hscCls = 'w-full bg-slate-50 border-none rounded-lg py-2 px-3 text-sm font-medium text-slate-600 shadow-inner focus:ring-2 focus:ring-indigo-500/20';

    async function bukaHargaSiapCetak(id) {
        try {
            const r = await fetch(`{{ url('/harga-siap-cetak') }}/${id}`, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!r.ok) throw new Error(r.status);
            const d = await r.json();
            hscProduk = id; hscGolongan = d.golongan;
            document.getElementById('hsc-nama').textContent = d.produk;
            document.getElementById('hsc-satuan').textContent = d.satuan;
            document.getElementById('hsc-kepala').innerHTML = '<th class="px-3 py-3 text-[10px] font-medium uppercase text-slate-400 w-28">Mulai</th><th class="px-3 py-3 text-[10px] font-medium uppercase text-slate-400 w-28">Sampai</th>'
                + d.golongan.map(g => `<th class="px-3 py-3 text-[10px] font-medium uppercase text-slate-400">${g.nama_golongan} (Rp)</th>`).join('') + '<th class="w-10"></th>';
            document.getElementById('hsc-isi').innerHTML = '';
            (d.tier.length ? d.tier : [{ min: 0, max: null, harga: {} }]).forEach(t => tambahBarisHsc(t));
            openModal('harga-sc');
        } catch (e) { Swal.fire('Gagal', 'Data harga tidak dapat dimuat.', 'error'); }
    }

    function tambahBarisHsc(t) {
        t = t && t.min !== undefined ? t : { min: '', max: null, harga: {} };
        const tr = document.createElement('tr');
        tr.innerHTML = `<td class="px-3 py-2"><input type="text" inputmode="decimal" class="${hscCls} hsc-min" value="${t.min}"></td>
            <td class="px-3 py-2"><input type="text" inputmode="decimal" class="${hscCls} hsc-max" value="${t.max ?? ''}" placeholder="tanpa batas"></td>`
            + hscGolongan.map(g => `<td class="px-3 py-2"><input type="text" inputmode="numeric" class="${hscCls} hsc-harga format-ribuan" data-gol="${g.id_golongan}" value="${t.harga[g.id_golongan] ? Number(t.harga[g.id_golongan]).toLocaleString('id-ID') : ''}" placeholder="-"></td>`).join('')
            + `<td class="px-2"><button type="button" onclick="this.closest('tr').remove()" title="Hapus rentang ini" class="w-8 h-8 rounded-lg text-slate-500 hover:text-rose-500 hover:bg-rose-50"><i class="far fa-trash-alt text-xs"></i></button></td>`;
        document.getElementById('hsc-isi').appendChild(tr);
    }

    const angkaHsc = v => { const s = String(v || '').replace(/\./g, '').replace(',', '.'); return s === '' ? null : parseFloat(s); };

    async function simpanHsc() {
        const tier = [...document.querySelectorAll('#hsc-isi tr')].map(tr => {
            const harga = {};
            tr.querySelectorAll('.hsc-harga').forEach(i => { const v = angkaHsc(i.value); if (v) harga[i.dataset.gol] = v; });
            return { min: angkaHsc(tr.querySelector('.hsc-min').value), max: angkaHsc(tr.querySelector('.hsc-max').value), harga };
        }).filter(t => t.min !== null);
        const btn = document.getElementById('hsc-simpan'); btn.disabled = true;
        try {
            const r = await fetch(`{{ url('/harga-siap-cetak') }}/${hscProduk}`, { method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify({ tier }) });
            const d = await r.json();
            if (!r.ok) throw new Error(d.message || (d.errors ? Object.values(d.errors)[0][0] : 'Gagal menyimpan'));
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Harga file siap cetak disimpan', timer: 1800, showConfirmButton: false });
            closeModal('harga-sc');
            setTimeout(() => location.reload(), 900);
        } catch (e) { Swal.fire('Periksa lagi', e.message, 'warning'); }
        finally { btn.disabled = false; }
    }
</script>
@endpush
@endsection
