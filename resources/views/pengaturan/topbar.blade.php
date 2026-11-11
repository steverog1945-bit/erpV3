@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-6xl mx-auto">
    <x-page-header title="Topbar Modul" subtitle="Atur label, urutan, dan tautan tab modul di baris kedua topbar. Tempat aman untuk mengubah teks tampilan tanpa menyentuh skrip mana pun." />

    @if (session('success'))
        <div role="status" class="bg-emerald-50 text-emerald-700 border border-emerald-200 p-4 rounded-xl mb-6 font-medium">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div role="alert" class="bg-rose-50 text-rose-700 border border-rose-200 p-4 rounded-xl mb-6 font-medium">
            {{ session('error') }}
        </div>
    @endif
    @if ($errors->any())
        <div role="alert" class="bg-rose-50 text-rose-700 border border-rose-200 p-4 rounded-xl mb-6 font-medium">
            @foreach ($errors->all() as $err)
                <p>{{ $err }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('pengaturan-aplikasi.topbar.update') }}" id="topbar-form">
        @csrf
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <p class="text-sm font-semibold text-slate-800">Tab modul</p>
                    <p class="text-xs text-slate-500 mt-0.5">URL harus diawali garis miring, mis. <span class="font-medium tabular-nums">/kasir</span>. Ikon dibatasi pada preset.
                    </p>
                </div>
                <button type="button" id="tambah-tab" class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:text-slate-800 hover:border-slate-300 transition-colors">
                    <i class="fas fa-plus text-[10px]"></i> Tambah Tab
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] max-w-full text-left text-sm">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-[0.14em] text-slate-400 bg-slate-50/70">
                            <th class="px-4 py-3 font-bold w-20">Urut</th>
                            <th class="px-4 py-3 font-bold w-40">Ikon</th>
                            <th class="px-4 py-3 font-bold">Label</th>
                            <th class="px-4 py-3 font-bold">URL (path)</th>
                            <th class="px-4 py-3 font-bold w-20 text-center">Tampil</th>
                            <th class="px-4 py-3 font-bold w-20 text-center">Hapus</th>
                        </tr>
                    </thead>
                    <tbody id="topbar-rows" class="divide-y divide-slate-50">
                        @php
                            $rows = $tabs->isNotEmpty()
                                ? $tabs->map(fn ($t) => ['id' => $t->id, 'urutan' => $t->urutan, 'label' => $t->label, 'url' => $t->url, 'icon' => $t->icon, 'aktif' => $t->aktif])->all()
                                : array_map(fn ($t, $i) => ['id' => null, 'urutan' => $t['urutan'], 'label' => $t['label'], 'url' => $t['url'], 'icon' => $t['icon'], 'aktif' => $t['aktif']], $defaults, array_keys($defaults));
                        @endphp
                        @foreach ($rows as $i => $row)
                            <tr class="topbar-row">
                                <td class="px-4 py-3 align-middle">
                                    <input type="hidden" name="rows[{{ $i }}][id]" value="{{ $row['id'] }}">
                                    <input type="number" min="0" max="9999" name="rows[{{ $i }}][urutan]" value="{{ $row['urutan'] }}" class="h-9 w-16 rounded-lg border border-slate-200 px-2 text-sm tabular-nums focus:outline-none focus:border-indigo-400" required>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <select name="rows[{{ $i }}][icon]" class="h-9 w-36 rounded-lg border border-slate-200 px-2 text-xs font-medium text-slate-600 bg-white focus:outline-none focus:border-indigo-400">
                                        @foreach ($icons as $key => $path)
                                            <option value="{{ $key }}" @selected($row['icon'] === $key)>{{ $key }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <input type="text" name="rows[{{ $i }}][label]" value="{{ $row['label'] }}" maxlength="30" class="h-9 w-full max-w-[10rem] rounded-lg border border-slate-200 px-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:border-indigo-400" required>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <input type="text" name="rows[{{ $i }}][url]" value="{{ $row['url'] }}" maxlength="255" class="h-9 w-full max-w-xs font-medium tabular-nums rounded-lg border border-slate-200 px-2.5 text-xs text-slate-600 focus:outline-none focus:border-indigo-400" required>
                                </td>
                                <td class="px-4 py-3 align-middle text-center">
                                    <input type="checkbox" name="rows[{{ $i }}][aktif]" value="1" @checked($row['aktif']) class="h-4 w-4 accent-indigo-600">
                                </td>
                                <td class="px-4 py-3 align-middle text-center">
                                    <input type="checkbox" name="rows[{{ $i }}][hapus]" value="1" class="h-4 w-4 accent-rose-600" aria-label="Hapus tab ini">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
                <p class="text-xs text-slate-400">Mencentang <span class="font-semibold text-rose-600">Hapus</span> lalu menekan Simpan akan menghapus tab tersebut secara permanen.</p>
                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex items-center gap-2 h-9 px-4 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-check text-[10px]"></i> Simpan
                    </button>
                </div>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('pengaturan-aplikasi.topbar.reset') }}" class="mt-4" onsubmit="return confirm('Kembalikan topbar ke bawaan? Semua kustomisasi akan hilang.');">
        @csrf
        <button type="submit" class="text-xs font-medium text-slate-500 hover:text-rose-600 transition-colors">Kembalikan ke bawaan</button>
    </form>

