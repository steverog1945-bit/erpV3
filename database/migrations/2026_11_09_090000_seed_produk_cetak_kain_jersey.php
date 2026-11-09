<?php

use App\Services\AuditLogger;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Produk jasa "Cetak Kain Jersey" untuk menu Penjualan: cetakan kain sublim
 * (bukan jahitan jadi) yang dijual per luas (metode 'ukuran' → m²), dengan
 * BOM dua bahan yang SUDAH terdaftar di Master Bahan Baku:
 *   1. SUBLIME PAPER        (kode SB)             → luas cetak + 10% margin potong
 *   2. KAIN BRAZIL KT 190-200 (kode KAIN-BRAZIL-KT190, stok kg, ±190 gsm)
 *                                                   → luas m² × 0,19 kg
 * Bila salah satu bahan belum terdaftar, ia didaftarkan dengan STOK 0
 * (tanpa kartu stok / tanpa jurnal - saldo awal tidak kejamang dari
 * migrasi, ikut dependamen operator tetap) supaya BOM tidak kosong.
 *
 * Tier harga SENGAJA TIDAK diisi (keputusan owner): checkout akan menolak
 * dengan pesan "harga belum diatur" sampai admin mengisinya di Price List -
 * aman salah harga jual secara diam-diam.
 *
 * Idempoten: setiap tahap hanya insert bila kunci/nama belum ada, aman
 * dieksekusi ulang di kedua lingkungan (V2 & produksi).
 */
return new class extends Migration
{
    public function up(): void
    {
        $idSatuanRoll = (int) DB::table('satuan')->where('nama_satuan', 'roll')->value('id_satuan');
        $idSatuanM2 = (int) DB::table('satuan')->where('nama_satuan', 'm2')->value('id_satuan');
        $idSatuanKg = (int) DB::table('satuan')->where('nama_satuan', 'kg')->value('id_satuan');
        $idMesin = (int) DB::table('mesin')->where('nama_mesin', 'LAINNYA')->value('id_mesin')
            ?: (int) DB::table('mesin')->value('id_mesin');
        $idKategori = (int) DB::table('kategori')->where('nama_kategori', 'Lain-lain')->value('id_kategori')
            ?: (int) DB::table('kategori')->value('id_kategori');

        $daftarBahan = [
            [
                'kode_bahan' => 'SB',
                'nama_bahan' => 'SUBLIME PAPER',
                'satuan_stok' => 'roll',
                'id_satuan_stok' => $idSatuanRoll,
                'id_satuan_hitung' => $idSatuanM2,
                'nilai_konversi' => 160.0,
                'lebar_roll' => 1.6,
                'panjang_roll' => 100.0,
                'harga_rata_rata' => 0,
            ],
            [
                'kode_bahan' => 'KAIN-BRAZIL-KT190',
                'nama_bahan' => 'KAIN BRAZIL SET KT 190-200',
                'satuan_stok' => 'kg',
                'id_satuan_stok' => $idSatuanKg,
                'id_satuan_hitung' => $idSatuanKg,
                'nilai_konversi' => 1,
                'lebar_roll' => null,
                'panjang_roll' => null,
                'harga_rata_rata' => 0,
            ],
        ];

        foreach ($daftarBahan as $b) {
            $ada = DB::table('bahan_baku')->where('kode_bahan', $b['kode_bahan'])->first();
            if ($ada) {
                continue;
            }
            DB::table('bahan_baku')->insert([
                'kode_bahan' => $b['kode_bahan'],
                'nama_bahan' => $b['nama_bahan'],
                'id_mesin' => $idMesin,
                'id_kategori' => $idKategori,
                'id_satuan_stok' => $b['id_satuan_stok'],
                'id_satuan_hitung' => $b['id_satuan_hitung'],
                'nilai_konversi' => $b['nilai_konversi'],
                'lebar_roll' => $b['lebar_roll'],
                'panjang_roll' => $b['panjang_roll'],
                'satuan_stok' => $b['satuan_stok'],
                'stok' => 0,
                'stok_minimal' => 0,
                'harga_rata_rata' => $b['harga_rata_rata'],
                'status' => 'aktif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $idProduk = DB::table('produk')->where('nama_produk', 'Cetak Kain Jersey')->value('id_produk');
        if (!$idProduk) {
            $idProduk = DB::table('produk')->insertGetId([
                'nama_produk' => 'Cetak Kain Jersey',
                'metode_hitung' => 'ukuran',
                'min_order' => 1.0,
                'min_luas_total' => 1.0,
                'status' => 'aktif',
                'keterangan' => 'Jasa cetak kain jersey sublim; BOM: sublime paper + kain Brazil KT 190-200. Harga diatur di Price List.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            AuditLogger::log('created', 'produk', $idProduk, null, ['nama' => 'Cetak Kain Jersey'], "Produk Cetak Kain Jersey didaftarkan (id {$idProduk}) lewat migrasi, BOM sublime paper + kain Brazil.");
        }

        $idKertas = DB::table('bahan_baku')->where('kode_bahan', 'SB')->value('id_bahan');
        $idKain = DB::table('bahan_baku')->where('kode_bahan', 'KAIN-BRAZIL-KT190')->value('id_bahan');

        $bom = [
            [$idKertas, '(P*L)/10000*1.1'],
            [$idKain, '(P*L)/10000*0.19'],
        ];
        foreach ($bom as [$idBahan, $rumus]) {
            if (!$idBahan) {
                continue;
            }
            $ada = DB::table('bom_detail')->where('id_produk', $idProduk)->where('id_bahan', $idBahan)->first();
            if ($ada) {
                continue;
            }
            DB::table('bom_detail')->insert([
                'id_produk' => $idProduk,
                'id_bahan' => $idBahan,
                'cara_hitung_pemakaian' => $rumus,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $idProduk = DB::table('produk')->where('nama_produk', 'Cetak Kain Jersey')->value('id_produk');
        if ($idProduk) {
            DB::table('bom_detail')->where('id_produk', $idProduk)->delete();
            DB::table('produk')->where('id_produk', $idProduk)->delete();
        }
    }
};
