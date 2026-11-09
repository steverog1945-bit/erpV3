{{--
    PENCARIAN CEPAT UNTUK DROPDOWN BANYAK OPSI
    ==========================================
    Dropdown yang isinya banyak (ratusan konsumen, supplier, bahan)
    menyulitkan mencari karena harus menggulir di dalam daftar.
    Dropdown yang cuma punya beberapa opsi statis (mis. "Semua Status")
    justru tidak perlu tombol tambahan.

    Karena itu logoutikanya otomatis, bukan per halaman:
      - dropdown dengan OPSI >= BATAS_AYIKAN  mendapat tombol kaca pembesar
      - dropdown di bawah itu dibiarkan polos

    Yang happens ketika tombol diklik:
      - modal terbuka berisi satu kolom daftar pilihan
      - mengetik SATU huruf saja langsung mempersempit daftar
      - klik salah satu pilihan -> nilai dropdown terisi, modal tertutup

    Batas 8 opsi: di bawah itu daftar masih muat di layar tanpa perlu
    menggulir, jadi tombol hanya menambah beban klik.
--}}

@php $qpThreshold = 8; @endphp

<div data-modal-root id="modal-quick-picker" hidden class="fixed inset-0 z-[60] items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-900/50" data-modal-backdrop></div>
    <div data-modal-panel
         class="relative bg-white w-full max-w-2xl rounded-lg border border-slate-200 shadow-xl max-h-[80vh] flex flex-col overflow-hidden"
         role="dialog" aria-modal="true" aria-labelledby="qp-title">
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200 shrink-0">
            <div class="min-w-0">
                <h3 id="qp-title" class="text-sm font-medium text-slate-900 truncate">Pilih Data</h3>
                <p class="text-xs text-slate-500 mt-0.5" id="qp-hint">Ketik untuk mencari, satu huruf sudah cukup</p>
            </div>
            <button type="button" data-modal-close
                    class="w-8 h-8 -mr-1 rounded-md flex items-center justify-center text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition-colors"
                    aria-label="Tutup">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>

        <div class="px-5 pt-4 shrink-0">
            <label for="qp-search" class="sr-only">Cari pilihan</label>
            <input type="text" id="qp-search" autocomplete="off"
                   placeholder="Ketik untuk mencari...">
        </div>

        <div class="px-5 py-3 overflow-y-auto grow min-h-0" id="qp-list" role="listbox" aria-label="Daftar pilihan"></div>

        <div class="px-5 py-3 border-t border-slate-200 shrink-0 flex items-center justify-between gap-3">
            <p class="text-xs text-slate-500" id="qp-count">0 pilihan</p>
            <x-button type="button" variant="secondary" onclick="closeModal('quick-picker')">Batal</x-button>
        </div>
    </div>
</div>

