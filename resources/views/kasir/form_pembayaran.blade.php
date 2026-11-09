@extends('layouts.app')

@section('content')
<div class="payment-mobile-shell w-full px-4 sm:px-6 lg:px-8 py-8 max-w-[1500px] mx-auto pb-24">

    <x-page-header title="Input Pembayaran">
        <x-slot:actions>
            <x-button href="{{ route('kasir.index') }}" variant="outline">
                <i class="fas fa-arrow-left"></i> Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

    {{-- Kas belum dibuka: pembayaran dikunci, nota tetap bisa disimpan sebagai antrean --}}
    @if(!$kasAktif)
    <div class="mb-6 flex flex-wrap items-center gap-4 rounded-2xl bg-amber-50 border border-amber-100 px-6 py-4">
        <div class="w-11 h-11 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center shrink-0"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="flex-1 min-w-[200px]">
            <p class="text-sm font-medium text-amber-800">Kas kasir belum dibuka</p>
            <p class="text-xs text-amber-600/90 mt-0.5">Pembayaran belum bisa dicatat sebelum saldo kas hari ini dibuka. Nota tetap bisa disimpan sebagai antrean (tanpa uang masuk).</p>
        </div>
        <x-button href="{{ route('kasir.kelola') }}" variant="warning" class="shrink-0 w-full sm:w-auto"><i class="fas fa-wallet"></i> Buka Kas</x-button>
    </div>
    @endif

    <form action="{{ url('/kasir/proses-bayar') }}" method="POST" id="form-pembayaran">
        @csrf
        <fieldset @if($notaSudahLunas) disabled @endif style="border:0;padding:0;margin:0;min-width:0;">
        <input type="hidden" name="id_transaksi" value="{{ $transaksi->id_transaksi }}">
        {{-- P0-4: anti klik-ganda. Diisi waktu pertama submit; kalau sudah terisi, submit kedua diabaikan (server tetap punya guard sendiri via session). --}}
        <input type="hidden" name="submit_id" value="">

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 items-start">

            {{-- ===================== KOLOM KIRI: nota, barang, metode ===================== --}}
            <div class="xl:col-span-8 space-y-6">

                {{-- Info nota & konsumen --}}
                <div class="flex flex-wrap items-center gap-4 rounded-2xl bg-white border border-slate-100 shadow-[0_8px_30px_rgba(15,23,42,0.04)] px-6 py-5">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0"><i class="fas fa-user text-lg"></i></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-lg font-medium text-slate-800 truncate">{{ $transaksi->nama_konsumen }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">Nota <span class="font-medium text-slate-500">#{{ $transaksi->nomor_nota }}</span> &middot; {{ \Carbon\Carbon::parse($transaksi->tgl_order)->translatedFormat('d M Y, H:i') }}</p>
                    </div>
                    <span class="px-3.5 py-1.5 rounded-full bg-indigo-50 text-indigo-500 text-[11px] font-medium tracking-wide">{{ $transaksi->nama_golongan ?? '-' }}</span>
                </div>

                {{-- Rincian barang: kartu lembut per item --}}
                <div class="rounded-2xl bg-white border border-slate-100 shadow-[0_8px_30px_rgba(15,23,42,0.04)] p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h4 class="text-sm font-medium text-slate-700">Rincian Barang</h4>
                        <span class="text-[11px] text-slate-400">Ubah harga per item lewat ikon pensil</span>
                    </div>

                    <div class="space-y-3">
                        @foreach($items as $i => $item)
                        @php
                            // Harga per satuan (informasi) = Biaya Cetak / qty hitung. Baris jasa/bahan (tanpa Biaya Cetak)
                            // memakai sisa subtotal setelah desain & finishing.
                            $dasarHarga = (float) $item->biaya_cetak > 0
                                ? (float) $item->biaya_cetak
                                : ((float) $item->subtotal + (float) $item->potongan - (float) $item->biaya_desain - (float) $item->biaya_finishing);
                            $hargaSatuan = ($item->qty_hitung > 0) ? $dasarHarga / $item->qty_hitung : 0;
                            $bagianBiaya = collect(['Cetak' => $item->biaya_cetak, 'Desain' => $item->biaya_desain, 'Finishing' => $item->biaya_finishing])
                                ->filter(fn ($v) => $v > 0)
                                ->map(fn ($v, $k) => $k . ' ' . number_format($v, 0, ',', '.'))
                                ->implode(' &bull; ');
                            if ($item->potongan > 0) {
                                $bagianBiaya .= ($bagianBiaya ? ' &bull; ' : '') . '-Rp ' . number_format($item->potongan, 0, ',', '.') . ' Potongan';
                            }
                        @endphp
                        <div class="item-row group flex flex-wrap items-center gap-4 rounded-2xl bg-slate-50/60 hover:bg-slate-50 border border-transparent hover:border-slate-100 px-4 py-3.5 transition-all" data-id="{{ $item->id_detail }}">
                            <span class="w-6 text-xs font-medium text-slate-500">{{ $i + 1 }}</span>
                            @if($item->thumbnail)
                                @if(\App\Support\BerkasDesain::bisaDitampilkan($item->thumbnail))
                                    <img loading="lazy" decoding="async" src="{{ asset('storage/'.$item->thumbnail) }}" class="w-12 h-12 rounded-xl object-cover border border-white shadow-sm shrink-0">
                                @else
                                    {{-- Berkas desain non-gambar (CDR, AI, EPS, PSD)
                                         tidak bisa ditampilkan peramban - pakai
                                         thumbnail default supaya kotaknya tidak
                                         kosong. Nama file tetap dari berkas asli. --}}
                                    <img loading="lazy" decoding="async" src="{{ asset('storage/default-thumbnail.png') }}" class="w-12 h-12 rounded-xl object-cover border border-white shadow-sm shrink-0 opacity-50" title="Berkas desain {{ strtoupper(\App\Support\BerkasDesain::ekstensi($item->thumbnail)) }}">
                                @endif
                            @else
                                <div class="w-12 h-12 rounded-xl bg-white border border-slate-100 shrink-0 flex items-center justify-center text-slate-500"><i class="far fa-image"></i></div>
                            @endif

                            <div class="flex-1 min-w-[180px]">
                                <p class="item-nama text-sm font-medium text-slate-700 truncate">{{ $item->nama_produk ?? $item->nama_jasa ?? $item->nama_file }}</p>
                                @if($item->nama_produk || $item->nama_jasa)
                                    <p class="text-xs text-slate-400 truncate">{{ $item->nama_file }}</p>
                                @endif
                                <p class="disp-biaya-tambahan text-[11px] text-slate-400 mt-1">{!! \App\Support\HtmlBersih::bersihkan($bagianBiaya) !!}</p>
                            </div>

                            <div class="text-right w-28 flex flex-col justify-center h-12">
                                <p class="text-[10px] leading-4 text-slate-500 font-medium uppercase tracking-wide">Harga</p>
                                <p class="disp-harga text-sm leading-5 font-medium text-slate-600">Rp {{ number_format($hargaSatuan, 0, ',', '.') }}</p>
                            </div>

                            <div class="text-center w-24 flex flex-col justify-center h-12">
                                <p class="text-[10px] leading-4 text-slate-500 font-medium uppercase tracking-wide">Qty</p>
                                <p class="text-sm leading-5 font-medium text-slate-600">
                                    @if($item->metode_hitung == 'ukuran')
                                        {{ number_format($item->qty_hitung, 2, ',', '.') }} m²
                                        <span class="block text-[10px] text-slate-500 font-normal">{{ angka($item->qty, 4) }} pcs</span>
                                    @else
                                        {{ angka($item->qty, 4) }} <span class="text-[11px] text-slate-400 font-normal">{{ $item->id_jasa ? 'Jasa' : ($item->id_bahan ? 'Bahan' : 'Pcs') }}</span>
                                    @endif
                                </p>
                            </div>

                            <div class="text-right w-32 flex flex-col justify-center h-12">
                                <p class="text-[10px] leading-4 text-slate-500 font-medium uppercase tracking-wide">Subtotal</p>
                                <p class="disp-subtotal text-base leading-6 font-medium text-slate-800">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</p>
                            </div>

                            <button type="button" onclick="openCustomHarga({{ $item->id_detail }})" class="w-9 h-9 inline-flex items-center justify-center rounded-xl bg-white text-slate-500 hover:text-indigo-500 hover:shadow-sm border border-slate-100 transition-all" title="Ubah harga item ini">
                                <i class="far fa-edit text-xs"></i>
                            </button>

                            {{-- Nilai efektif dikirim lewat hidden input, diedit HANYA lewat modal Custom Harga --}}
                            <input type="hidden" class="inp-harga" name="items[{{ $item->id_detail }}][harga]" value="0">
                            <input type="hidden" class="inp-cetak" name="items[{{ $item->id_detail }}][biaya_cetak]" value="{{ (int) $item->biaya_cetak }}">
                            <input type="hidden" class="inp-desain" name="items[{{ $item->id_detail }}][biaya_desain]" value="{{ (int) $item->biaya_desain }}">
                            <input type="hidden" class="inp-finishing" name="items[{{ $item->id_detail }}][biaya_finishing]" value="{{ (int) $item->biaya_finishing }}">
                            <input type="hidden" class="inp-potongan" name="items[{{ $item->id_detail }}][potongan]" value="{{ (int) $item->potongan }}">
                            <input type="hidden" class="inp-qty" value="{{ number_format($item->qty_hitung, 4, '.', '') }}" data-satuan="{{ $item->metode_hitung == 'ukuran' ? 'm²' : ($item->id_jasa ? 'jasa' : 'pcs') }}">
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Jenis & metode pembayaran --}}
                <div class="rounded-2xl bg-white border border-slate-100 shadow-[0_8px_30px_rgba(15,23,42,0.04)] p-6">
                    @if($saldoDeposit > 0)
                    <div class="mb-5 flex items-center gap-3 rounded-2xl bg-emerald-50/70 border border-emerald-100 px-4 py-3">
                        <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0"><i class="fas fa-wallet text-sm"></i></div>
                        <div class="flex-1">
                            <p class="text-xs font-medium text-emerald-700">Deposit tersedia Rp {{ number_format($saldoDeposit, 0, ',', '.') }}</p>
                            <p class="text-[11px] text-emerald-600/80 mt-0.5">Otomatis diterapkan. Ganti Metode Pembayaran jika ingin pakai Tunai/Transfer.</p>
                        </div>
                    </div>
                    @elseif($depositSudahHabis)
                    <div class="mb-5 flex items-center gap-3 rounded-2xl bg-slate-50 border border-slate-100 px-4 py-3">
                        <div class="w-9 h-9 rounded-xl bg-slate-200/70 text-slate-400 flex items-center justify-center shrink-0"><i class="fas fa-ban text-sm"></i></div>
                        <div class="flex-1">
                            <p class="text-xs font-medium text-slate-500">Deposit sudah habis terpakai</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">Konsumen ini pernah setor deposit, tapi saldonya sudah habis - tidak bisa dipakai lagi sampai ada setoran baru.</p>
                        </div>
                    </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-[11px] font-medium text-slate-400 mb-2.5">Jenis Pembayaran</label>
                            <div class="grid grid-cols-2 md:grid-cols-1 gap-2">
                                <label class="tipe-bayar-option relative flex items-center justify-center gap-2 py-3 rounded-2xl border border-indigo-500 bg-indigo-50 text-indigo-700 font-medium text-xs cursor-pointer transition-all">
                                    <input type="radio" name="tipe_bayar" value="lunas" class="sr-only" checked onchange="onTipeBayarChange()">
                                    <i class="fas fa-check-circle"></i> Lunas
                                </label>
                                <label class="tipe-bayar-option relative flex items-center justify-center gap-2 py-3 rounded-2xl border border-slate-100 text-slate-400 font-medium text-xs cursor-pointer transition-all hover:border-slate-200">
                                    <input type="radio" name="tipe_bayar" value="dp" class="sr-only" onchange="onTipeBayarChange()">
                                    <i class="fas fa-hand-holding-usd"></i> DP
                                </label>
                            </div>
                        </div>

                        <div class="md:col-span-3">
                            <label class="block text-[11px] font-medium text-slate-400 mb-2.5">Metode Pembayaran</label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="metode-bayar-option relative flex items-center justify-center gap-2 py-3 rounded-2xl border border-indigo-500 bg-indigo-50 text-indigo-700 font-medium text-xs cursor-pointer transition-all">
                                    <input type="radio" name="metode_pembayaran" id="metode-tunai" value="Tunai" class="sr-only" checked onchange="onMetodePembayaranChange()">
                                    <i class="fas fa-money-bill-wave"></i> Tunai
                                </label>
                                <label class="metode-bayar-option relative flex items-center justify-center gap-2 py-3 rounded-2xl border border-slate-100 text-slate-400 font-medium text-xs cursor-pointer transition-all hover:border-slate-200">
                                    <input type="radio" name="metode_pembayaran" value="Debit" class="sr-only" onchange="onMetodePembayaranChange()">
                                    <i class="fas fa-credit-card"></i> Debit
                                </label>
                                <label class="metode-bayar-option relative flex items-center justify-center gap-2 py-3 rounded-2xl border border-slate-100 text-slate-400 font-medium text-xs cursor-pointer transition-all hover:border-slate-200">
                                    <input type="radio" name="metode_pembayaran" value="Transfer" class="sr-only" onchange="onMetodePembayaranChange()">
                                    <i class="fas fa-university"></i> Transfer
                                </label>
                                <label class="metode-bayar-option relative flex items-center justify-center gap-2 py-3 rounded-2xl border border-slate-100 text-slate-400 font-medium text-xs cursor-pointer transition-all hover:border-slate-200 {{ $saldoDeposit <= 0 ? 'opacity-40 cursor-not-allowed' : '' }}"
                                    title="{{ $saldoDeposit > 0 ? '' : ($depositSudahHabis ? 'Deposit konsumen ini sudah habis terpakai, tidak bisa dipakai lagi.' : 'Konsumen ini belum pernah setor deposit.') }}">
                                    <input type="radio" name="metode_pembayaran" value="Deposit" class="sr-only" {{ $saldoDeposit <= 0 ? 'disabled' : '' }} onchange="onMetodePembayaranChange()">
                                    <i class="fas fa-wallet"></i> Deposit
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="wrapper-bank" class="hidden mt-5">
                        <label class="block text-[11px] font-medium text-slate-400 mb-2">Rekening Tujuan</label>
                        <select name="id_bank" id="input-bank" class="w-full bg-slate-50 border-none rounded-2xl py-3 px-4 text-sm font-medium text-slate-600 shadow-inner focus:ring-2 focus:ring-indigo-500/20">
                            <option value="">-- Pilih Rekening --</option>
                            @foreach($banks as $b)
                                <option value="{{ $b->id_bank }}">{{ $b->nama_bank }} - {{ $b->nomor_rekening }} ({{ $b->atas_nama }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="wrapper-deposit-info" class="hidden mt-5 rounded-2xl bg-indigo-50/60 border border-indigo-100 px-4 py-3">
                        <p class="text-xs font-medium text-indigo-600">Saldo deposit: Rp {{ number_format($saldoDeposit, 0, ',', '.') }}</p>
                        <p class="text-[11px] text-indigo-600 mt-0.5">Jumlah bayar tidak boleh melebihi saldo ini.</p>
                    </div>

                    <div class="mt-6">
                        {{-- Nota yang sudah lunas: field ini diisi nominal yang
                             BENAR-BENAR sudah diterima, lalu dikunci readonly.
                             Tujuannya bukti pendataan dan pengingat kasir, bukan
                             entri baru - tanpa kuncian ini nominal tersebut
                             bisa terkirim dua kali dan uang nota terpotong
                             seluruhnya. --}}
                        <label id="label-uang-diterima" class="block text-[11px] font-medium text-slate-400 mb-2">
                            {{ $notaSudahLunas ? 'Uang Diterima (sudah lunas)' : 'Uang Diterima' }}
                        </label>
                        <div class="relative">
                            <span id="prefix-uang-diterima" class="absolute left-5 top-1/2 -translate-y-1/2 font-medium text-slate-500 text-lg">Rp</span>
                            <input type="text" id="input-bayar-mask"
                                   @if($notaSudahLunas) readonly data-terkunci="1" title="Nominal yang sudah tercatat - tidak bisa diubah karena nota ini sudah lunas." @endif
                                   class="format-ribuan w-full h-14 pl-14 pr-4 rounded-2xl border border-slate-100 bg-slate-50/60 text-xl font-medium text-indigo-600 focus:bg-white focus:border-indigo-300 focus:ring-4 focus:ring-indigo-50 transition-all read-only:opacity-80 read-only:cursor-not-allowed"
                                   placeholder="0">
                            <input type="hidden" name="jumlah_bayar" id="input-bayar-asli">
                        </div>

                        @if($notaSudahLunas)
                        <p class="mt-2 flex items-start gap-1.5 text-[11px] text-emerald-600">
                            <i class="fas fa-circle-check mt-0.5"></i>
                            <span>Nominal di atas adalah uang yang sudah tercatat untuk nota ini
                                @if($penerimaan->isNotEmpty())
                                    ({{ $penerimaan->pluck('metode')->unique()->join(', ') }}, {{ $penerimaan->count() }} kali bayar)
                                @endif
                                - jadi pengingat, bukan pembayaran baru.</span>
                        </p>
                        @endif

                        <div class="mt-2.5 flex justify-end">
                            <button type="button" id="btn-bayar-pas" onclick="setUangPas()" @if($notaSudahLunas) disabled title="Nota ini sudah lunas" @endif class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-indigo-50 hover:bg-indigo-100 text-indigo-500 text-[11px] font-medium transition-all disabled:opacity-40 disabled:cursor-not-allowed">
                                <i class="fas fa-money-bill-wave"></i> Bayar Sisa Tagihan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===================== KOLOM KANAN: ringkasan & aksi (menempel saat scroll) ===================== --}}
            <div class="xl:col-span-4 xl:sticky xl:top-6 space-y-6">
                <div class="rounded-2xl bg-indigo-50 border border-indigo-100/70 shadow-[0_8px_30px_rgba(79,70,229,0.08)] p-6 relative overflow-hidden">
                    @if($totalTerbayar > 0)
                    <span class="absolute top-4 right-4 px-3 py-1 rounded-full bg-amber-100 text-amber-700 text-[10px] font-medium">Sudah dibayar Rp {{ number_format($totalTerbayar, 0, ',', '.') }}</span>
                    @endif

                    <h4 class="text-sm font-medium text-slate-700 mb-5">Ringkasan Tagihan</h4>

                    <div class="flex justify-between items-center text-sm mb-3">
                        <span class="text-slate-400">Total Nota</span>
                        <span id="total-kotor" class="font-medium text-slate-700">Rp 0</span>
                    </div>

                    <div class="flex justify-between items-center mb-3">
                        <span class="text-sm text-slate-400">Diskon (Nota)</span>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 font-medium text-[11px]">Rp</span>
                            <input type="text" id="input-diskon" name="diskon" value="{{ number_format((float) ($transaksi->diskon ?? 0), 0, ',', '.') }}"
                                class="format-ribuan w-36 h-9 bg-white border border-slate-100 rounded-xl text-right text-sm text-slate-700 font-medium focus:ring-2 focus:ring-indigo-100 focus:border-indigo-300 pl-9">
                        </div>
                    </div>

                    @if($totalTerbayar > 0)
                    <div class="flex justify-between items-center text-sm mb-3 text-emerald-600">
                        <span>Sudah Dibayar</span>
                        <span class="font-medium">- Rp {{ number_format($totalTerbayar, 0, ',', '.') }}</span>
                    </div>
                    <input type="hidden" id="total_terbayar_hidden" value="{{ $totalTerbayar }}">
                    @else
                    <input type="hidden" id="total_terbayar_hidden" value="0">
                    @endif

                    <div class="h-px bg-indigo-100/70 my-4"></div>

                    <div class="flex justify-between items-end">
                        <span class="text-xs font-medium text-indigo-600">{{ $totalTerbayar > 0 ? 'Sisa Tagihan' : 'Grand Total' }}</span>
                        <input type="hidden" id="grand_total_hidden" name="total_akhir" value="0">
                        <span id="display-grand-total" class="text-3xl font-medium text-slate-800 tracking-tight">Rp 0</span>
                    </div>

                    <div class="mt-6 rounded-2xl bg-white/80 border border-white px-4 py-3.5">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-[11px] font-medium text-slate-400">Status</span>
                            <span id="status-label" class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 text-[10px] font-medium">MENUNGGU...</span>
                        </div>
                        <span id="label-selisih" class="block text-[11px] text-slate-400 mt-2">Kalkulasi</span>
                        <div id="display-selisih" class="text-xl font-medium text-slate-700">Rp 0</div>

                        {{-- Metode pembayaran ikut ditampilkan di sini. Keterangan
                             "sudah bayar" tanpa metode tidak bisa dipakai saat
                             rekonsiliasi uang laci, jadi kasir harus bisa melihat
                             uang nota ini masuk lewat Tunai atau Transfer. --}}
                        <div class="flex justify-between items-center mt-3 pt-3 border-t border-indigo-100/60">
                            <span class="text-[11px] font-medium text-slate-400">Metode</span>
                            <span id="metode-label" class="text-[11px] font-medium text-slate-600">-</span>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center gap-2">
                        <input type="checkbox" name="cetak_nota" id="cetak-nota" value="1" class="w-4 h-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <label for="cetak-nota" class="text-[11px] font-medium text-slate-500">Cetak nota setelah bayar</label>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <button type="button" onclick="simpanSaja()" class="flex items-center justify-center gap-2 py-3.5 rounded-2xl bg-white border border-slate-100 hover:border-indigo-200 text-slate-500 hover:text-indigo-600 font-medium text-xs transition-all">
                            <i class="fas fa-inbox"></i> Simpan
                        </button>
                        <button type="submit" id="btn-bayar" @if(!$kasAktif || $notaSudahLunas) disabled title="{{ !$kasAktif ? 'Kas belum dibuka - lihat peringatan di atas' : 'Nota ini sudah lunas - tidak ada sisa yang perlu dibayar' }}" @endif class="flex items-center justify-center gap-2 py-3.5 rounded-2xl bg-indigo-500 hover:bg-indigo-600 text-white font-medium text-xs shadow-sm transition-all disabled:opacity-40 disabled:cursor-not-allowed disabled:shadow-none">
                            <i class="fas fa-cash-register"></i> Bayar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </fieldset>
    </form>

    {{-- MODAL CUSTOM HARGA - satu modal dipakai bergantian untuk semua item
         (diisi ulang lewat JS tiap kali tombol edit satu baris diklik),
         bukan satu modal per item - lebih ringan & konsisten dengan pola
         openEditX(data) yang sudah dipakai di halaman master lain. --}}
    <x-modal id="custom-harga" title="Custom Harga" maxWidth="sm">
        <p id="ch-nama-item" class="text-xs text-slate-400 uppercase font-medium mb-1 -mt-2"></p>
        {{-- Acuan per-unit murni informasi (Biaya Cetak / Qty), dihitung LIVE
             lewat hitungPreviewCustomHarga() - Biaya Cetak sekarang komponen
             UTAMA Subtotal (bukan lagi Harga x Qty ditambah Biaya Cetak
             sebagai ekstra), jadi tidak ada lagi field "Harga" per-unit
             terpisah yang perlu diketik/disimpan sendiri. --}}
        <p id="ch-harga-acuan" class="text-[11px] text-slate-400 mb-5"></p>

        <div class="space-y-4">
            {{-- Biaya Cetak SELALU tampil, defaultnya = subtotal saat item ini
                 pertama dibuat (dipulihkan di openCustomHarga() dari
                 subtotal-potongan-desain-finishing, supaya nilainya
                 konsisten baik untuk item baru maupun item lama sebelum
                 Biaya Cetak jadi komponen utama). --}}
            <div>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2">Biaya Cetak</label>
                <input type="text" id="ch-cetak" class="format-ribuan-modal w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 shadow-inner">
            </div>

            {{-- Biaya Desain/Finishing disembunyikan default, cuma muncul kalau
                 item ini memang sudah punya nilainya (dari nota asli) atau
                 operator sengaja menambahkannya lewat tombol di bawah -
                 selaras dengan penjualan/create.blade.php. --}}
            <div id="ch-wrapper-desain" class="hidden">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2">Biaya Desain</label>
                <input type="text" id="ch-desain" class="format-ribuan-modal w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 shadow-inner">
            </div>
            <div id="ch-wrapper-finishing" class="hidden">
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2">Biaya Finishing</label>
                <input type="text" id="ch-finishing" class="format-ribuan-modal w-full bg-slate-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-slate-700 focus:ring-2 focus:ring-indigo-500/20 shadow-inner">
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1">
                <button type="button" id="ch-btn-tambah-desain" onclick="tampilkanChBiaya('desain')" class="text-indigo-500 hover:text-indigo-700 text-[10px] font-medium uppercase tracking-widest underline underline-offset-4 decoration-dotted">
                    <i class="fas fa-plus-circle"></i> Biaya Desain
                </button>
                <button type="button" id="ch-btn-tambah-finishing" onclick="tampilkanChBiaya('finishing')" class="text-indigo-500 hover:text-indigo-700 text-[10px] font-medium uppercase tracking-widest underline underline-offset-4 decoration-dotted">
                    <i class="fas fa-plus-circle"></i> Biaya Finishing
                </button>
            </div>

            <div>
                <label class="block text-[10px] font-medium text-slate-400 uppercase tracking-widest mb-2">Potongan Tunai (Diskon Item Ini)</label>
                <input type="text" id="ch-potongan" class="format-ribuan-modal w-full bg-rose-50 border-none rounded-xl py-3 px-4 text-sm font-medium text-rose-600 focus:ring-2 focus:ring-rose-500/20 shadow-inner">
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-between">
                <span class="text-[11px] font-medium text-slate-400 uppercase">Total Potongan</span>
                <span id="ch-total-potongan" class="text-sm font-medium text-rose-500">Rp 0</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500 uppercase">Subtotal Baru</span>
                <span id="ch-preview-subtotal" class="text-xl font-medium text-indigo-600">Rp 0</span>
            </div>
        </div>

        {{-- Kembalikan nilai SEMUA field di modal ini ke kondisi saat modal
             dibuka (nota asli sebelum diedit) - berguna kalau operator sudah
             sempat mengubah-ubah Biaya Desain/Finishing/Potongan lalu
             berubah pikiran, tanpa perlu tutup modal & buka ulang. --}}
        <button type="button" onclick="kembalikanNilaiAsliCh()" class="mt-4 w-full flex items-center justify-center gap-2 py-2.5 rounded-xl border-2 border-dashed border-slate-200 text-slate-400 hover:text-indigo-600 hover:border-indigo-300 font-medium text-[11px] uppercase tracking-widest transition-all">
            <i class="fas fa-undo-alt"></i> Kembalikan Nilai Asli
        </button>

        <div class="pt-3 flex gap-3">
            <x-button type="button" variant="secondary" onclick="closeModal('custom-harga')" class="flex-1">Batalkan</x-button>
            <x-button type="button" onclick="simpanCustomHarga()" class="flex-1">Simpan</x-button>
        </div>
    </x-modal>

