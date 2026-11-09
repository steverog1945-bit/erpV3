<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JasaController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('jasa')
            ->leftJoin('satuan', 'jasa.id_satuan', '=', 'satuan.id_satuan')
            // Divisi = mesin, sama seperti produk.id_mesin. COALESCE
            // supaya jasa tanpa mesin tetap terbaca sebagai "-" di tabel,
            // bukan sebagai sel kosong yang dianggap hilang.
            ->leftJoin('mesin', 'jasa.id_mesin', '=', 'mesin.id_mesin')
            ->select(
                'jasa.*',
                'satuan.nama_satuan',
                DB::raw('COALESCE(mesin.nama_mesin, "-") as nama_mesin')
            );

        if ($request->filled('search')) {
            $query->where('jasa.nama_jasa', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('id_satuan')) {
            $query->where('jasa.id_satuan', $request->id_satuan);
        }

        // Filter divisi. Nilai kosong berarti "semua" - termasuk jasa
        // yang belum ditugaskan ke mesin mana pun, bukan berarti filter
        // dengan hasil kosong.
        if ($request->filled('id_mesin')) {
            $query->where('jasa.id_mesin', $request->id_mesin);
        }

        $jasa = $query->orderBy('jasa.nama_jasa')->paginate(20)->withQueryString();

        $satuan = DB::table('satuan')->orderBy('nama_satuan')->get();

        // Hanya mesin yang tampil sebagai divisi di produksi (sama seperti
        // DivisiService). Kalau semua mesin ikut, filter ini jadi hampir
        // tidak berguna karena hampir tidak ada jasa yang memakai mesin
        // yang tidak di-flag.
        $mesin = DB::table('mesin')
            ->where('tampil_divisi', true)
            ->orderBy('nama_mesin')
            ->get(['id_mesin', 'nama_mesin']);

        $hargaTiers = DB::table('jasa_harga')
            ->whereIn('id_jasa', $jasa->pluck('id_jasa'))
            ->orderBy('range_min')
            ->get()
            ->groupBy('id_jasa');

        return view('master.jasa.index', compact('jasa', 'satuan', 'mesin', 'hargaTiers'));
    }

    /**
     * Aturan validasi untuk store() dan update() - satu sumber, supaya
     * keduanya tidak bisa berbeda lagi.
     *
     * @return array<string, string>
     */
    private function rules(): array
    {
        return [
            'nama_jasa'         => 'required|max:100',
            'id_satuan'         => 'nullable|exists:satuan,id_satuan',
            // Divisi: mesin pengerjaan. Nullable karena tiga baris jasa
            // yang sudah ada belum punya - mewajibkan akan menggagalkan
            // INSERT sebelum data lama diisi, dan tidak ada nilai default
            // yang bisa diasumsikan.
            'id_mesin'          => 'nullable|exists:mesin,id_mesin',
            'harga_default'     => 'nullable|numeric|min:0',
            'biaya_operasional' => 'nullable|numeric|min:0',
            'biaya_tinta'       => 'nullable|numeric|min:0',
            'keterangan'        => 'nullable|max:1000',
        ];
    }

    public function store(Request $request)
    {
        $request->validate($this->rules());

        $id = DB::table('jasa')->insertGetId([
            'nama_jasa'         => $request->nama_jasa,
            'id_satuan'         => $request->id_satuan ?: null,
            'id_mesin'          => $request->id_mesin ?: null,
            'harga_default'     => $request->harga_default ?: null,
            'biaya_operasional' => $request->biaya_operasional ?: 0,
            'biaya_tinta'       => $request->biaya_tinta ?: 0,
            'keterangan'        => $request->keterangan,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        AuditLogger::log('created', 'jasa', (int) $id, null, (array) DB::table('jasa')->where('id_jasa', $id)->first(), 'Jasa baru: ' . $request->nama_jasa);

        return redirect()->back()->with('success', 'Jasa berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $request->validate($this->rules());

        // Id datang dari URL, tapi harus milik jasa yang benar-benar ada.
        // Tanpa cek ini, query update tidak menyentuh baris mana pun dan
        // tetap membalas "berhasil" - kasir mengira datanya tersimpan
        // padahal tidak ada yang berubah.
        $lama = DB::table('jasa')->where('id_jasa', $id)->first();
        if (! $lama) {
            return redirect()->back()->with('error', 'Jasa tidak ditemukan.');
        }

        DB::table('jasa')->where('id_jasa', $id)->update([
            'nama_jasa'         => $request->nama_jasa,
            'id_satuan'         => $request->id_satuan ?: null,
            'id_mesin'          => $request->id_mesin ?: null,
            'harga_default'     => $request->harga_default ?: null,
            'biaya_operasional' => $request->biaya_operasional ?: 0,
            'biaya_tinta'       => $request->biaya_tinta ?: 0,
            'keterangan'        => $request->keterangan,
            'updated_at'        => now(),
        ]);

        AuditLogger::log('updated', 'jasa', (int) $id, (array) $lama, (array) DB::table('jasa')->where('id_jasa', $id)->first(), 'Jasa diperbarui: ' . $request->nama_jasa);

        return redirect()->back()->with('success', 'Jasa berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $lama = DB::table('jasa')->where('id_jasa', $id)->first();
        DB::table('jasa')->where('id_jasa', $id)->delete();
        if ($lama) {
            AuditLogger::log('deleted', 'jasa', (int) $id, (array) $lama, null, 'Jasa dihapus: ' . $lama->nama_jasa);
        }

        return redirect()->back()->with('success', 'Jasa berhasil dihapus.');
    }
}
