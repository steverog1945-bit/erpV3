@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-[1200px] mx-auto">

    <x-page-header :title="'Hak Akses User: ' . $user->username" :subtitle="'Role: ' . optional($user->role)->nama_role . ' - baris berlatar kuning = pengecualian khusus user ini; selebihnya ikut role.'">
        <x-slot:actions>
            <x-button href="{{ url('/users') }}" variant="secondary"><i class="fas fa-arrow-left"></i> Kembali</x-button>
        </x-slot:actions>
    </x-page-header>

    @if(optional($user->role)->nama_role === 'Administrator')
        <div class="p-5 rounded-2xl bg-emerald-50 text-emerald-700 text-sm font-medium">User berrole Administrator selalu memiliki semua akses.</div>
    @else
    <form action="{{ route('akses.user.update', $user->id_user) }}" method="POST">
        @csrf
        <input type="hidden" name="_versi" value="{{ $versi }}">
        @include('akses._matriks', ['menus' => $menus, 'izin' => $izin, 'izinRole' => $izinRole, 'override' => $override])
        <div class="mt-6 flex flex-wrap gap-3">
            <x-button type="submit" variant="primary"><i class="fas fa-save"></i> Simpan Hak Akses User</x-button>
        </div>
    </form>
    @if($override->isNotEmpty())
    <form action="{{ route('akses.user.reset', $user->id_user) }}" method="POST" class="mt-3" data-konfirmasi="Hapus semua pengecualian dan kembali mengikuti role?">
        @csrf
        <button type="submit" class="px-4 py-3 rounded-xl bg-rose-50 text-rose-600 text-sm font-medium hover:bg-rose-100"><i class="fas fa-undo"></i> Kembalikan ke hak role ({{ $override->count() }} pengecualian)</button>
    </form>
    @endif
    @endif
</div>
@endsection
