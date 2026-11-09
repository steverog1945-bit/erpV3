{{-- Bantuan isian qty per ukuran: total per kelompok & per barang (live), Enter = ukuran berikutnya. Diinclude sekali per halaman. --}}
<script>
(function () {
    function hitungKartu(kartu) {
        let total = 0;
        kartu.querySelectorAll('.grup-ukuran').forEach(g => {
            let sub = 0;
            g.querySelectorAll('.input-qty-ukuran').forEach(i => { sub += parseInt(i.value) || 0; });
            const el = g.querySelector('.qty-total-grup'); if (el && el.textContent !== String(sub)) el.textContent = sub;
            total += sub;
        });
        const t = kartu.querySelector('.qty-total-line'); if (t && t.textContent !== String(total)) t.textContent = total;
    }
    document.addEventListener('input', e => {
        if (!e.target.classList?.contains('input-qty-ukuran')) return;
        const kartu = e.target.closest('.jersey-line-card') || e.target.closest('form') || document;
        hitungKartu(kartu);
    });
    // Enter: pindah ke isian ukuran berikutnya (bukan kirim form).
    document.addEventListener('keydown', e => {
        if (e.key !== 'Enter' || !e.target.classList?.contains('input-qty-ukuran')) return;
        e.preventDefault();
        const semua = [...(e.target.closest('.wrapper-qty-ukuran')?.querySelectorAll('.input-qty-ukuran') || [])];
        const nx = semua[semua.indexOf(e.target) + 1];
        if (nx) { nx.focus(); nx.select(); }
    });
    // Hitung awal untuk kartu yang sudah terisi (edit) atau baru ditambahkan.
    function awal() { document.querySelectorAll('.jersey-line-card').forEach(hitungKartu); }
    window.hitungUlangQtyUkuran = awal;
    document.addEventListener('DOMContentLoaded', awal);
    new MutationObserver(awal).observe(document.body, { childList: true, subtree: true });
})();
</script>
