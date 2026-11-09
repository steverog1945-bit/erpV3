@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-10 max-w-6xl mx-auto">
    <x-page-header title="Tema & Tampilan" subtitle="Pilih satu palet brand untuk seluruh pengguna aplikasi." />

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

    @if ($errors->has('preset'))
        <div role="alert" class="bg-rose-50 text-rose-700 border border-rose-200 p-4 rounded-xl mb-6 font-medium">
            {{ $errors->first('preset') }}
        </div>
    @endif

    <form id="brand-theme-form" method="POST" action="{{ route('pengaturan-aplikasi.tema.update') }}" class="space-y-6">
        @csrf
        <fieldset>
            <legend class="sr-only">Pilih preset tema</legend>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($presets as $preset)
                    <label
                        class="brand-theme-option block rounded-xl border p-4 sm:p-5 cursor-pointer transition-colors"
                        style="--preview-accent: {{ $preset['accent']['light'] }}; --preview-accent-soft: {{ $preset['accent']['surface_light'] }};"
                    >
                        <input
                            type="radio"
                            name="preset"
                            value="{{ $preset['id'] }}"
                            class="sr-only"
                            @checked(old('preset', $currentTheme['id']) === $preset['id'])
                        >
                        <span class="flex items-start justify-between gap-4">
                            <span>
                                <span class="block text-base font-medium text-slate-900">{{ $preset['name'] }}</span>
                                <span class="block mt-1 text-sm text-slate-600">{{ $preset['description'] }}</span>
                            </span>
                            <span class="brand-theme-check shrink-0 text-sm font-medium text-indigo-700" aria-hidden="true">
                                <i class="fas fa-check-circle"></i>
                            </span>
                        </span>

                        <span class="flex gap-2 mt-4" aria-label="Warna palet">
                            @foreach ($preset['swatches'] as $swatch)
                                <span class="h-7 w-7 rounded-full border border-black/10" style="background-color: {{ $swatch }}"></span>
                            @endforeach
                        </span>

                        <span class="brand-theme-preview mt-4 flex items-center justify-between gap-3 rounded-lg border p-3">
                            <span>
                                <span class="block text-xs font-medium text-slate-800">Pratinjau komponen</span>
                                <span class="block mt-1 text-xs text-slate-600">Contoh kartu, status, dan tombol</span>
                            </span>
                            <span class="flex shrink-0 items-center gap-2">
                                <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Aktif</span>
                                <span class="rounded-md px-3 py-2 text-xs font-medium text-white" style="background-color: var(--preview-accent)">
                                    Simpan
                                </span>
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-slate-200 pt-5">
            <p class="max-w-2xl text-sm text-slate-600">
                Tema tersimpan pada berkas lokal privat di <code>storage/app/system/brand-theme.json</code>.
                Berkas hanya berisi ID preset; nilai CSS selalu berasal dari preset tetap aplikasi.
            </p>
            <button id="brand-theme-submit" type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-3 text-sm font-medium text-white hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 disabled:cursor-wait disabled:opacity-70">
                <i id="brand-theme-submit-icon" class="fas fa-palette" aria-hidden="true"></i>
                <span id="brand-theme-submit-label" aria-live="polite">Terapkan tema</span>
            </button>
        </div>
    </form>
</div>

<style>
    .brand-theme-option {
        border-color: var(--border-primary);
        background: var(--surface-primary);
    }

    .brand-theme-option:has(input:checked) {
        border-color: var(--preview-accent);
        box-shadow: 0 0 0 1px var(--preview-accent);
    }

    .brand-theme-option:has(input:focus-visible) {
        outline: 2px solid var(--preview-accent);
        outline-offset: 3px;
    }

    .brand-theme-check {
        visibility: hidden;
    }

    .brand-theme-option:has(input:checked) .brand-theme-check {
        visibility: visible;
        color: var(--preview-accent);
    }

    .brand-theme-preview {
        border-color: var(--border-primary);
        background: var(--preview-accent-soft);
    }

    html[data-brand-theme="copilot-light"] .brand-theme-option,
    html[data-brand-theme="copilot-light"] .brand-theme-option .text-slate-900,
    html[data-brand-theme="copilot-light"] .brand-theme-option .text-slate-800 {
        color: var(--text-primary);
    }

    html[data-brand-theme="copilot-light"] .brand-theme-option .text-slate-600 {
        color: var(--text-secondary);
    }

    #brand-theme-submit:disabled {
        cursor: wait;
        opacity: .7;
    }
</style>
<script>
    const themeForm = document.getElementById('brand-theme-form');
    const themeSubmit = document.getElementById('brand-theme-submit');
    const showSubmitting = function () {
        themeSubmit.disabled = true;
        themeSubmit.setAttribute('aria-busy', 'true');
        document.getElementById('brand-theme-submit-icon').className = 'fas fa-spinner fa-spin';
        document.getElementById('brand-theme-submit-label').textContent = 'Menerapkan tema...';
    };

    themeForm.addEventListener('change', function (event) {
        if (!event.target.matches('input[name="preset"]')) return;

        if (typeof themeForm.requestSubmit === 'function') {
            themeForm.requestSubmit(themeSubmit);
        } else {
            showSubmitting();
            themeForm.submit();
        }
    });

    themeForm.addEventListener('submit', function () {
        showSubmitting();
    });
</script>
@endsection
