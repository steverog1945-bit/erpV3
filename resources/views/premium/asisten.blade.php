@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-6 max-w-[1100px] mx-auto">
    <x-page-header title="Asisten Order (Lokal)" subtitle="Tempel chat pelanggan apa adanya — Asisten memilah jumlah, ukuran, dan tenggat sebagai draf. Tanpa API, tanpa mengirim data ke mana-mana.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#E7D9B6] bg-[#FDF6E3] px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.16em] text-[#C98A1B]"><i class="fas fa-gem"></i> Premium</span>
        </x-slot:actions>
    </x-page-header>

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- INPUT --}}
        <div class="rounded-2xl border border-slate-200/70 bg-white p-6 shadow-[0_1px_3px_rgba(15,30,62,.05)]">
            <label for="teksChat" class="text-[11px] font-semibold uppercase tracking-widest text-slate-500">Tempel chat pelanggan</label>
            <textarea id="teksChat" rows="14" spellcheck="false" class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50/60 p-4 text-[13px] leading-relaxed text-slate-800 placeholder:text-slate-400 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 focus:outline-none" placeholder="Contoh:
Halo Orion, mau bikin banner 3x1 meter 2 pcs, sama name tag 25 pcs
untuk acara tanggal 24. Sama poster A3 50 lembar full color doff ya.
Btw bisa jadi hari Jumat?"></textarea>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button type="button" id="btnAnalisis" class="inline-flex items-center gap-2 h-10 px-5 rounded-lg bg-slate-900 text-white text-[12.5px] font-semibold hover:bg-slate-800 transition-colors"><i class="fas fa-wand-magic-sparkles"></i> Analisis Chat</button>
                <button type="button" id="btnBersih" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-slate-200 text-[12px] font-semibold text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-colors"><i class="fas fa-eraser"></i> Bersihkan</button>
            </div>
            <p class="mt-4 text-[10.5px] leading-relaxed text-slate-400">Analisis berjalan 100% di browser Anda: pencocokan pola jumlah, ukuran, dan kata tenggat. Ini bukan AI cloud — kerahasiaan data pelanggan tetap terjaga. Hasilnya draf: operator tetap yang memasukkan ke nota.</p>
        </div>

        {{-- HASIL --}}
        <div class="rounded-2xl border border-slate-200/70 bg-white p-6 shadow-[0_1px_3px_rgba(15,30,62,.05)] flex flex-col">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-[11px] font-semibold uppercase tracking-widest text-slate-500">Hasil Analisis</h3>
                <button type="button" id="btnSalin" class="hidden inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-slate-200 text-[11px] font-semibold text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-colors"><i class="far fa-copy"></i> Salin Ringkasan</button>
            </div>
            <div id="hasilKosong" class="flex-1 grid place-items-center py-12 text-center">
                <div>
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-indigo-50 grid place-items-center text-indigo-400 text-2xl"><i class="fas fa-wand-magic-sparkles"></i></div>
                    <p class="mt-4 text-[12px] font-medium text-slate-500">Hasil analisis akan tampil di sini.</p>
                    <p class="mt-1 text-[10.5px] text-slate-400">Chat panjang pun bisa — Asisten memilah per baris/item.</p>
                </div>
            </div>
            <div id="hasilIsi" class="hidden mt-4 space-y-4"></div>
        </div>
    </div>
</div>

