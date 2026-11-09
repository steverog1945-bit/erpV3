{{-- Matriks hak akses per modul: Lihat / Tambah / Kelola (ubah) / Hapus. Param: $menus, $izin, opsional $izinRole & $override (mode per user) --}}
@php
    $aksi = ['view' => 'Lihat', 'add' => 'Tambah', 'edit' => 'Kelola', 'delete' => 'Hapus'];
    $ada = fn ($idMenu, $a) => (bool) ($izin[$idMenu][$a] ?? false);
    $modeUser = isset($izinRole);
@endphp
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="matriks-izin">
            <thead>
                <tr class="bg-slate-900 text-white">
                    <th class="p-4 text-xs font-medium uppercase tracking-wider">Modul</th>
                    @foreach($aksi as $k => $label)
                    <th class="p-4 text-xs font-medium uppercase tracking-wider text-center w-28">
                        <label class="inline-flex flex-col items-center gap-1 cursor-pointer">
                            {{ $label }}
                            <input type="checkbox" class="cek-kolom w-4 h-4 rounded border-slate-400 text-indigo-500" data-aksi="{{ $k }}" title="Pilih semua kolom {{ $label }}">
                        </label>
                    </th>
                    @endforeach
                    <th class="p-4 text-xs font-medium uppercase tracking-wider text-center w-24">Semua</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($menus as $menu)
                @php
                    $beda = $modeUser && isset($override[$menu->id_menu]);
                    $nama = $menu->label_kustom ?: $menu->nama_menu;
                @endphp
                <tr class="hover:bg-indigo-50/40 transition-colors {{ $menu->aktif ? '' : 'opacity-50' }} {{ $beda ? 'bg-amber-50/60' : '' }}" data-row>
                    <td class="p-4">
                        <div class="font-medium text-slate-700 text-sm">{{ $nama }}
                            @if(!$menu->aktif)<span class="ml-1 text-[10px] font-medium text-slate-400 uppercase">(disembunyikan)</span>@endif
                            @if($beda)<span class="ml-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 text-[10px] font-medium uppercase">khusus user</span>@endif
                        </div>
                        <div class="text-[11px] text-slate-400">{{ $menu->url_link }}</div>
                    </td>
                    @foreach($aksi as $k => $label)
                    <td class="p-4 text-center">
                        <input type="checkbox" name="izin[{{ $menu->id_menu }}][{{ $k }}]" value="1" data-aksi="{{ $k }}" {{ $ada($menu->id_menu, $k) ? 'checked' : '' }}
                               class="cek-izin w-6 h-6 rounded-lg border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                    </td>
                    @endforeach
                    <td class="p-4 text-center">
                        <input type="checkbox" class="cek-baris w-5 h-5 rounded border-slate-300 text-emerald-600 cursor-pointer" title="Beri semua aksi pada modul ini">
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<p class="mt-3 text-xs text-slate-400">Tambah/Kelola/Hapus otomatis menyalakan Lihat. <b>Tambah</b> = menyimpan data baru, <b>Kelola</b> = mengubah/memperbarui data, <b>Hapus</b> = menghapus/membatalkan.</p>

<script>
(function () {
    const tbl = document.getElementById('matriks-izin');
    const barisCek = tr => [...tr.querySelectorAll('.cek-izin')];
    function segarkan() {
        tbl.querySelectorAll('tr[data-row]').forEach(tr => {
            const c = barisCek(tr); tr.querySelector('.cek-baris').checked = c.every(x => x.checked);
        });
        tbl.querySelectorAll('.cek-kolom').forEach(h => {
            const c = [...tbl.querySelectorAll('.cek-izin[data-aksi="' + h.dataset.aksi + '"]')]; h.checked = c.length && c.every(x => x.checked);
        });
    }
    tbl.addEventListener('change', e => {
        const t = e.target, tr = t.closest('tr[data-row]');
        if (t.classList.contains('cek-izin') && tr) {
            const c = barisCek(tr);
            if (t.dataset.aksi !== 'view' && t.checked) c.find(x => x.dataset.aksi === 'view').checked = true;
            if (t.dataset.aksi === 'view' && !t.checked) c.forEach(x => x.checked = false);
        } else if (t.classList.contains('cek-baris') && tr) {
            barisCek(tr).forEach(x => x.checked = t.checked);
        } else if (t.classList.contains('cek-kolom')) {
            tbl.querySelectorAll('.cek-izin[data-aksi="' + t.dataset.aksi + '"]').forEach(x => {
                x.checked = t.checked;
                if (t.checked && t.dataset.aksi !== 'view') x.closest('tr').querySelector('.cek-izin[data-aksi="view"]').checked = true;
                if (!t.checked && t.dataset.aksi === 'view') barisCek(x.closest('tr')).forEach(y => y.checked = false);
            });
        }
        segarkan();
    });
    segarkan();
})();
</script>
