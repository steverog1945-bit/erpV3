@extends('layouts.app')

@section('content')
<div class="w-full max-w-[1180px] mx-auto px-4 sm:px-6 lg:px-8 py-6">
    {{-- HEADER --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
        <div class="min-w-0">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-[#EFF4FF] grid place-items-center text-[#2563EB] text-lg"><i class="fas fa-wand-magic-sparkles"></i></span>
                <div>
                    <h1 class="text-[20px] font-semibold text-[#0F1E3E] tracking-tight leading-tight">Asisten Orion</h1>
                    <p class="text-[12px] font-medium text-slate-500">Tanya jawab order, stok, kas &amp; rekap laporan — dijalankan di server Anda. Tidak ada data yang keluar ke internet.</p>
                </div>
            </div>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full border border-[#E7D9B6] bg-[#FDF6E3] px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.16em] text-[#C98A1B]"><i class="fas fa-gem"></i> Premium</span>
    </div>

    <div class="grid lg:grid-cols-[minmax(0,1fr)_340px] gap-5">
        {{-- KOLOM KIRI: CHAT --}}
        <div class="rounded-2xl border border-[#E3E9F6] bg-white shadow-[0_1px_3px_rgba(15,30,62,.05)] flex flex-col min-h-[520px] overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-5 h-12 border-b border-[#EDF2F9]">
                <span class="text-[11px] font-semibold uppercase tracking-widest text-slate-500">Percakapan</span>
                <span id="statusKoneksi" class="inline-flex items-center gap-1.5 text-[10px] font-semibold text-emerald-700"><i class="fas fa-circle text-[6px]"></i> Siap</span>
            </div>

            <div id="chatLog" class="flex-1 overflow-y-auto px-5 py-5 flex flex-col gap-4 max-h-[560px]" aria-live="polite">
                <div class="flex items-start gap-2.5 max-w-[92%]">
                    <span class="w-8 h-8 rounded-lg bg-[#2563EB] grid place-items-center text-white text-[11px] shrink-0 mt-0.5"><i class="fas fa-wand-magic-sparkles"></i></span>
                    <div class="rounded-2xl rounded-tl-md bg-[#F1F5FA] border border-[#E3E9F6] px-4 py-2.5 text-[13px] leading-relaxed text-[#1E2A4A]">
                        Halo! Saya <strong>Asisten Orion</strong>. Saya menghitung langsung dari data ERP Anda — tidak mengarang angka. Coba ketik: <em>rekap bulan ini</em>, <em>berapa order hari ini</em>, atau <em>nota LAN-1</em>. Ketik <strong>/bantuan</strong> untuk daftar lengkap.
                    </div>
                </div>
            </div>

            <form id="formTanya" class="border-t border-[#EDF2F9] p-4 flex items-end gap-3 bg-white">
                <label class="sr-only" for="inputTanya">Tulis pertanyaan</label>
                <textarea id="inputTanya" rows="1" maxlength="500" placeholder="Tanya sesuatu… (Enter mengirim, Shift+Enter baris baru)" class="flex-1 resize-none rounded-xl border border-[#E3E9F6] bg-[#F9FBFF] px-4 py-2.5 text-[13px] text-slate-800 placeholder:text-slate-400 focus:border-[#2563EB] focus:ring-2 focus:ring-[#2563EB]/20 focus:outline-none" required></textarea>
                <button type="submit" id="btnKirim" class="h-11 pl-4 pr-5 rounded-xl bg-[#2563EB] text-white text-[13px] font-semibold inline-flex items-center gap-2 hover:bg-[#1D4ED8] transition-colors disabled:opacity-60 disabled:pointer-events-none">
                    <span>Kirim</span><i class="fas fa-paper-plane text-[11px]"></i>
                </button>
            </form>
        </div>

        {{-- KOLOM KANAN: PINTASAN --}}
        <div class="flex flex-col gap-5">
            <div class="rounded-2xl border border-[#E3E9F6] bg-white p-5 shadow-[0_1px_3px_rgba(15,30,62,.05)]">
                <h2 class="text-[11px] font-semibold uppercase tracking-widest text-slate-500 mb-3">Pintasan cepat</h2>
                <div class="grid grid-cols-2 gap-2" id="chipsCepat">
                    <button type="button" data-tanya="rekap bulan ini" class="chip-cepat col-span-2 bg-[#2563EB] text-white border-[#2563EB] hover:bg-[#1D4ED8]">Rekap bulan ini</button>
                    <button type="button" data-tanya="berapa order hari ini" class="chip-cepat">Order hari ini</button>
                    <button type="button" data-tanya="omzet hari ini" class="chip-cepat chip-uang">Omzet hari ini</button>
                    <button type="button" data-tanya="rekap minggu ini" class="chip-cepat">Rekap minggu ini</button>
                    <button type="button" data-tanya="rekap hari ini" class="chip-cepat">Rekap hari ini</button>
                    <button type="button" data-tanya="piutang belum lunas" class="chip-cepat chip-uang">Piutang belum lunas</button>
                    <button type="button" data-tanya="kas masuk hari ini" class="chip-cepat chip-uang">Kas masuk hari ini</button>
                    <button type="button" data-tanya="antrean produksi" class="chip-cepat">Antrean produksi</button>
                    <button type="button" data-tanya="stok menipis" class="chip-cepat">Stok menipis</button>
                    <button type="button" data-tanya="produk terlaris" class="chip-cepat">Produk terlaris</button>
                    <button type="button" data-tanya="/bantuan" class="chip-cepat">/bantuan</button>
                </div>
                <p class="mt-3 text-[10px] leading-relaxed text-slate-400">Chip <span class="font-semibold text-slate-500">biru angka uang</span> hanya menjawab untuk role yang boleh melihat harga.</p>
            </div>

            <div class="rounded-2xl border border-[#E3E9F6] bg-white p-5 shadow-[0_1px_3px_rgba(15,30,62,.05)]">
                <h2 class="text-[11px] font-semibold uppercase tracking-widest text-slate-500 mb-2">Yang bisa dijawab</h2>
                <ul class="text-[12px] leading-relaxed text-slate-600 space-y-1.5">
                    <li><strong>Nota</strong> — <code class="code">nota LAN-1</code> cek status &amp; total</li>
                    <li><strong>Order &amp; omzet</strong> — harian / mingguan / bulanan</li>
                    <li><strong>Rekap</strong> — omzet, HPP, laba kotor, margin, piutang</li>
                    <li><strong>Piutang &amp; kas</strong> — sisa tagihan &amp; uang masuk hari ini</li>
                    <li><strong>Produksi</strong> — antrean Antre &amp; item dikerjakan</li>
                    <li><strong>Stok</strong> — bahan menipis / habis</li>
                    <li><strong>Produk terlaris</strong> — 30 hari terakhir</li>
                    <li><strong>Konsumen</strong> — <code class="code">konsumen adim</code> cari pelanggan</li>
                </ul>
            </div>

            <div class="rounded-2xl border border-[#E3E9F6] bg-[#F9FBFF] p-5">
                <h2 class="text-[11px] font-semibold uppercase tracking-widest text-slate-500 mb-2">AI lokal (opsional)</h2>
                <p class="text-[11.5px] leading-relaxed text-slate-600">Rekap sudah dibuat dari angka asli ERP. Bila pemilik memasang <strong>Ollama</strong> di mesin ini dan menyetel <code class="code">ASISTEN_AI_ENABLED=true</code>, AI lokal hanya <em>membacakan</em> angka itu menjadi kalimat eksekutif — tetap tanpa internet. Tanpa itu, ringkasan angka tetap tampil.</p>
                <p class="mt-2 text-[10px] text-slate-400">Belum ada model eksternal/cloud yang dipasang. Keputusan model ada di tangan pemilik.</p>
            </div>
        </div>
    </div>
</div>

<style>
    .chip-cepat { display:inline-flex; align-items:center; justify-content:center; height:38px; padding:0 10px; border-radius:10px; border:1px solid #E3E9F6; background:#F7F9FE; font-size:11px; font-weight:600; color:#3E4C74; text-align:center; transition:all .15s ease; cursor:pointer; }
    .chip-cepat:hover { border-color:#2563EB; color:#1D4ED8; background:#EFF4FF; transform:translateY(-1px); }
    .chip-uang { border-color:#DCD2F8; color:#5B3FB8; background:#F6F3FF; }
    .chip-uang:hover { border-color:#8B5CF6; color:#4C1D95; background:#EFE9FF; }
    .code { background:#EDF1FA; border-radius:6px; padding:1px 6px; font-size:11px; color:#1E3A8A; }
    .bubble-user { max-width:88%; align-self:flex-end; background:#2563EB; color:#fff; border-radius:18px; border-bottom-right-radius:6px; padding:9px 14px; font-size:13px; line-height:1.55; animation: bubbleIn .25s ease; }
    .bubble-asisten { max-width:88%; align-self:flex-start; background:#F1F5FA; border:1px solid #E3E9F6; color:#1E2A4A; border-radius:18px; border-bottom-left-radius:6px; padding:9px 14px; font-size:13px; line-height:1.6; animation: bubbleIn .25s ease; white-space: pre-line; }
    .bubble-asisten strong { color:#0F1E3E; }
    .bubble-loading { opacity:.75; font-style:italic; }
    @keyframes bubbleIn { from { opacity:0; transform:translateY(6px) scale(.98); } to { opacity:1; transform:none; } }
    @media (prefers-reduced-motion: reduce) { .bubble-user, .bubble-asisten { animation:none; } }
</style>

<script>
(function () {
    var log = document.getElementById('chatLog');
    var form = document.getElementById('formTanya');
    var input = document.getElementById('inputTanya');
    var btn = document.getElementById('btnKirim');
    var statusEl = document.getElementById('statusKoneksi');
    var urlTanya = @json(route('premium.asisten.tanya'));
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function render(s) {
        return esc(s).replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
    }
    function bubble(isi, siapa) {
        var d = document.createElement('div');
        d.className = siapa === 'user' ? 'bubble-user' : 'bubble-asisten';
        if (siapa === 'user') {
            d.textContent = isi;
        } else {
            d.innerHTML = render(isi);
        }
        log.appendChild(d);
        log.scrollTop = log.scrollHeight;
        return d;
    }
    function setAkhir(on) {
        btn.disabled = on;
        input.disabled = on;
        statusEl.innerHTML = on
            ? '<i class="fas fa-circle-notch fa-spin text-[10px]"></i> Menghitung…'
            : '<i class="fas fa-circle text-[6px]"></i> Siap';
        statusEl.className = 'inline-flex items-center gap-1.5 text-[10px] font-semibold ' + (on ? 'text-blue-700' : 'text-emerald-700');
    }
    function kirim(teks) {
        teks = (teks || '').trim();
        if (!teks) return;
        bubble(teks, 'user');
        var loading = bubble('Menghitung dari data Anda…', 'asisten');
        loading.classList.add('bubble-loading');
        setAkhir(true);

        fetch(urlTanya, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ pesan: teks }),
        }).then(function (r) {
            return r.json().catch(function () { return { reply: 'Jawaban tidak terbaca. Coba lagi.' }; });
        }).then(function (d) {
            loading.remove();
            bubble(d.reply || 'Jawaban kosong.', 'asisten');
        }).catch(function () {
            loading.remove();
            bubble('Gagal menghubungi server. Periksa koneksi Anda, lalu coba lagi.', 'asisten');
        }).finally(function () {
            setAkhir(false);
            input.focus();
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        kirim(input.value);
        input.value = '';
        input.style.height = 'auto';
    });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            kirim(input.value);
            input.value = '';
        }
    });
    input.addEventListener('input', function () {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
    });
    document.getElementById('chipsCepat').addEventListener('click', function (e) {
        var b = e.target.closest('[data-tanya]');
        if (b) kirim(b.getAttribute('data-tanya'));
    });
})();
</script>
@endsection
