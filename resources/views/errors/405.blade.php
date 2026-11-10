@extends('errors.layout')

@section('code', '405')
@section('title', 'Metode Tidak Diizinkan')
@section('message', 'Halaman ini tidak menerima jenis aksi yang dikirim.')
@section('hint', 'Biasanya terjadi karena mengirim formulir langsung dari tautan lama. Buka kembali halaman terkait, lalu ulangi aksi dari sana.')

@section('ikon')
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="9"/>
        <path d="M6 6l12 12"/>
    </svg>
@endsection

@section('primary')
    <a class="btn btn-primary" href="{{ url('/') }}">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-8 9 8"/><path d="M5 10v10a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V10"/></svg>
        Ke Beranda
    </a>
@endsection

@section('secondary')
    <button type="button" class="btn btn-secondary" onclick="window.history.back()">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        Kembali
    </button>
@endsection
