@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1200px] mx-auto">

    <x-page-header title="Bagi Hasil Desainer">
        <x-slot:subtitle>
            Pengaturan <span class="mx-1 text-slate-500">/</span> Kelas desainer &amp; persen komisi dari biaya desain
        </x-slot:subtitle>
    </x-page-header>

    @if (session('success'))
        <div class="bg-emerald-50 text-emerald-700 p-4 rounded-2xl mb-6 font-medium">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="bg-rose-50 text-rose-600 p-4 rounded-2xl mb-6 font-medium">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="bg-rose-50 text-rose-600 p-4 rounded-2xl mb-6 font-medium">
            <ul class="list-disc list-inside space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="bg-slate-100 text-slate-600 p-4 rounded-2xl mb-6 text-xs font-medium flex items-start gap-2">
        <i class="fas fa-circle-info mt-0.5"></i>
        <span>Cara kerja: tiap operator desain punya <b>kelas</b>, dan tiap kelas punya <b>persen komisi</b> dari biaya desain yang ia terapkan di nota. Persen dicatat (snapshot) saat nota dibuat, jadi mengubah persen di sini hanya berlaku untuk nota berikutnya. Komisi dibayarkan berkala dari Laporan Biaya &amp; Komisi Desain.</span>
    </div>

    {{-- KELAS --}}
    <x-card class="mb-8">
        <h3 class="text-sm font-medium text-slate-800 mb-4">Kelas Desainer</h3>
        <x-table bare>
            <thead>
                <tr>
                    <th class="p-3 text-left">Nama Kelas</th>
                    <th class="p-3 text-left w-32">Komisi (%)</th>
                    <th class="p-3 text-left">Keterangan</th>
                    <th class="p-3 text-center w-24">Anggota</th>
                    <th class="p-3 text-center w-28">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @foreach($kelas as $k)
                <tr>
                    <form method="POST" action="{{ route('desainer.kelas.update', $k->id_kelas) }}" id="form-kelas-{{ $k->id_kelas }}">@csrf @method('PUT')</form>
                    <td class="p-3"><x-form.input type="text" name="nama_kelas" form="form-kelas-{{ $k->id_kelas }}" value="{{ $k->nama_kelas }}" required title="Nama kelas, mis. Junior / Senior" /></td>
                    <td class="p-3"><x-form.input type="number" step="0.01" min="0" max="100" name="persen_komisi" form="form-kelas-{{ $k->id_kelas }}" value="{{ $k->persen_komisi + 0 }}" required title="Persen dari biaya desain yang menjadi bagian desainer (0-100)" /></td>
                    <td class="p-3"><x-form.input type="text" name="keterangan" form="form-kelas-{{ $k->id_kelas }}" value="{{ $k->keterangan }}" title="Catatan bebas tentang kelas ini" /></td>
                    <td class="p-3 text-center text-xs text-slate-500">{{ $k->jumlah_anggota }}</td>
                    <td class="p-3 text-center whitespace-nowrap">
                        <button type="submit" form="form-kelas-{{ $k->id_kelas }}" title="Simpan perubahan kelas ini" class="w-9 h-9 rounded-xl text-slate-400 hover:text-emerald-600 hover:bg-emerald-50"><i class="fas fa-check text-sm"></i></button>
                        <form method="POST" action="{{ route('desainer.kelas.destroy', $k->id_kelas) }}" class="inline" data-konfirmasi="Hapus kelas {{ $k->nama_kelas }}?">
                            @csrf @method('DELETE')
                            <button type="submit" title="Hapus kelas (hanya bila tidak ada operator di dalamnya)" class="w-9 h-9 rounded-xl text-slate-400 hover:text-rose-500 hover:bg-rose-50"><i class="far fa-trash-alt text-sm"></i></button>
                        </form>
                    </td>
                </tr>
                @endforeach
                <tr class="bg-slate-50/60">
                    <form method="POST" action="{{ route('desainer.kelas.store') }}" id="form-kelas-baru">@csrf</form>
                    <td class="p-3"><x-form.input type="text" name="nama_kelas" form="form-kelas-baru" placeholder="Kelas baru, mis. Senior" required /></td>
                    <td class="p-3"><x-form.input type="number" step="0.01" min="0" max="100" name="persen_komisi" form="form-kelas-baru" placeholder="0" required /></td>
                    <td class="p-3"><x-form.input type="text" name="keterangan" form="form-kelas-baru" placeholder="Keterangan (opsional)" /></td>
                    <td class="p-3"></td>
                    <td class="p-3 text-center"><button type="submit" form="form-kelas-baru" title="Tambah kelas baru" class="px-3 py-2 rounded-xl bg-indigo-600 text-white text-[10px] font-medium uppercase tracking-widest hover:bg-indigo-700"><i class="fas fa-plus"></i> Tambah</button></td>
                </tr>
            </tbody>
        </x-table>
    </x-card>

    {{-- ANGGOTA --}}
    <x-card>
        <h3 class="text-sm font-medium text-slate-800 mb-4">Kelas per Operator Desain</h3>
        <x-table bare>
            <thead>
                <tr>
                    <th class="p-3 text-left">Operator Desain</th>
                    <th class="p-3 text-left">Username</th>
                    <th class="p-3 text-left w-64">Kelas</th>
                    <th class="p-3 text-center w-20">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse($anggota as $a)
                <tr>
                    <form method="POST" action="{{ route('desainer.anggota', $a->id_user) }}" id="form-anggota-{{ $a->id_user }}">@csrf</form>
                    <td class="p-3 text-xs font-medium text-slate-700">{{ $a->nama }}</td>
                    <td class="p-3 text-xs text-slate-500">{{ $a->username }}</td>
                    <td class="p-3">
                        <x-form.select name="id_kelas" form="form-anggota-{{ $a->id_user }}" title="Kelas menentukan persen komisi operator ini">
                            <option value="">-- Tanpa kelas (komisi 0%) --</option>
                            @foreach($kelas as $k)
                                <option value="{{ $k->id_kelas }}" {{ (int) $a->id_kelas_desainer === (int) $k->id_kelas ? 'selected' : '' }}>{{ $k->nama_kelas }} ({{ angka($k->persen_komisi, 2) }}%)</option>
                            @endforeach
                        </x-form.select>
                    </td>
                    <td class="p-3 text-center"><button type="submit" form="form-anggota-{{ $a->id_user }}" title="Simpan kelas operator ini" class="w-9 h-9 rounded-xl text-slate-400 hover:text-emerald-600 hover:bg-emerald-50"><i class="fas fa-check text-sm"></i></button></td>
                </tr>
                @empty
                <tr><td colspan="4"><x-empty-state title="Belum ada operator desain" description="Buat user dengan role Operator Desain di menu Pengaturan Users." /></td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>
</div>
@endsection
