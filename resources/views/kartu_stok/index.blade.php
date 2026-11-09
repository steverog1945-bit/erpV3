@extends('layouts.app')

@section('content')
<div class="pwa-ops-page w-full px-4 sm:px-6 lg:px-8 py-6 max-w-[1600px] mx-auto">

    <x-page-header title="Kartu Stok">
        <x-slot:subtitle>
            Bahan Baku &amp; Logistik <span class="mx-1 text-slate-500">/</span> Riwayat Pergerakan Stok per Bahan
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button onclick="openModal('penyesuaian-stok')">
                <i class="fas fa-sliders-h"></i>
                Penyesuaian Stok
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER RAMPING --}}
    @php $adaFilter = request()->anyFilled(['search', 'kategori', 'status_stok']) || request('urut', 'nama') !== 'nama'; @endphp
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 mb-4">
        <form method="GET" action="{{ route('kartu-stok.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[240px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5 ml-2">Cari Bahan</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ $search }}"
                           class="w-full bg-slate-50 border-none rounded-xl py-2.5 pl-10 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 shadow-inner"
                           placeholder="Ketik SKU atau Nama Bahan...">
                </div>
            </div>
            <div class="w-full sm:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5 ml-2">Kategori</label>
                <x-form.select name="kategori">
                    <option value="">Semua Kategori</option>
                    @foreach($kategoriList as $kt)<option value="{{ $kt->id_kategori }}" {{ (string) request('kategori') === (string) $kt->id_kategori ? 'selected' : '' }}>{{ $kt->nama_kategori }}</option>@endforeach
                </x-form.select>
            </div>
            <div class="w-full sm:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5 ml-2">Status Stok</label>
                <x-form.select name="status_stok">
                    <option value="">Semua</option>
                    <option value="habis" {{ $statusStok === 'habis' ? 'selected' : '' }}>Habis (0 / minus)</option>
                    <option value="menipis" {{ $statusStok === 'menipis' ? 'selected' : '' }}>Menipis (&le; stok min)</option>
                    <option value="aman" {{ $statusStok === 'aman' ? 'selected' : '' }}>Aman</option>
                </x-form.select>
            </div>
            <div class="w-full sm:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5 ml-2">Urutkan</label>
                <x-form.select name="urut">
                    <option value="nama" {{ $urut === 'nama' ? 'selected' : '' }}>Nama (A-Z)</option>
                    <option value="kode" {{ $urut === 'kode' ? 'selected' : '' }}>Kode / SKU</option>
                    <option value="stok_asc" {{ $urut === 'stok_asc' ? 'selected' : '' }}>Stok terendah</option>
                    <option value="stok_desc" {{ $urut === 'stok_desc' ? 'selected' : '' }}>Stok tertinggi</option>
                    <option value="mutasi" {{ $urut === 'mutasi' ? 'selected' : '' }}>Mutasi terbaru</option>
                </x-form.select>
            </div>
            <div class="flex items-center gap-2">
                <x-button type="submit" title="Terapkan filter"><i class="fas fa-filter"></i> Filter</x-button>
                @if($adaFilter)
                    <a href="{{ route('kartu-stok.index') }}" class="h-[38px] px-3.5 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all shrink-0" title="Reset Filter">
                        <i class="fas fa-undo-alt text-sm"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- RINGKASAN HASIL FILTER --}}
    <div class="grid grid-cols-3 gap-3 mb-4">
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm px-4 py-3"><p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Bahan Tampil</p><p class="text-xl font-medium text-slate-800 mt-0.5">{{ $ringkas['total'] }}</p></div>
        <a href="{{ route('kartu-stok.index', array_merge(request()->except('status_stok'), ['status_stok' => 'habis'])) }}" title="Saring bahan yang stoknya habis" class="bg-white rounded-2xl border border-slate-100 shadow-sm px-4 py-3 hover:shadow-md transition-all"><p class="text-[10px] font-medium text-rose-500 uppercase tracking-widest">Habis</p><p class="text-xl font-medium text-rose-600 mt-0.5">{{ $ringkas['habis'] }}</p></a>
        <a href="{{ route('kartu-stok.index', array_merge(request()->except('status_stok'), ['status_stok' => 'menipis'])) }}" title="Saring bahan yang stoknya menipis" class="bg-white rounded-2xl border border-slate-100 shadow-sm px-4 py-3 hover:shadow-md transition-all"><p class="text-[10px] font-medium text-amber-500 uppercase tracking-widest">Menipis</p><p class="text-xl font-medium text-amber-600 mt-0.5">{{ $ringkas['menipis'] }}</p></a>
    </div>

    <x-table wrapperClass="pwa-ops-shell overflow-hidden" class="pwa-ops-table pwa-ops-table--stock">
        <thead>
            <tr>
                <th class="px-4 py-3 text-center w-10">#</th>
                <th class="px-6 py-3 text-left">Kode</th>
                <th class="px-4 py-3 text-left">Nama Bahan</th>
                <th class="px-4 py-3 text-left">Kategori / Mesin</th>
                <th class="px-4 py-3 text-right">Stok Saat Ini</th>
                <th class="px-4 py-3 text-left">Satuan</th>
                <th class="px-4 py-3 text-center">Status Stok</th>
                <th class="px-4 py-3 text-left">Mutasi Terakhir</th>
                <th class="px-6 py-3 text-center">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($bahan as $i => $b)
            <tr class="pwa-ops-row group hover:bg-slate-50/50 transition-all">
                <td class="pwa-ops-cell pwa-ops-sequence px-4 py-3 text-center text-[11px] text-slate-400" data-label="Urutan">{{ $i + 1 }}</td>
                <td class="pwa-ops-cell pwa-ops-code px-6 py-3" data-label="Kode Bahan">
                    <span class="text-[12px] font-medium text-slate-700">{{ $b->kode_bahan }}</span>
                </td>
                <td class="pwa-ops-cell pwa-ops-name px-4 py-3" data-label="Nama Bahan">
                    <span class="text-[13px] font-medium text-slate-700 uppercase group-hover:text-indigo-600 transition-colors">{{ $b->nama_bahan }}</span>
                </td>
                <td class="pwa-ops-cell pwa-ops-category px-4 py-3" data-label="Kategori dan Mesin">
                    <span class="block text-[12px] font-medium text-slate-600">{{ $b->nama_kategori ?? '-' }}</span>
                    <span class="block text-[10px] font-medium text-slate-400 uppercase">{{ $b->nama_mesin ?? '-' }}</span>
                </td>
                <td class="pwa-ops-cell pwa-ops-stock px-4 py-3 text-right" data-label="Stok">
                    <span class="text-[13px] font-medium text-slate-800">{{ angka($b->stok, 2) }}</span>
                </td>
                <td class="pwa-ops-cell pwa-ops-unit px-4 py-3" data-label="Satuan">
                    <span class="inline-flex px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 text-[11px] font-medium uppercase tracking-wide">{{ $b->nama_satuan_stok ?? $b->satuan_stok ?? '-' }}</span>
                </td>
                <td class="pwa-ops-cell pwa-ops-status px-4 py-3 text-center" data-label="Status Stok">
                    <x-badge :variant="['Habis' => 'danger', 'Menipis' => 'warning', 'Aman' => 'success'][$b->status_stok] ?? 'info'">{{ $b->status_stok }}</x-badge>
                </td>
                <td class="pwa-ops-cell pwa-ops-movement px-4 py-3 text-left" data-label="Mutasi Terakhir">
                    @if($b->mutasi_terakhir)
                        <span class="text-[12px] font-medium text-slate-600">{{ \Carbon\Carbon::parse($b->mutasi_terakhir)->translatedFormat('d M Y') }}</span>
                        <span class="block text-[10px] text-slate-400">{{ $b->jumlah_mutasi }} mutasi</span>
                    @else
                        <span class="text-[11px] text-slate-500 italic">Belum ada</span>
                    @endif
                </td>
                <td class="pwa-ops-cell pwa-ops-actions px-6 py-3 text-center" data-label="Aksi">
                    <div class="flex items-center justify-center gap-2">
                        <x-button href="{{ url('/kartu-stok/'.$b->id_bahan) }}" size="icon" variant="ghost" class="border border-slate-100 hover:text-indigo-600" title="Lihat Kartu Stok">
                            <i class="fas fa-book text-xs"></i>
                        </x-button>
                        <x-button type="button" size="icon" variant="ghost" onclick="openPenyesuaian({{ $b->id_bahan }}, {{ json_encode($b->nama_bahan) }}, {{ angka($b->stok, 4) }})" class="border border-slate-100 hover:text-amber-500" title="Penyesuaian Stok">
                            <i class="fas fa-sliders-h text-xs"></i>
                        </x-button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9">
                    <x-empty-state :title="$adaFilter ? 'Tidak ada bahan yang cocok dengan filter' : 'Belum ada data bahan baku'" :description="$adaFilter ? 'Ubah atau reset filter untuk melihat bahan lain.' : null" />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    {{-- MODAL PENYESUAIAN STOK --}}
    <x-modal id="penyesuaian-stok" title="Penyesuaian Stok" maxWidth="md">
        <form id="formPenyesuaian" action="{{ route('kartu-stok.adjustment') }}" method="POST" class="space-y-5">
            @csrf
            {{-- Optimistic locking: stok yang terlihat saat formulir dibuka; ditolak server bila sudah berubah. --}}
            <input type="hidden" name="stok_dibuka" id="adj_stok_dibuka" value="">
            <x-form.group label="Bahan" name="id_bahan" required dalam>
                <x-form.select name="id_bahan" id="adj_id_bahan" required>
                    <option value="">-- Pilih Bahan --</option>
                    @foreach($bahan as $b)
                        <option value="{{ $b->id_bahan }}" data-stok-raw="{{ (float) $b->stok }}" data-stok="{{ angka($b->stok, 4) }}" data-satuan="{{ $b->nama_satuan_stok ?? $b->satuan_stok }}" data-nama="{{ $b->nama_bahan }}">{{ $b->nama_bahan }} ({{ $b->kode_bahan }})</option>
                    @endforeach
                </x-form.select>
            </x-form.group>

            <p class="text-[11px] text-slate-400 -mt-3 ml-1">Stok saat ini: <span id="adj_stok_display" class="font-medium text-slate-600">-</span> <span id="adj_satuan_display" class="inline-flex px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-[10px] font-medium uppercase"></span></p>

            <x-form.group label="Jenis Penyesuaian" name="tipe" required dalam>
                <x-form.select name="tipe" id="adj_tipe" onchange="toggleAdjTipe()" required>
                    <option value="OUT">Barang Keluar (rusak, sample, hilang)</option>
                    <option value="ADJUSTMENT">Revisi Stok / Hasil Opname Fisik</option>
                </x-form.select>
            </x-form.group>

            <div id="adj_wrapper_qty">
                <x-form.group label="Qty Keluar (dalam satuan stok)" name="qty">
                    <div class="flex items-center gap-2"><x-form.input type="text" inputmode="decimal" name="qty" id="adj_qty" class="input-desimal" autocomplete="off" placeholder="0" /><span class="adj-unit shrink-0 px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-xs font-medium uppercase">-</span></div>
                </x-form.group>
            </div>

            <div id="adj_wrapper_stok_baru" class="hidden">
                <x-form.group label="Stok Sebenarnya (Hasil Hitung Fisik)" name="stok_baru" dalam>
                    <div class="flex items-center gap-2"><x-form.input type="text" inputmode="decimal" name="stok_baru" id="adj_stok_baru" class="input-desimal" autocomplete="off" placeholder="0" /><span class="adj-unit shrink-0 px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-xs font-medium uppercase">-</span></div>
                </x-form.group>
            </div>

            <x-form.group label="Keterangan / Alasan" name="keterangan" required dalam>
                <textarea name="keterangan" rows="2" required placeholder="Contoh: Rusak saat produksi, hasil stock opname bulanan, dsb."
                    class="w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 shadow-inner"></textarea>
            </x-form.group>

            <div class="pt-4 flex gap-3">
                <x-button type="button" variant="secondary" onclick="closeModal('penyesuaian-stok')" class="flex-1">Batalkan</x-button>
                @php $langsung = optional(auth()->user()->role)->nama_role === 'Administrator'; @endphp
                <x-button type="submit" variant="warning" class="flex-1" title="{{ $langsung ? 'Stok bahan langsung diperbarui dan tercatat di kartu stok' : 'Diajukan ke Administrator; stok berubah setelah disetujui' }}">{{ $langsung ? 'Terapkan Penyesuaian' : 'Ajukan Penyesuaian' }}</x-button>
            </div>
        </form>
    </x-modal>

