{{-- Footer global aplikasi: kredit pembuat + waktu render halaman (server).
     Sengaja satu baris kecil dan tenang - tidak ada kartu/gradien, tidak
     menyaingi konten; tidak memakai JS alias angka waktu dihitung di Blade. --}}
<footer class="mt-auto px-4 sm:px-6 pb-20 md:pb-4 pt-4 border-t border-slate-100">
    <div class="flex flex-col sm:flex-row items-center justify-center gap-1.5 sm:gap-3 text-[11px] text-slate-400">
        <span>
            Dibuat oleh
            <a href="https://www.obiyshinichiart.com" target="_blank" rel="noopener"
               class="font-semibold text-indigo-500 hover:text-indigo-600 transition-colors">obiyshinichiart.com</a>
        </span>
        <span class="hidden sm:inline" aria-hidden="true">&middot;</span>
        @if(defined('LARAVEL_START'))
            <span title="Waktu pembuatan HTML halaman ini di server">Page rendered in {{ number_format(microtime(true) - LARAVEL_START, 3, '.', '') }}s</span>
        @endif
    </div>
</footer>
