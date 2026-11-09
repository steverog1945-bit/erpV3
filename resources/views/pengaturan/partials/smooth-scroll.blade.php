{{--
    SMOOTH SCROLL DENGAN OFFSET HEADER STICKY YANG DINAMIS.

    Dipakai bersama oleh: Landing Page publik, Preview Builder, halaman
    artikel, dan beranda fallback. Offset TIDAK lagi angka tetap (dulu 64px)
    karena tinggi header berbeda antar breakpoint: ~65px di Desktop, ~89px di
    Mobile 375px. Angka tetap membuat judul section tertutup header.

    Perilaku:
      - Offset = tinggi header AKTUAL + jarak aman (GAP).
      - ResizeObserver + listener resize menjaga offset tetap akurat saat
        tinggi header berubah. SATU observer saja; partial ini dijaga agar
        idempoten lewat window.__lpSmoothScroll sehingga partial yang
        dirender ulang tidak menumpuk listener.
      - Menghormati prefers-reduced-motion: scroll jadi 'auto' (datar).
      - Anchor yang targetnya tidak ada di halaman ini TIDAK dicegah, jadi
        perilaku bawaan browser tetap berlaku dan tidak ada klik mati.
      - Link yang menuju halaman lain (mis. "#tentang" setelah base URL)
        tidak diintersep.
--}}
<script>
(function () {
    'use strict';

    // Guard idempoten: partial ini bisa ikut ter-render lebih dari sekali
    // pada satu dokumen. Tanpa guard, setiap kemunculan akan menambah
    // listener klik baru dan satu klik bisa memicu beberapa kali scroll.
    if (window.__lpSmoothScroll) { return; }
    window.__lpSmoothScroll = true;

    var GAP = 16;                  // jarak aman di bawah header
    var headerEl = null;
    var offset = GAP;

    function cariHeader() {
        return document.querySelector('header.lp-header')
            || document.querySelector('header.bl-kepala')
            || document.querySelector('.lp-header');
    }

    function ukur() {
        headerEl = cariHeader();
        var tinggi = headerEl ? Math.round(headerEl.getBoundingClientRect().height) : 0;
        offset = (tinggi > 0 ? tinggi : GAP) + GAP;
    }

    function kurangiGerak() {
        return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    }

    document.addEventListener('click', function (e) {
        var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!a) return;

        var href = a.getAttribute('href') || '';
        if (href.charAt(0) !== '#' || href.length < 2) return;   // bukan anchor lokal

        var id = href.substring(1);
        var el = document.getElementById(id);
        if (!el) return;   // target tidak ada di halaman ini -> biarkan browser

        e.preventDefault();

        // "Beranda" adalah header itu sendiri: tujuan scrollnya paling atas.
        if (el.tagName === 'HEADER' || id === 'beranda') {
            window.scrollTo({ top: 0, behavior: kurangiGerak() ? 'auto' : 'smooth' });
        } else {
            var top = el.getBoundingClientRect().top + window.pageYOffset - offset;
            window.scrollTo({ top: Math.max(0, top), behavior: kurangiGerak() ? 'auto' : 'smooth' });
        }

        if (history.replaceState) { history.replaceState(null, '', href); }
    });

    ukur();

    // Offset selalu mengikuti tinggi header saat ini. ResizeObserver pada
    // elemen header + resize window menutupi perubahan dari breakpoint,
    // font yang dimuat belakangan, dan hamburger mobile. Observer dipasang
    // satu kali saja (guard di atas), jadi tidak pernah menumpuk.
    if (headerEl && window.ResizeObserver) {
        new ResizeObserver(ukur).observe(headerEl);
    }
    window.addEventListener('resize', ukur);
    window.addEventListener('load', ukur);
})();
</script>