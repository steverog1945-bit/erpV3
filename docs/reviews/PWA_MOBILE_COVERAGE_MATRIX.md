# ORION GRAFIKA â€” PWA Mobile Coverage (complete file inventory)

**Checkpoint:** 26 Oktober 2026. **Branch:** `feat/pwa-mobile-completion-phase2`. **PR:** [#21](https://github.com/steverog1945-bit/percetakan-app/pull/21). **Status:** IN PROGRESS; production NO-GO.

## Provenance and limitations

- Inventaris ini dihasilkan kembali dari **194 file Blade** di branch PR #21. Ini **bukan 194 route aktif**; route/menu/role perlu dicocokkan dengan kode routes, controller, permission dan diuji pada staging.
- `SOURCE MOBILE (UNTESTED)`: kelas Blade dan CSS mobile modul spesifik sudah ada. Ini **bukan PASS** untuk tampilan Laravel browser, interaksi, permission, desktop maupun perangkat.
- `GENERIC ONLY / UI TODO`: hanya lapisan responsif umum; implementasi atau penilaian route masih diperlukan.
- `PARTIAL / NOT ROUTE`: reusable partial/layout yang perlu diperiksa lewat parent route; bukan otomatis route.
- `PRINT VIEW / PRINT TEST DUE`: calon halaman print/ekspor yang tetap harus diuji format cetaknya.
- Semua tes interaksi Laravel, RBAC/IDOR, transaksi, iOS/Android PWA dan desktop parity **NOT TESTED**, kecuali ada catatan evidence terbaru per screen.

## Ringkasan yang cocok dengan source

| Status source | Jumlah view |
|---|---:|
| GENERIC ONLY / UI TODO | 108 |
| PARTIAL / NOT ROUTE | 63 |
| SOURCE MOBILE (UNTESTED) | 14 |
| PRINT VIEW / PRINT TEST DUE | 9 |
| **TOTAL** | **194** |

**Implementasi terbaru PR #21** pada commit `875f078`: Monitoring Kasir, Pembelian Bahan, Kedatangan Bahan, Kartu Stok. Sebelumnya dari PR #20: Collecting dan Jersey order index/create/edit-lines/keuangan, Master Bahan, POS create/bayar, SPK dan Produksi.

## Matriks lengkap (pertahankan per-file)

| Blade file | Grup | Status source | Route / Role / Browser / Function |
|---|---|---|---|
| `resources/views/accounting-period/index.blade.php` | accounting-period | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/akses/_matriks.blade.php` | akses | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/akses/index.blade.php` | akses | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/akses/user.blade.php` | akses | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/akuntansi/_subnav.blade.php` | akuntansi | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/akuntansi/buku-besar.blade.php` | akuntansi | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/akuntansi/jurnal.blade.php` | akuntansi | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/akuntansi/laba-rugi.blade.php` | akuntansi | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/akuntansi/neraca-saldo.blade.php` | akuntansi | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/akuntansi/neraca.blade.php` | akuntansi | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/akuntansi/perbandingan.blade.php` | akuntansi | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/analisis/biaya-tinta.blade.php` | analisis | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/analisis/dashboard.blade.php` | analisis | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/analisis/pemakaian-bahan.blade.php` | analisis | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/analisis/profitabilitas.blade.php` | analisis | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/approval/index.blade.php` | approval | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/audit-log/index.blade.php` | audit-log | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/audit-owner/index.blade.php` | audit-owner | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/backup/index.blade.php` | backup | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/berkas-order/show.blade.php` | berkas-order | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/collecting/index.blade.php` | collecting | SOURCE MOBILE (UNTESTED) | NOT VERIFIED |
| `resources/views/components/badge.blade.php` | components | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/components/button.blade.php` | components | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/components/card.blade.php` | components | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/components/empty-state.blade.php` | components | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/components/form/group.blade.php` | components | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/components/form/input.blade.php` | components | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/components/form/select.blade.php` | components | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/components/laporan/print-table.blade.php` | components | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/components/laporan/resume-table.blade.php` | components | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/components/modal.blade.php` | components | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/components/page-header.blade.php` | components | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/components/polling-notifikasi.blade.php` | components | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/components/table.blade.php` | components | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/components/table/pagination.blade.php` | components | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/dashboard-desain.blade.php` | dashboard-desain.blade.php | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/dashboard-gudang.blade.php` | dashboard-gudang.blade.php | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/dashboard-produksi.blade.php` | dashboard-produksi.blade.php | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/dashboard.blade.php` | dashboard.blade.php | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/finishing/index.blade.php` | finishing | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/jasa-jahit/index.blade.php` | jasa-jahit | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/jersey/keuangan/index.blade.php` | jersey | SOURCE MOBILE (UNTESTED) | NOT VERIFIED |
| `resources/views/jersey/master/_filter-toolbar.blade.php` | jersey | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/jersey/master/_row-harga.blade.php` | jersey | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/jersey/master/_row-simple.blade.php` | jersey | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/jersey/master/_row-size.blade.php` | jersey | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/jersey/master/index.blade.php` | jersey | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/jersey/orders/_csv-pemain-card-buttons.blade.php` | jersey | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/jersey/orders/_csv-pemain-hasil-panel.blade.php` | jersey | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/jersey/orders/_csv-pemain-tools.blade.php` | jersey | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/jersey/orders/_line-card-fields.blade.php` | jersey | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/jersey/orders/_player-row-fields.blade.php` | jersey | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/jersey/orders/_size-helper.blade.php` | jersey | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/jersey/orders/create.blade.php` | jersey | SOURCE MOBILE (UNTESTED) | NOT VERIFIED |
| `resources/views/jersey/orders/edit-lines.blade.php` | jersey | SOURCE MOBILE (UNTESTED) | NOT VERIFIED |
| `resources/views/jersey/orders/index.blade.php` | jersey | SOURCE MOBILE (UNTESTED) | NOT VERIFIED |
| `resources/views/jersey/orders/print.blade.php` | jersey | PRINT VIEW / PRINT TEST DUE | NOT VERIFIED |
| `resources/views/jersey/orders/show.blade.php` | jersey | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/jersey/orders/spk-print.blade.php` | jersey | PRINT VIEW / PRINT TEST DUE | NOT VERIFIED |
| `resources/views/kartu_stok/detail.blade.php` | kartu_stok | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/kartu_stok/index.blade.php` | kartu_stok | SOURCE MOBILE (UNTESTED) | NOT VERIFIED |
| `resources/views/kas-kecil/index.blade.php` | kas-kecil | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/kasir/form_pembayaran.blade.php` | kasir | SOURCE MOBILE (UNTESTED) | NOT VERIFIED |
| `resources/views/kasir/kelola_kas.blade.php` | kasir | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/kasir/monitoring_transaksi.blade.php` | kasir | SOURCE MOBILE (UNTESTED) | NOT VERIFIED |
| `resources/views/kasir/open_order.blade.php` | kasir | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/kasir/preview_closing.blade.php` | kasir | PRINT VIEW / PRINT TEST DUE | NOT VERIFIED |
| `resources/views/kasir/print_closing.blade.php` | kasir | PRINT VIEW / PRINT TEST DUE | NOT VERIFIED |
| `resources/views/kasir/print_nota.blade.php` | kasir | PRINT VIEW / PRINT TEST DUE | NOT VERIFIED |
| `resources/views/kasir/waiting_list.blade.php` | kasir | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/kedatangan-bahan/create.blade.php` | kedatangan-bahan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/kedatangan-bahan/form.blade.php` | kedatangan-bahan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/kedatangan-bahan/index.blade.php` | kedatangan-bahan | SOURCE MOBILE (UNTESTED) | NOT VERIFIED |
| `resources/views/konsumen-deposit/index.blade.php` | konsumen-deposit | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/konsumen/timeline.blade.php` | konsumen | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/landing-page-public.blade.php` | landing-page-public.blade.php | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/laporan/belanja-bahan.blade.php` | laporan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/laporan/biaya-desain.blade.php` | laporan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/laporan/evaluasi-master.blade.php` | laporan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/laporan/hpp.blade.php` | laporan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/laporan/hutang.blade.php` | laporan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/laporan/job-costing.blade.php` | laporan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/laporan/kas.blade.php` | laporan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/laporan/order-jersey.blade.php` | laporan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/laporan/order.blade.php` | laporan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/laporan/piutang.blade.php` | laporan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/laporan/produksi.blade.php` | laporan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/laporan/stok-bahan.blade.php` | laporan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/layouts/app.blade.php` | layouts | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/layouts/badge.blade.php` | layouts | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/layouts/button.blade.php` | layouts | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/layouts/card.blade.php` | layouts | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/layouts/detail.blade.php` | layouts | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/layouts/empty-state.blade.php` | layouts | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/layouts/group.blade.php` | layouts | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/layouts/index.blade.php` | layouts | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/layouts/input.blade.php` | layouts | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/layouts/modal.blade.php` | layouts | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/layouts/page-header.blade.php` | layouts | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/layouts/pagination.blade.php` | layouts | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/layouts/select.blade.php` | layouts | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/layouts/table.blade.php` | layouts | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/login.blade.php` | login.blade.php | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/akun/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/bahan/index.blade.php` | master | SOURCE MOBILE (UNTESTED) | NOT VERIFIED |
| `resources/views/master/bank/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/golongan/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/gudang/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/harga-promo/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/harga_siap_cetak/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/jasa/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/jenis-bahan/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/karyawan/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/kategori-kas-kecil/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/kategori/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/konsumen/_field.blade.php` | master | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/master/konsumen/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/merk/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/mesin/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/pendukung/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/platform-belanja/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/produk/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/satuan/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/setting_harga/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/supplier/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/master/user/index.blade.php` | master | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/masuk.blade.php` | masuk.blade.php | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/partials/command-palette.blade.php` | partials | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/partials/dashboard-info-terbaru.blade.php` | partials | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/partials/dashboard-tab.blade.php` | partials | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/partials/divisi-tabs.blade.php` | partials | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/partials/image-compress-script.blade.php` | partials | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/partials/modal-pelunasan-hutang.blade.php` | partials | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/partials/print-header.blade.php` | partials | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/partials/print-report-header.blade.php` | partials | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/partials/print-report-table.blade.php` | partials | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/partials/print-style.blade.php` | partials | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/partials/quick-picker.blade.php` | partials | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/partials/rupiah-format-script.blade.php` | partials | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/partials/spk-print-style.blade.php` | partials | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/partials/supplier-baru-modal.blade.php` | partials | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/partials/tooltip-script.blade.php` | partials | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/pembelian-bahan/create.blade.php` | pembelian-bahan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/pembelian-bahan/edit.blade.php` | pembelian-bahan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/pembelian-bahan/index.blade.php` | pembelian-bahan | SOURCE MOBILE (UNTESTED) | NOT VERIFIED |
| `resources/views/pembelian-bahan/print.blade.php` | pembelian-bahan | PRINT VIEW / PRINT TEST DUE | NOT VERIFIED |
| `resources/views/pembelian-bahan/show.blade.php` | pembelian-bahan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/pengaturan/aplikasi.blade.php` | pengaturan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/pengaturan/desainer.blade.php` | pengaturan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/pengaturan/jam-kerja.blade.php` | pengaturan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/pengaturan/landing-page-builder.blade.php` | pengaturan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/pengaturan/landing-page-preview-iframe.blade.php` | pengaturan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/pengaturan/landing-page-preview.blade.php` | pengaturan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/pengaturan/menu-editor.blade.php` | pengaturan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/pengaturan/partials/footer-render.blade.php` | pengaturan | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/pengaturan/partials/renderer-styles.blade.php` | pengaturan | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/pengaturan/partials/scroll-reveal.blade.php` | pengaturan | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/pengaturan/partials/section-render.blade.php` | pengaturan | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/pengaturan/partials/smooth-scroll.blade.php` | pengaturan | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/pengaturan/tema.blade.php` | pengaturan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/penjualan-barang/create.blade.php` | penjualan-barang | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/penjualan/create.blade.php` | penjualan | SOURCE MOBILE (UNTESTED) | NOT VERIFIED |
| `resources/views/penjualan/edit-approval.blade.php` | penjualan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/penjualan/page-header.blade.php` | penjualan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/penjualan/riwayat.blade.php` | penjualan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/penjualan/show.blade.php` | penjualan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/postingan/_daftar.blade.php` | postingan | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/postingan/form.blade.php` | postingan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/postingan/index.blade.php` | postingan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/postingan/show.blade.php` | postingan | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/produksi/index.blade.php` | produksi | SOURCE MOBILE (UNTESTED) | NOT VERIFIED |
| `resources/views/publik/_kartu.blade.php` | publik | PARTIAL / NOT ROUTE | NOT VERIFIED |
| `resources/views/publik/baca.blade.php` | publik | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/publik/katalog-produk.blade.php` | publik | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/publik/layout-artikel.blade.php` | publik | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/publik/layout-masuk.blade.php` | publik | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/publik/layout.blade.php` | publik | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/purchase-order/form.blade.php` | purchase-order | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/purchase-order/index.blade.php` | purchase-order | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/purchase-request/form.blade.php` | purchase-request | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/purchase-request/index.blade.php` | purchase-request | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/qc/create.blade.php` | qc | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/qc/index.blade.php` | qc | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/quotation/form.blade.php` | quotation | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/quotation/index.blade.php` | quotation | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/quotation/print.blade.php` | quotation | PRINT VIEW / PRINT TEST DUE | NOT VERIFIED |
| `resources/views/spk/index.blade.php` | spk | SOURCE MOBILE (UNTESTED) | NOT VERIFIED |
| `resources/views/spk/print-lengkap.blade.php` | spk | PRINT VIEW / PRINT TEST DUE | NOT VERIFIED |
| `resources/views/spk/print.blade.php` | spk | PRINT VIEW / PRINT TEST DUE | NOT VERIFIED |
| `resources/views/stock-opname/create.blade.php` | stock-opname | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/stock-opname/edit.blade.php` | stock-opname | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/stock-opname/index.blade.php` | stock-opname | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/waste/create.blade.php` | waste | GENERIC ONLY / UI TODO | NOT VERIFIED |
| `resources/views/waste/index.blade.php` | waste | GENERIC ONLY / UI TODO | NOT VERIFIED |

## Queue operasional tanpa prompt ulang

1. Petakan `routes/web.php`, route file lain, definisi menu, permission dan view per role; update kolom route/role untuk setiap layar aktif. Jangan menjadikan path sebagai bukti izin.
2. Implementasikan berikutnya: Kasir `waiting_list`, `open_order`, `kelola_kas`; Pembelian `create/edit/show`, Kedatangan `create/form`; kartu stok `detail`; gudang/logistik; semua Master lain; keuangan dan laporan; dashboard/setting sesuai rute. Pertahankan aksi, label, validasi, konfirmasi, bulk selection.
3. Commit setiap gugus ke **PR #21 yang sama**, catat screen yang betul-betul diubah. Jangan menulis `SELESAI` ketika masih terdapat view/route aktif UI TODO.
4. Jalankan build GitHub CI dan, apabila tersedia lingkungan staging yang terbukti sintetis serta terisolasi, tes Laravel, peran, transaksi, modal, keyboard, PWA, desktop. Record PASS/FAIL/NOT TESTED dengan SHA dan URL bukti.
5. Jika seluruh UI selesai tetapi runtime NOT TESTED, laporkan FRONTEND SOURCE COMPLETE / PROD NO-GO; jangan merge main/deploy tanpa GO owner/operator.

**Keselamatan:** jangan akses `C:\xampp\htdocs\grafikaorion`, `.env`, database `oriondb`, data pelanggan, SSL, backup atau Windows services produksi.

## Visual Verification (PR #21, Phase 2)

Status diperbarui berdasarkan screenshot nyata runtime (lihat `PWA_MOBILE_VISUAL_GALLERY.md`).

| File view | Route | Status visual |
|---|---|---|
| `resources/views/kasir/monitoring_transaksi.blade.php` | `GET /kasir/monitoring` | SCREENSHOT VERIFIED (390/430/1366) — filter compact collapsible, header leak fix |
| `resources/views/kasir/waiting_list.blade.php` | `GET /kasir/waiting-list` | SCREENSHOT VERIFIED (390) — kartu, Terima Transaksi 44px |
| `resources/views/kasir/kelola_kas.blade.php` | `GET /kasir/kelola-kas` | SCREENSHOT VERIFIED (390) |
| `resources/views/kasir/open_order.blade.php` | (tidak terhubung route mana pun) | VIEW ORPHAN — tidak ada bukti runtime |
| `resources/views/pembelian-bahan/index.blade.php` | `GET /pembelian-bahan` | SCREENSHOT VERIFIED (390) |
| `resources/views/pembelian-bahan/create.blade.php` | `GET /pembelian-bahan/create` | SCREENSHOT VERIFIED (390) |
| `resources/views/kedatangan-bahan/index.blade.php` | `GET /kedatangan-bahan` | SCREENSHOT VERIFIED (390) |
| `resources/views/kedatangan-bahan/create.blade.php` | `GET /kedatangan-bahan/create` | SCREENSHOT VERIFIED (390) |
| `resources/views/kartu_stok/index.blade.php` | `GET /kartu-stok` | SCREENSHOT VERIFIED (390) |
| `resources/views/kartu_stok/detail.blade.php` | `GET /kartu-stok/{id}` | SCREENSHOT VERIFIED (390) |
| `resources/views/master/bahan/index.blade.php` | `GET /bahan-baku` | SCREENSHOT VERIFIED (390) + modal Registrasi |
| `resources/views/kas-kecil/index.blade.php` | `GET /kas-kecil` | SCREENSHOT VERIFIED (390) |
| `resources/views/laporan/stok-bahan.blade.php` | `GET /laporan/stok-bahan` | SCREENSHOT VERIFIED (390) |
| `resources/views/users/index.blade.php` | `GET /users` | SCREENSHOT VERIFIED (390) |
| `resources/views/dashboard/*` | `GET /dashboard` | SCREENSHOT VERIFIED (320/390/1366) |

**Kesimpulan:** 14 layar aktif terverifikasi visual nyata. Sisanya (master lain, keuangan detail, laporan lain, dashboard per role) tetap GENERIC ONLY / UI TODO dan di luar scope kelompok visual ini; tidak ada regresi yang ditemukan pada CSS terkait.
