{{-- Baris tabel entitas sederhana (desainer/tipe produk/kategori/bahan) -
     HARUS hasilkan markup yang sama persis dengan baseRowHtml() di JS pada
     master/index.blade.php, karena baris hasil AJAX add memakai fungsi JS
     itu sedangkan render awal halaman memakai partial ini. Baris nonaktif
     pakai opacity (bukan badge status terpisah) - konsisten dengan pola
     Master Bahan Baku yang sudah ada di aplikasi. --}}
<tr data-id="{{ $row->id }}" class="{{ $row->is_active ? '' : 'opacity-50' }}">
    <td class="p-2.5 font-medium">
        {{ $row->name }}
        @if($entity === 'product-types' && !$row->has_sleeve_collar)
            <span class="ml-2 text-[9px] font-medium uppercase tracking-wide text-slate-400 bg-slate-100 rounded px-1.5 py-0.5">Tanpa Lengan/Kerah</span>
        @endif
        @if(in_array($entity, ['sleeve-types', 'collar-types']) && $row->price_surcharge > 0)
            <span class="ml-2 text-[9px] font-medium uppercase tracking-wide text-emerald-600 bg-emerald-50 rounded px-1.5 py-0.5">+Rp {{ number_format($row->price_surcharge, 0, ',', '.') }}</span>
        @endif
    </td>
    <td class="p-2.5 text-center">
        <div class="flex items-center justify-center gap-1">
            <button type="button" onclick="bukaEditSimple('{{ $entity }}', {{ $row->id }}, {{ Illuminate\Support\Js::from($row->name) }}, {{ Illuminate\Support\Js::from($row->has_sleeve_collar ?? true) }}, {{ Illuminate\Support\Js::from((float) ($row->price_surcharge ?? 0)) }})" class="w-8 h-8 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50" title="Ubah"><i class="far fa-edit text-xs"></i></button>
            <button type="button" onclick="toggleEntitas('{{ $entity }}', {{ $row->id }})" class="w-8 h-8 rounded-lg text-slate-400 {{ $row->is_active ? 'hover:text-slate-600 hover:bg-slate-100' : 'hover:text-emerald-500 hover:bg-emerald-50' }}" title="{{ $row->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"><i class="far {{ $row->is_active ? 'fa-eye-slash' : 'fa-eye' }} text-xs"></i></button>
        </div>
    </td>
</tr>
