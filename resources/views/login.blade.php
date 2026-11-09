@extends('publik.layout')

@php
    $set = \App\Models\AppSetting::current();
    $nama = $set->nama_aplikasi;
    // Nomor WhatsApp dari Pengaturan Aplikasi (08xx -> 628xx). Tanpa nomor, tombolnya tidak ditampilkan.
    $wa = preg_replace('/\D+/', '', (string) $set->telepon);
    $wa = $wa !== '' ? (str_starts_with($wa, '0') ? '62' . substr($wa, 1) : $wa) : null;
    $tautWa = $wa ? 'https://wa.me/' . $wa . '?text=' . urlencode('Halo ' . $nama . ', saya ingin bertanya soal pesanan cetak.') : null;
    $sampulHero = $unggulan && $unggulan->url_sampul ? $unggulan : null;
    // Kelompok layanan, disusun dari katalog produk yang memang ada di sistem (bukan daftar karangan).
    $layanan = [
        ['nama' => 'Spanduk dan banner', 'ket' => 'Flexi, X-banner, roll up, tripod banner'],
        ['nama' => 'Stiker', 'ket' => 'Vinil, transparan, hologram, one way vision'],
        ['nama' => 'Cetak foto', 'ket' => '2R sampai 24R, kanvas, laminasi'],
        ['nama' => 'Brosur dan kartu nama', 'ket' => 'Art paper, art carton, satu atau dua sisi'],
        ['nama' => 'Undangan dan amplop', 'ket' => 'Linen, ukuran custom'],
        ['nama' => 'DTF dan sublim', 'ket' => 'A5 sampai A3, film dan kertas sublim'],
        ['nama' => 'Jersey custom', 'ket' => 'Nama dan nomor per pemain, semua ukuran'],
        ['nama' => 'ID card dan paperbag', 'ket' => 'PVC, laminasi doff atau glossy'],
    ];
@endphp

@section('judul', $nama)
@section('deskripsi', 'Percetakan ' . $nama . ': layanan, cara pesan, kabar, dan kontak.')