<script>
    (function () {
        'use strict';
        if (window.qpReady) return;
        window.qpReady = true;

        const BATAS = {{ $qpThreshold }};
        const root = document.getElementById('modal-quick-picker');
        if (!root) return;

        const input  = document.getElementById('qp-search');
        const list   = document.getElementById('qp-list');
        const judul  = document.getElementById('qp-title');
        const hitung = document.getElementById('qp-count');

        let target = null;   // <select> yang sedang diisi
        let semuaOpsi = []; // [{ value, label }]

        const esc = (s) => String(s).replace(/[&<>"']/g, (c) => (
            { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
        ));

        function ambilOpsi(el) {
            // data-qp-saring="#id_penyaring": hanya opsi yang data-kategori-nya sama dengan nilai penyaring
            // (mis. produk mengikuti kategori yang dipilih), sama dengan aturan dropdown aslinya.
            const penyaring = el.dataset.qpSaring ? document.querySelector(el.dataset.qpSaring) : null;
            const nilaiSaring = penyaring ? penyaring.value : null;
            return Array.from(el.options)
                .filter((o) => o.value !== '' && !o.disabled && !o.hidden)      // buang placeholder dan opsi nonaktif
                .filter((o) => !penyaring || (nilaiSaring !== '' && o.dataset.kategori === nilaiSaring))
                .map((o) => ({ value: o.value, label: o.textContent.trim() }));
        }

        function gambar(query) {
            const q = (query || '').trim().toLowerCase();
            const hasil = q ? semuaOpsi.filter((o) => o.label.toLowerCase().includes(q)) : semuaOpsi;

            if (!hasil.length) {
                list.innerHTML = '<p class="py-8 text-center text-sm text-slate-500">Tidak ada pilihan yang cocok dengan "' + esc(query) + '".</p>';
            } else {
                const html = hasil.map((o) => (
                    '<button type="button" role="option" data-value="' + esc(o.value) + '" ' +
                    'class="w-full flex items-center justify-between gap-3 px-3 py-2.5 rounded-md text-left text-sm ' +
                    'text-slate-700 hover:bg-slate-100 transition-colors">' +
                    '<span class="truncate">' + esc(o.label) + '</span>' +
                    '</button>'
                )).join('');
                list.innerHTML = '<div class="space-y-0.5">' + html + '</div>';
            }
            hitung.textContent = hasil.length + ' pilihan';
        }

        function buka(el) {
            // Dropdown yang sedang dikunci (mis. Produk sebelum Kategori dipilih) tidak boleh dibuka lewat pencari.
            if (el.disabled) {
                const ket = el.dataset.qpKunci || 'Lengkapi isian sebelumnya terlebih dahulu.';
                if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: ket, showConfirmButton: false, timer: 2500 });
                return;
            }
            target = el;
            semuaOpsi = ambilOpsi(el);

            // Judul modal mengikuti nama select-nya supaya jelas sedang
            // memilih apa, bukan "Pilih Data" generik.
            judul.textContent = 'Pilih ' + (el.dataset.qpLabel || el.name || 'Data')
                .replace(/[-_]/g, ' ')
                .replace(/\b\w/g, (c) => c.toUpperCase());

            input.value = '';
            gambar('');
            window.openModal('quick-picker');
            setTimeout(() => input.focus(), 80);
        }

        // Delegasi: daftar rebuilt setiap ketikan, jadi event-nya dipasang
        // satu kali di list, bukan di tiap tombol.
        list.addEventListener('click', function (e) {
            const btn = e.target.closest('button[data-value]');
            if (!btn || !target) return;

            const $ = window.jQuery;
            const el = target;
            el.value = btn.dataset.value;

            // Beri tahu Select2 dan form bahwa nilainya berubah.
            if ($) {
                const $el = $(el);
                if ($el.data('select2')) $el.val(btn.dataset.value).trigger('change.select2');
                $el.trigger('change');
            } else {
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }

            window.closeModal('quick-picker');
        });

        input.addEventListener('input', function () { gambar(this.value); });

        // Panah atas/bawah untuk pindah pilihan, Enter untuk memilih.
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const opsi = list.querySelectorAll('button[data-value]');
                if (!opsi.length) return;
                const sekarang = list.querySelector('button[data-value]:hover');
                if (e.key === 'ArrowDown' && !sekarang) opsi[0].focus();
                else if (sekarang) {
                    const i = Array.from(opsi).indexOf(sekarang);
                    const next = e.key === 'ArrowDown' ? opsi[i + 1] : opsi[i - 1];
                    if (next) next.focus();
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const fokus = list.querySelector('button[data-value]:hover') || list.querySelector('button[data-value]');
                if (fokus) fokus.click();
            }
        });

        /* Pasang tombol pada dropdown yang opsi-nya banyak.
           Berlaku untuk DUA bentuk dropdown:

             1. yang sudah jadi Select2 (kelas .select2-* / .select2-filter)
                -> tombol ditaruh di kanan kotak Select2

             2. select native yang belum di-Select2-kan
                -> tombol ditaruh di kanan select itu

           Cas kedua justru yang paling butuh: <select> native tidak punya
           kolom cari sama sekali, jadi 80-an pilihan harus digulir
           buta. Di aplikasi ini sebagian besar dropdown filter masih
           bentuk native.

           Select2 disembunyikan (class select2-hidden-accessible) dan
           digantikan kotak, jadi posisi kotak dicari lewat
           nextElementSibling, bukan dengan menebak struktur. */
        /* Ikon yang bisa dipakai sebagai pemicu. Field yang sudah punya
           ikon kaca pembesar tidak perlu tombol tambahan sama sekali -
           satu pemicu untuk satu field, bukan dua. */
        const IKON_CARI = /\b(fa-search|fa-magnifying-glass)\b/;

        function ikonCariYangSudahAda(el, anchor) {
            // Ikon biasanya saudara dari <select> di dalam .relative.
            const kandidat = [];
            if (el.previousElementSibling) kandidat.push(el.previousElementSibling);
            if (el.nextElementSibling) kandidat.push(el.nextElementSibling);
            if (anchor.previousElementSibling) kandidat.push(anchor.previousElementSibling);
            if (anchor.nextElementSibling) kandidat.push(anchor.nextElementSibling);
            return kandidat.find(function (n) {
                return n.tagName === 'I' && IKON_CARI.test(n.className) &&
                    getComputedStyle(n).position === 'absolute';
            }) || null;
        }

        /* Pasang pemicu cari cepat pada dropdown yang opsi-nya banyak.

           Bentuknya penting. Versi pertama menyisipkan <button> sebagai
           saudara baru tepat setelah field. Di dalam grid - yang dipakai
           hampir semua baris filter aplikasi ini - satu anak tambahan
           berarti satu sel grid tambahan, jadi seluruh isi baris itu
           bergeser dan lebar kolom tetangganya ikut berubah. Itulah
           keluhan "tombolnya mengganggu field lainnya".

           Perbaikannya: field dan tombol dibungkus satu <div> flex,
           lalu wrapper itu yang mengambil posisi field. Jumlah anak di
           dalam grid tetap sama, jadi tidak ada yang bergeser.

           Kalau field-nya sudah punya ikon kaca pembesar, ikon itu yang
           dijadikan pemicu dan tidak ada tombol yang ditambahkan. */
        window.qpPasang = function () {
            document.querySelectorAll('select').forEach(function (el) {
                if (el.multiple) return;
                // Baris hasil cloneNode membawa tanda "sudah dipasang" dan tombolnya, tetapi TIDAK membawa
                // penangan kliknya. Tombol salinan seperti itu dibuang supaya dipasang ulang dengan benar.
                if (el.dataset.qpPasang) {
                    const grupLama = el.closest('.qp-grup');
                    const tombolLama = grupLama ? grupLama.querySelector('.qp-tombol') : null;
                    if (!tombolLama || tombolLama._qpHidup) return;
                    tombolLama.remove();
                    grupLama.parentNode.insertBefore(el, grupLama);
                    grupLama.remove();
                    delete el.dataset.qpPasang;
                }
                if (el.hasAttribute('data-no-refresh')) return;
                if (el.options.length < BATAS) return;

                const $ = window.jQuery;
                // Dropdown dengan sumber AJAX sudah punya kolom cari
                // sendiri di dalam dropdown, jadi tidak perlu tambahan.
                if ($ && $(el).data('select2')?.options?.options?.ajax) return;

                // Kalau sudah jadi Select2, cari kotak yang menempel.
                let anchor = el;
                if ($ && $(el).data('select2')) {
                    const semuaKotak = Array.from(document.querySelectorAll('.select2-container'));
                    const menempel = semuaKotak.filter((k) => k.previousElementSibling === el);
                    if (menempel.length) anchor = menempel[menempel.length - 1];
                }

                el.dataset.qpPasang = '1';

                const bukaPencari = function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    buka(el);
                };

                // Kasus 1: sudah ada ikon cari, jadikan ikon itu pemicu.
                const ikon = ikonCariYangSudahAda(el, anchor);
                if (ikon) {
                    ikon.dataset.qpPemicu = '1';
                    ikon.style.cursor = 'pointer';
                    ikon.style.pointerEvents = 'auto';
                    ikon.addEventListener('click', bukaPencari);
                    ikon.setAttribute('title', 'Cari cepat (ketik satu huruf)');
                    return;
                }

                // Kasus 2: tidak ada ikon, pakai tombol yang berisi ikon.
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'qp-tombol';
                btn.title = 'Cari cepat (ketik satu huruf)';
                btn.setAttribute('aria-label', 'Cari cepat ' + (el.name || 'pilihan'));
                btn.innerHTML = '<i class="fas fa-magnifying-glass"></i>';
                btn.addEventListener('click', bukaPencari);
                btn._qpHidup = true; // properti JS tidak ikut tersalin oleh cloneNode
                // Tombol ikut terkunci bila dropdown-nya dikunci, dan ikut terbuka saat kuncinya dilepas.
                const samakanKunci = function () { btn.disabled = el.disabled; btn.classList.toggle('opacity-40', el.disabled); btn.classList.toggle('cursor-not-allowed', el.disabled); };
                samakanKunci();
                new MutationObserver(samakanKunci).observe(el, { attributes: true, attributeFilter: ['disabled'] });

                const induk = anchor.parentNode;
                const grup = document.createElement('div');
                grup.className = 'qp-grup';
                induk.insertBefore(grup, anchor);
                grup.appendChild(anchor);
                grup.appendChild(btn);
            });
        };

        document.addEventListener('DOMContentLoaded', function () {
            window.qpPasang();
            // Select2 diserialisasi setelah DOM siap; coba lagi setelah
            // jeda pendek supaya kotaknya sudah ada.
            setTimeout(window.qpPasang, 300);
            setTimeout(window.qpPasang, 1200);

            // Baris yang ditambahkan belakangan (Tambah Item, modal yang diisi ulang, hasil AJAX) juga harus
            // mendapat tombol cari. Sebelumnya pemasangan hanya terjadi saat halaman dimuat, jadi tombol
            // hilang mulai baris kedua. Pengamat ini memasang ulang setiap kali ada <select> baru di halaman.
            let jadwal = null;
            new MutationObserver(function (mutasi) {
                const adaSelectBaru = mutasi.some(function (m) {
                    return Array.prototype.some.call(m.addedNodes, function (n) {
                        if (n.nodeType !== 1 || n.classList.contains('qp-grup') || n.classList.contains('qp-tombol')) return false;
                        return n.matches('select, option') || !!n.querySelector('select');
                    });
                });
                if (!adaSelectBaru || jadwal) return;
                jadwal = setTimeout(function () { jadwal = null; window.qpPasang(); }, 60);
            }).observe(document.body, { childList: true, subtree: true });
        });
    })();
</script>
