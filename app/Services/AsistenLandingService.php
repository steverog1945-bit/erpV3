<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Asisten chat publik di landing page - HANYA menjawab seputar ORDERAN
 * (cara pesan, pembayaran, pengantaran, file, layanan, kontak, jam).
 *
 * Sengaja rule-based (bukan AI): tidak ada cara mem-bypass filter lewat
 * prompt injection, dan mustahil membocorkan data internal. Omzet,
 * pemasukan, laba, HPP, gaji, laporan keuangan dan angka perusahaan apa pun
 * ditolak tegas - halaman ini dibaca publik.
 *
 * Harga: publik, sesuai keputusan owner. Yang boleh keluar HANYA
 * harga_jual (harga untuk dibayar konsumen) dari setting_harga golongan
 * Umum - HPP, harga bahan, biaya produksi tidak pernah disentuh. Data
 * katalog di-cache singkat (5 menit) supaya endpoint publik ini tidak
 * memukul DB setiap chat.
 */
class AsistenLandingService
{
    private const KATALOG_CACHE_KEY = 'asisten_landing_katalog_v1';
    private const KATALOG_CACHE_TTL = 300;

    /** Kata umum yang TIDAK boleh jadi kunci pencocokan produk. */
    private const STOPWORDS = ['cetak', 'cetakan', 'percetakan', 'print', 'printing', 'bikin', 'buat', 'harga', 'berapa', 'ukuran', 'muka', 'sisi', 'custom'];

    /** @var array<int, array{pattern:string, kategori:string}> */
    private const POLA_PRIVASI = [
        '/omset|omzet/',
        '/pendapatan|pemasukan|revenue/',
        '/keuntungan|profit|margin|untung|rugi|keuntungan bersih/',
        '/laba/i',
        '/hpp|harga modal|biaya produksi|\bmodal\b/i',
        '/gaji|karyawan|bonus/',
        '/owner|pemilik usaha|manajer|direktur/',
        '/laporan keuangan|audit|pembukuan|neraca|keuangan/i',
        '/closing kas|saldo kas|selisih kas|closing/i',
        '/berapa uang|berapa duit|berapa omset/',
        '/target penjualan|target bulanan/',
        '/uang masuk|kas masuk|uang keluar|kas keluar|cashflow/i',
    ];