</div>

<script>
    function formatRupiah(angka) {
        let string = angka.toString().replace(/[^0-9]/g, "");
        return string.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    function cleanRupiah(string) {
        if (!string) return 0;
        return parseFloat(string.toString().replace(/\./g, '')) || 0;
    }

    // Rangkuman Cetak/Desain/Finishing/Potongan jadi satu baris kecil di
    // bawah Deskripsi (bukan kolom sendiri-sendiri) - dipakai ulang setelah
    // Custom Harga disimpan, sama persis pola PHP di server saat render awal
    // (lihat $bagianBiaya di tabel Rincian Barang) supaya konsisten.
    function buildBiayaTambahanText(cetak, desain, finishing, potongan) {
        const bagian = [];
        if (cetak > 0) bagian.push('Cetak ' + formatRupiah(cetak));
        if (desain > 0) bagian.push('Desain ' + formatRupiah(desain));
        if (finishing > 0) bagian.push('Finishing ' + formatRupiah(finishing));
        let teks = bagian.join(' &bull; ');
        if (potongan > 0) teks += (teks ? ' &bull; ' : '') + '-Rp ' + formatRupiah(potongan) + ' Potongan';
        return teks;
    }

    // TRUE begitu kasir mengetik nominal sendiri. Lihat penjelasannya di
    // dalam calculateInvoice().
    let nominalSudahDiisiManual = false;

    function calculateInvoice() {
        let totalKotor = 0;

        // Subtotal = Cetak + Desain + Finishing - Potongan (SAMA PERSIS
        // dengan rumus server di KasirController::prosesBayar()) - TIDAK
        // lagi ada komponen Harga x Qty terpisah. reconcileSemuaBiayaCetak()
        // di DOMContentLoaded sudah memastikan .inp-harga selalu 0 untuk
        // SEMUA baris sebelum fungsi ini pernah dipanggil, jadi rumus di
        // sini dan yang tersimpan di server tidak akan pernah berbeda lagi.
        document.querySelectorAll('.item-row').forEach(row => {
            const subtotal = cleanRupiah(row.querySelector('.inp-cetak').value)
                + cleanRupiah(row.querySelector('.inp-desain').value)
                + cleanRupiah(row.querySelector('.inp-finishing').value)
                - cleanRupiah(row.querySelector('.inp-potongan').value);
            row.querySelector('.disp-subtotal').innerText = 'Rp ' + formatRupiah(Math.max(0, subtotal));
            totalKotor += Math.max(0, subtotal);
        });

        const diskon = cleanRupiah(document.getElementById('input-diskon').value);
        const totalTerbayarSebelumnya = parseFloat(document.getElementById('total_terbayar_hidden').value) || 0;

        let grandTotalNota = totalKotor - diskon;
        let sisaTagihan = Math.round(grandTotalNota - totalTerbayarSebelumnya);

        document.getElementById('total-kotor').innerText = 'Rp ' + formatRupiah(totalKotor);
        document.getElementById('display-grand-total').innerText = 'Rp ' + formatRupiah(sisaTagihan);
        document.getElementById('grand_total_hidden').value = sisaTagihan;

        // Uang Diterima hanya diisi otomatis SEBELUM kasir mengetik nominal sendiri.
        // Sebelumnya baris ini berjalan setiap calculateInvoice() dipanggil,
        // termasuk setiap kali kasir menekan tombol angka - sehingga ketikan
        // apa pun langsung tertimpa kembali dengan sisa tagihan, dan kasir
        // tidak pernah bisa menginput nominal pembayaran yang sebenarnya.
        // Aturan barunya: begitu kasir menyentuh field ini, angkanya miliknya
        // sampai mode pembayaran diganti atau tombol "Bayar Sisa Tagihan" ditekan.
        // NOTA SUDAH LUNAS = tidak ada sisa tagihan DAN uangnya sudah tercatat.
        // Dicek lebih dulu sebelum cabang "belum bayar". Bug lama ada di sini:
        // kolom atas menulis "Sudah dibayar Rp ..." padahal baris paling bawah
        // - yang lebih dulu diuji dengan `bayar <= 0` - menampilkan
        // "BELUM BAYAR". Satu layar jadi memuat dua status yang bertentangan
        // untuk nota yang sama, dan kasir tidak tahu mana yang benar.
        const notaSudahLunas = sisaTagihan <= 0 && totalTerbayarSebelumnya > 0;

        const tipeBayar = document.querySelector('input[name="tipe_bayar"]:checked')?.value;
        if (notaSudahLunas) {
            // Nominal yang sama persis dengan yang sudah tercatat, supaya field ini
            // jadi bukti pendataan dan pengingat kasir, bukan entri pembayaran
            // baru. Input dikunci readonly dan tombol bayar dimatikan supaya
            // nominal ini tidak bisa tanpa sengaja terkirim dua kali.
            nominalSudahDiisiManual = true;
            document.getElementById('input-bayar-mask').value = formatRupiah(totalTerbayarSebelumnya);
        }
        // Nominal TIDAK diisi otomatis lagi untuk nota yang belum lunas:
        // kasir mengetik sendiri atau menekan tombol "Bayar Sisa Tagihan"
        // di bawah. Default 0 supaya tidak terlihat seolah sudah dibayar
        // sebelum nominal benar-benar diisi - status LUNAS yang tertera
        // lebih dulu sebelum nominal diisi adalah yang membingungkan kasir.
        // (Cabang 'lunas' yang sebelumnya mengisi sisaTagihan otomatis
        // dihapus; tombol 'Bayar Sisa Tagihan' tetap tersedia.)

        const inputMask = document.getElementById('input-bayar-mask');
        let bayar = cleanRupiah(inputMask.value);
        document.getElementById('input-bayar-asli').value = bayar;

        let selisih = bayar - sisaTagihan;
        const displaySelisih = document.getElementById('display-selisih');
        const statusLabel = document.getElementById('status-label');
        const labelSelisih = document.getElementById('label-selisih');
        const metodeLabel = document.getElementById('metode-label');

        // Metode pembayaran ikut ditampilkan di bawah status, karena keterangan
        // "sudah bayar" tanpa metode tidak bisa dipakai saat rekonsiliasi laci.
        const metodeTerpakai = [...document.querySelectorAll('[name="metode_pembayaran"]:checked, select[name="metode_pembayaran"]')]
            .map(el => el.value).filter(Boolean);
        if (metodeLabel) {
            metodeLabel.innerText = metodeTerpakai.length ? metodeTerpakai.join(', ') : '-';
        }

        if (notaSudahLunas) {
            statusLabel.innerText = "LUNAS";
            statusLabel.className = "chip chip-success rounded-sm";
            labelSelisih.innerText = "SUDAH DITERIMA";
            displaySelisih.innerText = 'Rp ' + formatRupiah(totalTerbayarSebelumnya);
        } else if (bayar <= 0) {
            statusLabel.innerText = "BELUM BAYAR";
            statusLabel.className = "chip chip-danger rounded-sm";
            labelSelisih.innerText = "SISA PIUTANG";
            displaySelisih.innerText = 'Rp ' + formatRupiah(Math.abs(sisaTagihan));
        } else if (bayar < sisaTagihan) {
            statusLabel.innerText = "PIUTANG / DP";
            statusLabel.className = "chip chip-warning rounded-sm";
            labelSelisih.innerText = "KEKURANGAN";
            displaySelisih.innerText = 'Rp ' + formatRupiah(Math.abs(selisih));
        } else {
            statusLabel.innerText = "LUNAS";
            statusLabel.className = "chip chip-success rounded-sm";
            const metodeDipilih = metodeTerpakai[0];
            labelSelisih.innerText = selisih > 0 && metodeDipilih && metodeDipilih !== 'Tunai' ? "LEBIH (non-tunai tidak ada kembalian)" : "KEMBALIAN";
            displaySelisih.innerText = 'Rp ' + formatRupiah(selisih);
        }

        // "Jenis Pembayaran" (Lunas / DP) ikut nominal yang diketik kasir.
        // Nota yang field-nya terkunci (sudah lunas) tidak disentuh - statusnya
        // sudah pasti dan nominalnya bukan milik kasir.
        if (document.getElementById('input-bayar-mask').dataset.terkunci !== '1') {
            terapkanTipeBayarOtomatis(bayar, sisaTagihan);
        }
    }

    document.addEventListener('input', function (e) {
        if (e.target.classList.contains('format-ribuan')) {
            e.target.value = formatRupiah(e.target.value);
            // Field Uang Diterima: dari sekarang angkanya milik kasir, jadi
            // calculateInvoice() tidak boleh menimpanya dengan sisa tagihan.
            if (e.target.id === 'input-bayar-mask') {
                nominalSudahDiisiManual = true;
            }
            calculateInvoice();
        }
        if (e.target.classList.contains('format-ribuan-modal')) {
            e.target.value = formatRupiah(e.target.value);
            hitungPreviewCustomHarga();
        }
    });

    function setUangPas() {
        // Nota sudah lunas: nominal yang tampil adalah bukti pendataan, bukan
        // tagihan. Tombol ini sengaja tidak boleh mengubahnya - kalau boleh,
        // angka 0 dari sisa tagihan akan menimpa nominal yang sudah tercatat
        // dan membuat kasir mengira nota ini belum dibayar.
        if (document.getElementById('input-bayar-mask').dataset.terkunci === '1') return;

        const sisa = document.getElementById('grand_total_hidden').value;
        // Tombol ini berarti kasir sengaja meminta angka pas, jadi penanda
        // "sudah diisi manual" harus dilepas supaya mode Lunas tetap bisa
        // mengikuti sisa tagihan kalau total berubah lagi.
        nominalSudahDiisiManual = false;
        document.getElementById('input-bayar-mask').value = formatRupiah(Math.max(0, sisa));
        calculateInvoice();
    }

    // "Jenis Pembayaran" (Lunas / DP) IKUTI nominal yang diketik kasir.
    //
    // Dulu pil ini hanya berubah kalau diklik atau kasir menekan "Bayar
    // Sisa Tagihan" - jadi kasir yang mengetik 50.000 dari tagihan 80.000
    // masih melihat pil "Lunas" padahal pembayarannya sebagian, dan
    // kembaliannya tidak terbaca. Sekarang: nominal kurang dari sisa
    // tagihan = DP, nominal cukup atau lebih = Lunas (selisih lebihnya
    // sudah dihitung sebagai KEMBALIAN di calculateInvoice()).
    //
    // Fungsi ini SENGAJA tidak memanggil onTipeBayarChange(): fungsi itu
    // mengisi ulang field Uang Diterima, jadi memanggilnya dari sini akan
    // menimpa angka yang sedang diketik kasir. Yang diubah hanya radio
    // yang terpilih, label pilnya, dan visibilitas tombol.
    function terapkanTipeBayarOtomatis(bayar, sisaTagihan) {
        const radioLunas = document.querySelector('input[name="tipe_bayar"][value="lunas"]');
        const radioDp = document.querySelector('input[name="tipe_bayar"][value="dp"]');
        if (!radioLunas || !radioDp) return;

        // Nominal 0 = belum ada pembayaran, bukan DP - pil dibiarkan
        // seperti yang sedang aktif supaya tidak berkedip bolak-balik
        // saat kasir baru mulai mengetik.
        if (bayar <= 0) return;

        const radio = bayar >= sisaTagihan ? radioLunas : radioDp;
        if (radio.checked) return; // sudah sesuai, jangan sentuh apa pun

        radio.checked = true;

        document.querySelectorAll('.tipe-bayar-option').forEach(label => {
            const isChecked = label.querySelector('input').checked;
            label.classList.toggle('border-indigo-500', isChecked);
            label.classList.toggle('bg-indigo-50', isChecked);
            label.classList.toggle('text-indigo-700', isChecked);
            label.classList.toggle('border-slate-100', !isChecked);
            label.classList.toggle('text-slate-400', !isChecked);
        });

        // Tombol "Bayar Sisa Tagihan" & judul field mengikuti mode baru.
        // Field-nya sendiri tidak disentuh - angka kasir tetap utuh.
        const btnBayarPas = document.getElementById('btn-bayar-pas');
        const labelUangDiterima = document.getElementById('label-uang-diterima');
        if (bayar >= sisaTagihan) {
            btnBayarPas.classList.add('hidden');
            labelUangDiterima.innerText = 'Uang Diterima';
        } else {
            btnBayarPas.classList.remove('hidden');
            labelUangDiterima.innerText = 'Uang Diterima (DP / Sebagian)';
        }
    }

    // Toggle "Jenis Pembayaran" murni bantuan UX - lihat catatan di atas
    // form. Pindah ke "Lunas Penuh" langsung mengisi Uang Diterima =
    // sisa tagihan (dan menyembunyikan tombol "Bayar Sisa Tagihan" karena
    // jadi mubazir); pindah ke "DP/Sebagian" mengosongkan field itu supaya
    // kasir mengetik sendiri berapa yang diterima sekarang.
    function onTipeBayarChange() {
        document.querySelectorAll('.tipe-bayar-option').forEach(label => {
            const isChecked = label.querySelector('input').checked;
            label.classList.toggle('border-indigo-500', isChecked);
            label.classList.toggle('bg-indigo-50', isChecked);
            label.classList.toggle('text-indigo-700', isChecked);
            label.classList.toggle('border-slate-100', !isChecked);
            label.classList.toggle('text-slate-400', !isChecked);
        });

        const tipe = document.querySelector('input[name="tipe_bayar"]:checked').value;
        const btnBayarPas = document.getElementById('btn-bayar-pas');
        const labelUangDiterima = document.getElementById('label-uang-diterima');

        // Nota sudah lunas = field Uang Diterima terkunci dan berisi nominal
        // yang sudah tercatat. Mengubah mode pembayaran TIDAK boleh menyentuh
        // isian itu (menjadikan "0" dan membuat nota yang lunas tampil
        // seolah belum dibayar). Kopas pil, tombol, dan label saja.
        if (document.getElementById('input-bayar-mask').dataset.terkunci === '1') {
            nominalSudahDiisiManual = true;
            btnBayarPas.classList.add('hidden');
            return;
        }

        // Ganti mode = pilihan baru, jadi penanda dilepas dan angka diisi ulang
        // sesuai mode yang dipilih.
        nominalSudahDiisiManual = false;

        if (tipe === 'lunas') {
            btnBayarPas.classList.add('hidden');
            labelUangDiterima.innerText = 'Uang Diterima';
            setUangPas();
        } else {
            btnBayarPas.classList.remove('hidden');
            labelUangDiterima.innerText = 'Uang Diterima (DP / Sebagian)';
            document.getElementById('input-bayar-mask').value = '';
            calculateInvoice();
        }
    }

    const saldoDepositTersedia = {{ (float) $saldoDeposit }};

    function getMetodePembayaran() {
        return document.querySelector('input[name="metode_pembayaran"]:checked')?.value || 'Tunai';
    }

    // Sinkron gaya pill button "Metode Pembayaran" dengan pill "Jenis
    // Pembayaran" di atasnya - sama-sama radio yang disamarkan (sr-only),
    // tampilannya digambar ulang lewat class toggle di label pembungkusnya.
    function onMetodePembayaranChange() {
        setTimeout(calculateInvoice, 0); // label kembalian/lebih mengikuti metode
        document.querySelectorAll('.metode-bayar-option').forEach(label => {
            const input = label.querySelector('input');
            const isChecked = input.checked;
            label.classList.toggle('border-indigo-500', isChecked);
            label.classList.toggle('bg-indigo-50', isChecked);
            label.classList.toggle('text-indigo-700', isChecked);
            label.classList.toggle('border-slate-100', !isChecked && !input.disabled);
            label.classList.toggle('text-slate-400', !isChecked);
        });
        toggleBankField();
    }

    function toggleBankField() {
        const metode = getMetodePembayaran();
        document.getElementById('wrapper-bank').classList.toggle('hidden', metode !== 'Transfer');
        document.getElementById('wrapper-deposit-info').classList.toggle('hidden', metode !== 'Deposit');
    }

    // Kalau konsumen ini punya saldo deposit, otomatis pilih Metode
    // Pembayaran = Deposit & isi Uang Diterima = saldo (dibatasi maksimal
    // sisa tagihan) saat halaman pertama dibuka, supaya kasir tidak perlu
    // ingat menerapkannya manual. HANYA jalan sekali di awal load (bukan
    // tiap kali angka berubah) supaya tidak menimpa input yang sudah
    // diketik sendiri - kasir tetap bebas ganti ke Tunai/Transfer kapan
    // saja (banner di atas cuma informasi, bukan kuncian).
    function autoTerapkanDeposit() {
        if (saldoDepositTersedia <= 0) return;
        // Nota sudah lunas: tidak ada sisa yang perlu dibayar, dan nominal
        // terkunci tidak boleh ditimpa deposit.
        if (document.getElementById('input-bayar-mask').dataset.terkunci === '1') return;
        const sisaTagihan = Math.round(parseFloat(document.getElementById('grand_total_hidden').value) || 0);
        if (sisaTagihan <= 0) return;

        document.getElementById('metode-tunai').checked = false;
        const radioDeposit = document.querySelector('input[name="metode_pembayaran"][value="Deposit"]');
        radioDeposit.checked = true;
        onMetodePembayaranChange();

        const jumlahOtomatis = Math.min(saldoDepositTersedia, sisaTagihan);
        const tipeLunas = jumlahOtomatis >= sisaTagihan;
        document.querySelector(`input[name="tipe_bayar"][value="${tipeLunas ? 'lunas' : 'dp'}"]`).checked = true;
        onTipeBayarChange();

        document.getElementById('input-bayar-mask').value = formatRupiah(jumlahOtomatis);
        calculateInvoice();
    }

    // ---- CUSTOM HARGA MODAL ----
    // Biaya Cetak sekarang komponen UTAMA subtotal (Subtotal = Cetak +
    // Desain + Finishing - Potongan), bukan lagi "Harga per unit x Qty"
    // ditambah Biaya Cetak sebagai ekstra - lihat perubahan formula yang
    // sama di KasirController::prosesBayar() & PenjualanController::store().
    let currentEditingRow = null;
    let chNilaiAsli = null; // snapshot saat modal dibuka, untuk tombol "Kembalikan Nilai Asli"

    function openCustomHarga(idDetail) {
        const row = document.querySelector(`.item-row[data-id="${idDetail}"]`);
        currentEditingRow = row;

        const qty = parseFloat(row.querySelector('.inp-qty').value) || 0;
        const hargaLama = cleanRupiah(row.querySelector('.inp-harga').value);
        let cetak = cleanRupiah(row.querySelector('.inp-cetak').value);
        const desain = cleanRupiah(row.querySelector('.inp-desain').value);
        const finishing = cleanRupiah(row.querySelector('.inp-finishing').value);
        const potongan = cleanRupiah(row.querySelector('.inp-potongan').value);

        // Item LAMA (dibuat sebelum Biaya Cetak jadi komponen utama)
        // biaya_cetak-nya bisa 0 padahal subtotal sudah benar (dulu dihitung
        // dari Harga x Qty terpisah). Dipulihkan di sini dari komponen harga
        // per unit yang masih tersisa, supaya "default sama dengan subtotal"
        // konsisten untuk item lama MAUPUN baru, dan klik Simpan tanpa ubah
        // apa pun tidak diam-diam mengubah subtotal nota yang sudah ada.
        if (cetak <= 0 && hargaLama > 0 && qty > 0) {
            cetak = Math.round(hargaLama * qty);
        }

        chNilaiAsli = { cetak, desain, finishing, potongan };

        // Nama produk diambil lewat kelas .item-nama. Selector lama memakai
    // "td:nth-child(2) .font-medium", padahal baris item di form ini bukan
    // tabel melainkan div flex - tidak ada satu pun elemen <td>. Akibatnya
    // querySelector mengembalikan null, fungsi ini berhenti di tengah jalan,
    // dan tombol "Ubah harga" kelihatan hidup tetapi tidak pernah membuka
// modal apa pun.
    const elNama = row.querySelector('.item-nama');
    document.getElementById('ch-nama-item').innerText = elNama ? elNama.innerText.trim() : 'Item';
        document.getElementById('ch-cetak').value = formatRupiah(cetak);
        document.getElementById('ch-desain').value = formatRupiah(desain);
        document.getElementById('ch-finishing').value = formatRupiah(finishing);
        document.getElementById('ch-potongan').value = formatRupiah(potongan);

        // Desain/Finishing cuma tampil kalau item ini memang sudah punya
        // nilainya - "muncul ketika ada penambahan biaya".
        document.getElementById('ch-wrapper-desain').classList.toggle('hidden', desain <= 0);
        document.getElementById('ch-btn-tambah-desain').classList.toggle('hidden', desain > 0);
        document.getElementById('ch-wrapper-finishing').classList.toggle('hidden', finishing <= 0);
        document.getElementById('ch-btn-tambah-finishing').classList.toggle('hidden', finishing > 0);

        hitungPreviewCustomHarga();
        openModal('custom-harga');
    }

    function tampilkanChBiaya(jenis) {
        document.getElementById(`ch-wrapper-${jenis}`).classList.remove('hidden');
        document.getElementById(`ch-btn-tambah-${jenis}`).classList.add('hidden');
        document.getElementById(`ch-${jenis}`).focus();
    }

    // Mengembalikan keempat field (Cetak/Desain/Finishing/Potongan) ke
    // kondisi PERSIS saat modal ini dibuka (chNilaiAsli) - membatalkan
    // ketikan yang sudah sempat diubah tanpa perlu tutup+buka ulang modal.
    function kembalikanNilaiAsliCh() {
        if (!chNilaiAsli) return;
        document.getElementById('ch-cetak').value = formatRupiah(chNilaiAsli.cetak);
        document.getElementById('ch-desain').value = formatRupiah(chNilaiAsli.desain);
        document.getElementById('ch-finishing').value = formatRupiah(chNilaiAsli.finishing);
        document.getElementById('ch-potongan').value = formatRupiah(chNilaiAsli.potongan);

        document.getElementById('ch-wrapper-desain').classList.toggle('hidden', chNilaiAsli.desain <= 0);
        document.getElementById('ch-btn-tambah-desain').classList.toggle('hidden', chNilaiAsli.desain > 0);
        document.getElementById('ch-wrapper-finishing').classList.toggle('hidden', chNilaiAsli.finishing <= 0);
        document.getElementById('ch-btn-tambah-finishing').classList.toggle('hidden', chNilaiAsli.finishing > 0);

        hitungPreviewCustomHarga();

        Toast.fire({ icon: 'info', title: 'Dikembalikan ke nilai sebelum diedit.' });
    }

    function hitungPreviewCustomHarga() {
        if (!currentEditingRow) return;
        const cetak = cleanRupiah(document.getElementById('ch-cetak').value);
        const desain = cleanRupiah(document.getElementById('ch-desain').value);
        const finishing = cleanRupiah(document.getElementById('ch-finishing').value);
        const potongan = cleanRupiah(document.getElementById('ch-potongan').value);

        const subtotal = Math.max(0, cetak + desain + finishing - potongan);

        // inp-qty menyimpan qty_hitung (m²/pcs) sebagai angka desimal biasa
        // (mis. "2.4500") - dibaca dengan parseFloat(), BUKAN cleanRupiah()
        // yang akan salah membuang titik desimalnya (dianggap pemisah
        // ribuan). Cuma dipakai untuk acuan "≈ Rp X /unit" - informasi saja,
        // tidak lagi bagian dari rumus subtotal.
        const qty = parseFloat(currentEditingRow.querySelector('.inp-qty').value) || 0;
        const satuan = currentEditingRow.querySelector('.inp-qty').dataset.satuan || 'unit';
        const acuan = document.getElementById('ch-harga-acuan');
        acuan.innerText = qty > 0 ? `≈ Rp ${formatRupiah(Math.round(cetak / qty))} / ${satuan}` : '';

        document.getElementById('ch-total-potongan').innerText = 'Rp ' + formatRupiah(potongan);
        document.getElementById('ch-preview-subtotal').innerText = 'Rp ' + formatRupiah(subtotal);
    }

    async function simpanCustomHarga() {
        if (!currentEditingRow) return;
        const row = currentEditingRow;

        const cetak = cleanRupiah(document.getElementById('ch-cetak').value);
        const desain = cleanRupiah(document.getElementById('ch-desain').value);
        const finishing = cleanRupiah(document.getElementById('ch-finishing').value);
        const potongan = cleanRupiah(document.getElementById('ch-potongan').value);

        // Biaya Cetak+Desain+Finishing yang semuanya 0 berarti item ini mau
        // disimpan dengan subtotal Rp 0 - biasanya bukan kesengajaan
        // (Biaya Cetak ke-clear tidak sengaja). Konfirmasi dulu, TIDAK
        // diblokir total (bisa saja memang item ini sengaja mau digratiskan/
        // dibatalkan harganya) - selaras prinsip "warn don't block".
        if (cetak <= 0 && desain <= 0 && finishing <= 0) {
            const konfirmasi = await Swal.fire({
                title: 'Subtotal Item Ini Jadi Rp 0?',
                text: 'Biaya Cetak, Desain, dan Finishing semuanya kosong - subtotal item ini akan tersimpan Rp 0. Lanjutkan?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#93481A',
                confirmButtonText: 'Ya, Simpan Rp 0',
                cancelButtonText: 'Batal, Saya Cek Lagi'
            });
            if (!konfirmasi.isConfirmed) return;
        }

        // "Harga" per-unit terpisah tidak lagi dipakai (Biaya Cetak sudah
        // jadi komponen utama) - diset 0 di baris tabel supaya tidak
        // menampilkan angka lama yang sudah tidak relevan/menyesatkan.
        row.querySelector('.inp-harga').value = 0;
        row.querySelector('.inp-cetak').value = cetak;
        row.querySelector('.inp-desain').value = desain;
        row.querySelector('.inp-finishing').value = finishing;
        row.querySelector('.inp-potongan').value = potongan;

        tampilHargaSatuan(row, cetak);
        // Cetak/Desain/Finishing/Potongan tidak lagi kolom terpisah - dirangkum
        // jadi satu baris kecil di bawah Deskripsi (lihat buildBiayaTambahanText),
        // sama seperti dulu dirender server-side saat halaman pertama dimuat.
        row.querySelector('.disp-biaya-tambahan').innerHTML = buildBiayaTambahanText(cetak, desain, finishing, potongan);

        closeModal('custom-harga');
        currentEditingRow = null;
        calculateInvoice();
    }

    function simpanSaja() {
        const terkunci = document.getElementById('input-bayar-mask').dataset.terkunci === '1';
        // Yang menentukan "ada pembayaran baru atau tidak" adalah hidden
        // input-bayar-asli, BUKAN field yang terlihat. Untuk nota yang sudah
        // lunas, hidden harus 0 supaya nominal yang ditampilkan sebagai bukti
        // pendataan tidak terkirim lagi dan tercatat dua kali - sementara
        // field yang tetap dibiarkan terlihat supaya kasir masih bisa melihat
        // nominal yang sudah masuk beserta methodsnya.
        document.getElementById('input-bayar-asli').value = 0;
        if (!terkunci) {
            document.getElementById('input-bayar-mask').value = 0;
        }
        document.getElementById('form-pembayaran').submit();
    }

    // Validasi ringan di sisi klien supaya kasir langsung tahu kalau input
    // melebihi saldo deposit - validasi SEBENARNYA tetap di server
    // (KasirController::prosesBayar), ini cuma UX, bukan satu-satunya
    // penjaga.
    document.getElementById('form-pembayaran').addEventListener('submit', function (e) {
        const metode = getMetodePembayaran();
        const bayar = cleanRupiah(document.getElementById('input-bayar-mask').value);
        if (metode === 'Deposit' && bayar > saldoDepositTersedia) {
            e.preventDefault();
            Swal.fire({
                title: 'Saldo Deposit Tidak Cukup',
                text: 'Jumlah bayar melebihi saldo deposit yang tersedia (Rp ' + formatRupiah(saldoDepositTersedia) + ').',
                icon: 'error',
                confirmButtonColor: '#006C7B',
            });
        }
    });

    // BUG PENTING (ditemukan dari laporan "harga di rincian barang jadi 0
    // setelah disimpan", termasuk transaksi DP/Deposit): server sekarang
    // menghitung subtotal murni dari Cetak+Desain+Finishing-Potongan (lihat
    // KasirController::prosesBayar()), TAPI hidden input .inp-cetak untuk
    // item LAMA (dibuat sebelum Biaya Cetak jadi komponen utama, biaya_cetak
    // masih 0 di database) baru direkonsiliasi ke nilai yang benar KETIKA
    // modal Custom Harga item itu dibuka (lihat openCustomHarga()). Form ini
    // SELALU mengirim items[][...] untuk SEMUA baris di setiap submit (Bayar
    // maupun Simpan) - kalau kasir tidak sempat membuka Custom Harga untuk
    // setiap item (kasus paling umum: langsung isi nominal bayar & klik
    // Bayar), hidden input yang masih 0 itu ikut terkirim apa adanya, server
    // menyimpannya sebagai subtotal 0 - persis yang dilaporkan.
    //
    // Diperbaiki di akarnya: rekonsiliasi SEMUA baris (bukan cuma yang
    // dibuka lewat modal) sekali di awal, SEBELUM form sempat disubmit sama
    // sekali - supaya hidden input yang terkirim selalu sudah benar apa pun
    // yang kasir lakukan setelahnya.
    // Kolom Harga = Biaya Cetak / qty hitung (informasi saja; nilai yang dikirim tetap dari Cetak/Desain/Finishing).
    function tampilHargaSatuan(row, cetak) {
        const qty = parseFloat(row.querySelector('.inp-qty').value) || 0;
        row.querySelector('.disp-harga').innerText = 'Rp ' + formatRupiah(qty > 0 ? Math.round(cetak / qty) : 0);
    }

    function reconcileSemuaBiayaCetak() {
        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.inp-qty').value) || 0;
            const hargaLama = cleanRupiah(row.querySelector('.inp-harga').value);
            const cetakLama = cleanRupiah(row.querySelector('.inp-cetak').value);

            if (cetakLama <= 0 && hargaLama > 0 && qty > 0) {
                row.querySelector('.inp-cetak').value = Math.round(hargaLama * qty);
                row.querySelector('.inp-harga').value = 0;
                tampilHargaSatuan(row, Math.round(hargaLama * qty));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        reconcileSemuaBiayaCetak();
        calculateInvoice();
        toggleBankField();
        autoTerapkanDeposit();

        // P0-4: cegah submit ganda tombol bayar (dobel pencatatan pembayaran
        // saat jaringan lambat / klik dua kali). Server tetap punya guard
        // session-nya sendiri sebagai lapis kedua.
        var sudahSubmit = false;
        document.getElementById('form-pembayaran').addEventListener('submit', function (e) {
            if (sudahSubmit) {
                e.preventDefault();
                return;
            }
            sudahSubmit = true;
            var f = this;
            f.querySelector('input[name=submit_id]').value = Date.now() + '-' + Math.random().toString(36).slice(2);
            var tombol = f.querySelector('button[type=submit]');
            if (tombol) {
                tombol.disabled = true;
                tombol.dataset.labelAsli = tombol.innerText;
                tombol.innerText = 'Memproses...';
            }
            setTimeout(function () {
                tombol.disabled = false;
                if (tombol.dataset.labelAsli) tombol.innerText = tombol.dataset.labelAsli;
                sudahSubmit = false;
            }, 8000);
        });
    });
</script>
@endsection
