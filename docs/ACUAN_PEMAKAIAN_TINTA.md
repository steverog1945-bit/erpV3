# Acuan Pemakaian Tinta (untuk Laporan Biaya Tinta)

Bahan baku tinta/toner per mesin sudah didaftarkan di Master Bahan Baku (stok 0,
harga kosong). Isi **stok** lewat Kedatangan Bahan (atau Stok Awal) dan **harga**
lewat Pembelian Bahan atau tombol harga di daftar bahan.

| Kode | Bahan | Mesin | Satuan stok / hitung |
|---|---|---|---|
| TNT-S80670 | Tinta Eco-Solvent Epson S80670 (CMYK) | Epson S80670 | liter / ml |
| TNT-FLORA | Tinta Solvent Flora Outdoor (CMYK) | Flora Outdoor | liter / ml |
| TNR-KONICA | Toner Konica Minolta (set CMYK) | Konica Minolta | pcs |
| TNT-CENTURY | Tinta Direct Print Century Star | Century Star | liter / ml |
| TNT-DTF-CMYK | Tinta DTF CMYK | Rhino DTF | liter / ml |
| TNT-DTF-WHT | Tinta DTF White | Rhino DTF | liter / ml |
| PWD-DTF | Powder DTF (hot melt) | Rhino DTF | kg / gram |
| TNT-SUBLIME | Tinta Sublime (CMYK) | Epson F7270 | liter / ml |

## Perkiraan pemakaian per m2 cetak (ACUAN - wajib diverifikasi)

Angka pabrikan/kebiasaan lapangan untuk kualitas standar dengan tutupan warna
sedang. Ukur riil dengan mencatat stok tinta sebelum dan sesudah cetak 20-50 m2.

| Mesin | Tinta | Perkiraan per m2 |
|---|---|---|
| Epson S80670 (eco-solvent) | CMYK | 10 ml |
| Flora Outdoor (solvent) | CMYK | 12 ml |
| Century Star (direct print) | Tinta | 18 ml |
| Rhino DTF | CMYK | 12 ml |
| Rhino DTF | White | 8 ml |
| Rhino DTF | Powder | 25 gram |
| Epson F7270 (sublime) | CMYK | 9 ml |
| Konica Minolta (toner) | Toner | dihitung per lembar dari isi toner (yield) |

## Rencana perhitungan di laporan (belum dibuat)

Biaya tinta per pesanan = luas cetak (m2) x pemakaian per m2 x harga rata-rata
per ml. Laporan yang akan memuatnya: Pemakaian Bahan, Job Costing, dan
Profitabilitas (biaya tinta menjadi baris biaya sendiri, terpisah dari media).
Pemakaian per m2 per mesin akan disimpan sebagai pengaturan yang bisa diubah,
bukan angka tetap di kode.