    /**
     * @return array{reply: string, intent: string}
     */
    public function jawab(string $pesan): array
    {
        $t = $this->normalisasi($pesan);
        if ($t === '') {
            return $this->balas($this->sapaan(), 'kosong');
        }

        // 1) Blokir topik privasi/perusahaan LEBIH DULU - tidak bisa ditembus.
        foreach (self::POLA_PRIVASI as $pola) {
            if (preg_match($pola, $t)) {
                return $this->balas('Maaf, itu informasi internal perusahaan dan tidak bisa saya bagikan ke siapa pun. Saya hanya bisa bantu seputar orderan: cara pesan, pembayaran, pengantaran, file, layanan, jam buka, dan kontak toko. Mau tanya itu?', 'ditolak-privasi');
            }
        }

        // 2) Bantuan / daftar perintah.
        if (preg_match('/^(\/bantuan|bantuan|help|mulai|apa saja)$/u', $t)) {
            return $this->balas($this->daftarBantuan(), 'bantuan');
        }

        // 3) Sapaan.
        if (preg_match('/^(halo|hai|pagi|siang|sore|malam|hiya|hello|permisi)\b/u', $t)) {
            return $this->balas($this->sapaan(), 'sapaan');
        }

        // 4) Cara pesan / order.
        if (preg_match('/(cara (pesan|order)|bagaimana cara|gimana cara|ingin pesan|mau pesan|order dong|pesan donk|mau order|cara nya pesan)/u', $t)) {
            return $this->balas(
                "Cara order di toko kami:\n1. Siapkan file desain (PDF/AI/CDR/PSD, 300 dpi) atau minta dibantu desainkan.\n2. Tentukan ukuran, bahan & jumlah (boleh datang langsung atau chat).\n3. Kasir cek harga final - harga tergantung ukuran, bahan, dan finishing.\n4. Bayar (DP untuk order besar / langsung lunas), terima nota.\n5. Produksi jalan; ambil di toko / diantar sesuai kesepakatan.\n\nButuh harga pasti? Langsung chat kasir lewat tombol WhatsApp di halaman ini - paling cepat & akurat.",
                'cara-pesan'
            );
        }

        // 4b) Produk spesifik disebut ("berapa harga banner", "ukuran stiker")
        // - jawab dari data katalog NYATA (harga jual golongan Retail, tier
        // qty terkecil), bukan kalimat generik. Istilah konsumen ("banner",
        // "spanduk", "baliho") diterjemahkan lewat kamus produk_alias yang
        // dikelola owner dari Master Produk - tidak ada pemetaan hardcode.
        $katalog = $this->katalog();
        if (!empty($katalog)) {
            $kandidat = $this->cariProduk($katalog, $t);
            if (!empty($kandidat)) {
                return $this->jawabProduk($kandidat, $t);
            }
        }

        // 5) Pembayaran.
        if (preg_match('/(cara bayar|pembayaran|bayar|bayar|dp|uang muka|transfer|tunai|debit|kredit|qris|cash|tempo|cicilan)/u', $t)) {
            return $this->balas(
                'Pembayaran bisa tunai, transfer, debit/QRIS (mengikuti fasilitas kasir). Untuk order besar biasanya DP dulu dan sisanya saat ambil - teknis lengkapnya ditanya langsung ke kasir, karena ketentuannya bisa berbeda per jenis pesanan. Ketik "cara pesan" kalau baru mulai.',
                'bayar'
            );
        }

        // 6) Pengantaran / ambil di toko.
        if (preg_match('/(antar|kirim|dikirim|pengantaran|ambil|ambil di|sampe|sampai|pick.?up)/u', $t)) {
            return $this->balas(
                'Pesanan bisa diambil langsung di toko, atau diantar untuk area tertentu (tergantung ukuran/order). Biaya & jadwal antar disepakati dengan kasir dulu sebelum produksi dimulai - ketik "cara pesan" untuk mulai.',
                'antar'
            );
        }

        // 7) Harga / biaya - generik (produk tidak teridentifikasi):
        // sebut contoh harga mulai dari data nyata, lalu arahkan ke kasir.
        if (preg_match('/(harga|biaya|berapa|tarif|ongkos|price|rate)/u', $t)) {
            $reply = 'Harga sangat tergantung ukuran, bahan, jumlah, dan finishing.';
            if (!empty($katalog)) {
                $reply .= ' Contoh harga mulai (harga umum, jumlah paling kecil):';
                foreach (array_slice($katalog, 0, 3) as $k) {
                    $reply .= "\n- " . $k['nama'] . ': Rp ' . number_format((float) $k['harga'], 0, ',', '.') . ($k['satuan'] !== '' ? ' / ' . $k['satuan'] : '');
                }
            }
            $reply .= "\n\nUntuk penawaran akurat (ukuran, bahan, finishing Anda), chat kasir lewat tombol WhatsApp di halaman ini atau datang langsung - pasti dibantu.";
            return $this->balas($reply, 'harga');
        }

        // 8) Layanan / jenis produk yang bisa dicetak - daftar dari data nyata.
        if (preg_match('/(layanan|jasa|cetak apa|produk|bisa (cetak|buat|bikin)|bikin (banner|spanduk|stiker|sticker|brosur|kartu nama|jersey|sablon|id card)|jersey|sablon)/u', $t)) {
            $reply = 'Kami melayani antara lain:';
            if (!empty($katalog)) {
                $namaProduk = array_slice(array_column($katalog, 'nama'), 0, 8);
                foreach ($namaProduk as $np) {
                    $reply .= "\n- " . $np;
                }
                if (count($katalog) > 8) {
                    $reply .= "\n- dan produk lainnya (tanya saja di sini)";
                }
            } else {
                $reply .= ' banner/spanduk, stiker & label, brosur/flyer, kartu nama, ID card, backdrop & pameran, sablon jersey (printing + press), plang nama, nota & kop surat, stempel, undangan, dan lainnya.';
            }
            $reply .= "\n\nUntuk item khusus, chat kasir - produksi kami bisa bantu desain sekalian.";
            return $this->balas($reply, 'layanan');
        }

        // 9) File / desain.
        if (preg_match('/(file|format|desain|dokumen|resolusi|dpi|ai|cdr|pdf|psd|gambar)/u', $t)) {
            return $this->balas(
                'File ideal: PDF, AI, CDR, atau PSD, resolusi 300 dpi (gambar/foto). Font di-outline/teks sudah diubah jadi kurva. Belum punya file? Tenang - jasa desain tersedia, sebutkan nama & contoh desain yang Anda inginkan ke kasir.',
                'file'
            );
        }

        // 10) Jam / hari buka.
        if (preg_match('/(jam|buka|tutup|operasional|hari kerja|weekend|minggu|libur)/u', $t)) {
            $app = AppSetting::current();
            return $this->balas(
                'Jam operasional toko silakan cek langsung di toko atau chat WhatsApp kasir - kami selalu update di sana. Untuk order mendadak sebaiknya konfirmasi dulu sebelum datang. Ada yang bisa dibantu seputar orderan?',
                'jam'
            );
        }

        // 11) Kontak / alamat / lokasi.
        if (preg_match('/(kontak|alamat|wa|whatsapp|telepon|telp|hp|nomer|nomor|email|lokasi|dimana|tok|cabang)/u', $t)) {
            $app = AppSetting::current();
            $lines = ["Berikut kontak resmi toko:"];
            if (!empty($app->nama_aplikasi)) {
                $lines[] = "- {$app->nama_aplikasi}";
            }
            if (!empty($app->alamat)) {
                $lines[] = "- Alamat: {$app->alamat}";
            }
            if (!empty($app->telepon)) {
                $lines[] = "- Telepon/WA: {$app->telepon}";
            }
            if (!empty($app->email)) {
                $lines[] = "- Email: {$app->email}";
            }
            if (!empty($app->website)) {
                $lines[] = "- Website: {$app->website}";
            }
            $lines[] = 'Cukup klik tombol WhatsApp di halaman ini - paling cepat dibalas.';
            return $this->balas(implode("\n", $lines), 'kontak');
        }

        // 12) Lama produksi / deadline.
        if (preg_match('/(lama|berapa lama|waktu|deadline|jadi|kapan|selesai|proses berapa|antre)/u', $t)) {
            return $this->balas(
                'Kecepatan pengerjaan tergantung jenis order & antrean (banner kecil bisa lebih cepat; jersey/printing butuh waktu lebih). Deadline yang realistis selalu kami sampaikan saat konfirmasi order - ketik "cara pesan" kalau mau langsung mulai.',
                'lama'
            );
        }

        // 13) Nota / kwitansi.
        if (preg_match('/(nota|kwitansi|struk|bukti bayar|tanda terima)/u', $t)) {
            return $this->balas(
                'Setiap order menerima nota sebagai bukti pemesanan. Jika lupa membawa atau nota rusak, bisa dicetak ulang selama order tercatat - konfirmasi dengan kasir.',
                'nota'
            );
        }

        // 14) Ukuran / besar - generik + batas ukuran dari data produk.
        if (preg_match('/(ukuran|besar|lebar|panjang|skala)/u', $t)) {
            $reply = 'Boleh ukuran berapa pun sesuai kebutuhan - tim kami bisa bantu mengukur & merekomendasikan ukuran hemat.';
            if (!empty($katalog)) {
                $batas = [];
                foreach ($katalog as $k) {
                    if ($k['min_p'] > 0 && $k['min_l'] > 0) {
                        $batas[] = $k['nama'] . ' min. ' . rtrim(rtrim(number_format((float) $k['min_p'], 2, ',', '.'), '0'), ',') . ' x ' . rtrim(rtrim(number_format((float) $k['min_l'], 2, ',', '.'), '0'), ',') . ' m';
                    }
                    if (count($batas) >= 3) break;
                }
                if (!empty($batas)) {
                    $reply .= "\n" . implode("\n", $batas);
                }
            }
            $reply .= "\n\nChat kasir untuk konsultasi gratis.";
            return $this->balas($reply, 'ukuran');
        }

        return $this->balas($this->daftarBantuan(), 'fallback');
    }

