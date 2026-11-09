{{-- Sebelumnya file ini bare fragment (tanpa @extends) - dulu tidak
     masalah karena cuma dipakai sebagai POST-redirect-back dari modal
     singkat di Kelola Kas (jarang benar-benar dilihat penuh). Sekarang
     jadi SATU-SATUNYA jalur closing (tombol "Closing Kas Sekarang"
     langsung ke sini), jadi perlu sidebar/layout app supaya tidak terasa
     seperti halaman lepas/rusak. --}}
@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1600px] mx-auto">

    <x-page-header title="Closing Alur Kas Kasir" subtitle="Rekap sesi kas sejak {{ $mulaiSesi->translatedFormat('d F Y, H:i') }} sebelum menutup & mengunci sesi.">
        <x-slot:actions>
            <x-button href="{{ route('kasir.kelola') }}" variant="outline">
                <i class="fas fa-arrow-left"></i> Kembali
            </x-button>
        </x-slot:actions>
    </x-page-header>

@if($tutupPaksa)
    <div class="max-w-4xl mx-auto mb-4 flex items-start gap-3 bg-rose-50 border-2 border-rose-200 rounded-2xl px-5 py-4 text-sm font-medium text-rose-700">
        <i class="fas fa-user-shield mt-0.5"></i>
        <span>Tutup paksa: sesi kas ini milik kasir lain. Isi <b>alasan</b> pada kolom keterangan; tindakan ini dicatat di Log Aktivitas.</span>
    </div>
