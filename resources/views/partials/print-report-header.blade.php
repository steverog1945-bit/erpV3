{{--
    Letterhead cetak laporan: logo & identitas perusahaan (nama, alamat,
    telp/fax, email/website) rata kiri, judul dokumen + periode cetak rata
    kanan, ditutup garis tebal. Cuma tampil saat dicetak (hidden
    print:block).

    Sebelumnya versi ini terasa "mepet" ke tepi kertas - ternyata BUKAN
    karena tata letak kiri-kanannya, tapi karena setiap halaman laporan
    men-set `main { padding: 0 !important }` saat print (lihat <style>
    @media print di masing-masing laporan), jadi seluruh konten memang
    menempel ke tepi kertas fisik. Perbaikannya di situ (padding print
    dikembalikan wajar), bukan dengan menghilangkan letterhead ini.

    Variabel dari view pemanggil:
    - $docTitle  : judul dokumen, mis. "Rekap Transaksi Order"
    - $tglDari, $tglSampai : dipakai untuk baris "Periode Cetak" (opsional)
--}}
@php($appSettingReportPrint = \App\Models\AppSetting::current())
{{-- Gaya cetak tabel laporan (huruf seragam satu ukuran, teks membungkus
     supaya tidak ada kolom yang terpotong). Dipakai semua laporan lewat
     partial ini - lihat partials/print-report-table. --}}
@include('partials.print-report-table')
<style>
    @media print {
        {{-- KOP LAPORAN: judul boleh lebih besar dari isi laporan (judul
             harus dibaca lebih dulu - itu bukan ketidakseragaman), tapi
             KEDUA judul harus sama besar, dan baris meta (alamat, telp,
             email, periode) memakai ukuran yang sama dengan isi laporan
             supaya tidak ada huruf mencampur di kepala halaman.

             Selector 'body main .kelas' (tiga tingkat) dipilih supaya
             mengalahkan aturan ukuran huruf yang lain, yang semuanya
             memakai !important. --}}
        body main .kop-laporan-judul {
            font-size: 14px !important;
        }

        body main .kop-laporan-meta {
            font-size: var(--fs-tabel) !important;
        }
    }
</style>
<div class="hidden print:block mb-6">
    <div class="flex items-start justify-between gap-6">
        <div class="flex items-start gap-3">
            @if($appSettingReportPrint->logo_path)
                <img src="{{ asset('storage/' . $appSettingReportPrint->logo_path) }}" alt="Logo" class="h-14 w-14 object-contain shrink-0">
            @endif
            <div>
                {{-- Huruf besar dihapus. OpenCode tidak memakai text-transform
                     untuk judul apa pun dan letter-spacing-nya 0; pada
                     letterhead kertas, huruf besar hanya menambah lebar tanpa
                     menambah hierarki. Warna memakai token OpenCode:
                     teks utama #171717, teks sekunder #6f6f6f. --}}
                <h1 class="kop-laporan-judul text-[15px] font-medium text-[#171717] leading-tight">{{ $appSettingReportPrint->nama_aplikasi }}</h1>
                @if($appSettingReportPrint->alamat)
                    <p class="kop-laporan-meta text-[10.5px] text-[#6f6f6f] leading-snug mt-0.5">{{ $appSettingReportPrint->alamat }}</p>
                @endif
                @if($appSettingReportPrint->telepon || $appSettingReportPrint->fax)
                    <p class="kop-laporan-meta text-[10.5px] text-[#6f6f6f] leading-snug">Telp : {{ $appSettingReportPrint->telepon ?: '-' }}, Fax : {{ $appSettingReportPrint->fax ?: '-' }}</p>
                @endif
                @if($appSettingReportPrint->email || $appSettingReportPrint->website)
                    <p class="kop-laporan-meta text-[10.5px] text-[#6f6f6f] leading-snug">Email : {{ $appSettingReportPrint->email ?: '-' }}, Website : {{ $appSettingReportPrint->website ?: '-' }}</p>
                @endif
            </div>
        </div>
        <div class="text-right shrink-0">
            <h2 class="kop-laporan-judul text-[17px] font-medium text-[#171717] leading-tight">{{ $docTitle }}</h2>
            @if(isset($tglDari) && isset($tglSampai))
                <p class="kop-laporan-meta text-[11px] text-[#6f6f6f] mt-1">
                    Periode {{ \Carbon\Carbon::parse($tglDari)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($tglSampai)->format('d/m/Y') }}
                </p>
            @endif
        </div>
    </div>
    {{-- Garis penutup satu piksel, bukan 2px. Di palet OpenCode semua
         garis satu piksel; 2px hanya menambah bobot visual tanpa menambah
         informasi. --}}
    <div class="mt-3 border-t border-[#8f8f8f]"></div>
</div>
