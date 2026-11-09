<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Closing Kas #{{ $kas->id_kas }}</title>
    @php
        // Angka stored dipakai apa adanya supaya cetakan selalu cocok dengan
        // yang tersimpan - lihat catatan di printReport().
        $tunaiMasuk  = (float) ($kas->total_tunai_masuk ?? 0);
        $transfer    = (float) ($kas->total_transfer_masuk ?? 0);
        $debit       = (float) ($kas->total_debit_masuk ?? 0);
        $deposit     = (float) $depositMasuk;
        $kasKeluar   = (float) ($kas->total_tunai_keluar ?? 0);
        $saldoAwal   = (float) ($kas->saldo_awal ?? 0);
        $saldoSistem = (float) ($kas->saldo_akhir ?? 0);
        $isiLaci     = (float) ($kas->saldo_fisik ?? 0);
        $selisih     = (float) ($kas->selisih ?? 0);
        // Saldo awal besok memakai uang yang benar-benar tertinggal di laci,
        // bukan angka sistem. Kalau ada selisih, angka inilah yang
        // mewariskan selisih itu ke sesi berikutnya - dan itu memang benar,
        // karena uangnya memang ada di sana.
        $saldoBesok  = $isiLaci;
        $thermal     = $format === 'thermal';
        $rp          = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
        $kelasSelisih = $selisih < 0 ? 'selisih-negatif' : ($selisih > 0 ? 'selisih-positif' : '');
        $jamTutup    = $kas->waktu_tutup ? \Carbon\Carbon::parse($kas->waktu_tutup)->format('d/m/Y H:i') : '-';
    @endphp
    @include('partials.print-style')
