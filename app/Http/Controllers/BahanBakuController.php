<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\BahanBaku;
use App\Models\Kategori;
use App\Models\Mesin;
use App\Models\Satuan;
use App\Models\Merk;
use App\Models\JenisBahan;
use App\Services\AccountingService;
use App\Services\AuditLogger;

class BahanBakuController extends Controller
{
    public function index(Request $request)
    {
        // Gunakan Eager Loading untuk semua relasi termasuk satuan ganda
        $query = BahanBaku::with(['kategori', 'mesin', 'satuanStok', 'satuanHitung', 'merk', 'jenisBahan']);

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('nama_bahan', 'like', '%' . $request->search . '%')
                  ->orWhere('kode_bahan', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('kategori')) {
            $query->where('id_kategori', $request->kategori);
        }

        // Default cuma tampilkan yang aktif - bahan nonaktif (varian lama
        // yang sudah berhenti dipakai) tetap ada di DB & riwayatnya utuh,
        // cuma disembunyikan dari daftar kerja sehari-hari kecuali diminta
        // eksplisit lewat filter.
        $statusFilter = $request->get('status', 'aktif');
        if ($statusFilter !== 'semua') {
            $query->where('status', $statusFilter);
        }

        if ($request->get('harga') === 'kosong') {
            $query->where(function ($q) {
                $q->whereNull('harga_rata_rata')->orWhere('harga_rata_rata', '<=', 0);
            });
        }

        // Urutan bawaan = abjad, sama dengan pilihan yang tampil di filter
        // (sebelumnya bawaannya "terbaru" padahal dropdown menampilkan Abjad,
        // sehingga memilih Abjad tidak memicu apa pun).
        $sortBy = $request->get('sort', 'nama_bahan');
        if ($sortBy == 'stok') {
            $query->orderBy('stok', 'asc')->orderBy('nama_bahan', 'asc');
        } elseif ($sortBy == 'created_at') {
            $query->orderBy('created_at', 'desc');
        } else {
            $query->orderBy('nama_bahan', 'asc');
        }

        $bahan = $query->paginate(20);
        $kategori = Kategori::all();
        $mesin = Mesin::all();
        $satuan = Satuan::all();
        $merk = Merk::orderBy('nama_merk')->get();
        $jenisBahan = JenisBahan::orderBy('nama_jenis')->get();

        // Pembanding harga: harga beli terakhir per bahan (dari faktur pembelian aktif) vs harga rata-rata.
        $beliTerakhir = \Illuminate\Support\Facades\DB::table('pembelian_bahan_detail as d')
            ->join('pembelian_bahan as p', 'p.id_pembelian', '=', 'd.id_pembelian')
            ->whereNull('p.deleted_at')->whereIn('d.id_bahan', $bahan->pluck('id_bahan'))
            ->orderByDesc('p.tgl_transaksi')->orderByDesc('p.id_pembelian')
            ->get(['d.id_bahan', 'd.harga', 'p.tgl_transaksi', 'p.no_faktur'])
            ->unique('id_bahan')->keyBy('id_bahan');

        return view('master/bahan.index', compact('bahan', 'kategori', 'mesin', 'satuan', 'merk', 'jenisBahan', 'beliTerakhir'));
    }

