<?php

namespace App\Http\Controllers\Jersey;

use App\Http\Controllers\Controller;
use App\Models\JerseyCategory;
use App\Models\JerseyCollarType;
use App\Models\JerseyDesigner;
use App\Models\JerseyMaterial;
use App\Models\JerseyOrder;
use App\Models\JerseyOrderLine;
use App\Models\JerseyProductType;
use App\Models\JerseySize;
use App\Models\JerseySleeveType;
use App\Models\Konsumen;
use App\Services\JerseyMaterialEstimationService;
use App\Services\JerseyOrderPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JerseyOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = JerseyOrder::with(['konsumen', 'lines'])->orderByDesc('id');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('order_number', 'like', "%$s%")
                    ->orWhere('customer_name', 'like', "%$s%")
                    ->orWhere('team_name', 'like', "%$s%");
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->get('bayar') === 'belum') {
            $query->whereIn('status_pembayaran', ['Belum Bayar', 'DP']);
        }

        $orders = $query->paginate(20)->withQueryString();

        // Ringkasan cepat untuk chip filter (tidak terpengaruh filter aktif)
        // supaya operator bisa lihat jumlah per status sekilas.
        $statusCounts = JerseyOrder::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $unpaidCount = JerseyOrder::whereIn('status_pembayaran', ['Belum Bayar', 'DP'])->count();

        return view('jersey.orders.index', compact('orders', 'statusCounts', 'unpaidCount'));
    }

    public function create()
    {
        return view('jersey.orders.create', $this->formData());
    }

    private function formData(): array
    {
        $desainerList = \App\Services\DesainKomisiService::daftarDesainer();
        $desainerTerkunci = \App\Services\DesainKomisiService::desainerTerkunci();
        $desainerTerkunciNama = $desainerTerkunci
            ? optional($desainerList->firstWhere('id_user', $desainerTerkunci))->nama
            : null;
        return [
            'konsumenList' => Konsumen::orderBy('nama_konsumen')->get(),
            'designers' => JerseyDesigner::where('is_active', true)->orderBy('name')->get(),
            'productTypes' => JerseyProductType::where('is_active', true)->orderBy('name')->get(),
            'categories' => JerseyCategory::where('is_active', true)->orderBy('name')->get(),
            'materials' => JerseyMaterial::where('is_active', true)->orderBy('name')->get(),
            'sizes' => JerseySize::where('is_active', true)->orderBy('sort_order')->get(),
            'sleeveTypes' => JerseySleeveType::where('is_active', true)->orderBy('sort_order')->get(),
            'collarTypes' => JerseyCollarType::where('is_active', true)->orderBy('sort_order')->get(),
            'desainerList' => $desainerList,
            'desainerTerkunci' => $desainerTerkunci,
            'desainerTerkunciNama' => $desainerTerkunciNama,
            'desainerDefault' => \App\Services\DesainKomisiService::tentukanDesainer(null),
        ];
    }

    /**
     * Upload thumbnail mockup desain SATU line - dipanggil terpisah dari
     * submit form utama (sama seperti PenjualanController::uploadThumbnail)
     * karena line-nya sendiri belum tentu tersimpan ke database saat user
     * memilih file (order masih dalam proses diisi di form). Dipakai baik
     * dari input file biasa maupun tempel (Ctrl+V) gambar dari clipboard.
     *
     * Format yang diterima lebar (semua gambar umum + dokumen desain pdf,
     * cdr, ai, eps, psd) - lihat App\Support\BerkasDesain untuk alasannya.
     * Ekstensi dicek dari nama file asal, bukan tebakan MIME, karena CDR dan
     * sebagian besar format desain punya MIME generik yang ditolak aturan
     * mimes. Ekstensi asli juga dipertahankan di nama berkas di disk.
     */
    public function uploadThumbnail(Request $request)
    {
        $file = $request->file('thumbnail');

        // Format & ukuran dicek sendiri (bukan aturan 'mimes'), karena aturan
        // itu menebak ekstensi dari MIME - CDR & sebagian besar format desain
        // punya MIME generik (application/octet-stream) yang akan ditolak
        // padahal berkasnya benar. Daftar formatnya satu sumber kebenaran di
        // App\Support\BerkasDesain.
        //
        // Error-nya tetap error validasi Laravel (422 + errors.thumbnail),
        // sama seperti jalur validasi lain di endpoint ini.
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['thumbnail' => $file],
            [
                'thumbnail' => [
                    'required',
                    'file',
                    function ($attribute, $value, $fail) {
                        if (!$value instanceof \Illuminate\Http\UploadedFile) {
                            return;
                        }

                        $ekstensi = \App\Support\BerkasDesain::ekstensi($value->getClientOriginalExtension());
                        if (!\App\Support\BerkasDesain::diizinkan($ekstensi)) {
                            $fail('Format file tidak didukung. Yang boleh: ' . \App\Support\BerkasDesain::daftarFormat() . '.');

                            return;
                        }

                        $maksKb = \App\Support\BerkasDesain::maksKilobyte($ekstensi);
                        if ($value->getSize() > $maksKb * 1024) {
                            $fail('Ukuran file terlalu besar (maksimal ' . number_format($maksKb / 1024, 0, ',', '.') . ' MB).');
                        }
                    },
                ],
            ]
        );

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $ekstensi = \App\Support\BerkasDesain::ekstensi($file->getClientOriginalExtension());

        // Berkas desain yang tidak bisa dirender (CDR, AI, EPS, PSD) disimpan
        // apa adanya sebagai sumber desain; PDF sudah dirender jadi gambar
        // di peramban (pdf.js) sebelum diunggah.
        $path = $file->storeAs(
            'jersey_thumbnails',
            \Illuminate\Support\Str::random(40) . '.' . $ekstensi,
            'public'
        );

        return response()->json([
            'status' => 'success',
            'path' => $path,
            'url' => asset('storage/' . $path),
            'bisa_ditampilkan' => \App\Support\BerkasDesain::bisaDitampilkan($ekstensi),
            'nama_asli' => $file->getClientOriginalName(),
        ]);
    }

    /**
     * Cek kelengkapan tiap baris barang SEBELUM benar-benar disimpan (harga
     * dari tier belum ketemu -> akan tersimpan Rp 0, Bahan belum dipilih)
     * - dipanggil lewat fetch() oleh create.blade.php sesaat sebelum submit
     * form yang sesungguhnya, supaya operator diberi peringatan dulu (warn,
     * bukan block - order tetap bisa disimpan kalau operator yakin) alih-
     * alih diam-diam tersimpan dengan harga Rp 0 tanpa disadari.
     */
    public function cekKelengkapan(Request $request)
    {
        $sizeIdByName = JerseySize::pluck('id', 'name');
        $peringatan = [];

        foreach ($request->input('lines', []) as $i => $lineData) {
            $sumber = $this->sumberQtyBaris($lineData);
            $qty = $sumber['qty'];
            if ($qty <= 0) {
                continue; // baris kosong - sama seperti store(), dilewati saja
            }

            $nama = trim($lineData['nama_barang'] ?? '') ?: ('Barang #' . ($i + 1));

            // Audit kelengkapan: wajib server-side di store/updateLines/susulan.
            // cekKelengkapan tetap melaporkannya untuk UX (marker di form),
            // tapi sumber kebenaran adalah validasi, bukan warning ini.
            if (empty($lineData['jersey_product_type_id'])) {
                $peringatan[] = "\"{$nama}\": Tipe Produk belum dipilih.";
            }
            if (empty($lineData['jersey_category_id'])) {
                $peringatan[] = "\"{$nama}\": Kategori belum dipilih.";
            }
            if (empty($lineData['jersey_material_id'])) {
                $peringatan[] = "\"{$nama}\": Bahan belum dipilih.";
            }

            $tier = JerseyOrderPricingService::cariTierHarga(
                !empty($lineData['jersey_product_type_id']) ? (int) $lineData['jersey_product_type_id'] : null,
                !empty($lineData['jersey_category_id']) ? (int) $lineData['jersey_category_id'] : null,
                !empty($lineData['jersey_material_id']) ? (int) $lineData['jersey_material_id'] : null,
                $qty
            );
            if (!$tier || (float) $tier->price <= 0) {
                $peringatan[] = "\"{$nama}\": belum ada tier harga yang cocok untuk qty {$qty} - harga akan tersimpan Rp 0.";
            }
        }

        return response()->json(['peringatan' => $peringatan]);
    }

    /**
     * Audit Order Jersey - SATU SUMBER KEBENARAN UKURAN: daftar nama ukuran
     * aktif dari master jersey_sizes, dipakai validasi items.*.size di
     * store/updateLines/storeLineSusulan. Cache per-request: validasi array
     * rules dieksekusi ulang untuk setiap baris pemain (N panggilan), tidak
     * boleh berubah jadi N query.
     */
    /**
     * Ukuran yang boleh dipakai: yang aktif di master, ditambah (saat mengelola order yang SUDAH ada) ukuran yang
     * sudah dipakai order itu walau kini dinonaktifkan. Tanpa ini order berjalan tertahan setelah master ukuran
     * dinonaktifkan: Kelola Barang dan barang susulan selalu ditolak.
     */
    private function ukuranDiizinkanQuery()
    {
        $idOrder = request()->attributes->get('jersey.order_id');

        return JerseySize::query()->where(function ($q) use ($idOrder) {
            $q->where('is_active', true);
            if ($idOrder) {
                $baris = DB::table('jersey_order_lines')->where('jersey_order_id', $idOrder)->pluck('id');
                $q->orWhereIn('id', DB::table('jersey_order_size_quantities')->whereIn('jersey_order_line_id', $baris)->pluck('jersey_size_id')->filter())
                    ->orWhereIn('name', DB::table('jersey_order_items')->whereIn('jersey_order_line_id', $baris)->pluck('size')->filter());
            }
        });
    }

    private function ukuranMasterAktif(): \Illuminate\Support\Collection
    {
        // Cache di atribut REQUEST (bukan `static`): `static` hidup selama proses PHP, jadi pada server
        // berumur panjang (Octane/worker) daftar ukuran akan basi setelah Master Ukuran diubah.
        $req = request();
        if (!$req->attributes->has('jersey.ukuran_aktif')) {
            $req->attributes->set('jersey.ukuran_aktif', $this->ukuranDiizinkanQuery()->pluck('name'));
        }

        return $req->attributes->get('jersey.ukuran_aktif');
    }

    /**
     * Audit: peta NAMA ukuran master aktif -> ID, dipakai mengisi FK
     * jersey_order_items.jersey_size_id (pendamping snapshot teks size).
     * Cache per-request sama seperti ukuranMasterAktif().
     */
    private function sizeIdByName(): \Illuminate\Support\Collection
    {
        $req = request();
        if (!$req->attributes->has('jersey.ukuran_id')) {
            $req->attributes->set('jersey.ukuran_id', $this->ukuranDiizinkanQuery()->pluck('id', 'name'));
        }

        return $req->attributes->get('jersey.ukuran_id');
    }

    /**
     * Audit: KEY size_quantities harus ID ukuran yang aktif di master -
     * ID palsu/di-nonaktifkan ditolak di sini dengan pesan jelas, bukan
     * gagal foreign key di tengah transaksi. Dipanggil SEBELUM transaksi
     * dibuka supaya ValidationException tampil sebagai error validasi
     * standar Laravel (redirect back + pesan), bukan flash generik.
     */
    private function validasiKeySizeQuantities($sizeQuantities, int $lineIndex): void
    {
        $ids = collect($sizeQuantities ?? [])->filter(fn ($q) => (int) $q > 0)->keys();
        if ($ids->isEmpty()) {
            return;
        }

        $sah = $this->ukuranDiizinkanQuery()->whereIn('id', $ids)->pluck('id');
        $tidakSah = $ids->diff($sah);
        if ($tidakSah->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                "lines.{$lineIndex}.size_quantities" => "Baris " . ($lineIndex + 1) . ": ukuran ID " . $tidakSah->implode(', ') . " tidak terdaftar (atau nonaktif) di Master Ukuran Jersey. Muat ulang halaman setelah master diperbarui.",
            ]);
        }
    }

    /**
     * Sumber qty satu baris barang, DITENTUKAN oleh mode-nya.
     *
     * Bug yang diperbaiki di sini: dulu qty dihitung
     * `count($items) + array_sum($size_quantities)` untuk SEMUA mode.
     *
     * Di mode "Daftar Pemain", form Kelola Barang (edit-lines.blade.php)
     * merender input size_quantities dengan nilai lama dari database - karena
     * _line-card-fields.blade.php selalu mengisi $sizeQtyMap tanpa melihat
     * mode. Wrapper-nya memang disembunyikan, tapi "tersembunyi" bukan berarti
     * "tidak terkirim", jadi 35 pemain + size_quantities 35 = 70.
     *
     * Client sebenarnya sudah mengosongkan qty per ukuran saat mode diganti
     * ke Daftar Pemain (toggleLineType), tapi saat halaman dibuka ulang dalam
     * mode Daftar Pemain tidak ada pergantian mode - tidak ada yang mengosongkan
     * apa pun. Server tidak boleh bergantung pada sesuatu yang hanya terjadi
     * saat ada klik.
     *
     * Aturan per mode:
     *   quantity_only : qty per ukuran saja
     *   player_list   : daftar pemain saja
     *   mixed         : dua-duanya, memang dua sumber yang berbeda
     *
     * Dipakai cekKelengkapan(), validasiQtyTerisi(), dan hitungHargaBaris()
     * supaya ketiganya tidak bisa berbeda kesimpulan lagi.
     */
    private function sumberQtyBaris(array $lineData): array
    {
        $mode = (string) ($lineData['order_type'] ?? 'quantity_only');

        $items = array_values(array_filter(
            $lineData['items'] ?? [],
            fn ($it) => trim((string) ($it['player_name'] ?? '')) !== ''
        ));
        $sizeQty = array_filter($lineData['size_quantities'] ?? [], fn ($q) => (int) $q > 0);

        $pakaiItems = in_array($mode, ['player_list', 'mixed'], true);
        $pakaiSizeQty = in_array($mode, ['quantity_only', 'mixed'], true);

        $itemsDipakai = $pakaiItems ? $items : [];
        $sizeQtyDipakai = $pakaiSizeQty ? $sizeQty : [];

        return [
            'mode' => $mode,
            'items' => $itemsDipakai,
            'sizeQuantities' => $sizeQtyDipakai,
            'qty' => count($itemsDipakai) + array_sum(array_map('intval', $sizeQtyDipakai)),
        ];
    }

    /**
     * Audit kelengkapan: WAJIB minimal satu sumber qty terisi per baris
     * berisi (pemain berukuran atau qty per ukuran > 0). Dipakai ketiga
     * jalur simpan (store/updateLines/susulan) sebelum transaksi dibuka.
     */
    private function validasiQtyTerisi(array $lines): void
    {
        foreach ($lines as $i => $lineData) {
            $sumber = $this->sumberQtyBaris($lineData);
            $items = $sumber['items'];
            $sizeQuantitiesMentah = $sumber['sizeQuantities'];
            $qty = $sumber['qty'];

            if ($qty <= 0) {
                continue; // baris kosong - dilewati (konsisten dengan hitungHargaBaris)
            }

            if (empty($sizeQuantitiesMentah) && empty($items)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "lines.{$i}.size_quantities" => 'Baris ' . ($i + 1) . ': lengkapi data pesanan lebih dulu - pilih minimal satu ukuran dengan qty, atau isi daftar pemain lengkap dengan ukurannya.',
                ]);
            }
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_date' => ['required', 'date', new \App\Rules\TanggalTransaksi],
            'id_konsumen' => 'required|exists:konsumen,id_konsumen',
            'team_name' => 'nullable|string|max:255',
            'deadline' => 'nullable|date',
            'jersey_designer_id' => 'nullable|exists:jersey_designers,id',
            'id_desainer' => 'nullable|exists:users,id_user',
            'file_siap_cetak' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
            'discount' => 'nullable|numeric|min:0',
            'additional_charge' => 'nullable|numeric|min:0',
            'deposit' => 'nullable|numeric|min:0',

            'lines' => 'required|array|min:1',
            'lines.*.nama_barang' => 'required|string|max:255',
            // Audit kelengkapan (dropdown wajib terisi): Tipe Produk, Kategori,
            // dan Bahan menentukan harga tier & estimasi kain - biarkan kosong
            // berarti order tersimpan tanpa harga/estimasi. Server-side WAJIB
            // menolak, bukan cuma warning client-side.
            'lines.*.jersey_product_type_id' => 'required|exists:jersey_product_types,id',
            'lines.*.jersey_category_id' => 'required|exists:jersey_categories,id',
            'lines.*.jersey_material_id' => 'required|exists:jersey_materials,id',
            // Mode "Daftar Pemain" (order_type = player_list) menyembunyikan
            // dropdown lengan/kerah default di level baris - pemakaiannya jadi
            // per pemain (lihat rules items.* di bawah). Kalau baris ini tetap
            // `required` polos, order mode Daftar Pemain tidak akan pernah bisa
            // disimpan karena user tidak punya UI untuk mengisinya.
            // Kolom di database sudah nullable dan diisi di baris penyimpan
            // memakai `!empty(...) ? (int) : null`, jadi null aman.
            'lines.*.jersey_sleeve_type_id' => ['nullable', 'exists:jersey_sleeve_types,id'],
            'lines.*.jersey_collar_type_id' => ['nullable', 'exists:jersey_collar_types,id'],
            'lines.*.keterangan' => 'nullable|string|max:2000',
            'lines.*.thumbnail' => 'required|string|max:255',
            'lines.*.order_type' => 'required|in:quantity_only,player_list,mixed',

            'lines.*.items' => 'nullable|array',
            'lines.*.items.*.player_name' => 'required|string|max:255',
            'lines.*.items.*.player_number' => 'nullable|string|max:20',
            // Audit Order Jersey: ukuran diambil dari MASTER jersey_sizes
            // (aktif) - BUKAN whitelist hard-code. Dulu 'in:S,M,L,XL,...'
            // membuat ukuran baru yang ditambahkan lewat master ditolak di
            // sini ("The selected lines.0.items.9.size is invalid") padahal
            // dropdown form (dari master yang sama) sudah menawarkannya.
            // Rule closure membaca master dengan cache static per-request
            // supaya N baris pemain tidak memicu N query.
            // Audit kelengkapan: pemain TANPA ukuran merusak rekap ukuran,
            // harga surcharge, dan estimasi kain - ukuran WAJIB untuk setiap
            // baris pemain (required, bukan nullable), tetap tervalidasi ke
            // master jersey_sizes.
            'lines.*.items.*.size' => ['required', 'string', 'max:50', function ($attribute, $value, $fail) {
                if ($value !== null && !$this->ukuranMasterAktif()->contains($value)) {
                    $fail("Ukuran \"{$value}\" tidak terdaftar (atau nonaktif) di Master Ukuran Jersey. Muat ulang halaman setelah master diperbarui, atau daftarkan ukurannya dulu.");
                }
            }],
            'lines.*.items.*.gender' => 'nullable|in:L,P',
            // Audit kelengkapan: lengan/kerah pemain WAJIB dipilih (pilihan
            // "Tanpa Lengan"/"Tanpa Kerah" tersedia di master untuk barang
            // yang memang tidak memakainya - bukan dropdown dibiarkan kosong).
            'lines.*.items.*.jersey_sleeve_type_id' => 'nullable|exists:jersey_sleeve_types,id',
            'lines.*.items.*.jersey_collar_type_id' => 'nullable|exists:jersey_collar_types,id',

            'lines.*.size_quantities' => 'nullable|array',
            // Audit: KEY size_quantities adalah ID ukuran master (dari form
            // qty-per-ukuran). Key palsu lolos validasi ini lalu gagal FK
            // dengan error kasar saat disimpan - dicek eksplisit di bawah.
            'lines.*.size_quantities.*' => 'nullable|integer|min:0',
        ], [
            'lines.*.thumbnail.required' => 'Thumbnail desain wajib diisi untuk setiap barang (acuan warna & bentuk produksi).',
        ]);

        $this->validasiSleeveCollar($request);

        // Audit: size_quantities dikunci ke master SEBELUM transaksi dibuka -
        // key yang bukan ID ukuran master aktif ditolak dengan pesan validasi
        // standar (dulu lolos validasi lalu gagal FK di tengah transaksi).
        foreach ($data['lines'] as $i => $lineData) {
            $this->validasiKeySizeQuantities($lineData['size_quantities'] ?? [], $i);
        }
        $this->validasiQtyTerisi($data['lines']);

        DB::beginTransaction();
        try {
            $konsumen = Konsumen::findOrFail($data['id_konsumen']);

            $order = JerseyOrder::create([
                'order_number' => JerseyOrder::generateOrderNumber(now()),
                'order_date' => $data['order_date'],
                'id_konsumen' => $konsumen->id_konsumen,
                'customer_name' => $konsumen->nama_konsumen,
                'customer_phone' => $konsumen->telepon,
                'team_name' => $data['team_name'] ?? null,
                'deadline' => $data['deadline'] ?? null,
                'status' => 'baru',
                'jersey_designer_id' => $data['jersey_designer_id'] ?? null,
                'designer_name' => !empty($data['jersey_designer_id'])
                    ? JerseyDesigner::find($data['jersey_designer_id'])?->name
                    : null,
                'id_desainer' => !empty($data['file_siap_cetak'])
                    ? null
                    : \App\Services\DesainKomisiService::kunciDesainer(
                        isset($data['id_desainer']) ? (int) $data['id_desainer'] : null
                    ),
                'file_siap_cetak' => !empty($data['file_siap_cetak']),
                'notes' => $data['notes'] ?? null,
                'total' => 0,
                // Diskon dan biaya tambahan adalah keputusan harga: role tanpa hak harga tidak bisa mengisinya.
                'discount' => \App\Support\AksesHarga::bolehLihat() ? ($data['discount'] ?? 0) : 0,
                'additional_charge' => \App\Support\AksesHarga::bolehLihat() ? ($data['additional_charge'] ?? 0) : 0,
                'deposit' => 0,
                'status_pembayaran' => 'Belum Bayar',
                'created_by' => Auth::id(),
            ]);

            $adaLineValid = false;
            foreach ($data['lines'] as $lineData) {
                if ($this->simpanBarisOrder($order, $lineData)) {
                    $adaLineValid = true;
                }
            }

            if (!$adaLineValid) {
                DB::rollBack();
                return back()->withInput()->with('error', 'Order harus punya minimal 1 jenis barang dengan pemain/qty terisi.');
            }

            $order->recalculateTotal();

            // DP awal saat order dibuat dicatat sebagai baris pertama di
            // ledger Keuangan Jersey (bukan cuma angka manual di kolom
            // deposit seperti sebelumnya) - supaya konsisten tercatat lewat
            // satu jalur yang sama dengan pembayaran susulan (cicilan,
            // pelunasan) yang ditambahkan lewat modul Keuangan Jersey.
            $depositAwal = (float) ($data['deposit'] ?? 0);
            if ($depositAwal > 0) {
                // Audit H-1b: DP tidak boleh melebihi total order (sebelumnya
                // tak divalidasi) dan WAJIB dijurnal + dicermin ke Kas Kecil
                // lewat jalur yang sama dengan pembayaran susulan. Sebelumnya
                // DP awal hanya masuk ledger tanpa jurnal -> piutang terlalu
                // besar & kas terlalu kecil di akuntansi.
                // Uang DP yang lebih besar dari total order: yang dicatat sebesar total, selebihnya kembalian.
                $totalOrder = (float) $order->fresh()->total;
                if ($depositAwal - $totalOrder > 0.004) {
                    $kembalianDp = round($depositAwal - $totalOrder, 2);
                    $depositAwal = $totalOrder;
                }

                $dp = $order->payments()->create([
                    'tanggal_bayar' => $data['order_date'],
                    'jumlah' => $depositAwal,
                    'metode_bayar' => 'Tunai',
                    'keterangan' => 'DP awal saat order dibuat.',
                    'uang_diterima' => round($depositAwal + ($kembalianDp ?? 0), 2),
                    'kembalian' => $kembalianDp ?? 0,
                    'created_by' => Auth::id(),
                ]);
                \App\Services\JerseyAccountingService::catatPembayaran($order, $dp);
                $order->recalculatePembayaran();
            }

            DB::commit();

            return redirect()->route('jersey.orders.show', $order->id)
                ->with('success', 'Order jersey ' . $order->order_number . ' berhasil dibuat.'
                    . (($kembalianDp ?? 0) > 0 ? ' Kembalian untuk pelanggan: Rp ' . number_format($kembalianDp, 0, ',', '.') . '.' : ''));
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', \App\Support\PesanError::untuk($e, 'Gagal menyimpan order'));
        }
    }

    /**
     * Bangun & simpan SATU baris barang (qty per ukuran, harga dari tier +
     * tambahan ukuran, estimasi bahan) - logika inti yang sama dipakai
     * store() (order baru) maupun updateLines() (edit barang di order yang
     * sudah ada), supaya kedua alur selalu menghitung harga & estimasi
     * dengan cara yang identik. Baris kosong (qty 0) dilewati, kembalikan
     * false supaya pemanggil tahu baris itu tidak jadi dibuat.
     */
    /**
     * Inti kalkulasi harga & pengelompokan qty per ukuran/tipe untuk SATU
     * baris barang, dari array $lineData mentah (bentuk yang sama persis
     * dengan request["lines"][idx] saat submit form) - dipakai BERSAMA oleh
     * simpanBarisOrder() (untuk benar-benar menyimpan) dan previewHarga()
     * (untuk kalkulasi live di form TANPA menyimpan apa pun), supaya kedua
     * jalur itu tidak pernah punya rumus yang beda sendiri-sendiri.
     * Return null kalau baris kosong (belum diisi qty/pemain sama sekali).
     */
    /** Cache master per instance controller (= per request), bukan static, agar tidak basi antar-request/tes. */
    private array $cacheMaster = [];

    private function hitungHargaBaris(array $lineData): ?array
    {
        $sizeIdByName = $this->cacheMaster['sizeIdByName'] ??= JerseySize::pluck('id', 'name');
        $surchargeBySizeId = $this->cacheMaster['surchargeBySizeId'] ??= JerseySize::pluck('price_surcharge', 'id');
        $percentBySizeId = $this->cacheMaster['percentBySizeId'] ??= JerseySize::pluck('price_percent', 'id');
        $sleeveNameById = $this->cacheMaster['sleeveNameById'] ??= JerseySleeveType::pluck('name', 'id');
        $collarNameById = $this->cacheMaster['collarNameById'] ??= JerseyCollarType::pluck('name', 'id');
        $sleeveSurchargeById = $this->cacheMaster['sleeveSurchargeById'] ??= JerseySleeveType::pluck('price_surcharge', 'id');
        $collarSurchargeById = $this->cacheMaster['collarSurchargeById'] ??= JerseyCollarType::pluck('price_surcharge', 'id');

        // Kunci 'size' dipaksa ada di sini, bukan hanya dijaga di tempat
        // dipakai. Alasannya bukan kerapian: sumberQtyBaris() hanya
        // memeriksa player_name, jadi baris pemain yang namanya sudah
        // terisi tapi ukurannya masih kosong tetap lolos. Dua loop di
        // bawah membaca $item['size'] secara langsung, dan tanpa
        // penjaga di sini satu baris yang belum lengkap membuat SELURUH
        // estimasi harga 500 (Undefined array key "size") - bukan cuma
        // baris itu yang gagal, tapi seluruh preview harga untuk order
        // ini. Gejalanya mudah terjadi karena operator mengetik nama dulu
        // dan ukuran menyusul.
        //
        // Catatan: sumberQtyBaris() juga ikut membuang sumber qty yang tidak
        // relevan untuk modenya, jadi $items/$sizeQuantitiesMentah di bawah
        // sudah sesuai mode - mode Daftar Pemain tidak lagi ikut menghitung
        // size_quantities sisa (bug qty 35 -> 70).
        $sumber = $this->sumberQtyBaris($lineData);
        $items = array_map(function ($i) {
            $i['size'] = trim((string) ($i['size'] ?? ''));

            return $i;
        }, $sumber['items']);
        $sizeQuantitiesMentah = $sumber['sizeQuantities'];

        $qty = $sumber['qty'];
        if ($qty <= 0) {
            return null; // baris kosong (line ditambahkan tapi tidak diisi) - dilewati, bukan error
        }

        // Qty per ukuran DIGABUNG dari kedua sumber (bukan salah satu saja)
        // - untuk mode "mixed" qty datang dari size_quantities DAN dari
        // daftar pemain sekaligus, keduanya harus masuk hitungan estimasi
        // bahan & rekap ukuran, bukan cuma salah satu.
        $qtyPerSizeId = [];
        foreach ($sizeQuantitiesMentah as $sizeId => $q) {
            $qtyPerSizeId[$sizeId] = ($qtyPerSizeId[$sizeId] ?? 0) + (int) $q;
        }
        foreach ($items as $item) {
            $sizeId = $sizeIdByName[$item['size']] ?? null;
            if ($sizeId) {
                $qtyPerSizeId[$sizeId] = ($qtyPerSizeId[$sizeId] ?? 0) + 1;
            }
        }

        // Qty per ukuran x tipe Lengan/Kerah - dipakai untuk estimasi bahan
        // yang lebih akurat (lengan panjang/kerah tertentu makan bahan
        // lebih banyak, tidak lagi disamaratakan). Bagian size_quantities
        // (tanpa data per pemain) memakai pilihan DEFAULT di level barang;
        // bagian daftar pemain memakai pilihan masing-masing pemain sendiri
        // - keduanya digabung per ukuran, sama seperti penggabungan qty.
        $lineSleeveTypeId = !empty($lineData['jersey_sleeve_type_id']) ? (int) $lineData['jersey_sleeve_type_id'] : null;
        $lineCollarTypeId = !empty($lineData['jersey_collar_type_id']) ? (int) $lineData['jersey_collar_type_id'] : null;

        $qtyPerSizeSleeveType = [];
        $qtyPerSizeCollarType = [];
        foreach ($sizeQuantitiesMentah as $sizeId => $q) {
            if ($lineSleeveTypeId) {
                $qtyPerSizeSleeveType[$sizeId][$lineSleeveTypeId] = ($qtyPerSizeSleeveType[$sizeId][$lineSleeveTypeId] ?? 0) + (int) $q;
            }
            if ($lineCollarTypeId) {
                $qtyPerSizeCollarType[$sizeId][$lineCollarTypeId] = ($qtyPerSizeCollarType[$sizeId][$lineCollarTypeId] ?? 0) + (int) $q;
            }
        }
        foreach ($items as $item) {
            $sizeId = $sizeIdByName[$item['size']] ?? null;
            if (!$sizeId) {
                continue;
            }
            if (!empty($item['jersey_sleeve_type_id'])) {
                $stId = (int) $item['jersey_sleeve_type_id'];
                $qtyPerSizeSleeveType[$sizeId][$stId] = ($qtyPerSizeSleeveType[$sizeId][$stId] ?? 0) + 1;
            }
            if (!empty($item['jersey_collar_type_id'])) {
                $ctId = (int) $item['jersey_collar_type_id'];
                $qtyPerSizeCollarType[$sizeId][$ctId] = ($qtyPerSizeCollarType[$sizeId][$ctId] ?? 0) + 1;
            }
        }

        $material = !empty($lineData['jersey_material_id']) ? JerseyMaterial::find($lineData['jersey_material_id']) : null;

        $tier = JerseyOrderPricingService::cariTierHarga(
            $lineData['jersey_product_type_id'] ?? null,
            $lineData['jersey_category_id'] ?? null,
            $material?->id,
            $qty
        );
        $hargaDewasa = (float) ($tier->price ?? 0);

        // Ukuran ANAK memakai tier harga kelompok 'anak' bila ada (dicari dengan qty total barang yang sama),
        // kalau tidak ada tier anak jatuh ke harga dewasa (selisih persen per ukuran tetap berlaku).
        $tierAnak = JerseyOrderPricingService::cariTierHarga(
            $lineData['jersey_product_type_id'] ?? null,
            $lineData['jersey_category_id'] ?? null,
            $material?->id,
            $qty,
            'anak'
        );
        $hargaAnak = $tierAnak ? (float) $tierAnak->price : $hargaDewasa;
        $kelompokBySizeId = $this->cacheMaster['kelompokBySizeId'] ??= JerseySize::pluck('kelompok', 'id');
        $hargaSatuanPerSize = [];
        $totalDasar = 0.0;
        foreach ($qtyPerSizeId as $sizeId => $qtyUkuran) {
            $hargaSatuanPerSize[$sizeId] = ($kelompokBySizeId[$sizeId] ?? 'dewasa') === 'anak' ? $hargaAnak : $hargaDewasa;
            $totalDasar += $hargaSatuanPerSize[$sizeId] * $qtyUkuran;
        }
        // Qty dari daftar pemain dengan ukuran tak dikenal (tidak ada di qtyPerSizeId) tetap dihargai dewasa.
        $qtyTerpetakan = array_sum($qtyPerSizeId);
        if ($qty > $qtyTerpetakan) {
            $totalDasar += $hargaDewasa * ($qty - $qtyTerpetakan);
        }
        // Jangan biarkan pemain TANPA ukuran dihargai senyap: dihitung &
        // dilaporkan, supaya preview bisa menampilkannya secara jujur
        // (dihitung harga dasar dewasa sampai ukurannya dipilih).
        $pemainTanpaUkuran = collect($items)
            ->filter(fn ($i) => ($sizeIdByName[$i['size'] ?? ''] ?? null) === null)
            ->count();
        // Harga satuan yang disimpan/ditampilkan = rata-rata tertimbang (sama dengan harga dewasa bila tak ada ukuran anak).
        $hargaSatuan = $qty > 0 ? round($totalDasar / $qty, 2) : 0.0;

        // Tambahan harga per ukuran (mis. 2XL-5XL lebih mahal, diatur di
        // Master Data > Ukuran) - dijumlahkan per ukuran x qty ukuran itu
        // di baris ini, bukan flat per qty, karena tidak semua ukuran dalam
        // satu baris barang tentu punya surcharge yang sama.
        $surchargeTotal = 0.0;
        $surchargePerSize = []; // rincian per ukuran - hanya untuk tampilan, tidak memengaruhi total
        foreach ($qtyPerSizeId as $sizeId => $qtyUkuran) {
            // Selisih per ukuran = nominal tetap + persen dari harga tier (persen negatif = diskon, mis. ukuran anak).
            $selisihPerPcs = (float) ($surchargeBySizeId[$sizeId] ?? 0) + ($hargaSatuanPerSize[$sizeId] ?? $hargaSatuan) * (float) ($percentBySizeId[$sizeId] ?? 0) / 100;
            $surchargePerSize[$sizeId] = $selisihPerPcs * $qtyUkuran;
            $surchargeTotal += $selisihPerPcs * $qtyUkuran;
        }

        // Tambahan harga per Tipe Lengan/Kerah (mis. lengan panjang lebih
        // mahal, diatur di Master Data > Tipe Lengan/Tipe Kerah) - dihitung
        // dari struktur ukuran x tipe yang sama dengan estimasi bahan di
        // bawah, supaya konsisten dengan pilihan lengan/kerah riil per
        // pemain (bukan disamaratakan pakai default level barang saja).
        $sleeveCollarSurchargeTotal = 0.0;
        // Rincian lengan/kerah DIREKAP per tipe (bukan per ukuran) - hanya
        // untuk tampilan; jumlahnya tetap sama persis dengan total surcharge.
        $rekapQtyLengan = [];
        $rekapQtyKerah = [];
        foreach ($qtyPerSizeSleeveType as $perType) {
            foreach ($perType as $typeId => $qtyTipe) {
                $sleeveCollarSurchargeTotal += (float) ($sleeveSurchargeById[$typeId] ?? 0) * $qtyTipe;
                $rekapQtyLengan[$typeId] = ($rekapQtyLengan[$typeId] ?? 0) + $qtyTipe;
            }
        }
        foreach ($qtyPerSizeCollarType as $perType) {
            foreach ($perType as $typeId => $qtyTipe) {
                $sleeveCollarSurchargeTotal += (float) ($collarSurchargeById[$typeId] ?? 0) * $qtyTipe;
                $rekapQtyKerah[$typeId] = ($rekapQtyKerah[$typeId] ?? 0) + $qtyTipe;
            }
        }

        // DAFTAR RINCIAN untuk tampilan di form (field "Perkiraan Harga
        // Barang Ini"). Murni diturunkan dari angka yang SUDAH dihitung di
        // atas - tidak menambah/mengurangi apa pun, tidak dipakai saat
        // menyimpan, hanya oleh previewHarga(). Setiap baris = satu
        // komponen harga: dasar per ukuran, selisih ukuran, lengan/kerah.
        $sizeNameById = $this->cacheMaster['sizeNameById'] ??= JerseySize::pluck('name', 'id');
        $rincian = [];
        foreach ($qtyPerSizeId as $sizeId => $qtyUkuran) {
            $rincian[] = [
                'label' => 'Harga dasar ' . ($sizeNameById[$sizeId] ?? ('#' . $sizeId)) . ' × ' . $qtyUkuran,
                'amount' => $hargaSatuanPerSize[$sizeId] * $qtyUkuran,
            ];
        }
        if ($qty > $qtyTerpetakan) {
            $rincian[] = [
                'label' => 'Harga dasar dewasa (ukuran belum dipilih) × ' . ($qty - $qtyTerpetakan),
                'amount' => $hargaDewasa * ($qty - $qtyTerpetakan),
            ];
        }
        foreach ($qtyPerSizeId as $sizeId => $qtyUkuran) {
            if (abs($surchargePerSize[$sizeId] ?? 0.0) < 0.005) {
                continue; // ukuran tanpa selisih harga tidak direkan
            }
            $rincian[] = [
                'label' => 'Selisih ukuran ' . ($sizeNameById[$sizeId] ?? ('#' . $sizeId)) . ' × ' . $qtyUkuran,
                'amount' => $surchargePerSize[$sizeId],
            ];
        }
        foreach ($rekapQtyLengan as $typeId => $qtyTipe) {
            if (abs((float) ($sleeveSurchargeById[$typeId] ?? 0)) < 0.005) {
                continue; // tipe lengan tanpa surcharge tidak direkan (sudah termasuk harga dasar)
            }
            $rincian[] = [
                'label' => 'Lengan ' . ($sleeveNameById[$typeId] ?? ('#' . $typeId)) . ' × ' . $qtyTipe,
                'amount' => (float) ($sleeveSurchargeById[$typeId] ?? 0) * $qtyTipe,
            ];
        }
        foreach ($rekapQtyKerah as $typeId => $qtyTipe) {
            if (abs((float) ($collarSurchargeById[$typeId] ?? 0)) < 0.005) {
                continue;
            }
            $rincian[] = [
                'label' => 'Kerah ' . ($collarNameById[$typeId] ?? ('#' . $typeId)) . ' × ' . $qtyTipe,
                'amount' => (float) ($collarSurchargeById[$typeId] ?? 0) * $qtyTipe,
            ];
        }

        $subtotal = $totalDasar + $surchargeTotal + $sleeveCollarSurchargeTotal;

        return [
            'items' => $items,
            'pemain_tanpa_ukuran' => $pemainTanpaUkuran,
            'qty' => $qty,
            'qtyPerSizeId' => $qtyPerSizeId,
            'qtyPerSizeSleeveType' => $qtyPerSizeSleeveType,
            'qtyPerSizeCollarType' => $qtyPerSizeCollarType,
            'lineSleeveTypeId' => $lineSleeveTypeId,
            'lineCollarTypeId' => $lineCollarTypeId,
            'sleeveNameById' => $sleeveNameById,
            'collarNameById' => $collarNameById,
            'material' => $material,
            'tier' => $tier,
            'hargaSatuan' => $hargaSatuan,
            'surchargeTotal' => $surchargeTotal,
            'sleeveCollarSurchargeTotal' => $sleeveCollarSurchargeTotal,
            'rincian' => $rincian, // tampilan saja - tidak dipakai saat menyimpan
            'subtotal' => $subtotal,
        ];
    }

    private function simpanBarisOrder(JerseyOrder $order, array $lineData, bool $susulan = false): bool
    {
        $h = $this->hitungHargaBaris($lineData);
        if ($h === null) {
            return false;
        }

        $line = $order->lines()->create([
            'jersey_product_type_id' => $lineData['jersey_product_type_id'] ?? null,
            'jersey_category_id' => $lineData['jersey_category_id'] ?? null,
            'jersey_material_id' => $h['material']?->id,
            'jersey_sleeve_type_id' => $h['lineSleeveTypeId'],
            'jersey_collar_type_id' => $h['lineCollarTypeId'],
            'nama_barang' => $lineData['nama_barang'],
            'bahan' => $h['material']?->name,
            'keterangan' => $lineData['keterangan'] ?? null,
            'thumbnail' => $lineData['thumbnail'],
            'order_type' => $lineData['order_type'],
            'is_susulan' => $susulan,
            'qty' => $h['qty'],
            'harga_satuan' => $h['hargaSatuan'],
            'size_surcharge_total' => $h['surchargeTotal'],
            'sleeve_collar_surcharge_total' => $h['sleeveCollarSurchargeTotal'],
            'subtotal' => $h['subtotal'],
            'jersey_price_setting_id' => $h['tier']->id ?? null,
        ]);

        foreach ($h['items'] as $item) {
            // sleeve/collar (teks) dipertahankan sebagai snapshot nama -
            // pola sama seperti jersey_order_lines.bahan mendampingi
            // jersey_material_id - supaya tampilan lama (SPK, daftar
            // pemain) tidak perlu diubah, cukup baca kolom teks ini.
            $item['sleeve'] = !empty($item['jersey_sleeve_type_id']) ? ($h['sleeveNameById'][$item['jersey_sleeve_type_id']] ?? null) : null;
            $item['collar'] = !empty($item['jersey_collar_type_id']) ? ($h['collarNameById'][$item['jersey_collar_type_id']] ?? null) : null;
            // Audit: size (teks snapshot) didampingi FK ke master ukuran -
            // pola persis sleeve/collar. Validasi sudah menjamin $item['size']
            // ada di master aktif, jadi lookup ini selalu ketemu.
            $item['jersey_size_id'] = ($item['size'] ?? null) !== null
                ? ($this->sizeIdByName()[$item['size']] ?? null)
                : null;
            $line->items()->create($item);
        }
        foreach ($h['qtyPerSizeId'] as $sizeId => $qtyUkuran) {
            $line->sizeQuantities()->create([
                'jersey_size_id' => $sizeId,
                'quantity' => $qtyUkuran,
            ]);
        }

        // Spesifikasi Order (pemakaian bahan) dihitung OTOMATIS dari qty per
        // ukuran x TIGA matriks estimasi di Master Data (Komponen, Tipe
        // Lengan, Tipe Kerah) - bukan diketik manual lagi, dan tidak lagi
        // menyamaratakan semua pilihan lengan/kerah (lihat
        // JerseyMaterialEstimationService). Estimasi murni.
        foreach (JerseyMaterialEstimationService::hitungEstimasi($h['qtyPerSizeId'], $lineData['jersey_product_type_id'] ?? null, $lineData['jersey_category_id'] ?? null) as $estimasi) {
            $line->materials()->create([
                'nama_barang' => $estimasi['component']->name,
                'penggunaan_meter' => $estimasi['total_meter'] > 0 ? round($estimasi['total_meter'], 2) : null,
                'penggunaan_kg' => $estimasi['total_weight_gram'] > 0 ? round($estimasi['total_weight_gram'] / 1000, 2) : null,
            ]);
        }
        // Lengan & Kerah hanya untuk tipe produk yang memilikinya (Celana tidak).
        $punyaLenganKerah = JerseyMaterialEstimationService::tipeMemilikiLenganKerah(!empty($lineData['jersey_product_type_id']) ? (int) $lineData['jersey_product_type_id'] : null);
        foreach (JerseyMaterialEstimationService::hitungEstimasiLengan($punyaLenganKerah ? $h['qtyPerSizeSleeveType'] : []) as $estimasi) {
            $line->materials()->create([
                'nama_barang' => 'Lengan ' . $estimasi['component']->name,
                'penggunaan_meter' => $estimasi['total_meter'] > 0 ? round($estimasi['total_meter'], 2) : null,
                'penggunaan_kg' => $estimasi['total_weight_gram'] > 0 ? round($estimasi['total_weight_gram'] / 1000, 2) : null,
            ]);
        }
        foreach (JerseyMaterialEstimationService::hitungEstimasiKerah($punyaLenganKerah ? $h['qtyPerSizeCollarType'] : []) as $estimasi) {
            $line->materials()->create([
                'nama_barang' => 'Kerah ' . $estimasi['component']->name,
                'penggunaan_meter' => $estimasi['total_meter'] > 0 ? round($estimasi['total_meter'], 2) : null,
                'penggunaan_kg' => $estimasi['total_weight_gram'] > 0 ? round($estimasi['total_weight_gram'] / 1000, 2) : null,
            ]);
        }

        return true;
    }

    /**
     * Preview kalkulasi harga LIVE di form (Order Jersey Baru/Kelola
     * Barang/Tambah Susulan) - dipanggil berulang kali oleh JS setiap field
     * harga-relevan berubah (tipe produk, kategori, bahan, qty, lengan,
     * kerah), TIDAK menyimpan apa pun ke database, murni baca & hitung
     * lewat hitungHargaBaris() yang sama persis dipakai saat submit
     * sungguhan - supaya angka yang dilihat operator SELALU sama dengan
     * yang akan benar-benar tersimpan.
     */
    public function previewHarga(Request $request)
    {
        $hasilPerBaris = [];
        $totalOrder = 0.0;

        foreach ($request->input('lines', []) as $idx => $lineData) {
            $h = $this->hitungHargaBaris(is_array($lineData) ? $lineData : []);
            if ($h === null) {
                continue;
            }
            $totalOrder += $h['subtotal'];
            $hasilPerBaris[$idx] = [
                'qty' => $h['qty'],
                'harga_satuan' => $h['hargaSatuan'],
                'size_surcharge_total' => $h['surchargeTotal'],
                'sleeve_collar_surcharge_total' => $h['sleeveCollarSurchargeTotal'],
                'subtotal' => $h['subtotal'],
                'ada_tier' => $h['tier'] !== null && (float) $h['tier']->price > 0,
                'tier_label' => $h['tier'] !== null
                    ? 'min ' . $h['tier']->qty_min . ($h['tier']->qty_max ? '–' . $h['tier']->qty_max : '') . ' pcs @ Rp ' . number_format((float) $h['tier']->price, 0, ',', '.')
                    : null,
                'rincian' => $h['rincian'],
                'pemain_tanpa_ukuran' => $h['pemain_tanpa_ukuran'],
            ];
        }

        $diskon = (float) str_replace(['.', ','], ['', '.'], (string) $request->input('discount', 0));
        $biayaTambahan = (float) str_replace(['.', ','], ['', '.'], (string) $request->input('additional_charge', 0));

        return response()->json([
            'lines' => $hasilPerBaris,
            'total_barang' => $totalOrder,
            'total_order' => max(0, $totalOrder - $diskon + $biayaTambahan),
        ]);
    }

    public function show(JerseyOrder $order)
    {
        $order->load([
            'konsumen', 'designer', 'payments',
            'lines.productType', 'lines.category', 'lines.material', 'lines.sleeveType', 'lines.collarType',
            'lines.items', 'lines.items.jerseySize', 'lines.sizeQuantities.size', 'lines.materials',
        ]);
        // Master data (termasuk designers) dari formData() yang sama dipakai
        // create/edit-lines - dibutuhkan form modal "Tambah Barang Susulan"
        // yang reuse partial _line-card-fields di halaman ini.
        return view('jersey.orders.show', $this->formData() + compact('order'));
    }

    public function update(Request $request, JerseyOrder $order)
    {
        if (\App\Support\VersiBaris::bentrok($request, $order->fresh())) {
            return back()->withInput()->with('error', \App\Support\VersiBaris::pesan($order->fresh()));
        }

        // status_pembayaran TIDAK lagi diisi manual di sini - sekarang
        // dihitung otomatis dari ledger Keuangan Jersey (lihat
        // JerseyOrder::recalculatePembayaran(), dipanggil dari
        // JerseyFinanceController tiap ada pembayaran ditambah/dihapus).
        $data = $request->validate([
            'status' => 'required|in:baru,proses,selesai,diambil',
            'deadline' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'team_name' => 'nullable|string|max:255',
            'jersey_designer_id' => 'nullable|exists:jersey_designers,id',
            'id_desainer' => 'nullable|exists:users,id_user',
            'file_siap_cetak' => 'nullable|boolean',
            'discount' => 'nullable|numeric|min:0',
            'additional_charge' => 'nullable|numeric|min:0',
        ]);

        $data['designer_name'] = !empty($data['jersey_designer_id'])
            ? JerseyDesigner::find($data['jersey_designer_id'])?->name
            : null;

        // Desainer (Operator Desain) - terkunci untuk role Operator Desain,
        // bebas untuk role lain. Bila file siap cetak, desainer tidak diperlukan.
        if (!empty($data['file_siap_cetak'])) {
            $data['id_desainer'] = null;
        } else {
            $data['id_desainer'] = \App\Services\DesainKomisiService::kunciDesainer(
                isset($data['id_desainer']) ? (int) $data['id_desainer'] : null
            );
        }
        $data['file_siap_cetak'] = !empty($data['file_siap_cetak']);

        // Role tanpa hak harga tidak melihat kolom diskon/biaya tambahan; nilai lama dipertahankan, bukan dikosongkan.
        if (! \App\Support\AksesHarga::bolehLihat()) {
            unset($data['discount'], $data['additional_charge']);
        }

        // Dibungkus transaction - update() dan recalculateTotal() (yang juga
        // memanggil recalculatePembayaran(), total 3 statement UPDATE
        // terpisah ke jersey_orders) sebelumnya tidak dibungkus apapun.
        // Kalau recalculateTotal() gagal di tengah setelah update() sukses,
        // discount/additional_charge sudah berubah tapi total & status
        // pembayaran belum ikut disesuaikan - order jadi tidak konsisten.
        $peringatan = [];
        $statusLama = $order->status;
        try {
        DB::transaction(function () use ($request, $order, $data, &$peringatan, $statusLama) {
            // Kunci baris order lalu cek versi lagi: dua simpan bersamaan antre, yang kedua tahu datanya sudah berubah.
            $terkunci = JerseyOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (\App\Support\VersiBaris::bentrok($request, $terkunci)) {
                throw new \RuntimeException(\App\Support\VersiBaris::pesan($terkunci));
            }
            $order->update($data);
            $order->recalculateTotal();
            // Hanya saat STATUS berubah: proses/selesai/diambil = kain dipotong dari stok; kembali ke "baru" = stok dikembalikan.
            // (Order lama yang sudah berstatus proses tidak dipotong mendadak hanya karena data lain diubah.)
            if ($statusLama !== $order->status) {
                $peringatan = \App\Services\JerseyStokService::selaraskan($order);
            }
        });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Order jersey berhasil diperbarui.' . ($peringatan ? ' ' . implode(' ', $peringatan) : ''));
    }

    /**
     * Halaman kelola barang (tambah/ubah/hapus) untuk order yang SUDAH ada
     * - reuse penuh markup & JS form multi-baris dari create.blade.php,
     * cuma pra-isi dengan data barang yang sudah tersimpan.
     */
    public function editLines(JerseyOrder $order)
    {
        $order->load(['lines.items', 'lines.sizeQuantities']);
        return view('jersey.orders.edit-lines', $this->formData() + compact('order'));
    }

    /**
     * Simpan ulang SELURUH barang order - pola sinkron hapus-lalu-buat-ulang
     * (sama seperti sync BOM di ProdukController, size_quantities saat
     * store(), dst di aplikasi ini) BUKAN diff per baris, supaya harga,
     * tambahan ukuran, & estimasi bahan tiap barang selalu dihitung ulang
     * dari nol dengan cara yang identik dengan store() - tidak ada celah
     * baris "setengah lama setengah baru" yang datanya tidak sinkron.
     *
     * Konsekuensi yang perlu disadari: id tiap baris barang berubah stelah
     * ini (baris lama dihapus, dibuat ulang) - link SPK yang sudah dicetak
     * untuk order ini (/{order}/lines/{line}/spk) jadi tidak berlaku lagi,
     * harus dicetak ulang dari halaman detail order setelah disimpan.
     */
    public function updateLines(Request $request, JerseyOrder $order)
    {
        $request->attributes->set('jersey.order_id', $order->id);
        if (\App\Support\VersiBaris::bentrok($request, $order->fresh())) {
            return back()->withInput()->with('error', \App\Support\VersiBaris::pesan($order->fresh()));
        }

        $data = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.nama_barang' => 'required|string|max:255',
            // Audit kelengkapan (dropdown wajib terisi): Tipe Produk, Kategori,
            // dan Bahan menentukan harga tier & estimasi kain - biarkan kosong
            // berarti order tersimpan tanpa harga/estimasi. Server-side WAJIB
            // menolak, bukan cuma warning client-side.
            'lines.*.jersey_product_type_id' => 'required|exists:jersey_product_types,id',
            'lines.*.jersey_category_id' => 'required|exists:jersey_categories,id',
            'lines.*.jersey_material_id' => 'required|exists:jersey_materials,id',
            // Mode "Daftar Pemain" (order_type = player_list) menyembunyikan
            // dropdown lengan/kerah default di level baris - pemakaiannya jadi
            // per pemain (lihat rules items.* di bawah). Kalau baris ini tetap
            // `required` polos, order mode Daftar Pemain tidak akan pernah bisa
            // disimpan karena user tidak punya UI untuk mengisinya.
            // Kolom di database sudah nullable dan diisi di baris penyimpan
            // memakai `!empty(...) ? (int) : null`, jadi null aman.
            'lines.*.jersey_sleeve_type_id' => ['nullable', 'exists:jersey_sleeve_types,id'],
            'lines.*.jersey_collar_type_id' => ['nullable', 'exists:jersey_collar_types,id'],
            'lines.*.keterangan' => 'nullable|string|max:2000',
            'lines.*.thumbnail' => 'required|string|max:255',
            'lines.*.order_type' => 'required|in:quantity_only,player_list,mixed',

            'lines.*.items' => 'nullable|array',
            'lines.*.items.*.player_name' => 'required|string|max:255',
            'lines.*.items.*.player_number' => 'nullable|string|max:20',
            // Audit Order Jersey: ukuran diambil dari MASTER jersey_sizes
            // (aktif) - BUKAN whitelist hard-code. Dulu 'in:S,M,L,XL,...'
            // membuat ukuran baru yang ditambahkan lewat master ditolak di
            // sini ("The selected lines.0.items.9.size is invalid") padahal
            // dropdown form (dari master yang sama) sudah menawarkannya.
            // Rule closure membaca master dengan cache static per-request
            // supaya N baris pemain tidak memicu N query.
            // Audit kelengkapan: pemain TANPA ukuran merusak rekap ukuran,
            // harga surcharge, dan estimasi kain - ukuran WAJIB untuk setiap
            // baris pemain (required, bukan nullable), tetap tervalidasi ke
            // master jersey_sizes.
            'lines.*.items.*.size' => ['required', 'string', 'max:50', function ($attribute, $value, $fail) {
                if ($value !== null && !$this->ukuranMasterAktif()->contains($value)) {
                    $fail("Ukuran \"{$value}\" tidak terdaftar (atau nonaktif) di Master Ukuran Jersey. Muat ulang halaman setelah master diperbarui, atau daftarkan ukurannya dulu.");
                }
            }],
            'lines.*.items.*.gender' => 'nullable|in:L,P',
            // Audit kelengkapan: lengan/kerah pemain WAJIB dipilih (pilihan
            // "Tanpa Lengan"/"Tanpa Kerah" tersedia di master untuk barang
            // yang memang tidak memakainya - bukan dropdown dibiarkan kosong).
            'lines.*.items.*.jersey_sleeve_type_id' => 'nullable|exists:jersey_sleeve_types,id',
            'lines.*.items.*.jersey_collar_type_id' => 'nullable|exists:jersey_collar_types,id',

            'lines.*.size_quantities' => 'nullable|array',
            // Audit: KEY size_quantities adalah ID ukuran master (dari form
            // qty-per-ukuran). Key palsu lolos validasi ini lalu gagal FK
            // dengan error kasar saat disimpan - dicek eksplisit di bawah.
            'lines.*.size_quantities.*' => 'nullable|integer|min:0',
        ], [
            'lines.*.thumbnail.required' => 'Thumbnail desain wajib diisi untuk setiap barang (acuan warna & bentuk produksi).',
        ]);

        $this->validasiSleeveCollar($request);

        // Audit: size_quantities dikunci ke master (lihat store()).
        foreach ($data['lines'] as $i => $lineData) {
            $this->validasiKeySizeQuantities($lineData['size_quantities'] ?? [], $i);
        }
        $this->validasiQtyTerisi($data['lines']);

        DB::beginTransaction();
        try {
            // Kunci baris order SEBELUM hapus-lalu-buat-ulang: dua simpan bersamaan tidak lagi menghasilkan barang ganda.
            $terkunci = JerseyOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (\App\Support\VersiBaris::bentrok($request, $terkunci)) {
                throw new \RuntimeException(\App\Support\VersiBaris::pesan($terkunci));
            }
            $order->lines()->delete();

            $adaLineValid = false;
            foreach ($data['lines'] as $lineData) {
                if ($this->simpanBarisOrder($order, $lineData)) {
                    $adaLineValid = true;
                }
            }

            if (!$adaLineValid) {
                DB::rollBack();
                return back()->withInput()->with('error', 'Order harus punya minimal 1 jenis barang dengan pemain/qty terisi.');
            }

            $order->recalculateTotal();
            \App\Services\JerseyStokService::segarkan($order); // estimasi kain berubah -> potongan stok disesuaikan

            DB::commit();

            return redirect()->route('jersey.orders.show', $order->id)
                ->with('success', 'Barang pada order jersey berhasil diperbarui. Cetak ulang SPK barang yang berubah bila perlu.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', \App\Support\PesanError::untuk($e, 'Gagal memperbarui barang'));
        }
    }

    /**
     * Tambah SATU barang susulan ke order yang sudah berjalan - TIDAK
     * menyentuh barang lama sama sekali (beda dari updateLines() yang
     * hapus-lalu-buat-ulang semua barang). Cocok untuk kasus qty/jenis
     * barang bertambah SETELAH order awal jalan (mis. panitia acara
     * bertambah, butuh jersey tambahan beberapa hari kemudian) - SPK
     * barang lama yang sudah dicetak/diproses tetap valid, cuma barang
     * baru ini yang ditandai is_susulan=true supaya jelas terlihat beda
     * dari pesanan awal di halaman detail & cetak.
     */
    public function storeLineSusulan(Request $request, JerseyOrder $order)
    {
        $request->attributes->set('jersey.order_id', $order->id);
        $data = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.nama_barang' => 'required|string|max:255',
            // Audit kelengkapan (dropdown wajib terisi): Tipe Produk, Kategori,
            // dan Bahan menentukan harga tier & estimasi kain - biarkan kosong
            // berarti order tersimpan tanpa harga/estimasi. Server-side WAJIB
            // menolak, bukan cuma warning client-side.
            'lines.*.jersey_product_type_id' => 'required|exists:jersey_product_types,id',
            'lines.*.jersey_category_id' => 'required|exists:jersey_categories,id',
            'lines.*.jersey_material_id' => 'required|exists:jersey_materials,id',
            // Mode "Daftar Pemain" (order_type = player_list) menyembunyikan
            // dropdown lengan/kerah default di level baris - pemakaiannya jadi
            // per pemain (lihat rules items.* di bawah). Kalau baris ini tetap
            // `required` polos, order mode Daftar Pemain tidak akan pernah bisa
            // disimpan karena user tidak punya UI untuk mengisinya.
            // Kolom di database sudah nullable dan diisi di baris penyimpan
            // memakai `!empty(...) ? (int) : null`, jadi null aman.
            'lines.*.jersey_sleeve_type_id' => ['nullable', 'exists:jersey_sleeve_types,id'],
            'lines.*.jersey_collar_type_id' => ['nullable', 'exists:jersey_collar_types,id'],
            'lines.*.keterangan' => 'nullable|string|max:2000',
            'lines.*.thumbnail' => 'required|string|max:255',
            'lines.*.order_type' => 'required|in:quantity_only,player_list,mixed',

            'lines.*.items' => 'nullable|array',
            'lines.*.items.*.player_name' => 'required|string|max:255',
            'lines.*.items.*.player_number' => 'nullable|string|max:20',
            // Audit Order Jersey: ukuran diambil dari MASTER jersey_sizes
            // (aktif) - BUKAN whitelist hard-code. Dulu 'in:S,M,L,XL,...'
            // membuat ukuran baru yang ditambahkan lewat master ditolak di
            // sini ("The selected lines.0.items.9.size is invalid") padahal
            // dropdown form (dari master yang sama) sudah menawarkannya.
            // Rule closure membaca master dengan cache static per-request
            // supaya N baris pemain tidak memicu N query.
            // Audit kelengkapan: pemain TANPA ukuran merusak rekap ukuran,
            // harga surcharge, dan estimasi kain - ukuran WAJIB untuk setiap
            // baris pemain (required, bukan nullable), tetap tervalidasi ke
            // master jersey_sizes.
            'lines.*.items.*.size' => ['required', 'string', 'max:50', function ($attribute, $value, $fail) {
                if ($value !== null && !$this->ukuranMasterAktif()->contains($value)) {
                    $fail("Ukuran \"{$value}\" tidak terdaftar (atau nonaktif) di Master Ukuran Jersey. Muat ulang halaman setelah master diperbarui, atau daftarkan ukurannya dulu.");
                }
            }],
            'lines.*.items.*.gender' => 'nullable|in:L,P',
            // Audit kelengkapan: lengan/kerah pemain WAJIB dipilih (pilihan
            // "Tanpa Lengan"/"Tanpa Kerah" tersedia di master untuk barang
            // yang memang tidak memakainya - bukan dropdown dibiarkan kosong).
            'lines.*.items.*.jersey_sleeve_type_id' => 'nullable|exists:jersey_sleeve_types,id',
            'lines.*.items.*.jersey_collar_type_id' => 'nullable|exists:jersey_collar_types,id',

            'lines.*.size_quantities' => 'nullable|array',
            // Audit: KEY size_quantities adalah ID ukuran master (dari form
            // qty-per-ukuran). Key palsu lolos validasi ini lalu gagal FK
            // dengan error kasar saat disimpan - dicek eksplisit di bawah.
            'lines.*.size_quantities.*' => 'nullable|integer|min:0',
        ], [
            'lines.*.thumbnail.required' => 'Thumbnail desain wajib diisi.',
        ]);

        $this->validasiSleeveCollar($request);

        // Audit: size_quantities dikunci ke master (lihat store()).
        foreach ($data['lines'] as $i => $lineData) {
            $this->validasiKeySizeQuantities($lineData['size_quantities'] ?? [], $i);
        }
        $this->validasiQtyTerisi($data['lines']);

        DB::beginTransaction();
        try {
            JerseyOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $adaLineValid = false;
            foreach ($data['lines'] as $lineData) {
                if ($this->simpanBarisOrder($order, $lineData, susulan: true)) {
                    $adaLineValid = true;
                }
            }

            if (!$adaLineValid) {
                DB::rollBack();
                return back()->withInput()->with('error', 'Isi qty/daftar pemain terlebih dahulu.');
            }

            $order->recalculateTotal();
            \App\Services\JerseyStokService::segarkan($order); // barang susulan menambah kebutuhan kain

            DB::commit();

            return redirect()->route('jersey.orders.show', $order->id)
                ->with('success', 'Barang susulan berhasil ditambahkan ke order ' . $order->order_number . '.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', \App\Support\PesanError::untuk($e, 'Gagal menambah barang susulan'));
        }
    }

    public function destroy(Request $request, JerseyOrder $order)
    {
        // Order yang sudah diakui sebagai pendapatan (atau sudah ada
        // pembayaran) tidak boleh dihapus langsung - jurnalnya harus dibalik,
        // jadi diajukan lewat Approval Workflow. Order yang belum bernilai
        // (total_terjurnal 0, tanpa pembayaran) masih boleh dihapus langsung.
        // Pemeriksaan dan penghapusan di dalam satu transaksi berkunci: pembayaran yang masuk di sela-sela tidak ikut
        // terhapus bersama ordernya.
        try {
            $perluApproval = DB::transaction(function () use ($order) {
                $terkunci = JerseyOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ((float) $terkunci->total_terjurnal > 0 || $terkunci->payments()->exists()) {
                    return true;
                }
                \App\Services\JerseyAccountingService::pastikanTanpaUpahJahitTertunda($terkunci);
                \App\Services\JerseyStokService::kembalikan($terkunci);
                $terkunci->delete();

                return false;
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($perluApproval) {
            $alasan = $request->input('alasan') ?: 'Pembatalan order jersey';

            $sudahAda = \App\Models\ApprovalRequest::where('tipe_aksi', 'batalkan_order_jersey')
                ->where('referensi_id', $order->id)->where('status', 'Menunggu')->exists();

            if ($sudahAda) {
                return back()->with('error', 'Order ini sudah punya pengajuan pembatalan yang menunggu.');
            }

            \App\Services\ApprovalService::ajukan(
                'batalkan_order_jersey',
                'jersey_orders',
                $order->id,
                ['alasan' => $alasan],
                "Batalkan order jersey {$order->order_number} (Rp " . number_format($order->total, 0, ',', '.') . ')',
                $alasan
            );

            return back()->with('success', 'Pengajuan pembatalan order dikirim untuk persetujuan Administrator.');
        }

        return redirect()->route('jersey.orders.index')->with('success', 'Order jersey berhasil dihapus.');
    }

    /**
     * Cetak ringkasan seluruh order - $format 'thermal' (58mm) atau
     * 'normal' (A4/F4), sama seperti pola print nota/closing kas di modul
     * Kasir yang sudah ada.
     */
    public function print(JerseyOrder $order, string $format = 'normal')
    {
        $order->load([
            'konsumen', 'designer',
            'lines.productType', 'lines.category', 'lines.items', 'lines.sizeQuantities.size', 'lines.materials',
        ]);
        $format = in_array($format, ['thermal', 'normal']) ? $format : 'normal';
        return response()->view('jersey.orders.print', compact('order', 'format'))->header('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * Cetak SPK (Surat Perintah Kerja) SATU line spesifik - format detail
     * produksi per-jenis-barang (thumbnail, ukuran, spesifikasi bahan),
     * beda dari print() di atas yang mencetak ringkasan seluruh order.
     */
    public function printLine(JerseyOrder $order, JerseyOrderLine $line)
    {
        abort_if($line->jersey_order_id !== $order->id, 404);
        $line->load(['productType', 'category', 'material', 'items', 'items.jerseySize', 'sizeQuantities.size', 'materials', 'order.konsumen', 'order.designer']);
        return view('jersey.orders.spk-print', compact('order', 'line'));
    }
}
