{{--
    Header perusahaan bersama untuk semua dokumen print (nota, closing kas,
    SPK) - sebelumnya cuma <h1>nama aplikasi</h1> hardcode, tidak ada logo/
    alamat. Include dengan variabel $docTitle (judul dokumen, mis. "Nota
    Penjualan") - $format harus sudah ada di scope pemanggil.
--}}
@php($appSettingPrint = \App\Models\AppSetting::current())
<div class="print-header">
    @if($appSettingPrint->logo_path)
        <img src="{{ asset('storage/' . $appSettingPrint->logo_path) }}" alt="Logo">
    @endif
    <h1>{{ $appSettingPrint->nama_aplikasi }}</h1>
    @if($appSettingPrint->alamat || $appSettingPrint->telepon)
        <p class="company-info">
            {{ $appSettingPrint->alamat }}{{ $appSettingPrint->alamat && $appSettingPrint->telepon ? ' • ' : '' }}{{ $appSettingPrint->telepon }}
        </p>
    @endif
    <p class="doc-title">{{ $docTitle }}</p>
</div>
