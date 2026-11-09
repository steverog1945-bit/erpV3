@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1400px] mx-auto">

    @php $editable = $stockOpname->status === 'Draft'; @endphp

    <x-page-header title="Stok Opname {{ $stockOpname->nomor }}">
        <x-slot:subtitle>
            <i class="fas fa-clipboard-list text-amber-400"></i>
            {{ $stockOpname->tanggal->translatedFormat('d M Y') }} - {{ $stockOpname->gudang?->nama_gudang ?? 'Semua Gudang' }}
        </x-slot:subtitle>
        <x-slot:actions>
            @php
                $variantMap = ['Draft' => 'info', 'Diajukan' => 'warning', 'Disetujui' => 'success', 'Ditolak' => 'danger'];
            @endphp
            <x-badge :status="$stockOpname->status" :variant="$variantMap[$stockOpname->status] ?? 'info'" />
        </x-slot:actions>
    </x-page-header>

    <form id="form-opname" method="POST" action="{{ route('stock-opname.update-details', $stockOpname->id) }}">
        @csrf @method('PATCH')

        <x-table>
            <thead>
                <tr>
                    <th class="px-8 py-5 text-left">Bahan</th>
                    <th class="px-6 py-5 text-right">Stok Sistem</th>
                    <th class="px-6 py-5 text-right w-56">Stok Fisik (+ satuan)</th>
                    <th class="px-6 py-5 text-right">Selisih</th>
                    <th class="px-8 py-5 text-left w-72">Alasan (wajib kalau selisih)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse($details as $d)
                <tr class="hover:bg-slate-50/30 transition-all" data-row>
                    <td class="px-8 py-5">
                        <span class="text-[13px] font-medium text-slate-700">{{ $d->bahan->nama_bahan ?? '-' }}</span>
                        <div class="text-[11px] text-slate-400">{{ $d->bahan->satuan_stok ?? '' }}</div>
                    </td>
                    <td class="px-6 py-5 text-right">
                        <span class="text-sm font-medium text-slate-600" data-sistem="{{ angka($d->stok_sistem, 4) }}">{{ angka($d->stok_sistem, 2) }}</span> <span class="text-[10px] font-medium text-indigo-600 uppercase">{{ $d->bahan->satuan_stok ?? '' }}</span>
                    </td>
                    <td class="px-6 py-5">
                        <div class="flex items-center gap-2">
                            <input type="number" step="0.01" name="detail[{{ $d->id }}][stok_fisik]" data-fisik
                                value="{{ old('detail.' . $d->id . '.stok_fisik', $d->stok_fisik) }}"
                                class="w-full rounded-xl border-slate-200 text-sm p-2 text-right" {{ $editable ? '' : 'disabled' }}>
                            <span class="shrink-0 px-2 py-1 rounded-lg bg-slate-100 text-slate-600 text-[10px] font-medium uppercase">{{ $d->bahan->satuan_stok ?? '-' }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-5 text-right">
                        <span class="text-sm font-medium" data-selisih>-</span>
                    </td>
                    <td class="px-8 py-5">
                        <input type="text" name="detail[{{ $d->id }}][alasan]"
                            value="{{ old('detail.' . $d->id . '.alasan', $d->alasan) }}"
                            placeholder="Mis. rusak, hilang, salah hitung" maxlength="255"
                            class="w-full rounded-xl border-slate-200 text-xs p-2" {{ $editable ? '' : 'disabled' }}>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <x-empty-state title="Tidak ada bahan aktif" />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </x-table>

        @if($editable)
        <div class="mt-8 flex flex-wrap gap-3 justify-end">
            <x-button type="submit" variant="secondary">
                <i class="fas fa-save"></i> Simpan Hasil Hitung
            </x-button>
            <x-button type="button" variant="primary" onclick="ajukanOpname()">
                <i class="fas fa-paper-plane"></i> Ajukan untuk Persetujuan
            </x-button>
        </div>
        @endif
    </form>

</div>

@push('scripts')
<script>
    function hitungSelisih() {
        document.querySelectorAll('[data-row]').forEach(function (row) {
            const sistem = parseFloat(row.querySelector('[data-sistem]').dataset.sistem) || 0;
            const fisikInput = row.querySelector('[data-fisik]');
            const fisik = fisikInput.value === '' ? null : parseFloat(fisikInput.value);
            const selisihEl = row.querySelector('[data-selisih]');

            if (fisik === null || isNaN(fisik)) {
                selisihEl.textContent = '-';
                selisihEl.className = 'text-sm font-medium text-slate-500';
                return;
            }

            const selisih = fisik - sistem;
            selisihEl.textContent = (selisih > 0 ? '+' : '') + angkaId(selisih, 2);
            selisihEl.className = 'text-sm font-medium ' + (selisih === 0 ? 'text-slate-400' : (selisih > 0 ? 'text-emerald-600' : 'text-rose-500'));
        });
    }

    document.querySelectorAll('[data-fisik]').forEach(el => el.addEventListener('input', hitungSelisih));
    hitungSelisih();

    function ajukanOpname() {
        Swal.fire({
            title: 'Ajukan hasil hitung untuk persetujuan?',
            text: 'Hasil hitung fisik yang belum disimpan akan disimpan otomatis. Stok baru berubah setelah disetujui Administrator.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#006C7B',
            confirmButtonText: 'Ya, Simpan & Ajukan',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (!result.isConfirmed) return;

            fetch(document.getElementById('form-opname').action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: new FormData(document.getElementById('form-opname'))
            }).then(async (res) => {
                // Hasil hitung harus benar-benar tersimpan dulu. Sebelumnya pengajuan tetap dikirim walau
                // penyimpanan gagal (mis. alasan selisih belum diisi), sehingga yang diajukan adalah angka lama.
                if (!res.ok) {
                    let pesan = 'Hasil hitung belum tersimpan. Periksa isian lalu coba lagi.';
                    try { const j = await res.json(); if (j.message) pesan = j.message; } catch (e) {}
                    Swal.fire('Gagal menyimpan', pesan, 'error');
                    return;
                }
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = "{{ route('stock-opname.ajukan', $stockOpname->id) }}";
                form.innerHTML = `@csrf @method('PATCH')`;
                document.body.appendChild(form);
                form.submit();
            }).catch(() => Swal.fire('Koneksi terputus', 'Hasil hitung belum tersimpan. Coba lagi.', 'error'));
        });
    }
</script>
@endpush
@endsection
