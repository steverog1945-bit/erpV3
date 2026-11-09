@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Detail Transaksi {{ $transaksi->nomor_nota }}" subtitle="Nota tersimpan - koreksi item dilakukan lewat Edit, dan hanya selisihnya yang menyentuh stok & jurnal">
        <x-slot:actions>
            <x-button href="{{ route('berkas-order.show', $transaksi->id_transaksi) }}" variant="outline">
                <i class="fas fa-folder-open"></i> Berkas Order
            </x-button>
            <x-button href="{{ route('penjualan.riwayat') }}" variant="outline">
                <i class="fas fa-arrow-left"></i> Kembali ke Riwayat
            </x-button>
            @if($transaksi->status_nota !== 'Dibatalkan' && \App\Support\Izin::boleh(auth()->user(), [3, 16, 48]))
                <x-button href="{{ route('kasir.print-opsi', $transaksi->id_transaksi) }}" target="_blank" variant="outline">
                    <i class="fas fa-print"></i> Cetak Nota
                </x-button>
            @endif
            @php
                $bisaTambahItem = $transaksi->status_nota === 'Aktif'
                    && in_array($transaksi->status_order, ['Menunggu Kasir', 'Diterima Kasir'], true)
                    && (int) $transaksi->id_user_kasir === (int) auth()->id()
                    && \Carbon\Carbon::parse($transaksi->tgl_order)->toDateString() === now()->toDateString();
                // Syarat edit selisih - sumbernya sama dengan server saat
                // menyimpan (NotaEditService).
                $syaratEdit = \App\Services\NotaEditService::bolehEdit($transaksi);
            @endphp
            @if($syaratEdit['boleh'])
                <x-button href="{{ route('penjualan.create', ['edit' => $transaksi->id_transaksi]) }}" variant="outline">
                    <i class="far fa-edit"></i> Edit Nota
                </x-button>
            @endif
            @if($bisaTambahItem)
                <x-button href="{{ route('penjualan.create', ['susulan' => $transaksi->id_transaksi]) }}" variant="primary">
                    <i class="fas fa-plus"></i> Tambah Order Susulan
                </x-button>
            @endif
            @if($transaksi->status_nota !== 'Dibatalkan')
                <x-button type="button" variant="danger" onclick="batalkanNota()">
                    <i class="fas fa-ban"></i> Batalkan Nota
                </x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Form "Tambah Item Susulan" yang dulu ada di halaman ini sudah
         DIHAPUS. Alasannya: form itu cuma punya 1 item sederhana (produk +
         qty + ukuran + 2 nominal) dan TIDAK punya fitur yang dipakai kasir
         setiap hari - thumbnail, urgency, catatan produksi, desainer,
         finishing, cek stok, dan harga live.

         Sekarang tombol "Tambah Order Susulan" di atas membuka LAYAR INPUT
         PESANAN yang sama (penjualan.create mode susulan), jadi kasir
         memakai form yang sama seperti saat membuat order - dan item
         susulan ikut punya semua fitur itu. Item yang disusun di sana dikirim
         ke endpoint yang sama (POST /penjualan/{id}/tambah-item) dengan
         validasi harga yang identik dengan nota baru. --}}

    @if($transaksi->status_nota === 'Dibatalkan')
        <div class="mb-8 bg-rose-50 border border-rose-200 rounded-2xl p-5 flex items-start gap-4">
            <i class="fas fa-ban text-rose-500 text-xl mt-0.5"></i>
            <div>
                <p class="text-sm font-medium text-rose-700 uppercase tracking-wide">Nota Ini Dibatalkan</p>
                <p class="text-sm text-rose-600 mt-1">
                    Dibatalkan oleh <b>{{ $transaksi->nama_pembatal_nota ?? '-' }}</b>
                    pada {{ $transaksi->tgl_dibatalkan_nota ? \Carbon\Carbon::parse($transaksi->tgl_dibatalkan_nota)->translatedFormat('d M Y, H:i') : '-' }}.
                </p>
                @if($transaksi->alasan_batal_nota)
                    <p class="text-sm text-rose-600 mt-1"><b>Alasan:</b> {{ $transaksi->alasan_batal_nota }}</p>
                @endif
                <p class="text-xs text-rose-400 mt-2">Data nota ini TIDAK dihapus - tetap tersimpan untuk jejak audit, hanya ditandai batal dan dikeluarkan dari antrian produksi aktif.</p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <x-card>
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-3">Konsumen</p>
            <p class="text-lg font-medium text-slate-800">{{ $transaksi->nama_konsumen }}</p>
            <p class="text-sm text-slate-500 mt-1">{{ $transaksi->telepon ?? '-' }}</p>
            <p class="text-xs text-indigo-500 font-medium uppercase mt-2">{{ $transaksi->nama_golongan ?? '-' }}</p>
        </x-card>

        <x-card>
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-3">Info Order</p>
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-medium {{ $transaksi->tipe_order === 'PO' ? 'bg-amber-50 text-amber-700 border border-amber-100' : 'bg-slate-50 text-slate-600 border border-slate-100' }}">
                    {{ $transaksi->tipe_order }}
                </span>
                @if($transaksi->no_po)
                    <span class="text-xs text-slate-500">PO: {{ $transaksi->no_po }}</span>
                @endif
            </div>
            <p class="text-sm text-slate-600">{{ \Carbon\Carbon::parse($transaksi->tgl_order)->translatedFormat('d M Y, H:i') }}</p>
            <p class="text-xs text-slate-400 mt-1">Kasir: {{ $transaksi->nama_kasir ?? '-' }}</p>
        </x-card>

        <x-card>
            <p class="text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-3">Status & Total</p>
            <div class="flex items-center gap-2">
                <x-badge :status="$transaksi->status_pembayaran" />
                @if($transaksi->status_nota === 'Dibatalkan')
                    <x-badge status="Dibatalkan" variant="danger" />
                @endif
            </div>
            @bolehHarga<p class="text-2xl font-medium {{ $transaksi->status_nota === 'Dibatalkan' ? 'text-slate-400 line-through' : 'text-indigo-600' }} mt-2">Rp {{ number_format($transaksi->total_bayar, 0, ',', '.') }}</p>@endbolehHarga
        </x-card>
    </div>

    <x-table>
        <thead>
            <tr>
                <th class="px-6 py-5 text-left w-12">No</th>
                <th class="px-6 py-5 text-left w-16">Gambar</th>
                <th class="px-6 py-5 text-left">Nama File / Item</th>
                <th class="px-6 py-5 text-left">Info Ukuran</th>
                <th class="px-4 py-5 text-center">Qty</th>
                <th class="px-4 py-5 text-center">Urgensi</th>
                @bolehHarga<th class="px-6 py-5 text-right">Biaya Cetak</th>@endbolehHarga
                @bolehHarga<th class="px-6 py-5 text-right">Biaya Finishing</th>@endbolehHarga
                @bolehHarga<th class="px-6 py-5 text-right">Subtotal</th>@endbolehHarga
                <th class="px-6 py-5 text-center">Status SPK</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
            @foreach($items as $i => $item)
            <tr class="hover:bg-slate-50/30 transition-all">
                <td class="px-6 py-4 text-xs text-slate-400">{{ $i + 1 }}</td>
                <td class="px-6 py-4">
                    @if($item->thumbnail)
                        @if(\App\Support\BerkasDesain::bisaDitampilkan($item->thumbnail))
                            <img loading="lazy" decoding="async" src="{{ asset('storage/' . $item->thumbnail) }}" class="w-10 h-10 rounded-lg object-cover border border-slate-100">
                        @else
                            {{-- Berkas desain non-gambar (CDR, AI, EPS, PSD) tidak
                                 bisa ditampilkan peramban - pakai thumbnail
                                 default supaya kotaknya tidak kosong. Nama
                                 file tetap dari berkas asli (kolom Nama File). --}}
                            <img loading="lazy" decoding="async" src="{{ asset('storage/default-thumbnail.png') }}" class="w-10 h-10 rounded-lg object-cover border border-slate-100 opacity-50" title="Berkas desain {{ strtoupper(\App\Support\BerkasDesain::ekstensi($item->thumbnail)) }}">
                        @endif
                    @else
                        <div class="w-10 h-10 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500"><i class="far fa-image"></i></div>
                    @endif
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm font-medium text-slate-700 flex items-center gap-2 flex-wrap">
                        {{ $item->nama_file }}
                        @if(!empty($item->is_susulan))
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-medium uppercase tracking-wide shrink-0" title="Item ini ditambahkan setelah nota dibuat (order susulan)">
                                <i class="fas fa-plus text-[8px]"></i> Susulan
                            </span>
                        @endif
                    </p>
                    <p class="text-xs text-slate-400">{{ $item->nama_produk ?? ($item->nama_jasa ? ' ' . $item->nama_jasa : '-') }}</p>
                    @forelse(($finishingItems[$item->id_detail] ?? collect()) as $fin)
                        <p class="text-[11px] text-indigo-500 font-medium mt-1"> {{ $fin->nama_finishing }} ({{ $fin->status_finishing }}){{ $fin->metode_hitung === 'quantity' && $fin->qty_pakai ? ' x ' . angka((float) $fin->qty_pakai, 2) : '' }}@bolehHarga <span class="text-slate-400 font-medium">&middot; Rp {{ number_format($fin->biaya, 0, ',', '.') }}</span>@endbolehHarga @if($fin->catatan)<span class="text-slate-400 font-medium">&middot; {{ $fin->catatan }}</span>@endif</p>
                    @empty
                        @if($item->nama_finishing)
                        <p class="text-[11px] text-indigo-500 font-medium mt-1"> {{ $item->nama_finishing }} ({{ $item->status_finishing ?? 'Standar' }})</p>
                        @endif
                    @endforelse
                </td>
                <td class="px-6 py-4 text-xs text-slate-500">
                    @if($item->p_ukuran > 0 || $item->l_ukuran > 0)
                        {{ number_format($item->p_ukuran, 0, ',', '.') }} x {{ number_format($item->l_ukuran, 0, ',', '.') }} cm
                    @else
                        -
                    @endif
                </td>
                <td class="px-4 py-4 text-center text-sm font-medium text-slate-700">{{ angka($item->qty, 4) }}</td>
                <td class="px-4 py-4 text-center">
                    <x-badge :status="$item->status_urgensi ?? 'Normal'" :variant="($item->status_urgensi ?? 'Normal') === 'Very Urgent' ? 'danger' : (($item->status_urgensi ?? 'Normal') === 'Urgent' ? 'warning' : 'info')" />
                </td>
                @bolehHarga<td class="px-6 py-4 text-right text-sm text-slate-600">{{ number_format($item->biaya_cetak ?? 0, 0, ',', '.') }}</td>@endbolehHarga
                @bolehHarga<td class="px-6 py-4 text-right text-sm text-slate-600">{{ number_format($item->biaya_finishing ?? 0, 0, ',', '.') }}</td>@endbolehHarga
                @bolehHarga<td class="px-6 py-4 text-right text-sm font-medium text-slate-800">{{ number_format($item->subtotal, 0, ',', '.') }}</td>@endbolehHarga
                <td class="px-6 py-4 text-center">
                    <x-badge :status="$item->status_spk" />
                    @if($item->spk_lengkap && in_array(optional(auth()->user()->role)->nama_role, ['Operator Desain', 'Administrator'], true))
                        <a href="{{ route('spk.desain', $item->id_detail) }}" target="_blank" class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-[11px] font-medium transition-all" title="Cetak SPK lengkap untuk desainer">
                            <i class="fas fa-file-image"></i> SPK Desain
                        </a>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="bg-slate-50/70 border-t-2 border-slate-100">
                @bolehHarga<td colspan="8" class="px-6 py-4 text-right text-[11px] font-medium text-slate-500 uppercase tracking-widest">Total</td>
                <td class="px-6 py-4 text-right text-sm font-medium text-slate-800">{{ number_format($transaksi->total_bayar, 0, ',', '.') }}</td>@endbolehHarga
                <td></td>
            </tr>
        </tfoot>
    </x-table>