</head>
<body>
    <div class="sheet">
        @include('partials.print-header', ['docTitle' => 'Rekap Closing Kasir'])

        @if($thermal)
            {{-- ===========================================================
                 THERMAL - strip ringkas. Satu layar 58mm, tanpa catatan
                 panjang dan tanpa blok tanda tangan: yang dicetak cukup
                 angka yang dibutuhkan untuk mencocokkan uang di laci dan
                 menuliskan sisa kas. Rinciannya ada di format A4.
                 =========================================================== --}}
            <table>
                <tr><td>Sesi #{{ $kas->id_kas }}</td><td class="right">{{ \Carbon\Carbon::parse($kas->tgl_kas)->format('d/m/Y') }}</td></tr>
                <tr><td>Kasir</td><td class="right">{{ $kas->nama_kasir ?? '-' }}</td></tr>
                <tr><td>Ditutup</td><td class="right">{{ $jamTutup }}</td></tr>
            </table>

            <div class="divider"></div>
            <table>
                <tr><td>Saldo awal</td><td class="right">{{ $rp($saldoAwal) }}</td></tr>
                <tr><td>+ Tunai masuk</td><td class="right">{{ $rp($tunaiMasuk) }}</td></tr>
                <tr><td>+ Kas masuk</td><td class="right">{{ $rp($manualMasuk) }}</td></tr>
                <tr><td>&minus; Kas keluar</td><td class="right">{{ $rp($kasKeluar) }}</td></tr>
                <tr class="total-row"><td>Saldo akhir sistem</td><td class="right">{{ $rp($saldoSistem) }}</td></tr>
            </table>

            <div class="divider"></div>
            <table>
                <tr><td>Total isi laci</td><td class="right">{{ $rp($isiLaci) }}</td></tr>
                <tr class="total-row {{ $kelasSelisih }}"><td>Selisih</td>
                    <td class="right">{{ $selisih < 0 ? '-' : '' }}{{ $rp(abs($selisih)) }}</td></tr>
            </table>

            <div class="divider"></div>
            <table>
                <tr class="total-row"><td>SALDO AWAL BESOK</td><td class="right">{{ $rp($saldoBesok) }}</td></tr>
                <tr><td>Status</td><td class="right">{{ $kas->status_rolling ?? '-' }}</td></tr>
            </table>
            <p class="sub">Transfer, debit, dan deposit tidak ada di laci.</p>

            <div class="signature">
                <div>Kasir<div class="line"></div></div>
                <div>Pemeriksa<div class="line"></div></div>
            </div>
        @else
            {{-- ===========================================================
                 A4 - laporan lengkap untuk arsip. Rantainya adalah
                 rumusnya: saldo awal + pemasukan + kas masuk - kas keluar
                 = saldo akhir. Urutan baris tidak boleh diacak karena
                 pembaca harus bisa menghitung ulang sendiri.
                 =========================================================== --}}
            <table>
                <tr><td>Sesi Kas</td><td class="right">#{{ $kas->id_kas }}</td></tr>
                <tr><td>Tanggal</td><td class="right">{{ \Carbon\Carbon::parse($kas->tgl_kas)->format('d/m/Y') }}</td></tr>
                <tr><td>Kasir</td><td class="right">{{ $kas->nama_kasir ?? '-' }}</td></tr>
                <tr><td>Dibuka</td><td class="right">{{ \Carbon\Carbon::parse($kas->created_at)->format('d/m/Y H:i') }}</td></tr>
                <tr><td>Ditutup</td><td class="right">{{ $jamTutup }}</td></tr>
            </table>

            <div class="divider"></div>
            <p class="sub" style="font-weight:600;color:#1a231f;margin-bottom:4px">A. Alur Kas Tunai (Laci)</p>
            <table>
                <tr><td>Saldo awal / modal</td><td class="right">{{ $rp($saldoAwal) }}</td></tr>
                <tr><td>&nbsp;&nbsp;+ Pemasukan tunai nota</td><td class="right">{{ $rp($tunaiMasuk) }}</td></tr>
                <tr><td>&nbsp;&nbsp;+ Kas masuk (tidak terduga)</td><td class="right">{{ $rp($manualMasuk) }}</td></tr>
                <tr><td>&nbsp;&nbsp;&minus; Kas keluar (tidak terduga)</td><td class="right">{{ $rp($kasKeluar) }}</td></tr>
                <tr class="total-row"><td>Saldo akhir sistem</td><td class="right">{{ $rp($saldoSistem) }}</td></tr>
            </table>

            <div class="divider"></div>
            <p class="sub" style="font-weight:600;color:#1a231f;margin-bottom:4px">B. Penghitungan Uang di Laci</p>
            <table>
                <tr><td>Total isi laci (dihitung kasir)</td><td class="right">{{ $rp($isiLaci) }}</td></tr>
                <tr class="total-row {{ $kelasSelisih }}">
                    <td>Selisih (isi laci &minus; saldo sistem)</td>
                    <td class="right">{{ $selisih < 0 ? '-' : '' }}{{ $rp(abs($selisih)) }}</td></tr>
            </table>
            <p class="sub">
                {{ $selisih == 0
                    ? 'Saldo isi laci cocok dengan saldo sistem.'
                    : ($selisih > 0
                        ? 'Uang di laci lebih banyak dari catatan sistem.'
                        : 'Uang di laci lebih sedikit dari catatan sistem.') }}
            </p>

            <div class="divider"></div>
            <p class="sub" style="font-weight:600;color:#1a231f;margin-bottom:4px">C. Saldo Awal Sesi Berikutnya</p>
            <table>
                <tr class="total-row"><td>Saldo awal besok (uang yang tertinggal di laci)</td><td class="right">{{ $rp($saldoBesok) }}</td></tr>
                <tr><td>Status rolling</td><td class="right">{{ $kas->status_rolling ?? '-' }}</td></tr>
            </table>
            <p class="sub">
                Saldo awal besok memakai <b>total isi laci</b>, bukan saldo akhir sistem.
                Uang yang benar-benar ada di laci adalah yang harus dibawa ke sesi
                berikutnya, sehingga selisih tidak hilang diam-diam. Nilai ini juga
                sudah otomatis menjadi saran saldo awal saat kasir membuka sesi baru.
            </p>

            {{-- Transfer, debit, dan deposit tidak pernah menyentuh uang fisik
                 di laci - tidak masuk saldo akhir sistem dan tidak memengaruhi
                 selisih. Dicetak terpisah supaya operator tidak salah menghitung
                 saat menghitung isi laci. --}}
            <div class="divider"></div>
            <p class="sub" style="font-weight:600;color:#1a231f;margin-bottom:4px">D. Pembayaran Non-Tunai (tidak ada di laci)</p>
            <table>
                <tr><td>Transfer masuk</td><td class="right">{{ $rp($transfer) }}</td></tr>
                <tr><td>Debit masuk</td><td class="right">{{ $rp($debit) }}</td></tr>
                <tr><td>Deposit masuk</td><td class="right">{{ $rp($deposit) }}</td></tr>
            </table>
            <p class="sub">
                Angka di sini tidak pernah ikut closing kas - bukan bagian dari isi
                laci dan tidak memengaruhi selisih. Dicatat terpisah agar laporan
                keuangan harian tetap lengkap.
            </p>

            {{-- Catatan pembuka hanya ada kalau saldo awal sesi ini berbeda dari
                 isi laci sesi sebelumnya. Kalau tidak ada, berarti saldo awal
                 cocok dan tidak ada yang perlu dijelaskan. --}}
            @if($kas->keterangan_pembukaan)
            <div class="divider"></div>
            <p class="sub" style="font-weight:600;color:#1a231f;margin-bottom:4px">E. Catatan Pembukaan Sesi</p>
            <p class="sub">
                Saldo awal sesi ini ({{ $rp($saldoAwal) }}) tidak sama dengan isi laci
                sesi sebelumnya, jadi kasir menuliskan penjelasannya:
            </p>
            <p class="sub" style="font-style:italic">&ldquo;{{ $kas->keterangan_pembukaan }}&rdquo;</p>
            @endif

            @if($kas->keterangan_closing)
            <div class="divider"></div>
            <p class="sub">Catatan closing: {{ $kas->keterangan_closing }}</p>
            @endif

            <div class="signature">
                <div>Kasir<div class="line"></div></div>
                <div>Atasan / Pemeriksa<div class="line"></div></div>
            </div>
            <p class="sub" style="text-align:center;margin-top:8px">
                Dicetak dari sistem pada {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}
            </p>
        @endif
    </div>

    <div class="no-print">
        <button onclick="window.print()">Cetak</button>
    </div>
</body>
</html>