    /**
     * Katalog publik: produk aktif yang punya harga golongan Umum.
     * Dikembalikan sebagai daftar [nama, harga (tier qty terkecil), satuan,
     * promo aktif, metode hitung, min_order/min_panjang/min_lebar].
     * HANYA kolom yang aman publik - keterangan produk, BOM, harga bahan
     * tidak pernah ikut.
     */
    private function katalog(): array
    {
        try {
            return Cache::remember(self::KATALOG_CACHE_KEY, self::KATALOG_CACHE_TTL, function () {
                return $this->muatKatalog();
            });
        } catch (\Throwable $e) {
            // DB bermasalah: endpoint publik ini TIDAK boleh 500. Balas generik saja.
            return [];
        }
    }

    private function muatKatalog(): array
    {
        // Harga publik memakai golongan Retail (harga walk-in), sesuai
        // praktik ERP: pelanggan tanpa golongan khusus dibayar Retail.
        // Fallback: golongan pertama bila belum ada yang bernama Retail.
        $golonganUmum = DB::table('golongan_konsumen')
            ->orderByRaw("CASE WHEN nama_golongan = 'Retail' THEN 0 ELSE 1 END")
            ->orderBy('id_golongan')
            ->value('id_golongan');
        if (!$golonganUmum) {
            return [];
        }

        $rows = DB::table('produk as p')
            ->join('setting_harga as sh', function ($j) use ($golonganUmum) {
                $j->on('sh.id_produk', '=', 'p.id_produk')
                    ->where('sh.id_golongan', '=', $golonganUmum);
            })
            ->leftJoin('satuan as s', 's.id_satuan', '=', 'sh.id_satuan')
            ->where('p.status', 'aktif')
            ->orderBy('p.id_produk')
            ->orderBy('sh.range_min')
            ->get([
                'p.id_produk', 'p.nama_produk', 'p.metode_hitung',
                'p.min_order', 'p.min_panjang', 'p.min_lebar',
                'p.min_luas_tagih', 'p.mode_luas',
                'sh.range_min', 'sh.harga_jual', 'sh.id_satuan',
                's.nama_satuan',
            ]);

        // Kamus alias + bahan utama (dari BOM) - read-only, untuk jawaban.
        $aliasSemua = DB::table('produk_alias')->get(['id_produk', 'alias'])
            ->groupBy('id_produk');
        $bahanUtama = DB::table('bom_detail as b')
            ->join('bahan_baku as bb', 'bb.id_bahan', '=', 'b.id_bahan')
            ->orderBy('b.id_bom')
            ->get(['b.id_produk', 'bb.nama_bahan'])
            ->groupBy('id_produk');

        $promo = DB::table('harga_promo')
            ->where('status_promo', 'aktif')
            ->where('tgl_mulai', '<=', today())
            ->where('tgl_selesai', '>=', today())
            ->pluck('harga_promo', 'id_produk');

        $katalog = [];
        foreach ($rows as $r) {
            $id = (int) $r->id_produk;
            if (isset($katalog[$id])) {
                $katalog[$id]['tiers']++;
                continue; // tier qty terkecil (range_min paling kecil) sudah tercatat dulu
            }
            $alias = isset($aliasSemua[$id])
                ? $aliasSemua[$id]->pluck('alias')->map(fn ($a) => $this->normalisasi((string) $a))->filter()->unique()->values()->all()
                : [];

            $katalog[$id] = [
                'id' => $id,
                'nama' => (string) $r->nama_produk,
                'harga' => (float) $r->harga_jual,
                'satuan' => (string) ($r->nama_satuan ?? ''),
                'promo' => isset($promo[$id]) ? (float) $promo[$id] : null,
                'metode' => (string) $r->metode_hitung,
                'min_order' => (float) $r->min_order,
                'min_p' => (float) ($r->min_panjang ?? 0),
                'min_l' => (float) ($r->min_lebar ?? 0),
                'min_luas_tagih' => $r->min_luas_tagih !== null ? (float) $r->min_luas_tagih : null,
                'mode_luas' => (string) ($r->mode_luas ?? 'per_item'),
                'bahan' => isset($bahanUtama[$id]) ? (string) $bahanUtama[$id]->first()->nama_bahan : '',
                'alias' => $alias,
                'tiers' => 1,
            ];
        }

        return array_values($katalog);
    }

