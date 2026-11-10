@extends('layouts.app')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-6 max-w-[1100px] mx-auto">
    <x-page-header title="Asisten Orion (Lokal)" subtitle="Tanya jawab seputar Orion Grafika & toko - atau tempel order pelanggan untuk dipilah. Tanpa API, data tetap di browser Anda.">
        <x-slot:actions>
            <span class="inline-flex items-center gap-1.5 rounded-full border border-[#E7D9B6] bg-[#FDF6E3] px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.16em] text-[#C98A1B]"><i class="fas fa-gem"></i> Premium</span>
        </x-slot:actions>
    </x-page-header>

    <div class="grid lg:grid-cols-2 gap-6">
        {{-- INPUT --}}
        <div class="rounded-2xl border border-slate-200/70 bg-white p-6 shadow-[0_1px_3px_rgba(15,30,62,.05)]">
            <label for="teksChat" class="text-[11px] font-semibold uppercase tracking-widest text-slate-500">Tanya atau tempel teks</label>
            <textarea id="teksChat" rows="11" spellcheck="false" class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50/60 p-4 text-[13px] leading-relaxed text-slate-800 placeholder:text-slate-400 focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 focus:outline-none" placeholder="Contoh:&#10;- Halo, cara pesan gimana?&#10;- Berapa harga banner 2x1?&#10;- banner 3x1 meter 2 pcs, name tag 25 pcs untuk acara tanggal 24"></textarea>

            <div class="mt-3 flex flex-wrap gap-1.5" id="chipsContoh" aria-label="Contoh pertanyaan">
                <button type="button" data-tanya="Cara pesan?" class="chip-contoh">Cara pesan?</button>
                <button type="button" data-tanya="Berapa harga banner?" class="chip-contoh">Berapa harga?</button>
                <button type="button" data-tanya="Alamat dan nomor WA?" class="chip-contoh">Alamat &amp; WA?</button>
                <button type="button" data-tanya="Cara pembayaran?" class="chip-contoh">Pembayaran?</button>
                <button type="button" data-tanya="Berapa lama pengerjaan?" class="chip-contoh">Berapa lama?</button>
                <button type="button" data-tanya="Jam buka?" class="chip-contoh">Jam buka?</button>
                <button type="button" data-tanya="Apa saja layanan?" class="chip-contoh">Layanan?</button>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button type="button" id="btnAnalisis" class="inline-flex items-center gap-2 h-10 px-5 rounded-lg bg-slate-900 text-white text-[12.5px] font-semibold hover:bg-slate-800 transition-colors"><i class="fas fa-wand-magic-sparkles"></i> Tanya Asisten</button>
                <button type="button" id="btnBersih" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg border border-slate-200 text-[12px] font-semibold text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-colors"><i class="fas fa-eraser"></i> Bersihkan</button>
            </div>
            <p class="mt-4 text-[10.5px] leading-relaxed text-slate-400">Berjalan 100% di browser Anda: cocokkan pola pertanyaan + parsing order. Tidak ada yang dikirim ke server luar. Jawaban bersumber dari data toko (halaman depan, pengaturan aplikasi) - bukan karangan.</p>
        </div>

        {{-- HASIL --}}
        <div class="rounded-2xl border border-slate-200/70 bg-white p-6 shadow-[0_1px_3px_rgba(15,30,62,.05)] flex flex-col min-h-[420px]">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-[11px] font-semibold uppercase tracking-widest text-slate-500">Jawaban Asisten</h3>
                <button type="button" id="btnSalin" class="hidden inline-flex items-center gap-1.5 h-8 px-3 rounded-lg border border-slate-200 text-[11px] font-semibold text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-colors"><i class="far fa-copy"></i> Salin</button>
            </div>
            <div id="percakapan" class="mt-4 flex-1 flex flex-col gap-3 overflow-y-auto max-h-[480px] pr-1">
                <div id="hasilKosong" class="flex-1 grid place-items-center py-10 text-center">
                    <div>
                        <div class="w-16 h-16 mx-auto rounded-2xl bg-indigo-50 grid place-items-center text-indigo-400 text-2xl"><i class="fas fa-comment-dots"></i></div>
                        <p class="mt-4 text-[12px] font-medium text-slate-500">Tanya apa pun tentang Orion Grafika.</p>
                        <p class="mt-1 text-[10.5px] text-slate-400 leading-relaxed">"Cara pesan?", "Berapa harga?", "Alamat?"<br>atau tempel order pelanggan apa adanya.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .chip-contoh { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 12px; border-radius: 999px; border: 1px solid #E3E9F6; background: #F7F9FE; font-size: 10.5px; font-weight: 600; color: #3E4C74; transition: all .15s ease; cursor: pointer; }
    .chip-contoh:hover { border-color: #2563EB; color: #1D4ED8; background: #EFF4FF; transform: translateY(-1px); }
    .bubble { max-width: 92%; padding: 10px 13px; border-radius: 14px; font-size: 12.5px; line-height: 1.55; }
    .bubble-user { align-self: flex-end; background: #2563EB; color: #fff; border-bottom-right-radius: 4px; }
    .bubble-asisten { align-self: flex-start; background: #F1F5FA; color: #1E2A4A; border: 1px solid #E3E9F6; border-bottom-left-radius: 4px; }
    .bubble-asisten strong { color: #0F1E3E; }
    .item-card { align-self: stretch; border: 1px solid #E3E9F6; background: #FBFCFF; border-radius: 12px; padding: 10px 12px; }
</style>

<script>
(function () {
    var teks = document.getElementById('teksChat');
    var percakapan = document.getElementById('percakapan');
    var hasilKosong = document.getElementById('hasilKosong');
    var btnSalin = document.getElementById('btnSalin');
    var ringkasan = '';
    var counter = 0;

    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

    /* ============ BASIS PENGETAHUAN LOKAL (dari data toko) ============ */
    var ALAMAT = 'Jalan Jaksa Agung Suprapto No. 48, Pliyang - Madura, Sampang (Jawa Timur)';
    var WA = '082334075550';
    var EMAIL = 'grafikaorion@gmail.com';
    var KB = [
        { k: ['alamat', 'lokasi', 'dimana', 'di mana', 'toko', 'kantor', 'workshop', 'cabang'], j: 'Alamat kami: <strong>' + ALAMAT + '</strong>. Buka Google Maps dan cari "Orion Grafika Sampang".' },
        { k: ['telepon', 'no hp', 'nomor', 'kontak', 'wa', 'whatsapp', 'hubungi', 'call'], j: 'Telepon / WhatsApp: <strong>' + WA + '</strong><br>Email: <strong>' + EMAIL + '</strong><br>Website: <strong>oriongrafika.com</strong>' },
        { k: ['email', 'surel', 'mail'], j: 'Email kami: <strong>' + EMAIL + '</strong>' },
        { k: ['website', 'situs', 'web'], j: 'Website: <strong>http://oriongrafika.com</strong>' },
        { k: ['rekening', 'transfer', 'tf', 'bca', 'bri', 'atm', 'bank'], j: 'Transfer ke:<br>• <strong>BCA 896 0407 068</strong><br>• <strong>BRI 01480 1001 350561</strong><br>a.n. Khaironi Firdiansyah.<br>Setelah transfer, kirim bukti ke WA <strong>' + WA + '</strong>.' },
        { k: ['cara pesan', 'pesan bagaimana', 'bagaimana pesan', 'proses pesan', 'order bagaimana', 'cara order', 'gimana cara pesan', 'prosedur'], j: 'Cara pesan:<br><strong>1)</strong> Kirim kebutuhan (jenis barang, ukuran, jumlah, file/desain) ke WA <strong>' + WA + '</strong> atau datang langsung.<br><strong>2)</strong> Kami buatkan nota.<br><strong>3)</strong> Bayar DP atau lunas.<br><strong>4)</strong> Produksi (Anda kami kabari saat selesai).<br><strong>5)</strong> Bawa nota saat pengambilan.' },
        { k: ['pembayaran', 'bayar', 'dp', 'lunas', 'cicil', 'cash', 'tunai'], j: 'Pembayaran bisa <strong>transfer bank atau tunai</strong>. Untuk pesanan dengan desain khusus, ketentuan DP perlu dikonfirmasi ke operator dulu.' },
        { k: ['lama', 'berapa lama', 'kapan jadi', 'kapan selesai', 'tenggat', 'estimasi'], j: '<strong>Sehari langsung ready</strong>, dengan ketentuan file sudah siap dari Anda. Semakin besar volume/finishing, bisa lebih lama - estimasi pasti kami sampaikan saat pesan.' },
        { k: ['jam', 'buka', 'tutup', 'operasional', 'libur'], j: 'Jam operasional terkini, silakan tanyakan langsung ke WA <strong>' + WA + '</strong> - kami jawab saat toko buka.' },
        { k: ['harga', 'berapa harganya', 'berapa harga', 'biaya', 'tarif', 'estimasi harga', 'ongkir'], j: 'Harga tergantung jenis barang, ukuran, bahan dan jumlah.<br><br>Untuk estimasi cepat, sebutkan contohnya: <strong>\'banner 2x1 m 2 pcs\'</strong> atau <strong>\'poster A3 50 lembar\'</strong>. Atau langsung tanya ke WA <strong>' + WA + '</strong>.' },
        { k: ['minimal', 'minimum', 'min order', 'min. order', 'paling sedikit'], j: 'Cetak digital kecil umumnya bisa satuan. Untuk finishing tertentu ada minimum tertentu - tanya detail ke WA <strong>' + WA + '</strong>.' },
        { k: ['file', 'format', 'dpi', 'artwork', 'desain saya', 'kirim desain'], j: 'Kirim file <strong>PDF / JPG / PNG resolusi tinggi</strong>, font di-<em>outline</em> supaya tidak berubah saat dibuka.<br><br>Belum punya desain? Tim kami bisa bantu buatkan.' },
        { k: ['revisi', 'koreksi', 'edit desain', 'ganti desain'], j: 'Revisi minor bisa dilakukan <strong>sebelum masuk produksi</strong>. Perubahan besar dihitung sebagai desain baru dengan estimasi waktu tambahan.' },
        { k: ['garansi', 'klaim', 'komplain', 'rusak', 'cacat', 'salah cetak'], j: 'Bawa nota ke toko - kami periksa hasil cetaknya dan proses klaim secepatnya.' },
        { k: ['lacak', 'tracking', 'status pesanan', 'status produksi', 'pesanan saya'], j: 'Minta kasir membuat <strong>tautan Lacak Pelanggan</strong> (menu Premium) - progres produksi bisa dipantau langsung tanpa perlu telepon.' },
        { k: ['layanan', 'apa saja', 'bisa apa', 'produk', 'jasa'], j: 'Kami mengerjakan: <strong>spanduk &amp; banner, stiker, cetak foto, brosur, kartu nama, undangan, DTF &amp; sublim, jersey custom, ID card</strong>, dan kebutuhan cetak lainnya. Di luar daftar? Tanya saja, sering tetap bisa.' },
        { k: ['jersey'], j: 'Jersey custom: nama dan nomor per pemain, semua ukuran. Kirim desain/referensi ke WA <strong>' + WA + '</strong>.' },
        { k: ['spanduk', 'banner', 'x-banner', 'roll up'], j: 'Spanduk/banner &amp; X-banner tersedia berbagai ukuran. Sebutkan ukuran &amp; jumlahnya (contoh: "banner 2x1 m 2 pcs") ke WA <strong>' + WA + '</strong> untuk estimasi.' },
        { k: ['terima kasih', 'makasih', 'thanks', 'thx'], j: 'Sama-sama! Senang bisa membantu. Kalau ada pertanyaan lain, tanya lagi saja.' },
        { k: ['halo', 'hai', 'pagi', 'siang', 'sore', 'malam', 'assalamualaikum', 'permisi'], j: 'Halo! Saya Asisten Orion Grafika. Mau cek <strong>harga</strong>, <strong>cara pesan</strong>, atau tulis langsung order Anda di bawah?' }
    ];

    function jawabanKB(q) {
        var s = String(q).toLowerCase();
        for (var i = 0; i < KB.length; i++) {
            for (var j = 0; j < KB[i].k.length; j++) {
                if (s.indexOf(KB[i].k[j]) !== -1) return KB[i].j;
            }
        }
        return null;
    }

    /* ============ PARSER ORDER (lokal) ============ */
    function parseOrder(raw) {
        var lines = String(raw || '').split(/\r?\n|[•\-*]\s+/).map(function (s) { return s.trim(); }).filter(function (s) { return s.length > 2; });
        var items = [];
        lines.forEach(function (baris) {
            var mUkuran = baris.match(/(\d+(?:[.,]\d+)?)\s*(?:x|×|\*)\s*(\d+(?:[.,]\d+)?)\s*(cm|mm|m\b|meter)?/i);
            var kertas = baris.match(/\b(A[0-9](?:[- ]?(?:plus|\+|premium))?|F4|Folio|A3\+)\b/i);
            var mQty = baris.match(/(\d+)\s*(pcs?|lembar|lbr|buah|unit|eksemplar|ex|set|box|rim)\b/i) || baris.match(/\bqty\s*[:=]?\s*(\d+)/i);
            var mTenggat = baris.match(/(besok|lusa|minggu depan|senin|selasa|rabu|kamis|jumat|sabtu|minggu|tanggal\s*\d{1,2}(?:\s*(januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember))?)/i);
            var fin = (baris.match(/(laminasi doff|laminasi glossy|laminasi matte|doff|glossy|matte|cutting|pond|die ?cut|potong|jilid|spiral|hardcover|vinyl|outdoor|indoor|full ?color|hitam putih)/ig) || []).map(function (s) { return s.toLowerCase(); });
            var qty = mQty ? parseInt(mQty[1], 10) : null;
            var ukuran = mUkuran ? (mUkuran[1] + ' × ' + mUkuran[2] + (mUkuran[3] ? ' ' + mUkuran[3].replace('meter', 'm') : ' m')) : (kertas ? kertas[1].toUpperCase() : null);
            var isItem = qty || ukuran || /banner|poster|stiker|name ?tag|brosur|kartu nama|flyer|x-?banner|roll ?up|spanduk|undangan|buku|katalog|label|packaging|packing|nota|amplop/i.test(baris);
            if (!isItem) return;
            var nama = baris
                .replace(/(\d+)\s*(pcs?|lembar|lbr|buah|unit|eksemplar|ex|set|box|rim)\b/ig, '')
                .replace(/(\d+(?:[.,]\d+)?)\s*(x|×|\*)\s*(\d+(?:[.,]\d+)?)\s*(cm|mm|m\b|meter)?/ig, '')
                .replace(/\s{2,}/g, ' ').replace(/^[,.\-\s]+|[,.\-\s]+$/g, '').trim();
            items.push({ nama: nama.charAt(0).toUpperCase() + nama.slice(1), qty: qty, ukuran: ukuran, tenggat: mTenggat ? mTenggat[1] : null, finishing: Array.from(new Set(fin)) });
        });
        return items;
    }

    /* ============ RENDER ============ */
    function tambahBubble(html, kelas) {
        if (hasilKosong && hasilKosong.parentNode) hasilKosong.remove();
        var div = document.createElement('div');
        div.className = 'bubble ' + kelas;
        div.innerHTML = html;
        percakapan.appendChild(div);
        percakapan.scrollTop = percakapan.scrollHeight;
    }

    function bersihkanHasil() {
        percakapan.innerHTML = '';
        ringkasan = '';
        btnSalin.classList.add('hidden');
        counter = 0;
    }

    function tampilkanOrder(items) {
        ringkasan = 'Ringkasan Order (draf):\n' + items.map(function (it, i) {
            return (i + 1) + '. ' + it.nama + (it.qty ? ' - qty ' + it.qty : '') + (it.ukuran ? ' - ' + it.ukuran : '') + (it.finishing.length ? ' - finishing: ' + it.finishing.join(', ') : '') + (it.tenggat ? ' - tenggat: ' + it.tenggat : '');
        }).join('\n');

        tambahBubble('Saya menemukan <strong>' + items.length + ' item</strong> dari teks Anda:', 'bubble-asisten');
        items.forEach(function (it, i) {
            var chips = '';
            if (it.qty) chips += '<span class="inline-flex items-center gap-1 rounded-md bg-indigo-50 border border-indigo-100 px-2 py-1 text-[10px] font-semibold text-indigo-700"><i class="fas fa-cubes"></i> Qty ' + esc(it.qty) + '</span>';
            if (it.ukuran) chips += '<span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 border border-emerald-100 px-2 py-1 text-[10px] font-semibold text-emerald-700"><i class="fas fa-ruler-combined"></i> ' + esc(it.ukuran) + '</span>';
            if (it.tenggat) chips += '<span class="inline-flex items-center gap-1 rounded-md bg-rose-50 border border-rose-100 px-2 py-1 text-[10px] font-semibold text-rose-600"><i class="far fa-clock"></i> ' + esc(it.tenggat) + '</span>';
            if (it.finishing.length) chips += '<span class="inline-flex items-center gap-1 rounded-md bg-amber-50 border border-amber-100 px-2 py-1 text-[10px] font-semibold text-amber-800"><i class="fas fa-layer-group"></i> ' + esc(it.finishing.join(', ')) + '</span>';
            var card = document.createElement('div');
            card.className = 'item-card';
            card.innerHTML = '<div class="flex items-start gap-3"><span class="w-6 h-6 shrink-0 rounded-md bg-slate-900 text-white text-[11px] font-bold grid place-items-center mt-0.5">' + (i + 1) + '</span><div class="min-w-0"><p class="text-[12.5px] font-semibold text-slate-800 leading-snug">' + esc(it.nama) + '</p>' + (chips ? '<div class="mt-2 flex flex-wrap gap-1.5">' + chips + '</div>' : '') + '</div></div>';
            percakapan.appendChild(card);
        });
        var ket = document.createElement('p');
        ket.className = 'self-start text-[10.5px] text-slate-400 px-1';
        ket.innerHTML = 'Ini draf hasil pilah otomatis - operator tetap yang memasukkan ke nota. Tombol Salin di kanan atas menyalin ringkasan siap kirim.';
        percakapan.appendChild(ket);
        percakapan.scrollTop = percakapan.scrollHeight;
        btnSalin.classList.remove('hidden');
    }

    function tanya() {
        var q = teks.value.trim();
        if (!q) { teks.focus(); return; }
        tambahBubble(esc(q), 'bubble-user');

        // Order-like? Pertanyaan? Pertanyaan menang atas parser.
        var pesanan = parseOrder(q);
        var tanyaJawab = /\b(berapa|gimana|bagaimana|kapan|di mana|apa saja|bolehkah|apakah|harga|bisa|cara)\b/i.test(q);

        if (pesanan.length && !tanyaJawab) { tampilkanOrder(pesanan); return; }

        var kb = jawabanKB(q);
        if (kb) {
            ringkasan = kb.replace(/<[^>]+>/g, '');
            tambahBubble(kb, 'bubble-asisten');
            btnSalin.classList.remove('hidden');
            return;
        }
        if (pesanan.length) { tampilkanOrder(pesanan); return; }

        ringkasan = 'Hubungi WA ' + WA + ' untuk jawaban pasti.';
        tambahBubble(
            'Saya belum punya jawaban pasti untuk itu.<br><br>' +
            'Coba tanya soal: <strong>alamat</strong>, <strong>WA</strong>, <strong>cara pesan</strong>, <strong>harga</strong>, <strong>pembayaran</strong>, <strong>lama pengerjaan</strong>, atau <strong>layanan</strong>.<br><br>' +
            'Atau tempel order pelanggan, contoh: <em>"banner 3x1 m 2 pcs, poster A3 50 lembar"</em>.<br><br>' +
            'Butuh manusia? Langsung WA <strong>' + WA + '</strong>.',
            'bubble-asisten'
        );
        btnSalin.classList.remove('hidden');
    }

    document.getElementById('btnAnalisis').addEventListener('click', tanya);
    teks.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); tanya(); }
    });
    document.getElementById('btnBersih').addEventListener('click', function () {
        teks.value = '';
        bersihkanHasil();
        var p = document.createElement('div');
        p.id = 'hasilKosong';
        p.className = 'flex-1 grid place-items-center py-10 text-center';
        p.innerHTML = '<div><div class="w-16 h-16 mx-auto rounded-2xl bg-indigo-50 grid place-items-center text-indigo-400 text-2xl"><i class="fas fa-comment-dots"></i></div><p class="mt-4 text-[12px] font-medium text-slate-500">Tanya apa pun tentang Orion Grafika.</p><p class="mt-1 text-[10.5px] text-slate-400 leading-relaxed">"Cara pesan?", "Berapa harga?", "Alamat?"<br>atau tempel order pelanggan apa adanya.</p></div>';
        percakapan.appendChild(p);
        hasilKosong = p;
        teks.focus();
    });
    document.querySelectorAll('.chip-contoh').forEach(function (chip) {
        chip.addEventListener('click', function () {
            teks.value = chip.getAttribute('data-tanya') || '';
            tanya();
        });
    });
    btnSalin.addEventListener('click', async function () {
        try { await navigator.clipboard.writeText(ringkasan || ''); btnSalin.innerHTML = '<i class="fas fa-check text-emerald-600"></i> Tersalin'; setTimeout(function(){ btnSalin.innerHTML = '<i class="far fa-copy"></i> Salin'; }, 1600); } catch (e) {}
    });
})();
</script>
@endsection