@section('gaya')
        /* ---------- Pembuka ---------- */
        .bl-hero { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 3.5rem; align-items: center; padding: 4.5rem 0 4rem; }
        .bl-hero h1 { font-weight: 650; font-size: clamp(2.25rem, 4.6vw, 3.5rem); line-height: 1.06; letter-spacing: -0.035em; margin: 0; text-wrap: balance; }
        .bl-hero p.lead { margin: 1.25rem 0 0; font-size: 1.125rem; color: var(--tinta-2); max-width: 44ch; }
        .bl-aksi { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 2rem; }
        .bl-aksi .bl-tombol { height: 46px; padding: 0 1.375rem; }
        .bl-hero-gambar { position: relative; display: block; }
        .bl-hero-gambar .bl-gambar { aspect-ratio: 4 / 3; border-radius: 10px; }
        .bl-hero-ket { display: flex; justify-content: space-between; gap: 1rem; margin-top: 0.75rem; font-size: 0.875rem; color: var(--tinta-3); }
        .bl-hero-ket b { color: var(--tinta); font-weight: 500; }

        /* ---------- Bagian umum ---------- */
        .bl-bagian { padding: 4rem 0; border-top: 1px solid var(--garis); scroll-margin-top: 64px; }
        .bl-bagian-kepala { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.6fr); gap: 2rem; margin-bottom: 2.5rem; }
        .bl-bagian h2 { font-weight: 650; font-size: clamp(1.5rem, 2.4vw, 2rem); line-height: 1.15; letter-spacing: -0.025em; margin: 0; }
        .bl-bagian-kepala p { margin: 0; color: var(--tinta-2); max-width: 52ch; align-self: end; }

        /* Layanan: daftar dua kolom bergaris, bukan kartu berikon. */
        .bl-layanan { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 3rem; }
        .bl-layanan li { display: flex; align-items: baseline; justify-content: space-between; gap: 1rem; padding: 0.9375rem 0; border-top: 1px solid var(--garis); font-size: 1.0625rem; font-weight: 500; }
        .bl-layanan li span { font-size: 0.875rem; font-weight: 400; color: var(--tinta-3); }

        /* Cara pesan: empat langkah bernomor. */
        .bl-langkah { list-style: none; margin: 0; padding: 0; counter-reset: langkah; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 2rem; }
        .bl-langkah li { counter-increment: langkah; padding-top: 1rem; border-top: 2px solid var(--tinta); color: var(--tinta-2); font-size: 0.9375rem; }
        .bl-langkah li::before { content: '0' counter(langkah); display: block; font-size: 0.875rem; color: var(--tinta-3); font-variant-numeric: tabular-nums; }
        .bl-langkah b { display: block; margin: 0.375rem 0 0.375rem; font-size: 1.0625rem; font-weight: 600; color: var(--tinta); }

        /* ---------- Tulisan ---------- */
        .bl-utama { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr); gap: 2.5rem; align-items: center; padding-bottom: 3rem; }
        .bl-utama h3 { font-weight: 650; font-size: clamp(1.5rem, 2.4vw, 2rem); line-height: 1.15; letter-spacing: -0.025em; margin: 0.625rem 0 0; text-wrap: balance; }
        .bl-utama .bl-ringkas { font-size: 1.0625rem; -webkit-line-clamp: 4; }
        .bl-baca { display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 1.25rem; font-weight: 500; border-bottom: 1px solid var(--tinta); padding-bottom: 2px; }
        .bl-baca i { font-size: 0.75rem; transition: transform 180ms var(--keluar); }

        .bl-gambar { overflow: hidden; border-radius: 8px; background: #eeeae4; aspect-ratio: 3 / 2; }
        .bl-gambar img { width: 100%; height: 100%; object-fit: cover; transition: transform 320ms var(--keluar); }
        .bl-meta { display: flex; flex-wrap: wrap; gap: 0.375rem 0.75rem; margin: 0; font-size: 0.8125rem; color: var(--tinta-3); }
        .bl-meta span { color: var(--tinta); font-weight: 500; }
        .bl-ringkas { margin: 0.625rem 0 0; color: var(--tinta-2); display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 3; overflow: hidden; }

        .bl-bilah { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 0; border-top: 1px solid var(--tinta); border-bottom: 1px solid var(--garis); }
        .bl-kategori { display: flex; flex-wrap: wrap; gap: 0.25rem 1.5rem; }
        .bl-kategori button { background: none; border: 0; padding: 0.25rem 0; font: inherit; font-size: 0.9375rem; color: var(--tinta-3); cursor: pointer; position: relative; transition: color 150ms ease; }
        .bl-kategori button::after { content: ''; position: absolute; left: 0; right: 0; bottom: -2px; height: 2px; background: var(--tinta); transform: scaleX(0); transform-origin: left; transition: transform 200ms var(--keluar); }
        .bl-kategori button[aria-pressed="true"] { color: var(--tinta); font-weight: 500; }
        .bl-kategori button[aria-pressed="true"]::after { transform: scaleX(1); }
        .bl-cari { position: relative; flex: 0 1 260px; }
        .bl-cari i { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--tinta-3); font-size: 0.8125rem; pointer-events: none; }
        .bl-cari input { width: 100%; padding: 0 0.75rem 0 2.25rem; background: var(--putih); border: 1px solid var(--garis-kuat); font: inherit; font-size: 0.9375rem; color: var(--tinta); outline: none; }
        .bl-cari input:focus { border-color: var(--aksen); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15) !important; }

        .bl-daftar { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 3rem 2rem; padding-top: 2.5rem; transition: opacity 150ms ease; }
        .bl-daftar:empty { padding-top: 0; }
        .bl-daftar[aria-busy="true"] { opacity: 0.5; }
        .bl-kartu-taut { display: block; }
        .bl-kartu .bl-meta { margin-top: 1rem; }
        .bl-kartu h3 { font-weight: 600; font-size: 1.1875rem; line-height: 1.3; letter-spacing: -0.015em; margin: 0.375rem 0 0; text-wrap: balance; }
        /* Kartu yang baru masuk naik sedikit sambil muncul; berurutan 40ms supaya terasa satu gerakan. */
        .bl-kartu.baru { animation: bl-naik 280ms var(--keluar) both; animation-delay: calc(var(--i, 0) * 40ms); }
        @keyframes bl-naik { from { opacity: 0; transform: translateY(10px); } }
        .bl-kosong { padding: 3rem 0 0; text-align: center; color: var(--tinta-3); }
        .bl-lagi { display: flex; justify-content: center; padding-top: 3rem; }

        /* ---------- Kontak ---------- */
        .bl-kontak { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.6fr); gap: 2rem; }
        .bl-kontak dl { margin: 0; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.5rem 2rem; }
        .bl-kontak dt { font-size: 0.875rem; color: var(--tinta-3); }
        .bl-kontak dd { margin: 0.25rem 0 0; font-size: 1.0625rem; font-weight: 500; }
        .bl-kontak dd a { border-bottom: 1px solid var(--garis-kuat); transition: border-color 150ms ease; }

        /* Muncul saat digulir: sekali, pendek, hanya opacity dan geser 12px. */
        .bl-muncul { opacity: 0; transform: translateY(12px); transition: opacity 420ms var(--keluar), transform 420ms var(--keluar); }
        .bl-muncul.tampak { opacity: 1; transform: none; }
        .tanpa-js .bl-muncul { opacity: 1; transform: none; }

        @media (hover: hover) {
            .bl-kartu-taut:hover .bl-gambar img, .bl-utama a:hover .bl-gambar img, .bl-hero-gambar:hover .bl-gambar img { transform: scale(1.03); }
            .bl-kartu-taut:hover h3 { text-decoration: underline; text-decoration-thickness: 1px; text-underline-offset: 4px; }
            .bl-baca:hover i { transform: translateX(3px); }
            .bl-kategori button:hover { color: var(--tinta); }
            .bl-kontak dd a:hover { border-color: var(--tinta); }
        }
        @media (max-width: 900px) {
            .bl-hero { grid-template-columns: 1fr; gap: 2rem; padding: 2.5rem 0 2.5rem; }
            .bl-bagian { padding: 2.75rem 0; }
            .bl-bagian-kepala, .bl-kontak { grid-template-columns: 1fr; gap: 0.75rem; margin-bottom: 1.75rem; }
            .bl-kontak { gap: 1.75rem; }
            .bl-langkah { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .bl-utama { grid-template-columns: 1fr; gap: 1.25rem; padding-bottom: 2.5rem; }
            .bl-daftar { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 2.5rem 1.25rem; }
            .bl-cari { flex-basis: 100%; }
        }
        @media (max-width: 560px) {
            .bl-daftar, .bl-layanan, .bl-langkah, .bl-kontak dl { grid-template-columns: 1fr; }
        }
        @media (prefers-reduced-motion: reduce) { .bl-muncul { opacity: 1; transform: none; } }
@endsection

@section('isi')
<div class="bl-lebar">
    <section class="bl-hero">
        <div>
            <h1>Percetakan untuk usaha, acara, dan tim Anda.</h1>
            <p class="lead">{{ $nama }} mengerjakan spanduk, stiker, cetak foto, undangan, DTF, dan jersey, dari berkas sampai barang jadi.</p>
            <div class="bl-aksi">
                @if($tautWa)<a class="bl-tombol" href="{{ $tautWa }}" target="_blank" rel="noopener"><i class="fab fa-whatsapp" aria-hidden="true"></i> Tanya lewat WhatsApp</a>@endif
                <a class="bl-tombol bl-tombol-garis" href="#layanan">Lihat layanan</a>
            </div>
        </div>
        @if($sampulHero)
        <a class="bl-hero-gambar" href="{{ route('publik.baca', $sampulHero->slug) }}">
            <div class="bl-gambar"><img src="{{ $sampulHero->url_sampul }}" alt="{{ $sampulHero->judul }}"></div>
            <p class="bl-hero-ket"><b>{{ \Illuminate\Support\Str::limit($sampulHero->judul, 60) }}</b><span>Baca</span></p>
        </a>
        @endif
    </section>

    @if(count($layanan))
    <section class="bl-bagian bl-muncul" id="layanan">
        <div class="bl-bagian-kepala">
            <h2>Yang kami kerjakan</h2>
            <p>Bahan dan ukuran di luar daftar ini sering tetap bisa dikerjakan. Tanyakan saja.</p>
        </div>
        <ul class="bl-layanan">
            @foreach($layanan as $l)
                <li>{{ $l['nama'] }}@if($l['ket'])<span>{{ $l['ket'] }}</span>@endif</li>
            @endforeach
        </ul>
    </section>
    @endif

    <section class="bl-bagian bl-muncul" id="cara-pesan">
        <div class="bl-bagian-kepala">
            <h2>Cara pesan</h2>
            <p>Urutan yang sama untuk setiap pesanan, supaya Anda tahu pesanan sedang di tahap mana.</p>
        </div>
        <ol class="bl-langkah">
            <li><b>Kirim kebutuhan</b>Sampaikan ukuran, jumlah, dan berkas desain. Belum punya desain, kami bantu buatkan.</li>
            <li><b>Nota dan pembayaran</b>Anda menerima nota berisi rincian dan harga. Bisa bayar di muka sebagian atau lunas.</li>
            <li><b>Produksi</b>Pesanan masuk antrean cetak dan finishing sesuai tenggat yang disepakati.</li>
            <li><b>Ambil barang</b>Kami kabari saat selesai. Bawa nota saat mengambil.</li>
        </ol>
    </section>

    <section class="bl-bagian" id="tulisan">
        <div class="bl-bagian-kepala">
            <h2>Kabar dan catatan</h2>
            <p>Pengumuman, panduan, dan hasil cetak terbaru kami.</p>
        </div>

        @if($unggulan)
        <div class="bl-utama" id="unggulan">
            <a href="{{ route('publik.baca', $unggulan->slug) }}" tabindex="-1" aria-hidden="true">
                @if($unggulan->url_sampul)<div class="bl-gambar"><img src="{{ $unggulan->url_sampul }}" alt="" loading="lazy"></div>@endif
            </a>
            <div>
                <p class="bl-meta">
                    @if($unggulan->kategori)<span>{{ $unggulan->kategori->nama }}</span>@endif
                    <time>{{ optional($unggulan->terbit_at)->locale('id')->translatedFormat('d F Y') }}</time>
                    <span style="font-weight:400;color:inherit">{{ $unggulan->menit_baca }} menit baca</span>
                </p>
                <h3><a href="{{ route('publik.baca', $unggulan->slug) }}">{{ $unggulan->judul }}</a></h3>
                @if($unggulan->ringkasan)<p class="bl-ringkas">{{ $unggulan->ringkasan }}</p>@endif
                <a class="bl-baca" href="{{ route('publik.baca', $unggulan->slug) }}">Baca tulisan</a>
            </div>
        </div>
        @endif

        <div class="bl-bilah">
            <div class="bl-kategori" role="group" aria-label="Saring menurut kategori">
                <button type="button" data-kategori="" aria-pressed="{{ $kategoriSlug === '' ? 'true' : 'false' }}">Semua</button>
                @foreach($kategoriList as $k)
                    <button type="button" data-kategori="{{ $k->slug }}" aria-pressed="{{ $kategoriSlug === $k->slug ? 'true' : 'false' }}">{{ $k->nama }}</button>
                @endforeach
            </div>
            <label class="bl-cari">
                <span class="sr-only">Cari tulisan</span>
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="bl-cari" value="{{ $cari }}" placeholder="Cari tulisan" autocomplete="off">
            </label>
        </div>

        <div class="bl-daftar" id="bl-daftar" aria-live="polite">@include('publik._kartu', ['posts' => $posts])</div>

        <p class="bl-kosong" id="bl-kosong" @if($posts->total() > 0 || $unggulan) hidden @endif>
            <span data-kosong-cari hidden>Tidak ada tulisan yang cocok. Coba kata lain atau pilih kategori Semua.</span>
            <span data-kosong-awal>Belum ada tulisan yang diterbitkan.</span>
        </p>

        <div class="bl-lagi" id="bl-lagi" @if(!$posts->hasMorePages()) hidden @endif>
            <button type="button" class="bl-tombol bl-tombol-garis" data-halaman="{{ $posts->currentPage() + 1 }}">Muat tulisan lain</button>
        </div>
    </section>

    <section class="bl-bagian bl-muncul" id="kontak">
        <div class="bl-kontak">
            <h2>Hubungi kami</h2>
            <dl>
                @if($set->alamat)<div><dt>Alamat</dt><dd>{{ $set->alamat }}</dd></div>@endif
                @if($set->telepon)<div><dt>Telepon dan WhatsApp</dt><dd>@if($tautWa)<a href="{{ $tautWa }}" target="_blank" rel="noopener">{{ $set->telepon }}</a>@else{{ $set->telepon }}@endif</dd></div>@endif
                @if($set->email)<div><dt>Email</dt><dd><a href="mailto:{{ $set->email }}">{{ $set->email }}</a></dd></div>@endif
                @if($set->website)<div><dt>Situs</dt><dd>{{ $set->website }}</dd></div>@endif
            </dl>
        </div>
    </section>
</div>
@endsection

@push('skrip')
<script>
// Muncul saat digulir (sekali). Tanpa IntersectionObserver semuanya langsung tampil.
(function () {
    var el = document.querySelectorAll('.bl-muncul');
    if (!('IntersectionObserver' in window)) { el.forEach(function (e) { e.classList.add('tampak'); }); return; }
    var io = new IntersectionObserver(function (masuk) {
        masuk.forEach(function (m) { if (m.isIntersecting) { m.target.classList.add('tampak'); io.unobserve(m.target); } });
    }, { rootMargin: '0px 0px -10% 0px' });
    el.forEach(function (e) { io.observe(e); });
})();

// Saring, cari, dan muat-lagi tanpa memuat ulang halaman. Tanpa JS, daftar awal tetap tampil lengkap.
(function () {
    var daftar = document.getElementById('bl-daftar'), lagi = document.getElementById('bl-lagi'), kosong = document.getElementById('bl-kosong');
    var unggulan = document.getElementById('unggulan'), cari = document.getElementById('bl-cari');
    var keadaan = { kategori: @json($kategoriSlug), cari: @json($cari) }, urutan = 0, tunda;

    function alamat(halaman) {
        var q = new URLSearchParams();
        if (keadaan.kategori) q.set('kategori', keadaan.kategori);
        if (keadaan.cari) q.set('cari', keadaan.cari);
        if (halaman > 1) q.set('page', halaman);
        var s = q.toString();
        return @json(url('/')) + (s ? '?' + s : '');
    }

    function muat(halaman, tambah) {
        var nomor = ++urutan;
        daftar.setAttribute('aria-busy', 'true');
        fetch(alamat(halaman), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(function (d) {
                if (nomor !== urutan) return; // jawaban lama kalah oleh ketikan yang lebih baru
                var wadah = document.createElement('div');
                wadah.innerHTML = d.html;
                var baru = Array.prototype.slice.call(wadah.children);
                baru.forEach(function (k, i) { k.classList.add('baru'); k.style.setProperty('--i', Math.min(i, 8)); });
                if (!tambah) daftar.innerHTML = '';
                baru.forEach(function (k) { daftar.appendChild(k); });

                var berfilter = !!(keadaan.kategori || keadaan.cari);
                if (unggulan) unggulan.hidden = berfilter;
                kosong.hidden = daftar.children.length > 0 || (!berfilter && !!unggulan);
                kosong.querySelector('[data-kosong-cari]').hidden = !berfilter;
                kosong.querySelector('[data-kosong-awal]').hidden = berfilter;
                lagi.hidden = !d.berikut;
                if (d.berikut) lagi.querySelector('button').dataset.halaman = d.berikut;
                if (!tambah) history.replaceState(null, '', alamat(1) + '#tulisan');
            })
            .catch(function () { if (!tambah) location.href = alamat(1); })
            .finally(function () { if (nomor === urutan) daftar.removeAttribute('aria-busy'); });
    }

    document.querySelectorAll('.bl-kategori button').forEach(function (t) {
        t.addEventListener('click', function () {
            document.querySelectorAll('.bl-kategori button').forEach(function (x) { x.setAttribute('aria-pressed', String(x === t)); });
            keadaan.kategori = t.dataset.kategori;
            muat(1, false);
        });
    });
    cari.addEventListener('input', function () {
        clearTimeout(tunda);
        tunda = setTimeout(function () { keadaan.cari = cari.value.trim(); muat(1, false); }, 250);
    });
    lagi.querySelector('button').addEventListener('click', function () { muat(parseInt(this.dataset.halaman, 10), true); });
})();
</script>
@endpush
