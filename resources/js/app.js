import './bootstrap';
import 'bootstrap'; // Bootstrap JS

/* SweetAlert2 SENGAJA tidak di-import di sini.
   Layout memuatnya sebagai skrip klasik di <head>
   (public/vendor/sweetalert2), karena ada skrip inline di badgean
   document yang memanggil Swal.mixin() dan Swal.fire() saat halaman
   sedang di-parse. Script type="module" selalu deferred, jadi import
   di sini baru tersedia setelah badgean selesai - terlambat untuk
   pemanggilan itu, dan hasilnya "Swal is not defined".

   Module ini tidak perlu menaruhnya di window juga: layout sudah
   memuat pustakanya duluan, jadi window.Swal sudah ada. */

/* Tooltip & Popover Bootstrap */
document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

window.deleteConfirm = function (formId) {
    Swal.fire({
        title: 'Hapus Bahan?',
        text: "Data yang dihapus tidak dapat dikembalikan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#2563EB',
        cancelButtonColor: '#f1f5f9',
        cancelButtonText: '<span style="color:#64748b">Batal</span>',
        confirmButtonText: 'Ya, Hapus!',
        customClass: { popup: 'rounded-lg', confirmButton: 'rounded-md px-4 py-2', cancelButton: 'rounded-md px-4 py-2' }
    }).then((result) => {
        if (result.isConfirmed) document.getElementById(formId).submit();
    });
};

/* ==========================================================================
   API MODAL
   ==========================================================================
   Satu-satunya cara membuka/menutup modal di seluruh aplikasi ini.

   Yang ditangani di sini (sebelumnya hanya ganti class, sehingga
   tombol tutup, Esc, dan klik luar tidak bekerja di banyak halaman):
     1. buka / tutup dengan animasi (scale + opacity, 200ms ease-out)
     2. tutup saat Esc ditekan
     3. tutup saat area gelap di luar panel diklik
     4. tombol di dalam panel bertanda data-modal-close
     5. kembalikan fokus ke elemen pemicu setelah ditutup
     6. kunci scroll halaman selama modal terbuka, dan buka kembali
        dengan benar bila ada modal lain masih terbuka

   Root modal memakai atribut `hidden` (bukan kelas), jadi elemen yang
   tertutup tidak bisa difokus atau dibaca screen reader.
   ========================================================================== */

const MODAL_TRANSITION_MS = 240;
let modalOpenCount = 0;
let lastFocused = null;

function modalPanel(el) {
    return el.querySelector('[data-modal-panel]') || el.firstElementChild;
}

function lockScroll() {
    modalOpenCount += 1;
    if (modalOpenCount !== 1) return;
    document.body.classList.add('overflow-hidden');
    // Hapus gaya inline yang sisa dari halaman sebelumnya. Beberapa
    // view lama menulis body.style.overflow = 'hidden' sendiri saat
    // membuka modal, lalu menutupnya lewat API ini - sehingga nilai
    // inline itu tertinggal dan halaman tidak bisa digulir lagi.
    document.body.style.overflow = '';
}

function unlockScroll() {
    modalOpenCount = Math.max(0, modalOpenCount - 1);
    if (modalOpenCount !== 0) return;
    document.body.classList.remove('overflow-hidden');
    // WAJIB dibersihkan, bukan cuma kelas Tailwind. Kalau view mana pun
    // pernah menyetel body.style.overflow = 'hidden' langsung, nilainya
    // menang atas kelas dan tidak hilang bersama kelasnya. Akibatnya
    // seluruh halaman jadi tidak bisa digulir setelah modal ditutup, dan
    // satu-satunya jalan keluar adalah muat ulang - persis gejala "stuck"
    // yang dilaporkan. Membersihkannya di sini menutup celah itu untuk
    // semua halaman, Sekalipun ada view yang belum ikut diperbaiki.
    document.body.style.overflow = '';
}