@endif
<div class="max-w-4xl mx-auto p-6 bg-white rounded-2xl shadow-xl">

    {{-- REKAP TRANSAKSI - nota Order yang tgl_order-nya HARI INI, dipecah per
         Mesin/Divisi (lihat KasirController::previewClosing()). "Nota
         Penjualan Barang"/"Piutang Penjualan Barang" di referensi TIDAK
         direplikasi di sini - app ini cuma py satu jenis transaksi
         (transaksi+transaksi_detail mencakup produk & jasa sekaligus),
         tidak ada jalur "penjualan barang" terpisah dari "nota order"
         seperti sistem lama, jadi tidak py padanan data nyata. --}}
    <div class="mb-3">
        <span class="inline-block px-3 py-1 bg-rose-500 text-white text-[10px] font-medium uppercase tracking-widest rounded-lg">Rekap Transaksi</span>
    </div>
    <div class="rounded-2xl border border-slate-200 overflow-hidden mb-6">
        <div class="grid grid-cols-2 bg-emerald-50 border-b border-slate-200">
            <div class="px-5 py-3 text-xs font-medium text-emerald-800 uppercase">Nota Order Cetak</div>
            <div class="px-5 py-3 text-sm font-medium text-slate-700 text-right">Rp {{ rupiah($totalNotaOrder, false) }}</div>
        </div>
        {{-- Semua divisi dari tabel mesin dimunculkan, termasuk yang nol.
             Divisi sepi tidak disembunyikan: absennya order itu sendiri
             informasi, dan daftar yang berubah-ubah tiap hari tidak bisa
             dipakai membandingkan hari ini dengan kemarin. Yang punya
             order diberi latar dan teks penuh; yang kosong tetap terbaca
             tapi turun kelas supaya tidak bersaing dengan angka riil. --}}
        @forelse($rekapKategori as $rk)
        <div class="grid grid-cols-2 px-5 py-2.5 border-b border-slate-100 {{ $rk->ada_order ? 'bg-sky-50/40' : '' }}">
            <div class="text-xs font-medium pl-8 {{ $rk->ada_order ? 'text-slate-600' : 'text-slate-400' }}">
                {{ $rk->kategori }}
                @unless($rk->ada_order)<span class="font-normal">tanpa order</span>@endunless
            </div>
            <div class="text-xs font-medium text-right tabular-nums {{ $rk->ada_order ? 'text-slate-500' : 'text-slate-400' }}">
                Rp {{ rupiah($rk->total, false) }}
            </div>
        </div>
        @empty
        <div class="px-5 py-4 text-xs text-slate-400 italic text-center border-b border-slate-100">
            Tidak ada divisi yang terdaftar di master mesin, jadi rekap per divisi tidak bisa ditampilkan.
        </div>
        @endforelse

        <div class="grid grid-cols-2 bg-rose-50 border-b border-t border-slate-200">
            <div class="px-5 py-3 text-xs font-medium text-rose-700 uppercase">Piutang Nota Order</div>
            <div class="px-5 py-3 text-sm font-medium text-rose-600 text-right">Rp {{ rupiah($piutangNotaOrder, false) }}</div>
        </div>

        <div class="grid grid-cols-2 bg-indigo-600">
            <div class="px-5 py-4 text-xs font-medium text-white uppercase tracking-widest">Total Pendapatan</div>
            <div class="px-5 py-4 text-base font-medium text-white text-right">Rp {{ rupiah($totalPendapatan, false) }}</div>
        </div>
    </div>

    {{-- REKAP PEMBAYARAN - seluruh uang yang DITERIMA hari ini (tgl_bayar),
         dipecah pelunasan nota lama vs nota yang order & bayarnya sama-sama
         hari ini. "Bayar DP Transaksi Ref" di referensi TIDAK direplikasi -
         tidak py alur "transaksi referensi" yang jelas padanannya di app
         ini, dari pada dipaksakan tanpa tahu maksud pastinya. --}}
    <div class="mb-3">
        <span class="inline-block px-3 py-1 bg-emerald-500 text-white text-[10px] font-medium uppercase tracking-widest rounded-lg">Rekap Pembayaran</span>
    </div>
    <div class="rounded-2xl border border-slate-200 overflow-hidden mb-6">
        <div class="grid grid-cols-2 border-b border-slate-100 bg-sky-50/40">
            <div class="px-5 py-3 text-xs font-medium text-slate-600">Bayar Pelunasan (nota hari sebelumnya)</div>
            <div class="px-5 py-3 text-sm font-medium text-slate-700 text-right">Rp {{ rupiah($bayarPelunasan, false) }}</div>
        </div>
        <div class="grid grid-cols-2 bg-emerald-50/60">
            <div class="px-5 py-3 text-xs font-medium text-emerald-700 uppercase">Bayar Transaksi Hari Ini</div>
            <div class="px-5 py-3 text-sm font-medium text-emerald-700 text-right">Rp {{ rupiah($bayarHariIni, false) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-6 mb-8">
        <div class="p-6 bg-emerald-50 rounded-2xl border-2 border-emerald-100">
            <h3 class="font-medium text-emerald-800 mb-4">ALUR KAS TUNAI (LACI)</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span>Saldo Awal (Modal)</span> <b>Rp {{ rupiah($kas->saldo_awal, false) }}</b></div>
                <div class="flex justify-between text-emerald-600"><span>Pemasukan Tunai Nota (+)</span> <b>Rp {{ rupiah($pemasukan->tunai_masuk, false) }}</b></div>
                <div class="flex justify-between text-emerald-600"><span>Kas Tidak Terduga Masuk (+)</span> <b>Rp {{ rupiah($manualMasuk, false) }}</b></div>
                <div class="flex justify-between text-red-500"><span>Kas Tidak Terduga Keluar (-)</span> <b>Rp {{ rupiah($pengeluaran_tunai, false) }}</b></div>
                <hr class="border-emerald-200">
                <div class="flex justify-between text-lg font-medium text-emerald-900">
                    <span>SALDO AKHIR LACI</span>
                    <span>Rp {{ rupiah($saldo_akhir_sistem, false) }}</span>
                </div>
            </div>
        </div>

        <div class="p-6 bg-blue-50 rounded-2xl border-2 border-blue-100">
            <h3 class="font-medium text-blue-800 mb-4">LAPORAN NON-TUNAI (NON-LACI)</h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span>Total Masuk Transfer</span> <b>Rp {{ rupiah($pemasukan->transfer_masuk, false) }}</b></div>
                <div class="flex justify-between"><span>Total Masuk Debit</span> <b>Rp {{ rupiah($pemasukan->debit_masuk ?? 0, false) }}</b></div>
                <div class="flex justify-between"><span>Total Masuk Deposit</span> <b>Rp {{ rupiah($pemasukan->deposit_masuk ?? 0, false) }}</b></div>
                {{-- Ditegaskan eksplisit sesuai arahan pemilik aplikasi: Transfer
                     (juga Debit & Deposit) TIDAK pernah ikut closing kas -
                     murni laporan keuangan, tidak menyentuh keadaan fisik laci
                     sama sekali (tidak ada di SALDO AKHIR LACI, tidak ada di
                     Total Isi Laci, tidak mempengaruhi Selisih). --}}
                <p class="text-[10px] text-blue-500 mt-4 font-medium">*Transfer/Debit/Deposit HANYA laporan keuangan - tidak ikut ditutup/dicocokkan di closing kas ini, karena tidak berwujud uang fisik di laci. Deposit sendiri sudah masuk kas saat konsumen setor deposit dulu, bukan hari ini.</p>
            </div>
        </div>
    </div>

    <form action="{{ route('kasir.simpan-closing') }}" method="POST" class="bg-slate-900 p-8 rounded-2xl text-white" id="form-closing">
        @csrf
        <input type="hidden" name="id_kas" value="{{ $kas->id_kas }}">
        <input type="hidden" name="saldo_sistem" value="{{ $saldo_akhir_sistem }}">
        <input type="hidden" name="tunai_masuk" value="{{ $pemasukan->tunai_masuk }}">
        <input type="hidden" name="transfer_masuk" value="{{ $pemasukan->transfer_masuk }}">
        <input type="hidden" name="debit_masuk" value="{{ $pemasukan->debit_masuk ?? 0 }}">
        <input type="hidden" name="tunai_keluar" value="{{ $pengeluaran_tunai }}">

        {{-- Total isi laci. Field ini DIAMAKAI "Saldo Revisi" di referensi
             pemilik aplikasi, tapi nama itu tidak menjelaskan apa pun bagi
             operator yang biasa dipakai kasir - di sini labelnya diganti
             jadi "Total Isi Laci (dihitung fisik)". --}}
        <label class="block text-center text-sm font-medium text-slate-400 mb-2 uppercase">Total Isi Laci (dihitung fisik)</label>
        <input type="text" inputmode="numeric" autocomplete="off" name="saldo_fisik" id="input-saldo-fisik" oninput="hitungSelisihLive()" class="w-full bg-transparent text-center text-4xl font-medium text-white border-b-2 border-slate-700 focus:outline-none focus:border-emerald-500 mb-2 format-ribuan" placeholder="0" required>

        {{-- Selisih DIHITUNG LIVE di browser (saldo fisik yang diketik vs
             SALDO AKHIR LACI sistem) - operator langsung tahu kurang/lebih
             SEBELUM menekan submit, bukan cuma tahu setelah tersimpan.
             Server tetap menghitung ulang angka final-nya sendiri (lihat
             KasirController::simpanClosing), ini murni bantuan tampilan. --}}
        <div id="display-selisih-live" class="text-center text-sm font-medium mb-6 text-slate-500">Selisih: Rp 0</div>

        <div class="mb-6">
            <label class="block text-center text-xs font-medium text-slate-400 mb-2 uppercase">Status Rolling Saldo Akhir</label>
            <select name="status_rolling" required class="w-full bg-white/10 border-none rounded-xl py-3 px-4 text-sm font-medium text-white focus:ring-2 focus:ring-emerald-500/40">
                <option value="Rolling">Rolling - Saldo Akhir Dipakai Kasir Besok (uang tetap di laci)</option>
                <option value="Tidak Rolling">Tidak Rolling - Uang Diambil/Disetor (besok mulai saldo baru)</option>
            </select>
        </div>

        <div class="mb-6">
            <label class="block text-center text-xs font-medium text-slate-400 mb-2 uppercase">Keterangan / Catatan Selisih</label>
            <textarea name="keterangan" id="input-keterangan" rows="3" required placeholder="Jelaskan penyebab selisih (kalau ada), atau tulis 'Aman' kalau pas."
                class="w-full bg-white/10 border-none rounded-xl py-3 px-4 text-sm font-medium text-white placeholder:text-slate-400 focus:ring-2 focus:ring-emerald-500/40"></textarea>
        </div>

        <button type="submit" class="w-full py-4 bg-emerald-500 hover:bg-emerald-400 text-white rounded-2xl font-medium uppercase tracking-widest transition-all">
            VERIFIKASI & KUNCI KASIR
        </button>
    </form>
</div>

</div>

@push('scripts')
<script>
    const saldoAkhirSistemJs = {{ (float) $saldo_akhir_sistem }};

    function hitungSelisihLive() {
        const fisik = cleanRupiah(document.getElementById('input-saldo-fisik').value);
        const selisih = fisik - saldoAkhirSistemJs;
        const fmt = new Intl.NumberFormat('id-ID');
        const el = document.getElementById('display-selisih-live');

        if (selisih === 0) {
            el.innerHTML = 'Selisih: <span class="text-emerald-400">Rp 0 (Pas)</span>';
        } else if (selisih < 0) {
            el.innerHTML = `Selisih: <span class="text-rose-400">- Rp ${fmt.format(Math.abs(selisih))} (Kurang dari sistem)</span>`;
        } else {
            el.innerHTML = `Selisih: <span class="text-amber-400">+ Rp ${fmt.format(selisih)} (Lebih dari sistem)</span>`;
        }
    }

    document.addEventListener('DOMContentLoaded', hitungSelisihLive);
</script>
@endpush
@endsection
