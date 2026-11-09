{{--
    SCROLL REVEAL - satu-satunya pemicu animasi masuk.

    Mengapa ada partial ini:
      Animasi yang didefinisikan sebagai keyframe CSS otomatis sudah selesai
      berjalan sebelum pengunjung menggulir ke bawah fold, jadi efeknya tidak
      pernah terlihat. Di sini animasi HANYA dipicu ketika elemen benar-benar
      masuk viewport (IntersectionObserver), sekali saja.

    Prinsip:
      - Tanpa JS, tanpa observer, atau reduced-motion -> konten selalu
        terlihat. .lp-motion-on hanya ditambahkan oleh JS.
      - Yang dianimasikan hanya transform & opacity.
      - Observer dipasang SATU kali (guard __lpReveal) dan setiap elemen
        di-unobserve setelah muncul.
      - Elemen yang dirender belakangan (mis. konten dinamis) ikut dipindai
        ulang lewat MutationObserver.
      - Listener scroll berat tidak dipakai sama sekali.
--}}

@php
    // Kelas kartu yang diberi efek fade-up berjenjang (stagger).
    $lpKartu = '.lp-feature, .card, article.lp-product, article.lp-portfolio-card, article.lp-posts-card, .faq-item';
    // Gambar yang masuk dari kanan. Hero paling lama, sisanya lebih cepat.
    $lpGbrKanan = '.lp-hero-visual img, .lp-hero-gambar img, .lp-about-visual img, .lp-portfolio-card img, article.lp-posts-card img, article.lp-product img, .lp-product-tile img';
@endphp

<script>
(function () {
    'use strict';

    // Idempoten: partial bisa ikut ter-render lebih dari sekali.
    if (window.__lpReveal) { return; }
    window.__lpReveal = true;

    // 1) Reduced motion -> jangan sembunyikan apa pun.
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    // 2) Browser tanpa IntersectionObserver -> konten tampil normal.
    if (!('IntersectionObserver' in window)) { return; }

    document.documentElement.classList.add('lp-motion-on');

    var KARTU = @js($lpKartu);
    var GBR_KANAN = @js($lpGbrKanan);
    var sudah = new WeakSet();

    function siapkan(el) {
        if (!el || sudah.has(el)) return;
        sudah.add(el);

        el.classList.add('lp-reveal');

        if (el.matches(GBR_KANAN)) {
            el.classList.add('lp-reveal--right');
            el.style.setProperty('--lp-reveal-dur', el.closest('.lp-hero-visual, .lp-hero-gambar') ? '800ms' : '700ms');
        } else {
            el.classList.add('lp-reveal--up');
            el.style.setProperty('--lp-reveal-dur', '650ms');
        }
    }

    function siapkanSection(section) {
        if (!section || section.__lpRevealSiap) return;
        section.__lpRevealSiap = true;

        // Heading & teks: fade-up 24px.
        section.querySelectorAll('h1, h2, h3, .lp-eyebrow, .lp-lead, .lp-footer-desc, .lp-footer-copy, .lp-footer-label, .lp-contact-label')
            .forEach(siapkan);

        // Gambar: slide-in dari kanan.
        section.querySelectorAll(GBR_KANAN).forEach(siapkan);

        // Kartu: fade-up dengan stagger 70ms per kartu (maksimum 5 langkah,
        // supaya kelompok besar tidak tertunda terlalu lama).
        var idx = 0;
        section.querySelectorAll(KARTU).forEach(function (el) {
            siapkan(el);
            el.style.setProperty('--lp-reveal-delay', (70 * (idx % 5)) + 'ms');
            idx++;
        });
    }

    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            io.unobserve(entry.target);
        });
    }, {
        threshold: 0.12,
        rootMargin: '0px 0px -6% 0px'
    });

    function pindai(rootEl) {
        (rootEl || document).querySelectorAll('[data-section-type]').forEach(function (s) {
            siapkanSection(s);
            s.querySelectorAll('.lp-reveal').forEach(function (el) { io.observe(el); });
        });
    }

    pindai();

    // Konten yang dirender belakangan tetap dapat efek yang sama.
    new MutationObserver(function () { pindai(); })
        .observe(document.body, { childList: true, subtree: true });
})();
</script>