/* Ada DUA cara menyembunyikan modal di aplikasi ini, dan keduanya
   masih dipakai sekalian:
     - <x-modal> menyembunyikannya lewat ATRIBUT `hidden`
     - modal yang ditulis manual per halaman lewat KELAS `hidden`
   Kalau hanya salah satu yang dikenali, modal manual terbaca "sudah
   terbuka" padahal tidak terlihat: tombol tidak melakukan apa-apa, dan
   Escape tidak menutup apa pun. Jadi keduanya selalu ditangani
   bersamaan di bawah. */
function isTertutup(el) {
    return el.hasAttribute('hidden') || el.classList.contains('hidden');
}

function tampilkan(el) {
    el.removeAttribute('hidden');
    el.classList.remove('hidden');
    // Hanya modal dari komponen yang butuh `flex`; modal manual sudah
    // menata dirinya lewat anak di dalamnya (min-h-screen + flex).
    if (el.querySelector(':scope > [data-modal-panel]')) el.classList.add('flex');
}

function sembunyikan(el) {
    el.setAttribute('hidden', '');
    el.classList.add('hidden');
    el.classList.remove('flex');
}

/* Semua modal yang sedang terbuka, untuk mendeteksi Escape.
   Selector memakai data-modal-root, BUKAN [id^="modal-"]. Id yang
   diawali "modal-" juga dipakai judul di dalam modal
   (h3 id="modal-{id}-title"), dan dulu selector prefix ikut
   menangkapnya: Escape menutup judulnya, bukan panelnya, sehingga teks
   judul hilang tapi modal tetap menggantung di layar. */
function openModals() {
    return Array.from(document.querySelectorAll('[data-modal-root]')).filter((m) => !isTertutup(m));
}

function focusFirstIn(panel) {
    const target = panel.querySelector(
        'input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [data-autofocus]'
    );
    if (target) {
        // Cegah browser menggulir ke bawah karena fokus.
        target.focus({ preventScroll: true });
    } else {
        panel.focus({ preventScroll: true });
    }
}

window.openModal = function (id) {
    const el = document.getElementById('modal-' + id) || document.getElementById(id);
    if (!el || !isTertutup(el)) return;

    const panel = modalPanel(el);
    lastFocused = document.activeElement;

    tampilkan(el);
    el.dataset.state = 'opening';
    lockScroll();

    // Pindah ke state "open" setelah browser selesai merender frame
    // pertama, supaya transisi masuk benar-benar terlihat. rAF
    // ditunda/dilewati ketika tab tidak terlihat, jadi ada jaring
    // pengaman berbasis waktu: tanpa itu modal bisa tertahan di
    // opacity 0 (tidak terlihat) padahal statusnya sudah terbuka.
    const bukaPenuh = () => { if (el.dataset.state === 'opening') el.dataset.state = 'open'; };
    requestAnimationFrame(bukaPenuh);
    setTimeout(bukaPenuh, 32);

    // Fokus setelah frame pertama supaya animasi masuk selesai dimulai.
    if (panel) setTimeout(() => focusFirstIn(panel), 60);

    document.addEventListener('keydown', onModalKeydown, true);
    return false;
};

window.closeModal = function (id) {
    const el = id ? (document.getElementById('modal-' + id) || document.getElementById(id)) : openModals().pop();
    if (!el || isTertutup(el)) return;

    el.dataset.state = 'closing';

    const done = () => {
        sembunyikan(el);
        el.dataset.state = 'closed';
        el.removeAttribute('data-state');
        unlockScroll();

        if (lastFocused && document.contains(lastFocused)) {
            lastFocused.focus({ preventScroll: true });
        }
        lastFocused = null;
    };

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) done();
    else setTimeout(done, MODAL_TRANSITION_MS);

    if (!openModals().length) {
        document.removeEventListener('keydown', onModalKeydown, true);
    }
    return false;
};

/* Alias untuk halaman lama yang memakai toggleModal(namaTanpaPrefix). */
window.toggleModal = function (id) {
    const el = document.getElementById('modal-' + id) || document.getElementById(id);
    if (!el) return;
    if (isTertutup(el)) window.openModal(id);
    else window.closeModal(id);
};