    /**
     * Kunci pencocokan satu produk: alias kamus (dari produk_alias) +
     * token dari nama produk. Alias boleh pendek (>=2 huruf, dipasang
     * owner sendiri); token nama minimal 4 huruf supaya "AC"/"10R"
     * tidak salah cocok tanpa alias.
     */
    private function kunciProduk(array $k): array
    {
        $kunci = $k['alias'] ?? [];
        foreach (preg_split('/\s+/u', $this->normalisasi($k['nama'])) ?: [] as $tok) {
            if (mb_strlen($tok) >= 4 && !is_numeric($tok) && !in_array($tok, self::STOPWORDS, true)) {
                $kunci[] = $tok;
            }
        }

        return array_values(array_unique($kunci));
    }

    /**
     * Cocokkan teks pengunjung dengan kunci produk (alias + nama).
     * Token diuji sebagai kata utuh; token >=5 huruf juga dicocokkan
     * sebagai bagian kata ("fls280" menangkap "fls280gr"). Alias
     * berisi spasi diuji sebagai frasa. Kembalikan SEMUA kandidat
     * (skor tertinggi dulu) - ambigu ("banner" -> 4 lebar FLS280)
     * dibalas daftar varian, bukan satu dipilih sembarangan.
     */
    private function cariProduk(array $katalog, string $t): array
    {
        $hasil = [];
        foreach ($katalog as $k) {
            $score = 0;
            foreach ($this->kunciProduk($k) as $tok) {
                if (str_contains($tok, ' ')) {
                    if (str_contains($t, $tok)) {
                        $score += 2;
                    }
                    continue;
                }
                if (mb_strlen($tok) < 2) {
                    continue;
                }
                if (preg_match('/(^|\s)' . preg_quote($tok, '/') . '(\s|$)/u', $t)) {
                    $score++;
                } elseif (mb_strlen($tok) >= 5 && str_contains($t, $tok)) {
                    $score++;
                }
            }
            if ($score > 0) {
                $hasil[] = ['k' => $k, 'score' => $score];
            }
        }
        usort($hasil, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn ($h) => $h['k'], $hasil);
    }

