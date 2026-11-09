{{-- Sinkron dengan rowHargaHtml() di JS master/index.blade.php. --}}
<tr data-id="{{ $row->id }}" data-material-id="{{ $row->jersey_material_id }}" data-type-id="{{ $row->jersey_product_type_id }}" data-category-id="{{ $row->jersey_category_id }}">
    <td class="p-2.5 font-medium text-indigo-600">{{ $row->material->name ?? 'Semua Bahan' }}@if(($row->kelompok ?? 'dewasa') === 'anak')<span class="ml-2 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[9px] font-medium uppercase">Anak</span>@endif</td>
    <td class="p-2.5">{{ $row->productType->name ?? 'Semua' }}</td>
    <td class="p-2.5">{{ $row->category->name ?? 'Semua' }}</td>
    <td class="p-2.5 text-center">{{ $row->qty_min }} - {{ $row->qty_max ?? '∞' }}</td>
    <td class="p-2.5 text-right font-medium">Rp {{ number_format($row->price, 0, ',', '.') }}</td>
    <td class="p-2.5 text-center">
        <div class="flex items-center justify-center gap-1">
            <button type="button" onclick="bukaEditHarga({{ Illuminate\Support\Js::from([
                'id' => $row->id,
                'jersey_material_id' => $row->jersey_material_id,
                'jersey_product_type_id' => $row->jersey_product_type_id,
                'jersey_category_id' => $row->jersey_category_id,
                'qty_min' => $row->qty_min,
                'qty_max' => $row->qty_max,
                'price' => $row->price,
            ]) }})" class="w-8 h-8 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50" title="Ubah"><i class="far fa-edit text-xs"></i></button>
            <button type="button" onclick="hapusHarga({{ $row->id }})" class="w-8 h-8 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50" title="Hapus"><i class="far fa-trash-alt text-xs"></i></button>
        </div>
    </td>
</tr>