function onModalKeydown(e) {
    const open = openModals();
    if (!open.length) return;
    const atas = open[open.length - 1];

    if (e.key === 'Escape') {
        e.stopPropagation();
        window.closeModal(atas.id);
        return;
    }

    // Tab berputar di dalam modal yang terbuka. Tanpa ini fokus keyboard keluar ke halaman di belakang
    // modal, dan Enter bisa menekan tombol yang tidak terlihat.
    if (e.key === 'Tab') {
        const panel = modalPanel(atas) || atas;
        const bisa = Array.from(panel.querySelectorAll(
            'a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
        )).filter((el) => el.offsetParent !== null);
        if (!bisa.length) return;
        const pertama = bisa[0], terakhir = bisa[bisa.length - 1];
        if (!panel.contains(document.activeElement)) {
            e.preventDefault();
            pertama.focus();
        } else if (e.shiftKey && document.activeElement === pertama) {
            e.preventDefault();
            terakhir.focus();
        } else if (!e.shiftKey && document.activeElement === terakhir) {
            e.preventDefault();
            pertama.focus();
        }
    }
}

/* Klik di luar panel, dan tombol bertanda data-modal-close.
   Dipasang sekali di document, jadi berlaku untuk modal yang baru
   dibuat halaman mana pun tanpa perlu registering ulang. */
document.addEventListener('click', function (e) {
    const closer = e.target.closest('[data-modal-close]');
    if (closer) {
        const root = closer.closest('[data-modal-root]');
        if (root) {
            e.preventDefault();
            window.closeModal(root.id);
        }
        return;
    }

    // Area gelap = elemen yang menjadi anak langsung root modal
    // dan ditandai data-modal-backdrop.
    if (e.target.matches('[data-modal-backdrop]')) {
        const root = e.target.closest('[data-modal-root]');
        if (root) {
            e.preventDefault();
            window.closeModal(root.id);
        }
    }
}, true);

