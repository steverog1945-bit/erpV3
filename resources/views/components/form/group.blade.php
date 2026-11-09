@props([
    'label'    => null,
    'name'     => null,
    'required' => false,
    'help'     => null,
    // dalam=true: label diletakkan di DALAM kolom (lihat ui-refresh.css bagian 25). Label besar seperti
    // placeholder saat kolom kosong, mengecil ke atas saat kolom diisi atau sedang diketik.
    'dalam'    => false,
])

@php
    $isi = (string) $slot;
    $pakaiDalam = false;
    if ($dalam && $label) {
        // Tidak semua isian cocok dengan label di dalam. Yang dikecualikan otomatis memakai tampilan biasa:
        // unggah berkas, centang/radio, dropdown yang bisa dicari (select2), isian dengan ikon atau awalan
        // yang ditempel (Rp, kaca pembesar), dan grup yang berisi lebih dari satu isian.
        $jumlahIsian = preg_match_all('/<(input|select|textarea)\b(?![^>]*type="hidden")/i', $isi);
        $dikecualikan = preg_match('/type="(file|checkbox|radio|color|range)"|select2|\babsolute\b|x-data|contenteditable/i', $isi);
        $pakaiDalam = $jumlahIsian === 1 && !$dikecualikan;
        if ($pakaiDalam) {
            // Label mengambang membutuhkan placeholder (boleh spasi) untuk mengenali kolom kosong.
            $isi = preg_replace('/<input\b(?![^>]*\bplaceholder=)/i', '<input placeholder=" "', $isi, 1);
            $isi = preg_replace('/<textarea\b(?![^>]*\bplaceholder=)/i', '<textarea placeholder=" "', $isi, 1);
            // Hubungkan label dengan isiannya (klik label = fokus ke isian, dibaca pembaca layar).
            if (preg_match('/<(?:input|select|textarea)\b[^>]*\bid="([^"]+)"/i', $isi, $m)) {
                $idIsian = $m[1];
            } else {
                $idIsian = 'ld-' . substr(md5($label . ($name ?? '') . uniqid('', true)), 0, 10);
                $isi = preg_replace('/<(input|select|textarea)\b(?![^>]*type="hidden")/i', '<$1 id="' . $idIsian . '"', $isi, 1);
            }
        }
    }
@endphp

<div {{ $attributes->merge(['class' => 'w-full']) }}>
    @if($pakaiDalam)
        <div class="ld">
            {!! $isi !!}
            <label class="ld-label" for="{{ $idIsian }}">{{ $label }}@if($required)<span class="ld-wajib" aria-hidden="true">*</span>@endif</label>
        </div>
    @else
        @if($label)
            <label class="block text-[10px] font-medium text-slate-500 uppercase tracking-widest mb-2 ml-2">
                {{ $label }}
                @if($required)
                    <span class="text-rose-500">*</span>
                @endif
            </label>
        @endif

        {{ $slot }}
    @endif

    @if($name)
        @error($name)
            <p class="mt-1.5 ml-2 text-xs font-medium text-rose-500">{{ $message }}</p>
        @enderror
    @endif

    @if($help)
        <p class="mt-1.5 ml-2 text-xs font-medium text-slate-500">{{ $help }}</p>
    @endif
</div>