    /**
     * Baca dimensi dari teks: "3x1", "3 x 1 m", "300x100 cm", "3,5x2 m".
     * Unit default METER (istilah umum percetakan); kalau ada "cm",
     * keduanya dianggap cm. Kembalikan [panjang_m, lebar_m] atau null.
     */
    private function parseUkuran(string $t): ?array
    {
        if (!preg_match('/(\d+(?:[.,]\d+)?)\s*(m|cm|meter|metre)?\s*[x×]\s*(\d+(?:[.,]\d+)?)\s*(m|cm|meter|metre)?/u', $t, $m)) {
            return null;
        }

        $a = (float) str_replace(',', '.', $m[1]);
        $b = (float) str_replace(',', '.', $m[3]);
        $satuan = ($m[2] ?? '') ?: ($m[4] ?? '');
        if ($satuan === 'cm') {
            $a /= 100;
            $b /= 100;
        }
        if ($a <= 0 || $b <= 0 || $a > 1000 || $b > 1000) {
            return null;
        }

        // Urutan bebas: simpan [sisi pendek, sisi panjang] - cetakan
        // berjalan memanjang roll, lebar material >= sisi pendek.
        return [min($a, $b), max($a, $b)];
    }

    /**
     * Estimasi harga SATU item memakai service yang SAMA PERSIS dengan
     * kasir (LuasTagihService::hitungGrup) - luas = p(cm)x l(cm) x qty /
     * 10000, tier dari luas, subtotal = round(luas x harga/m², 2), plus
     * aturan minimum luas kelompok (mode gabungan). Tanpa ini, angka
     * asisten bisa berbeda dengan nota - itu tidak boleh terjadi.
     */
    private function estimasi(array $k, int $golongan, array $ukuranM): ?array
    {
        if ($k['promo'] !== null || $k['metode'] !== 'ukuran') {
            return null; // promo flat / produk per-pcs: biarkan kasir menghitung
        }

        [$pendek, $panjang] = $ukuranM;
        $row = (object) [
            'id_produk' => $k['id'],
            'mode_luas' => $k['mode_luas'],
            'min_luas_tagih' => $k['min_luas_tagih'],
            'metode_hitung' => $k['metode'],
        ];

        try {
            $hasil = LuasTagihService::hitungGrup(
                [['p' => $pendek * 100, 'l' => $panjang * 100, 'qty' => 1]],
                $row,
                $golongan
            );
        } catch (\Throwable $e) {
            return null;
        }

        $item = $hasil['items'][0] ?? null;
        if (!$item || ($item['harga_per_m2'] ?? 0) <= 0) {
            return null;
        }

        return [
            'luas' => (float) $item['luas_ditagih'],
            'harga_per_m2' => (float) $item['harga_per_m2'],
            'total' => (float) $item['subtotal'],
            'minimum' => $hasil['catatan'] ?? null,
        ];
    }