// ==========================================================================
// GridFocus - navigasi fokus 2D (baris x kolom) untuk form dengan banyak input.
// Aktif hanya pada container yang ditandai [data-grid-focus]. Pemakaian:
//   <div data-grid-focus> ... input/select/textarea ... </div>
// Tombol: ArrowRight/Enter -> kanan (di kolom terakhir baris -> baris berikutnya kolom 1),
//         ArrowLeft -> kiri, ArrowDown -> bawah, ArrowUp -> atas (kolom sama).
// ==========================================================================
(function () {
    function collectGrid(container) {
        const sel = 'input:not([type=hidden]):not([disabled]):not([readonly]), select:not([disabled]), textarea:not([disabled]):not([readonly])';
        const els = Array.from(container.querySelectorAll(sel)).filter((e) => {
            const r = e.getBoundingClientRect();
            return r.width > 0 && r.height > 0;
        });
        const hasCoords = els.some((e) => e.dataset.row !== undefined || e.dataset.col !== undefined);
        if (hasCoords) {
            const rowMap = new Map();
            els.forEach((e) => {
                const r = parseInt(e.dataset.row || '0', 10);
                const c = parseInt(e.dataset.col || '0', 10);
                if (!rowMap.has(r)) rowMap.set(r, []);
                rowMap.get(r).push({ el: e, col: c });
            });
            return [...rowMap.entries()].sort((a, b) => a[0] - b[0]).map(([, items]) => items.sort((a, b) => a.col - b.col).map((x) => x.el));
        }
        const sorted = els.slice().sort((a, b) => {
            const ra = a.getBoundingClientRect(), rb = b.getBoundingClientRect();
            if (Math.abs(ra.top - rb.top) > 8) return ra.top - rb.top;
            return ra.left - rb.left;
        });
        const rows = [];
        let currentRow = null;
        let currentTop = null;
        sorted.forEach((e) => {
            const top = e.getBoundingClientRect().top;
            if (currentTop === null || Math.abs(top - currentTop) > 8) {
                currentRow = [];
                rows.push(currentRow);
                currentTop = top;
            }
            currentRow.push(e);
        });
        rows.forEach((r) => r.sort((a, b) => a.getBoundingClientRect().left - b.getBoundingClientRect().left));
        return rows.filter((r) => r.length);
    }

    function findPos(rows, el) {
        for (let r = 0; r < rows.length; r++) {
            const c = rows[r].indexOf(el);
            if (c !== -1) return { r, c };
        }
        return null;
    }

    function focusEl(el) {
        if (!el) return;
        try { el.focus({ preventScroll: false }); } catch (e) { el.focus(); }
        if (el.select && el.tagName === 'INPUT' && (el.type === 'text' || el.type === 'number' || el.type === 'search')) {
            try { el.select(); } catch (e) {}
        }
    }

    function handler(e) {
        const el = document.activeElement;
        if (!el) return;
        const container = el.closest('[data-grid-focus]');
        if (!container) return;
        const keys = ['Enter', 'ArrowRight', 'ArrowLeft', 'ArrowDown', 'ArrowUp'];
        if (!keys.includes(e.key)) return;
        if (e.key === 'Enter' && el.tagName === 'TEXTAREA' && !e.ctrlKey) return;
        if (el.tagName === 'SELECT' && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) return;

        const rows = collectGrid(container);
        const pos = findPos(rows, el);
        if (!pos) return;

        let nr = pos.r, nc = pos.c;
        const lastCol = rows[pos.r].length - 1;
        const lastRow = rows.length - 1;

        if (e.key === 'ArrowRight') {
            if (pos.c + 1 < rows[pos.r].length) nc = pos.c + 1;
            else if (pos.r + 1 <= lastRow) { nr = pos.r + 1; nc = 0; }
            else nc = lastCol;
        } else if (e.key === 'Enter') {
            if (pos.c + 1 < rows[pos.r].length) nc = pos.c + 1;
            else if (pos.r + 1 <= lastRow) { nr = pos.r + 1; nc = 0; }
            else nc = pos.c;
        } else if (e.key === 'ArrowLeft') {
            if (pos.c - 1 >= 0) nc = pos.c - 1;
            else if (pos.r - 1 >= 0) { nr = pos.r - 1; nc = rows[nr].length - 1; }
            else nc = 0;
        } else if (e.key === 'ArrowDown') {
            if (pos.c === lastCol) {
                if (pos.r + 1 <= lastRow) { nr = pos.r + 1; nc = 0; }
            } else if (pos.r + 1 <= lastRow) {
                nr = pos.r + 1;
                nc = Math.min(pos.c, rows[nr].length - 1);
            }
        } else if (e.key === 'ArrowUp') {
            if (pos.r - 1 >= 0) {
                nr = pos.r - 1;
                nc = Math.min(pos.c, rows[nr].length - 1);
            }
        }

        nr = Math.max(0, Math.min(lastRow, nr));
        if (!rows[nr]) return;
        nc = Math.max(0, Math.min(rows[nr].length - 1, nc));
        const target = rows[nr][nc];
        if (target && target !== el) {
            e.preventDefault();
            focusEl(target);
        }
    }

    document.addEventListener('keydown', handler, true);

    window.enableGridFocus = function (container) {
        if (container) container.setAttribute('data-grid-focus', '');
    };
})();

