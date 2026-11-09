<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Satuan;

/**
 * Price List & Level - REDESAIN TOTAL dari versi sebelumnya. Sebelumnya
 * satu produk punya toggle Flat/Range TERPISAH per golongan (state ganda
 * yang membingungkan - "flat" sebenarnya cuma kasus khusus dari "range
 * dengan satu baris"), disimpan lewat SATU submit besar yang menghapus
 * SEMUA baris harga produk itu lalu insert ulang semuanya (update_bulk) -
 * riwayat bug: key array bentrok bikin baris hilang diam-diam, dan field
 * id_satuan/qty_berlaku/id_satuan_berlaku TERNYATA TIDAK PERNAH dipakai
 * sama sekali di HargaService::getHargaEfektif (murni dekoratif, cuma
 * menambah kebingungan tanpa fungsi).
 *
 * Sekarang: SATU MODE SAJA (selalu berupa baris rentang qty->harga, "flat"
 * cukup satu baris dengan Max kosong/tak terbatas), field yang tidak
 * pernah dipakai dihapus dari UI, dan setiap baris py aksi CRUD sendiri
 * (storeRow/updateRow/destroyRow) dengan id_harga asli di database -
 * tidak ada lagi "hapus semua lalu insert ulang", jadi tidak ada lagi
 * kelas bug "baris lain ikut hilang". Alokasi Mesin Produksi juga
 * DIHAPUS dari modal ini - itu domain Master Produk, sudah py tempatnya
 * sendiri di sana, tidak perlu dobel di halaman harga.
 */