    /**
     * Jawaban per produk. Satu kandidat -> jawaban lengkap (harga mulai /
     * promo, estimasi jika ukuran disebut, bahan dari BOM, batas ukuran).
     * Beberapa kandidat -> daftar varian dengan harga mulai masing-masing.
     */
    private function jawabProduk(array $kandidat, string $t): array
    {
        $tanyaUkuran = (bool) preg_match('/(ukuran|besar|lebar|panjang|minimal|minimum|min berapa)/u', $t);
        $tanyaHarga = (bool) preg_match('/(harga|biaya|berapa|tarif|ongkos|price|rate)/u', $t);
        $ukuran = $this->parseUkuran($t);
        $intent = ($tanyaHarga || !$tanyaUkuran) ? 'harga-produk' : 'ukuran-produk';

        if (count($kandidat) === 1) {
            return $this->balas($this->jawabSatuProduk($kandidat[0], $t, $ukuran), $intent);
        }

        // Beberapa varian cocok (mis. "banner" -> FLS280 1.6/2.2/2.6/3.2m):
        // kalau ukuran disebut, estimasikan varian yang materialnya muat
        // (lebar material >= sisi pendek, terkecil yang memenuhi).
        $k = $this->pilihVarian($kandidat, $ukuran);
        if ($ukuran !== null) {
            return $this->balas($this->jawabSatuProduk($k, $t, $ukuran, count($kandidat)), $intent);
        }

        $lines = ['Ada beberapa varian - sebutkan ukuran/lebar yang Anda mau untuk hitungan pas:'];
        foreach (array_slice($kandidat, 0, 5) as $c) {
            $lines[] = '- ' . $c['nama'] . ': Rp ' . number_format((float) $c['harga'], 0, ',', '.') . ($c['satuan'] !== '' ? ' / ' . $c['satuan'] : '') . ($c['promo'] !== null ? ' (PROMO Rp ' . number_format((float) $c['promo'], 0, ',', '.') . ')' : '');
        }
        if (count($kandidat) > 5) {
            $lines[] = '- dan varian lainnya';
        }
        $lines[] = 'Contoh: "banner 3 x 1 meter". Untuk hitungan pasti, chat kasir lewat WhatsApp - gratis.';

        return $this->balas(implode("\n", $lines), $intent);
    }

