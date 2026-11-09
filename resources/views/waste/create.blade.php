@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-10">

    <x-page-header title="Catat Waste" subtitle="Susut bahan baku yang tidak menjadi barang terjual" />

    @if($errors->any())
        <div class="mb-6 p-4 rounded-xl bg-rose-50 text-rose-600 text-sm font-medium">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    @if($terbatas)
        <div class="mb-4 px-4 py-3 rounded-xl bg-slate-100 text-slate-600 text-sm font-medium"><i class="fas fa-industry mr-2"></i>Divisi Anda: {{ $divisiAktif }} - hanya bahan dan cetakan divisi ini yang ditampilkan.</div>
    @else
        <form method="GET" action="{{ route('waste.create') }}" class="mb-4 flex items-center gap-3">
            <label class="text-[10px] font-medium text-slate-400 uppercase tracking-widest">Divisi</label>
            <select name="divisi" class="rounded-xl border-slate-200 text-sm p-2 w-64">
                <option value="">Semua divisi</option>
                @foreach($daftarMesin as $m)<option value="{{ $m->id_mesin }}" {{ (string) request('divisi') === (string) $m->id_mesin ? 'selected' : '' }}>{{ $m->nama_mesin }}</option>@endforeach
            </select>
        </form>
    @endif

    <x-card>
        <form method="POST" action="{{ route('waste.store') }}" class="space-y-5">
            @csrf

            <x-form.group label="Bahan" dalam>
                <x-form.select name="id_bahan" id="id_bahan" required>
                    <option value="">-- Pilih Bahan --</option>
                    @foreach($bahanList as $b)
                        <option value="{{ $b->id_bahan }}" data-satuan="{{ $b->satuan_stok }}" data-stok="{{ (float) $b->stok }}" data-konversi="{{ (float) $b->nilai_konversi }}" data-hitung="{{ $b->satuan_hitung }}" data-id-hitung="{{ $b->id_satuan_hitung }}" data-id-stok="{{ $b->id_satuan_stok }}" {{ old('id_bahan') == $b->id_bahan ? 'selected' : '' }}>{{ $b->nama_bahan }} (stok {{ angka($b->stok, 2) }} {{ $b->satuan_stok }})</option>
                    @endforeach
                </x-form.select>
            </x-form.group>

            <div class="grid grid-cols-2 gap-5">
                <div>
                    {{-- Input mengikuti SATUAN HITUNG bahan: Panjang x Lebar (luas) atau jumlah (pcs/lembar/meter) --}}
                    <div id="waste-blok-qty">
                        <x-form.group label="Qty Waste" dalam>
                            <div class="flex items-center gap-2">
                                <x-form.input type="number" step="0.0001" min="0.0001" name="qty" id="waste-qty" value="{{ old('qty') }}" />
                                <span id="waste-unit" class="shrink-0 px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-xs font-medium uppercase">-</span>
                            </div>
                        </x-form.group>
                    </div>
                    <div id="waste-blok-luas" class="hidden">
                        <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Bagian yang rusak (cm)</label>
                        <div class="grid grid-cols-3 gap-2">
                            <x-form.input type="number" step="0.01" min="0" name="panjang" id="waste-p" value="{{ old('panjang') }}" placeholder="Panjang" />
                            <x-form.input type="number" step="0.01" min="0" name="lebar" id="waste-l" value="{{ old('lebar') }}" placeholder="Lebar" />
                            <x-form.input type="number" step="1" min="1" name="jumlah" id="waste-j" value="{{ old('jumlah', 1) }}" placeholder="Jumlah" />
                        </div>
                        <p class="mt-1 text-[10px] text-slate-400 ml-2">Panjang x Lebar x Jumlah (kali kejadian/lembar)</p>
                    </div>
                    <p id="waste-konversi" class="mt-2 text-[11px] font-medium text-slate-500"></p>
                </div>
                <x-form.group label="Jenis" dalam>
                    <x-form.select name="jenis" required>
                        <option value="Normal" {{ old('jenis') === 'Normal' ? 'selected' : '' }}>Normal (susut wajar proses cetak)</option>
                        <option value="Abnormal" {{ old('jenis') === 'Abnormal' ? 'selected' : '' }}>Abnormal (rusak, salah potong, hilang)</option>
                    </x-form.select>
                </x-form.group>
            </div>

            <x-form.group label="Terkait Item Order (opsional - masuk biaya Biaya Pekerjaan order itu)" dalam>
                <x-form.select name="id_detail" id="id_detail">
                    <option value="">-- Tidak terkait order tertentu --</option>
                    @foreach($items as $i)
                        <option value="{{ $i->id_detail }}" {{ old('id_detail') == $i->id_detail ? 'selected' : '' }}>{{ $i->nomor_nota }} - {{ $i->nama_file }} ({{ $i->nama_produk }})</option>
                    @endforeach
                </x-form.select>
            </x-form.group>

            <x-form.group label="Alasan" dalam>
                <x-form.input type="text" name="alasan" maxlength="255" value="{{ old('alasan') }}" placeholder="Mis. sisa potongan roll, basah kena air" required />
            </x-form.group>

            <p class="text-xs text-slate-400">Normal langsung mengurangi stok dan dijurnal. Abnormal diajukan ke Approval Workflow; stok baru berkurang setelah disetujui.</p>

            <x-button type="submit" variant="primary" class="w-full"><i class="fas fa-save"></i> Simpan</x-button>
        </form>
    </x-card>
