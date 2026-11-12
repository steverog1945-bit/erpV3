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
        // - jawab dari data katalog NYATA (harga jual golongan Umum, tier
        // qty terkecil), bukan kalimat generik.
        $katalog = $this->katalog();
        if (!empty($katalog)) {
            $produk = $this->cariProduk($katalog, $t);
            if ($produk !== null) {
                return $this->jawabProduk($produk, $t);
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
        $golonganUmum = DB::table('golongan_konsumen')
            ->orderBy('id_golongan')->orderByRaw("FIELD(nama_golongan, 'Umum') DESC")
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
                'sh.range_min', 'sh.harga_jual', 'sh.id_satuan',
                's.nama_satuan',
            ]);

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
                'tiers' => 1,
            ];
        }

        return array_values($katalog);
    }

    /**
     * Cocokkan teks pengunjung dengan nama produk: skor = jumlah token nama
     * produk (>=3 huruf, bukan angka, bukan kata umum "cetak/print") yang
     * muncul utuh di teks. Skor tertinggi menang.
     */
    private function cariProduk(array $katalog, string $t): ?array
    {
        $stopwords = ['cetak', 'cetakan', 'percetakan', 'print', 'printing', 'bikin', 'buat', 'harga', 'berapa', 'ukuran'];
        $best = null;
        $bestScore = 0;
        foreach ($katalog as $k) {
            $tokens = preg_split('/\s+/u', $this->normalisasi($k['nama'])) ?: [];
            $score = 0;
            foreach ($tokens as $tok) {
                if (mb_strlen($tok) < 3 || is_numeric($tok) || in_array($tok, $stopwords, true)) {
                    continue;
                }
                if (preg_match('/(^|\s)' . preg_quote($tok, '/') . '(\s|$)/u', $t)) {
                    $score++;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $k;
            }
        }

        return $best;
    }

    /**
     * Jawaban per produk: harga mulai (atau harga promo bila aktif) + batas
     * ukuran/min order dari master. Tanpa kalimat yang mengklaim pasti -
     * total final tetap dihitung kasir dari ukuran & finishing.
     */
    private function jawabProduk(array $k, string $t): array
    {
        $tanyaUkuran = (bool) preg_match('/(ukuran|besar|lebar|panjang|minimal|minimum|min berapa)/u', $t);
        $tanyaHarga = (bool) preg_match('/(harga|biaya|berapa|tarif|ongkos|price|rate)/u', $t);

        $lines = [];
        if ($tanyaHarga || !$tanyaUkuran) {
            if ($k['promo'] !== null) {
                $lines[] = 'Harga ' . $k['nama'] . ' saat ini PROMO: Rp ' . number_format((float) $k['promo'], 0, ',', '.') . ($k['satuan'] !== '' ? ' / ' . $k['satuan'] : '') . ' (berlaku untuk semua pelanggan).';
            } else {
                $lines[] = 'Harga mulai ' . $k['nama'] . ': Rp ' . number_format((float) $k['harga'], 0, ',', '.') . ($k['satuan'] !== '' ? ' / ' . $k['satuan'] : '') . ' (harga umum untuk jumlah paling kecil).';
                if ($k['tiers'] > 1) {
                    $lines[] = 'Semakin besar jumlah/luasnya, harga per satuan makin hemat (tier lengkapnya dihitung kasir saat order).';
                }
            }
        }

        if ($tanyaUkuran || !$tanyaHarga) {
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
                $lines[] = $k['nama'] . ': ' . implode(', ', $batas) . '.';
            } else {
                $lines[] = 'Ukuran ' . $k['nama'] . ' fleksibel sesuai kebutuhan Anda.';
            }
        }

        $lines[] = 'Untuk hitungan pasti (ukuran & bahan Anda), langsung chat kasir lewat tombol WhatsApp di halaman ini - gratis dan cepat.';

        return $this->balas(implode("\n", $lines), ($tanyaHarga || !$tanyaUkuran) ? 'harga-produk' : 'ukuran-produk');
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
