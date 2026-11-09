<?php

namespace App\Http\Controllers;

use App\Models\Finishing;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinishingController extends Controller
{

    public function index(Request $request)
    {
        // Pencarian dan penyaring sama dengan halaman master lain (Jasa, Konsumen).
        $data = Finishing::with(['mesin', 'kategori'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w
                ->where('nama_finishing', 'like', '%' . $request->search . '%')
                ->orWhere('keterangan', 'like', '%' . $request->search . '%')))
            ->when($request->filled('id_mesin'), fn ($q) => $q->where('id_mesin', $request->id_mesin))
            ->when($request->filled('id_kategori'), fn ($q) => $request->id_kategori === 'semua'
                ? $q->whereNull('id_kategori')
                : $q->where('id_kategori', $request->id_kategori))
            ->when($request->filled('metode_hitung'), fn ($q) => $q->where('metode_hitung', $request->metode_hitung))
            ->orderBy('nama_finishing')
            ->get();
        $mesin = DB::table('mesin')->orderBy('nama_mesin')->get();
        $kategori = DB::table('kategori')->orderBy('nama_kategori')->get();

        $hargaTiers = DB::table('finishing_harga')
            ->whereIn('id_finishing', $data->pluck('id_finishing'))
            ->orderBy('range_min')
            ->get()
            ->groupBy('id_finishing');

        return view('finishing.index', compact('data', 'mesin', 'kategori', 'hargaTiers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_finishing' => 'required|string|max:100',
            'harga_default'  => 'required|numeric|min:0',
            'id_mesin'       => 'nullable|exists:mesin,id_mesin',
            'id_kategori'    => 'nullable|exists:kategori,id_kategori',
            // LINEAR_GRID = Potong Manual (biaya dari meter garis potong x
            // tarif per meter + minimum charge). Dulu metode ini TIDAK ada
            // di daftar, padahal master Potong Manual memakainya - artinya
            // finishing Potong Manual tidak bisa dibuat maupun diubah tarifnya
            // lewat halaman ini, harus lewat SQL.
            // KEPING_ROLL = jahit keliling per keping: meter jahit =
            // jumlah keping x keliling UKURAN BARANG jahit (2x(P_b+L_b)/100).
            // Media/nota (mis. 120x500) tidak ikut jahit - barang 100x500
            // tetap jahit keliling 100x500.
            'metode_hitung'  => 'required|in:luas,keliling,quantity,LINEAR_GRID,KEPING_ROLL',
            // Lantai biaya finishing per item (dipakai mode Potong Manual).
            // 0 = tidak ada minimum charge.
            'minimum_charge' => 'nullable|numeric|min:0',
            'keterangan'     => 'nullable|string',
        ]);

        $finishing = Finishing::create($validated);

        AuditLogger::log('created', 'finishing', (int) $finishing->id_finishing, null, $validated,
            'Finishing baru: ' . $validated['nama_finishing']);

        return redirect()->back()->with('success', 'Finishing berhasil ditambahkan');
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nama_finishing' => 'required|string|max:100',
            'harga_default'  => 'required|numeric|min:0',
            'id_mesin'       => 'nullable|exists:mesin,id_mesin',
            'id_kategori'    => 'nullable|exists:kategori,id_kategori',
            // Sama seperti store() - LINEAR_GRID (Potong Manual) boleh.
            // KEPING_ROLL = jahit keliling per keping: meter jahit =
            // jumlah keping x keliling UKURAN BARANG jahit (2x(P_b+L_b)/100).
            // Media/nota (mis. 120x500) tidak ikut jahit - barang 100x500
            // tetap jahit keliling 100x500.
            'metode_hitung'  => 'required|in:luas,keliling,quantity,LINEAR_GRID,KEPING_ROLL',
            'minimum_charge' => 'nullable|numeric|min:0',
            'keterangan'     => 'nullable|string',
        ]);

        $lama = Finishing::findOrFail($id)->toArray();
        Finishing::findOrFail($id)->update($validated);
        $baru = Finishing::findOrFail($id)->toArray();

        AuditLogger::log('updated', 'finishing', (int) $id, $lama, $baru,
            'Finishing diperbarui: ' . ($validated['nama_finishing'] ?? $lama['nama_finishing']));

        return redirect()->back()->with('success', 'Finishing berhasil diperbarui');
    }

    public function destroy($id)
    {
        // Cek pemakaian di BOM Finishing supaya tidak menghapus jenis
        // finishing yang masih dipakai oleh produk manapun.
        $terpakai = DB::table('bom_finishing')->where('id_finishing', $id)->exists();
        if ($terpakai) {
            return redirect()->back()->with('error', 'Gagal hapus! Finishing ini masih digunakan pada BOM Produk.');
        }

        // Ditambahkan: tier harga dan pemakaian di transaksi_detail juga
        // menunjuk finishing. Keduanya tidak punya foreign key, jadi
        // menghapusnya akan meninggalkan baris yang menunjuk finishing
        // yang sudah tidak ada - dan harga jual per finishing ikut hilang
        // tanpa jejak.
        $terpakaiLain = [
            'tier harga'    => DB::table('finishing_harga')->where('id_finishing', $id)->exists(),
            'transaksi'     => DB::table('transaksi_detail')->where('id_finishing', $id)->exists(),
        ];
        $dipakai = array_keys(array_filter($terpakaiLain));
        if ($dipakai) {
            return redirect()->back()->with('error',
                'Gagal hapus! Finishing ini masih dipakai pada: ' . implode(', ', $dipakai) . '.'
            );
        }

        $lama = Finishing::findOrFail($id)->toArray();
        Finishing::findOrFail($id)->delete();

        AuditLogger::log('deleted', 'finishing', (int) $id, $lama, null,
            'Finishing dihapus: ' . ($lama['nama_finishing'] ?? ''));

        return redirect()->back()->with('success', 'Finishing dihapus');
    }

}
