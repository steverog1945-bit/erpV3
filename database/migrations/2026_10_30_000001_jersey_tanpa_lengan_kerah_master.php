<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit kelengkapan Order Jersey: Lengan & Kerah TIDAK lagi boleh dibiarkan
 * kosong. Barang yang memang tidak berlengan/tidak berkerah (mis. celana,
 * dalaman) WAJIB dipilih eksplisit "Tanpa Lengan" / "Tanpa Kerah" dari
 * master - tercatat di database sebagai pilihan, bukan NULL tanpa aksi.
 *
 * Dua master baru ini dibuat sebagai DATA (mengikuti pola seed reference
 * master 2026_09_24_190000), bukan struktur baru: surcharge 0 rupiah dan
 * sort_order paling akhir supaya muncul di bawah daftar dropdown. Estimasi
 * bahan otomatis aman: tabel jersey_size_sleeve_usage /
 * jersey_size_collar_usage untuk tipe baru ini memang tidak ada angkanya,
 * jadi hitungEstimasiDuaDimensi() mengabaikannya (tanpa pemakaian = tanpa
 * tambahan bahan), dan surcharge 0 tidak mengubah harga.
 *
 * Idempoten ala seed reference master: hanya diisi kalau tabel masih kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jersey_sleeve_types') && DB::table('jersey_sleeve_types')->count() === 0) {
            DB::table('jersey_sleeve_types')->insert([
                ['name' => 'Pendek', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Panjang', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Tanpa Lengan', 'sort_order' => 99, 'is_active' => true, 'price_surcharge' => 0, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        if (Schema::hasTable('jersey_sleeve_types')) {
            $adaTanpaLengan = DB::table('jersey_sleeve_types')->where('name', 'Tanpa Lengan')->exists();
            if (!$adaTanpaLengan) {
                $sortMaks = (int) (DB::table('jersey_sleeve_types')->max('sort_order') ?? 0);
                DB::table('jersey_sleeve_types')->insert([
                    ['name' => 'Tanpa Lengan', 'sort_order' => $sortMaks + 1, 'is_active' => true, 'price_surcharge' => 0, 'created_at' => now(), 'updated_at' => now()],
                ]);
            }
        }

        if (Schema::hasTable('jersey_collar_types')) {
            $adaTanpaKerah = DB::table('jersey_collar_types')->where('name', 'Tanpa Kerah')->exists();
            if (!$adaTanpaKerah) {
                $sortMaks = (int) (DB::table('jersey_collar_types')->max('sort_order') ?? 0);
                DB::table('jersey_collar_types')->insert([
                    ['name' => 'Tanpa Kerah', 'sort_order' => $sortMaks + 1, 'is_active' => true, 'price_surcharge' => 0, 'created_at' => now(), 'updated_at' => now()],
                ]);
            }
        }
    }

    public function down(): void
    {
        // Master "Tanpa ..." hanya dihapus kalau tidak pernah dipakai order -
        // kalau sudah dipakai, baris histori tetap merujuk nama snapshot di
        // kolom teks, tapi FK-nya tidak boleh dibiarkan yatim.
        $sleeveId = DB::table('jersey_sleeve_types')->where('name', 'Tanpa Lengan')->value('id');
        if ($sleeveId && !DB::table('jersey_order_items')->where('jersey_sleeve_type_id', $sleeveId)->exists()
            && !DB::table('jersey_order_lines')->where('jersey_sleeve_type_id', $sleeveId)->exists()) {
            DB::table('jersey_sleeve_types')->where('id', $sleeveId)->delete();
        }

        $collarId = DB::table('jersey_collar_types')->where('name', 'Tanpa Kerah')->value('id');
        if ($collarId && !DB::table('jersey_order_items')->where('jersey_collar_type_id', $collarId)->exists()
            && !DB::table('jersey_order_lines')->where('jersey_collar_type_id', $collarId)->exists()) {
            DB::table('jersey_collar_types')->where('id', $collarId)->delete();
        }
    }
};