    /**
     * Koreksi harga rata-rata (nilai per satuan stok) bahan yang SUDAH ada -
     * mis. bahan lama yang belum punya harga sehingga HPP/margin nol. Hanya
     * mengubah dasar biaya HPP ke depan; tidak membuat jurnal dan tidak
     * menyentuh stok. Alasan wajib dan perubahan tercatat di log aktivitas.
     */
    public function updateHarga(Request $request, $id_bahan)
    {
        $data = $request->validate([
            'harga_rata_rata' => 'required|numeric|min:0',
            'alasan' => 'required|string|max:255',
        ]);

        $bahan = BahanBaku::findOrFail($id_bahan);
        if (\App\Support\VersiBaris::bentrok($request, $bahan)) {
            return back()->with('error', \App\Support\VersiBaris::pesan($bahan));
        }
        $lama = (float) $bahan->harga_rata_rata;
        $baru = round((float) $data['harga_rata_rata'], 2);

        if ($lama === $baru) {
            return back()->with('error', 'Harga tidak berubah.');
        }

        DB::transaction(function () use ($bahan, $lama, $baru, $data) {
            DB::table('bahan_baku')->where('id_bahan', $bahan->id_bahan)->update(['harga_rata_rata' => $baru, 'updated_at' => now()]);
            AuditLogger::log('updated', 'bahan_baku', (int) $bahan->id_bahan, ['harga_rata_rata' => $lama], ['harga_rata_rata' => $baru], 'Koreksi harga rata-rata: ' . $data['alasan']);
        });

        return back()->with('success', "Harga rata-rata {$bahan->nama_bahan} diperbarui menjadi Rp " . number_format($baru, 0, ',', '.') . '.');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_bahan'        => 'required|max:100',
            'kode_bahan'        => 'required|unique:bahan_baku,kode_bahan|max:50',
            'id_mesin'          => 'required',
            'id_kategori'       => 'required',
            'id_satuan_stok'    => 'required',
            'id_satuan_hitung'  => 'required',
            'nilai_konversi'    => 'required|numeric|min:0.0001',
            'stok'              => 'required|numeric|min:0',
            'stok_minimal'      => 'required|numeric',
            'id_merk'           => 'nullable|exists:merk,id_merk',
            'id_jenis_bahan'    => 'nullable|exists:jenis_bahan,id_jenis_bahan',
            'lebar_roll'        => 'nullable|numeric|min:0',
            'panjang_roll'      => 'nullable|numeric|min:0',
            'harga_beli'        => 'nullable|numeric|min:0',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                // satuan_stok (kolom string legacy) diturunkan otomatis dari nama
                // satuan yang dipilih di id_satuan_stok - form ini tidak pernah
                // mengirim field teks terpisah untuk itu, jadi sebelumnya selalu
                // tersimpan '-' untuk SEMUA bahan baru (lihat Paper Sublime,
                // id_bahan 13, yang persis menunjukkan gejala ini).
                $namaSatuanStok = Satuan::find($request->id_satuan_stok)->nama_satuan ?? '-';

                $bahan = BahanBaku::create([
                    'nama_bahan'       => $request->nama_bahan,
                    'kode_bahan'       => $request->kode_bahan,
                    'id_mesin'         => $request->id_mesin,
                    'id_kategori'      => $request->id_kategori,
                    'id_satuan_stok'   => $request->id_satuan_stok,
                    'id_satuan_hitung' => $request->id_satuan_hitung,
                    'nilai_konversi'   => $request->nilai_konversi,
                    'lebar_roll'       => $request->lebar_roll ?: null,
                    'panjang_roll'     => $request->panjang_roll ?: null,
                    'stok'             => $request->stok,
                    'stok_minimal'     => $request->stok_minimal,
                    'harga_rata_rata'  => (float) $request->harga_beli > 0 ? round((float) $request->harga_beli, 2) : 0,
                    'satuan_stok'      => $namaSatuanStok,
                    'id_merk'          => $request->id_merk ?: null,
                    'id_jenis_bahan'   => $request->id_jenis_bahan ?: null,
                    'status'           => 'aktif'
                ]);

                // Stok awal saat bahan baru didaftarkan tetap harus tercatat
                // di kartu_stok - kalau tidak, angka stok pertama bahan ini
                // muncul begitu saja tanpa jejak (audit trail bolong sejak
                // hari pertama bahan itu ada).
                if ((float) $request->stok > 0) {
                    DB::table('kartu_stok')->insert([
                        'id_bahan'     => $bahan->id_bahan,
                        'tanggal'      => now(),
                        'tipe'         => 'IN',
                        'qty'          => $request->stok,
                        'stok_sebelum' => 0,
                        'stok_sesudah' => $request->stok,
                        'referensi'    => 'STOK-AWAL',
                        'keterangan'   => 'Stok awal saat pendaftaran bahan baru',
                        'created_by'   => auth()->id(),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }

                // Harga beli per satuan stok (opsional) = modal awal HPP bahan ini
                // (harga_rata_rata), dan - bila stok awal > 0 - dijurnal sebagai
                // saldo awal persediaan (Dr Persediaan / Cr Modal) kecuali
                // dimatikan (mis. saldo awal akuntansi akan ditetapkan sekaligus
                // lewat akuntansi:saldo-awal supaya tidak dobel).
                $hargaBeli = (float) $request->harga_beli;
                $nilaiStokAwal = round((float) $request->stok * $hargaBeli, 2);
                if ($hargaBeli > 0) {
                    AuditLogger::log('updated', 'bahan_baku', (int) $bahan->id_bahan, null, ['harga_rata_rata' => round($hargaBeli, 2)], 'Harga beli awal saat registrasi bahan');
                }
                if ($nilaiStokAwal > 0 && $request->boolean('catat_jurnal', true)) {
                    AccountingService::postSaldoAwalPersediaan(
                        'STOK-AWAL-' . $bahan->kode_bahan,
                        "Saldo awal persediaan {$bahan->nama_bahan} ({$request->stok} {$namaSatuanStok} x Rp " . number_format($hargaBeli, 0, ',', '.') . ')',
                        $nilaiStokAwal
                    );
                }

                return back()->with('success', 'Bahan baku dengan logika konversi berhasil disimpan!');
            });
        } catch (\Exception $e) {
            return back()->with('error', \App\Support\PesanError::untuk($e, 'Gagal menyimpan'));
        }
    }

    public function update(Request $request, $id_bahan)
    {
        $request->validate([
            'nama_bahan'        => 'required|max:100',
            'kode_bahan'        => 'max:50|required|unique:bahan_baku,kode_bahan,'.$id_bahan.',id_bahan',
            'id_satuan_stok'    => 'required',
            'id_satuan_hitung'  => 'required',
            'nilai_konversi'    => 'required|numeric|min:0.0001',
            'stok_minimal'      => 'required|numeric',
            'id_merk'           => 'nullable|exists:merk,id_merk',
            'id_jenis_bahan'    => 'nullable|exists:jenis_bahan,id_jenis_bahan',
            'lebar_roll'        => 'nullable|numeric|min:0',
            'panjang_roll'      => 'nullable|numeric|min:0',
        ]);

        $bahan = BahanBaku::findOrFail($id_bahan);
        if (\App\Support\VersiBaris::bentrok($request, $bahan)) {
            return back()->withInput()->with('error', \App\Support\VersiBaris::pesan($bahan));
        }

        // 'stok' SENGAJA tidak lagi diterima di sini (sebelumnya ada di
        // validasi & fillable, jadi form edit bahan biasa - buat perbaiki
        // nama/kategori/dll - bisa diam-diam mengubah angka stok tanpa
        // jejak apa pun di kartu_stok. Field "Stok Awal" di modal sekarang
        // dikunci saat mode edit (lihat editBahan() di view); satu-satunya
        // jalur resmi mengubah stok bahan yang SUDAH ada adalah Kartu Stok
        // > Penyesuaian, yang mewajibkan alasan dan selalu mencatat kartu_stok.
        $data = $request->only([
            'nama_bahan', 'kode_bahan', 'id_mesin', 'id_kategori',
            'stok_minimal', 'id_satuan_stok', 'id_satuan_hitung', 'nilai_konversi',
        ]);
        $data['id_merk'] = $request->id_merk ?: null;
        $data['id_jenis_bahan'] = $request->id_jenis_bahan ?: null;
        $data['lebar_roll'] = $request->lebar_roll ?: null;
        $data['panjang_roll'] = $request->panjang_roll ?: null;
        $data['satuan_stok'] = Satuan::find($request->id_satuan_stok)->nama_satuan ?? $bahan->satuan_stok;

        $bahan->update($data);

        // back() bukan redirect()->route() supaya query string halaman saat
        // ini (?page=2&search=...) ikut terbawa - edit banyak bahan dari
        // halaman 2+ dulu selalu terlempar balik ke halaman 1 tiap simpan.
        return back()->with('success', 'Data bahan & logika konversi diperbarui!');
    }

    public function destroy($id)
    {
        // Audit Fase 2: penjagaan di tingkat aplikasi. Komentar migrasi bilang
        // FK RESTRICT yang menolak hapus bahan berriwayat, tetapi FK itu tidak
        // ada di database produksi (hilang saat pemulihan crash) - tanpa cek
        // ini bahan berriwayat terhapus dan meninggalkan kartu_stok/BOM orphan.
        $dipakai = self::pemakaianBahan((int) $id);
        if ($dipakai !== []) {
            return back()->with('error', 'Bahan tidak bisa dihapus karena sudah tercatat di: ' . implode(', ', $dipakai) . '. Nonaktifkan saja agar riwayatnya tetap utuh.');
        }

        try {
            BahanBaku::destroy($id);
            return back()->with('success', 'Bahan berhasil dihapus.');
        } catch (\Exception $e) {
            report($e);
            return back()->with('error', 'Gagal hapus! Data masih terikat dengan transaksi.');
        }
    }

    /**
     * Alternatif untuk hapus permanen: bahan yang sudah punya riwayat
     * transaksi tidak pernah bisa dihapus (FK RESTRICT, sengaja begitu -
     * lihat migrasi 310000). Nonaktifkan menyembunyikan bahan dari
     * dropdown pemilihan di transaksi baru (Kedatangan Bahan, BOM Produk)
     * tanpa kehilangan histori kartu_stok/BOM yang sudah ada - berguna
     * untuk varian lebar roll yang berhenti dipakai tapi tetap perlu
     * muncul di laporan lama.
     */
    public function toggleStatus($id)
    {
        $bahan = BahanBaku::findOrFail($id);
        $bahan->update([
            'status' => $bahan->status === 'aktif' ? 'nonaktif' : 'aktif',
        ]);

        return back()->with('success', $bahan->status === 'aktif'
            ? 'Bahan diaktifkan kembali.'
            : 'Bahan dinonaktifkan - tidak akan muncul lagi di pilihan transaksi baru.');
    }

    /**
     * Riwayat yang membuat bahan tidak boleh dihapus (dipakai hapus satuan dan massal).
     *
     * @return array<int, string>
     */
    private static function pemakaianBahan(int $id): array
    {
        $dipakai = [];
        foreach ([
            'kartu stok' => 'kartu_stok', 'BOM produk' => 'bom_detail', 'kedatangan bahan' => 'kedatangan_bahan_detail',
            'pembelian' => 'pembelian_bahan_detail', 'nota penjualan' => 'transaksi_detail', 'purchase order' => 'purchase_order_details',
            'purchase request' => 'purchase_request_details', 'stock opname' => 'stock_opname_details', 'waste' => 'waste_log',
            'material jersey' => 'jersey_materials', 'potong stok jersey' => 'jersey_order_potong_stok',
        ] as $label => $tabel) {
            if (DB::table($tabel)->where('id_bahan', $id)->exists()) {
                $dipakai[] = $label;
            }
        }

        return $dipakai;
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->ids;

        if (!$ids) {
            return back()->with('error', 'Tidak ada data dipilih.');
        }

        // Satu per satu, dengan pengecekan riwayat yang sama dengan hapus satuan: sebelumnya hanya mengandalkan FK,
        // padahal sebagian tabel riwayat tidak punya FK sehingga bahan berriwayat ikut terhapus.
        $berhasil = 0;
        $ditahan = [];

        foreach ($ids as $id) {
            // Per bahan satu transaksi dengan baris bahan dikunci: setiap pergerakan stok juga mengunci baris ini, jadi
            // riwayat baru tidak bisa menyelip di antara pemeriksaan riwayat dan penghapusan.
            try {
                $hasil = DB::transaction(function () use ($id) {
                    $bahan = BahanBaku::whereKey($id)->lockForUpdate()->first();
                    if (!$bahan) {
                        return null;
                    }
                    if (self::pemakaianBahan((int) $id) !== []) {
                        return $bahan->nama_bahan;
                    }
                    $bahan->delete();

                    return true;
                });
            } catch (\Exception $e) {
                report($e);
                $hasil = (string) (BahanBaku::whereKey($id)->value('nama_bahan') ?? "#{$id}");
            }

            if ($hasil === true) {
                $berhasil++;
            } elseif (is_string($hasil)) {
                $ditahan[] = $hasil;
            }
        }

        $pesan = "{$berhasil} bahan berhasil dihapus.";
        if ($ditahan !== []) {
            $pesan .= ' Tidak dihapus karena sudah punya riwayat transaksi (nonaktifkan saja): ' . implode(', ', $ditahan) . '.';
        }

        return back()->with($berhasil > 0 ? 'success' : 'error', $pesan);
    }
}