@extends('layouts.app')

@section('content')
@include('partials.rupiah-format-script')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Finishing Produk" subtitle="Master Produk & BOM / Jenis Pekerjaan Finishing">
        <x-slot:actions>
            <x-button onclick="openModal('tambah-finishing')">
                <i class="fas fa-plus"></i>
                Finishing Baru
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER & SEARCH (bentuknya sama dengan Jasa dan Konsumen) --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-8">
        <form method="GET" action="{{ route('finishing.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[260px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Finishing</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full bg-white border border-slate-200 rounded-md py-2 pl-12 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "
                           placeholder="Ketik nama finishing atau keterangan...">
                </div>
            </div>
            <div class="w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Mesin/Divisi</label>
                <select name="id_mesin" class="select2-filter w-full">
                    <option value="">Semua Divisi</option>
                    @foreach($mesin as $m)
                        <option value="{{ $m->id_mesin }}" @selected(request('id_mesin') == $m->id_mesin)>{{ $m->nama_mesin }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Kategori Berlaku</label>
                <select name="id_kategori" class="select2-filter w-full">
                    <option value="">Semua</option>
                    <option value="semua" @selected(request('id_kategori') === 'semua')>Berlaku semua kategori</option>
                    @foreach($kategori as $kat)
                        <option value="{{ $kat->id_kategori }}" @selected(request('id_kategori') == $kat->id_kategori)>{{ $kat->nama_kategori }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full md:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Metode Hitung</label>
                <select name="metode_hitung" class="select2-filter w-full">
                    <option value="">Semua Metode</option>
                    <option value="luas" @selected(request('metode_hitung') === 'luas')>Luas (m²)</option>
                    <option value="keliling" @selected(request('metode_hitung') === 'keliling')>Keliling (m)</option>
                    <option value="KEPING_ROLL" @selected(request('metode_hitung') === 'KEPING_ROLL')>Keping (m) — jahit keliling barang per keping</option>
                    <option value="quantity" @selected(request('metode_hitung') === 'quantity')>Satuan (Pcs)</option>
                    <option value="LINEAR_GRID" @selected(request('metode_hitung') === 'LINEAR_GRID')>Potong Manual (meter potong)</option>
                </select>
            </div>
            <button type="submit" class="h-[38px] px-6 bg-indigo-600 text-white rounded-xl text-[11px] font-medium uppercase tracking-widest hover:bg-indigo-700">Filter</button>
            <a href="{{ route('finishing.index') }}" class="h-[38px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all" title="Reset Filter">
                <i class="fas fa-undo-alt text-sm"></i>
            </a>
        </form>
    </div>

    <x-table>
        <thead>
            <tr>
                <th class="px-4 py-5 text-center w-10">#</th>
                <th class="px-6 py-5 text-left">Nama Finishing</th>
                <th class="px-4 py-5 text-left">Mesin/Divisi</th>
                <th class="px-4 py-5 text-left">Kategori Berlaku</th>
                <th class="px-4 py-5 text-center">Metode Hitung</th>
                <th class="px-4 py-5 text-right">Harga Default</th>
                <th class="px-8 py-5 text-right">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @forelse($data as $i => $d)
            <tr class="group hover:bg-slate-50/30 transition-all">
                <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $i + 1 }}</td>
                <td class="px-6 py-5">
                    <div class="text-[13px] font-medium text-slate-700 group-hover:text-indigo-600 transition-colors uppercase">
                        {{ $d->nama_finishing }}
                    </div>
                    @if($d->keterangan)
                        <div class="text-[10px] text-slate-400 mt-0.5">{{ $d->keterangan }}</div>
                    @endif
                </td>
                <td class="px-4 py-5">
                    <span class="text-[12px] font-medium text-slate-600">{{ $d->mesin->nama_mesin ?? '-' }}</span>
                </td>
                <td class="px-4 py-5">
                    @if($d->kategori)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-600 border border-emerald-100 uppercase">{{ $d->kategori->nama_kategori }}</span>
                    @else
                        <span class="text-[11px] text-slate-500 italic">Semua Kategori</span>
                    @endif
                </td>
                <td class="px-4 py-5 text-center">
                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-[10px] font-medium uppercase tracking-wider bg-slate-100 text-slate-600 border border-slate-200">
                        {{ match($d->metode_hitung) { 'luas' => 'Luas (m²)', 'keliling' => 'Keliling (m)', 'KEPING_ROLL' => 'Keping (m)', 'LINEAR_GRID' => 'Potong Manual (m)', default => 'Satuan (Pcs)' } }}
                    </span>
                </td>
                <td class="px-4 py-5 text-right">
                    <span class="text-[13px] font-medium text-slate-800">Rp {{ number_format($d->harga_default, 0, ',', '.') }}</span>
                    @if($d->metode_hitung === 'LINEAR_GRID')
                        <div class="text-[10px] text-slate-400 mt-0.5">tarif per meter potong</div>
                        <div class="text-[10px] text-slate-500 mt-0.5">
                            minimum charge Rp {{ number_format((float) ($d->minimum_charge ?? 0), 0, ',', '.') }}
                            @if((float) ($d->minimum_charge ?? 0) <= 0)
                                <span class="italic text-slate-400">(tanpa minimum)</span>
                            @endif
                        </div>
                    @endif
                </td>
                <td class="px-8 py-5 text-right">
                    <div class="flex justify-end gap-2">
                        <x-button size="icon" variant="ghost" onclick="openModal('harga-finishing-{{ $d->id_finishing }}')" class="hover:text-indigo-500 hover:bg-indigo-50" title="Harga Bertingkat per Qty">
                            <i class="fas fa-layer-group text-sm"></i>
                        </x-button>
                        <x-button size="icon" variant="ghost" onclick="openEditFinishing({{ json_encode($d) }})" class="hover:text-amber-500 hover:bg-amber-50" aria-label="Ubah" title="Ubah">
                            <i class="far fa-edit text-sm"></i>
                        </x-button>

                        <form action="{{ route('finishing.destroy', $d->id_finishing) }}" method="POST" class="inline" id="delete-form-finishing-{{ $d->id_finishing }}">
                            @csrf @method('DELETE')
                            <x-button type="button" size="icon" variant="ghost" onclick="confirmDeleteFinishing('{{ $d->id_finishing }}', '{{ $d->nama_finishing }}')" class="hover:text-rose-500 hover:bg-rose-50">
                                <i class="far fa-trash-alt text-sm"></i>
                            </x-button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    @if(request()->hasAny(['search', 'id_mesin', 'id_kategori', 'metode_hitung']))
                        <x-empty-state title="Tidak ada finishing yang cocok" description="Ubah kata kunci atau penyaring, atau tekan tombol reset." />
                    @else
                        <x-empty-state title="Belum ada jenis finishing" description="Tambahkan jenis finishing seperti Laminasi, Kisscut, atau Jahit Banner." />
                    @endif
                </td>
            </tr>
            @endforelse
        </tbody>
    </x-table>

    {{-- MODAL HARGA BERTINGKAT (satu per finishing - dirender server-side,
         setiap baris tier form-nya sendiri dengan id_finishing_harga asli
         di database, supaya add/edit/hapus satu baris tidak pernah
         menyentuh baris lain sama sekali). --}}
    @foreach($data as $d)
    <x-modal id="harga-finishing-{{ $d->id_finishing }}" title="Harga Bertingkat: {{ $d->nama_finishing }}"
         subtitle="Harga jual menyesuaikan jumlah, makin banyak makin murah per pcs"
         icon="fa-layer-group" maxWidth="xl">
        <div class="mb-4 p-3 bg-slate-50 border border-slate-100 rounded-xl text-xs text-slate-500">
            Harga Default (<b>Rp {{ number_format($d->harga_default, 0, ',', '.') }}</b>) dipakai kalau qty tidak cocok tier manapun di bawah, atau belum ada tier sama sekali. Kasir tetap bisa menimpa manual di Input Order - ini hanya acuan/starting point.
        </div>

        @php
            $tiers = $hargaTiers[$d->id_finishing] ?? collect();
            // Satuan rentang eksplisit ikut metode_hitung finishing ITU SENDIRI
            // ('luas'/'keliling'/'quantity' - lihat perbaikan qty acuan
            // finishing sesi sebelumnya), sama seperti Daftar Harga produk -
            // luas m² & keliling meter boleh pecahan, Pcs wajib bulat.
            $satuanRentangFinishing = match($d->metode_hitung) { 'luas' => 'm²', 'keliling' => 'm', 'LINEAR_GRID' => 'meter', default => 'Pcs' };
            $stepRentangFinishing = $d->metode_hitung === 'quantity' ? '1' : '0.01';
        @endphp

        <div id="tier-container-finishing-{{ $d->id_finishing }}" class="space-y-2 mb-4" data-satuan-rentang="{{ $satuanRentangFinishing }}">
            <p class="text-xs text-slate-400 italic tier-empty-msg-finishing" {{ $tiers->isNotEmpty() ? 'hidden' : '' }}>Belum ada tier harga - selalu memakai Harga Default di atas.</p>
            @foreach($tiers as $t)
            <div class="tier-row-finishing flex items-center gap-2 bg-white border border-slate-100 rounded-xl p-2" data-id="{{ $t->id_finishing_harga }}">
                <form action="{{ route('finishing.harga.update', $t->id_finishing_harga) }}" class="tier-edit-form-finishing flex items-center gap-2 flex-1">
                    @csrf
                    <input type="number" name="range_min" value="{{ angka_polos($t->range_min, 4) }}" min="{{ $stepRentangFinishing }}" step="{{ $stepRentangFinishing }}" required class="w-20 text-xs text-center bg-slate-50 border-none rounded-lg py-2">
                    <span class="text-[10px] text-slate-500 font-medium">s/d</span>
                    <input type="number" name="range_max" value="{{ angka_polos($t->range_max, 4) }}" min="{{ $stepRentangFinishing }}" step="{{ $stepRentangFinishing }}" placeholder="~" class="w-20 text-xs text-center bg-slate-50 border-none rounded-lg py-2">
                    <span class="text-[9px] text-slate-400 uppercase shrink-0">{{ $satuanRentangFinishing }}</span>
                    <span class="text-[10px] text-slate-400">=</span>
                    <span class="text-[10px] text-slate-400">Rp</span>
                    <input type="text" inputmode="numeric" name="harga" value="{{ number_format((int) $t->harga, 0, '', '.') }}" required class="flex-1 text-xs bg-slate-50 border-none rounded-lg py-2 font-medium format-ribuan">
                    <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all shrink-0" title="Simpan">
                        <i class="fas fa-save text-xs"></i>
                    </button>
                </form>
                <button type="button" onclick="confirmHapusTierFinishing({{ $t->id_finishing_harga }})" class="w-8 h-8 flex items-center justify-center rounded-lg text-rose-400 hover:bg-rose-50 transition-all shrink-0" title="Hapus tier ini">
                    <i class="fas fa-trash-alt text-xs"></i>
                </button>
            </div>
            @endforeach
        </div>

        <form action="{{ route('finishing.harga.store', $d->id_finishing) }}" class="tier-add-form-finishing flex items-center gap-2 bg-emerald-50/50 border border-dashed border-emerald-200 rounded-xl p-2" data-id-finishing="{{ $d->id_finishing }}" data-satuan-rentang="{{ $satuanRentangFinishing }}">
            @csrf
            <input type="number" name="range_min" placeholder="Min" min="{{ $stepRentangFinishing }}" step="{{ $stepRentangFinishing }}" required class="w-20 text-xs text-center bg-white border-none rounded-lg py-2">
            <span class="text-[10px] text-slate-500 font-medium">s/d</span>
            <input type="number" name="range_max" placeholder="~" min="{{ $stepRentangFinishing }}" step="{{ $stepRentangFinishing }}" class="w-20 text-xs text-center bg-white border-none rounded-lg py-2">
            <span class="text-[9px] text-slate-400 uppercase shrink-0">{{ $satuanRentangFinishing }}</span>
            <span class="text-[10px] text-slate-400">=</span>
            <span class="text-[10px] text-slate-400">Rp</span>
            <input type="text" inputmode="numeric" name="harga" placeholder="0" required class="flex-1 text-xs bg-white border-none rounded-lg py-2 font-medium format-ribuan">
            <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 transition-all shrink-0" title="Tambah Tier">
                <i class="fas fa-plus text-xs"></i>
            </button>
        </form>

        <div class="pt-4 flex justify-end">
            <x-button type="button" variant="secondary" onclick="closeModal('harga-finishing-{{ $d->id_finishing }}')">Tutup</x-button>
        </div>
    </x-modal>
    @endforeach

    {{-- MODAL TAMBAH --}}
    <x-modal id="tambah-finishing" title="Finishing Baru" maxWidth="md">
        <form action="{{ route('finishing.store') }}" method="POST" class="space-y-5">
            @csrf
            <x-form.group label="Nama Finishing" name="nama_finishing" required dalam>
                <x-form.input type="text" name="nama_finishing" placeholder="Contoh: Laminasi Doff" required />
            </x-form.group>

            <div class="grid grid-cols-2 gap-4">
                <x-form.group label="Mesin/Divisi" name="id_mesin" dalam>
                    <x-form.select name="id_mesin">
                        <option value="">-- Umum --</option>
                        @foreach($mesin as $m)
                            <option value="{{ $m->id_mesin }}">{{ $m->nama_mesin }}</option>
                        @endforeach
                    </x-form.select>
                </x-form.group>
                <x-form.group label="Metode Hitung" name="metode_hitung" required dalam>
                    <x-form.select name="metode_hitung" required>
                        <option value="luas">Luas (m²)</option>
                        <option value="keliling">Keliling (m) - mis. jahit tepi</option>
                        <option value="KEPING_ROLL">Keping (m) - jahit keliling barang per keping</option>
                        <option value="quantity">Satuan (Pcs)</option>
                        <option value="LINEAR_GRID">Potong Manual - tarif per meter potong</option>
                    </x-form.select>
                </x-form.group>
            </div>

            <x-form.group label="Kategori Berlaku" name="id_kategori" help="Kosongkan supaya finishing ini muncul untuk semua kategori produk di Input Pesanan." dalam>
                <x-form.select name="id_kategori">
                    <option value="">-- Semua Kategori --</option>
                    @foreach($kategori as $kat)
                        <option value="{{ $kat->id_kategori }}">{{ $kat->nama_kategori }}</option>
                    @endforeach
                </x-form.select>
            </x-form.group>

            <x-form.group label="Harga Default (Rp)" name="harga_default" help="Untuk metode Potong Manual, angka ini dibaca sebagai tarif per meter potong (biaya = meter garis potong x tarif, ditambah minimum charge di bawah)." dalam>
                <x-form.input type="text" inputmode="numeric" name="harga_default" required class="format-ribuan" />
            </x-form.group>

            <x-form.group label="Minimum Charge (Rp)" name="minimum_charge" help="Lantai biaya finishing per item - hanya dipakai metode Potong Manual. Isi 0 kalau tidak ada minimum charge (biaya persis hasil rumus)." dalam>
                <x-form.input type="text" inputmode="numeric" name="minimum_charge" value="0" class="format-ribuan" />
            </x-form.group>

            <x-form.group label="Keterangan" name="keterangan" dalam>
                <textarea name="keterangan" rows="2" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "></textarea>
            </x-form.group>

            <div class="pt-4 flex gap-3">
                <x-button type="button" variant="secondary" onclick="closeModal('tambah-finishing')" class="flex-1">Batalkan</x-button>
                <x-button type="submit" class="flex-1">Simpan Finishing</x-button>
            </div>
        </form>
    </x-modal>

    {{-- MODAL EDIT --}}
    <x-modal id="edit-finishing" title="Perbarui Finishing" maxWidth="md">
        <form id="formEditFinishing" method="POST" class="space-y-5">
            @csrf @method('PUT')
            <x-form.group label="Nama Finishing" dalam>
                <x-form.input type="text" name="nama_finishing" id="edit_nama_finishing" required />
            </x-form.group>

            <div class="grid grid-cols-2 gap-4">
                <x-form.group label="Mesin/Divisi" dalam>
                    <x-form.select name="id_mesin" id="edit_id_mesin">
                        <option value="">-- Umum --</option>
                        @foreach($mesin as $m)
                            <option value="{{ $m->id_mesin }}">{{ $m->nama_mesin }}</option>
                        @endforeach
                    </x-form.select>
                </x-form.group>
                <x-form.group label="Metode Hitung" dalam>
                    <x-form.select name="metode_hitung" id="edit_metode_hitung" required>
                        <option value="luas">Luas (m²)</option>
                        <option value="keliling">Keliling (m) - mis. jahit tepi</option>
                        <option value="KEPING_ROLL">Keping (m) - jahit keliling barang per keping</option>
                        <option value="quantity">Satuan (Pcs)</option>
                        <option value="LINEAR_GRID">Potong Manual - tarif per meter potong</option>
                    </x-form.select>
                </x-form.group>
            </div>

            <x-form.group label="Kategori Berlaku" help="Kosongkan supaya finishing ini muncul untuk semua kategori produk di Input Pesanan." dalam>
                <x-form.select name="id_kategori" id="edit_id_kategori">
                    <option value="">-- Semua Kategori --</option>
                    @foreach($kategori as $kat)
                        <option value="{{ $kat->id_kategori }}">{{ $kat->nama_kategori }}</option>
                    @endforeach
                </x-form.select>
            </x-form.group>

            <x-form.group label="Harga Default (Rp)" help="Untuk metode Potong Manual, angka ini dibaca sebagai tarif per meter potong (biaya = meter garis potong x tarif, ditambah minimum charge di bawah)." dalam>
                <x-form.input type="text" inputmode="numeric" name="harga_default" id="edit_harga_default" required class="format-ribuan" />
            </x-form.group>

            <x-form.group label="Minimum Charge (Rp)" help="Lantai biaya finishing per item - hanya dipakai metode Potong Manual. Isi 0 kalau tidak ada minimum charge (biaya persis hasil rumus)." dalam>
                <x-form.input type="text" inputmode="numeric" name="minimum_charge" id="edit_minimum_charge" value="0" class="format-ribuan" />
            </x-form.group>

            <x-form.group label="Keterangan" dalam>
                <textarea name="keterangan" id="edit_keterangan" rows="2" class="w-full bg-white border border-slate-200 rounded-md py-2 px-3 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-amber-500/20 "></textarea>
            </x-form.group>

            <div class="pt-4 flex gap-3">
                <x-button type="button" variant="secondary" onclick="closeModal('edit-finishing')" class="flex-1">Batalkan</x-button>
                <x-button type="submit" variant="warning" class="flex-1">Simpan Perubahan</x-button>
            </div>
        </form>
    </x-modal>

