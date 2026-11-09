<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * FIXTURE SINTETIS KHUSUS STAGING VISUAL (bukan data produksi).
 * Dipakai hanya untuk screenshot PWA Mobile di database terisolasi
 * orion_pwa_staging_pr21. Jangan pernah dijalankan di produksi.
 * Jalankan: php artisan db:seed --force --class=StagingFixtureSeeder
 */
class StagingFixtureSeeder extends Seeder
{
    public function run(): void
    {
        // Hard fail closed: this fixture mutates database tables and role permissions.
        // Running it on any database other than the dedicated disposable PR21
        // staging database is forbidden, even when --force was used.
        $database = DB::connection()->getDatabaseName();
        if (app()->environment('production') || $database !== 'orion_pwa_staging_pr21') {
            throw new \RuntimeException(
                'StagingFixtureSeeder may only run on isolated orion_pwa_staging_pr21; current database: '
                . ($database ?: '[unknown]')
            );
        }

        // ---------- Karyawan ----------
        if (DB::table('karyawan')->count() === 0) {
            DB::table('karyawan')->insert([
                ['id_karyawan' => 1, 'nama_karyawan' => 'Admin Utama', 'bagian' => 'Admin', 'telepon' => '081234567890', 'status_aktif' => 1],
                ['id_karyawan' => 2, 'nama_karyawan' => 'Ratna Kasir', 'bagian' => 'Kasir', 'telepon' => '081234567891', 'status_aktif' => 1],
                ['id_karyawan' => 3, 'nama_karyawan' => 'Budi Gudang', 'bagian' => 'Gudang', 'telepon' => '081234567892', 'status_aktif' => 1],
                ['id_karyawan' => 4, 'nama_karyawan' => 'Sinta Desain', 'bagian' => 'Desain', 'telepon' => '081234567893', 'status_aktif' => 1],
            ]);
        }

        // ---------- Users ----------
        if (DB::table('users')->count() === 0) {
            DB::table('users')->insert([
                ['id_user' => 1, 'id_karyawan' => 1, 'username' => 'admin', 'password' => Hash::make('admin123'), 'id_role' => 1],
                ['id_user' => 2, 'id_karyawan' => 2, 'username' => 'kasir', 'password' => Hash::make('kasir123'), 'id_role' => 3],
                ['id_user' => 3, 'id_karyawan' => 3, 'username' => 'gudang', 'password' => Hash::make('gudang123'), 'id_role' => 3],
                ['id_user' => 4, 'id_karyawan' => 4, 'username' => 'desain', 'password' => Hash::make('desain123'), 'id_role' => 2],
            ]);
        }

        // ---------- Kategori & Satuan (master dasar) ----------
        if (DB::table('kategori')->count() === 0) {
            DB::table('kategori')->insert([
                ['id_kategori' => 1, 'nama_kategori' => 'Sticker'],
                ['id_kategori' => 2, 'nama_kategori' => 'Banner/Flexi'],
                ['id_kategori' => 3, 'nama_kategori' => 'Kain/Jersey'],
            ]);
        }
        if (DB::table('satuan')->count() === 0) {
            DB::table('satuan')->insert([
                ['id_satuan' => 1, 'nama_satuan' => 'cm', 'nilai_konversi' => 1],
                ['id_satuan' => 2, 'nama_satuan' => 'm', 'nilai_konversi' => 100],
                ['id_satuan' => 3, 'nama_satuan' => 'rim', 'nilai_konversi' => 1],
            ]);
        }

        // ---------- Fix role sintetis: Administrator baru + role staf dari migrasi ----------
        if (DB::table('roles')->where('id_role', 4)->doesntExist()) {
            DB::table('roles')->insert(['id_role' => 4, 'nama_role' => 'Administrator', 'boleh_lihat_harga' => 1, 'boleh_input_biaya_desain' => 1]);
        }
        DB::table('users')->where('id_user', 1)->update(['id_role' => 4]);
        DB::table('users')->whereIn('id_user', [2, 3])->update(['id_role' => 1]);
        // Role operasi (id 1) diberi akses penuh semua menu untuk staging visual.
        DB::table('role_permissions')->where('id_role', 1)->delete();
        DB::table('role_permissions')->insert(array_map(fn ($m) => [
            'id_role' => 1, 'id_menu' => $m, 'can_view' => 1, 'can_add' => 1, 'can_edit' => 1, 'can_delete' => 1,
        ], (array) (DB::table('menus')->pluck('id_menu')->all())));

        // ---------- Mesin ----------
        if (DB::table('mesin')->count() === 0) {
            DB::table('mesin')->insert([
                ['id_mesin' => 1, 'nama_mesin' => 'Mutoh ValueJet', 'tipe_mesin' => 'Inkjet Roll', 'tampil_divisi' => 1],
                ['id_mesin' => 2, 'nama_mesin' => 'Epson SureColor', 'tipe_mesin' => 'Inkjet A3+', 'tampil_divisi' => 1],
                ['id_mesin' => 3, 'nama_mesin' => 'Mesin Jahit AC-1', 'tipe_mesin' => 'Jahit', 'tampil_divisi' => 1],
            ]);
        }

        // ---------- Master pendukung Bahan ----------
        if (DB::table('jenis_bahan')->count() === 0) {
            DB::table('jenis_bahan')->insert([
                ['id_jenis_bahan' => 1, 'nama_jenis' => 'Indoor PVC', 'keterangan' => null],
                ['id_jenis_bahan' => 2, 'nama_jenis' => 'Vinyl Sticker', 'keterangan' => null],
                ['id_jenis_bahan' => 3, 'nama_jenis' => 'Kain Polyester', 'keterangan' => null],
            ]);
        }
        if (DB::table('merk')->count() === 0) {
            DB::table('merk')->insert([
                ['id_merk' => 1, 'nama_merk' => 'Orion Flex China', 'keterangan' => null],
                ['id_merk' => 2, 'nama_merk' => 'Generic Import', 'keterangan' => null],
            ]);
        }

        // ---------- Bahan Baku ----------
        if (DB::table('bahan_baku')->count() === 0) {
            DB::table('bahan_baku')->insert([
                ['id_bahan' => 1, 'kode_bahan' => 'BB-FLEX-001', 'nama_bahan' => 'Flexible Indoor PVC 280 gsm Lebar 1.8m', 'id_mesin' => 1, 'id_kategori' => 2, 'id_merk' => 1, 'id_jenis_bahan' => 1, 'stok' => 48.5, 'stok_minimal' => 10, 'satuan_stok' => 'm', 'id_satuan_stok' => 2, 'id_satuan_hitung' => 2, 'nilai_konversi' => 100, 'lebar_roll' => 1.8, 'panjang_roll' => 50, 'harga_rata_rata' => 45000, 'status' => 1],
                ['id_bahan' => 2, 'kode_bahan' => 'BB-STR-002', 'nama_bahan' => 'Clear Sticker A3 20 Lembar', 'id_mesin' => 2, 'id_kategori' => 1, 'id_merk' => 2, 'id_jenis_bahan' => 2, 'stok' => 6, 'stok_minimal' => 10, 'satuan_stok' => 'rim', 'id_satuan_stok' => 2, 'id_satuan_hitung' => 2, 'nilai_konversi' => 1, 'lebar_roll' => null, 'panjang_roll' => null, 'harga_rata_rata' => 78000, 'status' => 1],
                ['id_bahan' => 3, 'kode_bahan' => 'BB-KAIN-003', 'nama_bahan' => 'Kain Jersey polyester Bird Eye 120 gsm', 'id_mesin' => 3, 'id_kategori' => 1, 'id_merk' => 2, 'id_jenis_bahan' => 3, 'stok' => 120, 'stok_minimal' => 20, 'satuan_stok' => 'm', 'id_satuan_stok' => 2, 'id_satuan_hitung' => 2, 'nilai_konversi' => 100, 'lebar_roll' => 1.5, 'panjang_roll' => 40, 'harga_rata_rata' => 28000, 'status' => 1],
                ['id_bahan' => 4, 'kode_bahan' => 'BB-FLEX-002', 'nama_bahan' => 'Softすることが Vinly Glossy 1.2m with very long material name to test ellipsis rendering', 'id_mesin' => 1, 'id_kategori' => 2, 'id_merk' => 1, 'id_jenis_bahan' => 1, 'stok' => 12.75, 'stok_minimal' => 5, 'satuan_stok' => 'm', 'id_satuan_stok' => 2, 'id_satuan_hitung' => 2, 'nilai_konversi' => 100, 'lebar_roll' => 1.2, 'panjang_roll' => 30, 'harga_rata_rata' => 52000, 'status' => 1],
            ]);
        }

        // ---------- Produk ----------
        if (DB::table('produk')->count() === 0) {
            DB::table('produk')->insert([
                ['id_produk' => 1, 'nama_produk' => 'Banner Standee 60x160', 'id_mesin' => 1, 'metode_hitung' => 'ukuran', 'min_order' => 1, 'keterangan' => null, 'status' => 1, 'barcode' => null, 'min_panjang' => 0, 'min_lebar' => 0, 'spk_lengkap' => 1],
                ['id_produk' => 2, 'nama_produk' => 'Cetak Stiker A3 Page', 'id_mesin' => 2, 'metode_hitung' => 'quantity', 'min_order' => 1, 'keterangan' => null, 'status' => 1, 'barcode' => null, 'min_panjang' => 0, 'min_lebar' => 0, 'spk_lengkap' => 1],
                ['id_produk' => 3, 'nama_produk' => 'Jersey Full Print Custom', 'id_mesin' => 3, 'metode_hitung' => 'quantity', 'min_order' => 1, 'keterangan' => null, 'status' => 1, 'barcode' => null, 'min_panjang' => 0, 'min_lebar' => 0, 'spk_lengkap' => 1],
            ]);
        }

        // ---------- Konsumen ----------
        if (DB::table('golongan_konsumen')->count() === 0) {
            DB::table('golongan_konsumen')->insert([
                ['id_golongan' => 1, 'nama_golongan' => 'Umum', 'keterangan' => null],
                ['id_golongan' => 2, 'nama_golongan' => 'Reseller', 'keterangan' => null],
            ]);
        }
        if (DB::table('konsumen')->count() === 0) {
            DB::table('konsumen')->insert([
                ['id_konsumen' => 1, 'nama_konsumen' => 'Toko Sinar Jaya', 'telepon' => '081200000001', 'alamat' => 'Jl. Merdeka No. 10', 'id_golongan' => 2, 'tipe_konsumen' => 'permanent', 'tgl_registrasi' => now()],
                ['id_konsumen' => 2, 'nama_konsumen' => 'Yayasan Cendekia Harapan Solidaritas Panjang', 'telepon' => '081200000002', 'alamat' => 'Jl. Pendidikan Raya No. 88 Blok C2 Kelurahan Cempaka Putih', 'id_golongan' => 1, 'tipe_konsumen' => 'permanent', 'tgl_registrasi' => now()],
                ['id_konsumen' => 3, 'nama_konsumen' => 'Andi Pratama Wijaya Kusuma Hadiwangsa', 'telepon' => '081200000003', 'alamat' => 'Perum Griya Asri Blok D', 'id_golongan' => 1, 'tipe_konsumen' => 'temporary', 'tgl_registrasi' => now()],
                ['id_konsumen' => 4, 'nama_konsumen' => 'CV Digital Print Nusantara Perkasa Abadi', 'telepon' => '081200000004', 'alamat' => 'Jl. Industri Raya Kav. 15-17', 'id_golongan' => 2, 'tipe_konsumen' => 'permanent', 'tgl_registrasi' => now()],
            ]);
        }

        // ---------- Supplier ----------
        if (DB::table('supplier')->count() === 0) {
            DB::table('supplier')->insert([
                ['id_supplier' => 1, 'nama_supplier' => 'PT Bahan Cetak Mandiri Jaya Sejahtera Perusahaan Distributor Terlengkap', 'alamat' => 'Jl. Cideng Timur IV No. 12 Jakarta Pusat', 'kontak' => '081300000001'],
                ['id_supplier' => 2, 'nama_supplier' => 'CV Sumber Tekstil Nusantara', 'alamat' => 'Jl. Achmad Yani No. 30, Bandung', 'kontak' => '081300000002'],
            ]);
        }

        // ---------- Sesi Kas Open ----------
        if (DB::table('kas_harian')->where('status_lock', 'open')->count() === 0) {
            DB::table('kas_harian')->insert([
                'tgl_kas' => now()->startOfDay(),
                'id_user' => 2,
                'saldo_awal' => 500000,
                'total_tunai_masuk' => 0,
                'total_transfer_masuk' => 0,
                'total_debit_masuk' => 0,
                'total_tunai_keluar' => 0,
                'saldo_akhir' => 500000,
                'saldo_fisik' => 0,
                'selisih' => 0,
                'total_transfer' => 0,
                'total_diskon' => 0,
                'status_lock' => 'open',
                'waktu_tutup' => null,
                'keterangan_closing' => null,
                'keterangan_pembukaan' => 'Sesi kasir pagi (fixture staging)',
                'status_rolling' => 'Rolling',
                'created_at' => now()->subHours(3),
            ]);
        }

        // ---------- Transaksi (kasir) ----------
        if (DB::table('transaksi')->count() === 0) {
            $rows = [
                // Antrean / waiting list
                ['nomor_nota' => 'N2610-0001', 'tipe_order' => 'Reguler', 'id_konsumen' => 2, 'id_user_kasir' => 2, 'total_bayar' => 385000, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Menunggu Kasir', 'metode_pembayaran' => 'Tunai'],
                ['nomor_nota' => 'N2610-0002', 'tipe_order' => 'Reguler', 'id_konsumen' => 1, 'id_user_kasir' => 2, 'total_bayar' => 1240000, 'status_pembayaran' => 'Belum Bayar', 'status_nota' => 'Aktif', 'status_order' => 'Menunggu Kasir', 'metode_pembayaran' => 'Transfer'],
                // Open orders di monitoring
                ['nomor_nota' => 'N2610-0003', 'tipe_order' => 'Reguler', 'id_konsumen' => 3, 'id_user_kasir' => 2, 'total_bayar' => 96200, 'status_pembayaran' => 'Lunas', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir', 'metode_pembayaran' => 'Tunai'],
                ['nomor_nota' => 'N2610-0004', 'tipe_order' => 'Reguler', 'id_konsumen' => 4, 'id_user_kasir' => 2, 'total_bayar' => 2750000, 'status_pembayaran' => 'DP', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir', 'metode_pembayaran' => 'Transfer'],
                ['nomor_nota' => 'N2610-0005', 'tipe_order' => 'Reguler', 'id_konsumen' => 1, 'id_user_kasir' => 2, 'total_bayar' => 640000, 'status_pembayaran' => 'Lunas', 'status_nota' => 'Aktif', 'status_order' => 'Diterima Kasir', 'metode_pembayaran' => 'Debit'],
            ];
            foreach ($rows as $i => $r) {
                DB::table('transaksi')->insert($r + [
                    'tgl_order' => now()->subMinutes(35 - $i * 9),
                    'diskon' => 0,
                    'alasan_batal_nota' => null,
                    'tgl_dibatalkan_nota' => null,
                    'id_user_pembatal_nota' => null,
                    'status_collecting' => 'Belum Diambil',
                    'tgl_collecting' => null,
                    'id_user_collecting' => null,
                    'nama_konsumen_manual' => null,
                ]);
            }
            // Detail nota
            $details = [
                ['id_transaksi' => 1, 'id_produk' => 1, 'p_ukuran' => '60', 'l_ukuran' => '40', 'qty' => 2, 'subtotal' => 240000, 'biaya_desain' => 145000, 'biaya_cetak' => 240000, 'status_spk' => 'Antre'],
                ['id_transaksi' => 2, 'id_produk' => 2, 'p_ukuran' => '297', 'l_ukuran' => '210', 'qty' => 40, 'subtotal' => 1240000, 'biaya_desain' => 0, 'biaya_cetak' => 1240000, 'status_spk' => 'Antre'],
                ['id_transaksi' => 3, 'id_produk' => 2, 'p_ukuran' => '297', 'l_ukuran' => '210', 'qty' => 3, 'subtotal' => 96200, 'biaya_desain' => 0, 'biaya_cetak' => 96200, 'status_spk' => 'Cetak'],
                ['id_transaksi' => 4, 'id_produk' => 3, 'p_ukuran' => 0, 'l_ukuran' => 0, 'qty' => 25, 'subtotal' => 2750000, 'biaya_desain' => 250000, 'biaya_cetak' => 2500000, 'status_spk' => 'Cetak'],
                ['id_transaksi' => 5, 'id_produk' => 1, 'p_ukuran' => '60', 'l_ukuran' => '160', 'qty' => 4, 'subtotal' => 640000, 'biaya_desain' => 0, 'biaya_cetak' => 640000, 'status_spk' => 'Cetak'],
            ];
            foreach ($details as $i => $d) {
                DB::table('transaksi_detail')->insert($d + [
                    'id_jasa' => null, 'id_bahan' => null, 'id_finishing' => null, 'biaya_finishing' => 0,
                    'file_siap_cetak' => 0, 'id_desainer' => null, 'komisi_desain_persen' => 0, 'komisi_desain' => 0,
                    'komisi_status' => 'belum', 'komisi_dibayar_at' => null, 'id_kas_kecil_komisi' => null,
                    'nama_file' => 'artwork-fixture-'.($i + 1).'.ai', 'thumbnail' => 0,
                    'status_urgent' => 0, 'status_urgensi' => 'Normal', 'status_finishing' => 'Standar', 'qty_cetak' => 0,
                    'catatan_produksi' => '', 'potongan' => 0, 'hpp_snapshot' => 0, 'harga_jual_snapshot' => 0,
                    'is_susulan' => 0,
                ]);
            }
            // Pembayaran untuk monitoring
            DB::table('pembayaran_riwayat')->insert([
                ['id_transaksi' => 3, 'nominal' => 96200, 'metode' => 'Tunai', 'id_bank' => null, 'tgl_bayar' => now()->subMinutes(20), 'keterangan' => 'Bayar penuh', 'uang_diterima' => 100000, 'kembalian' => 3800],
                ['id_transaksi' => 4, 'nominal' => 1500000, 'metode' => 'Transfer', 'id_bank' => null, 'tgl_bayar' => now()->subMinutes(15), 'keterangan' => 'DP 50%', 'uang_diterima' => 0, 'kembalian' => 0],
                ['id_transaksi' => 5, 'nominal' => 640000, 'metode' => 'Debit', 'id_bank' => null, 'tgl_bayar' => now()->subMinutes(10), 'keterangan' => null, 'uang_diterima' => 0, 'kembalian' => 0],
            ]);
        }

        // ---------- Pembelian + Kedatangan (kedatangan dulu: FK pembelian → kedatangan) ----------
        if (DB::table('kedatangan_bahan')->count() === 0) {
            // Gudang sudah di-seed migrasi (id 1 = Gudang Utama); tidak perlu insert gudang di sini.
            DB::table('kedatangan_bahan')->insert([
                ['id_kedatangan' => 1, 'no_surat_jalan' => 'SJ-2026-0088', 'id_supplier' => 1, 'id_gudang' => 1, 'tgl_datang' => now()->subDays(2), 'status' => 'Diterima Lengkap', 'keterangan' => null, 'id_purchase_order' => null, 'id_platform' => null, 'nama_toko_online' => null, 'no_pesanan_online' => null, 'no_resi' => null],
                ['id_kedatangan' => 2, 'no_surat_jalan' => 'SJ-2026-0090', 'id_supplier' => 2, 'id_gudang' => 1, 'tgl_datang' => now()->subDay(), 'status' => 'Sebagian', 'keterangan' => 'Sisa 40m menyusul', 'id_purchase_order' => null, 'id_platform' => null, 'nama_toko_online' => null, 'no_pesanan_online' => null, 'no_resi' => null],
                ['id_kedatangan' => 3, 'no_surat_jalan' => 'SJ-2026-0094', 'id_supplier' => 1, 'id_gudang' => 1, 'tgl_datang' => now(), 'status' => 'Diterima Lengkap', 'keterangan' => null, 'id_purchase_order' => null, 'id_platform' => null, 'nama_toko_online' => null, 'no_pesanan_online' => null, 'no_resi' => null],
            ]);
            DB::table('kedatangan_bahan_detail')->insert([
                ['id_detail' => 1, 'id_kedatangan' => 1, 'id_bahan' => 1, 'qty' => 50, 'satuan' => 'm', 'status' => 'Masuk'],
                ['id_detail' => 2, 'id_kedatangan' => 1, 'id_bahan' => 2, 'qty' => 6, 'satuan' => 'rim', 'status' => 'Masuk'],
                ['id_detail' => 3, 'id_kedatangan' => 2, 'id_bahan' => 3, 'qty' => 160, 'satuan' => 'm', 'status' => 'Masuk'],
                ['id_detail' => 4, 'id_kedatangan' => 3, 'id_bahan' => 4, 'qty' => 40, 'satuan' => 'm', 'status' => 'Masuk'],
            ]);
        }

        if (DB::table('pembelian_bahan')->count() === 0) {
            DB::table('pembelian_bahan')->insert([
                ['id_kedatangan' => 1, 'id_pembelian' => 1, 'grand_total' => 2700000, 'status_pajak' => 0, 'no_faktur' => 'FTR-2026-0091', 'tgl_transaksi' => now()->subDays(2), 'tipe_pembelian' => 'Tunai', 'jatuh_tempo_hari' => 0, 'jatuh_tempo_tgl' => null, 'status_pembayaran' => 'Lunas', 'tgl_pelunasan' => now()->subDays(1), 'include_pajak' => 0, 'ongkir' => 0, 'biaya_layanan' => 0, 'voucher' => 0],
                ['id_kedatangan' => 2, 'id_pembelian' => 2, 'grand_total' => 5400000, 'status_pajak' => 0, 'no_faktur' => 'FTR-2026-0092', 'tgl_transaksi' => now()->subDay(), 'tipe_pembelian' => 'Tempo', 'jatuh_tempo_hari' => 30, 'jatuh_tempo_tgl' => now()->addDays(29), 'status_pembayaran' => 'Hutang', 'tgl_pelunasan' => null, 'include_pajak' => 0, 'ongkir' => 50000, 'biaya_layanan' => 0, 'voucher' => 0],
                ['id_kedatangan' => 3, 'id_pembelian' => 3, 'grand_total' => 1520000, 'status_pajak' => 0, 'no_faktur' => 'FTR-2026-0093', 'tgl_transaksi' => now(), 'tipe_pembelian' => 'Tempo', 'jatuh_tempo_hari' => 14, 'jatuh_tempo_tgl' => now()->addDays(14), 'status_pembayaran' => 'Hutang', 'tgl_pelunasan' => null, 'include_pajak' => 0, 'ongkir' => 0, 'biaya_layanan' => 0, 'voucher' => 0],
            ]);
            DB::table('pembelian_bahan_detail')->insert([
                ['id_detail' => 1, 'id_pembelian' => 1, 'id_bahan' => 1, 'qty' => 50, 'harga' => 45000, 'diskon' => 0, 'potongan' => 0, 'subtotal' => 2250000],
                ['id_detail' => 2, 'id_pembelian' => 1, 'id_bahan' => 2, 'qty' => 6, 'harga' => 75000, 'diskon' => 3000, 'potongan' => 0, 'subtotal' => 450000],
                ['id_detail' => 3, 'id_pembelian' => 2, 'id_bahan' => 3, 'qty' => 200, 'harga' => 27000, 'diskon' => 2000, 'potongan' => 0, 'subtotal' => 5400000],
                ['id_detail' => 4, 'id_pembelian' => 3, 'id_bahan' => 4, 'qty' => 40, 'harga' => 38000, 'diskon' => 0, 'potongan' => 0, 'subtotal' => 1520000],
            ]);
        }

        // ---------- Kartu Stok ----------
        if (DB::table('kartu_stok')->count() === 0) {
            DB::table('kartu_stok')->insert([
                ['id_bahan' => 1, 'tanggal' => now()->subDays(2), 'tipe' => 'Masuk', 'qty' => 50, 'stok_sebelum' => 0, 'stok_sesudah' => 50, 'referensi' => 'PUR-1/SJ-2026-0088', 'keterangan' => 'Kedatangan bahan fixture', 'created_by' => 1],
                ['id_bahan' => 1, 'tanggal' => now()->subDay(), 'tipe' => 'Keluar', 'qty' => 1.5, 'stok_sebelum' => 50, 'stok_sesudah' => 48.5, 'referensi' => 'N2610-0003', 'keterangan' => 'Pemakaian nota fixture', 'created_by' => 1],
                ['id_bahan' => 3, 'tanggal' => now()->subDay(), 'tipe' => 'Masuk', 'qty' => 160, 'stok_sebelum' => 0, 'stok_sesudah' => 160, 'referensi' => 'PUR-2/SJ-2026-0090', 'keterangan' => 'Kedatangan sebagian', 'created_by' => 1],
                ['id_bahan' => 2, 'tanggal' => now(), 'tipe' => 'Keluar', 'qty' => 6, 'stok_sebelum' => 12, 'stok_sesudah' => 6, 'referensi' => 'N2610-0005', 'keterangan' => 'Pemakaian nota fixture', 'created_by' => 1],
            ]);
        }
    }
}