<script>
(function () {
    var teks = document.getElementById('teksChat');
    var hasilIsi = document.getElementById('hasilIsi');
    var hasilKosong = document.getElementById('hasilKosong');
    var btnSalin = document.getElementById('btnSalin');
    var ringkasan = '';

    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

    function analisis(raw) {
        var lines = String(raw || '').split(/\r?\n|[•\-*]\s+/).map(function (s) { return s.trim(); }).filter(function (s) { return s.length > 2; });
        var items = [];
        lines.forEach(function (baris) {
            // ukuran: 3x1 meter, 60×90 cm, 2*3 m, A3, A4, F4, A5
            var mUkuran = baris.match(/(\d+(?:[.,]\d+)?)\s*(?:x|×|\*)\s*(\d+(?:[.,]\d+)?)\s*(cm|mm|m\b|meter)?/i);
            var kertas = baris.match(/\b(A[0-9](?:[- ]?(?:plus|\+|premium))?|F4|Folio|A3\+)\b/i);
            // jumlah: 25 pcs, 2 pc, 50 lembar, 3 unit, 10 buah
            var mQty = baris.match(/(\d+)\s*(pcs?|lembar|lbr|buah|unit|eksemplar|ex|set|box|rim)\b/i) || baris.match(/\bqty\s*[:=]?\s*(\d+)/i);
            // tenggat: besok, lusa, Jumat, tanggal 24
            var mTenggat = baris.match(/(besok|lusa|minggu depan|senin|selasa|rabu|kamis|jumat|jum’at|sabtu|minggu|tanggal\s*\d{1,2}(?:\s*(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember))?)/i);
            // finishing keywords
            var fin = (baris.match(/(laminasi doff|laminasi glossy|laminasi matte|doff|glossy|matte|cutting|pond|die ?cut|potong|jilid|spiral|hardcover|stiker|vinyl|outdoor|indoor|full ?color|bw|hitam putih)/ig) || []).map(function (s) { return s.toLowerCase(); });
            var qty = mQty ? parseInt(mQty[1], 10) : null;
            var ukuran = mUkuran ? (mUkuran[1] + ' × ' + mUkuran[2] + (mUkuran[3] ? ' ' + mUkuran[3].replace('meter', 'm') : ' m')) : (kertas ? kertas[1].toUpperCase() : null);
            var isItem = qty || ukuran || /banner|poster|stiker|name ?tag|brosur|kartu nama|flyer|x-?banner|roll ?up|spanduk|undangan|cv|porto|buku|katalog|label|packaging|packing|nota|amplop|kop/i.test(baris);
            if (!isItem) return;
            // bersihkan nama: buang frasa jumlah & ukuran dari nama item
            var nama = baris
                .replace(/(\d+)\s*(pcs?|lembar|lbr|buah|unit|eksemplar|ex|set|box|rim)\b/ig, '')
                .replace(/(\d+(?:[.,]\d+)?)\s*(x|×|\*)\s*(\d+(?:[.,]\d+)?)\s*(cm|mm|m\b|meter)?/ig, '')
                .replace(/\s{2,}/g, ' ').replace(/^[,.\-\s]+|[,.\-\s]+$/g, '').trim();
            items.push({ nama: nama.charAt(0).toUpperCase() + nama.slice(1), qty: qty, ukuran: ukuran, tenggat: mTenggat ? mTenggat[0] : null, finishing: Array.from(new Set(fin)) });
        });
        return items;
    }

    function render(items) {
        if (!items.length) {
            hasilKosong.classList.remove('hidden');
            hasilIsi.classList.add('hidden');
            btnSalin.classList.add('hidden');
            hasilKosong.innerHTML = '<div><div class="w-16 h-16 mx-auto rounded-2xl bg-amber-50 grid place-items-center text-amber-500 text-2xl"><i class="fas fa-circle-question"></i></div><p class="mt-4 text-[12px] font-medium text-slate-600">Tidak ada item terdeteksi.</p><p class="mt-1 text-[10.5px] text-slate-400">Tulis lebih spesifik (jumlah + jenis barang), mis. "banner 3x1m 2 pcs".</p></div>';
            return;
        }
        hasilKosong.classList.add('hidden');
        hasilIsi.classList.remove('hidden');
        btnSalin.classList.remove('hidden');
        ringkasan = 'Ringkasan Order (draf):\n' + items.map(function (it, i) {
            return (i + 1) + '. ' + it.nama + (it.qty ? ' - qty ' + it.qty : '') + (it.ukuran ? ' - ' + it.ukuran : '') + (it.finishing.length ? ' - finishing: ' + it.finishing.join(', ') : '') + (it.tenggat ? ' - tenggat: ' + it.tenggat : '');
        }).join('\n');
        hasilIsi.innerHTML = items.map(function (it, i) {
            return '<div class="rounded-xl border border-slate-200/70 bg-slate-50/50 p-4">'
                + '<div class="flex items-start gap-3">'
                + '<span class="w-6 h-6 shrink-0 rounded-md bg-slate-900 text-white text-[11px] font-bold grid place-items-center mt-0.5">' + (i + 1) + '</span>'
                + '<div class="min-w-0">'
                + '<p class="text-[13px] font-semibold text-slate-800 leading-snug">' + esc(it.nama) + '</p>'
                + '<div class="mt-2 flex flex-wrap gap-1.5">'
                + (it.qty ? '<span class="inline-flex items-center gap-1 rounded-md bg-indigo-50 border border-indigo-100 px-2 py-1 text-[10px] font-semibold text-indigo-700"><i class="fas fa-cubes"></i> Qty ' + esc(it.qty) + '</span>' : '')
                + (it.ukuran ? '<span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 border border-emerald-100 px-2 py-1 text-[10px] font-semibold text-emerald-700"><i class="fas fa-ruler-combined"></i> ' + esc(it.ukuran) + '</span>' : '')
                + (it.tenggat ? '<span class="inline-flex items-center gap-1 rounded-md bg-rose-50 border border-rose-100 px-2 py-1 text-[10px] font-semibold text-rose-600"><i class="far fa-clock"></i> ' + esc(it.tenggat) + '</span>' : '')
                + (it.finishing.length ? '<span class="inline-flex items-center gap-1 rounded-md bg-amber-50 border border-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-800"><i class="fas fa-layer-group"></i> ' + esc(it.finishing.join(', ')) + '</span>' : '')
                + '</div></div></div></div>';
        }).join('');
    }

    document.getElementById('btnAnalisis').addEventListener('click', function () { render(analisis(teks.value)); });
    document.getElementById('btnBersih').addEventListener('click', function () { teks.value = ''; teks.focus(); });
    btnSalin.addEventListener('click', async function () {
        try { await navigator.clipboard.writeText(ringkasan); btnSalin.innerHTML = '<i class="fas fa-check text-emerald-600"></i> Tersalin'; setTimeout(function(){ btnSalin.innerHTML = '<i class="far fa-copy"></i> Salin Ringkasan'; }, 1600); } catch (e) {}
    });
})();
</script>
@endsection
