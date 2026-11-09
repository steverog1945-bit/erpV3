{{--
    Partial bersama untuk pemisah ribuan pada input nominal - generalisasi
    dari formatRupiah()/cleanRupiah() yang sebelumnya cuma ada lokal di
    kasir/form_pembayaran.blade.php. Include sekali per halaman, lalu:
    - beri class "format-ribuan" pada <input type="text" inputmode="numeric">
      supaya otomatis tampil "1.000.000" saat diketik.
    - kalau halaman punya JS kalkulasi sendiri yang membaca nilai input ini,
      baca lewat cleanRupiah(el.value) bukan parseFloat(el.value) langsung.
    - saat form displaya name="..." langsung (bukan lewat JS fetch), value
      yang dikirim ke server otomatis dibersihkan dari titik oleh listener
      submit di bawah - controller tetap terima angka polos seperti biasa.

    PENTING - bug yang pernah terjadi & wajib dihindari lagi: formatRupiah()
    HANYA aman dipanggil dengan string HASIL KETIKAN USER (boleh sudah ada
    titik ribuan dari pemanggilan formatRupiah() sebelumnya, mis. "20.000").
    JANGAN PERNAH memanggil formatRupiah(data.sesuatu) langsung dengan nilai
    mentah dari DB/JSON (mis. hasil json_encode($model) atau fetch API) -
    kolom decimal(15,2) di Laravel di-serialize sebagai STRING "20000.00",
    dan formatRupiah() akan salah membaca titik desimal itu sebagai
    pemisah ribuan (dua digit "00" di belakang titik ikut kehitung jadi
    angka signifikan) - "20000.00" bisa berubah jadi "2.000.000" (100x
    lipat). Untuk populate field edit dari data mentah, SELALU pakai
    formatRupiahDariAngka() di bawah, bukan formatRupiah() langsung.
--}}
@once
<script>
    function formatRupiah(angka) {
        let string = angka.toString().replace(/[^0-9]/g, "");
        return string.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    // Dipakai khusus untuk populate field edit dari nilai mentah DB/JSON
    // (mis. data.harga_promo dari json_encode($model), atau hasil fetch API)
    // - parseFloat dulu supaya ".00" desimal terbaca benar sebagai pecahan
    // (dibuang, bukan disambung jadi digit), baru dibulatkan ke rupiah utuh.
    function formatRupiahDariAngka(nilai) {
        return formatRupiah(Math.round(parseFloat(nilai) || 0));
    }

    function cleanRupiah(string) {
        if (!string) return 0;
        return parseFloat(string.toString().replace(/\./g, '')) || 0;
    }

    // ---- Kolom uang yang boleh berdesimal (mis. harga beli Rp 281.057,64): class "format-ribuan-desimal".
    // Saat mengetik: titik = pemisah ribuan otomatis, KOMA = desimal (maks. data-desimal, bawaan 2).
    // Yang terkirim ke server selalu angka polos "281057.64" (lihat listener submit di bawah).
    function formatUangDesimal(teks, maksDesimal) {
        maksDesimal = maksDesimal === undefined ? 2 : maksDesimal;
        let s = String(teks).replace(/[^0-9,]/g, '');
        const i = s.indexOf(',');
        let bulat = i === -1 ? s : s.slice(0, i);
        let desimal = i === -1 ? null : s.slice(i + 1).replace(/,/g, '').slice(0, maksDesimal);
        bulat = bulat.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return desimal === null ? bulat : bulat + ',' + desimal;
    }

    // "281.057,64" -> 281057.64 (angka). Aman untuk teks kosong.
    function cleanUangDesimal(teks) {
        if (!teks) return 0;
        return parseFloat(String(teks).replace(/\./g, '').replace(',', '.')) || 0;
    }

    // Isi awal kolom dari nilai mentah DB/JSON ("281057.64" atau 281057.64) -> "281.057,64" (nol pecahan dibuang).
    function formatUangDariAngka(nilai, maksDesimal) {
        const n = parseFloat(nilai);
        if (isNaN(n)) return '';
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: maksDesimal === undefined ? 2 : maksDesimal }).format(n);
    }

    // Tampilan angka (bukan input) berformat Indonesia, nol pecahan dipotong: angkaId(200000, 0) = "200.000", angkaId(12.5) = "12,5".
    function angkaId(nilai, maksDesimal) {
        const n = parseFloat(nilai);
        if (isNaN(n)) return '0';
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: maksDesimal === undefined ? 2 : maksDesimal }).format(n);
    }

    document.addEventListener('input', function (e) {
        if (!e.target.matches) return;
        if (e.target.matches('input.format-ribuan')) {
            e.target.value = formatRupiah(e.target.value);
        } else if (e.target.matches('input.format-ribuan-desimal')) {
            const max = e.target.dataset.desimal !== undefined ? parseInt(e.target.dataset.desimal, 10) : 2;
            e.target.value = formatUangDesimal(e.target.value, max);
        }
    });

    // Jaring pengaman terakhir sebelum form dikirim - pastikan nilai yang benar-benar terkirim ke server
    // selalu angka polos, tanpa pemisah ribuan (titik) dan tanpa koma desimal.
    // Berlaku untuk submit biasa (event submit) DAN form.submit() lewat kode (yang tidak memicu event).
    // Kolom berdesimal dipulihkan ke tampilan berformat setelah nilai terkirim, supaya halaman/modal
    // yang tetap terbuka (AJAX) tidak menampilkan angka polos yang membingungkan.
    function bersihkanFormUang(form) {
        form.querySelectorAll('input.format-ribuan').forEach(function (input) {
            input.value = cleanRupiah(input.value);
        });
        form.querySelectorAll('input.format-ribuan-desimal').forEach(function (input) {
            if (input.value.trim() === '') return;
            if (input.dataset.bersih !== undefined && input.value === input.dataset.bersih) return; // sudah bersih (hindari dobel)
            const tampilan = input.value;
            const bersih = String(cleanUangDesimal(tampilan));
            input.value = bersih;
            input.dataset.bersih = bersih;
            setTimeout(function () { if (input.value === bersih) input.value = tampilan; }, 0);
        });
    }

    document.addEventListener('submit', function (e) {
        if (e.target instanceof HTMLFormElement) bersihkanFormUang(e.target);
    });

    const submitAsli = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        bersihkanFormUang(this);
        return submitAsli.call(this);
    };
</script>
@endonce
