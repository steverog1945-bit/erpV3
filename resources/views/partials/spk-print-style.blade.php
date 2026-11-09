<link rel="stylesheet" href="{{ asset('vendor/fonts/inter.css') }}">
<style>
    /* ==========================================================================
       GAYA CETAK SPK - dipakai bersama

       Bedanya dari partials/print-style.blade.php: ini bukan strukthermal
       atau nota, tapi dokumen kerja internal yang Tempel di mesin dan
       dibaca operator produksi. Jadi tetap perlu padat dan jelas, tapi
       tidak perlu countdown ke kertas.

       Yang diubah dari versi lama, dan alasannya:

       - Gradient ungu di topbar diganti #171717 pekat. OpenCode tidak
         memakai gradient; warna solid gelapnya justru lebih terbaca
         di printer dan memakai tinta jauh lebih sedikit.
       - Bobot 800 -> 500 di semua tempat. Di dokumen ini teks dibaca
         sambil berdiri di depan mesin, bukan di meja; 800 hanya menambah
         bobot yang tidak dibutuhkan. Kerapian datang dari jarak dan
         ukuran, bukan dari tebal.
       - Huruf besar + letter-spacing lebar dibuang. Di slip yang
         dipakai sambil bekerja, huruf besar justru memperlambat.
       - Garis warna 4px di kiri judul bagian dibuang. Itu dekorasi yang
         tidak membawa informasi; judul sudah jelas dari posisinya.
       - Bingkai putus-putus di sekitar gambar diganti garis solid
         satu piksel, supaya tidak tertukar dengan gambar itu sendiri.
       - Semua warna dari token OpenCode: #171717 teks utama, #6f6f6f
         sekunder, #8f8f8f tersier, #f3f3f3 latar, #e5e5e5 garis,
         #034cff aksen, #2dba26 sukses, #ed4831 kritis, #efa72e
         peringatan.
       ========================================================================== */
    * { box-sizing: border-box; }

    body {
        /* Inter: huruf yang sama dengan layar aplikasi, dilayani dari server sendiri. */
        font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        font-size: 13px;
        line-height: 1.45;
        margin: 0;
        padding: 20px;
        color: #171717;
        background: #f3f3f3;
    }

    .sheet {
        max-width: 760px;
        margin: 0 auto;
        background: #ffffff;
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        overflow: hidden;
    }

    /* ----------------------------------------------------------------------
       Kepala
       ---------------------------------------------------------------------- */
    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 20px;
        background: #171717;
        color: #fcfcfc;
    }
    .topbar .brand { display: flex; align-items: center; gap: 10px; }
    .topbar .brand img { height: 26px; object-fit: contain; background: #ffffff; border-radius: 4px; padding: 2px; }
    .topbar .brand strong { display: block; font-size: 13px; font-weight: 500; letter-spacing: -0.01em; }
    .topbar .brand span { display: block; font-size: 13px; color: #a3a3a3; }

    .topbar .doc-badge { text-align: right; }
    /* Pil hilang. Label dokumen jadi teks biasa: bentuk pil tidak
       membawa informasi apa pun, dan menambah tinggi baris. */
    .topbar .doc-label {
        display: block; font-size: 13px; font-weight: 500; color: #a3a3a3;
    }
    .topbar .doc-code { display: block; margin-top: 3px; font-size: 13px; font-weight: 500; }

    /* ----------------------------------------------------------------------
       Blok utama: gambar desain di kiri, data di kanan
       ---------------------------------------------------------------------- */
    .hero { display: flex; gap: 18px; padding: 18px 20px; border-bottom: 1px solid #e5e5e5; align-items: flex-start; }

    .hero-thumb {
        flex: 0 0 auto;
        min-width: 160px;
        min-height: 160px;
        max-width: 320px;
        max-height: 320px;
        border-radius: 6px;
        overflow: hidden;
        background: #f3f3f3;
        border: 1px solid #e5e5e5;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .hero-thumb img { display: block; max-width: 320px; max-height: 320px; width: auto; height: auto; }
    .hero-thumb .placeholder { color: #8f8f8f; font-size: 13px; text-align: center; padding: 0 12px; }

    .hero-info { flex: 1; min-width: 0; }
    .hero-info h1 { margin: 0 0 4px; font-size: 20px; font-weight: 500; color: #171717; letter-spacing: -0.01em; }
    .hero-info .sub { margin: 0 0 14px; font-size: 13px; color: #6f6f6f; }

    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 16px; }
    /* Label field: 9px uppercase + 800 adalah pola yang paling sering
       membuat tabel terasa berat. Diganti 10px normal dengan warna
       tersier - tetap berbeda dari isinya, tapi tidak berteriak. */
    .info-grid .item span { display: block; font-size: 13px; color: #8f8f8f; margin-bottom: 2px; }
    .info-grid .item strong { font-size: 13px; font-weight: 500; color: #171717; }
    .info-grid .item strong.accent { color: #034cff; font-size: 15px; }
    /* Keterangan peran di dalam nilai (mis. "Administrator" setelah nama
       desainer). Bobot 400 supaya tidak ikut dibaca sebagai bagian dari
       nama, dan warnanya sama dengan label - keduanya properties. */
    .info-grid .item strong .info-catatan { font-weight: 400; color: #6f6f6f; }

    /* Blok kode QR di bawah tabel bahan. Terpusat, tanpa bingkai. */
    .kode-blok { margin: 8px 0; text-align: center; }
    .kode-qr { display: inline-block; }
    /* Kode transaksi dibaca scanner atau diketik ulang manual, jadi
       spacing-nya bukan dekorasi. 0.5px, bukan 1px - pada label 10px,
       1px sudah cukup untuk membuat huruf bersenggolan. */
    .kode-trx { font-size: 13px; letter-spacing: 0.5px; margin-top: 2px; color: #171717; }
    .scan-caption { font-size: 13px; color: #6f6f6f; }

    /* Catatan produksi */
    .keterangan-box { margin: 14px 20px 0; padding: 10px 0 0; border-top: 1px solid #e5e5e5; }
    .keterangan-box p { margin: 0 0 3px; font-size: 13px; color: #8f8f8f; }
    .keterangan-box pre { margin: 0; font-family: inherit; font-size: 13px; white-space: pre-wrap; color: #171717; line-height: 1.6; }

    /* ----------------------------------------------------------------------
       Bagian
       ---------------------------------------------------------------------- */
    .section { padding: 16px 20px 4px; }

    /* Garis warna di kiri judul dihapus - dekorasi tanpa informasi. */
    .section-title {
        margin: 0 0 10px;
        font-size: 15px;
        font-weight: 500;
        color: #171717;
    }
    .section-title::before { content: none; }
    .section-title .note { font-size: 13px; color: #8f8f8f; }

    /* ----------------------------------------------------------------------
       Chip ukuran
       ---------------------------------------------------------------------- */
    .size-chips { display: flex; flex-wrap: wrap; gap: 8px; padding-bottom: 4px; }
    .size-chips .chip {
        display: flex;
        align-items: stretch;
        border-radius: 6px;
        overflow: hidden;
        border: 1px solid #e5e5e5;
        min-width: 56px;
    }
    .size-chips .chip .chip-size {
        padding: 5px 9px;
        background: #f3f3f3;
        font-size: 13px;
        font-weight: 500;
        color: #171717;
        display: flex;
        align-items: center;
    }
    .size-chips .chip .chip-qty {
        padding: 5px 10px;
        background: #f3f3f3;
        font-size: 13px;
        font-weight: 500;
        color: #034cff;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 26px;
        border-left: 1px solid #e5e5e5;
    }
    .size-chips .chip-total {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: 4px;
        padding: 5px 12px;
        background: #171717;
        border-radius: 6px;
        color: #fcfcfc;
        font-size: 13px;
        font-weight: 500;
    }

    .empty-note { font-size: 13px; color: #8f8f8f; font-style: italic; }

    /* ----------------------------------------------------------------------
       Tabel
       ---------------------------------------------------------------------- */
    table.roster, table.material { width: 100%; border-collapse: collapse; margin-bottom: 6px; }

    table.roster th, table.material th {
        background: #f8f8f8;
        font-size: 13px;
        font-weight: 500;
        color: #6f6f6f;
        text-align: left;
        padding: 6px 8px;
        border-bottom: 1px solid #e5e5e5;
    }
    table.roster td, table.material td { padding: 6px 8px; font-size: 13px; border-bottom: 1px solid #f3f3f3; color: #171717; }

    /* Zebra dihapus. Pada daftar bahan dan roster yang biasanya satu
       halaman penuh, garis horizontal sudah cukup membedakan baris;
       belang abu-abu hanya menambah kabut saatCETAK. */
    table.roster tr:nth-child(even) td,
    table.material tr:nth-child(even) td { background: transparent; }

    table.roster td.num, table.material td.num { text-align: right; font-weight: 500; color: #034cff; }

    /* Kolom angka di header juga rata kanan. Karena th sudah punya
       text-align: left dari aturan umum tabel, header angka harus
       ditimpa di sini - kalau tidak, judul "Perkiraan Meter" berdiri
       di kiri sementara angkanya di kanan, dan kolomnya terbaca dua
       arah. */
    table.roster th.num, table.material th.num { text-align: right; }

    /* Baris TOTAL. Garis atas 1px abu-abu + bobot 500, bukan garis
       1.5px hitam + bobot 800 seperti sebelumnya. */
    .total-row td {
        border-top: 1px solid #8f8f8f;
        font-weight: 500;
        color: #171717;
    }
    .total-row td.num { color: #171717; }

    /* Baris kosong ("Belum ada data estimasi..."). Padding-nya
       lebih longgar supaya pesan itu tidak terlihat seperti baris
       data yang isinya hilang. */
    .empty-row td { padding: 12px 8px; }

    /* Judul kelompok di dalam tabel bahan (Kain jersey / Rib). */
    table.material tr.kelompok td { padding-top: 12px; color: #6f6f6f; border-bottom: 1px solid #e5e5e5; }

    /* ----------------------------------------------------------------------
       Catatan bawah
       ---------------------------------------------------------------------- */
    /* Latar kuning krem diganti garis pemisah + teks warna peringatan.
       Warna #fef3c7 di printer Laser sering hilang atau berubah jadi
       abu-abu; teks berwarna lebih andal. */
    .footer-strip {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 6px 20px 0;
        padding: 8px 0 0;
        border-top: 1px solid #e5e5e5;
    }
    .footer-strip .dot { width: 5px; height: 5px; border-radius: 50%; background: #efa72e; flex-shrink: 0; }
    .footer-strip span { font-size: 13px; color: #171717; }

    /* ----------------------------------------------------------------------
       Tanda tangan
       ---------------------------------------------------------------------- */
    .signature { display: flex; padding: 22px 20px 18px; gap: 20px; }
    .signature > div { flex: 1; text-align: center; }
    .signature p.role { font-size: 13px; color: #8f8f8f; margin: 0 0 42px; }
    .signature .line { border-top: 1px solid #8f8f8f; width: 75%; margin: 0 auto; }

    /* ----------------------------------------------------------------------
       Tombol cetak
       ---------------------------------------------------------------------- */
    .no-print { text-align: center; margin-top: 18px; }
    .no-print button {
        padding: 8px 20px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        background: #171717;
        color: #fcfcfc;
        border: none;
        border-radius: 6px;
    }
    .no-print button:hover { background: #000000; }

    @media screen and (max-width: 640px) {
        body { padding: 10px; }
        .hero { flex-direction: column; }
        .hero-thumb { flex: none; width: 100%; max-width: 100%; max-height: 220px; }
        table.roster, table.material { display: block; overflow-x: auto; white-space: nowrap; }
    }

    @media print {
        body { background: #ffffff; padding: 0; }
        .sheet { border: none; border-radius: 0; max-width: 100%; }
        .no-print { display: none !important; }
    }
</style>
