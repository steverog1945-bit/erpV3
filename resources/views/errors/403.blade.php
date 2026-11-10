@extends('errors.layout')

@section('code', '403')
@section('title', 'Akses Ditolak')
@section('message', 'Anda tidak memiliki hak akses untuk membuka halaman ini.')
@section('hint', 'Butuh akses? Hubungi Administrator untuk menambahkan izin menu pada akun Anda.')

@section('ikon')
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <rect x="4" y="10" width="16" height="10" rx="2"/>
        <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
        <circle cx="12" cy="15" r="0.6" fill="var(--accent)" stroke="none"/>
    </svg>
@endsection

@section('primary')
    <a class="btn btn-primary" href="{{ url('/') }}">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-8 9 8"/><path d="M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10"/></svg>
        Ke Beranda
    </a>
@endsection

@section('secondary')
    <button type="button" class="btn btn-secondary" onclick="window.location.reload()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.34-5.66"/><path d="M20 3v5h-5"/></svg>
        Muat Ulang
    </button>
@endsection
