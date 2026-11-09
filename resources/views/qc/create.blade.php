@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-10">

    <x-page-header title="QC Baru" subtitle="Periksa hasil cetak satu item order - isi jumlah reject bila ada" />

    @if($errors->any())
        <div class="mb-6 p-4 rounded-xl bg-rose-50 text-rose-600 text-sm font-medium">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <x-card>
        <form method="POST" action="{{ route('qc.store') }}" class="space-y-6">
            @csrf

            <x-form.group label="Item Order (sudah berstatus Cetak/Selesai)" dalam>
                <x-form.select name="id_detail" id="id_detail" required>
                    <option value="">-- Pilih Item --</option>
                    @foreach($items as $i)
                        <option value="{{ $i->id_detail }}" data-qty="{{ $i->qty_cetak ?? $i->qty }}" {{ old('id_detail') == $i->id_detail ? 'selected' : '' }}>
                            {{ $i->nomor_nota }} - {{ $i->nama_file }} ({{ $i->qty_cetak ?? $i->qty }} pcs)
                        </option>
                    @endforeach
                </x-form.select>
            </x-form.group>

            <div class="grid grid-cols-2 gap-5">
                <x-form.group label="Qty Diperiksa" dalam>
                    <x-form.input type="number" min="1" name="qty_diperiksa" id="qty_diperiksa" value="{{ old('qty_diperiksa') }}" required />
                </x-form.group>
                <x-form.group label="Qty Reject" dalam>
                    <x-form.input type="number" min="0" name="qty_reject" id="qty_reject" value="{{ old('qty_reject', 0) }}" required />
                </x-form.group>
            </div>
            <p class="text-xs text-slate-400 -mt-3">Lolos = diperiksa - reject: <b id="qty-lolos">0</b>. Reject &gt; 0 otomatis membuka Rework.</p>

            <div>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-3 ml-1">Poin bermasalah (centang bila ada masalah)</label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach($checklist as $key => $label)
                    <label class="flex items-center gap-2 p-3 rounded-xl bg-slate-50 text-sm font-medium text-slate-600">
                        <input type="checkbox" name="checklist[{{ $key }}]" value="1" {{ old("checklist.$key") ? 'checked' : '' }}> {{ $label }}
                    </label>
                    @endforeach
                </div>
            </div>

            <x-form.group label="Catatan (opsional)" dalam>
                <textarea name="catatan" rows="3" class="w-full rounded-xl border-slate-200 text-sm p-3">{{ old('catatan') }}</textarea>
            </x-form.group>

            <x-button type="submit" variant="primary" class="w-full"><i class="fas fa-save"></i> Simpan QC</x-button>
        </form>
    </x-card>
</div>

@push('scripts')
<script>
    const sel = document.getElementById('id_detail'), dik = document.getElementById('qty_diperiksa'), rej = document.getElementById('qty_reject');
    function hitung() { document.getElementById('qty-lolos').textContent = Math.max(0, (parseInt(dik.value) || 0) - (parseInt(rej.value) || 0)); }
    sel.addEventListener('change', function () {
        const q = this.selectedOptions[0]?.dataset.qty;
        // Selalu ikuti qty item yang baru dipilih (sebelumnya hanya terisi untuk pilihan pertama).
        dik.value = q ? parseFloat(q) : '';
        rej.value = 0;
        hitung();
    });
    dik.addEventListener('input', hitung); rej.addEventListener('input', hitung); hitung();
</script>
@endpush
@endsection
