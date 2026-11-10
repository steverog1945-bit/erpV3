@extends('layouts.app')

@section('content')
@include('partials.rupiah-format-script')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">
    <x-page-header title="Master Jasa" subtitle="Layanan non-material (mis. instalasi, laminasi luar).">
        <x-slot:actions>
            <x-button onclick="toggleModal('jasa-tambah')">
                <i class="fas fa-concierge-bell"></i>
                Tambah Jasa
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER & SEARCH --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-8">
        <form method="GET" action="{{ route('jasa.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[260px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Jasa</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full bg-white border border-slate-200 rounded-md py-2 pl-12 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 "
                           placeholder="Ketik nama jasa...">
                </div>
            </div>
            {{-- Filter divisi. Strukturnya SAMA PERSIS dengan filter
                 Unit Hitung di sebelahnya: satu <div> pembungkus (agar
                 bisa participating dalam flex) lalu <div> di dalamnya
                 yang memegang lebar md:w-56.

                 Versi sebelumnya hanya punya <div> pembuka tanpa
                 penutup, sehingga blok Unit Hitung masuk ke dalamnya dan
                 tombol reset justru berada DI LUAR <form>. Akibatnya
                 dua filter tersusun ke bawah, tombol Filter menggantung
                 di baris ketiga, dan ada ruang abu-abu lebar yang
                 menganggur. --}}
            <div>
                <div class="w-full md:w-56">
                    <label class="block text-[10px] font-medium text-slate-400 mb-2 ml-2">Divisi</label>
                    <select name="id_mesin" class="select2-filter w-full">
                        <option value="">Semua Divisi</option>
                        @foreach($mesin as $m)
                            <option value="{{ $m->id_mesin }}" {{ request('id_mesin') == $m->id_mesin ? 'selected' : '' }}>{{ $m->nama_mesin }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <div class="w-full md:w-56">
                    <label class="block text-[10px] font-medium text-slate-400 mb-2 ml-2">Unit Hitung</label>
                    <select name="id_satuan" class="select2-filter w-full">
                        <option value="">Semua Satuan</option>
                        @foreach($satuan as $s)
                            <option value="{{ $s->id_satuan }}" {{ request('id_satuan') == $s->id_satuan ? 'selected' : '' }}>{{ $s->nama_satuan }}</option>
                        @endforeach
                </select>
                </div>
            </div>

            <button type="submit" class="h-[38px] px-6 bg-indigo-600 text-white rounded-xl text-[11px] font-medium hover:bg-indigo-700">Filter</button>
            <a href="{{ route('jasa.index') }}" class="h-[38px] px-4 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all" title="Reset Filter">
                <i class="fas fa-undo-alt text-sm"></i>
            </a>
        </form>
    </div>

    {{-- TABLE CONTAINER --}}
    <x-table>
        <thead>
            <tr>
                        <th class="px-4 py-5 text-center w-10">#</th>
                        <th class="px-8 py-5 text-left w-24">ID</th>
                        <th class="px-6 py-5 text-left">Nama Jasa</th>
                        <th class="px-6 py-5 text-center">Divisi</th>
                        <th class="px-6 py-5 text-center">Unit Hitung</th>
                        <th class="px-6 py-5 text-right">Harga</th>
                        <th class="px-6 py-5 text-right">Biaya</th>
                        <th class="px-6 py-5 text-left">Keterangan</th>
                        <th class="px-8 py-5 text-right">Manajemen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($jasa as $i => $j)
                    <tr class="group hover:bg-slate-50/30 transition-all">
                        <td class="px-4 py-5 text-center text-[11px] text-slate-400">{{ $jasa->firstItem() + $i }}</td>
                        <td class="px-8 py-5 text-xs text-slate-400">#{{ $j->id_jasa }}</td>
                        <td class="px-6 py-5">
                            <span class="text-sm font-medium text-slate-700 uppercase">{{ $j->nama_jasa }}</span>
                        </td>
                        <td class="px-6 py-5 text-center">
                            @if($j->id_mesin)
                                <span class="text-sm text-slate-600">{{ $j->nama_mesin }}</span>
                            @else
                                {{-- Jasa tanpa mesin = belum ditugaskan ke divisi mana. --}}
                                {{-- Ditampilkan, bukan disembunyikan, supaya jasa yang --}}
                                {{-- belum ada divisinya terlihat dan bisa dilengkapi. --}}
                                <span class="text-sm text-slate-400">Tanpa divisi</span>
                            @endif
                        </td>
                        <td class="px-6 py-5 text-center">
                            <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-[10px] font-medium bg-slate-100 text-slate-600 uppercase">
                                {{ $j->nama_satuan ?? '-' }}
                            </span>
                        </td>
                        <td class="px-6 py-5 text-right">
                            @php $tierCount = ($hargaTiers[$j->id_jasa] ?? collect())->count(); @endphp
                            <span id="jasa-harga-summary-{{ $j->id_jasa }}">
                                @if($tierCount > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200">BERTINGKAT ({{ $tierCount }})</span>
                                @elseif($j->harga_default)
                                    <span class="text-sm font-medium text-slate-700">Rp {{ number_format($j->harga_default, 0, ',', '.') }}</span>
                                @else
                                    <span class="text-[10px] text-slate-500 italic">Manual di Kasir</span>
                                @endif
                            </span>
                        </td>
                        <td class="px-6 py-5 text-right">
                            @php $totalBiayaJasa = (float) $j->biaya_operasional + (float) $j->biaya_tinta; @endphp
                            @if($totalBiayaJasa > 0)
                                <span class="text-sm text-slate-600">Rp {{ number_format($totalBiayaJasa, 0, ',', '.') }}</span>
                            @else
                                <span class="text-sm text-slate-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-5">
                            <p class="text-sm text-slate-500 leading-relaxed truncate max-w-xs">
                                {{ $j->keterangan ?? 'Tidak ada deskripsi tambahan' }}
                            </p>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <div class="flex justify-end gap-2">
                                <button onclick="openModal('harga-jasa-{{ $j->id_jasa }}')"
                                        class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-indigo-500 hover:bg-indigo-50 rounded-lg transition-all" title="Harga Bertingkat per Qty">
                                    <i class="fas fa-layer-group text-sm"></i>
                                </button>
                                <button onclick="openEditJasa({{ json_encode($j) }})"
                                        class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-all" aria-label="Ubah" title="Ubah">
                                    <i class="far fa-edit text-sm"></i>
                                </button>

                                <form id="delete-form-jasa-{{ $j->id_jasa }}" action="{{ route('jasa.destroy', $j->id_jasa) }}" method="POST" class="inline">
                                    @csrf @method('DELETE')
                                </form>
                                <button type="button" onclick="confirmDeleteJasa('{{ $j->id_jasa }}', {{ json_encode($j->nama_jasa) }})"
                                        class="w-9 h-9 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all">
                                    <i class="far fa-trash-alt text-sm"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-20 text-center text-slate-400 font-medium italic">Belum ada jasa yang terdaftar.</td>
                    </tr>
                    @endforelse
        </tbody>
    </x-table>

    <x-table.pagination :paginator="$jasa" />
</div>

{{-- MODAL TAMBAH

     Tanpa pembungkus max-w-[85%] mx-auto di tiap field. Wrapper itu
     membuat isi modal ini sempit dan bergeser ke tengah, sementara
     modal lain memakai lebar penuh - perbedaan yang terasa langsung
     waktu membandingkan keduanya.
--}}
<x-modal id="jasa-tambah" title="Tambah Jasa"
         subtitle="Jasa non-material yang ikut ditagihkan di nota"
         icon="fa-screwdriver-wrench" maxWidth="md">
    <form action="{{ route('jasa.store') }}" method="POST" class="space-y-4">
        @csrf
        <div class="ld">
            <input type="text" name="nama_jasa" id="tambah_nama_jasa" placeholder=" " required>
            <label class="ld-label" for="tambah_nama_jasa">Nama jasa<span class="ld-wajib" aria-hidden="true">*</span></label>
        </div>

        {{-- Divisi = mesin pengerjaan, sama seperti produk.id_mesin. Kosongkan bila belum ditugaskan. --}}
        <div class="ld">
            <select name="id_mesin" id="tambah_id_mesin">
                <option value="">-- Tanpa Divisi --</option>
                @foreach($mesin as $m)
                    <option value="{{ $m->id_mesin }}">{{ $m->nama_mesin }}</option>
                @endforeach
            </select>
            <label class="ld-label" for="tambah_id_mesin">Divisi yang mengerjakan</label>
        </div>

        <div class="ld">
            <select name="id_satuan" id="tambah_id_satuan">
                <option value="">-- Pilih Satuan --</option>
                @foreach($satuan as $s)
                    <option value="{{ $s->id_satuan }}">{{ $s->nama_satuan }}</option>
                @endforeach
            </select>
            <label class="ld-label" for="tambah_id_satuan">Unit hitung</label>
        </div>

        <div class="ld">
            <input type="text" inputmode="numeric" name="harga_default" id="tambah_harga_default" placeholder=" " class="format-ribuan">
            <label class="ld-label" for="tambah_harga_default">Harga dasar (Rp), opsional</label>
        </div>

        {{-- Biaya ini dipotong dari harga jual untuk menghitung margin. --}}
        {{-- Kalau dibiarkan 0, margin jasa selalu terlihat 100% di laporan - --}}
        {{-- bukan karena jasanya gratis, tapi karena biayanya belum dicatat. --}}
        <div class="grid grid-cols-2 gap-4">
            <div class="ld">
                <input type="text" inputmode="numeric" name="biaya_operasional" id="tambah_biaya_operasional" placeholder=" " class="format-ribuan">
                <label class="ld-label" for="tambah_biaya_operasional">Biaya operasional (Rp)</label>
            </div>
            <div class="ld">
                <input type="text" inputmode="numeric" name="biaya_tinta" id="tambah_biaya_tinta" placeholder=" " class="format-ribuan">
                <label class="ld-label" for="tambah_biaya_tinta">Biaya tinta (Rp)</label>
            </div>
        </div>

        <div class="ld">
            <textarea name="keterangan" id="tambah_keterangan" placeholder=" "></textarea>
            <label class="ld-label" for="tambah_keterangan">Keterangan singkat</label>
        </div>

        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="toggleModal('jasa-tambah')">Batal</x-button>
            <x-button type="submit">Simpan Jasa</x-button>
        </x-slot:footer>
    </form>
</x-modal>

{{-- MODAL EDIT --}}
<x-modal id="jasa-edit" title="Edit Jasa"
         subtitle="Perubahan berlaku untuk nota berikutnya"
         icon="fa-pen" maxWidth="md">
    <form id="formEditJasa" method="POST" class="space-y-4">
        @csrf @method('PUT')
        <div class="ld">
            <input placeholder=" " type="text" name="nama_jasa" id="edit_nama_jasa" required
                   class="w-full text-sm font-medium text-slate-700">
            <label class="ld-label" for="edit_nama_jasa">Nama Jasa<span class="ld-wajib" aria-hidden="true">*</span></label>
        </div>

        <div class="ld">
            <select name="id_mesin" id="edit_id_mesin"
                    class="w-full text-sm font-medium text-slate-700 appearance-none">
                <option value="">-- Tanpa Divisi --</option>
                @foreach($mesin as $m)
                    <option value="{{ $m->id_mesin }}">{{ $m->nama_mesin }}</option>
                @endforeach
            </select>
            <label class="ld-label" for="edit_id_mesin">Divisi (Mesin)</label>
        </div>

        <div class="ld">
            <select name="id_satuan" id="edit_id_satuan"
                    class="w-full text-sm font-medium text-slate-700 appearance-none">
                <option value="">-- Pilih Satuan --</option>
                @foreach($satuan as $s)
                    <option value="{{ $s->id_satuan }}">{{ $s->nama_satuan }}</option>
                @endforeach
            </select>
            <label class="ld-label" for="edit_id_satuan">Unit Hitung</label>
        </div>

        <div class="ld">
            <input type="text" inputmode="numeric" name="harga_default" id="edit_harga_default" placeholder="0"
                   class="w-full text-sm font-medium text-slate-700 format-ribuan">
            <label class="ld-label" for="edit_harga_default">Harga Dasar (Rp) - opsional, titik awal di Kasir</label>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="ld">
                <input type="text" inputmode="numeric" name="biaya_operasional" id="edit_biaya_operasional" placeholder="0"
                       class="w-full text-sm font-medium text-slate-700 format-ribuan">
                <label class="ld-label" for="edit_biaya_operasional">Biaya Operasional (Rp)</label>
            </div>
            <div class="ld">
                <input type="text" inputmode="numeric" name="biaya_tinta" id="edit_biaya_tinta" placeholder="0"
                       class="w-full text-sm font-medium text-slate-700 format-ribuan">
                <label class="ld-label" for="edit_biaya_tinta">Biaya Tinta (Rp)</label>
            </div>
        </div>

        <div class="ld">
            <textarea placeholder=" " name="keterangan" id="edit_keterangan"
                      class="w-full text-sm font-medium text-slate-700"></textarea>
            <label class="ld-label" for="edit_keterangan">Keterangan Singkat</label>
        </div>

        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="toggleModal('jasa-edit')">Batal</x-button>
            <x-button type="submit">Simpan Perubahan</x-button>
        </x-slot:footer>
    </form>
</x-modal>
{{-- MODAL HARGA BERTINGKAT (satu per jasa - pola sama seperti Finishing,
     server-rendered per baris supaya add/edit/hapus satu tier tidak pernah
     menyentuh tier lain). --}}
@foreach($jasa as $j)
<x-modal id="harga-jasa-{{ $j->id_jasa }}" title="Harga Bertingkat: {{ $j->nama_jasa }}"
         subtitle="Harga jual menyesuaikan jumlah, makin banyak makin murah per pcs"
         icon="fa-layer-group" maxWidth="xl">
    <div class="mb-4 p-3 bg-slate-50 border border-slate-100 rounded-xl text-xs text-slate-500">
        @if($j->harga_default)
            Harga Dasar (<b>Rp {{ number_format($j->harga_default, 0, ',', '.') }}</b>) dipakai kalau qty tidak cocok tier manapun di bawah, atau belum ada tier sama sekali.
        @else
            Belum ada Harga Dasar diatur - tanpa tier yang cocok, kasir tetap input manual seperti biasa.
        @endif
        Kasir tetap bisa menimpa manual di Input Order - ini hanya acuan/starting point.
    </div>

    @php $tiersJ = $hargaTiers[$j->id_jasa] ?? collect(); @endphp

    <div id="tier-container-jasa-{{ $j->id_jasa }}" class="space-y-2 mb-4" data-harga-default="{{ $j->harga_default ?? '' }}">
        <p class="text-xs text-slate-400 italic tier-empty-msg-jasa" {{ $tiersJ->isNotEmpty() ? 'hidden' : '' }}>Belum ada tier harga.</p>
        @foreach($tiersJ as $t)
        <div class="tier-row-jasa flex items-center gap-2 bg-white border border-slate-100 rounded-xl p-2" data-id="{{ $t->id_jasa_harga }}">
            <form action="{{ route('jasa.harga.update', $t->id_jasa_harga) }}" class="tier-edit-form-jasa flex items-center gap-2 flex-1">
                @csrf
                <input type="number" name="range_min" value="{{ angka_polos($t->range_min, 4) }}" min="1" required class="w-16 text-xs text-center bg-slate-50 border-none rounded-lg py-2">
                <span class="text-[10px] text-slate-500 font-medium">s/d</span>
                <input type="number" name="range_max" value="{{ angka_polos($t->range_max, 4) }}" min="1" placeholder="~" class="w-16 text-xs text-center bg-slate-50 border-none rounded-lg py-2">
                <span class="text-[10px] text-slate-400">=</span>
                <span class="text-[10px] text-slate-400">Rp</span>
                <input type="text" inputmode="numeric" name="harga" value="{{ number_format((int) $t->harga, 0, '', '.') }}" required class="flex-1 text-xs bg-slate-50 border-none rounded-lg py-2 font-medium format-ribuan">
                <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all shrink-0" title="Simpan">
                    <i class="fas fa-save text-xs"></i>
                </button>
            </form>
            <button type="button" onclick="confirmHapusTierJasa({{ $t->id_jasa_harga }})" class="w-8 h-8 flex items-center justify-center rounded-lg text-rose-400 hover:bg-rose-50 transition-all shrink-0" title="Hapus tier ini">
                <i class="fas fa-trash-alt text-xs"></i>
            </button>
        </div>
        @endforeach
    </div>

    <form action="{{ route('jasa.harga.store', $j->id_jasa) }}" class="tier-add-form-jasa flex items-center gap-2 bg-emerald-50/50 border border-dashed border-emerald-200 rounded-xl p-2" data-id-jasa="{{ $j->id_jasa }}">
        @csrf
        <input type="number" name="range_min" placeholder="Min" min="1" required class="w-16 text-xs text-center bg-white border-none rounded-lg py-2">
        <span class="text-[10px] text-slate-500 font-medium">s/d</span>
        <input type="number" name="range_max" placeholder="~" min="1" class="w-16 text-xs text-center bg-white border-none rounded-lg py-2">
        <span class="text-[10px] text-slate-400">=</span>
        <span class="text-[10px] text-slate-400">Rp</span>
        <input type="text" inputmode="numeric" name="harga" placeholder="0" required class="flex-1 text-xs bg-white border-none rounded-lg py-2 font-medium format-ribuan">
        <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 transition-all shrink-0" title="Tambah Tier">
            <i class="fas fa-plus text-xs"></i>
        </button>
    </form>

    <div class="pt-4 flex justify-end">
        <x-button type="button" variant="secondary" onclick="closeModal('harga-jasa-{{ $j->id_jasa }}')">Tutup</x-button>
    </div>
</x-modal>
@endforeach

@push('scripts')
<script>
    $(document).ready(function() {
        $('.select2-filter').select2({ width: '100%' }).on('change', function() {
            $(this).closest('form').submit();
        });
    });
</script>
@endpush

<script>

    function openEditJasa(data) {
        const form = document.getElementById('formEditJasa');
        form.action = `{{ url('/jasa') }}/${data.id_jasa}`;
        document.getElementById('edit_nama_jasa').value = data.nama_jasa;
        document.getElementById('edit_id_mesin').value = data.id_mesin || '';
        document.getElementById('edit_biaya_operasional').value = formatRupiahDariAngka(data.biaya_operasional);
        document.getElementById('edit_biaya_tinta').value = formatRupiahDariAngka(data.biaya_tinta);
        document.getElementById('edit_id_satuan').value = data.id_satuan || '';
        document.getElementById('edit_harga_default').value = data.harga_default ? formatRupiahDariAngka(data.harga_default) : '';
        document.getElementById('edit_keterangan').value = data.keterangan || '';
        toggleModal('jasa-edit');
    }

    // Aksi tier harga (tambah/edit/hapus) dikirim lewat fetch() dengan body
    // JSON - modal tetap terbuka, tabel update di tempat tanpa reload
    // halaman. Body HARUS JSON (bukan FormData/multipart) untuk method
    // PATCH/DELETE - PHP tidak otomatis mem-parse body multipart untuk
    // method selain POST asli, jadi kalau tetap FormData request akan
    // sampai ke Laravel dengan input kosong dan validasi selalu gagal.
    const csrfTokenJasa = document.querySelector('meta[name="csrf-token"]').content;

    function buildJasaTierRowHtml(t) {
        return `
            <div class="tier-row-jasa flex items-center gap-2 bg-white border border-slate-100 rounded-xl p-2" data-id="${t.id_jasa_harga}">
                <form action="{{ url('/jasa-harga') }}/${t.id_jasa_harga}" class="tier-edit-form-jasa flex items-center gap-2 flex-1">
                    <input type="hidden" name="_token" value="${csrfTokenJasa}">
                    <input type="number" name="range_min" value="${t.range_min}" min="1" required class="w-16 text-xs text-center bg-slate-50 border-none rounded-lg py-2">
                    <span class="text-[10px] text-slate-500 font-medium">s/d</span>
                    <input type="number" name="range_max" value="${t.range_max ?? ''}" min="1" placeholder="~" class="w-16 text-xs text-center bg-slate-50 border-none rounded-lg py-2">
                    <span class="text-[10px] text-slate-400">=</span>
                    <span class="text-[10px] text-slate-400">Rp</span>
                    <input type="text" inputmode="numeric" name="harga" value="${formatRupiah(parseInt(t.harga))}" required class="flex-1 text-xs bg-slate-50 border-none rounded-lg py-2 font-medium format-ribuan">
                    <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white transition-all shrink-0" title="Simpan">
                        <i class="fas fa-save text-xs"></i>
                    </button>
                </form>
                <button type="button" onclick="confirmHapusTierJasa(${t.id_jasa_harga})" class="w-8 h-8 flex items-center justify-center rounded-lg text-rose-400 hover:bg-rose-50 transition-all shrink-0" title="Hapus tier ini">
                    <i class="fas fa-trash-alt text-xs"></i>
                </button>
            </div>`;
    }

    // Kolom "Harga" di TABEL UTAMA (di belakang modal) dihitung ulang dari
    // baris-baris yang SEDANG ada di modal - dipanggil tiap kali tambah/
    // edit/hapus tier sukses, supaya begitu modal ditutup tabel utama sudah
    // akurat tanpa perlu reload halaman.
    function refreshJasaSummary(idJasa) {
        const cell = document.getElementById('jasa-harga-summary-' + idJasa);
        const container = document.getElementById('tier-container-jasa-' + idJasa);
        if (!cell || !container) return;

        const tierCount = container.querySelectorAll('.tier-row-jasa').length;
        const hargaDefault = container.dataset.hargaDefault;

        if (tierCount > 0) {
            cell.innerHTML = `<span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-600 border border-slate-200">BERTINGKAT (${tierCount})</span>`;
        } else if (hargaDefault) {
            cell.innerHTML = `<span class="text-sm font-medium text-slate-700">Rp ${new Intl.NumberFormat('id-ID').format(hargaDefault)}</span>`;
        } else {
            cell.innerHTML = '<span class="text-[10px] text-slate-500 italic">Manual di Kasir</span>';
        }
    }

    async function submitJasaTierFormAjax(form, method) {
        const formData = new FormData(form);
        const payload = {};
        formData.forEach((v, k) => { if (k !== '_token') payload[k] = v; });
        try {
            const res = await fetch(form.action, {
                method,
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfTokenJasa },
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
        if (e.target.matches('.tier-add-form-jasa')) {
            e.preventDefault();
            const form = e.target;
            // Pagar klik ganda. Form ini dikirim lewat fetch() JSON jadi tidak
            // pernah membawa submit_id dan tidak terlindungi SubmitSekaliJalan.
            // Pagar sesungguhnya ada di server (CekRentangHarga menolak rentang
            // bertabrakan); yang ini supaya operator tidak mengirim dua kali dan
            // tidak melihat pesan "rentang tumpang tindih" untuk kliknya sendiri.
            const tombol = form.querySelector('button[type="submit"]');
            if (tombol) { tombol.disabled = true; tombol.classList.add('opacity-60'); }
            const data = await submitJasaTierFormAjax(form, 'POST');
            // Tombol hidup lagi baik sukses maupun gagal: form di-reset setelah
            // sukses supaya operator bisa menambah tier lagi.
            if (tombol) { tombol.disabled = false; tombol.classList.remove('opacity-60'); }
            if (!data) return;
            const idJasa = form.dataset.idJasa;
            const container = document.getElementById('tier-container-jasa-' + idJasa);
            container.querySelector('.tier-empty-msg-jasa').hidden = true;
            container.insertAdjacentHTML('beforeend', buildJasaTierRowHtml(data.row));
            form.reset();
            refreshJasaSummary(idJasa);
            Toast.fire({ icon: 'success', title: data.message });
        } else if (e.target.matches('.tier-edit-form-jasa')) {
            e.preventDefault();
            const data = await submitJasaTierFormAjax(e.target, 'PATCH');
            if (!data) return;
            Toast.fire({ icon: 'success', title: data.message });
        }
    });

    function confirmHapusTierJasa(id) {
        Swal.fire({
            title: 'Hapus Tier Harga Ini?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            cancelButtonColor: '#F1F5F1',
            cancelButtonText: '<span style="color: #5D6A85">Batal</span>',
            confirmButtonText: 'Ya, Hapus!'
        }).then(async (result) => {
            if (!result.isConfirmed) return;
            try {
                const res = await fetch(`{{ url('/jasa-harga') }}/${id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfTokenJasa },
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    Swal.fire('Gagal menghapus', data.message || 'Gagal menghapus.', 'error');
                    return;
                }
                const row = document.querySelector(`.tier-row-jasa[data-id="${id}"]`);
                const container = row.closest('[id^="tier-container-jasa-"]');
                const idJasa = container.id.replace('tier-container-jasa-', '');
                row.remove();
                if (container.querySelectorAll('.tier-row-jasa').length === 0) {
                    container.querySelector('.tier-empty-msg-jasa').hidden = false;
                }
                refreshJasaSummary(idJasa);
                Toast.fire({ icon: 'success', title: data.message });
            } catch (e) {
                Swal.fire('Koneksi terputus', 'Terjadi kesalahan koneksi.', 'error');
            }
        });
    }

    function confirmDeleteJasa(id, nama) {
        Swal.fire({
            title: 'Hapus Jasa?',
            html: `Jasa <b>${escHtml(nama)}</b> akan dihapus permanen.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            cancelButtonColor: '#F5F8FF',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'rounded-lg px-6 py-2.5',
                cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById(`delete-form-jasa-${id}`).submit();
            }
        });
    }
</script>
@endsection
