<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Supplier;
use App\Models\BahanBaku;
use App\Models\PembelianBahan;
use App\Models\KartuStok;
use App\Models\KedatanganBahan;
use App\Models\KedatanganBahanDetail;
use App\Models\Gudang;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KedatanganBahanController extends Controller
{
    /**
     * Ambil daftar gudang dari tabel `gudang` yang sesungguhnya.
     * Sebelumnya data ini di-hardcode langsung di controller (2 baris
     * dummy "Gudang Utama" / "Gudang Samping"). Sekarang dibaca dari
     * database, dengan fallback ke data lama HANYA jika tabel `gudang`
     * belum ada / masih kosong, supaya form kedatangan bahan tetap bisa
     * dipakai walau migration belum sempat dijalankan di environment ini.
     */
    private function getGudangList()
    {
        if (Schema::hasTable('gudang')) {
            $gudangs = Gudang::orderBy('id_gudang')->get();
            if ($gudangs->isNotEmpty()) {
                return $gudangs;
            }
        }

        return collect([
            (object)['id_gudang' => 1, 'nama_gudang' => 'Gudang Utama'],
            (object)['id_gudang' => 2, 'nama_gudang' => 'Gudang Samping'],
        ]);
    }

    public function index(Request $request)
{
    $query = KedatanganBahan::with(['supplier', 'gudang']);

    // 1. Filter Pencarian (No SJ atau Nama Supplier)
    $query->when($request->search, function ($q) use ($request) {
        $q->where('no_surat_jalan', 'like', '%' . $request->search . '%')
          ->orWhereHas('supplier', function ($sq) use ($request) {
              $sq->where('nama_supplier', 'like', '%' . $request->search . '%');
          });
    });

    // 2. Filter Tanggal (Range)
    $query->when($request->from, function ($q) use ($request) {
        $q->where('tgl_datang', '>=', $request->from); // DATE: sargable
    });

    $query->when($request->to, function ($q) use ($request) {
        $q->where('tgl_datang', '<=', $request->to);
    });

    // Urutkan berdasarkan yang terbaru dan paginasi
    $kedatangans = $query->latest('tgl_datang')->paginate(20);

    return view('kedatangan-bahan.index', compact('kedatangans'));
}
    public function create()
    {
        $suppliers = Supplier::orderBy('nama_supplier')->get();
        $platforms = \App\Models\PlatformBelanja::where('aktif', true)->orderBy('urutan')->orderBy('nama')->get();
        $bahans = BahanBaku::where('status', 'aktif')->orderBy('nama_bahan')->get();

        $gudangs = $this->getGudangList();
        return view('kedatangan-bahan.create', compact('suppliers', 'bahans', 'gudangs', 'platforms'));
    }

  public function store(Request $request)
{
    // 1. Validasi Backend (Pastikan field sesuai dengan name di HTML)
    $request->validate([
        'no_surat_jalan' => 'required|unique:kedatangan_bahan,no_surat_jalan|max:255',
        'id_supplier'    => 'required|exists:supplier,id_supplier',
        // Audit kelengkapan (dropdown wajib terisi): gudang menentukan lokasi
        // fisik stok yang masuk - wajib dipilih (dulu nullable, barang masuk
        // tanpa gudang jelas).
        'id_gudang'      => 'required|exists:gudang,id_gudang',
        'id_platform'    => 'nullable|exists:platform_belanja,id',
        'tgl_datang'     => ['required', 'date', new \App\Rules\TanggalTransaksi],
        'id_bahan'       => 'required|array|min:1',
        'qty'            => 'required|array|min:1',
        'qty.*'          => 'required|numeric|min:0.01',
    ]);

    try {
        \DB::beginTransaction();

        // 2. Simpan Header
        $kedatangan = KedatanganBahan::create([
    'no_surat_jalan' => $request->no_surat_jalan,
    'id_supplier'    => $request->id_supplier,
    'tgl_datang'     => $request->tgl_datang,
    'id_gudang'      => $request->id_gudang, // Tambahkan ini
    'keterangan'     => $request->keterangan,
    'id_platform'       => $request->filled('id_platform') ? (int) $request->id_platform : null,
    'nama_toko_online'  => $request->filled('id_platform') ? $request->nama_toko_online : null,
    'no_pesanan_online' => $request->filled('id_platform') ? $request->no_pesanan_online : null,
    'no_resi'           => $request->filled('id_platform') ? $request->no_resi : null,
]);

        // 3. Simpan Detail (Looping Array)
        foreach ($request->id_bahan as $index => $id_bahan) {
            $kedatangan->detail()->create([
                'id_bahan' => $id_bahan,
                'qty'      => $request->qty[$index],
            ]);

            // OPSIONAL: Update stok di tabel bahan jika diperlukan
            // $bahan = Bahan::find($id_bahan);
            // $bahan->increment('stok', $request->qty[$index]);
        }

        \DB::commit();
        session()->flash('lanjut', ['teks' => 'Berikutnya: cek qty fisik, lalu klik Validasi agar stok bertambah', 'url' => null, 'tombol' => '']);
        return redirect()->route('kedatangan-bahan.index')
                         ->with('success', 'Data berhasil disimpan!');

    } catch (\Exception $e) {
        \DB::rollBack();
        return back()->withInput()->with('error', \App\Support\PesanError::untuk($e, 'Gagal simpan'));
    }
}
public function validateStatus($id)
{
    return DB::transaction(function () use ($id) {
        // Ambil data kedatangan beserta itemnya
        // lockForUpdate() (konsisten dengan update()/bulkDelete()/destroy() di file ini)
        // supaya klik ganda atau dua kedatangan untuk id_bahan yang sama tidak
        // menyebabkan lost-update pada bahan_baku.stok / validasi dobel.
        $kedatangan = KedatanganBahan::with('detail')->lockForUpdate()->findOrFail($id);

        if ($kedatangan->status == 'Selesai') {
            return back()->with('error', 'Peringatan: Data ini sudah divalidasi sebelumnya.');
        }

        foreach ($kedatangan->detail as $item) {
            // Ambil stok terakhir bahan baku
            $bahan = BahanBaku::where('id_bahan', $item->id_bahan)->lockForUpdate()->first();
            
            $stokAwal = $bahan->stok;
            $stokAkhir = $stokAwal + $item->qty;

            // 1. Update Stok di Master Bahan
            $bahan->update(['stok' => $stokAkhir]);

            // 2. Insert ke Kartu Stok (Sesuai Struktur SQL Anda)
            KartuStok::create([
                'id_bahan'      => $item->id_bahan,
                'tanggal'       => now(),
                'tipe'          => 'IN',
                'qty'           => $item->qty,
                'stok_sebelum'  => $stokAwal,
                'stok_sesudah'  => $stokAkhir,
                'referensi'     => $kedatangan->no_surat_jalan,
                'keterangan'    => 'Penerimaan Gudang: ' . ($kedatangan->keterangan ?? 'Tanpa Catatan'),
                'created_by'    => auth()->id(), // Mencatat siapa yang melakukan validasi
            ]);
        }

        // 3. Update Status Kedatangan
        $kedatangan->update(['status' => 'Selesai']);
        \App\Services\PurchaseOrderService::sinkronPenerimaan($kedatangan->id_purchase_order);

        \App\Support\JalurLanjut::set('Berikutnya: catat faktur/tagihan supplier', 'pembelian-bahan.create', 'Buat Faktur Pembelian');
        return back()->with('success', 'Berhasil! Stok telah diperbarui dan tercatat di Kartu Stok.');
    });
}
    public function edit($id)
    {
        $kedatangan = KedatanganBahan::with('detail.bahan')->findOrFail($id);
        $suppliers = Supplier::orderBy('nama_supplier')->get();
        $platforms = \App\Models\PlatformBelanja::where(fn ($q) => $q->where('aktif', true)->orWhere('id', $kedatangan->id_platform))->orderBy('urutan')->orderBy('nama')->get();
        $bahans = BahanBaku::where('status', 'aktif')->orderBy('nama_bahan')->get();

        $gudangs = $this->getGudangList();

        return view('kedatangan-bahan.create', compact('kedatangan', 'suppliers', 'bahans', 'gudangs', 'platforms'));
    }
public function bulkDelete(Request $request)
{
    $ids = $request->ids;

    if (!$ids) {
        return back()->with('error', 'Tidak ada data dipilih');
    }

    // Semua-atau-tidak-sama-sekali: satu kedatangan ditolak = seluruh penghapusan massal batal.
    try {
        DB::transaction(function () use ($ids) {
            foreach ($ids as $id) {
                $kedatangan = KedatanganBahan::with('detail')->where('id_kedatangan', $id)->first();
                if ($kedatangan) {
                    $this->hapusKedatangan($kedatangan, 'Pembatalan kedatangan bahan (hapus massal) - stok dikembalikan');
                }
            }
        });
    } catch (\RuntimeException $e) {
        return back()->with('error', \App\Support\PesanError::untuk($e));
    }

    return redirect()->route('kedatangan-bahan.index')->with('success', 'Data kedatangan terpilih berhasil dihapus.');
}
    public function update(Request $request, $id)
{
    // Aturan isi sama dengan form tambah (store): qty yang tidak sah tidak boleh sampai ke perhitungan selisih stok.
    $request->validate([
        'no_surat_jalan' => 'required|max:255',
        'tgl_datang'     => ['required', 'date', new \App\Rules\TanggalTransaksi],
        'id_supplier'    => 'required|exists:supplier,id_supplier',
        'id_gudang'      => 'nullable|exists:gudang,id_gudang',
        'id_bahan'       => 'required|array|min:1',
        'id_bahan.*'     => 'required|exists:bahan_baku,id_bahan',
        'qty'            => 'required|array|size:' . count((array) $request->input('id_bahan', [])),
        'qty.*'          => 'required|numeric|min:0.01',
    ], [
        'qty.size' => 'Jumlah baris qty harus sama dengan jumlah bahan.',
    ]);

    try {
        $statusBaru = DB::transaction(function () use ($request, $id) {
            // Baris kedatangan dikunci lebih dulu, baru detail lama dan faktur dibaca: dua edit bersamaan antre (tidak
            // menghitung selisih dari detail lama yang sama dua kali), dan faktur tidak bisa dibuat di sela pengecekan
            // (PembelianBahanController::store mengunci baris yang sama).
            $kedatangan = KedatanganBahan::whereKey($id)->lockForUpdate()->firstOrFail();
            $kedatangan->load('detail');

            // Faktur, hutang, dan persediaan mengacu ke qty kedatangan ini: mengubahnya diam-diam membuat ketiganya tidak cocok.
            // Faktur yang sudah dibatalkan (jurnalnya dibalik) tidak lagi mengacu ke qty ini, jadi tidak menahan koreksi.
            $faktur = PembelianBahan::where('id_kedatangan', $kedatangan->id_kedatangan)->first();
            if ($faktur) {
                throw new \RuntimeException("Kedatangan {$kedatangan->no_surat_jalan} sudah difakturkan ({$faktur->no_faktur}), jadi tidak bisa diubah. Bila qty-nya salah, batalkan fakturnya dulu lewat Pembelian Bahan (perlu persetujuan).");
            }

            // Form edit tidak mengirim status: status lama dipertahankan. Sebelumnya jatuh ke 'Draft', sehingga setiap edit
            // kedatangan yang sudah divalidasi diam-diam menarik seluruh stoknya kembali.
            $statusLama = $kedatangan->status;
            $statusBaru = in_array($request->input('status'), ['Draft', 'Selesai'], true) ? $request->input('status') : $statusLama;

            // Stok bergeser sebesar selisih per bahan (qty baru bila Selesai - qty lama bila Selesai), satu baris kartu
            // per bahan, dan ditolak bila hasilnya minus (bahan sudah terpakai).
            $selisih = [];
            if ($statusLama === 'Selesai') {
                foreach ($kedatangan->detail as $old) {
                    $selisih[$old->id_bahan] = ($selisih[$old->id_bahan] ?? 0) - (float) $old->qty;
                }
            }
            if ($statusBaru === 'Selesai') {
                foreach ($request->id_bahan as $key => $idBahan) {
                    $selisih[$idBahan] = ($selisih[$idBahan] ?? 0) + (float) $request->qty[$key];
                }
            }

            $kedatangan->update([
                'no_surat_jalan' => $request->no_surat_jalan,
                'tgl_datang'     => $request->tgl_datang,
                'id_supplier'    => $request->id_supplier,
                'id_gudang'      => $request->id_gudang ?? 1,
                'status'         => $statusBaru,
                'keterangan'     => $request->keterangan,
                'id_platform'       => $request->filled('id_platform') ? (int) $request->id_platform : null,
                'nama_toko_online'  => $request->filled('id_platform') ? $request->nama_toko_online : null,
                'no_pesanan_online' => $request->filled('id_platform') ? $request->no_pesanan_online : null,
                'no_resi'           => $request->filled('id_platform') ? $request->no_resi : null,
            ]);

            $kedatangan->detail()->delete();
            foreach ($request->id_bahan as $key => $idBahan) {
                KedatanganBahanDetail::create([
                    'id_kedatangan' => $kedatangan->id_kedatangan,
                    'id_bahan'      => $idBahan,
                    'qty'           => $request->qty[$key],
                    'satuan'        => $request->satuan[$key] ?? '',
                    'status'        => 'OK',
                ]);
            }

            foreach ($selisih as $idBahan => $delta) {
                if (abs($delta) < 0.0001) {
                    continue;
                }
                $bahan = BahanBaku::where('id_bahan', $idBahan)->lockForUpdate()->first();
                if (!$bahan) {
                    continue;
                }
                $stokSebelum = (float) $bahan->stok;
                $stokSesudah = $stokSebelum + $delta;
                if ($stokSesudah < -0.0001) {
                    throw new \RuntimeException("Stok {$bahan->nama_bahan} sudah terpakai (sisa " . angka($stokSebelum, 2) . ', berkurang ' . angka(-$delta, 2) . ' bila diubah). Perubahan dibatalkan; koreksi lewat penyesuaian stok bila memang salah catat.');
                }
                $bahan->update(['stok' => $stokSesudah]);

                DB::table('kartu_stok')->insert([
                    'id_bahan'     => $idBahan,
                    'tanggal'      => now(),
                    'tipe'         => $delta > 0 ? 'IN' : 'OUT',
                    'qty'          => abs($delta),
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $stokSesudah,
                    'referensi'    => $request->no_surat_jalan,
                    'keterangan'   => 'Edit kedatangan bahan - selisih qty',
                    'created_by'   => auth()->id(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            \App\Services\PurchaseOrderService::sinkronPenerimaan($kedatangan->id_purchase_order);

            return $statusBaru;
        });
    } catch (\RuntimeException $e) {
        return back()->withInput()->with('error', \App\Support\PesanError::untuk($e));
    }

    if ($statusBaru === 'Selesai') {
        \App\Support\JalurLanjut::set('Berikutnya: catat faktur/tagihan supplier', 'pembelian-bahan.create', 'Buat Faktur Pembelian');
    } else {
        session()->flash('lanjut', ['teks' => 'Berikutnya: cek qty fisik, lalu klik Validasi agar stok bertambah', 'url' => null, 'tombol' => '']);
    }

    return redirect()->route('kedatangan-bahan.index')
        ->with('success', 'Data berhasil diperbarui.');
}
public function ajaxBahan(Request $request)
{
    $search = $request->search;

    // status='aktif' konsisten dengan 2 tempat lain di controller ini
    // (create/edit) yang sudah memfilternya - endpoint search ini yang
    // sebelumnya kelewatan, jadi bahan nonaktif masih bisa dipilih lewat
    // pencarian meski tidak muncul di daftar awal.
    $bahans = BahanBaku::query()
        ->where('status', 'aktif')
        ->when($search, function ($q) use ($search) {
            $q->where(function ($qq) use ($search) {
                $qq->where('nama_bahan', 'like', "%{$search}%")
                   ->orWhere('kode_bahan', 'like', "%{$search}%");
            });
        })
        ->orderBy('nama_bahan')
        ->paginate(20);

    return response()->json($bahans);
}
    public function destroy($id)
{
    $kedatangan = KedatanganBahan::with('detail')->findOrFail($id);

    try {
        DB::transaction(fn () => $this->hapusKedatangan($kedatangan, 'Kedatangan bahan dihapus - stok dikembalikan'));
    } catch (\RuntimeException $e) {
        return back()->with('error', \App\Support\PesanError::untuk($e));
    }

    return redirect()->route('kedatangan-bahan.index')
        ->with('success','Data kedatangan berhasil dihapus.');
}

    /**
     * Satu-satunya jalur hapus kedatangan (satuan dan massal). Wajib dipanggil di dalam transaksi.
     * - Kedatangan yang punya faktur (aktif maupun yang sudah dibatalkan) tidak boleh dihapus: faktur dibatalkan lewat
     *   approval Pembelian Bahan (jurnal hutang & persediaan dibalik di sana). Sebelumnya hapus massal ikut menghapus
     *   fakturnya diam-diam tanpa membalik jurnal.
     * - Kedatangan yang sudah divalidasi mengembalikan stok; bila stoknya sudah terpakai sehingga akan minus, ditolak.
     */
    private function hapusKedatangan(KedatanganBahan $kedatangan, string $keterangan): void
    {
        $faktur = PembelianBahan::withTrashed()->where('id_kedatangan', $kedatangan->id_kedatangan)->first();
        if ($faktur) {
            throw new \RuntimeException($faktur->trashed()
                ? "Kedatangan {$kedatangan->no_surat_jalan} punya riwayat faktur {$faktur->no_faktur} (sudah dibatalkan), jadi disimpan sebagai jejak dan tidak bisa dihapus."
                : "Kedatangan {$kedatangan->no_surat_jalan} sudah difakturkan ({$faktur->no_faktur}). Batalkan fakturnya dulu lewat Pembelian Bahan (perlu persetujuan), baru kedatangannya bisa dihapus.");
        }

        if ($kedatangan->status == 'Selesai') {
            foreach ($kedatangan->detail as $d) {
                $bahan = BahanBaku::where('id_bahan', $d->id_bahan)->lockForUpdate()->first();
                if (!$bahan) {
                    continue;
                }

                $stokSebelum = (float) $bahan->stok;
                $stokSesudah = $stokSebelum - (float) $d->qty;
                if ($stokSesudah < -0.0001) {
                    throw new \RuntimeException("Stok {$bahan->nama_bahan} dari kedatangan {$kedatangan->no_surat_jalan} sudah terpakai (sisa " . angka($stokSebelum, 2) . ', perlu ' . angka((float) $d->qty, 2) . '). Kedatangan tidak bisa dihapus; koreksi lewat penyesuaian stok bila memang salah catat.');
                }
                $bahan->update(['stok' => $stokSesudah]);

                DB::table('kartu_stok')->insert([
                    'id_bahan'     => $d->id_bahan,
                    'tanggal'      => now(),
                    'tipe'         => 'OUT',
                    'qty'          => $d->qty,
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $stokSesudah,
                    'referensi'    => $kedatangan->no_surat_jalan,
                    'keterangan'   => $keterangan,
                    'created_by'   => auth()->id(),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }

        $idPo = $kedatangan->id_purchase_order;
        $kedatangan->detail()->delete();
        $kedatangan->delete();
        \App\Services\PurchaseOrderService::sinkronPenerimaan($idPo);
    }
}