</div>

@push('scripts')
<script>
    $(function () {
        $('.select2-filter').select2({ width: '100%' }).on('change', function () { $(this).closest('form').submit(); });
    });

    function openEditFinishing(data) {
        const form = document.getElementById('formEditFinishing');
        form.action = `{{ url('finishing') }}/${data.id_finishing}`;

        document.getElementById('edit_nama_finishing').value = data.nama_finishing;
        document.getElementById('edit_id_mesin').value = data.id_mesin || '';
        document.getElementById('edit_id_kategori').value = data.id_kategori || '';
        document.getElementById('edit_metode_hitung').value = data.metode_hitung || 'luas';
        document.getElementById('edit_harga_default').value = formatRupiahDariAngka(data.harga_default);
        document.getElementById('edit_minimum_charge').value = formatRupiahDariAngka(data.minimum_charge || 0);
        document.getElementById('edit_keterangan').value = data.keterangan || '';

        openModal('edit-finishing');
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    // Semua aksi tier harga (tambah/edit/hapus) dikirim lewat fetch(),
    // BUKAN submit form biasa - supaya modal tetap terbuka dan tabelnya
    // update di tempat tanpa reload seluruh halaman. Sebelumnya reload
    // penuh tiap satu tier disimpan terasa lambat kalau mau memasukkan
    // beberapa tier berturut-turut.
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    function buildFinishingTierRowHtml(t, satuanRentang, stepRentang) {
        return `
            <div class="tier-row-finishing flex items-center gap-2 bg-white border border-slate-100 rounded-xl p-2" data-id="${t.id_finishing_harga}">
                <form action="{{ url('/finishing-harga') }}/${t.id_finishing_harga}" class="tier-edit-form-finishing flex items-center gap-2 flex-1">
                    <input type="hidden" name="_token" value="${csrfToken}">
                    <input type="number" name="range_min" value="${t.range_min}" min="${stepRentang}" step="${stepRentang}" required class="w-20 text-xs text-center bg-slate-50 border-none rounded-lg py-2">
                    <span class="text-[10px] text-slate-500 font-medium">s/d</span>
                    <input type="number" name="range_max" value="${t.range_max ?? ''}" min="${stepRentang}" step="${stepRentang}" placeholder="~" class="w-20 text-xs text-center bg-slate-50 border-none rounded-lg py-2">
                    <span class="text-[9px] text-slate-400 uppercase shrink-0">${satuanRentang}</span>
                    <span class="text-[10px] text-slate-400">=</span>
                    <span class="text-[10px] text-slate-400">Rp</span>
                    <input type="text" inputmode="numeric" name="harga" value="${formatRupiah(parseInt(t.harga))}" required class="flex-1 text-xs bg-slate-50 border-none rounded-lg py-2 font-medium format-ribuan">
                    <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all shrink-0" title="Simpan">
                        <i class="fas fa-save text-xs"></i>
                    </button>
                </form>
                <button type="button" onclick="confirmHapusTierFinishing(${t.id_finishing_harga})" class="w-8 h-8 flex items-center justify-center rounded-lg text-rose-400 hover:bg-rose-50 transition-all shrink-0" title="Hapus tier ini">
                    <i class="fas fa-trash-alt text-xs"></i>
                </button>
            </div>`;
    }

    // PENTING: body dikirim sebagai JSON, BUKAN FormData - PHP tidak
    // otomatis mem-parse body multipart/form-data untuk method PATCH/PUT/
    // DELETE (keterbatasan lama PHP, cuma method POST asli yang diurai ke
    // $_POST). Kalau tetap pakai FormData, request PATCH/DELETE akan
    // sampai ke Laravel dengan input KOSONG dan validasi selalu gagal
    // "field is required" walau datanya kelihatan benar di form.
    async function submitTierFormAjax(form, method) {
        const formData = new FormData(form);
        const payload = {};
        formData.forEach((v, k) => { if (k !== '_token') payload[k] = v; });
        try {
            const res = await fetch(form.action, {
                method,
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                Swal.fire('Gagal menyimpan', data.message || 'Gagal menyimpan.', 'error');
                return null;
            }
            return data;
        } catch (e) {
            Swal.fire('Koneksi terputus', 'Terjadi kesalahan koneksi.', 'error');
            return null;
        }
    }

    document.addEventListener('submit', async function (e) {
        if (e.target.matches('.tier-add-form-finishing')) {
            e.preventDefault();
            const form = e.target;
            // Pagar klik ganda. Form ini dikirim lewat fetch() JSON jadi tidak
            // pernah membawa submit_id dan tidak terlindungi SubmitSekaliJalan.
            // Pagar sesungguhnya ada di server (CekRentangHarga menolak rentang
            // bertabrakan); yang ini supaya operator tidak mengirim dua kali dan
            // tidak melihat pesan "rentang tumpang tindih" untuk kliknya sendiri.
            const tombol = form.querySelector('button[type="submit"]');
            if (tombol) { tombol.disabled = true; tombol.classList.add('opacity-60'); }
            const data = await submitTierFormAjax(form, 'POST');
            // Tombol hidup lagi baik sukses maupun gagal: form di-reset setelah
            // sukses supaya operator bisa menambah tier lagi.
            if (tombol) { tombol.disabled = false; tombol.classList.remove('opacity-60'); }
            if (!data) return;
            const idFinishing = form.dataset.idFinishing;
            const container = document.getElementById('tier-container-finishing-' + idFinishing);
            container.querySelector('.tier-empty-msg-finishing').hidden = true;
            const stepRentang = form.querySelector('[name="range_min"]').step || '1';
            container.insertAdjacentHTML('beforeend', buildFinishingTierRowHtml(data.row, form.dataset.satuanRentang || '', stepRentang));
            form.reset();
            Toast.fire({ icon: 'success', title: data.message });
        } else if (e.target.matches('.tier-edit-form-finishing')) {
            e.preventDefault();
            const data = await submitTierFormAjax(e.target, 'PATCH');
            if (!data) return;
            Toast.fire({ icon: 'success', title: data.message });
        }
    });

    function confirmHapusTierFinishing(id) {
        Swal.fire({
            title: 'Hapus Tier Harga Ini?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            cancelButtonColor: '#F1F5F1',
            cancelButtonText: '<span style="color: #4A5B53">Batal</span>',
            confirmButtonText: 'Ya, Hapus!'
        }).then(async (result) => {
            if (!result.isConfirmed) return;
            try {
                const res = await fetch(`{{ url('/finishing-harga') }}/${id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    Swal.fire('Gagal menghapus', data.message || 'Gagal menghapus.', 'error');
                    return;
                }
                const row = document.querySelector(`.tier-row-finishing[data-id="${id}"]`);
                const container = row.closest('[id^="tier-container-finishing-"]');
                row.remove();
                if (container.querySelectorAll('.tier-row-finishing').length === 0) {
                    container.querySelector('.tier-empty-msg-finishing').hidden = false;
                }
                Toast.fire({ icon: 'success', title: data.message });
            } catch (e) {
                Swal.fire('Koneksi terputus', 'Terjadi kesalahan koneksi.', 'error');
            }
        });
    }

    function confirmDeleteFinishing(id, name) {
        Swal.fire({
            title: 'Hapus Finishing?',
            html: `Menghapus <b>${escapeHtml(name)}</b>? Data yang dihapus tidak dapat dikembalikan.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            cancelButtonColor: '#F1F5F1',
            cancelButtonText: '<span style="color: #4A5B53">Batal</span>',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete-form-finishing-' + id).submit();
            }
        });
    }
</script>
@endpush
@endsection
