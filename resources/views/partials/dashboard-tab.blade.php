{{-- Tab Dashboard: Ringkasan | Info & Panduan. Tab Info hanya untuk yang berhak melihat Info & Panduan (menu 67). --}}
@php $bolehInfoTab = \App\Support\Izin::boleh(auth()->user(), [67], 'view'); $tabInfoAktif = ($tabInfo ?? false); @endphp
@if($bolehInfoTab)
<div class="flex items-center gap-2 mb-6" role="tablist">
    <a href="{{ route('dashboard') }}" role="tab" title="Ringkasan pekerjaan dan angka hari ini" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[11px] font-medium uppercase tracking-widest transition-all {{ !$tabInfoAktif ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white border border-slate-100 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600' }}"><i class="fas fa-gauge-high"></i> Ringkasan</a>
    <a href="{{ route('dashboard', ['tab' => 'info']) }}" role="tab" title="Pengumuman, SOP, tips, dan hasil karya" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-[11px] font-medium uppercase tracking-widest transition-all {{ $tabInfoAktif ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white border border-slate-100 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600' }}"><i class="fas fa-newspaper"></i> Info &amp; Panduan</a>
</div>
@endif
