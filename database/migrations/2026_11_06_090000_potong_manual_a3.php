<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Potong Manual untuk mesin KONICA MINOLTA (kategori A3).
 *
 * 1) MASTER FINISHING BARU "Potong Manual A3"
 *
 *    Potong Manual yang sudah ada (id 15) terikat ke kategori INDOOR dan
 *    mesin EPSON S80670, jadi tidak pernah muncul untuk produk A3 - padahal
 *    produk A3 (AC, HVS, foto, undone) justru yang paling sering dipotong
 *    manual (mis. potong A3 jadi ukuran 10R). Baris baru ini memakai
 *    metode_hitung yang sama (LINEAR_GRID) dan tarif per meter yang sama,
 *    jadi rumus biayanya identik - tidak ada aturan hitung baru.
 *
 *    Idempotent: kalau baris dengan nama sama sudah ada, migration ini
 *    tidak menambah duplikat.
 *
 * 2) KOLOM UKURAN MEDIA (lebar_media / tinggi_media)
 *
 *    Rumus LINEAR_GRID butuh ukuran media yang dipotong. Untuk produk
 *    ber-metode_hitung 'ukuran' (roll/banner) ukuran itu sudah ada di
 *    transaksi_detail.p_ukuran/l_ukuran. Untuk produk yang dijual per
 *    lembar (kategori A3) form TIDAK punya Panjang/Lebar sama sekali,
 *    jadi operator mengisinya sendiri di panel Potong Manual.
 *
 *    Tanpa kolom ini, sisi "media" hilang untuk produk A3 - masalah yang
 *    sama seperti P0 "dimensi potong tidak pernah tersimpan" (lihat
 *    migration 2026_11_02_090000_simpan_dimensi_potong.php), tapi untuk
 *    sisi yang lain. Nullable, bukan default 0: finishing non-LINEAR_GRID
 *    memang tidak punya ukuran media, dan NULL lebih jujur daripada 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_detail_finishing', function (Blueprint $t) {
            if (!Schema::hasColumn('transaksi_detail_finishing', 'lebar_media')) {
                $t->decimal('lebar_media', 10, 2)->nullable()->after('tinggi_potong');
            }

            if (!Schema::hasColumn('transaksi_detail_finishing', 'tinggi_media')) {
                $t->decimal('tinggi_media', 10, 2)->nullable()->after('lebar_media');
            }
        });

        if (DB::table('finishing')->where('nama_finishing', 'Potong Manual A3')->exists()) {
            return;
        }

        // Pastikan kolom minimum_charge sudah ada (ditambah oleh migration
        // 2026_11_06_200000_minimum_charge_finishing yang berjalan setelah ini).
        // Jika belum ada, tambahkan dulu agar insert tidak gagal.
        if (!Schema::hasColumn('finishing', 'minimum_charge')) {
            Schema::table('finishing', function (Blueprint $t) {
                $t->decimal('minimum_charge', 15, 2)->default(0)->after('harga_default');
            });
        }

        // Kategori A3 + mesin KONICA MINOLTA. Di database kosong (mis. test
        // yang migrate:fresh) master kategori/mesin belum ada, jadi id-nya
        // NULL - artinya finishing ini berlaku untuk semua kategori, sama
        // seperti finishing lama yang id_kategorinya kosong.
        $idKategoriA3 = DB::table('kategori')->where('nama_kategori', 'A3')->value('id_kategori');
        $idMesinKonica = DB::table('mesin')->where('nama_mesin', 'KONICA MINOLTA')->value('id_mesin');

        DB::table('finishing')->insert([
            'nama_finishing' => 'Potong Manual A3',
            'harga_default' => 450.00,
            'minimum_charge' => 2000.00,
            'id_kategori' => $idKategoriA3,
            'id_mesin' => $idMesinKonica,
            'metode_hitung' => 'LINEAR_GRID',
            'keterangan' => 'Potong manual untuk mesin KONICA MINOLTA (A3). Biaya = meter garis potong x tarif per meter, minimum charge Rp 2.000. Wajib memiliki tier harga sebelum bisa digunakan.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('finishing')->where('nama_finishing', 'Potong Manual A3')->delete();

        Schema::table('transaksi_detail_finishing', function (Blueprint $t) {
            if (Schema::hasColumn('transaksi_detail_finishing', 'tinggi_media')) {
                $t->dropColumn('tinggi_media');
            }

            if (Schema::hasColumn('transaksi_detail_finishing', 'lebar_media')) {
                $t->dropColumn('lebar_media');
            }
        });
    }
};
