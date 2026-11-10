@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-6 max-w-[1600px] mx-auto">
    <x-page-header title="Master Bahan Baku" subtitle="Manajemen Inventaris & Kontrol Stok Real-time">
        <x-slot:actions>
            <x-button onclick="openTambahModal()">
                <i class="fas fa-plus-circle"></i>
                Registrasi Bahan
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- FILTER & SEARCH --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-8">
        <form method="GET" action="{{ route('bahan.index') }}" id="filterForm" class="mobile-bahan-filter-form flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[300px]">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Cari Bahan Baku</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           class="w-full bg-slate-50 border-none rounded-xl py-2.5 pl-10 pr-4 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 shadow-inner"
                           placeholder="Ketik SKU atau Nama Bahan...">
                </div>
            </div>

            <div class="w-full md:w-48">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2 ml-2">Kategori</label>
                <select name="kategori" class="select2-filter w-full">
                    <option value="">Semua Divisi</option>
                    @foreach($kategori as $kat)
                        <option value="{{ $kat->id_kategori }}" {{ request('kategori') == $kat->id_kategori ? 'selected' : '' }}>{{ $kat->nama_kategori }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full sm:w-36">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5 ml-2">Status</label>
                <select name="status" class="select2-filter w-full">
                    <option value="aktif" {{ request('status', 'aktif') == 'aktif' ? 'selected' : '' }}>Aktif</option>
                    <option value="nonaktif" {{ request('status') == 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                    <option value="semua" {{ request('status') == 'semua' ? 'selected' : '' }}>Semua</option>
                </select>
            </div>

            <div class="w-full sm:w-40">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5 ml-2">Harga (HPP)</label>
                <select name="harga"
                        class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-3 text-sm font-medium text-slate-600 focus:ring-2 focus:ring-indigo-500/20 shadow-inner appearance-none cursor-pointer">
                    <option value="">Semua</option>
                    <option value="kosong" {{ request('harga') == 'kosong' ? 'selected' : '' }}>Belum ada harga</option>
                </select>
            </div>

            <div class="w-full sm:w-44">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-1.5 ml-2">Urutan</label>
                <select name="sort"
                        class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-3 text-sm font-medium text-indigo-600 focus:ring-2 focus:ring-indigo-500/20 shadow-inner appearance-none cursor-pointer">
                    <option value="nama_bahan" {{ request('sort', 'nama_bahan') == 'nama_bahan' ? 'selected' : '' }}>Abjad (A-Z)</option>
                    <option value="stok" {{ request('sort') == 'stok' ? 'selected' : '' }}>Stok Terendah</option>
                    <option value="created_at" {{ request('sort') == 'created_at' ? 'selected' : '' }}>Terbaru</option>
                </select>
            </div>

            <a href="{{ route('bahan.index') }}" class="h-[38px] px-3.5 flex items-center justify-center bg-slate-100 text-slate-400 rounded-xl hover:bg-rose-50 hover:text-rose-500 transition-all shrink-0" title="Reset Filter">
                <i class="fas fa-undo-alt text-sm"></i>
            </a>
        </form>
    </div>

    {{-- TABLE --}}
    <div class="mobile-bahan-records bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <form id="bulk-delete-form" method="POST" action="{{ route('bahan.bulk-delete') }}">
            @csrf
        <x-table bare>
            <thead>
                <tr>
                    <th class="px-4 py-3 w-12 text-center">
                        <input type="checkbox" id="checkAll" class="rounded-md border-slate-200 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer">
                    </th>
                    <th class="px-3 py-3 text-center w-10">#</th>
                    <th class="px-6 py-3 text-left">Identitas Material</th>
                    <th class="px-4 py-3 text-center">Spesifikasi</th>
                    <th class="px-4 py-3 text-center">Volume Stok</th>
                    <th class="px-4 py-3 text-center">Harga Rata-rata</th>
                    <th class="px-4 py-3 text-center" title="Harga satuan pada faktur pembelian terbaru bahan ini">Harga Beli Terakhir</th>
                    <th class="px-4 py-3 text-center" title="Selisih harga beli terakhir terhadap harga rata-rata: naik = merah (biaya bahan naik), turun = hijau">Selisih</th>
                    <th class="px-6 py-3 text-right">Manajemen</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($bahan as $i => $b)
                    <tr class="group hover:bg-slate-50/30 transition-all {{ $b->status === 'nonaktif' ? 'opacity-50' : '' }}">
                        <td class="px-4 py-3 text-center" data-label="Pilih">
                            <input type="checkbox" name="ids[]" value="{{ $b->id_bahan }}" class="row-checkbox rounded-md border-slate-200 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer">
                        </td>
                        <td class="px-3 py-3 text-center text-[11px] text-slate-400" data-label="Nomor">{{ $bahan->firstItem() + $i }}</td>
                        <td class="px-6 py-3" data-label="Identitas">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600 font-medium text-xs shrink-0">
                                    {{ substr($b->nama_bahan, 0, 1) }}
                                </div>
                                <div>
                                    <div class="text-[13px] font-medium text-slate-700 group-hover:text-indigo-600 transition-colors">
                                        {{ $b->nama_bahan }}
                                        @if($b->status === 'nonaktif')
                                            <span class="mobile-bahan-desktop-status ml-1 text-[9px] font-medium text-slate-400 bg-slate-100 px-2 py-0.5 rounded uppercase align-middle">Nonaktif</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">{{ $b->kode_bahan }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-5 text-center" data-label="Spesifikasi">
                            <div class="flex flex-col gap-1 items-center">
                                <span class="text-[10px] font-medium text-slate-600 bg-slate-100 px-2.5 py-1 rounded-md uppercase">
                                    {{ $b->mesin->nama_mesin ?? '-' }}
                                </span>
                                <span class="text-[9px] text-slate-400 font-medium uppercase">{{ $b->kategori->nama_kategori ?? 'Umum' }}</span>
                                @if($b->merk || $b->jenisBahan)
                                    <span class="text-[9px] text-indigo-600 font-medium uppercase">
                                        {{ $b->merk->nama_merk ?? '' }}{{ $b->merk && $b->jenisBahan ? ' • ' : '' }}{{ $b->jenisBahan->nama_jenis ?? '' }}
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center" data-label="Stok">
                            <span class="mobile-bahan-status {{ $b->status === 'aktif' ? 'mobile-bahan-status-active' : 'mobile-bahan-status-inactive' }}">
                                <span aria-hidden="true"></span>{{ $b->status === 'aktif' ? 'Aktif' : 'Nonaktif' }}
                            </span>
                            @php
                                $angka = fn ($v, $d = 2) => angka((float) $v, $d);
                                $stokHabis = (float) $b->stok <= (float) $b->stok_minimal;
                                $rasio = (float) $b->stok_minimal > 0 ? min(1, (float) $b->stok / ((float) $b->stok_minimal * 3)) : ((float) $b->stok > 0 ? 1 : 0);
                                $warnaBar = $stokHabis ? 'bg-rose-400' : ($rasio < 0.5 ? 'bg-amber-400' : 'bg-emerald-400');
                            @endphp
                            <div class="inline-flex flex-col items-center gap-1" title="Stok minimal: {{ $angka($b->stok_minimal) }} {{ $b->satuan_stok }}">
                                <div class="flex items-baseline gap-1.5">
                                    <span class="text-[14px] font-medium tabular-nums {{ $stokHabis ? 'text-rose-600' : 'text-slate-700' }}">{{ $angka($b->stok) }}</span>
                                    <span class="text-xs font-medium text-slate-400">{{ $b->satuan_stok }}</span>
                                </div>
                                <div class="w-16 h-1 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full rounded-full {{ $warnaBar }}" style="width: {{ max(4, round($rasio * 100)) }}%"></div>
                                </div>
                                @if($stokHabis)
                                    <span class="text-[9px] font-medium text-rose-500 uppercase tracking-wider">Stok menipis</span>
                                @elseif($b->satuanHitung && $b->id_satuan_stok !== $b->id_satuan_hitung && $b->nilai_konversi > 1)
                                    <span class="text-[10px] font-medium text-slate-400">≈ {{ $angka($b->stok * $b->nilai_konversi, 0) }} {{ $b->satuanHitung->nama_satuan }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center" data-label="Harga Rata-rata">
                            <button type="button" onclick='koreksiHarga({{ $b->id_bahan }}, @json($b->nama_bahan), {{ (float) $b->harga_rata_rata }}, @json($b->satuan_stok), @json(\App\Support\VersiBaris::token($b)))'
                                    class="group/harga inline-flex items-baseline gap-1.5 px-2.5 py-1 rounded-lg transition-colors {{ (float) $b->harga_rata_rata > 0 ? 'hover:bg-slate-100' : 'bg-amber-50 hover:bg-amber-100' }}"
                                    title="Ubah harga rata-rata (dasar HPP)">
                                @if((float) $b->harga_rata_rata > 0)
                                    <span class="text-[12px] font-medium tabular-nums text-slate-700">Rp {{ number_format($b->harga_rata_rata, 0, ',', '.') }}</span>
                                    <span class="text-[11px] font-medium text-slate-400">/ {{ $b->satuan_stok }}</span>
                                    <i class="fas fa-pen text-[9px] text-slate-500 opacity-0 group-hover/harga:opacity-100 transition-opacity self-center"></i>
                                @else
                                    <span class="text-[11px] font-medium text-amber-600"><i class="fas fa-exclamation-triangle mr-1"></i>Belum ada harga</span>
                                @endif
                            </button>
                        </td>
                        @php
                            $bt = $beliTerakhir[$b->id_bahan] ?? null;
                            $rata = (float) $b->harga_rata_rata;
                            $selisihRp = $bt ? (float) $bt->harga - $rata : 0;
                            $selisihPersen = ($bt && $rata > 0) ? $selisihRp / $rata * 100 : null;
                        @endphp
                        <td class="px-4 py-3 text-center" data-label="Harga Beli Terakhir">
                            @if($bt)
                                <span class="text-[12px] font-medium tabular-nums text-slate-700">Rp {{ number_format($bt->harga, 0, ',', '.') }}</span>
                                <span class="block text-[10px] font-medium text-slate-400 mt-0.5" title="Faktur {{ $bt->no_faktur }}">{{ \Carbon\Carbon::parse($bt->tgl_transaksi)->translatedFormat('d M Y') }}</span>
                            @else
                                <span class="text-[11px] font-medium text-slate-500 italic" title="Belum ada faktur pembelian untuk bahan ini">Belum pernah dibeli</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center" data-label="Selisih">
                            @if($selisihPersen === null)
                                <span class="text-slate-500">-</span>
                            @elseif(abs($selisihRp) < 0.5)
                                <span class="text-[11px] font-medium text-slate-400" title="Harga beli terakhir sama dengan harga rata-rata">Sama</span>
                            @else
                                <span class="inline-flex flex-col items-center px-2 py-0.5 rounded-lg {{ $selisihRp > 0 ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600' }}" title="Harga beli terakhir {{ $selisihRp > 0 ? 'lebih mahal' : 'lebih murah' }} Rp {{ number_format(abs($selisihRp), 0, ',', '.') }} dari harga rata-rata">
                                    <span class="text-[11px] font-medium tabular-nums">{{ $selisihRp > 0 ? '▲' : '▼' }} {{ angka(abs($selisihPersen), 1) }}%</span>
                                    <span class="text-[9px] font-medium tabular-nums">{{ $selisihRp > 0 ? '+' : '-' }}Rp {{ number_format(abs($selisihRp), 0, ',', '.') }}</span>
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right" data-label="Aksi">
                            <details class="mobile-bahan-actions">
                                <summary aria-label="Aksi bahan" title="Aksi bahan">
                                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                        <circle cx="12" cy="5" r="1.8"></circle>
                                        <circle cx="12" cy="12" r="1.8"></circle>
                                        <circle cx="12" cy="19" r="1.8"></circle>
                                    </svg>
                                </summary>
                                <div class="mobile-bahan-action-menu flex justify-end gap-1">
                                    <button type="button" onclick='editBahan(@json($b), @json(\App\Support\VersiBaris::token($b)))' class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-amber-500 hover:bg-amber-50 rounded-lg transition-all" aria-label="Ubah" title="Ubah">
                                        <i class="far fa-edit text-xs"></i>
                                    </button>
                                    <button type="button" onclick="toggleStatus('{{ $b->id_bahan }}', '{{ $b->status }}', {{ json_encode($b->nama_bahan) }})"
                                            class="w-8 h-8 flex items-center justify-center text-slate-400 {{ $b->status === 'aktif' ? 'hover:text-slate-600 hover:bg-slate-100' : 'hover:text-emerald-500 hover:bg-emerald-50' }} rounded-lg transition-all"
                                            title="{{ $b->status === 'aktif' ? 'Nonaktifkan' : 'Aktifkan kembali' }}">
                                        <i class="far {{ $b->status === 'aktif' ? 'fa-eye-slash' : 'fa-eye' }} text-xs"></i>
                                    </button>
                                    <button type="button" onclick="singleDelete('{{ $b->id_bahan }}')" class="w-8 h-8 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all" aria-label="Hapus" title="Hapus">
                                        <i class="far fa-trash-alt text-xs"></i>
                                    </button>
                                </div>
                            </details>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="py-20 text-center text-slate-400 font-medium italic">Data tidak ditemukan.</td>
                    </tr>
                    @endforelse
                </tbody>
        </x-table>

        {{-- BULK ACTIONS FOOTER --}}
        <div id="bulk-footer" class="px-8 py-5 border-t border-slate-50 flex justify-start opacity-0 pointer-events-none transition-all duration-300 translate-y-2">
            <button type="button" id="btn-bulk-delete"
                    class="px-6 py-3 bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white rounded-xl text-[11px] font-medium uppercase tracking-widest transition-all flex items-center gap-2 shadow-sm">
                <i class="fas fa-trash-alt"></i> Hapus Terpilih (<span id="count-checked">0</span>)
            </button>
        </div>
        </form>

       {{-- PAGINATION - komponen bersama x-table.pagination (sama dengan
            semua halaman lain), bukan lagi markup Bootstrap-4 sendiri -
            sekaligus dapat fitur "Ke Halaman" untuk lompat langsung ke
            nomor halaman berapa pun tanpa harus klik Next berulang. --}}
       <x-table.pagination :paginator="$bahan" />
    </div>
</div>

{{-- MODAL CRUD --}}
<x-modal id="bahan" title="Registrasi Material" maxWidth="2xl">
    <form id="formBahan" method="POST" class="space-y-5">
        @csrf
        <div id="methodField"></div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <x-form.group label="Nama Bahan" required class="sm:col-span-2" dalam>
                <x-form.input type="text" name="nama_bahan" id="input_nama_bahan" required />
            </x-form.group>
            <x-form.group label="Kode SKU" required dalam>
                <x-form.input type="text" name="kode_bahan" id="input_kode_bahan" required />
            </x-form.group>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-form.group label="Mesin Alokasi" required dalam>
                <x-form.select name="id_mesin" id="input_id_mesin" required>
                    @foreach($mesin as $m)<option value="{{ $m->id_mesin }}">{{ $m->nama_mesin }}</option>@endforeach
                </x-form.select>
            </x-form.group>
            <x-form.group label="Kategori" required dalam>
                <x-form.select name="id_kategori" id="input_id_kategori" required>
                    @foreach($kategori as $k)<option value="{{ $k->id_kategori }}">{{ $k->nama_kategori }}</option>@endforeach
                </x-form.select>
            </x-form.group>
            <x-form.group label="Merk (Opsional)" dalam>
                <x-form.select name="id_merk" id="input_id_merk">
                    <option value="">-- Tanpa Merk --</option>
                    @foreach($merk as $m)<option value="{{ $m->id_merk }}">{{ $m->nama_merk }}</option>@endforeach
                </x-form.select>
            </x-form.group>
            <x-form.group label="Jenis Bahan (Opsional)" dalam>
                <x-form.select name="id_jenis_bahan" id="input_id_jenis_bahan">
                    <option value="">-- Tanpa Jenis --</option>
                    @foreach($jenisBahan as $j)<option value="{{ $j->id_jenis_bahan }}">{{ $j->nama_jenis }}</option>@endforeach
                </x-form.select>
            </x-form.group>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-5 border-t border-slate-100">
            <x-form.group label="Satuan Stok (Beli)" required dalam>
                <x-form.select name="id_satuan_stok" id="input_id_satuan_stok" required>
                    <option value="">Pilih Satuan</option>
                    @foreach($satuan as $s)<option value="{{ $s->id_satuan }}">{{ $s->nama_satuan }}</option>@endforeach
                </x-form.select>
            </x-form.group>
            <x-form.group label="Satuan Hitung (Pakai)" required dalam>
                <x-form.select name="id_satuan_hitung" id="input_id_satuan_hitung" required>
                    <option value="">Pilih Satuan</option>
                    @foreach($satuan as $s)<option value="{{ $s->id_satuan }}">{{ $s->nama_satuan }}</option>@endforeach
                </x-form.select>
            </x-form.group>
        </div>

        <div id="wrapper_dimensi_roll" class="p-4 bg-indigo-50/50 border border-indigo-100 rounded-xl space-y-3">
            <p class="text-[10px] font-medium text-indigo-500 uppercase tracking-widest">
                <i class="fas fa-ruler-combined mr-1"></i> Dimensi Roll (Opsional - bahan roll m²)
            </p>
            <div class="grid grid-cols-2 gap-4">
                <x-form.group label="Lebar Roll (m)" dalam>
                    <x-form.input type="number" step="0.01" name="lebar_roll" id="input_lebar_roll" oninput="hitungNilaiKonversiRoll()" placeholder="Contoh: 1.5" class="!bg-white" />
                </x-form.group>
                <x-form.group label="Panjang Roll (m)" dalam>
                    <x-form.input type="number" step="0.01" name="panjang_roll" id="input_panjang_roll" oninput="hitungNilaiKonversiRoll()" placeholder="Contoh: 50" class="!bg-white" />
                </x-form.group>
            </div>
        </div>

        <x-form.group label="Nilai Konversi (1 Satuan Stok = ? Satuan Hitung)" required help="Contoh: 1 Rim = 500 Lembar, isi 500. Bahan roll terisi otomatis dari dimensi di atas." dalam>
            <x-form.input type="number" step="0.001" name="nilai_konversi" id="input_nilai_konversi" required value="1" />
        </x-form.group>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-5 border-t border-slate-100">
            <x-form.group label="Stok Awal" required dalam>
                <x-form.input type="number" step="0.001" name="stok" id="input_stok" required value="0" class="disabled:opacity-60 disabled:cursor-not-allowed" />
                <p id="stok_locked_hint" class="hidden mt-1.5 ml-2 text-xs text-slate-400">
                    <i class="fas fa-lock mr-0.5"></i> Ubah lewat
                    <a href="{{ route('kartu-stok.index') }}" class="text-indigo-500 font-medium hover:underline" target="_blank">Kartu Stok &rarr; Penyesuaian</a>
                </p>
            </x-form.group>
            <x-form.group label="Stok Minimal" required dalam>
                <x-form.input type="number" step="0.001" name="stok_minimal" id="input_stok_minimal" required value="0" />
            </x-form.group>
            <div id="blok_harga_awal">
                <x-form.group label="Harga Beli / Satuan Stok" help="Opsional. Menjadi harga rata-rata awal (dasar HPP)." dalam>
                    <x-form.input class="format-ribuan-desimal" type="text" inputmode="decimal" autocomplete="off" name="harga_beli" id="input_harga_beli" placeholder="0" />
                </x-form.group>
            </div>
        </div>

        <label id="blok_jurnal" class="flex items-center gap-2 ml-2 text-xs font-medium text-slate-500"
               title="Hilangkan centang bila saldo awal akuntansi akan diisi sekaligus lewat akuntansi:saldo-awal, supaya tidak tercatat dua kali.">
            <input type="hidden" name="catat_jurnal" value="0">
            <input type="checkbox" name="catat_jurnal" value="1" checked class="rounded border-slate-300 text-indigo-600">
            Catat saldo awal persediaan ke akuntansi
        </label>

        <div class="pt-4 flex gap-3">
            <x-button type="button" variant="secondary" onclick="closeModal('bahan')" class="flex-1">Batalkan</x-button>
            <x-button type="submit" class="flex-1">Simpan Data</x-button>
        </div>
    </form>
</x-modal>

@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('.select2-filter').select2({ width: '100%' }); // filter otomatis diterapkan oleh skrip global layout
    });

    function openTambahModal() {
        document.getElementById('modal-bahan-title').innerText = "Registrasi Material Baru";
        document.getElementById('formBahan').action = "{{ route('bahan.store') }}";
        document.getElementById('methodField').innerHTML = '';
        document.getElementById('formBahan').reset();

        // Stok Awal hanya bisa diisi bebas saat REGISTRASI bahan baru (belum
        // ada histori kartu_stok apa pun) - lihat editBahan() untuk kenapa
        // ini dikunci saat mode edit.
        document.getElementById('input_stok').disabled = false;
        document.getElementById('stok_locked_hint').classList.add('hidden');
        document.getElementById('blok_harga_awal').classList.remove('hidden');
        document.getElementById('blok_jurnal').classList.remove('hidden');
        document.getElementById('input_harga_beli').disabled = false;

        openModal('bahan');
    }

    function editBahan(data, versi) {
        document.getElementById('modal-bahan-title').innerText = "Update Spesifikasi Material";
        const form = document.getElementById('formBahan');
        form.action = `{{ url('/bahan-baku') }}/${data.id_bahan}`;
        // _versi = updated_at saat halaman dimuat (optimistic locking) - simpan ditolak bila bahan ini sudah diubah orang lain.
        document.getElementById('methodField').innerHTML = '@method("PUT")<input type="hidden" name="_versi" value="' + (versi || '') + '">';

        document.getElementById('input_nama_bahan').value = data.nama_bahan;
        document.getElementById('input_kode_bahan').value = data.kode_bahan;
        document.getElementById('input_id_mesin').value = data.id_mesin;
        document.getElementById('input_id_kategori').value = data.id_kategori;
        document.getElementById('input_id_satuan_stok').value = data.id_satuan_stok;
        document.getElementById('input_id_satuan_hitung').value = data.id_satuan_hitung;
        document.getElementById('input_nilai_konversi').value = data.nilai_konversi;

        // Stok pada bahan yang SUDAH ada tidak boleh diubah lewat form ini
        // lagi - dulu field ini biasa saja, jadi mengedit nama/kategori bisa
        // ikut diam-diam mengubah angka stok tanpa jejak apa pun di
        // kartu_stok. Field dikunci (disabled -> tidak ikut terkirim saat
        // submit) dan diarahkan ke Kartu Stok > Penyesuaian, satu-satunya
        // jalur resmi yang selalu mewajibkan alasan & mencatat histori.
        document.getElementById('input_stok').value = data.stok;
        document.getElementById('input_stok').disabled = true;
        document.getElementById('stok_locked_hint').classList.remove('hidden');
        // Harga awal hanya untuk registrasi; harga bahan yang sudah ada diubah lewat tombol harga di daftar.
        document.getElementById('blok_harga_awal').classList.add('hidden');
        document.getElementById('blok_jurnal').classList.add('hidden');
        document.getElementById('input_harga_beli').disabled = true;

        document.getElementById('input_stok_minimal').value = data.stok_minimal;
        document.getElementById('input_id_merk').value = data.id_merk || '';
        document.getElementById('input_id_jenis_bahan').value = data.id_jenis_bahan || '';
        document.getElementById('input_lebar_roll').value = data.lebar_roll || '';
        document.getElementById('input_panjang_roll').value = data.panjang_roll || '';

        openModal('bahan');
    }

    // Lebar x Panjang roll (meter) = total m2 per roll = Nilai Konversi
    // untuk bahan yang Satuan Hitung-nya m2. Sama prinsipnya dengan
    // (P*L)/10000 di form order - "hitungan panjang x lebar" diterapkan ke
    // dimensi ROLL bahan baku, bukan cuma ke ukuran cetak pesanan.
    function hitungNilaiKonversiRoll() {
        const lebar = parseFloat(document.getElementById('input_lebar_roll').value) || 0;
        const panjang = parseFloat(document.getElementById('input_panjang_roll').value) || 0;
        if (lebar > 0 && panjang > 0) {
            document.getElementById('input_nilai_konversi').value = (lebar * panjang).toFixed(3);
        }
    }

    // Fungsi Delete tetap sama dengan perbaikan visual sedikit
    function singleDelete(id) {
        Swal.fire({
            title: 'Hapus data?',
            text: "Data yang dihapus tidak dapat dikembalikan.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#2563EB',
            cancelButtonColor: '#FAFBF9',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'rounded-lg px-6 py-2.5',
                cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `{{ url('/bahan-baku') }}/${id}`;
                form.innerHTML = `@csrf @method('DELETE')`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    function toggleStatus(id, currentStatus, namaBahan) {
        const mauNonaktifkan = currentStatus === 'aktif';
        Swal.fire({
            title: mauNonaktifkan ? 'Nonaktifkan bahan ini?' : 'Aktifkan kembali bahan ini?',
            html: mauNonaktifkan
                ? `<b>${escapeHtml(namaBahan)}</b> tidak akan muncul lagi di pilihan Kedatangan Bahan & BOM Produk baru.<br><small class="text-slate-400">Riwayat & stok tidak berubah, dan bisa diaktifkan lagi kapan saja.</small>`
                : `<b>${escapeHtml(namaBahan)}</b> akan muncul lagi di pilihan transaksi baru.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: mauNonaktifkan ? '#4A5B53' : '#10b981',
            cancelButtonColor: '#FAFBF9',
            confirmButtonText: mauNonaktifkan ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'rounded-lg px-6 py-2.5',
                cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `{{ url('/bahan-baku') }}/${id}/toggle-status`;
                form.innerHTML = `@csrf @method('PATCH')`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    function koreksiHarga(id, nama, hargaSekarang, satuan, versi) {
        Swal.fire({
            title: 'Harga rata-rata bahan',
            html: `<div class="text-left text-sm"><b>${escapeHtml(nama)}</b><br><span class="text-slate-400 text-xs">Nilai per ${escapeHtml(satuan)}. Dasar HPP/margin ke depan; tidak mengubah stok dan tidak membuat jurnal.</span></div>
                   <input id="swal-harga" type="text" inputmode="decimal" autocomplete="off" class="swal2-input format-ribuan-desimal" placeholder="Harga per ${escapeHtml(satuan)}" value="${hargaSekarang > 0 ? formatUangDariAngka(hargaSekarang) : ''}">
                   <input id="swal-alasan" type="text" class="swal2-input" placeholder="Alasan (wajib), mis. isi harga awal">`,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Simpan',
            cancelButtonText: 'Batal',
            preConfirm: () => {
                const teksHarga = document.getElementById('swal-harga').value.trim();
                const harga = cleanUangDesimal(teksHarga);
                const alasan = document.getElementById('swal-alasan').value.trim();
                if (teksHarga === '') { Swal.showValidationMessage('Isi harga (boleh 0).'); return false; }
                if (!alasan) { Swal.showValidationMessage('Alasan wajib diisi.'); return false; }
                return { harga, alasan };
            }
        }).then((result) => {
            if (!result.isConfirmed) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `{{ url('/bahan-baku') }}/${id}/harga`;
            form.innerHTML = `@csrf @method('PATCH')`;
            [['harga_rata_rata', result.value.harga], ['alasan', result.value.alasan], ['_versi', versi || '']].forEach(([n, v]) => {
                const i = document.createElement('input');
                i.type = 'hidden'; i.name = n; i.value = v;
                form.appendChild(i);
            });
            document.body.appendChild(form);
            form.submit();
        });
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Bulk Delete
    const checkAll = document.getElementById('checkAll');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const bulkFooter = document.getElementById('bulk-footer');
    const bulkDeleteBtn = document.getElementById('btn-bulk-delete');
    const countCheckedSpan = document.getElementById('count-checked');

    function updateBulkUI() {
        const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
        countCheckedSpan.textContent = checkedCount;

        if (checkedCount > 0) {
            bulkFooter.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-2');
        } else {
            bulkFooter.classList.add('opacity-0', 'pointer-events-none', 'translate-y-2');
        }
    }

    checkAll?.addEventListener('change', function() {
        rowCheckboxes.forEach(cb => cb.checked = this.checked);
        updateBulkUI();
    });

    rowCheckboxes.forEach(cb => cb.addEventListener('change', updateBulkUI));

    bulkDeleteBtn?.addEventListener('click', function() {
        const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
        Swal.fire({
            title: 'Hapus Massal?',
            text: `${checkedCount} bahan terpilih akan dihapus. Bahan yang masih terikat riwayat transaksi otomatis dilewati.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#93481A',
            cancelButtonColor: '#FAFBF9',
            confirmButtonText: 'Ya, Hapus Semua',
            cancelButtonText: 'Batal',
            customClass: {
                confirmButton: 'rounded-lg px-6 py-2.5',
                cancelButton: 'rounded-lg px-6 py-2.5 text-slate-600'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('bulk-delete-form').submit();
            }
        });
    });
</script>
<script>
    // Kartu mobile: satu menu aksi (titik-tiga) yang terbuka pada satu waktu.
    // Tanpa ini, membuka menu di baris lain menumpuk menu-menu yang sudah
    // terbuka sebelumnya (semua terlihat sekaligus). Klik di luar menutup.
    // Sengaja di script tag TERPISAH supaya tetap berjalan walau script
    // utama di atas gagal (mis. jQuery belum termuat).
    (function () {
        function closeAll(except) {
            document.querySelectorAll('details.mobile-bahan-actions[open]').forEach(function (d) {
                if (d !== except) d.open = false;
            });
        }
        document.addEventListener('click', function (e) {
            var summary = e.target.closest('details.mobile-bahan-actions > summary');
            if (summary) {
                closeAll(summary.parentElement);
                return;
            }
            var btn = e.target.closest('details.mobile-bahan-actions button');
            if (btn) {
                var d = btn.closest('details.mobile-bahan-actions');
                if (d) d.open = false;
                return;
            }
            if (!e.target.closest('details.mobile-bahan-actions')) {
                closeAll(null);
            }
        }, true);
    })();
</script>
@endpush