    /**
     * Pilih varian terbaik untuk ukuran yang disebut: lebar material
     * (dibaca dari nama, mis. "FLS280 3.2m") harus >= sisi pendek ukuran;
     * di antara yang muat, ambil lebar terkecil (paling hemat). Tidak ada
     * yang muat -> varian terlebar. Heuristik ini SEBAGAI SARAN - kasir
     * tetap yang menentukan material final saat order.
     */
    private function pilihVarian(array $kandidat, ?array $ukuran): array
    {
        if ($ukuran === null) {
            return $kandidat[0];
        }
        $pendek = $ukuran[0];

        // Lebar material dibaca dari nama MENTAH (bukan normalisasi yang
        // membuang titik): "FLS280 3.2m" -> 3.2; "120cm" tidak dianggap m.
        $lebar = function (array $k): ?float {
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*m\b/u', mb_strtolower($k['nama']), $m)) {
                $nilai = (float) str_replace(',', '.', $m[1]);
                return $nilai > 0 ? $nilai : null;
            }

            return null;
        };

        $muat = array_values(array_filter(
            $kandidat,
            fn ($k) => ($lebar($k)) !== null && $lebar($k) >= $pendek - 0.001
        ));
        if (empty($muat)) {
            usort($kandidat, fn ($a, $b) => ($lebar($b) ?? 0) <=> ($lebar($a) ?? 0));

            return $kandidat[0];
        }
        usort($muat, fn ($a, $b) => $lebar($a) <=> $lebar($b));

