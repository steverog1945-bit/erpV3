@extends('layouts.app')

@section('content')
@php
    $penjahitAktif = $penjahit->where('aktif', true);
    $tarifJersey = $jenis->where('untuk_jersey', true);
    $tarifBiasa = $jenis->where('untuk_jersey', false);
    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
@endphp
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Jasa Jahit Borongan"
                   subtitle="Penjahit, tarif borongan, dan catatan pengeluaran jahit per order">
        <x-slot:actions>
            <x-button variant="secondary" onclick="toggleModal('jahit-buat')">
                <i class="fas fa-plus"></i>
                Tarif Jahit
            </x-button>
            <x-button variant="secondary" onclick="toggleModal('jahit-catat')">
                <i class="fas fa-receipt"></i>
                Catat Jahit Nota
            </x-button>
            <x-button onclick="toggleModal('jahit-jersey')">
                <i class="fas fa-shirt"></i>
                Catat Jahit Jersey
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-lg border border-slate-200 px-4 py-3">
            <p class="text-[11px] font-medium text-slate-400">Pengeluaran Jahit Bulan Ini</p>
            <p class="text-lg font-medium text-slate-900 mt-0.5">{{ $rp($totalBulanIni) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 px-4 py-3">
            <p class="text-[11px] font-medium text-slate-400">Belum Dibayar ke Penjahit</p>
            <p class="text-lg font-medium {{ $totalBelumDibayar > 0 ? 'text-amber-600' : 'text-slate-900' }} mt-0.5">{{ $rp($totalBelumDibayar) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 px-4 py-3">
            <p class="text-[11px] font-medium text-slate-400">Saldo Kas Kecil</p>
            <p class="text-lg font-medium text-slate-900 mt-0.5">{{ $rp(max(0, $saldoKasKecil)) }}</p>
        </div>
        <div class="bg-white rounded-lg border border-slate-200 px-4 py-3">
            <p class="text-[11px] font-medium text-slate-400">Nota Menunggu Jahit</p>
            <p class="text-lg font-medium text-slate-900 mt-0.5">{{ $orderan->count() }}</p>
        </div>
    </div>

    {{-- ---------------------------------------------------------------- PENJAHIT --}}
    <x-card :padding="false" class="mb-6">
        <div class="px-5 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-medium text-slate-900">Penjahit</h3>
                <p class="text-[11px] text-slate-400 mt-0.5">Angka bulan ini dihitung sejak tanggal 1 {{ now()->translatedFormat('F Y') }}.</p>
            </div>
            <x-button size="sm" variant="secondary" onclick="toggleModal('penjahit-buat')">
                <i class="fas fa-user-plus"></i>
                Penjahit Baru
            </x-button>
        </div>
        <x-table bare>
            <thead>
                <tr>
                    <th class="text-left">Nama</th>
                    <th class="text-left">Telepon</th>
                    <th class="text-right">Pcs Bulan Ini</th>
                    <th class="text-right">Upah Bulan Ini</th>
                    <th class="text-right">Belum Dibayar</th>
                    <th class="text-center">Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($penjahit as $p)
                <tr @class(['opacity-60' => ! $p->aktif])>
                    <td>
                        <span class="text-sm font-medium text-slate-700">{{ $p->nama }}</span>
                        @if($p->keterangan)
                            <p class="text-[11px] text-slate-400 mt-0.5">{{ $p->keterangan }}</p>
                        @endif
                    </td>
                    <td class="text-sm text-slate-600">{{ $p->telepon ?: '-' }}</td>
                    <td class="text-right text-sm text-slate-600">{{ angka($p->qty_bulan_ini, 2) }}</td>
                    <td class="text-right text-sm font-medium text-slate-700">{{ $rp($p->total_bulan_ini) }}</td>
                    <td class="text-right text-sm font-medium {{ $p->belum_dibayar > 0 ? 'text-amber-600' : 'text-slate-400' }}">{{ $rp($p->belum_dibayar) }}</td>
                    <td class="text-center text-sm">
                        <span class="{{ $p->aktif ? 'text-emerald-600' : 'text-slate-400' }}">{{ $p->aktif ? 'Aktif' : 'Nonaktif' }}</span>
                    </td>
                    <td class="text-right">
                        <div class="flex items-center justify-end gap-1">
                            @if($p->belum_dibayar > 0 && $bolehBayar)
                                <x-button size="sm" onclick="bukaBayar({{ $p->id_penjahit }})">
                                    <i class="fas fa-money-bill-wave"></i>
                                    Bayar
                                </x-button>
                            @endif
                            <button type="button" class="w-9 h-9 flex items-center justify-center text-slate-400 rounded-lg transition-all hover:text-indigo-500 hover:bg-indigo-50" title="Ubah penjahit"
                                    onclick='ubahPenjahit(@json($p))'>
                                <i class="fas fa-pen text-[11px]"></i>
                            </button>
                            <form action="{{ route('jasa-jahit.penjahit.destroy', $p->id_penjahit) }}" method="POST" class="inline"
                                  data-konfirmasi="{{ $p->jumlah_catatan > 0 ? 'Nonaktifkan ' . $p->nama . '? Penjahit ini sudah punya ' . $p->jumlah_catatan . ' catatan, jadi datanya disimpan dan hanya disembunyikan dari pilihan.' : 'Hapus penjahit ' . $p->nama . '?' }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-9 h-9 flex items-center justify-center text-slate-400 rounded-lg transition-all hover:text-rose-500 hover:bg-rose-50" title="Hapus penjahit">
                                    <i class="fas fa-trash text-[11px]"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <x-empty-state title="Belum ada penjahit"
                                       description="Daftarkan penjahit lebih dulu. Setiap catatan jahit menyebut siapa yang mengerjakan, supaya upah per orang bisa direkap." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    {{-- ---------------------------------------------------------------- KATALOG TARIF --}}
    <x-card :padding="false" class="mb-6">
        <div class="px-5 py-4 border-b border-slate-200">
            <h3 class="text-sm font-medium text-slate-900">Tarif Jahit Borongan</h3>
            <p class="text-[11px] text-slate-400 mt-0.5">
                Tarif jersey dipilih otomatis per baju: lengan dan kerah cocok dulu, lalu kerah saja, lalu lengan saja, terakhir tarif jersey umum.
                Mengubah harga tidak mengubah catatan yang sudah ada.
            </p>
        </div>
        <x-table bare>
            <thead>
                <tr>
                    <th class="text-left">Jenis Jahit</th>
                    <th class="text-left">Berlaku Untuk</th>
                    <th class="text-right">Harga per Satuan</th>
                    <th class="text-center">Terpakai</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($jenis as $j)
                <tr>
                    <td>
                        <span class="text-sm font-medium text-slate-700">{{ $j->nama_jahit }}</span>
                        @if($j->keterangan)
                            <p class="text-[11px] text-slate-400 mt-0.5">{{ $j->keterangan }}</p>
                        @endif
                    </td>
                    <td class="text-sm">
                        @if($j->untuk_jersey)
                            <span class="text-slate-700">Jersey</span>
                            <span class="text-slate-500">
                                @if($j->nama_lengan || $j->nama_kerah)
                                    {{ collect([$j->nama_lengan ? 'lengan ' . strtolower($j->nama_lengan) : null, $j->nama_kerah ? 'kerah ' . $j->nama_kerah : null])->filter()->implode(', ') }}
                                @else
                                    semua model
                                @endif
                            </span>
                        @elseif($j->id_finishing)
                            <span class="text-slate-600">Nota dengan finishing {{ $j->nama_finishing }}</span>
                        @else
                            <span class="text-slate-400">Dipilih manual</span>
                        @endif
                    </td>
                    <td class="text-right text-sm font-medium text-slate-700">
                        {{ $rp($j->harga_borongan) }}<span class="text-[11px] font-normal text-slate-400">/{{ $j->untuk_jersey ? 'pcs' : ($j->nama_satuan ?? 'satuan') }}</span>
                    </td>
                    <td class="text-center text-sm text-slate-600">{{ $j->jumlah_catatan }}× catatan</td>
                    <td class="text-right">
                        <div class="flex items-center justify-end gap-1">
                            <button type="button" class="w-9 h-9 flex items-center justify-center text-slate-400 rounded-lg transition-all hover:text-indigo-500 hover:bg-indigo-50" title="Ubah tarif"
                                    onclick='editJenisJahit(@json($j))'>
                                <i class="fas fa-pen text-[11px]"></i>
                            </button>
                            @if($j->jumlah_catatan == 0)
                                <form action="{{ route('jasa-jahit.destroy', $j->id_jasa_jahit) }}" method="POST" class="inline"
                                      data-konfirmasi="Hapus tarif &quot;{{ $j->nama_jahit }}&quot;?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="w-9 h-9 flex items-center justify-center text-slate-400 rounded-lg transition-all hover:text-rose-500 hover:bg-rose-50" title="Hapus tarif">
                                        <i class="fas fa-trash text-[11px]"></i>
                                    </button>
                                </form>
                            @else
                                {{-- Sudah dipakai: menghapus akan menghilangkan riwayat pengeluaran. --}}
                                <span class="w-9 h-9 flex items-center justify-center text-slate-500" title="Sudah dipakai di catatan, tidak bisa dihapus">
                                    <i class="fas fa-lock text-[11px]"></i>
                                </span>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <x-empty-state title="Belum ada tarif jahit"
                                       description="Contoh untuk jersey: Lengan pendek Rp 5.000, Lengan panjang Rp 7.000, Kerah wangki Rp 8.000." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    {{-- ---------------------------------------------------------------- RIWAYAT --}}
    <x-card :padding="false">
        <div class="px-5 py-4 border-b border-slate-200">
            <h3 class="text-sm font-medium text-slate-900">Riwayat Pengeluaran Jahit</h3>
            <p class="text-[11px] text-slate-400 mt-0.5">100 catatan terbaru. Catatan tidak bisa diubah; kalau salah, hapus lalu catat ulang.</p>
        </div>
        <x-table bare>
            <thead>
                <tr>
                    <th class="text-left">Tanggal</th>
                    <th class="text-left">Penjahit</th>
                    <th class="text-left">Jenis Jahit</th>
                    <th class="text-left">Order</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">Total</th>
                    <th class="text-left">Pembayaran</th>
                    <th class="text-left">Dicatat Oleh</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($riwayat as $r)
                <tr>
                    <td class="text-sm text-slate-600 whitespace-nowrap">{{ \Carbon\Carbon::parse($r->tgl_catat)->translatedFormat('d M Y') }}</td>
                    <td class="text-sm text-slate-700">{{ $r->nama_penjahit ?? '-' }}</td>
                    <td class="text-sm font-medium text-slate-700">{{ $r->nama_jahit }}</td>
                    <td class="text-sm">
                        @if($r->order_number)
                            <a href="{{ route('jersey.orders.show', $r->id_jersey_order) }}" class="text-slate-600 hover:text-indigo-600">{{ $r->order_number }}</a>
                            <p class="text-[11px] text-slate-400">{{ $r->team_name ?: $r->customer_name }}</p>
                        @elseif($r->nomor_nota)
                            <span class="text-slate-600">{{ $r->nomor_nota }}</span>
                            @if($r->nama_file)
                                <p class="text-[11px] text-slate-400">{{ $r->nama_file }}</p>
                            @endif
                        @else
                            <span class="text-slate-400">Tanpa order</span>
                        @endif
                    </td>
                    <td class="text-right text-sm text-slate-600">{{ angka($r->qty, 4) }}</td>
                    <td class="text-right text-sm text-slate-600">{{ $rp($r->harga_snapshot) }}</td>
                    <td class="text-right text-sm font-medium text-slate-700">{{ $rp($r->total) }}</td>
                    <td class="text-sm whitespace-nowrap">
                        @if($r->id_kas_kecil)
                            <span class="text-emerald-600">Sudah dibayar</span>
                            <p class="text-[11px] text-slate-400">KK-{{ $r->id_kas_kecil }}, {{ \Carbon\Carbon::parse($r->dibayar_at)->translatedFormat('d M Y') }}</p>
                        @else
                            <span class="text-amber-600">Belum dibayar</span>
                        @endif
                    </td>
                    <td class="text-sm text-slate-500">{{ $r->username ?? '-' }}</td>
                    <td class="text-right">
                        @if($r->id_kas_kecil)
                            <span class="inline-flex w-9 h-9 items-center justify-center text-slate-500" title="Sudah dibayar. Batalkan pengeluaran KK-{{ $r->id_kas_kecil }} di Kas Kecil bila perlu dikoreksi.">
                                <i class="fas fa-lock text-[11px]"></i>
                            </span>
                        @else
                        <form action="{{ route('jasa-jahit.catat.destroy', $r->id_jasa_jahit_catat) }}" method="POST" class="inline"
                              data-konfirmasi="Hapus catatan ini? Kalau angkanya salah, catat ulang yang benar setelah menghapus.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-9 h-9 flex items-center justify-center text-slate-400 rounded-lg transition-all hover:text-rose-500 hover:bg-rose-50" title="Hapus catatan">
                                <i class="fas fa-trash text-[11px]"></i>
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10">
                        <x-empty-state title="Belum ada pengeluaran jahit"
                                       description="Catat dari tombol Catat Jahit Jersey atau Catat Jahit Nota." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>
</div>

{{-- ================= PENJAHIT ================= --}}
<x-modal id="penjahit-buat" title="Penjahit Baru" icon="fa-user-plus" maxWidth="md">
    <form action="{{ route('jasa-jahit.penjahit.store') }}" method="POST" class="space-y-4">
        @csrf
        <x-form.group label="Nama Penjahit" name="nama" required dalam>
            <x-form.input type="text" name="nama" required maxlength="100" />
        </x-form.group>
        <x-form.group label="Telepon" name="telepon" dalam>
            <x-form.input type="text" name="telepon" inputmode="tel" maxlength="30" />
        </x-form.group>
        <x-form.group label="Keterangan" name="keterangan" dalam>
            <textarea name="keterangan" rows="2" maxlength="1000" class="w-full text-sm font-medium text-slate-700"></textarea>
        </x-form.group>
        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="toggleModal('penjahit-buat')">Batal</x-button>
            <x-button type="submit">Simpan Penjahit</x-button>
        </x-slot:footer>
    </form>
</x-modal>

<x-modal id="penjahit-ubah" title="Ubah Penjahit" icon="fa-pen" maxWidth="md">
    <form action="" method="POST" id="form-ubah-penjahit" class="space-y-4">
        @csrf
        @method('PUT')
        <x-form.group label="Nama Penjahit" name="nama" required dalam>
            <x-form.input type="text" name="nama" id="pj-nama" required maxlength="100" />
        </x-form.group>
        <x-form.group label="Telepon" name="telepon" dalam>
            <x-form.input type="text" name="telepon" id="pj-telepon" inputmode="tel" maxlength="30" />
        </x-form.group>
        <x-form.group label="Keterangan" name="keterangan" dalam>
            <textarea name="keterangan" id="pj-keterangan" rows="2" maxlength="1000" class="w-full text-sm font-medium text-slate-700"></textarea>
        </x-form.group>
        <label class="flex items-center gap-2 text-sm text-slate-600 ml-1">
            <input type="checkbox" name="aktif" value="1" id="pj-aktif" class="rounded border-slate-300 text-indigo-600">
            Aktif, muncul di pilihan penjahit
        </label>
        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="toggleModal('penjahit-ubah')">Batal</x-button>
            <x-button type="submit">Simpan Perubahan</x-button>
        </x-slot:footer>
    </form>
</x-modal>

{{-- ================= TARIF (tambah dan ubah memakai isian yang sama) ================= --}}
@foreach(['buat' => ['Tarif Jahit Baru', route('jasa-jahit.store'), 'POST'], 'ubah' => ['Ubah Tarif Jahit', '', 'PUT']] as $mode => [$judul, $aksi, $metode])
<x-modal id="jahit-{{ $mode }}" :title="$judul" subtitle="Harga borongan per satuan, disepakati dengan penjahit" icon="fa-scissors" maxWidth="md">
    <form action="{{ $aksi }}" method="POST" id="form-{{ $mode }}-jahit" class="space-y-4" data-tarif>
        @csrf
        @if($metode === 'PUT') @method('PUT') @endif

        <label class="flex items-center gap-2 text-sm text-slate-600 ml-1">
            <input type="checkbox" name="untuk_jersey" value="1" id="{{ $mode }}-jersey" class="rounded border-slate-300 text-indigo-600" onchange="aturTarif(this.form)">
            Tarif jahit jersey (dihitung otomatis dari isi order jersey)
        </label>

        <x-form.group label="Nama Jenis Jahit" name="nama_jahit" required dalam>
            <x-form.input type="text" name="nama_jahit" id="{{ $mode }}-nama" required maxlength="100" placeholder="Contoh: Jersey lengan pendek" />
        </x-form.group>

        <x-form.group label="Harga Borongan (Rp)" name="harga_borongan" required dalam>
            <x-form.input type="text" inputmode="numeric" name="harga_borongan" id="{{ $mode }}-harga" required class="format-ribuan" />
        </x-form.group>

        <div class="grid grid-cols-2 gap-4" data-bagian="jersey">
            <x-form.group label="Tipe Lengan" name="jersey_sleeve_type_id" dalam>
                <x-form.select name="jersey_sleeve_type_id" id="{{ $mode }}-lengan">
                    <option value="">Semua lengan</option>
                    @foreach($lengan as $l)
                        <option value="{{ $l->id }}">{{ $l->name }}</option>
                    @endforeach
                </x-form.select>
            </x-form.group>
            <x-form.group label="Tipe Kerah" name="jersey_collar_type_id" dalam>
                <x-form.select name="jersey_collar_type_id" id="{{ $mode }}-kerah">
                    <option value="">Semua kerah</option>
                    @foreach($kerah as $k)
                        <option value="{{ $k->id }}">{{ $k->name }}</option>
                    @endforeach
                </x-form.select>
            </x-form.group>
        </div>

        <div class="grid grid-cols-2 gap-4" data-bagian="nota">
            <x-form.group label="Satuan" name="id_satuan" dalam>
                <x-form.select name="id_satuan" id="{{ $mode }}-satuan">
                    <option value="">-- Pilih Satuan --</option>
                    @foreach($satuan as $s)
                        <option value="{{ $s->id_satuan }}">{{ $s->nama_satuan }}</option>
                    @endforeach
                </x-form.select>
            </x-form.group>
            <x-form.group label="Finishing Penanda" name="id_finishing" dalam>
                <x-form.select name="id_finishing" id="{{ $mode }}-finishing">
                    <option value="">-- Belum Ditautkan --</option>
                    @foreach($finishing as $f)
                        <option value="{{ $f->id_finishing }}">{{ $f->nama_finishing }}</option>
                    @endforeach
                </x-form.select>
            </x-form.group>
        </div>
        <p class="text-[11px] text-slate-400 -mt-2 ml-2" data-bagian="nota">Nota yang memakai finishing ini muncul di Catat Jahit Nota.</p>
        <p class="text-[11px] text-slate-400 -mt-2 ml-2" data-bagian="jersey">Kosongkan lengan dan kerah untuk tarif jersey umum. Satu kombinasi lengan dan kerah hanya boleh punya satu tarif.</p>

        <x-form.group label="Keterangan" name="keterangan" dalam>
            <textarea name="keterangan" id="{{ $mode }}-keterangan" rows="2" maxlength="1000" class="w-full text-sm font-medium text-slate-700"></textarea>
        </x-form.group>

        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="toggleModal('jahit-{{ $mode }}')">Batal</x-button>
            <x-button type="submit">{{ $mode === 'buat' ? 'Simpan Tarif' : 'Simpan Perubahan' }}</x-button>
        </x-slot:footer>
    </form>
</x-modal>
@endforeach

{{-- ================= BAYAR PENJAHIT (Kas Kecil) ================= --}}
<x-modal id="bayar-penjahit" title="Bayar Penjahit" subtitle="Dibayar tunai dari Kas Kecil dan langsung masuk jurnal" icon="fa-money-bill-wave" maxWidth="2xl">
    <form action="{{ route('jasa-jahit.bayar') }}" method="POST" class="space-y-4" id="form-bayar-penjahit" data-konfirmasi="Bayar upah ini dari Kas Kecil? Uang tercatat keluar dan masuk jurnal. Kalau keliru, batalkan lewat pengajuan di Kas Kecil.">
        @csrf
        <input type="hidden" name="id_penjahit" id="bayar-penjahit-id">
        <p class="text-sm text-slate-600">Penjahit: <span class="font-medium text-slate-900" id="bayar-penjahit-nama"></span></p>
        <div class="rounded-lg border border-slate-200 max-h-80 overflow-y-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[11px] text-slate-400">
                        <th class="px-4 py-2 w-8"><input type="checkbox" id="bayar-semua" checked class="rounded border-slate-300 text-indigo-600" onchange="pilihSemuaBayar(this.checked)" aria-label="Pilih semua"></th>
                        <th class="text-left font-medium px-2 py-2">Tanggal</th>
                        <th class="text-left font-medium px-2 py-2">Jenis Jahit</th>
                        <th class="text-left font-medium px-2 py-2">Order</th>
                        <th class="text-right font-medium px-2 py-2">Qty</th>
                        <th class="text-right font-medium px-4 py-2">Total</th>
                    </tr>
                </thead>
                <tbody id="bayar-daftar"></tbody>
            </table>
        </div>
        <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 space-y-1">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-medium text-slate-500">Dibayar sekarang</span>
                <span class="text-base font-medium text-slate-900" id="bayar-total">Rp 0</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-medium text-slate-500">Saldo Kas Kecil</span>
                <span class="text-sm text-slate-600">{{ $rp(max(0, $saldoKasKecil)) }}</span>
            </div>
            <p class="text-[11px] text-rose-500 hidden" id="bayar-kurang">Saldo Kas Kecil tidak cukup. Isi Kas Kecil dulu atau kurangi catatan yang dibayar.</p>
        </div>
        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="toggleModal('bayar-penjahit')">Batal</x-button>
            <x-button type="submit" id="bayar-kirim">Bayar dari Kas Kecil</x-button>
        </x-slot:footer>
    </form>
</x-modal>

{{-- ================= CATAT JAHIT JERSEY ================= --}}
<x-modal id="jahit-jersey" title="Catat Jahit Jersey" subtitle="Jumlah baju dihitung dari isi order, tarif dari daftar tarif jersey" icon="fa-shirt" maxWidth="2xl">
    <form action="{{ route('jasa-jahit.catat-jersey.store') }}" method="POST" class="space-y-4">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-form.group label="Penjahit" name="id_penjahit" required dalam>
                <x-form.select name="id_penjahit" id="jj-penjahit" required>
                    <option value=""></option>
                    @foreach($penjahitAktif as $p)
                        <option value="{{ $p->id_penjahit }}">{{ $p->nama }}</option>
                    @endforeach
                </x-form.select>
            </x-form.group>
            <x-form.group label="Tanggal Catat" name="tgl_catat" required dalam>
                <x-form.input type="date" name="tgl_catat" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required />
            </x-form.group>
        </div>
        <x-form.group label="Order Jersey" name="id_jersey_order" required dalam>
            <x-form.select name="id_jersey_order" id="jj-order" required onchange="muatRincianJersey()">
                <option value=""></option>
                @foreach($orderJersey as $o)
                    <option value="{{ $o->id }}">{{ $o->order_number }} · {{ $o->team_name ?: $o->customer_name }} ({{ $o->status }})</option>
                @endforeach
            </x-form.select>
        </x-form.group>

        <div id="jj-rincian" class="rounded-lg border border-slate-200">
            <p class="px-4 py-6 text-sm text-slate-400 text-center">Pilih order untuk melihat jumlah baju per tarif.</p>
        </div>

        <x-form.group label="Catatan" name="catatan" dalam>
            <textarea name="catatan" rows="2" maxlength="1000" class="w-full text-sm font-medium text-slate-700"></textarea>
        </x-form.group>

        @if($penjahitAktif->isEmpty() || $tarifJersey->isEmpty())
            <p class="text-[11px] text-amber-600 ml-2">
                <i class="fas fa-exclamation-triangle text-[10px]"></i>
                {{ $penjahitAktif->isEmpty() ? 'Belum ada penjahit aktif.' : '' }}
                {{ $tarifJersey->isEmpty() ? 'Belum ada tarif jahit jersey.' : '' }}
                Lengkapi dulu di halaman ini.
            </p>
        @endif

        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="toggleModal('jahit-jersey')">Batal</x-button>
            <x-button type="submit">Catat Jahit</x-button>
        </x-slot:footer>
    </form>
</x-modal>

{{-- ================= CATAT JAHIT NOTA ================= --}}
<x-modal id="jahit-catat" title="Catat Jahit Nota" subtitle="Untuk nota yang finishing-nya ditandai jahit, atau biaya jahit lain" icon="fa-receipt" maxWidth="lg">
    <form action="{{ route('jasa-jahit.catat.store') }}" method="POST" class="space-y-4">
        @csrf
        <x-form.group label="Penjahit" name="id_penjahit" required dalam>
            <x-form.select name="id_penjahit" required>
                <option value=""></option>
                @foreach($penjahitAktif as $p)
                    <option value="{{ $p->id_penjahit }}">{{ $p->nama }}</option>
                @endforeach
            </x-form.select>
        </x-form.group>

        <x-form.group label="Jenis Jahit" name="id_jasa_jahit" required dalam>
            <x-form.select name="id_jasa_jahit" id="catat-jenis" required onchange="isiHargaOtomatis()">
                <option value=""></option>
                @foreach($tarifBiasa as $j)
                    <option value="{{ $j->id_jasa_jahit }}" data-harga="{{ $j->harga_borongan }}">{{ $j->nama_jahit }}</option>
                @endforeach
            </x-form.select>
        </x-form.group>

        <x-form.group label="Nota" name="id_transaksi_detail" dalam>
            <x-form.select name="id_transaksi_detail">
                <option value="">Tanpa nota (biaya jahit lain)</option>
                @foreach($orderan as $o)
                    <option value="{{ $o->id_detail }}">{{ $o->nomor_nota }} · {{ $o->nama_file ?? $o->nama_produk }} ({{ $o->nama_finishing }}, qty {{ angka($o->qty, 2) }})</option>
                @endforeach
            </x-form.select>
        </x-form.group>

        <div class="grid grid-cols-2 gap-4">
            <x-form.group label="Tanggal Catat" name="tgl_catat" required dalam>
                <x-form.input type="date" name="tgl_catat" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required />
            </x-form.group>
            <x-form.group label="Qty" name="qty" required dalam>
                <x-form.input type="number" name="qty" id="catat-qty" value="1" step="0.0001" min="0.0001" required oninput="hitungTotalJahit()" />
            </x-form.group>
        </div>

        <x-form.group label="Harga Satuan (Rp)" name="harga" help="Terisi dari tarif. Ubah kalau harga yang disepakati kali ini berbeda." dalam>
            <x-form.input type="text" inputmode="numeric" name="harga" id="catat-harga" class="format-ribuan" oninput="hitungTotalJahit()" />
        </x-form.group>

        <div class="rounded-lg bg-slate-50 border border-slate-200 px-4 py-3 flex items-center justify-between">
            <span class="text-[11px] font-medium text-slate-500">Total Pengeluaran</span>
            <span class="text-base font-medium text-slate-900" id="catat-total">Rp 0</span>
        </div>

        <x-form.group label="Catatan" name="catatan" dalam>
            <textarea name="catatan" rows="2" maxlength="1000" class="w-full text-sm font-medium text-slate-700"></textarea>
        </x-form.group>

        <x-slot:footer>
            <x-button type="button" variant="secondary" onclick="toggleModal('jahit-catat')">Batal</x-button>
            <x-button type="submit">Catat Pengeluaran</x-button>
        </x-slot:footer>
    </form>
</x-modal>

<script>
    const rupiahJahit = n => 'Rp ' + formatRupiahDariAngka(n);

    // Tarif jersey memakai lengan/kerah; tarif nota memakai satuan/finishing. Bagian yang tidak relevan disembunyikan.
    function aturTarif(form) {
        const jersey = form.querySelector('[name="untuk_jersey"]').checked;
        form.querySelectorAll('[data-bagian="jersey"]').forEach(el => el.classList.toggle('hidden', !jersey));
        form.querySelectorAll('[data-bagian="nota"]').forEach(el => el.classList.toggle('hidden', jersey));
    }
    document.querySelectorAll('form[data-tarif]').forEach(aturTarif);

    function editJenisJahit(j) {
        document.getElementById('ubah-jersey').checked = !!Number(j.untuk_jersey);
        document.getElementById('ubah-nama').value = j.nama_jahit;
        document.getElementById('ubah-harga').value = formatRupiahDariAngka(j.harga_borongan);
        document.getElementById('ubah-lengan').value = j.jersey_sleeve_type_id || '';
        document.getElementById('ubah-kerah').value = j.jersey_collar_type_id || '';
        document.getElementById('ubah-satuan').value = j.id_satuan || '';
        document.getElementById('ubah-finishing').value = j.id_finishing || '';
        document.getElementById('ubah-keterangan').value = j.keterangan || '';
        const form = document.getElementById('form-ubah-jahit');
        form.action = '{{ url('/jasa-jahit') }}/' + j.id_jasa_jahit;
        aturTarif(form);
        toggleModal('jahit-ubah');
    }

    function ubahPenjahit(p) {
        document.getElementById('pj-nama').value = p.nama;
        document.getElementById('pj-telepon').value = p.telepon || '';
        document.getElementById('pj-keterangan').value = p.keterangan || '';
        document.getElementById('pj-aktif').checked = !!Number(p.aktif);
        document.getElementById('form-ubah-penjahit').action = '{{ url('/jasa-jahit/penjahit') }}/' + p.id_penjahit;
        toggleModal('penjahit-ubah');
    }

    function isiHargaOtomatis() {
        const sel = document.getElementById('catat-jenis');
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('catat-harga').value = opt && opt.dataset.harga ? formatRupiahDariAngka(opt.dataset.harga) : '';
        hitungTotalJahit();
    }

    function hitungTotalJahit() {
        const qty = parseFloat(document.getElementById('catat-qty').value) || 0;
        const harga = cleanRupiah(document.getElementById('catat-harga').value);
        document.getElementById('catat-total').textContent = rupiahJahit(qty * harga);
    }

    // ---- Bayar penjahit: daftar catatan yang belum dibayar, semuanya tercentang. Centang dilepas untuk membayar sebagian.
    const dataBelumDibayar = @json($belumDibayar);
    const namaPenjahit = @json($penjahit->pluck('nama', 'id_penjahit'));
    const saldoKasKecil = {{ (float) $saldoKasKecil }};

    function bukaBayar(id) {
        document.getElementById('bayar-penjahit-id').value = id;
        document.getElementById('bayar-penjahit-nama').textContent = namaPenjahit[id] || '';
        document.getElementById('bayar-semua').checked = true;
        document.getElementById('bayar-daftar').innerHTML = (dataBelumDibayar[id] || []).map(c => '<tr class="border-t border-slate-100">'
            + '<td class="px-4 py-2"><input type="checkbox" name="catat[]" value="' + c.id_jasa_jahit_catat + '" data-total="' + c.total + '" checked class="rounded border-slate-300 text-indigo-600" onchange="hitungBayar()"></td>'
            + '<td class="px-2 py-2 text-slate-600 whitespace-nowrap">' + esc(new Date(c.tgl_catat + 'T00:00:00').toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'})) + '</td>'
            + '<td class="px-2 py-2 text-slate-700">' + esc(c.nama_jahit) + '</td>'
            + '<td class="px-2 py-2 text-slate-500">' + esc(c.order_number || c.nomor_nota || 'Tanpa order') + '</td>'
            + '<td class="px-2 py-2 text-right text-slate-600">' + Number(c.qty) + '</td>'
            + '<td class="px-4 py-2 text-right text-slate-700">' + rupiahJahit(c.total) + '</td></tr>').join('');
        hitungBayar();
        toggleModal('bayar-penjahit');
    }

    function pilihSemuaBayar(nyala) {
        document.querySelectorAll('#bayar-daftar input[type=checkbox]').forEach(cb => cb.checked = nyala);
        hitungBayar();
    }

    function hitungBayar() {
        let total = 0;
        document.querySelectorAll('#bayar-daftar input[type=checkbox]:checked').forEach(cb => total += parseFloat(cb.dataset.total) || 0);
        document.getElementById('bayar-total').textContent = rupiahJahit(total);
        const kurang = total > saldoKasKecil;
        document.getElementById('bayar-kurang').classList.toggle('hidden', !kurang);
        const tombol = document.querySelector('button[form="form-bayar-penjahit"][type=submit], #form-bayar-penjahit button[type=submit]');
        if (tombol) tombol.disabled = kurang || total <= 0;
    }

    // ---- Catat jahit jersey: rincian dimuat dari server, qty per tarif bisa dikurangi (mis. baru sebagian selesai).
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

    async function muatRincianJersey() {
        const id = document.getElementById('jj-order').value;
        const kotak = document.getElementById('jj-rincian');
        if (!id) {
            kotak.innerHTML = '<p class="px-4 py-6 text-sm text-slate-400 text-center">Pilih order untuk melihat jumlah baju per tarif.</p>';
            return;
        }
        kotak.innerHTML = '<p class="px-4 py-6 text-sm text-slate-400 text-center">Menghitung...</p>';
        try {
            const res = await fetch('{{ url('/jasa-jahit/jersey') }}/' + id + '/rincian', {headers: {'Accept': 'application/json'}});
            if (!res.ok) throw new Error();
            gambarRincian(await res.json());
        } catch (e) {
            kotak.innerHTML = '<p class="px-4 py-6 text-sm text-rose-500 text-center">Rincian order tidak bisa dimuat. Coba pilih ulang.</p>';
        }
    }

    function gambarRincian(d) {
        const kotak = document.getElementById('jj-rincian');
        let html = '<table class="w-full text-sm"><thead><tr class="text-[11px] text-slate-400">'
            + '<th class="text-left font-medium px-4 py-2">Tarif</th><th class="text-right font-medium px-2 py-2">Baju</th>'
            + '<th class="text-right font-medium px-2 py-2">Sudah dicatat</th><th class="text-right font-medium px-2 py-2 w-24">Dicatat sekarang</th>'
            + '<th class="text-right font-medium px-4 py-2">Subtotal</th></tr></thead><tbody>';
        d.baris.forEach(b => {
            const sisa = Math.max(0, b.qty - b.sudah);
            html += '<tr class="border-t border-slate-100">'
                + '<td class="px-4 py-2 text-slate-700">' + esc(b.nama_jahit) + '<div class="text-[11px] text-slate-400">' + rupiahJahit(b.harga) + '/pcs</div></td>'
                + '<td class="px-2 py-2 text-right text-slate-600">' + b.qty + '</td>'
                + '<td class="px-2 py-2 text-right ' + (b.sudah > 0 ? 'text-amber-600' : 'text-slate-400') + '">' + b.sudah + '</td>'
                + '<td class="px-2 py-2 text-right"><input type="number" min="0" max="' + sisa + '" step="1" value="' + sisa + '" name="qty[' + b.id_jasa_jahit + ']" data-harga="' + b.harga + '"'
                + ' class="w-20 text-right rounded-lg border border-slate-200 bg-[#F5F8FF] px-2 py-1.5 text-sm" oninput="hitungTotalJersey()"' + (sisa === 0 ? ' disabled' : '') + '></td>'
                + '<td class="px-4 py-2 text-right text-slate-700" data-subtotal></td></tr>';
        });
        if (!d.baris.length) {
            html += '<tr><td colspan="5" class="px-4 py-4 text-center text-slate-400">Tidak ada baju yang cocok dengan tarif jersey.</td></tr>';
        }
        html += '</tbody><tfoot><tr class="border-t border-slate-200"><td class="px-4 py-3 text-[11px] font-medium text-slate-500" colspan="4">Total ' + d.total_baju + ' baju di order ini</td>'
            + '<td class="px-4 py-3 text-right font-medium text-slate-900" id="jj-total">Rp 0</td></tr></tfoot></table>';
        if (d.tanpa_tarif.length) {
            html += '<p class="px-4 pb-3 text-[11px] text-amber-600"><i class="fas fa-exclamation-triangle text-[10px]"></i> Belum ada tarif untuk: '
                + d.tanpa_tarif.map(t => esc(t.keterangan) + ' (' + t.qty + ' baju)').join('; ')
                + '. Tambahkan tarif jersey yang cocok atau tarif jersey umum.</p>';
        }
        kotak.innerHTML = html;
        hitungTotalJersey();
    }

    function hitungTotalJersey() {
        let total = 0;
        document.querySelectorAll('#jj-rincian input[data-harga]').forEach(inp => {
            const max = parseInt(inp.max, 10) || 0;
            let q = parseInt(inp.value, 10) || 0;
            if (q > max) { q = max; inp.value = max; }
            const sub = q * parseFloat(inp.dataset.harga);
            total += sub;
            inp.closest('tr').querySelector('[data-subtotal]').textContent = rupiahJahit(sub);
        });
        const el = document.getElementById('jj-total');
        if (el) el.textContent = rupiahJahit(total);
    }
</script>
@endsection