class SettingHargaController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('produk')
            ->leftJoin('mesin', 'produk.id_mesin', '=', 'mesin.id_mesin')
            ->select('produk.*', 'mesin.nama_mesin');

        if ($request->filled('search')) {
            $query->where('produk.nama_produk', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('category')) {
            $query->where('produk.id_mesin', $request->category);
        }

        // Daftar putih: kolom urut dari URL tidak boleh bebas (mengurutkan berdasar kolom mana pun dari join, atau
        // memicu galat 500 dengan nilai sembarang).
        $sort = in_array($request->get('sort'), ['produk.nama_produk', 'mesin.nama_mesin', 'produk.id_produk'], true) ? $request->get('sort') : 'produk.nama_produk';
        $direction = $request->get('direction') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sort, $direction);

        $produk = $query->paginate(20)->withQueryString();

        $golongan = DB::table('golongan_konsumen')->orderBy('nama_golongan')->get();
        $categories = DB::table('mesin')->orderBy('nama_mesin')->get();

        $all_harga = DB::table('setting_harga')
            ->whereIn('id_produk', $produk->pluck('id_produk'))
            ->orderBy('id_golongan')
            ->orderBy('range_min')
            ->get()
            ->groupBy('id_produk');

        return view('master.setting_harga.index', compact(
            'produk', 'golongan', 'all_harga', 'categories'
        ));
    }

    public function storeRow(Request $request, $idProduk)
    {
        // Rentang wajib BILANGAN BULAT untuk produk metode_hitung='quantity'
        // (satuan Pcs - pecahan tidak bermakna sama sekali disini, mis. Min
        // "1,01" pcs) - tapi TETAP numeric/pecahan untuk metode_hitung=
        // 'ukuran' (lihat migrasi 2026_09_11_240000: satuan rentang di situ
        // luas m² pecahan, mis. 0.09 m² utk stiker 30x30cm - integer|min:1
        // di situ dulu membuat order di bawah 1 m² MUSTAHIL dapat harga).
        // Divalidasi di server (bukan cuma dibulatkan di JS) karena endpoint
        // ini dipanggil lewat fetch() JSON, bukan submit form native, jadi
        // atribut "step" HTML tidak pernah benar-benar dicek browser.
        $metodeHitung = DB::table('produk')->where('id_produk', $idProduk)->value('metode_hitung');
        $isPcs = $metodeHitung !== 'ukuran';
        $request->validate([
            'id_golongan' => 'required|exists:golongan_konsumen,id_golongan',
            'range_min'   => $isPcs ? 'required|integer|min:1' : 'required|numeric|min:0.01',
            'range_max'   => ($isPcs ? 'nullable|integer' : 'nullable|numeric') . '|gte:range_min',
            'harga_jual'  => 'required|numeric|min:0',
        ]);

        if ($this->tumpangTindih($idProduk, $request->id_golongan, $request->range_min, $request->range_max)) {
            return response()->json(['success' => false, 'message' => 'Rentang qty ini tumpang tindih dengan tier yang sudah ada untuk golongan tersebut. Sesuaikan dulu Min/Max-nya.'], 422);
        }

        $id = DB::table('setting_harga')->insertGetId([
            'id_produk'   => $idProduk,
            'id_golongan' => $request->id_golongan,
            'range_min'   => $request->range_min,
            'range_max'   => $request->range_max ?: null,
            'harga_jual'  => $request->harga_jual,
            'qty_berlaku' => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $row = DB::table('setting_harga')->where('id_harga', $id)->first();

        // Hardening P0-3: perubahan harga jual (Price List) sebelumnya tidak
        // pernah tercatat di audit_logs sama sekali - lihat catatan yang
        // sama di ProdukController.
        AuditLogger::log('created', 'setting_harga', (int) $id, null, (array) $row, 'Tier harga baru untuk produk #' . $idProduk);

        return response()->json(['success' => true, 'message' => 'Tier harga ditambahkan.', 'row' => $row]);
    }

    public function updateRow(Request $request, $id)
    {
        $row = DB::table('setting_harga')->where('id_harga', $id)->first();
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Tier harga tidak ditemukan.'], 404);
        }

        // Optimistic locking: tier ini sudah diubah orang lain sejak halaman dimuat -> jangan menimpa.
        if (\App\Support\VersiBaris::bentrok($request, $row)) {
            return response()->json(['success' => false, 'message' => \App\Support\VersiBaris::pesan($row)], 409);
        }

        // integer utk Pcs, numeric/pecahan utk m² - lihat catatan di storeRow().
        $metodeHitung = DB::table('produk')->where('id_produk', $row->id_produk)->value('metode_hitung');
        $isPcs = $metodeHitung !== 'ukuran';
        $request->validate([
            'range_min'  => $isPcs ? 'required|integer|min:1' : 'required|numeric|min:0.01',
            'range_max'  => ($isPcs ? 'nullable|integer' : 'nullable|numeric') . '|gte:range_min',
            'harga_jual' => 'required|numeric|min:0',
        ]);

        if ($this->tumpangTindih($row->id_produk, $row->id_golongan, $request->range_min, $request->range_max, $id)) {
            return response()->json(['success' => false, 'message' => 'Rentang qty ini tumpang tindih dengan tier lain untuk golongan tersebut. Sesuaikan dulu Min/Max-nya.'], 422);
        }

        DB::table('setting_harga')->where('id_harga', $id)->update([
            'range_min'  => $request->range_min,
            'range_max'  => $request->range_max ?: null,
            'harga_jual' => $request->harga_jual,
            'updated_at' => $sekarang = now(),
        ]);

        AuditLogger::log('updated', 'setting_harga', (int) $id,
            ['range_min' => $row->range_min, 'range_max' => $row->range_max, 'harga_jual' => $row->harga_jual],
            ['range_min' => $request->range_min, 'range_max' => $request->range_max ?: null, 'harga_jual' => $request->harga_jual],
            'Tier harga diperbarui');

        return response()->json(['success' => true, 'message' => 'Tier harga diperbarui.', 'versi' => $sekarang->format('Y-m-d H:i:s')]);
    }

    public function destroyRow($id)
    {
        $row = DB::table('setting_harga')->where('id_harga', $id)->first();
        DB::table('setting_harga')->where('id_harga', $id)->delete();

        if ($row) {
            AuditLogger::log('deleted', 'setting_harga', (int) $id, (array) $row, null, 'Tier harga dihapus');
        }

        return response()->json(['success' => true, 'message' => 'Tier harga dihapus.']);
    }

    /**
     * Copy SELURUH tier harga satu golongan (golongan yang sedang aktif di
     * tab modal) ke SEMUA golongan lain untuk produk yang sama - dipakai
     * saat harga memang sama rata di semua golongan, supaya tidak perlu
     * mengetik ulang tier satu-satu di tiap tab. Tier golongan tujuan
     * DIGANTI TOTAL (hapus lalu insert ulang) dengan salinan tier golongan
     * sumber - ini aksi destruktif yang disengaja (harus dikonfirmasi dulu
     * di sisi klien), bukan digabung dengan tier yang sudah ada di golongan
     * tujuan supaya hasilnya benar-benar identik dengan golongan sumber,
     * tidak tercampur sisa tier lama yang mungkin tumpang tindih.
     */
    public function copyKeSemuaGolongan(Request $request, $idProduk)
    {
        $request->validate([
            'id_golongan_sumber' => 'required|exists:golongan_konsumen,id_golongan',
        ]);

        $idSumber = (int) $request->id_golongan_sumber;

        $tiersSumber = DB::table('setting_harga')
            ->where('id_produk', $idProduk)
            ->where('id_golongan', $idSumber)
            ->orderBy('range_min')
            ->get();

        if ($tiersSumber->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Golongan ini belum punya tier harga untuk disalin.'], 422);
        }

        $golonganLain = DB::table('golongan_konsumen')->where('id_golongan', '!=', $idSumber)->pluck('id_golongan');

        DB::transaction(function () use ($idProduk, $idSumber, $tiersSumber, $golonganLain) {
            DB::table('setting_harga')
                ->where('id_produk', $idProduk)
                ->whereIn('id_golongan', $golonganLain)
                ->delete();

            $baris = [];
            foreach ($golonganLain as $idGolonganTujuan) {
                foreach ($tiersSumber as $t) {
                    $baris[] = [
                        'id_produk'   => $idProduk,
                        'id_golongan' => $idGolonganTujuan,
                        'range_min'   => $t->range_min,
                        'range_max'   => $t->range_max,
                        'harga_jual'  => $t->harga_jual,
                        'qty_berlaku' => 1,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
                }
            }
            if (!empty($baris)) {
                DB::table('setting_harga')->insert($baris);
            }

            AuditLogger::log('updated', 'setting_harga', (int) $idProduk, null, ['dari_golongan' => $idSumber, 'ke_golongan' => $golonganLain->all(), 'jumlah_tier' => count($baris)], 'Harga disalin ke semua golongan lain');
        });

        // Kembalikan SELURUH tier produk ini (semua golongan) yang sudah
        // diperbarui, dikelompokkan per golongan - dipakai klien untuk
        // menggambar ulang semua tab tanpa reload halaman.
        $semuaTier = DB::table('setting_harga')
            ->where('id_produk', $idProduk)
            ->orderBy('id_golongan')
            ->orderBy('range_min')
            ->get()
            ->groupBy('id_golongan');

        return response()->json([
            'success' => true,
            'message' => 'Harga golongan ini berhasil disalin ke ' . $golonganLain->count() . ' golongan lain.',
            'tiers' => $semuaTier,
        ]);
    }

    /**
     * Cek apakah rentang [min, max] (max null = tak terbatas) tumpang
     * tindih dengan tier LAIN yang sudah ada untuk produk+golongan yang
     * sama - sebelumnya TIDAK ADA validasi ini sama sekali, jadi dua tier
     * yang saling tumpang tindih bisa tersimpan berdampingan dan
     * HargaService::getHargaEfektif akan diam-diam memilih salah satu
     * secara tidak terduga (bergantung urutan ORDER BY range_min DESC).
     */
    private function tumpangTindih($idProduk, $idGolongan, $min, $max, $excludeId = null): bool
    {
        // Arahkan ke implementasi bersama supaya aturan "satu tier per rentang"
        // hanya ada satu di aplikasi ini. Audit C1 menemukan aturan ini hanya
        // ada di SettingHargaController, sementara finishing_harga dan
        // jasa_harga tidak punya sama sekali - padahal ketiganya punya form
        // tier dengan cara yang persis sama.
        //
        // Kolom primary key tabel ini bukan "id" melainkan id_harga, jadi
        // diteruskan secara eksplisit.
        return \App\Support\CekRentangHarga::tumpangTindih(
            'setting_harga',
            'id_produk',
            $idProduk,
            ['id_golongan' => $idGolongan],
            $min,
            $max,
            $excludeId,
            'id_harga'
        );
    }

    /* =====================================================================
     |  CETAK DAFTAR HARGA + TIER PER GOLONGAN
     |======================================================================
     |  Lembar harga jual siap cetak: per kategori (produk / jasa /
     |  finishing), matriks baris = rentang qty, kolom = golongan konsumen.
     |
     |  HANYA harga jual. Dilarang dan dijaga mengangkat HPP, modal, margin,
     |  harga beli, atau identitas konsumen: sumbernya murni setting_harga /
     |  harga_siap_cetak / jasa_harga+harga_default / finishing_harga+
     |  harga_default+minimum_charge — baris baris yang SAMA yang dibaca
     |  HargaService di POS, tanpa rumus ganda (dibandingkan otomatis ke
     |  HargaService di test).
     |
     |  Izin SAMA dengan halaman daftar harga (/setting-harga):
     |   - path "setting-harga/cetak" jatuh di prefix 'setting-harga'
     |     (config/menu_access.php) → pemeriksaan menu yang sama buat
     |     CheckMenuAccess/Izin::bolehPath;
     |   - tambahan di action ini: AksesHarga::bolehLihat() yang menutup
     |     role tanpa roles.boleh_lihat_harga sama seperti halaman berharga
     |     lain. Row tanpa login diblokir middleware 'auth' (redirect).
     */
    public function cetak(Request $request)
    {
        abort_unless(
            \App\Support\AksesHarga::bolehLihat(),
            403,
            'Role Anda tidak diizinkan melihat atau mengubah harga. Halaman ini khusus role yang berhak.'
        );

        return view('master.setting_harga.cetak', $this->susunDataDaftarHarga($request));
    }

    /**
     * Menyusun seluruh data lembar harga. PUBLIK-testable: test bandingkan
     * setiap angka di $bloks dengan HargaService::getHargaEfektif/… 
     * (memanggil method ini langsung, tanpa HTTP, berdasarkan DB yang aktif).
     */
    public function susunDataDaftarHarga(Request $request): array
    {
        $filterGolongan = $request->filled('golongan') && ctype_digit((string) $request->query('golongan'))
            ? (int) $request->query('golongan')
            : null;
        $kategori = in_array($request->query('kategori'), ['produk', 'jasa', 'finishing'], true)
            ? $request->query('kategori')
            : 'semua';

        $golongan = DB::table('golongan_konsumen')->orderBy('nama_golongan')->get();
        if ($filterGolongan !== null) {
            $golongan = $golongan->where('id_golongan', $filterGolongan)->values();
        }

        $bloks = collect();
        if (in_array($kategori, ['produk', 'semua'], true)) {
            $bloks = $bloks->merge($this->blokProduk($golongan));
        }
        if (in_array($kategori, ['jasa', 'semua'], true)) {
            $bloks = $bloks->merge($this->blokJasaDanFinishing($golongan, 'jasa'));
        }
        if (in_array($kategori, ['finishing', 'semua'], true)) {
            $bloks = $bloks->merge($this->blokJasaDanFinishing($golongan, 'finishing'));
        }

        return [
            'usaha' => \App\Models\AppSetting::current(),
            'tanggalCetak' => now()->translatedFormat('d F Y, H:i'),
            'golongan' => $golongan,
            'bloks' => $bloks,
            'filterGolongan' => $filterGolongan,
            'kategori' => $kategori,
        ];
    }

    /**
     * Blok produk: satu baris matriks per RENTANG qty unik, kolom per
     * golongan konsumen (> 4 golongan tampil landscape otomatis, diatur
     * di view). Produk status != 'aktif' tidak dicetak; produk tanpa
     * satu pun harga (setting_harga & harga_siap_cetak kosong dan tidak
     * ada promo) tidak dicetak.
     */
    private function blokProduk(\Illuminate\Support\Collection $golongan): \Illuminate\Support\Collection
    {
        $produk = DB::table('produk')
            ->leftJoin('mesin', 'produk.id_mesin', '=', 'mesin.id_mesin')
            ->where('produk.status', 'aktif')
            ->orderBy('produk.nama_produk')
            ->get(['produk.id_produk', 'produk.nama_produk', 'produk.metode_hitung', 'mesin.nama_mesin']);

        if ($produk->isEmpty()) {
            return collect();
        }
        $ids = $produk->pluck('id_produk')->all();
        $idGolonganAktif = $golongan->pluck('id_golongan')->all();

        // EAGER: empat query untuk SELURUH produk — tanpa N+1. Predikat
        // promo disalin verbatim dari HargaService::promoAktif (status
        // aktif, tgl_mulai <= hari ini <= tgl_selesai) dan pemilih
        // pemenangnya (id_promo terbesar) juga sama.
        $setting = DB::table('setting_harga')
            ->whereIn('id_produk', $ids)
            ->whereIn('id_golongan', $idGolonganAktif)
            ->get()
            ->groupBy(fn ($t) => (int) $t->id_produk);
        $promo = DB::table('harga_promo')
            ->whereIn('id_produk', $ids)
            ->where('status_promo', 'aktif')
            ->where('tgl_mulai', '<=', now()->format('Y-m-d'))
            ->where('tgl_selesai', '>=', now()->format('Y-m-d'))
            ->orderByDesc('id_promo')
            ->get()
            ->groupBy(fn ($t) => (int) $t->id_produk)
            ->map->first();
        $siap = DB::table('harga_siap_cetak')
            ->whereIn('id_produk', $ids)
            ->whereIn('id_golongan', $idGolonganAktif)
            ->get()
            ->groupBy(fn ($t) => (int) $t->id_produk);

        $bloks = collect();
        foreach ($produk as $p) {
            $tiers = ($setting[(int) $p->id_produk] ?? collect())->values();
            $siapItem = ($siap[(int) $p->id_produk] ?? collect())->values();
            $promoItem = $promo[(int) $p->id_produk] ?? null;

            if ($tiers->isEmpty() && $siapItem->isEmpty() && $promoItem === null) {
                continue; // item tanpa harga: tidak dicetak
            }

            $unit = $p->metode_hitung === 'ukuran' ? 'm²' : 'Pcs';
            $baris = $this->barisMatriksProduk($tiers, $golongan, $promoItem, $unit);
            $barisSiap = $siapItem->isEmpty()
                ? null
                : $this->barisMatriksProduk($siapItem, $golongan, null, $unit);

            if ($baris === [] && $barisSiap === null) {
                continue;
            }

            $bloks->push([
                'tipe' => 'produk',
                'id' => (int) $p->id_produk,
                'nama' => $p->nama_produk,
                'info' => $p->nama_mesin ?? 'Tanpa Mesin',
                'unit' => $unit,
                'promo' => $promoItem ? ['tglSelesai' => (string) $promoItem->tgl_selesai] : null,
                'baris' => $baris,
                'barisSiapCetak' => $barisSiap,
                'catatan' => null,
                'kolomGolongan' => true,
            ]);
        }

        return $bloks;
    }

    /**
     * Blok jasa / finishing: tanpa dimensi GOLONGAN — HargaService
     * memperlakukan jasa_harga/finishing_harga + harga_default sebagai
     * harga untuk semua pelanggan. Baris = rentang tier; tanpa tier =
     * satu baris harga flat dari harga_default. Item tanpa harga sama
     * sekali (tanpa tier dan harga_default <= 0) tidak dicetak.
     */
    private function blokJasaDanFinishing(\Illuminate\Support\Collection $golongan, string $tipe): \Illuminate\Support\Collection
    {
        $isJasa = $tipe === 'jasa';
        $tabel = $isJasa ? 'jasa' : 'finishing';
        $tabelHarga = $isJasa ? 'jasa_harga' : 'finishing_harga';
        $pk = $isJasa ? 'id_jasa' : 'id_finishing';
        $namaKol = $isJasa ? 'nama_jasa' : 'nama_finishing';

        $items = DB::table($tabel)->orderBy($namaKol)->get();
        if ($items->isEmpty()) {
            return collect();
        }

        $tiers = DB::table($tabelHarga)
            ->whereIn($pk, $items->pluck($pk)->all())
            ->orderBy('range_min')
            ->get()
            ->groupBy(fn ($t) => (int) $t->{$pk});

        $bloks = collect();
        foreach ($items as $item) {
            $tierRows = ($tiers[(int) $item->{$pk}] ?? collect())->values();
            $default = (float) ($item->harga_default ?? 0);

            if ($tierRows->isEmpty() && $default <= 0) {
                continue; // item tanpa harga: tidak dicetak
            }

            $baris = [];
            if ($tierRows->isEmpty()) {
                $baris[] = ['label' => 'Semua Qty', 'rep' => 1.0, 'hargaFlat' => $default];
            } else {
                foreach ($tierRows as $t) {
                    $minT = (float) $t->range_min;
                    $maxT = $t->range_max === null ? null : (float) $t->range_max;
                    $baris[] = [
                        'label' => angka_polos($minT, 2) . ' - '
                            . ($maxT === null ? '∞' : angka_polos($maxT, 2)),
                        'rep' => max($minT, $maxT ?? $minT),
                        'hargaFlat' => (float) $t->harga,
                    ];
                }
            }

            // Catatan minimum charge HANYA untuk finishing (kolom minimum_charge).
            $catatan = !$isJasa && (float) ($item->minimum_charge ?? 0) > 0
                ? 'Biaya = tarif/meter × meter garis potong, minimum charge Rp '
                    . number_format((float) $item->minimum_charge, 0, ',', '.')
                : null;

            $bloks->push([
                'tipe' => $tipe,
                'id' => (int) $item->{$pk},
                'nama' => $item->{$namaKol},
                'info' => $isJasa ? null : ($item->metode_hitung ?? null),
                'baris' => $baris,
                'barisProduk' => null,
                'barisSiapCetak' => null,
                'promo' => null,
                'catatan' => $catatan,
                'kolomGolongan' => false,
            ]);
        }

        return $bloks;
    }

    /**
     * Baris matriks produk: gabungan rentang dari seluruh golongan.
     * Nilai sel memakai aturan pemilihan yang SAMA dengan
     * HargaService::getHargaEfektif (range_min <= qty, range_max null atau
     * >= qty, ambil range_min terbesar) — bukan rumus ganda; test
     * membandingkan setiap sel ke HargaService langsung.
     */
    private function barisMatriksProduk(\Illuminate\Support\Collection $tiers, \Illuminate\Support\Collection $golongan, ?object $promo, string $unit): array
    {
        // Promo aktif menang di atas tier untuk SEMUA golongan & SEMUA qty
        // (persis HargaService::getHargaEfektif: if ($promo) return promo).
        if ($promo !== null) {
            $harga = [];
            foreach ($golongan as $g) {
                $harga[(int) $g->id_golongan] = ['harga' => (float) $promo->harga_promo, 'promo' => true];
            }

            return [['label' => 'Semua Qty', 'rep' => 1.0, 'harga' => $harga]];
        }

        // Kunci baris = pasangan (range_min, range_max) unik lintas golongan.
        $perKunci = [];
        foreach ($tiers as $t) {
            $min = (float) $t->range_min;
            $max = $t->range_max === null ? null : (float) $t->range_max;
            $perKunci[$min . '|' . ($max === null ? 'n' : (string) $max)] = ['min' => $min, 'max' => $max];
        }
        uasort($perKunci, fn ($a, $b) => [$a['min'], $a['max'] ?? INF] <=> [$b['min'], $b['max'] ?? INF]);

        $baris = [];
        foreach ($perKunci as $rentang) {
            // Qty uji di dalam rentang ini untuk evaluasi sel: batas ATAS
            // (inclusive di HargaService), atau batas bawah bila tak berbatas
            // atas/tak valid. Angka harga CONTOH sel = harga efektif kasir
            // untuk qty di rentang itu.
            $rep = max($rentang['min'], $rentang['max'] ?? $rentang['min']);
            $label = angka_polos($rentang['min'], 2) . ' - '
                . ($rentang['max'] === null ? '∞' : angka_polos($rentang['max'], 2)) . ' ' . $unit;

            $harga = [];
            foreach ($golongan as $g) {
                $tier = $this->pilihTier($tiers, (int) $g->id_golongan, $rep);
                $harga[(int) $g->id_golongan] = $tier !== null
                    ? ['harga' => (float) $tier->harga_jual, 'promo' => false]
                    : null;
            }

            $baris[] = ['label' => $label, 'rep' => $rep, 'harga' => $harga];
        }

        return $baris;
    }

    /**
     * Pemilih tier yang PERSIS meniru HargaService::getHargaEfektif:
     * where range_min <= qty, (range_max null atau >= qty),
     * orderBy('range_min','desc') → first.
     */
    private function pilihTier(\Illuminate\Support\Collection $tiers, int $idGolongan, float $qty): ?object
    {
        $pilih = null;
        foreach ($tiers as $t) {
            if ((int) $t->id_golongan !== $idGolongan) {
                continue;
            }
            if ((float) $t->range_min > $qty + 1e-9) {
                continue;
            }
            if ($t->range_max !== null && (float) $t->range_max < $qty - 1e-9) {
                continue;
            }
            if ($pilih === null || (float) $t->range_min > (float) $pilih->range_min) {
                $pilih = $t;
            }
        }

        return $pilih;
    }
}