        return $muat[0];
    }

    private function jawabSatuProduk(array $k, string $t, ?array $ukuran, int $totalKandidat = 1): string
    {
        $tanyaUkuran = (bool) preg_match('/(ukuran|besar|lebar|panjang|minimal|minimum|min berapa)/u', $t);
        $tanyaHarga = (bool) preg_match('/(harga|biaya|berapa|tarif|ongkos|price|rate)/u', $t);

        $lines = [];
        $nama = $k['nama'] . ($k['bahan'] !== '' ? ' (' . $k['bahan'] . ')' : '');

        // Estimasi memakai service kasir (LuasTagihService) - hanya produk
        // metode ukuran tanpa promo aktif; promo flat ditangani kasir.
        $estimasi = $ukuran !== null ? $this->estimasi($k, $this->golonganPublikId(), $ukuran) : null;

        if ($k['promo'] !== null) {
            $lines[] = 'Harga ' . $nama . ' saat ini PROMO: Rp ' . number_format((float) $k['promo'], 0, ',', '.') . ($k['satuan'] !== '' ? ' / ' . $k['satuan'] : '') . ' (berlaku untuk semua pelanggan).';
        } elseif ($estimasi !== null) {
            $pendek = rtrim(rtrim(number_format($ukuran[0], 2, ',', '.'), '0'), ',');
            $panjang = rtrim(rtrim(number_format($ukuran[1], 2, ',', '.'), '0'), ',');
            $lines[] = 'Estimasi ' . $nama . ' ukuran ' . $pendek . ' x ' . $panjang . ' m: Rp ' . number_format($estimasi['total'], 0, ',', '.') . ' (Rp ' . number_format($estimasi['harga_per_m2'], 0, ',', '.') . '/m² × ' . rtrim(rtrim(number_format($estimasi['luas'], 2, ',', '.'), '0'), ',') . ' m²).';
            if ($estimasi['minimum'] !== null) {
                $lines[] = $estimasi['minimum'] . '.';
            }
            $lines[] = 'Estimasi untuk 1 lembar, belum termasuk finishing (laminasi, mata ayam, dsb.) - harga final dihitung kasir saat order.';
        } else {
            $lines[] = 'Harga mulai ' . $nama . ': Rp ' . number_format((float) $k['harga'], 0, ',', '.') . ($k['satuan'] !== '' ? ' / ' . $k['satuan'] : '') . ' (harga umum untuk jumlah paling kecil).';
            if ($k['tiers'] > 1) {
                $lines[] = 'Semakin besar jumlah/luasnya, harga per satuan makin hemat (tier lengkapnya dihitung kasir saat order).';
            }
        }

        if ($tanyaUkuran || $ukuran === null) {
            $batas = [];
            if ($k['min_p'] > 0 && $k['min_l'] > 0) {
                $batas[] = 'ukuran minimal ' . rtrim(rtrim(number_format($k['min_p'], 2, ',', '.'), '0'), ',') . ' x ' . rtrim(rtrim(number_format($k['min_l'], 2, ',', '.'), '0'), ',') . ' m';
            } elseif ($k['min_p'] > 0) {
                $batas[] = 'panjang minimal ' . rtrim(rtrim(number_format($k['min_p'], 2, ',', '.'), '0'), ',') . ' m';
            }
            if ($k['min_order'] > 0) {
                $batas[] = ($k['metode'] === 'ukuran' ? 'luas' : 'jumlah') . ' minimal ' . rtrim(rtrim(number_format($k['min_order'], 2, ',', '.'), '0'), ',');
            }
            if (!empty($batas)) {
                $lines[] = $nama . ': ' . implode(', ', $batas) . '.';
            } elseif ($tanyaUkuran) {
                $lines[] = 'Ukuran ' . $k['nama'] . ' fleksibel sesuai kebutuhan Anda.';
            }
        }

        if ($totalKandidat > 1) {
            $lines[] = 'Varian lain juga tersedia - sebutkan kebutuhan Anda atau chat kasir.';
        }
        $lines[] = 'Untuk hitungan pasti, langsung chat kasir lewat tombol WhatsApp di halaman ini - gratis dan cepat.';

        return implode("\n", $lines);
    }

    /** Id golongan harga publik (Retail; fallback golongan pertama). */
    private function golonganPublikId(): int
    {
        return (int) (Cache::remember('asisten_landing_golongan', self::KATALOG_CACHE_TTL, function () {
            return DB::table('golongan_konsumen')
                ->orderByRaw("CASE WHEN nama_golongan = 'Retail' THEN 0 ELSE 1 END")
                ->orderBy('id_golongan')
                ->value('id_golongan');
        }) ?? 0);
    }

    private function normalisasi(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = preg_replace('/[^\p{L}\p{N}\s\/\-]/u', ' ', $s);
        return preg_replace('/\s+/u', ' ', $s);
    }

    private function balas(string $reply, string $intent): array
    {
        return ['reply' => $reply, 'intent' => $intent];
    }

    private function sapaan(): string
    {
        return "Halo! Saya asisten order toko ini. Saya hanya bisa bantu seputar ORDERAN ya — cara pesan, pembayaran, pengantaran, file desain, layanan, harga, jam buka, dan kontak toko. Ketik \"bantuan\" untuk daftar lengkap, atau langsung tanya misal \"berapa lama pesanan jadi?\"";
    }

    private function daftarBantuan(): string
    {
        return "Yang bisa saya jawab (khusus orderan):\n"
            . "- \"cara pesan\" - langkah order dari awal\n"
            . "- \"cara bayar\" / \"DP\" - opsi pembayaran\n"
            . "- \"harga\" - kenapa harga bervariasi & cara dapat penawaran\n"
            . "- \"layanan\" / \"cetak apa saja\" - daftar produk\n"
            . "- \"file\" - spesifikasi file desain\n"
            . "- \"jam buka\" / \"alamat\" / \"whatsapp\" - kontak toko\n"
            . "- \"lama jadi\" - estimasi pengerjaan\n\n"
            . "Coba ketik salah satu di atas, atau klik chip pintasan di bawah.";
    }
}
