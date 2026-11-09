@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Persetujuan Edit Nota">
        <x-slot:subtitle>
            <i class="fas fa-user-shield text-amber-400"></i>
            Khusus Administrator <span class="text-slate-500">/</span> Anti-penyalahgunaan edit nota
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button href="{{ route('penjualan.riwayat') }}" variant="outline">
                <i class="fas fa-arrow-left"></i> Kembali ke Riwayat Nota
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-8">
        <form method="GET" action="{{ route('penjualan.edit-approval') }}" class="flex flex-wrap items-end gap-4">
            <div class="w-full md:w-64">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Status</label>
                <x-form.select name="status" onchange="this.form.submit()">
                    <option value="Menunggu" {{ request('status', 'Menunggu') == 'Menunggu' ? 'selected' : '' }}>Menunggu</option>
                    <option value="Disetujui" {{ request('status') == 'Disetujui' ? 'selected' : '' }}>Disetujui</option>
                    <option value="Ditolak" {{ request('status') == 'Ditolak' ? 'selected' : '' }}>Ditolak</option>
                    <option value="Selesai" {{ request('status') == 'Selesai' ? 'selected' : '' }}>Selesai (sudah dikoreksi)</option>
                </x-form.select>
            </div>
        </form>
    </x-card>

    <x-table>
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-8 py-5 text-left">Nota</th>
                <th class="px-6 py-5 text-left">Diajukan Oleh</th>
                <th class="px-6 py-5 text-left">Alasan</th>
                <th class="px-6 py-5 text-left">Tanggal Ajuan</th>
                <th class="px-6 py-5 text-center">Status</th>
                <th class="px-8 py-5 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($requests as $i => $r)
            <tr class="hover:bg-slate-50/30 transition-all">
                <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $requests->firstItem() + $i }}</td>
                <td class="px-8 py-5">
                    <span class="text-[13px] font-medium text-slate-700">{{ $r->nomor_nota }}</span>
                    <div class="text-[11px] text-slate-400">{{ $r->nama_konsumen }} &middot; Rp {{ number_format($r->total_bayar, 0, ',', '.') }}</div>
                </td>
                <td class="px-6 py-5">
                    <span class="text-sm font-medium text-slate-600">{{ $r->nama_requester }}</span>
                </td>
                <td class="px-6 py-5 max-w-xs">
                    <span class="text-xs text-slate-500">{{ $r->alasan }}</span>
                    @if($r->catatan_approver)
                        <div class="text-[11px] text-slate-400 italic mt-1">Catatan admin: {{ $r->catatan_approver }}</div>
                    @endif
                </td>
                <td class="px-6 py-5">
                    <span class="text-xs text-slate-500">{{ \Carbon\Carbon::parse($r->created_at)->translatedFormat('d M Y H:i') }}</span>
                </td>
                <td class="px-6 py-5 text-center">
                    @php
                        $variantMap = ['Menunggu' => 'warning', 'Disetujui' => 'success', 'Ditolak' => 'danger', 'Selesai' => 'info'];
                    @endphp
                    <x-badge :status="$r->status" :variant="$variantMap[$r->status] ?? 'info'" />
                </td>
                <td class="px-8 py-5 text-right">
                    @if($r->status === 'Menunggu')
                        <div class="flex items-center justify-end gap-2">
                            <x-button size="sm" variant="danger" onclick="openTolak('{{ $r->id_request }}')">
                                <i class="fas fa-times"></i> Tolak
                            </x-button>
                            <x-button size="sm" variant="success" onclick="confirmSetujui('{{ $r->id_request }}', {{ json_encode($r->nomor_nota) }})">
                                <i class="fas fa-check"></i> Setujui
                            </x-button>
                        </div>
                    @else
                        <span class="text-[11px] text-slate-400 italic">
                            {{ $r->nama_approver ? 'oleh ' . $r->nama_approver : '-' }}
                        </span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <x-empty-state title="Tidak ada permintaan edit dengan status ini" />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$requests" />

    {{-- MODAL TOLAK --}}
    <x-modal id="tolak-edit" title="Tolak Permintaan Edit" maxWidth="sm">
        <form id="form-tolak" method="POST">
            @csrf @method('PATCH')
            <x-form.group label="Alasan Penolakan" dalam>
                <textarea name="catatan" required rows="4" placeholder="Jelaskan kenapa permintaan ini ditolak" class="w-full rounded-xl border-slate-200 text-sm p-3"></textarea>
            </x-form.group>
            <div class="pt-4 flex gap-3">
                <x-button type="button" variant="secondary" onclick="closeModal('tolak-edit')" class="flex-1">Batal</x-button>
                <x-button type="submit" variant="danger" class="flex-1">Tolak Permintaan</x-button>
            </div>
        </form>
    </x-modal>

</div>

@push('scripts')
<script>
    function openTolak(idRequest) {
        document.getElementById('form-tolak').action = `{{ url('/penjualan/edit-nota') }}/${idRequest}/reject`;
        openModal('tolak-edit');
    }

    function confirmSetujui(idRequest, nomorNota) {
        Swal.fire({
            title: 'Setujui edit nota ini?',
            html: `Nota <b>${escHtml(nomorNota)}</b> akan bisa dikoreksi kasir setelah ini disetujui (nota lama dibatalkan otomatis saat kasir memproses koreksinya).`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Ya, Setujui',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `{{ url('/penjualan/edit-nota') }}/${idRequest}/approve`;
                form.innerHTML = `@csrf @method('PATCH')`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
</script>
@endpush
@endsection