// ==========================================================================
// C1-C2: penanganan error yang tidak diam + apiFetch + safeClick
// ==========================================================================
(function () {
    const REPORTED = new Set();
    function genCode() { return 'ERR-' + Math.random().toString(36).slice(2, 8).toUpperCase(); }

    function showToast(msg) {
        if (window.Swal) {
            Swal.fire({ icon: 'error', title: 'Terjadi kesalahan', text: msg, toast: true, position: 'top-end', timer: 6000, showConfirmButton: false });
        } else {
            // fallback minimal tanpa Swal
            try { alert(msg); } catch (e) {}
        }
    }

    function reportToServer(code, message, extra) {
        try {
            const payload = {
                kode: code,
                pesan: String(message || '').slice(0, 1000),
                url: (location && location.pathname) || '',
                baris: extra && extra.line ? extra.line : null,
                kolom: extra && extra.col ? extra.col : null,
                stack: extra && extra.stack ? String(extra.stack).slice(0, 2000) : null,
            };
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || (document.querySelector('input[name="_token"]')?.value);
            // Deploy di sub-direktori (…/grafikaorion/public): path absolut
            // '/client-log' akan 404 (htdocs/client-log tidak ada) dan
            // laporan klien tidak pernah tersimpan. Pakai base akar akhir
            // URL halaman supaya menempel di prefix aplikasi.
            fetch(new URL('client-log', document.baseURI || location.href).toString(), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token || '', 'Accept': 'application/json' },
                body: JSON.stringify(payload),
                keepalive: true,
            }).catch((e) => { console.warn('Laporan error klien tidak terkirim: ' + ((e && e.message) || e)); });
        } catch (e) {}
    }

    function handle(err, source) {
        const msg = (err && (err.message || err.reason || err)) || 'Unknown error';
        const code = genCode();
        const key = code + '|' + String(msg).slice(0, 80);
        if (REPORTED.has(key)) return;
        // Error muat aset (img/script/css 404) TIDAK boleh berbunyi seperti
        // error aplikasi: acara 'error' dengan capture=true juga menangkap
        // kartu <img> berkas yang sudah dihapus (thumbnail nota dsb.). Yang
        // begitu punya e.target (ElementEvent), bukan kegagalan skrip runtime.
        // SEBENARNYA: event resource tidak punya .error.. Penyaringannya
        // dilakukan di listener di bawah, bukan di sini (pesan resource tidak
        // pernah berwujud Error/Element di parameter handle).
        REPORTED.add(key);
        // jangan ganggu halaman publik/login
        const path = (location && location.pathname) || '';
        if (path === '/login' || path === '/masuk' || path.startsWith('/publik')) return;
        showToast('Terjadi kesalahan di halaman ini (kode ' + code + '). Data belum tersimpan. Muat ulang halaman atau hubungi admin.');
        reportToServer(code, msg, { stack: (err && (err.stack || err.reason?.stack)) || null });
    }

    window.addEventListener('error', (e) => {
        // Kegagalan muat aset (img/script/css 404, gambar nota yang sudah
        // dihapus, dsb.) juga sampai di listener 'error' dengan capture=true
        // sebagai event dgn target Element dan TANPA e.error. Statusnya bukan
        // kegagalan skrip runtime - jangan dibunyikan sebagai error aplikasi
        // "Data belum tersimpan" yang bikin panik.
        if (e && e.target && e.target !== window && (e.target instanceof window.Element) && !e.error) return;
        handle(e.error || e.message, 'error');
    }, true);
    window.addEventListener('unhandledrejection', (e) => handle(e.reason, 'unhandledrejection'));

    // apiFetch: satu pintu fetch untuk AJAX
    window.apiFetch = async function (url, opts) {
        opts = opts || {};
        let res;
        try {
            res = await fetch(url, Object.assign({ headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }, opts));
        } catch (e) {
            const code = genCode();
            showToast('Jaringan putus (kode ' + code + '). Coba lagi.');
            reportToServer(code, 'network-error: ' + (e && e.message), { stack: e && e.stack });
            throw e;
        }
        let data = null;
        const ct = res.headers.get('content-type') || '';
        if (ct.includes('application/json')) {
            try { data = await res.json(); } catch (e) { data = null; }
        } else {
            try { data = await res.text(); } catch (e) { data = null; }
        }
        if (res.status === 401 || res.status === 419) {
            showToast('Sesi berakhir. Muat ulang halaman.');
            throw new Error('session-expired');
        }
        if (res.status === 422) {
            const msg = (data && (data.msg || data.message)) || 'Validasi gagal.';
            showToast(msg);
            const err = new Error(msg); err.status = 422; err.data = data; throw err;
        }
        if (!res.ok) {
            const code = genCode();
            const msg = (data && (data.msg || data.message)) || ('Terjadi kesalahan server (kode ' + code + ').');
            showToast(msg + ' (kode ' + code + ')');
            reportToServer(code, 'http-' + res.status + ': ' + msg, {});
            const err = new Error(msg); err.status = res.status; err.data = data; throw err;
        }
        return data;
    };

    // safeClick: disable tombol saat proses, try/catch dengan pesan, enable kembali.
    window.safeClick = async function (btn, fn) {
        if (!btn) return;
        if (btn.disabled) return;
        btn.disabled = true;
        btn.classList.add('opacity-50', 'pointer-events-none');
        try {
            await fn();
        } catch (e) {
            if (e && e.message && e.message !== 'session-expired') {
                // pesan sudah ditampilkan oleh apiFetch/fn bila perlu; fallback
            }
        } finally {
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'pointer-events-none');
        }
    };
})();
/* ==========================================================================
   Cek nama konsumen mirip saat mengetik (dipakai form Tambah Konsumen dan
   quick-add POS).  Menampilkan kandidat yang sudah ada di database supaya
   konsumen tidak terdaftar ganda - lengkap dengan kode CST-000123.
   opsi: { input, hint, pilih(id, kode, nama, telepon), url }
   ========================================================================== */
