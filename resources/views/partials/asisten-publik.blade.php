{{-- Asisten chat publik — HANYA seputar orderan (server menolak topik lain).
     Tanpa aset eksternal (halaman publik tidak memuat jQuery/FA), SVG inline.
     Endpoint publik POST /landing-tanya, dibatasi laju. --}}
<style>
    .publik-asisten-fab { position: fixed; right: 18px; bottom: calc(18px + env(safe-area-inset-bottom, 0px)); z-index: 80; width: 58px; height: 58px; border-radius: 999px; border: 0; background: #2563EB; color: #fff; display: grid; place-items: center; cursor: pointer; box-shadow: 0 10px 28px rgba(37, 99, 235, .4), inset 0 1px 0 rgba(255,255,255,.35); transition: transform .18s ease, background .18s ease; }
    .publik-asisten-fab:hover { transform: translateY(-2px) scale(1.04); background: #1D4ED8; }
    .publik-asisten-fab:focus-visible { outline: 3px solid #93C5FD; outline-offset: 2px; }
    .publik-asisten-panel { position: fixed; right: 18px; bottom: calc(86px + env(safe-area-inset-bottom, 0px)); z-index: 80; width: min(360px, calc(100vw - 36px)); height: min(480px, calc(100dvh - 120px)); border-radius: 18px; overflow: hidden; background: #fff; border: 1px solid #E3E9F6; box-shadow: 0 24px 60px rgba(15, 30, 62, .28); display: none; flex-direction: column; font-family: Inter, system-ui, sans-serif; }
    .publik-asisten-panel.terbuka { display: flex; animation: publikAsistenIn .26s cubic-bezier(.22,1,.36,1); }
    @keyframes publikAsistenIn { from { opacity: 0; transform: translateY(14px) scale(.96); } to { opacity: 1; transform: none; } }
    .publik-asisten-head { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: #0F1E3E; color: #fff; }
    .publik-asisten-head .pa-avatar { width: 36px; height: 36px; border-radius: 10px; background: #2563EB; display: grid; place-items: center; flex: 0 0 36px; }
    .publik-asisten-head .pa-nama { font-size: 13px; font-weight: 600; letter-spacing: .01em; color: #fff; }
    .publik-asisten-head .pa-sub { font-size: 10px; color: #9FB3E8; line-height: 1.3; }
    .publik-asisten-tutup { margin-left: auto; width: 32px; height: 32px; border-radius: 8px; border: 0; background: transparent; color: #9FB3E8; font-size: 17px; cursor: pointer; }
    .publik-asisten-tutup:hover { background: rgba(255,255,255,.12); color: #fff; }
    .publik-asisten-log { flex: 1; overflow-y: auto; padding: 12px 12px 6px; display: flex; flex-direction: column; gap: 8px; background: #F7F9FE; }
    .pa-bubble { max-width: 88%; padding: 9px 12px; border-radius: 14px; font-size: 12.5px; line-height: 1.55; white-space: pre-line; }
    .pa-user { align-self: flex-end; background: #2563EB; color: #fff; border-bottom-right-radius: 4px; }
    .pa-asisten { align-self: flex-start; background: #fff; border: 1px solid #E3E9F6; color: #1E2A4A; border-bottom-left-radius: 4px; }
    .pa-chips { display: flex; gap: 6px; flex-wrap: wrap; padding: 8px 10px 0; background: #F7F9FE; border-top: 1px solid #EDF2F9; }
    .pa-chip { height: 30px; padding: 0 12px; border-radius: 999px; border: 1px solid #E3E9F6; background: #fff; font-size: 11px; font-weight: 500; color: #3E4C74; cursor: pointer; transition: all .15s ease; }
    .pa-chip:hover { border-color: #2563EB; color: #1D4ED8; }
    .publik-asisten-form { display: flex; gap: 8px; padding: 10px; border-top: 1px solid #EDF2F9; background: #fff; }
    .publik-asisten-input { flex: 1; min-width: 0; height: 42px; border: 1px solid #E3E9F6; border-radius: 10px; padding: 0 12px; font: inherit; font-size: 13px; color: #1E2A4A; background: #F9FBFF; outline: 0; }
    .publik-asisten-input:focus { border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37,99,235,.15); background: #fff; }
    .publik-asisten-kirim { height: 42px; padding: 0 16px; border-radius: 10px; border: 0; background: #2563EB; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; }
    .publik-asisten-kirim:hover { background: #1D4ED8; }
    @media (prefers-reduced-motion: reduce) { .publik-asisten-fab, .publik-asisten-panel.terbuka { animation: none !important; transition: none !important; } }
</style>
<button type="button" class="publik-asisten-fab" id="publikAsistenFab" aria-expanded="false" aria-controls="publikAsistenPanel" aria-label="Buka tanya asisten order">
    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 6 8.38 8.38 0 0 1-3.8-.9L3 22l1.6-5.7A8.38 8.38 0 0 1 3.5 12.4 8.5 8.5 0 0 1 9.5 4.7a8.38 8.38 0 0 1 3.8-.9h.6a8.5 8.5 0 0 1 7.1 7.7v.6z"/></svg>
</button>
<div class="publik-asisten-panel" id="publikAsistenPanel" role="dialog" aria-modal="false" aria-label="Asisten order" hidden>
    <div class="publik-asisten-head">
        <span class="pa-avatar"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg></span>
        <span>
            <span class="pa-nama">Tanya Orderan</span><br>
            <span class="pa-sub">Hanya seputar orderan &nbsp;·&nbsp; bukan angka usaha</span>
        </span>
        <button type="button" class="publik-asisten-tutup" id="publikAsistenTutup" aria-label="Tutup">&times;</button>
    </div>
    <div class="publik-asisten-log" id="publikAsistenLog" aria-live="polite"></div>
    <div class="pa-chips" id="publikAsistenChips">
        <button type="button" class="pa-chip" data-tanya="cara pesan">Cara pesan?</button>
        <button type="button" class="pa-chip" data-tanya="harga">Harga?</button>
        <button type="button" class="pa-chip" data-tanya="berapa harga banner 3 x 1 meter">Banner 3x1?</button>
        <button type="button" class="pa-chip" data-tanya="cara bayar">Cara bayar?</button>
        <button type="button" class="pa-chip" data-tanya="jam buka">Jam buka?</button>
        <button type="button" class="pa-chip" data-tanya="kontak">Kontak toko</button>
        <button type="button" class="pa-chip" data-tanya="bantuan">Bantuan</button>
    </div>
    <form class="publik-asisten-form" id="publikAsistenForm">
        <label style="display:none" for="publikAsistenInput">Tulis pertanyaan tentang orderan</label>
        <input class="publik-asisten-input" id="publikAsistenInput" type="text" placeholder="Tanya: cara pesan, harga, pengiriman…" maxlength="300" autocomplete="off">
        <button class="publik-asisten-kirim" type="submit">Kirim</button>
    </form>
</div>
<script>
(function () {
    var fab = document.getElementById('publikAsistenFab');
    var panel = document.getElementById('publikAsistenPanel');
    var tutup = document.getElementById('publikAsistenTutup');
    var log = document.getElementById('publikAsistenLog');
    var form = document.getElementById('publikAsistenForm');
    var input = document.getElementById('publikAsistenInput');
    var chips = document.getElementById('publikAsistenChips');
    if (!fab || !panel) return;
    var urlTanya = @json(route('landing.tanya'));
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content;
    var disapa = false;

    function bubble(isi, siapa) {
        var d = document.createElement('div');
        d.className = 'pa-bubble ' + (siapa === 'user' ? 'pa-user' : 'pa-asisten');
        d.textContent = isi;
        log.appendChild(d);
        log.scrollTop = log.scrollHeight;
        return d;
    }
    function kirim(teks) {
        teks = (teks || '').trim();
        if (!teks) return;
        bubble(teks, 'user');
        var loading = bubble('…', 'asisten');
        fetch(urlTanya, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf || '', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            body: JSON.stringify({ pesan: teks })
        }).then(function (r) {
            return r.text().then(function (txt) {
                var data = null;
                try { data = JSON.parse(txt); } catch (e) { /* bukan JSON (mis. halaman error 419/500) */ }
                return { ok: r.ok, status: r.status, data: data, mentah: txt };
            });
        }).then(function (h) {
            loading.remove();
            if (!h.data) {
                bubble('Maaf, server menolak (HTTP ' + h.status + '). Coba muat ulang halaman; jika tetap gagal, hubungi kasir lewat WhatsApp.', 'asisten');
                return;
            }
            var bal = h.data.reply || h.data.message || 'Maaf, jawaban kosong.';
            bubble(bal, 'asisten');
        }).catch(function () {
            loading.remove();
            bubble('Maaf, gagal terhubung ke server. Coba lagi sebentar.', 'asisten');
        });
    }
    function buka(v) {
        panel.hidden = !v;
        panel.classList.toggle('terbuka', v);
        fab.setAttribute('aria-expanded', v ? 'true' : 'false');
        if (v) {
            if (!disapa) {
                disapa = true;
                bubble('Halo! Saya asisten order toko ini — tanya seputar orderan: cara pesan, pembayaran, pengantaran, file, jam buka, kontak. Ketik "bantuan".', 'asisten');
            }
            setTimeout(function () { input.focus(); }, 60);
        } else {
            fab.focus();
        }
    }
    fab.addEventListener('click', function () { buka(!panel.classList.contains('terbuka')); });
    tutup.addEventListener('click', function () { buka(false); });
    // Deep-link demo/bantuan: ?asisten=1 membuka panel otomatis saat load;
    // &tanya=... langsung mengirim pertanyaan itu (tautan dukungan/demo).
    if (new URLSearchParams(location.search).get('asisten') === '1') {
        buka(true);
        var tanyaLink = (new URLSearchParams(location.search).get('tanya') || '').trim();
        if (tanyaLink) {
            setTimeout(function () { kirim(tanyaLink); }, 150);
        }
    }
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        kirim(input.value);
        input.value = '';
    });
    chips.addEventListener('click', function (e) {
        var b = e.target.closest('[data-tanya]');
        if (b) kirim(b.getAttribute('data-tanya'));
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && panel.classList.contains('terbuka')) buka(false);
    });
})();
</script>
