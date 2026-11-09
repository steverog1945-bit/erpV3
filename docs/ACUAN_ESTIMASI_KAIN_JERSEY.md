# Acuan Estimasi Kain Jersey (untuk Diverifikasi di Lapangan)

Angka di bawah sudah terisi di Master Data > Jersey > Estimasi Bahan. Awalnya
dari angka yang Anda input sendiri (dipulihkan dari backup 13 Sep 2026), lalu
diaudit kewajarannya (24 Sep 2026). **Ini acuan awal, bukan hasil ukur riil** -
verifikasi dulu sebelum go-live.

## Estimasi per pcs (meter kain / gram)

| Ukuran | Body | Lengan Pendek | Lengan Panjang | Celana Pendek |
|---|---|---|---|---|
| S | 0,71 m / 160 g | 0,18 m / 40 g | 0,42 m / 95 g | 0,55 m / 125 g |
| M | 0,74 m / 165 g | 0,19 m / 45 g | 0,44 m / 100 g | 0,60 m / 135 g |
| L | 0,78 m / 175 g | 0,20 m / 45 g | 0,47 m / 105 g | 0,65 m / 145 g |
| XL | 0,83 m / 185 g | 0,21 m / 45 g | 0,49 m / 110 g | 0,70 m / 160 g |
| 2XL | 0,88 m / 200 g | 0,22 m / 50 g | 0,52 m / 115 g | 0,76 m / 170 g |
| 3XL | 0,93 m / 210 g | 0,24 m / 55 g | 0,55 m / 125 g | 0,82 m / 185 g |
| 4XL | 0,99 m / 225 g | 0,25 m / 55 g | 0,58 m / 130 g | 0,88 m / 200 g |
| 5XL | 1,05 m / 235 g | 0,26 m / 60 g | 0,61 m / 135 g | 0,94 m / 210 g |

Rib (di luar kerah): 0,03 m / 5 g (S-2XL) dan 0,04 m / 5 g (3XL-5XL).

Estimasi Kerah per tipe (meter / gram per pcs, sama untuk semua ukuran; gram
naik di ukuran besar):

| Tipe Kerah | Meter | Gram (S-L / XL-2XL / 3XL-5XL) |
|---|---|---|
| Oneck | 0,08 m | 7 / 8 / 10 |
| Vneck | 0,10 m | 8 / 10 / 12 |
| Wangki | 0,12 m | 10 / 12 / 15 |
| Shanghai Zip | 0,24 m | 20 / 24 / 30 |

Hanya bagian yang dipesan yang ikut dihitung: jersey lengan pendek tidak
memuat lengan panjang maupun celana, dan celana tidak memuat body/lengan/kerah.

## Hasil audit kewajaran (24 Sep 2026)

Dua uji dipakai: (1) gram harus sebanding dengan meter, (2) pola kenaikan antar
ukuran harus halus dan masuk akal.

| Temuan | Perbaikan |
|---|---|
| Berat per meter melebar dari ~200 sampai ~300 g/m (mis. Body 5XL 320 g untuk 1,04 m). Kain jersey dryfit ~150 gsm x lebar 1,5 m = ~225 g per meter. | Gram dihitung ulang = meter x 225 g/m, dibulatkan 5 g. |
| Kurva Body tidak halus: S-L hampir datar, lalu XL melompat 15%. | Dirapikan naik ~5,5% per ukuran (lingkar dada +2 cm, panjang +1,5 cm). |
| Lengan pendek naik 9% per ukuran (0,18 ke 0,34), padahal body 5,5%. Lengan panjang serupa. | Disamakan ~5,5% per ukuran. |
| Celana pendek 0,84-1,25 m tidak wajar: celana pendek dewasa L sekitar 0,65 m pada lebar kain 1,5 m. 1 m kain juga lebih mahal dari harga jual celana (Rp 30.000). | Diturunkan ke 0,55-0,94 m. |
| Kerah dan Rib | Sudah masuk akal (5-30 g per kerah), tidak diubah. |

Total jersey lengan pendek ukuran L sekarang 0,98 m (Body + Lengan) dan sekitar
220 g, sesuai kebiasaan pola jersey dewasa pada kain lebar 1,5 m.

Catatan lain: matriks Lengan masih menyimpan angka untuk 2 tipe lengan lama
(id 5 dan 6) yang sudah tidak ada di daftar. Tidak dipakai; abaikan.

## Cara verifikasi riil (disarankan, sekali saja per tipe)

1. Ambil 1 pola jadi untuk tiap ukuran (minimal S, L, XL, 3XL; sisanya
   mengikuti selisih).
2. Ukur panjang kain terpakai per bagian (body, lengan, celana, kerah) pada
   lebar kain yang biasa dipakai, tambah kira-kira 3-5% untuk sisa potong.
3. Timbang kain terpakai. Jika berat per meter berbeda jauh dari 225 g,
   gantilah gram (bukan meternya) - bahan Milano/Brazil/Nike bisa lebih ringan
   atau lebih berat.
4. Ubah angka di Master Data > Jersey > Estimasi Bahan bila selisih dengan
   tabel di atas lebih dari sekitar 5%.

Catatan: estimasi hanya perkiraan (bukan pemotongan stok). Angka riil dicatat
terpisah lewat Waste/Stok Opname.
