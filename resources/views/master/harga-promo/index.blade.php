@extends('layouts.app')

@section('content')
@include('partials.rupiah-format-script')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Harga Promo">
        <x-slot:subtitle>
            Master <span class="mx-1 text-slate-500">/</span> Harga Promo Berlaku Waktu Terbatas
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button onclick="openModal('tambah-promo')">
                <i class="fas fa-fire"></i>
                Promo Baru
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('harga-promo.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[260px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Produk</label>
                <x-form.input type="text" name="search" value="{{ request('search') }}" placeholder="Nama produk..." />
            </div>
            <x-button type="submit">
                <i class="fas fa-filter"></i>
                Filter
            </x-button>
            @if(request()->filled('search'))
                <a href="{{ route('harga-promo.index') }}" class="text-[11px] font-medium text-rose-500 uppercase tracking-widest hover:underline ml-2">Reset</a>
            @endif
        </form>
    </x-card>

    <x-table>
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-6 py-5 text-left">Produk</th>
                <th class="px-4 py-5 text-right">Harga Normal</th>
                <th class="px-4 py-5 text-right">Harga Promo</th>
                <th class="px-4 py-5 text-left">Periode</th>
                <th class="px-4 py-5 text-center">Status</th>
                <th class="px-8 py-5 text-center">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($promo as $i => $p)
            @php
                $today = now()->format('Y-m-d');
                $sedangBerlaku = $p->status_promo === 'aktif' && $p->tgl_mulai <= $today && $p->tgl_selesai >= $today;
            @endphp
            <tr class="hover:bg-slate-50/50 transition-all {{ $sedangBerlaku ? 'bg-rose-50/30' : '' }}">
                <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $promo->firstItem() + $i }}</td>
                <td class="px-6 py-5">
                    <span class="text-[13px] font-medium text-slate-700 uppercase">{{ $p->nama_produk }}</span>
                </td>
                <td class="px-4 py-5 text-right">
                    <span class="text-[12px] text-slate-400 line-through">Lihat Price List</span>
                </td>
                <td class="px-4 py-5 text-right">
                    <span class="text-[14px] font-medium text-rose-600">Rp {{ number_format($p->harga_promo, 0, ',', '.') }}</span>
                </td>
                <td class="px-4 py-5">
                    <span class="text-[12px] font-medium text-slate-600">
                        {{ \Carbon\Carbon::parse($p->tgl_mulai)->translatedFormat('d M Y') }} - {{ \Carbon\Carbon::parse($p->tgl_selesai)->translatedFormat('d M Y') }}
                    </span>
                </td>
                <td class="px-4 py-5 text-center">
                    @if($sedangBerlaku)
                        <x-badge variant="success">Sedang Berlaku</x-badge>
                    @elseif($p->status_promo === 'non-aktif')
                        <x-badge variant="danger">Dinonaktifkan</x-badge>
                    @elseif($p->tgl_selesai < $today)
                        <x-badge variant="info">Berakhir</x-badge>
                    @else
                        <x-badge variant="warning">Terjadwal</x-badge>
                    @endif
                </td>
                <td class="px-8 py-5 text-center">
                    <div class="flex items-center justify-center gap-2">
                        <x-button type="button" size="icon" variant="ghost" onclick="openEditPromo({{ json_encode($p) }})" class="hover:text-amber-500 hover:bg-amber-50" title="Edit">
                            <i class="far fa-edit text-sm"></i>
                        </x-button>

                        <form action="{{ route('harga-promo.toggle', $p->id_promo) }}" method="POST" class="inline">
                            @csrf @method('PATCH')
                            <x-button type="submit" size="icon" variant="ghost" class="hover:text-indigo-600 hover:bg-indigo-50" title="{{ $p->status_promo === 'aktif' ? 'Nonaktifkan' : 'Aktifkan' }}">
                                <i class="fas {{ $p->status_promo === 'aktif' ? 'fa-toggle-on' : 'fa-toggle-off' }} text-sm"></i>
                            </x-button>
                        </form>

                        <form action="{{ route('harga-promo.destroy', $p->id_promo) }}" method="POST" class="inline" id="delete-form-promo-{{ $p->id_promo }}">
                            @csrf @method('DELETE')
                            <x-button type="button" size="icon" variant="ghost" onclick="confirmDeletePromo('{{ $p->id_promo }}', '{{ $p->nama_produk }}')" class="hover:text-rose-500 hover:bg-rose-50" title="Hapus">
                                <i class="far fa-trash-alt text-sm"></i>
                            </x-button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <x-empty-state title="Belum ada harga promo" description="Buat promo baru supaya harga otomatis berubah di POS selama periode berlaku." />
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$promo" />

    {{-- MODAL TAMBAH --}}
    <x-modal id="tambah-promo" title="Buat Harga Promo" maxWidth="md">
        <form action="{{ route('harga-promo.store') }}" method="POST" class="space-y-5">
            @csrf
            <x-form.group label="Produk" name="id_produk" required dalam>
                <x-form.select name="id_produk" required>
                    <option value="">-- Pilih Produk --</option>
                    @foreach($produk as $pr)
                        <option value="{{ $pr->id_produk }}">{{ $pr->nama_produk }}</option>
                    @endforeach
                </x-form.select>
            </x-form.group>

            <x-form.group label="Harga Promo (Rp, berlaku semua golongan)" name="harga_promo" required dalam>
                <x-form.input type="text" inputmode="numeric" name="harga_promo" required class="format-ribuan" />
            </x-form.group>

            <div class="grid grid-cols-2 gap-4">
                <x-form.group label="Tanggal Mulai" name="tgl_mulai" required dalam>
                    <x-form.input type="date" name="tgl_mulai" required />
                </x-form.group>
                <x-form.group label="Tanggal Selesai" name="tgl_selesai" required dalam>
                    <x-form.input type="date" name="tgl_selesai" required />
                </x-form.group>
            </div>

            <div class="pt-4 flex gap-3">
                <x-button type="button" variant="secondary" onclick="closeModal('tambah-promo')" class="flex-1">Batalkan</x-button>
                <x-button type="submit" class="flex-1">Simpan Promo</x-button>
            </div>
        </form>
    </x-modal>

    {{-- MODAL EDIT --}}
    <x-modal id="edit-promo" title="Perbarui Harga Promo" maxWidth="md">
        <form id="formEditPromo" method="POST" class="space-y-5">
            @csrf @method('PUT')
            <x-form.group label="Produk" dalam>
                <x-form.select name="id_produk" id="edit_id_produk" required>
                    @foreach($produk as $pr)
                        <option value="{{ $pr->id_produk }}">{{ $pr->nama_produk }}</option>
                    @endforeach
                </x-form.select>
            </x-form.group>

            <x-form.group label="Harga Promo (Rp)" dalam>
                <x-form.input type="text" inputmode="numeric" name="harga_promo" id="edit_harga_promo" required class="format-ribuan" />
            </x-form.group>

            <div class="grid grid-cols-2 gap-4">
                <x-form.group label="Tanggal Mulai" dalam>
                    <x-form.input type="date" name="tgl_mulai" id="edit_tgl_mulai" required />
                </x-form.group>
                <x-form.group label="Tanggal Selesai" dalam>
                    <x-form.input type="date" name="tgl_selesai" id="edit_tgl_selesai" required />
                </x-form.group>
            </div>

            <div class="pt-4 flex gap-3">
                <x-button type="button" variant="secondary" onclick="closeModal('edit-promo')" class="flex-1">Batalkan</x-button>
                <x-button type="submit" variant="warning" class="flex-1">Simpan Perubahan</x-button>
            </div>
        </form>
    </x-modal>

</div>
@endsection

@push('scripts')
<script>
    function openEditPromo(data) {
        const form = document.getElementById('formEditPromo');
        form.action = `{{ url('harga-promo') }}/${data.id_promo}`;
        document.getElementById('edit_id_produk').value = data.id_produk;
        document.getElementById('edit_harga_promo').value = formatRupiahDariAngka(data.harga_promo);
        document.getElementById('edit_tgl_mulai').value = data.tgl_mulai;
        document.getElementById('edit_tgl_selesai').value = data.tgl_selesai;
        openModal('edit-promo');
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function confirmDeletePromo(id, nama) {
        Swal.fire({
            title: 'Hapus Promo?',
            html: `Menghapus promo untuk <b>${escapeHtml(nama)}</b>? Harga akan kembali normal seketika.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            cancelButtonColor: '#F1F5F1',
            cancelButtonText: '<span style="color: #4A5B53">Batal</span>',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete-form-promo-' + id).submit();
            }
        });
    }
</script>
@endpush
