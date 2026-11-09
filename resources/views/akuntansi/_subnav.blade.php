@php
    $tabs = [
        'akuntansi.jurnal'       => ['Jurnal Umum', 'fa-book'],
        'akuntansi.buku-besar'   => ['Buku Besar', 'fa-layer-group'],
        'akuntansi.neraca-saldo' => ['Neraca Saldo', 'fa-balance-scale'],
        'akuntansi.laba-rugi'    => ['Laba Rugi', 'fa-chart-line'],
        'akuntansi.neraca'       => ['Neraca', 'fa-file-invoice-dollar'],
        'akuntansi.perbandingan' => ['Perbandingan', 'fa-scale-unbalanced'],
    ];
@endphp
<div class="flex flex-wrap gap-2 mb-8">
    @foreach($tabs as $route => [$label, $icon])
        <a href="{{ route($route) }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-medium uppercase tracking-wider transition-all {{ request()->routeIs($route) ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-500 border border-slate-200 hover:bg-slate-50' }}">
            <i class="fas {{ $icon }}"></i> {{ $label }}
        </a>
    @endforeach
    <a href="{{ route('akun.index') }}"
       class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-medium uppercase tracking-wider transition-all bg-white text-slate-400 border border-dashed border-slate-200 hover:bg-slate-50 ml-auto">
        <i class="fas fa-cog"></i> Daftar Akun
    </a>
</div>
