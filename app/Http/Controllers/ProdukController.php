<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProdukController extends Controller
{
public function index(Request $request)
{
    // 1. Buat subquery untuk mengambil daftar bahan baku per produk
    $bomSubquery = DB::table('bom_detail')
        ->leftJoin('bahan_baku', 'bom_detail.id_bahan', '=', 'bahan_baku.id_bahan')
        ->select(
            'bom_detail.id_produk',
            DB::raw("GROUP_CONCAT(DISTINCT bahan_baku.nama_bahan SEPARATOR ', ') as daftar_bahan"),
            DB::raw("GROUP_CONCAT(bom_detail.cara_hitung_pemakaian SEPARATOR ' | ') as cara_hitung_pemakaian")
        )
        ->groupBy('bom_detail.id_produk');

    // 2. Query utama: Join produk dengan mesin dan hasil subquery BOM tadi
    $query = DB::table('produk')
        ->leftJoin('mesin', 'produk.id_mesin', '=', 'mesin.id_mesin')
        ->leftJoinSub($bomSubquery, 'bom_agg', function ($join) {
            $join->on('produk.id_produk', '=', 'bom_agg.id_produk');
        })
        ->select('produk.*', 'mesin.nama_mesin', 'bom_agg.daftar_bahan', 'bom_agg.cara_hitung_pemakaian');

    // 3. Filter Quick Search
    if ($request->filled('search')) {
        $searchTerm = '%' . $request->search . '%';
        $query->where(function($q) use ($searchTerm) {
            $q->where('produk.nama_produk', 'like', $searchTerm)
              ->orWhere('produk.barcode', 'like', $searchTerm);
        });
    }

    // 4. Filter Mesin
    if ($request->filled('category')) {
        $query->where('produk.id_mesin', $request->category);
    }

    // Default cuma tampilkan yang aktif - sama seperti pola BahanBakuController::
    // index(). Produk nonaktif (dipensiunkan tapi masih punya riwayat transaksi
    // lama) tetap ada di DB, cuma disembunyikan dari daftar kerja sehari-hari
    // kecuali diminta eksplisit lewat filter status=nonaktif / semua.
    $statusFilter = $request->get('status', 'aktif');
    if ($statusFilter !== 'semua') {
        $query->where('produk.status', $statusFilter);
    }

    // 5. Eksekusi Pagination
    $produk = $query->latest('produk.created_at')
        ->paginate(20)
        ->withQueryString();

    // Data pendukung untuk modal
    $mesin = DB::table('mesin')->get();
    $bahan_baku = DB::table('bahan_baku')
        ->leftJoin('kategori', 'bahan_baku.id_kategori', '=', 'kategori.id_kategori')
        ->where('bahan_baku.status', 'aktif')
        ->select('bahan_baku.*', 'kategori.nama_kategori')
        ->get();
    $finishing = DB::table('finishing')->orderBy('nama_finishing')->get();

    return view('master.produk.index', compact('produk', 'bahan_baku', 'mesin', 'finishing'));
}
    public function store(Request $request)
    {
        // Validasi dasar
        $request->validate([
            'nama_produk' => 'required|max:100',
            'metode_hitung' => 'required',
            'id_bahan' => 'required|array'
        ]);

        $this->validasiMinimumKelompok($request);

        return DB::transaction(function () use ($request) {
            // Hitung Min Order
            $minPanjang = (float) ($request->min_p ?? 0);
            $minLebar = (float) ($request->min_l ?? 0);
            $minOrderValue = ($request->metode_hitung == 'ukuran')
                ? ($minPanjang * $minLebar) / 10000
                : ($request->min_order_qty ?? 0);

            // Minimum luas per kelompok (hybrid T4-A) - hanya bermakna utk metode
            // 'ukuran'; produk quantity selalu 0 (akumulasi m² tiap berarti di sana).
            $minLuasTotal = ($request->metode_hitung == 'ukuran') ? (float) ($request->min_luas_total ?? 0) : 0;
            $kelompokMinimum = ($request->metode_hitung == 'ukuran' && trim((string) $request->kelompok_minimum) !== '')
                ? trim($request->kelompok_minimum) : null;

            // 1. Insert Produk
            $id_produk = DB::table('produk')->insertGetId([
                'nama_produk'   => $request->nama_produk,
                'id_mesin'      => $request->id_mesin,
                'metode_hitung' => $request->metode_hitung,
                'barcode'       => $request->barcode, // Pastikan barcode masuk
                'spk_lengkap'   => $request->boolean('spk_lengkap'),
                'min_order'     => $minOrderValue,
                'min_panjang'   => $minPanjang,
                'min_lebar'     => $minLebar,
                'min_luas_total' => $minLuasTotal,
                'kelompok_minimum' => $kelompokMinimum,
                'keterangan'    => $request->keterangan ?? '-',
                'status'        => 'aktif',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // 2. Insert BOM
            $bomData = [];
            foreach($request->id_bahan as $key => $val_id_bahan) {
                if(!empty($val_id_bahan)) {
                    $bomData[] = [
                        'id_produk'             => $id_produk,
                        'id_bahan'              => $val_id_bahan,
                        'cara_hitung_pemakaian' => \App\Services\BomCalculationService::rumusValid((string) ($request->cara_hitung_pemakaian[$key] ?? 'Q')) ? $request->cara_hitung_pemakaian[$key] : 'Q',
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ];
                }
            }
            
            if(!empty($bomData)) {
                DB::table('bom_detail')->insert($bomData);
            }

            $this->syncBomFinishing($id_produk, $request);

            // Hardening P0-3: BOM (bom_detail) menentukan qty bahan baku yang
            // dipotong tiap order DAN dipakai ulang saat reversal stok (lihat
            // BomCalculationService/StokReversalService) - sebelumnya tidak
            // ada jejak sama sekali kapan/oleh siapa/dari-ke-apa BOM sebuah
            // produk berubah. Dicatat di sini, bukan lewat trait Auditable,
            // karena controller ini menulis lewat DB::table() (Eloquent tidak
            // pernah terlibat).
            AuditLogger::log('created', 'produk', (int) $id_produk, null, [
                'nama_produk' => $request->nama_produk, 'metode_hitung' => $request->metode_hitung,
                'min_luas_total' => $minLuasTotal, 'kelompok_minimum' => $kelompokMinimum, 'bom' => $bomData,
            ], 'Produk baru dibuat beserta BOM-nya');

            return redirect()->back()->with('success', 'Master Produk & Engineering BOM berhasil disimpan!');
        });
    }

    /**
     * Minimum luas per kelompok (hybrid T4-A) hanya bermakna berpasangan:
     * min_luas_total diisi tanpa nama kelompok tidak bisa digrup-kan di
     * checkout (selisih tidak tahu diroll-kan ke kelompok mana), jadi
     * ditolak di master - bukan diam-diam jadi minimum tanpa kelompok.
     */
    private function validasiMinimumKelompok(Request $request): void
    {
        if ($request->metode_hitung != 'ukuran') {
            return;
        }

        $minLuas = (float) ($request->min_luas_total ?? 0);
        if ($minLuas > 0 && trim((string) $request->kelompok_minimum) === '') {
            abort(redirect()->back()->withInput()
                ->with('error', 'Nama Kelompok Minimum wajib diisi kalau Min. Luas Total Kelompok diisi.'));
        }
    }

    /**
     * Sync Bahan Finishing Penyusun (bom_finishing) - dulu tabel & controller-
     * nya (BomFinishingController) sudah ada tapi TIDAK PERNAH tersambung ke
     * satu pun view/route, jadi fitur "Finishing Penyusun" ini sebelumnya
     * sama sekali tidak bisa dipakai. Sekarang disatukan ke dalam transaksi
     * DB yang sama dengan store()/update() (bukan panggilan AJAX terpisah ke
     * BomFinishingController) - satu tombol "Simpan Semua Data Produk" wajib
     * atomik: bahan baku, finishing, & data produk sendiri sukses/gagal
     * bersamaan. Pola delete & re-insert sama persis dengan sync bom_detail.
     */
    private function syncBomFinishing(int $idProduk, Request $request): void
    {
        DB::table('bom_finishing')->where('id_produk', $idProduk)->delete();

        if (!$request->has('id_finishing')) {
            return;
        }

        $data = [];
        foreach ($request->id_finishing as $key => $idFinishing) {
            if (empty($idFinishing)) {
                continue;
            }
            $data[] = [
                'id_produk'    => $idProduk,
                'id_finishing' => $idFinishing,
                'qty'          => (float) ($request->finishing_qty[$key] ?? 1),
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
        }

        if (!empty($data)) {
            DB::table('bom_finishing')->insert($data);
        }
    }

public function show($id)
{
    $produk = DB::table('produk')->where('id_produk', $id)->first();
    $bom = DB::table('bom_detail')
        ->join('bahan_baku', 'bom_detail.id_bahan', '=', 'bahan_baku.id_bahan')
        ->where('bom_detail.id_produk', $id)
        ->select('bahan_baku.id_bahan', 'bahan_baku.nama_bahan', 'bahan_baku.satuan_stok', 'bom_detail.cara_hitung_pemakaian')
        ->get();
    $bomFinishing = DB::table('bom_finishing')
        ->join('finishing', 'bom_finishing.id_finishing', '=', 'finishing.id_finishing')
        ->where('bom_finishing.id_produk', $id)
        ->select('finishing.id_finishing', 'finishing.nama_finishing', 'bom_finishing.qty')
        ->get();

    return response()->json([
        'produk' => $produk,
        'bom'    => $bom,
        'bom_finishing' => $bomFinishing,
    ]);
}

    public function update(Request $request, $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $terkini = DB::table('produk')->where('id_produk', $id)->lockForUpdate()->first();
            if ($terkini && \App\Support\VersiBaris::bentrok($request, $terkini)) {
                return redirect()->back()->withInput()->with('error', \App\Support\VersiBaris::pesan($terkini));
            }

            $minPanjang = (float) ($request->min_p ?? 0);
            $minLebar = (float) ($request->min_l ?? 0);
            $minOrderValue = ($request->metode_hitung == 'ukuran')
                ? ($minPanjang * $minLebar) / 10000
                : ($request->min_order_qty ?? $request->min_order ?? 0);

            // Sama seperti store(): minimum luas kelompok hanya utk metode 'ukuran'.
            $this->validasiMinimumKelompok($request);
            $minLuasTotal = ($request->metode_hitung == 'ukuran') ? (float) ($request->min_luas_total ?? 0) : 0;
            $kelompokMinimum = ($request->metode_hitung == 'ukuran' && trim((string) $request->kelompok_minimum) !== '')
                ? trim($request->kelompok_minimum) : null;

            // BOM lama diambil SEBELUM delete&re-insert supaya bisa dicatat
            // ke audit_logs (lihat catatan di bawah, dekat AuditLogger::log).
            $bomLama = DB::table('bom_detail')->where('id_produk', $id)->get(['id_bahan', 'cara_hitung_pemakaian'])->toArray();

            // 1. Update Produk
            DB::table('produk')->where('id_produk', $id)->update([
                'nama_produk'   => $request->nama_produk,
                'id_mesin'      => $request->id_mesin,
                'metode_hitung' => $request->metode_hitung,
                'barcode'       => $request->barcode,
                'spk_lengkap'   => $request->boolean('spk_lengkap'),
                'min_order'     => $minOrderValue,
                'min_panjang'   => $minPanjang,
                'min_lebar'     => $minLebar,
                'min_luas_total' => $minLuasTotal,
                'kelompok_minimum' => $kelompokMinimum,
                'keterangan'    => $request->keterangan ?? '-',
                'updated_at'    => now(),
            ]);

            // 2. Sync BOM (Delete & Re-insert adalah cara termudah untuk array input)
            DB::table('bom_detail')->where('id_produk', $id)->delete();
            
            if($request->has('id_bahan')) {
                $bomData = [];
                foreach($request->id_bahan as $key => $val_id_bahan) {
                    if(!empty($val_id_bahan)) {
                        $bomData[] = [
                            'id_produk'             => $id,
                            'id_bahan'              => $val_id_bahan,
                            'cara_hitung_pemakaian' => \App\Services\BomCalculationService::rumusValid((string) ($request->cara_hitung_pemakaian[$key] ?? 'Q')) ? $request->cara_hitung_pemakaian[$key] : 'Q',
                            'created_at'            => now(),
                            'updated_at'            => now(),
                        ];
                    }
                }
                if(!empty($bomData)) {
                    DB::table('bom_detail')->insert($bomData);
                }
            }

            $this->syncBomFinishing((int) $id, $request);

            // Hardening P0-3 (lihat catatan sama di store()). Selalu dicatat
            // setiap kali form disimpan (bukan cuma saat terdeteksi beda) -
            // form ini SELALU delete&re-insert seluruh bom_detail, jadi
            // pembanding "apakah benar-benar berubah" gampang keliru kalau
            // tipe data request (string) vs kolom DB (int) tidak persis sama;
            // lebih aman audit_logs sesekali mencatat baris yang isinya
            // identik daripada sampai ada perubahan BOM yang lolos tak tercatat.
            $bomBaruUntukLog = collect($request->id_bahan ?? [])->filter()
                ->map(fn ($v, $k) => ['id_bahan' => $v, 'cara_hitung_pemakaian' => $request->cara_hitung_pemakaian[$k] ?? 'Q'])
                ->values()->all();
            AuditLogger::log('updated', 'produk', (int) $id, [
                'bom' => $bomLama, 'minimum_kelompok' => [
                    'min_luas_total' => $terkini->min_luas_total ?? null,
                    'kelompok_minimum' => $terkini->kelompok_minimum ?? null,
                ],
            ], [
                'bom' => $bomBaruUntukLog, 'minimum_kelompok' => [
                    'min_luas_total' => $minLuasTotal, 'kelompok_minimum' => $kelompokMinimum,
                ],
            ], 'BOM & minimum kelompok produk diperbarui (atau disimpan ulang tanpa perubahan)');

            return redirect()->back()->with('success', 'Update Produk & Engineering Berhasil.');
        });
    }
    /**
     * Hapus permanen HANYA untuk produk yang belum pernah dipakai di
     * transaksi apa pun - transaksi_detail.id_produk sengaja FK RESTRICT
     * (bukan CASCADE) supaya riwayat nota lama tidak pernah ikut hilang atau
     * jadi rujukan yatim kalau produknya dihapus. Sebelum ini destroy() juga
     * tidak membersihkan bom_finishing & harga_promo (dua tabel lain yang
     * FK ke produk) - keduanya sekarang ikut dibersihkan supaya produk yang
     * memang belum ada riwayat transaksi bisa dihapus bersih tanpa
     * SQLSTATE[23000]. Untuk produk yang SUDAH punya riwayat, arahkan ke
     * Nonaktifkan (lihat toggleStatus) alih-alih memaksa hapus.
     */
    public function destroy($id)
    {
        if (DB::table('transaksi_detail')->where('id_produk', $id)->exists()) {
            return redirect()->back()->with('error', 'Produk ini sudah pernah dipakai dalam transaksi, tidak bisa dihapus permanen. Gunakan tombol Nonaktifkan supaya tidak muncul lagi di order baru, riwayat lama tetap utuh.');
        }

        try {
            DB::transaction(function () use ($id) {
                $produkLama = DB::table('produk')->where('id_produk', $id)->first();
                DB::table('setting_harga')->where('id_produk', $id)->delete();
                DB::table('bom_detail')->where('id_produk', $id)->delete();
                DB::table('bom_finishing')->where('id_produk', $id)->delete();
                DB::table('harga_promo')->where('id_produk', $id)->delete();
                DB::table('produk')->where('id_produk', $id)->delete();
                AuditLogger::log('deleted', 'produk', (int) $id, ['nama_produk' => $produkLama->nama_produk ?? null], null, 'Produk dihapus permanen beserta BOM & harga terkait');
            });
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->with('error', 'Produk masih terikat data lain dan tidak bisa dihapus. Gunakan tombol Nonaktifkan sebagai gantinya.');
        }

        return redirect()->back()->with('success', 'Produk berhasil dihapus selamanya.');
    }

    /**
     * Alternatif hapus permanen - lihat catatan di destroy(). Pola sama
     * persis dengan BahanBakuController::toggleStatus().
     */
    public function toggleStatus($id)
    {
        $produk = DB::table('produk')->where('id_produk', $id)->first();
        if (!$produk) {
            return redirect()->back()->with('error', 'Produk tidak ditemukan.');
        }

        $statusBaru = $produk->status === 'aktif' ? 'nonaktif' : 'aktif';
        DB::table('produk')->where('id_produk', $id)->update(['status' => $statusBaru, 'updated_at' => now()]);

        return redirect()->back()->with('success', $statusBaru === 'aktif'
            ? 'Produk diaktifkan kembali.'
            : 'Produk dinonaktifkan - tidak akan muncul lagi di pilihan order baru.');
    }
}