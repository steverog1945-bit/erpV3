@extends('publik.layout-masuk')

{{--
    HALAMAN LOGIN ERP KHUSUS KARYAWAN (/masuk).

    Terpisah dari Landing Page lama atas keputusan operator: form login tidak
    lagi diletakkan di atas hero, daftar layanan, atau section template lama.

    YANG DIJAGA (tidak boleh berubah):
      - action form  : url('login')  -> POST /login (controller + throttle:login)
      - nama field   : 'username' dan 'password'  (dipakai AuthController::login)
      - token CSRF   : @csrf
      - atribut name="username" / name="password"  (dipakai test)
      - tampilkan $errors (validasi & pesan rate limit)

    Tidak ada hero, daftar layanan, daftar tulisan, kontak, atau footer lama
    yang dirender di belakang form.
--}}

@php
    $set = \App\Models\AppSetting::current();
    $nama = $set->nama_aplikasi;
    $wa = preg_replace('/\D+/', '', (string) $set->telepon);
    $wa = $wa !== '' ? (str_starts_with($wa, '0') ? '62' . substr($wa, 1) : $wa) : null;
    $tautWa = $wa ? 'https://wa.me/' . $wa : null;
@endphp

@section('judul', 'Masuk - ' . $nama)

@section('isi')
    <h1 class="mk-judul">Masuk ke sistem</h1>
    <p class="mk-sub">Akun dibuat oleh Administrator. Hubungi admin bila lupa kata sandi.</p>

    @if($errors->any())
        <div class="mk-galat" role="alert">
            <i class="fas fa-circle-exclamation mt-[2px]" aria-hidden="true"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <form action="{{ url('login') }}" method="POST">
        @csrf

        <div class="mk-grup">
            <label class="mk-label" for="login-username">Username</label>
            <input type="text"
                   id="login-username"
                   name="username"
                   class="mk-input"
                   placeholder="Nama pengguna"
                   required
                   autocomplete="username"
                   autocapitalize="none"
                   spellcheck="false"
                   autofocus>
        </div>

        <div class="mk-grup">
            <label class="mk-label" for="login-password">Password</label>
            <div class="mk-sandi">
                <input type="password"
                       id="login-password"
                       name="password"
                       class="mk-input"
                       placeholder="Kata sandi"
                       required
                       autocomplete="current-password">
                <button type="button"
                        id="login-toggle"
                        class="mk-lihat"
                        aria-label="Tampilkan password"
                        aria-pressed="false">
                    <i class="fas fa-eye" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="mk-tombol">Masuk</button>
    </form>

    @if($tautWa)
        <p class="mk-kaki" style="margin:18px 0 0">
            <a href="{{ $tautWa }}" target="_blank" rel="noopener noreferrer">Butuh bantuan? Hubungi WhatsApp</a>
        </p>
    @endif
@endsection

@push('skrip')
<script>
(function () {
    'use strict';
    var input = document.getElementById('login-password');
    var tombol = document.getElementById('login-toggle');
    if (!input || !tombol) return;

    tombol.addEventListener('click', function () {
        var tampil = input.type === 'password';
        input.type = tampil ? 'text' : 'password';
        tombol.setAttribute('aria-pressed', String(tampil));
        tombol.setAttribute('aria-label', tampil ? 'Sembunyikan password' : 'Tampilkan password');
        tombol.innerHTML = '<i class="fas ' + (tampil ? 'fa-eye-slash' : 'fa-eye') + '" aria-hidden="true"></i>';
        input.focus();
    });
})();
</script>
@endpush