</div>
@push('scripts')
<script>
    const wSel = document.getElementById('id_bahan');
    const wQty = document.getElementById('waste-qty'), wInfo = document.getElementById('waste-konversi');
    const wP = document.getElementById('waste-p'), wL = document.getElementById('waste-l'), wJ = document.getElementById('waste-j');
    const fmt = v => v.toLocaleString('id-ID', { maximumFractionDigits: 6 });
    function modeBahan() {
        const d = wSel.selectedOptions[0]?.dataset || {};
        const hitung = (d.hitung || '').toLowerCase().trim();
        const ada = parseFloat(d.konversi) > 0 && d.hitung && d.idHitung && d.idHitung !== d.idStok;
        if (!ada) return 'stok';
        return ['m2', 'm²', 'cm2', 'cm²'].includes(hitung) ? 'luas' : 'hitung';
    }
    function segarkanSatuanWaste() {
        const d = wSel.selectedOptions[0]?.dataset || {}; const mode = modeBahan();
        document.getElementById('waste-blok-luas').classList.toggle('hidden', mode !== 'luas');
        document.getElementById('waste-blok-qty').classList.toggle('hidden', mode === 'luas');
        document.getElementById('waste-unit').textContent = mode === 'hitung' ? d.hitung : (d.satuan || '-');
        hitungKonversi();
    }
    // Tampilkan hasil konversi ke satuan stok, supaya jelas berapa yang benar-benar dikurangkan.
    function hitungKonversi() {
        const d = wSel.selectedOptions[0]?.dataset || {}; const mode = modeBahan();
        const k = parseFloat(d.konversi) || 0, stok = parseFloat(d.stok) || 0;
        let hasil = 0, dasar = '';
        if (mode === 'luas') {
            const luas = ((parseFloat(wP.value) || 0) * (parseFloat(wL.value) || 0) * Math.max(1, parseFloat(wJ.value) || 1)) / ((d.hitung || '').toLowerCase().startsWith('cm') ? 1 : 10000);
            hasil = k > 0 ? luas / k : 0; dasar = luas > 0 ? `${fmt(luas)} ${d.hitung} ` : '';
        } else if (mode === 'hitung') {
            const q = parseFloat(wQty.value) || 0; hasil = k > 0 ? q / k : 0; dasar = q > 0 ? `${fmt(q)} ${d.hitung} ` : '';
        } else { hasil = parseFloat(wQty.value) || 0; }
        if (!d.satuan) { wInfo.textContent = ''; return; }
        wInfo.textContent = hasil > 0 ? `${dasar}= stok berkurang ${fmt(hasil)} ${d.satuan} (stok sekarang ${fmt(stok)} ${d.satuan}).` : `Stok sekarang ${fmt(stok)} ${d.satuan}.`;
        wInfo.className = 'mt-2 text-[11px] font-medium ' + (hasil > stok ? 'text-rose-600' : 'text-slate-500');
    }
    wSel.addEventListener('change', segarkanSatuanWaste);
    [wQty, wP, wL, wJ].forEach(el => el.addEventListener('input', hitungKonversi));
    segarkanSatuanWaste();
</script>
@endpush
@endsection