<template id="topbar-row-template">
    <tr class="topbar-row">
        <td class="px-4 py-3 align-middle">
            <input type="hidden" name="rows[__INDEX__][id]" value="">
            <input type="number" min="0" max="9999" name="rows[__INDEX__][urutan]" value="100" class="h-9 w-16 rounded-lg border border-slate-200 px-2 text-sm tabular-nums focus:outline-none focus:border-indigo-400" required>
        </td>
        <td class="px-4 py-3 align-middle">
            <select name="rows[__INDEX__][icon]" class="h-9 w-36 rounded-lg border border-slate-200 px-2 text-xs font-medium text-slate-600 bg-white focus:outline-none focus:border-indigo-400">
                @foreach ($icons as $key => $path)
                    <option value="{{ $key }}">{{ $key }}</option>
                @endforeach
            </select>
        </td>
        <td class="px-4 py-3 align-middle">
            <input type="text" name="rows[__INDEX__][label]" value="" maxlength="30" class="h-9 w-full max-w-[10rem] rounded-lg border border-slate-200 px-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:border-indigo-400" required>
        </td>
        <td class="px-4 py-3 align-middle">
            <input type="text" name="rows[__INDEX__][url]" value="/" maxlength="255" class="h-9 w-full max-w-xs font-medium tabular-nums rounded-lg border border-slate-200 px-2.5 text-xs text-slate-600 focus:outline-none focus:border-indigo-400" required>
        </td>
        <td class="px-4 py-3 align-middle text-center">
            <input type="checkbox" name="rows[__INDEX__][aktif]" value="1" checked class="h-4 w-4 accent-indigo-600">
        </td>
        <td class="px-4 py-3 align-middle text-center">
            <input type="checkbox" name="rows[__INDEX__][hapus]" value="1" class="h-4 w-4 accent-rose-600" aria-label="Hapus tab ini">
        </td>
    </tr>
</template>

<script>
(function () {
    var form = document.getElementById('topbar-form');
    if (!form) return;
    var rows = document.getElementById('topbar-rows');
    var template = document.getElementById('topbar-row-template');
    var btn = document.getElementById('tambah-tab');
    if (!rows || !template || !btn) return;

    function nextIndex() {
        return rows.querySelectorAll('tr.topbar-row').length;
    }
    function normalizeUrls() {
        rows.querySelectorAll('input[name$="[url]"]').forEach(function (input) {
            var v = input.value.trim();
            if (v !== '' && v.charAt(0) !== '/') input.value = '/' + v;
        });
    }

    btn.addEventListener('click', function () {
        var idx = nextIndex();
        var html = template.innerHTML.split('__INDEX__').join(idx);
        var box = document.createElement('tbody');
        box.innerHTML = html;
        rows.appendChild(box.firstElementChild);
        normalizeUrls();
    });

    form.addEventListener('submit', normalizeUrls);
})();
</script>
</div>
@endsection
