<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD per-baris untuk tier harga Jasa (jasa_harga) - pola identik dengan
 * FinishingHargaController (satu baris satu aksi, bukan bulk replace,
 * SELALU JSON supaya dipanggil lewat fetch() tanpa reload halaman).
 */
class JasaHargaController extends Controller
{
    public function store(Request $request, $idJasa)
    {
        // numeric (bukan integer) - konsisten dengan Price List produk &
        // Finishing (lihat migrasi 2026_09_11_240000), walau qty jasa
        // biasanya bilangan bulat, disamakan supaya satu aturan berlaku di
        // ketiga tabel harga bertingkat ini.
        $request->validate([
            'range_min' => 'required|numeric|min:0.01',
            'range_max' => 'nullable|numeric|gte:range_min',
            'harga'     => 'required|numeric|min:0',
        ]);

        // Aturan bisnis: satu jasa hanya boleh punya satu tier untuk satu rentang
        // qty. Tanpa ini, klik ganda pada form tier (yang dikirim lewat fetch()
        // JSON, jadi tidak pernah membawa submit_id dan tidak terlindungi
        // SubmitSekaliJalan) membuat tier kembar - lalu qty 1-10 punya dua harga
        // dan mana yang dipakai bergantung urutan baris.
        // Bentuk respons dibuat sama persis dengan SettingHargaController
        // supaya helper JS di ketiga halaman bisa sama.
        if (\App\Support\CekRentangHarga::tumpangTindih(
            'jasa_harga', 'id_jasa', (int) $idJasa, [], $request->range_min, $request->range_max
        )) {
            return response()->json(['success' => false, 'message' => 'Rentang qty ini tumpang tindih dengan tier yang sudah ada. Sesuaikan dulu Min/Max-nya.'], 422);
        }

        $id = DB::table('jasa_harga')->insertGetId([
            'id_jasa'    => $idJasa,
            'range_min'  => $request->range_min,
            'range_max'  => $request->range_max ?: null,
            'harga'      => $request->harga,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('jasa_harga')->where('id_jasa_harga', $id)->first();

        // Hardening P0-3: lihat catatan yang sama di ProdukController/SettingHargaController.
        AuditLogger::log('created', 'jasa_harga', (int) $id, null, (array) $row, 'Tier harga jasa baru untuk jasa #' . $idJasa);

        return response()->json(['success' => true, 'message' => 'Tier harga ditambahkan.', 'row' => $row]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'range_min' => 'required|numeric|min:0.01',
            'range_max' => 'nullable|numeric|gte:range_min',
            'harga'     => 'required|numeric|min:0',
        ]);

        $lama = DB::table('jasa_harga')->where('id_jasa_harga', $id)->first();

        DB::table('jasa_harga')->where('id_jasa_harga', $id)->update([
            'range_min'  => $request->range_min,
            'range_max'  => $request->range_max ?: null,
            'harga'      => $request->harga,
            'updated_at' => now(),
        ]);

        if ($lama) {
            AuditLogger::log('updated', 'jasa_harga', (int) $id,
                ['range_min' => $lama->range_min, 'range_max' => $lama->range_max, 'harga' => $lama->harga],
                ['range_min' => $request->range_min, 'range_max' => $request->range_max ?: null, 'harga' => $request->harga],
                'Tier harga jasa diperbarui');
        }

        return response()->json(['success' => true, 'message' => 'Tier harga diperbarui.']);
    }

    public function destroy($id)
    {
        $row = DB::table('jasa_harga')->where('id_jasa_harga', $id)->first();
        DB::table('jasa_harga')->where('id_jasa_harga', $id)->delete();

        if ($row) {
            AuditLogger::log('deleted', 'jasa_harga', (int) $id, (array) $row, null, 'Tier harga jasa dihapus');
        }

        return response()->json(['success' => true, 'message' => 'Tier harga dihapus.']);
    }
}
