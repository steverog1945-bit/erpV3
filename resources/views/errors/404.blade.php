@extends('errors.layout')

@section('code', '404')
@section('title', 'Halaman Tidak Ditemukan')
@section('message', 'Alamat yang Anda buka tidak ada, sudah dipindahkan, atau tautannya salah.')

@section('ikon')
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="11" cy="11" r="7"/>
        <path d="M21 21l-4.3-4.3"/>
        <path d="M8.5 8.5l5 5M13.5 8.5l-5 5"/>
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
