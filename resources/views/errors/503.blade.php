@extends('errors.layout')

@section('code', '503')
@section('title', 'Layanan Sedang Sibuk')
@section('message', 'Sistem sedang sibuk memproses permintaan lain, atau sedang dalam pemeliharaan singkat.')
@section('hint', 'Permintaan yang tertunda bentrok dengan proses kasir lain di saat yang sama. Data Anda tidak hilang ΓÇö coba lagi dalam beberapa detik.')

@section('ikon')
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="8" width="18" height="12" rx="2"/>
        <path d="M9 8V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/>
        <path d="M3 13h18"/>
    </svg>
@endsection

@section('primary')
    <button type="button" class="btn btn-primary" onclick="window.location.reload()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12a8 8 0 1 1-2.34-5.66"/><path d="M20 3v5h-5"/></svg>
        Coba Lagi
    </button>
@endsection

@section('secondary')
    <a class="btn btn-secondary" href="{{ url('/') }}">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-8 9 8"/><path d="M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10"/></svg>
        Ke Beranda
    </a>
@endsection
