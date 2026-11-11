<?php

namespace App\Services;

use App\Models\AppSetting;
use Carbon\Carbon;

/**
 * Asisten chat publik di landing page - HANYA menjawab seputar ORDERAN
 * (cara pesan, pembayaran, pengantaran, file, layanan, kontak, jam).
 *
 * Sengaja rule-based (bukan AI): tidak ada cara mem-bypass filter lewat
 * prompt injection, dan mustahil membocorkan data internal. Omzet,
 * pemasukan, laba, HPP, gaji, laporan keuangan dan angka perusahaan apa pun
 * ditolak tegas - halaman ini dibaca publik.
 */
class AsistenLandingService
{
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

        // 7) Harga / biaya.
        if (preg_match('/(harga|biaya|berapa|tarif|ongkos|price|rate)/u', $t)) {
            return $this->balas(
                'Harga sangat tergantung ukuran, bahan, dan finishing (mis. banner 1 m² vs 4 m² beda jauh). Saya tidak bisa menyebut angka satu harga untuk semua - untuk penawaran akurat, ukur dulu dengan kasir/tim kami lewat WhatsApp atau datang langsung. Sebutkan ukuran & bahan yang Anda inginkan, pasti dibantu.',
                'harga'
            );
        }

        // 8) Layanan / jenis produk yang bisa dicetak.
        if (preg_match('/(layanan|jasa|cetak apa|produk|bisa (cetak|buat|bikin)|bikin (banner|spanduk|stiker|sticker|brosur|kartu nama|jersey|sablon|id card)|jersey|sablon)/u', $t)) {
            return $this->balas(
                'Kami melayani antara lain: banner/spanduk, stiker & label, brosur/flyer, kartu nama, ID card, backdrop & pameran, sablon jersey (printing + press), plang nama, nota & kop surat, stempel, undangan, dan lainnya. Untuk item khusus, chat kasir - produksi kami bisa bantu desain sekalian.',
                'layanan'
            );
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

        // 14) Ukuran / besar.
        if (preg_match('/(ukuran|besar|lebar|panjang|skala)/u', $t)) {
            return $this->balas(
                'Boleh ukuran berapa pun sesuai kebutuhan - tim kami bisa bantu mengukur & merekomendasikan ukuran hemat. Ukuran paling umum: banner 1x2 m sampai 4x6 m, backdrop kelas atas 3x3 sampai 3x4 m, stiker dll mengikuti ukuran bahan. Chat kasir untuk konsultasi gratis.',
                'ukuran'
            );
        }

        return $this->balas($this->daftarBantuan(), 'fallback');
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
