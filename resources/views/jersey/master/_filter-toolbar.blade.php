{{-- Toolbar cari + filter status - dipakai di tab Desainer/Tipe Produk/
     Kategori/Ukuran/Bahan (semuanya list nama + status aktif/nonaktif).
     Filtering murni di client (JS, lihat terapkanFilterSederhana() di
     master/index.blade.php) - data tab ini kecil & sudah dimuat semua di
     halaman, jadi tidak perlu reload/roundtrip ke server untuk sekadar
     mencari atau menyaring status. --}}
<div class="flex flex-col sm:flex-row gap-3 mb-4">
    <div class="flex-1 relative">
        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
        <input type="text" id="{{ $searchId }}" placeholder="{{ $placeholder ?? 'Cari nama...' }}"
            class="w-full bg-white border border-slate-200 rounded-xl py-2.5 pl-10 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20">
    </div>
    @if(!empty($statusId))
    <select id="{{ $statusId }}" class="sm:w-44 bg-white border border-slate-200 rounded-xl py-2.5 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20">
        <option value="semua">Semua Status</option>
        <option value="aktif">Aktif</option>
        <option value="nonaktif">Nonaktif</option>
    </select>
    @endif
</div>