</div>

@push('scripts')
<script>
    function toggleAdjTipe() {
        const tipe = document.getElementById('adj_tipe').value;
        document.getElementById('adj_wrapper_qty').classList.toggle('hidden', tipe !== 'OUT');
        document.getElementById('adj_wrapper_stok_baru').classList.toggle('hidden', tipe !== 'ADJUSTMENT');
    }

    document.getElementById('adj_id_bahan').addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const stok = opt.getAttribute('data-stok');
        document.getElementById('adj_stok_display').textContent = stok ? parseFloat(stok).toLocaleString('id-ID', {minimumFractionDigits: 2}) : '-';
        tampilSatuanAdj(opt.getAttribute('data-satuan') || '');
    });

    function tampilSatuanAdj(satuan) {
        document.getElementById('adj_satuan_display').textContent = satuan;
        document.querySelectorAll('.adj-unit').forEach(el => el.textContent = satuan || '-');
    }

    // Kotak angka: hanya digit dan satu pemisah desimal (titik/koma); huruf, tanda, dan "e" langsung dibuang.
    document.querySelectorAll('input.input-desimal').forEach(el => {
        el.addEventListener('input', () => {
            let v = el.value.replace(/[^0-9.,]/g, '');
            const i = v.search(/[.,]/);
            if (i >= 0) v = v.slice(0, i + 1) + v.slice(i + 1).replace(/[.,]/g, '');
            if (v !== el.value) el.value = v;
        });
    });
    const angkaAdj = (id) => parseFloat((document.getElementById(id).value || '').replace(',', '.'));

    // Simpan stok yang terlihat saat pilihan bahan berubah / formulir dibuka (dasar optimistic locking).
    function catatStokDibuka() {
        const opt = document.getElementById('adj_id_bahan').selectedOptions[0];
        document.getElementById('adj_stok_dibuka').value = opt ? (opt.getAttribute('data-stok-raw') || '') : '';
    }
    document.getElementById('adj_id_bahan').addEventListener('change', catatStokDibuka);

    // Konfirmasi sebelum diajukan: tampilkan angka + SATUAN-nya supaya tidak salah input (mis. 5 dus vs 5 pcs).
    document.getElementById('formPenyesuaian').addEventListener('submit', function (e) {
        if (this.__ok) return;
        e.preventDefault();
        const sel = document.getElementById('adj_id_bahan');
        const opt = sel.options[sel.selectedIndex];
        const satuan = opt.getAttribute('data-satuan') || '';
        const tipe = document.getElementById('adj_tipe').value;
        const nilai = angkaAdj(tipe === 'OUT' ? 'adj_qty' : 'adj_stok_baru');
        if (!(nilai >= 0) || (tipe === 'OUT' && !(nilai > 0))) { Swal.fire('Angka tidak valid', 'Isi angka yang benar (hanya digit dan satu tanda desimal).', 'warning'); return; }
        // titik/koma desimal dikirim sebagai angka baku
        document.getElementById('adj_qty').value = tipe === 'OUT' ? String(nilai) : document.getElementById('adj_qty').value;
        document.getElementById('adj_stok_baru').value = tipe !== 'OUT' ? String(nilai) : document.getElementById('adj_stok_baru').value;
        const teks = tipe === 'OUT'
            ? `Keluarkan <b>${nilai.toLocaleString('id-ID')} ${escHtml(satuan)}</b> dari <b>${escHtml(opt.dataset.nama)}</b>?`
            : `Set stok <b>${escHtml(opt.dataset.nama)}</b> menjadi <b>${nilai.toLocaleString('id-ID')} ${escHtml(satuan)}</b>?`;
        Swal.fire({ title: 'Cek satuan sebelum lanjut', html: teks + `<br><small class="text-slate-400">Satuan stok bahan ini: ${escHtml(satuan)}. Pastikan angka BUKAN dalam satuan lain.</small>`, icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, benar', cancelButtonText: 'Periksa lagi' })
            .then(r => { if (r.isConfirmed) { this.__ok = true; this.requestSubmit(); } });
    });

    function openPenyesuaian(idBahan, namaBahan, stok) {
        document.getElementById('adj_id_bahan').value = idBahan;
        document.getElementById('adj_stok_display').textContent = parseFloat(stok).toLocaleString('id-ID', {minimumFractionDigits: 2});
        tampilSatuanAdj(document.getElementById('adj_id_bahan').selectedOptions[0]?.getAttribute('data-satuan') || '');
        document.getElementById('adj_tipe').value = 'OUT';
        toggleAdjTipe();
        catatStokDibuka();
        openModal('penyesuaian-stok');
    }
</script>
@endpush
@endsection