</div>

@push('scripts')
<script>
    function batalkanNota() {
        // T3/C5 - JANGAN diam: kalau SweetAlert2 gagal dimuat/berjalan
        // (window.Swal error), tombol Batalkan tidak boleh mati tanpa
        // suara apa pun. Fallback ke dialog bawaan browser supaya alur
        // tetap bisa dilakukan sampai pemicu bawaan diketahui (audit
        // ERR-DBNGBW 2026-11-02).
        function kirimPembatalan(p) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = `{{ route('penjualan.batalkan-nota', $transaksi->id_transaksi) }}`;
            form.innerHTML = `@csrf @method('PATCH')<input type="hidden" name="alasan" value="${p.alasan.replace(/"/g, '&quot;')}"><input type="hidden" name="kembalikan_stok" value="${p.kembalikanStok ? '1' : '0'}">`;
            document.body.appendChild(form);
            form.submit();
        }

        function kirimViaConfirm() {
            var alasan = (window.prompt('Batalkan Nota {{ $transaksi->nomor_nota }}?\n\nAlasan pembatalan (wajib):') || '').trim();
            if (alasan === '') { window.alert('Alasan pembatalan wajib diisi.'); return; }
            var kembali = window.confirm('Kembalikan bahan baku yang sudah dipotong ke stok? OK = ya (material belum dipakai/rusak), Batal = tidak.');
            kirimPembatalan({ alasan: alasan, kembalikanStok: kembali });
        }

        var bisaSwal = false;
        try { bisaSwal = (typeof Swal !== 'undefined') && !!Swal && typeof Swal.fire === 'function'; } catch (e) {}
        if (!bisaSwal) { kirimViaConfirm(); return; }

        try {
            Swal.fire({
                title: 'Batalkan Nota {{ $transaksi->nomor_nota }}?',
                html: `
                    <div class="text-left">
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 mb-3">
                            <p class="text-xs text-amber-700"><b>Ini BUKAN hapus permanen.</b> Nota tetap tersimpan untuk jejak audit (nomor nota, riwayat pembayaran, kartu stok tidak hilang) - hanya ditandai Dibatalkan dan seluruh item SPK di dalamnya ikut dibatalkan supaya tidak ada yang lanjut memproduksinya.</p>
                        </div>
                        <label class="block text-xs font-medium text-slate-500 uppercase mb-1">Alasan pembatalan (wajib diisi)</label>
                        <textarea id="swal-alasan-nota" class="swal2-textarea" style="display:flex" placeholder="Contoh: salah input konsumen, nota duplikat, dsb."></textarea>
                        <label class="flex items-start gap-2 text-sm text-slate-600 mt-2 px-1 cursor-pointer">
                            <input type="checkbox" id="swal-kembalikan-stok-nota" class="mt-0.5 w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer">
                            <span>Kembalikan bahan baku yang sudah dipotong ke stok (untuk semua item di nota ini)<br><small class="text-slate-400">Centang HANYA kalau materialnya belum benar-benar dipakai/rusak.</small></span>
                        </label>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#93481A',
                confirmButtonText: 'Ya, Batalkan Nota',
                cancelButtonText: 'Kembali',
                customClass: { popup: 'rounded-2xl' },
                preConfirm: () => {
                    const alasan = document.getElementById('swal-alasan-nota').value.trim();
                    if (!alasan) {
                        Swal.showValidationMessage('Alasan pembatalan wajib diisi');
                        return false;
                    }
                    return {
                        alasan,
                        kembalikanStok: document.getElementById('swal-kembalikan-stok-nota').checked,
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const { alasan, kembalikanStok } = result.value;
                    kirimPembatalan({ alasan, kembalikanStok });
                }
            }, (e) => {
                // Swal berjalan setengah jalan lalu meledak - jangan diam.
                console.warn('Dialog batal gagal: ' + (e && e.message));
                kirimViaConfirm();
            });
        } catch (e) {
            console.warn('Dialog batal gagal: ' + (e && e.message));
            kirimViaConfirm();
        }
    }
</script>
@endpush
@endsection