window.cekKonsumenMirip = function (opsi) {
    var input = opsi.input, hint = opsi.hint, pilih = opsi.pilih, url = opsi.url || '/konsumen/cek-nama';
    if (!input || !hint) return;
    var timer = null;

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function render(data) {
        var rows = data.results || [];
        if (!rows.length) {
            hint.className = 'cek-nama-hint cek-nama-aman';
            hint.innerHTML = '<i class="fas fa-circle-check"></i> Nama ini belum ada di database - aman dipakai.';
            hint.classList.remove('hidden');
            return;
        }
        var html = '<div class="cek-nama-judul"><i class="fas ' + (data.persis ? 'fa-triangle-exclamation' : 'fa-magnifying-glass') + '"></i> '
            + (data.persis ? 'Nama ini SUDAH TERDAFTAR persis - pilih yang lama?' : 'Nama serupa sudah ada:') + '</div><div class="cek-nama-daftar">';
        rows.forEach(function (r) {
            html += '<button type="button" class="cek-nama-item" data-id="' + r.id + '" data-kode="' + escapeHtml(r.kode) + '" data-nama="' + escapeHtml(r.nama) + '" data-telepon="' + escapeHtml(r.telepon) + '">'
                + '<span class="cek-nama-kode">' + escapeHtml(r.kode) + '</span>'
                + '<span class="cek-nama-nama">' + escapeHtml(r.nama) + '</span>'
                + '<span class="cek-nama-telp">' + escapeHtml(r.telepon) + '</span>'
                + '</button>';
        });
        html += '</div>';
        hint.className = 'cek-nama-hint ' + (data.persis ? 'cek-nama-warn' : 'cek-nama-info');
        hint.innerHTML = html;
        hint.classList.remove('hidden');
    }
    function sembunyi() { hint.classList.add('hidden'); hint.innerHTML = ''; }

    input.addEventListener('input', function () {
        var q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 2) { sembunyi(); return; }
        timer = setTimeout(function () {
            fetch(url + '?nama=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin'
            }).then(function (r) { return r.ok ? r.json() : null; })
              .then(function (data) { if (data) render(data); })
              .catch(function () {});
        }, 350);
    });
    input.addEventListener('blur', function () { setTimeout(function () { /* beri waktu klik pilihan */ }, 0); });
    hint.addEventListener('click', function (e) {
        var btn = e.target.closest('.cek-nama-item');
        if (!btn || typeof pilih !== 'function') return;
        e.preventDefault();
        pilih({
            id: parseInt(btn.getAttribute('data-id'), 10),
            kode: btn.getAttribute('data-kode'),
            nama: btn.getAttribute('data-nama'),
            telepon: btn.getAttribute('data-telepon')
        });
    });
};