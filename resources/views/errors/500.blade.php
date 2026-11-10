@extends('errors.layout')

@section('code', '500')
@section('title', 'Terjadi Kesalahan')
@section('message', 'Maaf, sistem sedang mengalami gangguan saat memproses permintaan Anda.')
@section('hint', 'Silakan coba lagi beberapa saat. Jika masalah terus terjadi, hubungi Administrator dan sebutkan kode 500 beserta halaman yang dibuka.')

@section('ikon')
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="7" rx="2"/>
        <rect x="3" y="13" width="18" height="7" rx="2"/>
        <path d="M7 7.5h.01M7 16.5h.01"/>
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
