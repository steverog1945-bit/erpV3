@extends('errors.layout')

@section('code', '419')
@section('title', 'Sesi Berakhir')
@section('message', 'Sesi masuk Anda telah berakhir karena terlalu lama tidak aktif.')
@section('hint', 'Muat ulang halaman, masuk kembali, lalu ulangi aksi yang tadi sempat gagal.')

@section('ikon')
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l3 2"/>
    </svg>
@endsection

@section('primary')
    <a class="btn btn-primary" href="{{ url('/login') }}">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>
        Masuk Kembali
    </a>
@endsection

@section('secondary')
    <button type="button" class="btn btn-secondary" onclick="window.location.reload()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.34-5.66"/><path d="M20 3v5h-5"/></svg>
        Muat Ulang
    </button>
@endsection
