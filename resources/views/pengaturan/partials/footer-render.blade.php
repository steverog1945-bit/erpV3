{{--
    FOOTER BUILDER - SATU-SATUNYA SUMBER MARKAH FOOTER.

    Dipakai oleh TIGA tempat sekaligus, supaya tidak lagi ada implementasi
    footer yang berbeda tampilan:
      1. Preview Landing Page   -> section-render.blade.php  (@case 'footer')
      2. Landing Page publik     -> landing-page-public.blade.php
      3. Halaman artikel         -> publik/layout-artikel.blade.php

    Data yang dipakai HANYA data yang sudah dikonfigurasi operator di Builder:
      - Kolom Identitas : section 'footer'  -> title, subtitle, desc
      - Kolom Navigasi  : section 'header'  -> items (Beranda, Tentang, ...)
      - Kolom Layanan   : section 'services' -> items[].title
      - Kolom Kontak    : section 'contact' -> items (WhatsApp, alamat, email)

    Anchor (#tentang, #faq, ...) SELALU ditulis sebagai URL lengkap
    "{beranda}#tentang". Di halaman artikel anchor lokal seperti #tentang
    tidak punya tujuan karena section itu tidak ada di sana - inilah alasan
    link footer artikel sebelumnya mati.

    Parameter (semua opsional, punya fallback di dalam partial):
      $lpFooterSection  array|null  config section 'footer'
      $lpFooterNavItems array       [['label' => ..., 'href' => ...], ...]
      $lpFooterSections array       daftar section lengkap (untuk layanan & kontak)
      $lpFooterSet      AppSetting  (default: AppSetting::current())
--}}

@php
    // ---- Helper lokal (partial ini harus mandiri: dipanggil dari 3 konteks berbeda)
    $lpFUrlAman = function ($url) {
        $url = trim((string) $url);
        if ($url === '') return null;
        if ($url[0] === '#') {
            return preg_match('/^#[A-Za-z0-9_-]+$/', $url) ? $url : null;
        }
        if (preg_match('/^(javascript|data|vbscript|file|about):/i', $url)) return null;
        if (str_starts_with($url, '/')) {
            if (str_starts_with($url, '//')) return null;
            if (str_contains($url, '..') || str_contains($url, '\\')) return null;
            return $url;
        }
        $skema = parse_url($url, PHP_URL_SCHEME);
        return in_array(strtolower((string) $skema), ['http', 'https'], true) ? $url : null;
    };
    $lpFEksternal = function ($url) {
        return $url && preg_match('#^https?://#i', $url);
    };

    // ---- Data Builder
    $lpFSec   = is_array($lpFooterSection ?? null) ? $lpFooterSection : [];
    $lpFSemua = is_array($lpFooterSections ?? null) ? $lpFooterSections : [];
    $lpFSet   = $lpFooterSet ?? \App\Models\AppSetting::current();

    $lpFIdentitas = trim((string) ($lpFSec['title'] ?? '')) ?: ($lpFSet->nama_aplikasi ?: 'ORION GRAFIKA');
    $lpFSubtitle  = trim((string) ($lpFSec['subtitle'] ?? ''));
    $lpFDesc      = trim((string) ($lpFSec['desc'] ?? ''));

    // Navasi: item yang dikonfigurasi operator di Header Builder.
    $lpFNav = [];
    foreach ((array) ($lpFooterNavItems ?? []) as $lpFN) {
        if (! is_array($lpFN)) continue;
        $lpFLabel = trim((string) ($lpFN['label'] ?? ''));
        $lpFHref  = $lpFUrlAman($lpFN['href'] ?? '');
        if ($lpFLabel !== '' && $lpFHref !== null) {
            $lpFNav[] = ['label' => $lpFLabel, 'href' => $lpFHref];
        }
    }

    // Layanan: judul kartu yang dikonfigurasi operator di section 'services'.
    $lpFLayanan = [];
    foreach ($lpFSemua as $lpFS) {
        if (($lpFS['type'] ?? '') !== 'services') continue;
        foreach ((array) ($lpFS['items'] ?? []) as $lpFI) {
            if (! is_array($lpFI)) continue;
            $lpFJ = trim((string) ($lpFI['title'] ?? ''));
            if ($lpFJ !== '') $lpFLayanan[] = $lpFJ;
        }
        break;
    }

    // Kontak: data yang dikonfigurasi operator di section 'contact'.
    $lpFKontak = [];
    foreach ($lpFSemua as $lpFS) {
        if (($lpFS['type'] ?? '') !== 'contact') continue;
        foreach ((array) ($lpFS['items'] ?? []) as $lpFI) {
            if (! is_array($lpFI)) continue;
            $lpFLabel = trim((string) ($lpFI['label'] ?? ''));
            $lpFNilai = trim((string) ($lpFI['value'] ?? ''));
            if ($lpFLabel !== '' && $lpFNilai !== '') {
                $lpFKontak[] = ['label' => $lpFLabel, 'value' => $lpFNilai];
            }
        }
        break;
    }

    // Nomor WhatsApp.
    //
    // Sumber pertama = data operator di section 'contact' (item "WhatsApp"),
    // karena nomor WA bisnis berbeda dari nomor telepon perusahaan di
    // Pengaturan Aplikasi. Fallback ke Pengaturan Aplikasi hanya dipakai bila
    // section contact memang tidak punya nomor.
    $lpFNoHp = null;
    foreach ($lpFKontak as $lpFK) {
        if (stripos($lpFK['label'], 'whatsapp') === false && stripos($lpFK['label'], 'wa') !== 0) continue;
        $lpFAngka = preg_replace('/\D+/', '', $lpFK['value']);
        if ($lpFAngka !== '' && $lpFAngka !== null) {
            $lpFNoHp = str_starts_with($lpFAngka, '0') ? '62' . substr($lpFAngka, 1) : $lpFAngka;
            break;
        }
    }
    if ($lpFNoHp === null) {
        $lpFAngka = preg_replace('/\D+/', '', (string) ($lpFSet->telepon ?? ''));
        if ($lpFAngka !== '') {
            $lpFNoHp = str_starts_with($lpFAngka, '0') ? '62' . substr($lpFAngka, 1) : $lpFAngka;
        }
    }

    // Basis URL anchor: SEMUA navigasi publik menuju 'landing-page.public'.
    //
    // Pemetaan operator (dari halaman artikel):
    //   Logo / Beranda / Tentang / Layanan / Produk / Portofolio / Kontak / FAQ
    //   -> route('landing-page.public') + '#anchor'
    //
    // Tidak memakai url('/') + '#anchor': pada fallback (revisi belum valid)
    // '/' bisa jadi halaman lain, dan pada Gate B OFF homepage existing
    // memang tidak punya section Builder sama sekali. route('landing-page.public')
    // selalu menampilkan snapshot PUBLISHED - untuk guest maupun karyawan,
    // untuk Gate B ON maupun OFF. Tidak ada hardcode 'localhost'.
    //
    // Pemanggil boleh menimpa lewat $lpFooterAnchorBase; layout-artikel
    // mengirim route('landing-page.public') supaya header dan footer
    // memakai tujuan yang sama persis.
    $lpFBeranda = $lpFooterAnchorBase ?? route('landing-page.public');
    $lpFUrl = function ($href) use ($lpFBeranda) {
        if ($href !== null && $href !== '' && $href[0] === '#') {
            return $lpFBeranda . $href;
        }
        return $href;
    };
    // Atribut target hanya untuk link yang ASLINYA eksternal.
    //
    // Penentuannya WAJIB memakai href SEBELUM ditulis ulang. Anchor internal
    // ("#tentang") ditulis menjadi URL absolut "{beranda}#tentang"; kalau
    // diperiksa setelah ditulis ulang, link internal ikut dianggap eksternal
    // dan setiap menu Navigasi membuka tab baru.
    // Link WhatsApp dan link eksternal tetap memakai target="_blank".
    $lpFAtributAsli = function ($hrefAsli) use ($lpFEksternal) {
        return $lpFEksternal($hrefAsli) ? 'target="_blank" rel="noopener noreferrer"' : '';
    };

    // Item footer tambahan yang benar-benar dikonfigurasi operator (bila ada).
    $lpFExtra = [];
    foreach ((array) ($lpFSec['items'] ?? []) as $lpFI) {
        if (! is_array($lpFI)) continue;
        $lpFLabel = trim((string) ($lpFI['label'] ?? ''));
        $lpFJudul = trim((string) ($lpFI['title'] ?? ''));
        $lpFHref  = $lpFUrlAman($lpFI['value'] ?? '');
        if ($lpFLabel !== '' && $lpFJudul !== '' && $lpFHref !== null) {
            $lpFExtra[] = ['label' => $lpFLabel, 'judul' => $lpFJudul, 'href' => $lpFHref];
        }
    }
@endphp

<footer class="lp-footer">
    <div class="lp-footer-grid">
        {{-- 1. Identitas --}}
        <div class="lp-footer-kol lp-footer-kol--identitas">
            <div class="lp-footer-brand">
                <span class="lp-footer-mark" aria-hidden="true">OG</span>
                <span class="lp-footer-name">{{ $lpFIdentitas }}</span>
            </div>
            @if($lpFSubtitle !== '')
                <p class="lp-footer-desc lp-footer-subtitle">{{ $lpFSubtitle }}</p>
            @endif
            @if($lpFDesc !== '')
                <p class="lp-footer-desc">{{ $lpFDesc }}</p>
            @endif
        </div>

        {{-- 2. Navigasi (item milik operator) --}}
        @if($lpFNav)
            <nav class="lp-footer-kol" aria-label="Navigasi footer">
                <h4>Navigasi</h4>
                <ul class="lp-footer-list">
                    @foreach($lpFNav as $lpFN)
                        {{-- Link INTERNAL navigasi: same-tab (tidak ada target).
                             Atribut ditentukan dari href ASAL. --}}
                        @php $lpFhref = $lpFUrl($lpFN['href']); @endphp
                        <li><a href="{{ $lpFhref }}" {!! $lpFAtributAsli($lpFN['href']) !!}>{{ $lpFN['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif

        {{-- 3. Layanan (judul kartu dari section services) --}}
        @if($lpFLayanan)
            <div class="lp-footer-kol">
                <h4>Layanan</h4>
                <ul class="lp-footer-list">
                    @foreach($lpFLayanan as $lpFL)
                        <li><a href="{{ $lpFUrl('#layanan') }}">{{ $lpFL }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- 4. Kontak (data dari section contact + WhatsApp) --}}
        <div class="lp-footer-kol">
            <h4>Kontak</h4>
            @if($lpFKontak)
                <ul class="lp-footer-list lp-footer-list--kontak">
                    @foreach($lpFKontak as $lpFK)
                        <li>
                            <span class="lp-footer-label">{{ $lpFK['label'] }}</span>
                            @if($lpFK['label'] === 'WhatsApp' && $lpFNoHp)
                                <a href="https://wa.me/{{ $lpFNoHp }}" target="_blank" rel="noopener noreferrer">{{ $lpFK['value'] }}</a>
                            @elseif($lpFK['label'] === 'Email' && filter_var($lpFK['value'], FILTER_VALIDATE_EMAIL))
                                <a href="mailto:{{ $lpFK['value'] }}">{{ $lpFK['value'] }}</a>
                            @else
                                <span class="lp-footer-value">{{ $lpFK['value'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            @if($lpFNoHp)
                <a class="lp-footer-wa" href="https://wa.me/{{ $lpFNoHp }}" target="_blank" rel="noopener noreferrer">Konsultasi Cetak</a>
            @endif
            @if(empty($lpFKontak))
                <ul class="lp-footer-list">
                    @if($lpFSet->alamat)<li><span class="lp-footer-value">{{ $lpFSet->alamat }}</span></li>@endif
                    @if($lpFSet->telepon)<li><span class="lp-footer-value">{{ $lpFSet->telepon }}</span></li>@endif
                    @if($lpFSet->email)<li><a href="mailto:{{ $lpFSet->email }}">{{ $lpFSet->email }}</a></li>@endif
                </ul>
            @endif
        </div>

        {{-- 5. Grup tambahan yang benar-benar diisi operator --}}
        @foreach($lpFExtra as $lpFE)
            <div class="lp-footer-kol">
                <h4>{{ $lpFE['label'] }}</h4>
                <ul class="lp-footer-list">
                    @php $lpFEhref = $lpFUrl($lpFE['href']); @endphp
                    <li><a href="{{ $lpFEhref }}" {!! $lpFAtributAsli($lpFE['href']) !!}>{{ $lpFE['judul'] }}</a></li>
                </ul>
            </div>
        @endforeach
    </div>

    <div class="lp-footer-bottom">
        <p class="lp-footer-copy">&copy; {{ date('Y') }} {{ $lpFSet->nama_aplikasi ?: 'ORION GRAFIKA' }}. Semua hak dilindungi.</p>
    </div>
</footer>