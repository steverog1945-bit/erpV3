<?php

namespace App\Http\Controllers\Kasir;

use App\Services\KasSesiService;
use App\Services\StatusPembayaran;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\AccountingService;
use App\Http\Controllers\KonsumenDepositController;

class KasirController extends Controller
{
    public function index(Request $request)
    {
        // §8I catch-up: tolak format tanggal yang rusak sebelum dipakai di
        // Carbon::parse() di bawah - tanpa ini input rusak melempar 500.
        $request->validate(['tgl_mulai' => 'nullable|date', 'tgl_selesai' => 'nullable|date']);

        // 1. Hitung jumlah antrean baru = order menunggu diterima kasir
        //    (status_order='Menunggu Kasir', nota belum dibatalkan) - SAMA
        //    PERSIS dengan waitingList() agar lencana & daftar sinkron.
        $waitingCount = DB::table('transaksi')
            ->where('status_order', 'Menunggu Kasir')
            ->where('status_nota', '!=', 'Dibatalkan')
            ->count();

        // 2. Query Monitoring Utama - SEMUA status pembayaran (Belum Bayar/DP/
        // Lunas) ikut ditampilkan & bisa difilter di sini, TAPI hanya untuk
        // nota yang transaksinya sudah dibuka kasir (lihat syarat status_order
        // di bawah). Status pembayaran dan status order adalah dua hal berbeda:
        // nota yang sudah "Diterima Kasir" tapi uangnya belum masuk tetap
        // berstatus "Belum Bayar" dan memang harus bisa ditagih dari sini.
        $query = DB::table('transaksi')
            ->join('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
            ->join('users', 'transaksi.id_user_kasir', '=', 'users.id_user')
            // Total uang diterima per nota, dihitung sekali lewat LEFT JOIN
            // teragregasi. Dipakai untuk status turunan DAN untuk filter, bukan
            // hanya tampilan - kalau filter memakai kolom salinan sementara
            // badge memakai status turunan, kasir bisa memfilter "Belum
            // Lunas" lalu bertemu nota yang badge-nya justru "LUNAS".
            ->leftJoin(
                DB::raw('(SELECT id_transaksi, SUM(nominal) AS jml_terbayar FROM pembayaran_riwayat GROUP BY id_transaksi) pr'),
                'pr.id_transaksi', '=', 'transaksi.id_transaksi'
            )
            ->select(
                'transaksi.*',
                'konsumen.nama_konsumen',
                'users.username',
                DB::raw('COALESCE(pr.jml_terbayar, 0) as total_masuk'),
                // Kolom transaksi.status_pembayaran hanya salinan, dan pernah
                // menampilkan dua cerita bertentangan di layar yang sama: badge
                // "Lunas" sementara catatan pembayarannya belum ada uang masuk
                // sepeser pun. Badge sekarang diturunkan langsung dari catatan
                // pembayaran memakai aturan yang sama persis dengan
                // Transaksi::display_status_pembayaran, jadi angka-angka di
                // layar tidak mungkin saling bertentangan.
                DB::raw(StatusPembayaran::sqlCase('transaksi.total_bayar', 'COALESCE(pr.jml_terbayar, 0)') . ' as display_status_pembayaran')
            )
            // Nota yang sudah dibatalkan (lihat PenjualanController::batalkanNota)
            // tidak perlu terus "dimonitor" pembayarannya di sini - sebelumnya
            // luput dikecualikan waktu fitur batal-nota ditambahkan, jadi nota
            // DP/Lunas yang belakangan dibatalkan tetap nyangkut di daftar ini.
            ->where('transaksi.status_nota', '!=', 'Dibatalkan')
            // Alur linear kasir: tabel ini hanya menampilkan nota yang
            // TRANSYKSINYA SUDAH DIBUKA kasir. Nota yang baru diinput dan belum
            // dibuka tetap hidup di "Antrean Baru" (lihat waitingList()).
            //
            // Tanpa syarat ini kedua halaman membaca kumpulan baris yang sama:
            // nota waitingList() (status_order='Menunggu Kasir') ikut muncul di
            // tabel ini karena query ini tidak pernah menyaring status_order,
            // sehingga kasir melihat nota yang sama di dua tempat dan tidak tahu
            // harus lewat mana. Kebalikan dari biasanya, "Belum Bayar" tetap
            // ikut tampil di sini - yang menentukan bukan status pembayaran,
            // tapi apakah transaksinya sudah dibuka.
            ->where('transaksi.status_order', 'Diterima Kasir');

        if ($request->filled('id_konsumen')) {
            $query->where('transaksi.id_konsumen', $request->id_konsumen);
        }

        if ($request->filled('nomor_nota')) {
            $query->where('transaksi.nomor_nota', 'like', "%{$request->nomor_nota}%");
        }

        // Perf dataset besar: rentang sargable, bukan whereDate() (DATE()
        // mematikan index (tgl_order,status_nota)). Tanpa filter tanggal
        // dari operator, TIDAK ada batas yang dipaksakan - seluruh nota
        // yang transaksinya sudah dibuka tetap ditampilkan.
        if ($request->filled('tgl_mulai')) {
            $query->where('transaksi.tgl_order', '>=', $request->tgl_mulai . ' 00:00:00');
        }
        if ($request->filled('tgl_selesai')) {
            $query->where('transaksi.tgl_order', '<', \Carbon\Carbon::parse($request->tgl_selesai)->addDay()->format('Y-m-d') . ' 00:00:00');
        }

        // Status: kalau operator MEMILIH status tertentu, ikuti pilihannya
        // (termasuk pilihan eksplisit "-- Semua Status --" = tidak difilter
        // sama sekali, ditandai dari hidden input "status_filtered" yang ikut
        // terkirim setiap kali form filter ini disubmit). Kalau halaman baru
        // dibuka TANPA filter apa pun (nav pertama dari sidebar), default-nya
        // tampilkan yang masih perlu ditagih saja (Belum Bayar + DP) - nota
        // yang sudah Lunas tetap bisa dicari kapan saja lewat filter status.
        //
        // Filter memakai status TURUNAN, sama persis dengan kolom yang dipakai
        // badge. Kalau filter masih membaca kolom salinan
        // transaksi.status_pembayaran, nota yang salinannya tertinggal akan
        // muncul di daftar "Belum Lunas" dengan badge "LUNAS" - persis
        // ambiguitas yang dikeluhkan kasir.
        $statusTurunan = StatusPembayaran::sqlCase('transaksi.total_bayar', 'COALESCE(pr.jml_terbayar, 0)');

        if ($request->filled('status_bayar')) {
            // "Belum Lunas" bukan nilai status, melainkan gabungan dua status
            // (Belum Bayar + DP) - keduanya berarti masih ada sisa tagihan
            // yang harus ditagih. Tanpa cabang khusus ini, memilih "Belum
            // Lunas" akan mengembalikan daftar kosong karena tidak ada nota
            // berstatus persis bernama "Belum Lunas".
            if ($request->status_bayar === 'Belum Lunas') {
                $query->whereIn(DB::raw("({$statusTurunan})"), ['Belum Bayar', 'DP']);
            } else {
                $query->whereRaw("({$statusTurunan}) = ?", [$request->status_bayar]);
            }
        } elseif (!$request->has('status_filtered')) {
            $query->whereIn(DB::raw("({$statusTurunan})"), ['Belum Bayar', 'DP']);
        }

        // COALESCE ke tgl_order - baris 'Belum Bayar' yang belum pernah
        // disentuh kasir sama sekali punya updated_at NULL (insertGetId()
        // di PenjualanController::store() tidak mengisinya), jadi kalau
        // urut murni by updated_at DESC baris-baris ini selalu terdorong ke
        // paling bawah biarpun order-nya baru masuk barusan.
        $transaksi = $query->orderByRaw('COALESCE(transaksi.updated_at, transaksi.tgl_order) DESC')->paginate(20)->withQueryString();

        // Dropdown filter Konsumen - dibatasi ke konsumen yang memang pernah
        // bertransaksi (bukan seluruh master konsumen), supaya daftar
        // pilihan relevan dan tidak membengkak tanpa guna.
        $konsumenList = DB::table('transaksi')
            ->join('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
            ->where('transaksi.status_nota', '!=', 'Dibatalkan')
            ->select('konsumen.id_konsumen', 'konsumen.nama_konsumen')
            ->distinct()
            ->orderBy('konsumen.nama_konsumen')
            ->get();

        // tglMulaiDefault / tglSelesaiDefault sengaja TIDAK ikut di compact():
        // rentang tanggal default pernah dipaksakan ke query, lalu dibatalkan
        // lagi - tanpa filter dari operator, semua nota yang transaksinya sudah
        // dibuka tetap ditampilkan. compact() dengan variabel yang tidak ada
        // akan memunculkan warning "Undefined variable" di PHP 8.
        return view('kasir.monitoring_transaksi', compact('transaksi', 'waitingCount', 'konsumenList'));
    }

    public function waitingList()
    {
        // Antrean Baru: transaksi yang menunggu Kasir menerima (status_order = Menunggu Kasir)
        // dan belum dibatalkan. Urut berdasar tgl_order (BUKAN created_at).
        $waiting = DB::table('transaksi')
            ->join('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
            ->where('transaksi.status_order', 'Menunggu Kasir')
            ->where('transaksi.status_nota', '!=', 'Dibatalkan')
            ->select('transaksi.*', 'konsumen.nama_konsumen')
            ->orderBy('transaksi.tgl_order', 'desc')
            ->get();

        return view('kasir.waiting_list', compact('waiting'));
    }

    public function bayarForm($id)
    {
        $transaksi = DB::table('transaksi')
            ->join('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
            ->leftJoin('golongan_konsumen', 'konsumen.id_golongan', '=', 'golongan_konsumen.id_golongan')
            ->where('transaksi.id_transaksi', $id)
            ->select('transaksi.*', 'konsumen.nama_konsumen', 'konsumen.telepon', 'golongan_konsumen.nama_golongan')
            ->first();

        if (!$transaksi) {
            return redirect()->route('kasir.index')->with('error', 'Transaksi tidak ditemukan!');
        }

        // P0-2: nota batal tidak boleh dibuka form pembayarannya - hanya
        // bisa dilihat (riwayat). Cegah kasir membayar nota yang sudah dibatalkan.
        if ($transaksi->status_nota === 'Dibatalkan') {
            return redirect()->route('penjualan.show', $transaksi->id_transaksi)
                ->with('error', "Nota {$transaksi->nomor_nota} sudah DIBATALKAN - pembayaran tidak bisa diproses.");
        }

        // Gate: transaksi harus sudah diterima Kasir (status_order = Diterima Kasir)
        // agar form pembayaran bisa diproses. Jika belum, arahkan ke halaman terima.
        if ($transaksi->status_order !== 'Diterima Kasir') {
            return redirect()->route('kasir.index')
                ->with('error', "Transaksi {$transaksi->nomor_nota} belum diterima Kasir. Klik 'Terima Transaksi' terlebih dahulu.");
        }

        // LEFT JOIN (bukan INNER) - baris Jasa punya id_produk NULL (lihat
        // migrasi add_jasa_support_to_transaksi_detail). INNER JOIN produk
        // sebelumnya diam-diam membuang baris Jasa dari halaman ini sama
        // sekali, termasuk dari TOTAL NOTA - nota jadi tertagih kurang dari
        // seharusnya tanpa ada tanda apa pun ke kasir.
        $items = DB::table('transaksi_detail')
            ->leftJoin('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
            ->leftJoin('jasa', 'transaksi_detail.id_jasa', '=', 'jasa.id_jasa')
            ->where('transaksi_detail.id_transaksi', $id)
            ->where('transaksi_detail.status_spk', '!=', 'Batal')
            ->select(
                'transaksi_detail.*',
                'produk.nama_produk',
                'produk.metode_hitung',
                'jasa.nama_jasa'
            )
            ->get();

        // qty_hitung = satuan hitung SEBENARNYA yang dipakai untuk Harga per
        // item (m² untuk produk metode 'ukuran', pcs untuk 'quantity'/jasa) -
        // PERSIS rumus yang sama dengan HargaService/PenjualanController::
        // store()/StokReversalService & prosesBayar() di bawah. Dihitung di
        // sini (bukan dipercaya dari kolom qty mentah) supaya tampilan Harga
        // & preview Custom Harga di blade selalu cocok dengan apa yang nanti
        // benar-benar disimpan server saat submit.
        foreach ($items as $item) {
            $item->qty_hitung = ($item->metode_hitung === 'ukuran')
                ? round(((float) $item->p_ukuran * (float) $item->l_ukuran / 10000) * (float) $item->qty, 4)
                : (float) $item->qty;
        }

        $totalTerbayar = (float) (DB::table('pembayaran_riwayat')->where('id_transaksi', $id)->sum('nominal') ?? 0);
        $sisaTagihan = $transaksi->total_bayar - $totalTerbayar;

        // Rincian "uang yang benar-benar sudah diterima" - dipakai untuk
        // mengisi field Uang Diterima dengan nominal yang sama persis pada
        // nota yang sudah lunas (bukti pendataan, bukan entri baru), sekaligus
        // menampilkan metode pembayarannya (Tunai/Transfer/Debit/Deposit) di
        // bawah status. Tanpa daftar ini, form hanya tahu totalnya sehingga
        // metode yang mana tidak bisa ditampilkan.
        $penerimaan = DB::table('pembayaran_riwayat')
            ->where('id_transaksi', $id)
            ->orderBy('tgl_bayar')
            ->orderBy('id_pembayaran')
            ->select('nominal', 'metode', 'tgl_bayar', 'kembalian')
            ->get();

        // Status memakai aturan yang sama dengan seluruh halaman lain (lihat
        // App\Services\StatusPembayaran) - bukan kolom salinan, supaya form
        // tidak pernah menampilkan "LUNAS" di satu baris dan "BELUM BAYAR" di
        // baris paling bawah untuk nota yang sama.
        $statusPembayaran = StatusPembayaran::dari((float) $transaksi->total_bayar, $totalTerbayar);
        $notaSudahLunas   = $statusPembayaran === StatusPembayaran::LUNAS && $totalTerbayar > 0;

        $banks = DB::table('bank_perusahaan')->orderBy('nama_bank')->get();
        $saldoDeposit = KonsumenDepositController::saldoUntuk($transaksi->id_konsumen);

        // Konsumen ini PERNAH setor deposit tapi saldoDeposit sekarang 0 -
        // dibedakan dari "memang tidak pernah punya deposit sama sekali",
        // supaya kasir dapat keterangan jelas kenapa opsi Deposit tidak bisa
        // dipakai (lihat pesan di form_pembayaran.blade.php), bukan cuma
        // opsi yang diam-diam disabled tanpa alasan.
        $depositSudahHabis = $saldoDeposit <= 0 && DB::table('konsumen_deposit')
            ->where('id_konsumen', $transaksi->id_konsumen)
            ->where('tipe', 'Setoran')
            ->exists();

        return view('kasir.form_pembayaran', compact('transaksi', 'items', 'totalTerbayar', 'sisaTagihan', 'banks', 'saldoDeposit', 'depositSudahHabis', 'penerimaan', 'statusPembayaran', 'notaSudahLunas'));
    }

    /**
     * Terima Transaksi - business action POST yang mengubah status_order
     * Menunggu Kasir -> Diterima Kasir secara atomik + mengaktifkan SPK
     * idempotent. GET bayarForm() tetap READ-only.
     */
    public function terimaTransaksi(Request $request, $id)
    {
        if (!$request->isMethod('post')) {
            abort(405, 'Method Not Allowed. Gunakan POST untuk menerima transaksi.');
        }

        $request->validate(['submit_id' => 'required|string|max:100']);

        $submitId = (string) $request->input('submit_id');
        $sudahDiproses = (array) $request->session()->get('terima_submit_id', []);
        if (in_array($submitId, $sudahDiproses, true)) {
            return $this->responTerima($request, 'error', 'Transaksi ini sudah pernah diterima. Cek Monitoring Kasir sebelum mencoba lagi.');
        }

        DB::beginTransaction();
        try {
            $transaksi = DB::table('transaksi')
                ->where('id_transaksi', $id)
                ->lockForUpdate()
                ->first();

            if (!$transaksi) {
                throw new \Exception('Transaksi tidak ditemukan.');
            }
            if (($transaksi->status_nota ?? null) === 'Dibatalkan' || ($transaksi->status_order ?? null) === 'Dibatalkan') {
                throw new \Exception('Transaksi tidak dapat diterima karena sudah dibatalkan.');
            }
            if (($transaksi->status_order ?? 'Menunggu Kasir') === 'Diterima Kasir') {
                throw new \Exception('Transaksi sudah diterima oleh Kasir.');
            }

            // Guard state machine tunggal - Diterima->Menunggu & Batal->Terima ditolak di sini.
            \App\Services\StatusTransitionGuard::validateStatusOrder(
                (string) ($transaksi->status_order ?? 'Menunggu Kasir'),
                'Diterima Kasir'
            );

            DB::table('transaksi')
                ->where('id_transaksi', $id)
                ->update(['status_order' => 'Diterima Kasir', 'updated_at' => now()]);

            // SPK idempotent: detail 'Antre' SUDAH dibuat saat nota disimpan
            // (PenjualanController::store) dalam transaksi yang sama - di sini
            // TIDAK ada insert baru, jadi retry tidak bisa membuat SPK #124.
            // Touch updated_at saja agar baris aktif tercatat tersentuh.
            DB::table('transaksi_detail')
                ->where('id_transaksi', $id)
                ->where('status_spk', 'Antre')
                ->update(['updated_at' => now()]);

            $sudahDiproses[] = $submitId;
            while (count($sudahDiproses) > 50) {
                array_shift($sudahDiproses);
            }
            $request->session()->put('terima_submit_id', $sudahDiproses);

            DB::commit();

            return $this->responTerima($request, 'success', 'Transaksi berhasil diterima. SPK siap diproses.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            $pesan = collect($e->errors())->flatten()->first() ?: 'Transaksi tidak dapat diterima karena statusnya sudah berubah.';
            return $this->responTerima($request, 'error', $pesan);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->responTerima($request, 'error', $e->getMessage());
        }
    }

    private function responTerima(Request $request, string $tipe, string $pesan)
    {
        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(
                ['ok' => $tipe === 'success', 'message' => $pesan],
                $tipe === 'success' ? 200 : 422
            );
        }

        return $tipe === 'success'
            ? redirect()->route('kasir.index')->with('success', $pesan)
            : back()->with('error', $pesan);
    }

    public function prosesBayar(Request $request)
    {
        // F1 (audit putaran 2): idempotensi per-SUBMIT, sebelum menyentuh
        // database. submit_id unik diisi JS tiap kali tombol bayar disubmit
        // (lihat form_pembayaran.blade.php); kalau id yang SAMA datang lagi,
        // itu klik ganda / retry jaringan - ditolak. Pembayaran berkala yang
        // sah (DP dulu, pelunasan kemudian) tetap lolos karena tiap klik
        // menghasilkan submit_id berbeda. Sesi Laravel (database driver)
        // mengerucutkan request bersamaan dari user yang sama, jadi dua
        // request dengan id identik selalu dieksekusi serial.
        $submitId = (string) $request->input('submit_id');
        if ($submitId !== '') {
            $sudahDiproses = (array) $request->session()->get('pembayaran_submit_id', []);
            if (in_array($submitId, $sudahDiproses, true)) {
                return back()->with('error', 'Pembayaran ini sudah pernah diproses. Cek Monitoring Kasir sebelum mencoba lagi.');
            }
        }

        DB::beginTransaction();
        try {
            $id_transaksi = $request->id_transaksi;

            // Kunci nota HARUS menjadi bacaan PERTAMA di transaksi ini. MySQL (REPEATABLE READ) memotret
            // data pada bacaan pertama; bila kunci baru diambil setelah bacaan lain, proses yang menunggu
            // kunci tetap menghitung "total sudah dibayar" dari potret lama dan ikut mencatat pembayaran.
            // Terbukti dengan uji proses paralel: 6 pelunasan bersamaan semuanya tercatat (6x tagihan).
            $notaTerkunci = DB::table('transaksi')->where('id_transaksi', $id_transaksi)->lockForUpdate()->first();
            if (!$notaTerkunci) {
                throw new \Exception('Nota tidak ditemukan.');
            }
            // Gate order lifecycle: pembayaran hanya untuk nota yang sudah
            // diterima kasir. Mencegah bypass bayarForm via POST langsung.
            if (($notaTerkunci->status_order ?? 'Menunggu Kasir') !== 'Diterima Kasir') {
                throw new \Exception("Nota {$notaTerkunci->nomor_nota} belum diterima Kasir - klik 'Terima Transaksi' di Antrian Baru terlebih dahulu.");
            }
            $uangDiterimaSekarang = (float) (str_replace('.', '', $request->jumlah_bayar) ?: 0);
            $kembalian = 0.0;
            // Diskon nota tersimpan di transaksi. Isian "0" berarti diskon sengaja dihapus; isian yang tidak dikirim sama
            // sekali berarti diskon yang sudah disepakati tetap berlaku (bukan kembali ke harga sebelum diskon).
            $diskon = $request->filled('diskon')
                ? (float) str_replace('.', '', $request->diskon)
                : (float) ($notaTerkunci->diskon ?? 0);
            $metode = $request->metode_pembayaran;

            if ($uangDiterimaSekarang < 0) {
                throw new \Exception("Jumlah bayar tidak boleh negatif.");
            }
            if ($diskon < 0) {
                throw new \Exception("Diskon tidak boleh negatif.");
            }
            if (!in_array($metode, ['Tunai', 'Debit', 'Transfer', 'Deposit', 'QRIS'], true)) {
                throw new \Exception("Metode pembayaran tidak dikenali.");
            }

            // id_bank hanya relevan untuk Transfer/QRIS - divalidasi ke tabel
            // bank_perusahaan supaya id yang dipalsukan dari client tidak
            // lolos ke foreign key pembayaran_riwayat.id_bank.
            $idBank = null;
            if (in_array($metode, ['Transfer', 'QRIS'], true) && $request->filled('id_bank')) {
                $idBank = (int) $request->id_bank;
                if (!DB::table('bank_perusahaan')->where('id_bank', $idBank)->exists()) {
                    throw new \Exception("Rekening bank tujuan tidak ditemukan.");
                }
            }

            // 1. Update Detail Barang ("Custom Harga" per item). Kasir memang
            // sengaja diberi wewenang untuk menyesuaikan harga & biaya di sini -
            // itu TIDAK dihapus, malah diperluas supaya biaya_cetak/biaya_desain/
            // biaya_finishing juga bisa dikoreksi (mis. operator lupa
            // menambahkannya saat nota dibuat), plus "potongan" (diskon per
            // item) untuk kasus itu juga.
            //
            // SEBELUMNYA baris ini HANYA mengganti subtotal = harga_baru * qty,
            // diam-diam MEMBUANG biaya_cetak/biaya_finishing yang sudah
            // tersimpan (kolom aslinya tidak disentuh sama sekali) - subtotal
            // jadi tidak sinkron lagi dengan biaya_cetak/finishing yang masih
            // tampil di SPK/Produksi/cetak nota. Sekarang SEMUA komponen
            // (harga, cetak, desain, finishing, potongan) dibaca & disimpan
            // bersamaan supaya subtotal selalu konsisten dengan kolom-kolom
            // penyusunnya.
            //
            // qty dipakai dari data transaksi_detail yang tersimpan (bukan
            // dari field tersembunyi di request) supaya tidak bisa dikalikan
            // dengan qty palsu untuk memperbesar/memperkecil subtotal di luar
            // penyesuaian harga yang sesungguhnya dimaksud.
            if ($request->items) {
                foreach ($request->items as $id_detail => $data) {
                    // lockForUpdate() - sudah di dalam transaction luar
                    // (baris 171), cuma menambah lock supaya klik ganda
                    // "Simpan" pada form koreksi harga yang sama tidak
                    // saling menimpa hasil hitungan biaya_cetak/subtotal.
                    $detailRow = DB::table('transaksi_detail')
                        ->where('id_detail', $id_detail)
                        ->where('id_transaksi', $id_transaksi)
                        ->lockForUpdate()
                        ->first();

                    if (!$detailRow) {
                        continue;
                    }

                    // Biaya Cetak SEKARANG komponen UTAMA subtotal (menggantikan
                    // "Harga per satuan hitung x qty", bukan lagi ditambahkan di
                    // atasnya - lihat catatan yang sama di PenjualanController::
                    // store()). "Harga" per-unit yang dulu dikalikan qty di sini
                    // TIDAK LAGI dipakai untuk hitungan - field itu murni acuan/
                    // referensi Price List di UI, tidak lagi disetorkan ke
                    // subtotal supaya tidak dobel-hitung dengan Biaya Cetak.
                    $bersihkan = fn ($v) => (float) str_replace('.', '', (string) ($v ?? 0));

                    $biayaCetakBaru = $bersihkan($data['biaya_cetak'] ?? $detailRow->biaya_cetak);
                    $biayaDesainBaru = $bersihkan($data['biaya_desain'] ?? $detailRow->biaya_desain);
                    $biayaFinishingBaru = $bersihkan($data['biaya_finishing'] ?? $detailRow->biaya_finishing);
                    $potonganBaru = $bersihkan($data['potongan'] ?? 0);

                    // JARING PENGAMAN: item LAMA (biaya_cetak masih 0 di
                    // database, dari sebelum Biaya Cetak jadi komponen
                    // utama) yang hidden input-nya di client belum sempat
                    // direkonsiliasi (lihat reconcileSemuaBiayaCetak() di
                    // form_pembayaran.blade.php) TIDAK BOLEH diam-diam
                    // menol-kan subtotal yang sudah ada cuma karena ketiga
                    // field biaya ini kosong di request - persis bug yang
                    // pernah membuat "harga di rincian barang jadi 0 setelah
                    // disimpan" (termasuk semua proses DP/Deposit). Kalau
                    // ketiganya 0 TAPI baris ini sudah punya subtotal
                    // tersimpan > 0, dipulihkan dulu dari situ - diperlakukan
                    // sebagai "belum sempat disentuh", bukan sengaja
                    // dikosongkan (potongan lama ikut dikembalikan supaya
                    // hasilnya persis subtotal yang sudah ada sebelum baris
                    // formula di bawah menghitung ulang dengan potongan BARU
                    // dari request ini).
                    if ($biayaCetakBaru <= 0 && $biayaDesainBaru <= 0 && $biayaFinishingBaru <= 0 && (float) $detailRow->subtotal > 0) {
                        $biayaCetakBaru = (float) $detailRow->subtotal + (float) $detailRow->potongan;
                    }

                    foreach (['Biaya cetak' => $biayaCetakBaru, 'Biaya desain' => $biayaDesainBaru, 'Biaya finishing' => $biayaFinishingBaru, 'Potongan' => $potonganBaru] as $label => $nilai) {
                        if ($nilai < 0) {
                            throw new \Exception("{$label} tidak boleh negatif.");
                        }
                    }

                    $subtotalBaru = $biayaCetakBaru + $biayaDesainBaru + $biayaFinishingBaru - $potonganBaru;

                    if ($subtotalBaru < 0) {
                        throw new \Exception("Potongan untuk item \"{$detailRow->nama_file}\" tidak boleh lebih besar dari total item itu sendiri.");
                    }

                    // Komisi desainer mengikuti biaya desain. Persen tetap dari saat nota dibuat; item yang baru diberi biaya
                    // desain memakai desainer default dan kelasnya sekarang. Komisi yang sudah dibayar mengunci biaya desain.
                    $komisi = [];
                    if (abs($biayaDesainBaru - (float) $detailRow->biaya_desain) > 0.001) {
                        if ($detailRow->komisi_status === 'Dibayar') {
                            throw new \Exception("Komisi desain item \"{$detailRow->nama_file}\" sudah dibayar ke desainer - biaya desain tidak bisa diubah lagi.");
                        }
                        $idDesainer = $detailRow->id_desainer ?: ($biayaDesainBaru > 0 ? \App\Services\DesainKomisiService::tentukanDesainer(null) : null);
                        $persen = $detailRow->id_desainer && (float) $detailRow->biaya_desain > 0
                            ? (float) $detailRow->komisi_desain_persen
                            : \App\Services\DesainKomisiService::hitung($idDesainer, $biayaDesainBaru)['persen'];
                        $nilaiKomisi = $idDesainer && $biayaDesainBaru > 0 ? round($biayaDesainBaru * $persen / 100, 2) : 0.0;
                        $komisi = [
                            'id_desainer' => $idDesainer,
                            'komisi_desain_persen' => $persen,
                            'komisi_desain' => $nilaiKomisi,
                            'komisi_status' => $nilaiKomisi > 0 ? 'Belum' : null,
                        ];
                    }

                    DB::table('transaksi_detail')->where('id_detail', $id_detail)->update([
                        'biaya_cetak'     => $biayaCetakBaru,
                        'biaya_desain'    => $biayaDesainBaru,
                        'biaya_finishing' => $biayaFinishingBaru,
                        'potongan'        => $potonganBaru,
                        'subtotal'        => $subtotalBaru,
                    ] + $komisi);
                }
            }

            $totalNotaBaru = DB::table('transaksi_detail')
                ->where('id_transaksi', $id_transaksi)
                ->where('status_spk', '!=', 'Batal')
                ->sum('subtotal');

            if ($diskon > $totalNotaBaru) {
                throw new \Exception("Diskon tidak boleh lebih besar dari total nota.");
            }

            // Pengaman salah ketik: diskon yang menghabiskan SELURUH total nota (tagihan jadi Rp 0) hampir selalu salah
            // input dan membuat total nota tampil Rp 0 di Monitoring Kasir. Nota gratis dibuat lewat potongan item / nominal wajar.
            if ($totalNotaBaru > 0 && $diskon >= $totalNotaBaru) {
                throw new \Exception("Diskon sama dengan total nota - tagihan akan menjadi Rp 0. Periksa kembali nominal diskon.");
            }

            $grandTotalFinal = $totalNotaBaru - $diskon;

            // Diskon/potongan/biaya item tidak boleh menurunkan total di bawah uang yang sudah diterima: piutang nota
            // menjadi minus dan kelebihannya menggantung tanpa kembalian maupun deposit.
            $sudahDiterima = (float) DB::table('pembayaran_riwayat')->where('id_transaksi', $id_transaksi)->sum('nominal');
            if ($grandTotalFinal < $sudahDiterima - 0.01) {
                throw new \Exception('Total nota baru (Rp ' . number_format($grandTotalFinal, 0, ',', '.') . ') lebih kecil dari uang yang sudah diterima (Rp '
                    . number_format($sudahDiterima, 0, ',', '.') . '). Kurangi diskon/potongan, atau batalkan pembayarannya dulu lewat void.');
            }

            // P0-2: nota yang sudah Dibatalkan TIDAK boleh menerima pembayaran
            // baru (form bisa saja dibuka sebelum nota dibatalkan, disubmit
            // sesudahnya). Submit tanpa uang (koreksi harga/status) tetap
            // diperbolehkan supaya alur pemeliharaan tidak macet.
            // F1: lockForUpdate() - dua pembayaran nyaris bersamaan untuk nota
            // yang sama tidak boleh sama-sama membaca status/total-terbayar
            // lama sebelum salah satu commit (guard Dibatalkan & overpayment
            // jadi serial per-nota, tidak ada dobel pembayaran).
            $transaksiRow = DB::table('transaksi')->where('id_transaksi', $id_transaksi)->lockForUpdate()->first();

            if (!$transaksiRow) {
                throw new \Exception("Nota tidak ditemukan.");
            }
            if ($uangDiterimaSekarang > 0 && $transaksiRow->status_nota === 'Dibatalkan') {
                throw new \Exception("Nota {$transaksiRow->nomor_nota} sudah DIBATALKAN - tidak bisa menerima pembayaran.");
            }

            // P0-4: cegah overpayment - total bayar baru tidak boleh melebihi
            // sisa tagihan yang masih ada (potongan/diskon boleh menurunkan
            // sisa tagihan; yang dicek adalah hasil akhirnya).
            if ($uangDiterimaSekarang > 0) {
                $totalBayarLama = (float) DB::table('pembayaran_riwayat')->where('id_transaksi', $id_transaksi)->sum('nominal');
                $sisaTagihan = $grandTotalFinal - $totalBayarLama;
                if ($uangDiterimaSekarang - $sisaTagihan > 0.01) {
                    // Uang tunai yang diserahkan boleh lebih besar dari tagihan: selisihnya kembalian, bukan
                    // pembayaran. Yang masuk ke riwayat, kas, dan jurnal tetap sebesar sisa tagihan. Nota yang
                    // sudah lunas tetap ditolak, dan metode non-tunai tidak punya kembalian.
                    if ($metode !== 'Tunai' || $sisaTagihan <= 0.01) {
                        throw new \Exception("Pembayaran melebihi sisa tagihan. Sisa tagihan: Rp " . number_format(max(0, $sisaTagihan), 0, ',', '.') . ".");
                    }
                    $kembalian = round($uangDiterimaSekarang - $sisaTagihan, 2);
                    $uangDiterimaSekarang = round($sisaTagihan, 2);
                }
            }

            // 2. Catat Pembayaran (Hanya jika uang > 0)
            if ($uangDiterimaSekarang > 0) {
                $kas = KasSesiService::aktifUntukTransaksi();

                if (!$kas) {
                    throw new \Exception("Kasir belum dibuka! Silakan buka kas terlebih dahulu.");
                }

                // Kas aktif itu MILIK SATU ORANG SAJA (lihat catatan di
                // storeKas()) - kalau yang sedang login BUKAN pemilik sesi
                // kas yang aktif (mis. Administrator sementara Kasir sedang
                // buka kas), pembayaran ditolak SAMA SEKALI, bukan diam-diam
                // tercatat ke sesi kas orang lain seperti sebelumnya.
                if ((int) $kas->id_user !== (int) auth()->id()) {
                    throw new \Exception("Anda tidak dapat melakukan transaksi. Cek kembali saldo kas kasir Anda. Kas kasir akan terus berjalan sebelum terjadi closing kas kasir.");
                }

                $nomorNota = $transaksiRow->nomor_nota;

                if ($metode === 'Deposit') {
                    // Lock baris konsumen sebagai mutex SEBELUM hitung saldo -
                    // saldoUntuk() cuma SUM() atas baris konsumen_deposit (tidak
                    // ada satu baris "saldo" yang bisa di-lockForUpdate langsung),
                    // jadi tanpa ini dua pembayaran Deposit yang hampir bersamaan
                    // untuk konsumen yang sama bisa sama-sama membaca saldo lama
                    // yang sama sebelum salah satu insert 'Pemakaian'-nya commit -
                    // deposit bisa terpakai melebihi yang disetor (double-spend).
                    DB::table('konsumen')->where('id_konsumen', $transaksiRow->id_konsumen)->lockForUpdate()->first();

                    // Bukan uang tunai/transfer baru - ini menarik saldo deposit
                    // yang sudah disetor sebelumnya (lihat KonsumenDepositController).
                    // Validasi saldo di sini juga (bukan cuma client-side) supaya
                    // tidak bisa "bayar" dari saldo yang tidak ada.
                    $saldoDeposit = KonsumenDepositController::saldoUntuk((int) $transaksiRow->id_konsumen, true);
                    if ($uangDiterimaSekarang > $saldoDeposit) {
                        throw new \Exception("Saldo deposit tidak cukup. Tersedia: Rp " . number_format($saldoDeposit, 0, ',', '.'));
                    }

                    DB::table('konsumen_deposit')->insert([
                        'id_konsumen'  => $transaksiRow->id_konsumen,
                        'tipe'         => 'Pemakaian',
                        'nominal'      => $uangDiterimaSekarang,
                        'metode_bayar' => 'Deposit',
                        'keterangan'   => "Pembayaran nota {$nomorNota}",
                        'tgl_deposit'  => now()->toDateString(),
                        'id_transaksi' => $id_transaksi,
                        'id_user'      => Auth::id(),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }

                $idBayarRiwayat = DB::table('pembayaran_riwayat')->insertGetId([
                    'id_transaksi' => $id_transaksi,
                    'nominal'      => $uangDiterimaSekarang,
                    'metode'       => $metode,
                    'id_bank'      => $idBank,
                    'tgl_bayar'    => now(),
                    'keterangan'   => "Pembayaran via Kasir",
                    // Catatan serah uang untuk nota: yang diserahkan pelanggan dan kembaliannya.
                    'uang_diterima' => round($uangDiterimaSekarang + $kembalian, 2),
                    'kembalian'    => $kembalian,
                ]);

                // pembayaran_riwayat tidak punya kolom pengguna/sesi kas, jadi
                // "siapa yang menerima uang ini" hanya bisa dijawab lewat audit.
                \App\Services\AuditLogger::log(
                    'created',
                    'pembayaran_riwayat',
                    (int) $idBayarRiwayat,
                    null,
                    ['nomor_nota' => $nomorNota, 'nominal' => (float) $uangDiterimaSekarang, 'metode' => $metode, 'id_bank' => $idBank, 'id_kas' => (int) $kas->id_kas],
                    "Pembayaran nota {$nomorNota} ({$metode}) Rp " . number_format((float) $uangDiterimaSekarang, 0, ',', '.')
                );

                // Update Saldo Kas - Deposit TIDAK menambah kas hari ini, karena
                // uangnya sudah masuk kas saat setoran deposit dulu, bukan hari ini.
                if ($metode == 'Tunai') {
                    DB::table('kas_harian')->where('id_kas', $kas->id_kas)->increment('saldo_akhir', $uangDiterimaSekarang);
                } elseif (in_array($metode, ['Transfer', 'QRIS'], true)) {
                    DB::table('kas_harian')->where('id_kas', $kas->id_kas)->increment('total_transfer', $uangDiterimaSekarang);
                }

                if ($metode === 'Deposit') {
                    AccountingService::postPembayaranDeposit($nomorNota, $uangDiterimaSekarang);
                } else {
                    AccountingService::postPembayaran($nomorNota, $uangDiterimaSekarang, $metode);
                }
            }

            // 3. Update Status - DIHITUNG JUJUR dari pembayaran_riwayat yang
            // BENAR-BENAR ada, bukan dipaksa jadi DP begitu form ini
            // disubmit apa pun isinya. Sebelumnya status SELALU dipaksa jadi
            // DP (bahkan untuk klik "Simpan" dengan uang 0 - lihat riwayat
            // git), jadi ada transaksi berstatus DP di database TANPA satu
            // pun baris pembayaran_riwayat menyertainya - kalau dibuka lagi
            // kelihatan seperti "nilai DP-nya hilang" padahal memang tidak
            // pernah ada uang yang tercatat sama sekali. Sekarang DP hanya
            // bisa dicapai lewat pembayaran yang sungguhan tercatat > 0;
            // kalau belum ada uang masuk sama sekali, tetap "Belum Bayar"
            // (sekarang aman ditinggal di status ini - Monitoring Kasir
            // sudah menampilkan & bisa memfilter Belum Bayar juga).
            $totalTerbayarSemua = DB::table('pembayaran_riwayat')->where('id_transaksi', $id_transaksi)->sum('nominal') ?? 0;

            if ($totalTerbayarSemua >= $grandTotalFinal && $grandTotalFinal > 0) {
                $status = 'Lunas';
            } elseif ($totalTerbayarSemua > 0) {
                $status = 'DP';
            } else {
                $status = 'Belum Bayar';
            }

            // Total nota berubah (diskon/potongan/biaya item): piutang & pendapatan di jurnal ikut disamakan.
            if ($transaksiRow->status_nota !== 'Dibatalkan') {
                AccountingService::sesuaikanTotalNota($transaksiRow->nomor_nota, (float) $grandTotalFinal);
            }

            // §9: guard status pembayaran - menolak mundur (Lunas -> DP/Belum Bayar).
            \App\Services\StatusTransitionGuard::validateStatusPembayaran(
                (string) $transaksiRow->status_pembayaran,
                $status
            );

            $updateData = [
                'total_bayar'       => $grandTotalFinal,
                'diskon'            => $diskon,
                'status_pembayaran' => $status,
                'updated_at'        => now()
            ];

            // metode_pembayaran cuma dicatat kalau nota INI memang baru
            // menerima uang sungguhan - jangan sampai klik "Simpan" (0
            // rupiah) diam-diam menuliskan metode dari radio button yang
            // sedang aktif di form, padahal tidak ada uang yang berpindah
            // lewat metode itu sama sekali.
            if ($uangDiterimaSekarang > 0) {
                $updateData['metode_pembayaran'] = $metode;
            }

            DB::table('transaksi')->where('id_transaksi', $id_transaksi)->update($updateData);

            // P0-4/F1: catat submit_id ini sudah diproses (anti klik-ganda
            // server-side). Dibatasi 50 id terakhir supaya sesi tidak tumbuh
            // tanpa batas.
            if ($submitId !== '') {
                $sudahDiproses = (array) $request->session()->get('pembayaran_submit_id', []);
                $sudahDiproses[] = $submitId;
                while (count($sudahDiproses) > 50) {
                    array_shift($sudahDiproses);
                }
                $request->session()->put('pembayaran_submit_id', $sudahDiproses);
            }

            DB::commit();
            \App\Support\JalurLanjut::set('Pekerjaan masuk antrian produksi', 'spk.index', 'Lihat Antrian SPK');
            if ($request->has('cetak_nota') && $request->cetak_nota) {
                return redirect()->route('kasir.print-opsi', $id_transaksi)->with('success', 'Transaksi berhasil diproses.'
                    . ($kembalian > 0 ? ' Kembalian untuk pelanggan: Rp ' . number_format($kembalian, 0, ',', '.') . '.' : ''));
            }
            return redirect()->route('kasir.index')->with('success', 'Transaksi berhasil diproses ke Monitoring.'
                . ($kembalian > 0 ? ' Kembalian untuk pelanggan: Rp ' . number_format($kembalian, 0, ',', '.') . '.' : ''));

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', \App\Support\PesanError::untuk($e, 'Gagal'));
        }
    }

    /** Pemilik sesi atau Administrator boleh closing. */
    private static function bolehTutup(object $kas): bool
    {
        return (int) $kas->id_user === (int) auth()->id() || optional(auth()->user()->role)->nama_role === 'Administrator';
    }

    /**
     * Manajemen Kas Harian
     */
    public function kelolaKas()
    {
        $hari_ini = date('Y-m-d');
        $kasAktif = KasSesiService::aktif();
        $mulaiSesi = $kasAktif ? KasSesiService::mulai($kasAktif) : now()->startOfDay();

        // "Status Rolling Saldo Akhir" - kalau sesi kas yang PALING BARU
        // ditutup (tanggal berapa pun sebelum hari ini, bukan cuma kemarin
        // persis - jaga-jaga ada hari libur/lewat tanpa buka kas) ditutup
        // dengan status 'Rolling', saldo fisik hasil hitungannya dibawa
        // lanjut jadi SARAN saldo awal hari ini (tetap bisa ditimpa manual
        // di prompt "Buka Saldo Kasir" - bukan otomatis dipaksakan).
        $saldoRolling = null;
        $kasSebelumnya = null;
        if (!$kasAktif) {
            // Tanpa filter tanggal, sama seperti guard di storeKas(). Filter
            // tgl_kas < hari_ini membuat sesi yang baru saja ditutup di hari
            // yang sama tidak terlihat, sehingga saran menunjuk ke sesi yang
            // sudah lama - padahal uangnya masih di laci itu juga.
            $kasSebelumnya = DB::table('kas_harian')
                ->where('status_lock', 'closed')
                ->orderByDesc('waktu_tutup')
                ->first();
            if ($kasSebelumnya && $kasSebelumnya->status_rolling === 'Rolling') {
                $saldoRolling = (float) $kasSebelumnya->saldo_fisik;
            }
        }

        $mutasi = DB::table('pembayaran_riwayat')
            ->join('transaksi', 'pembayaran_riwayat.id_transaksi', '=', 'transaksi.id_transaksi')
            ->join('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
            // Bank tujuan untuk pembayaran Transfer - sebelumnya halaman ini
            // tidak menampilkan rekening sama sekali padahal bank_perusahaan
            // terisi per pembayaran. Null tetap aman (pembayaran Tunai/Deposit).
            ->leftJoin('bank_perusahaan', 'pembayaran_riwayat.id_bank', '=', 'bank_perusahaan.id_bank')
            ->leftJoin(
                DB::raw('(SELECT id_transaksi, SUM(nominal) AS jml_terbayar FROM pembayaran_riwayat GROUP BY id_transaksi) pr'),
                'pr.id_transaksi', '=', 'transaksi.id_transaksi'
            )
            ->where('pembayaran_riwayat.tgl_bayar', '>=', $mulaiSesi)
            // Status turunan, bukan kolom salinan - supaya badge di daftar
            // mutasi dan badge di Monitoring Kasir tidak pernah berbeda untuk
            // nota yang sama.
            ->select('pembayaran_riwayat.*', 'transaksi.nomor_nota', 'konsumen.nama_konsumen', 'bank_perusahaan.nama_bank', 'bank_perusahaan.nomor_rekening')
            ->selectRaw(StatusPembayaran::sqlCase('transaksi.total_bayar', 'COALESCE(pr.jml_terbayar, 0)') . ' as display_status_pembayaran')
            ->orderBy('pembayaran_riwayat.tgl_bayar', 'desc')
            ->get();

        // "Kas Tidak Terduga" - mutasi tunai yang tidak terikat nota (lihat
        // migrasi create_kas_manual_table). Ditampilkan terpisah dari
        // $mutasi (yang murni pembayaran_riwayat/nota) supaya kolom-kolom
        // yang tidak relevan (Nota, Konsumen, Metode) tidak perlu dipaksakan
        // ada isinya untuk baris ini.
        $mutasiManual = DB::table('kas_manual')
            ->join('users', 'kas_manual.id_user', '=', 'users.id_user')
            ->when($kasAktif, fn ($q) => $q->where('kas_manual.id_kas', $kasAktif->id_kas), fn ($q) => $q->where('kas_manual.waktu', '>=', $mulaiSesi))
            ->select('kas_manual.*', 'users.username as dicatat_oleh')
            ->orderBy('kas_manual.waktu', 'desc')
            ->get();

        $data_kas = collect([(object) [
            'nominal_tunai' => $mutasi->where('metode', 'Tunai')->sum('nominal'),
            'nominal_transfer' => $mutasi->where('metode', 'Transfer')->sum('nominal'),
            'nominal_debit' => $mutasi->where('metode', 'Debit')->sum('nominal'),
            'nominal_manual_masuk' => $mutasiManual->where('status', 'Masuk')->sum('nominal'),
            'nominal_manual_keluar' => $mutasiManual->where('status', 'Keluar')->sum('nominal'),
        ]]);

        // Sesi yang baru saja ditutup (id_kas datang dari flash simpanClosing).
        // Kalau kasir sudah membuka sesi baru di sebelum sempat mencetak, flash
        // ini sudah habis. Untuk sesi yang barusan ditutup, pintasan ke
        // Laporan Kas (filter status_lock=closed) ada di halaman Kelola Kas, dan
        // Laporan Kas itu punya tombol print per sesi.
        $kasBaruDitutup = null;
        if ($idBaru = session('kas_baru_ditutup')) {
            $kasBaruDitutup = DB::table('kas_harian')
                ->leftJoin('users', 'kas_harian.id_user', '=', 'users.id_user')
                ->leftJoin('karyawan', 'users.id_karyawan', '=', 'karyawan.id_karyawan')
                ->where('kas_harian.id_kas', $idBaru)
                ->select('kas_harian.*', DB::raw('COALESCE(karyawan.nama_karyawan, users.username) as nama_kasir'))
                ->first();
        }

        return view('kasir.kelola_kas', compact(
            'kasAktif',
            'mutasi',
            'mutasiManual',
            'data_kas',
            'saldoRolling',
            'kasSebelumnya',
            'kasBaruDitutup'
        ));
    }

    /**
     * "Kas Tidak Terduga" - catat mutasi tunai yang tidak terikat nota
     * (pengeluaran operasional dadakan, kas masuk di luar penjualan).
     * Mengikuti referensi form "Alur Kas Kasir": Keterangan, Status
     * (Debet/Kas Masuk atau Kredit/Kas Keluar), Nominal - selalu Tunai
     * (uang fisik di laci), tidak ada pilihan metode seperti pembayaran
     * nota.
     */
    public function storeKasManual(Request $request)
    {
        $request->validate([
            'keterangan' => 'required|string|max:500',
            'status'     => 'required|in:Masuk,Keluar',
            'nominal'    => 'required|numeric|min:1',
        ]);

        DB::beginTransaction();
        try {
            $kas = KasSesiService::aktifUntukTransaksi();

            if (!$kas) {
                throw new \Exception(KasSesiService::alasanTerblokir() ?? 'Kasir belum dibuka! Silakan buka kas terlebih dahulu.');
            }

            // Sama seperti prosesBayar() - kas manual (mutasi tunai di luar
            // nota) cuma boleh dicatat oleh PEMILIK sesi kas yang aktif.
            if ((int) $kas->id_user !== (int) auth()->id()) {
                throw new \Exception('Anda tidak dapat melakukan transaksi. Cek kembali saldo kas kasir Anda. Kas kasir akan terus berjalan sebelum terjadi closing kas kasir.');
            }

            if ($request->status === 'Keluar') {
                KasSesiService::pastikanLaciCukup($kas, (float) $request->nominal);
            }

            $idKasManual = DB::table('kas_manual')->insertGetId([
                'id_kas'      => $kas->id_kas,
                'waktu'       => now(),
                'keterangan'  => $request->keterangan,
                'status'      => $request->status,
                'nominal'     => $request->nominal,
                'id_user'     => Auth::id(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            AccountingService::postKasManual('KAS-MANUAL-' . $idKasManual, (float) $request->nominal, $request->status);

            DB::commit();
            return redirect()->route('kasir.kelola')->with('success', 'Mutasi kas tidak terduga berhasil dicatat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('kasir.kelola')->with('error', \App\Support\PesanError::untuk($e, 'Gagal mencatat'));
        }
    }

   public function storeKas(Request $request)
{
    // P0-1: endpoint sekarang POST + CSRF (lihat routes/web.php &
    // kelola_kas.blade.php). Nominal divalidasi numerik di server -
    // sebelumnya cuma 'required' lalu langsung str_replace titik.
    $request->validate([
        'jumlah' => 'required|numeric|min:0',
        // Wajib HANYA kalau saldo awal berbeda dari isi laci sesi sebelumnya.
        // Dicek di dalam transaksi, bukan di sini, karena bergantung pada
        // sesi mana yang terakhir ditutup.
        'keterangan_pembukaan' => 'nullable|string|max:500',
    ]);
    $nominal = (float) str_replace('.', '', $request->jumlah);
    $keteranganPembukaan = (string) $request->keterangan_pembukaan;

    if ($nominal < 0) {
        return redirect()->route('kasir.kelola')->with('error', 'Nominal saldo awal tidak boleh negatif.');
    }

    // Hari Minggu = kas libur (bisa dinonaktifkan admin di Pengaturan
    // Aplikasi) - dicek PALING AWAL sebelum guard eksklusivitas di bawah,
    // supaya pesannya jelas menunjuk alasan hari libur, bukan "sudah dibuka
    // orang lain" yang membingungkan.
    if ($alasanLibur = \App\Services\JamKerjaService::alasanTutup()) {
        return redirect()->route('kasir.kelola')->with('error', 'Open Saldo tidak bisa dilakukan. ' . $alasanLibur);
    }

    // Open saldo bersifat EKSKLUSIF - hanya SATU sesi kas boleh aktif dalam
    // satu waktu, SIAPA PUN rolenya (termasuk Administrator). Sebelumnya
    // pengecekan ini hanya melihat kas milik user yang sedang login sendiri
    // (where id_user = auth()->id()), jadi Administrator tetap bisa buka
    // sesi kas KEDUA sementara punya Kasir masih terbuka - akibatnya dua
    // sesi kas aktif bersamaan, dan prosesBayar()/storeKasManual() (yang
    // mencari kas aktif tanpa peduli siapa pemiliknya) diam-diam mencatat
    // pembayaran ke SALAH SATU sesi secara tidak menentu, mengacaukan
    // pertanggungjawaban uang fisik per kasir. Sekarang dicek GLOBAL: kas
    // aktif milik siapa pun (termasuk diri sendiri) memblokir open saldo
    // baru sampai sesi itu di-closing.
    // Cek + insert di atas dibungkus transaksi, dan sebelum cek dilakukan
    // kita lockForUpdate() baris app_settings (selalu ada persis 1 baris)
    // sebagai "mutex" global. Tanpa ini, dua request storeKas() yang hampir
    // bersamaan bisa sama-sama lolos SELECT "belum ada kas aktif" sebelum
    // salah satu sempat INSERT - hasilnya dua sesi kas open sekaligus,
    // padahal baris di atas justru menegaskan itu harus eksklusif. Karena
    // belum ada baris kas_harian yang bisa di-lock (sesi barunya belum
    // ada), app_settings dipakai sebagai baris pengganti untuk lock.
    return DB::transaction(function () use ($nominal, $keteranganPembukaan) {
        DB::table('app_settings')->lockForUpdate()->first();

        $kasAktifSiapaPun = KasSesiService::aktif();

        if ($kasAktifSiapaPun) {
            $tglBuka = \Carbon\Carbon::parse($kasAktifSiapaPun->tgl_kas)->format('d/m/Y');
            if ((int) $kasAktifSiapaPun->id_user === (int) auth()->id()) {
                return redirect()->route('kasir.kelola')->with('error', "Sesi kas Anda (dibuka {$tglBuka}) masih berjalan dan belum di-closing. Lakukan closing kas dulu sebelum membuka sesi baru.");
            }
            $namaKasirAktif = DB::table('users')
                ->leftJoin('karyawan', 'users.id_karyawan', '=', 'karyawan.id_karyawan')
                ->where('users.id_user', $kasAktifSiapaPun->id_user)
                ->select(DB::raw('COALESCE(karyawan.nama_karyawan, users.username) as nama'))
                ->value('nama') ?? 'pengguna lain';
            return redirect()->route('kasir.kelola')->with('error', "Sesi kas milik {$namaKasirAktif} (dibuka {$tglBuka}) belum di-closing. Hanya satu sesi kas yang boleh aktif dalam satu waktu - closing dulu sebelum membuka sesi baru (Administrator dapat menutup paksa).");
        }

        // Saldo awal yang diketik harus sama dengan UANG YANG BENAR-BENARNA
        // masih di laci. Kalau sesi sebelumnya ditutup dengan status
        // "Rolling", isi laci sesi itu adalah titik awal yang benar - bukan
        // angka sistem, dan bukan tambahan atas sesi sebelumnya.
        //
        // Tanpa guard ini, selisih saat buka kas hilang tanpa jejak. Bukti
        // nyata di oriondb: sesi #2 ditutup dengan isi laci Rp436.193
        // (status_rolling = 'Rolling'), lalu sesi #3 dibuka dengan
        // saldo_awal Rp100.000 - sistem sudah menawarkan Rp436.193 lewat
        // saran, tapi diam saja. Rp336.193 tidak tercatat di mana pun.
        //
        // ACUAN = sesi tertutup yang paling baru, TANPA filter tanggal.
        // Filter lama tgl_kas < hari_ini hanya benar untuk "bawa saldo dari
        // sebelum hari ini", tapi salah begitu kasir menutup lalu membuka
        // sesi di hari yang sama: sesi yang baru ditutup tgl_kas-nya sama
        // dengan hari ini sehingga tidak terlihat, dan acuan jatuh ke sesi
        // yang lebih lama. Akibatnya saldo yang BENAR justru ditolak minta
        // alasan - persis skenario yang paling sering dipakai kasir.
        // Karena hanya satu sesi boleh aktif sekaligus, sesi tertutup
        // terakhir menurut waktu_tutup selalu embody uang yang ada di laci.
        $kasSebelumnya = DB::table('kas_harian')
            ->where('status_lock', 'closed')
            ->orderByDesc('waktu_tutup')
            ->first();

        $isiLaciSebelumnya = ($kasSebelumnya && $kasSebelumnya->status_rolling === 'Rolling')
            ? (float) $kasSebelumnya->saldo_fisik
            : null;

        // 0.005 tolerateansi floating point; selisih 1 rupiah pun tetap
        // dianggap berbeda karena uang tidak pernah berdesimal.
        $beda = $isiLaciSebelumnya !== null && abs($nominal - $isiLaciSebelumnya) > 0.005;

        if ($beda && trim((string) $keteranganPembukaan) === '') {
            $tgl = \Carbon\Carbon::parse($kasSebelumnya->tgl_kas)->format('d/m/Y');
            return redirect()->route('kasir.kelola')->with('error', 'Saldo awal Rp '
                . number_format($nominal, 0, ',', '.')
                . ' TIDAK sama dengan isi laci sesi sebelumnya ('
                . $tgl . ') yang Rp ' . number_format($isiLaciSebelumnya, 0, ',', '.')
                . ' - selisih Rp ' . number_format(abs($nominal - $isiLaciSebelumnya), 0, ',', '.')
                . '. Hitung ulang uang di laci. Kalau memang berbeda, isi alasan pembukaannya.');
        }

        DB::table('kas_harian')->insert([
            'tgl_kas'     => now()->format('Y-m-d'),
            'id_user'     => auth()->id(),
            'saldo_awal'  => $nominal,
            'saldo_akhir' => $nominal,
            'status_lock' => 'open',
            'keterangan_pembukaan' => $beda ? trim($keteranganPembukaan) : null,
            'created_at'  => now(),
            'updated_at'  => now()
        ]);

        $pesan = $beda
            ? ' Kas Berhasil Dibuka dengan catatan selisih: ' . $keteranganPembukaan
            : '';

        // Setelah simpan, lempar ke halaman monitoring
        return redirect()->route('kasir.index')->with('success', 'Kas Berhasil Dibuka!' . $pesan);
    });
}
/**
 * Rombak total mengikuti referensi "Closing Alur Kas Kasir" milik pemilik
 * aplikasi - rekap nota HARI INI per Mesin/Divisi (sebelumnya cuma rincian
 * pemasukan per metode bayar, tidak ada gambaran nota apa saja yang masuk
 * hari ini). "Nota Penjualan Barang"/"Piutang Penjualan Barang" di
 * referensi SENGAJA TIDAK direplikasi - app ini cuma py satu jenis
 * transaksi (transaksi+transaksi_detail, mencakup produk & jasa
 * sekaligus), tidak ada jalur "penjualan barang" terpisah dari "nota
 * order" seperti sistem lama pemilik aplikasi, jadi bagian itu tidak py
 * padanan data nyata untuk ditampilkan (bukan dihilangkan sengaja,
 * memang tidak ada). Begitu juga "Status Rolling Saldo Akhir" & "Bayar DP
 * Transaksi Ref" - tidak py alur kerja yang jelas padanannya di app ini,
 * jadi tidak dipaksakan dibangun tanpa tahu maksud pastinya.
 */
public function previewClosing()
{
    $hari_ini = date('Y-m-d');
    $kas = KasSesiService::aktif();

    // Proteksi: Tidak bisa closing jika tidak ada kas aktif
    if (!$kas) {
        return redirect()->route('kasir.kelola')->with('error', 'Tidak ada alur kas aktif yang bisa ditutup!');
    }

    // Closing: pemilik sesi, atau Administrator (tutup paksa, mis. kasir lupa closing).
    if (!self::bolehTutup($kas)) {
        return redirect()->route('kasir.kelola')->with('error', 'Sesi kas ini milik kasir lain. Hanya pemilik sesi atau Administrator yang dapat melakukan closing.');
    }
    $mulaiSesi = KasSesiService::mulai($kas);
    $akhirSesi = KasSesiService::akhir($kas);
    $tutupPaksa = (int) $kas->id_user !== (int) auth()->id();

    // REKAP TRANSAKSI - nota yang ORDER-nya dibuat hari ini (tgl_order),
    // dipecah per Mesin/Divisi produk (Jasa & produk tanpa mesin masuk
    // "Jasa / Lain-lain"). Ini "Nota Order Cetak" di referensi.
    //
    // Divisi yang TIDAK ada pekerjaannya tetap dimunculkan dengan angka nol.
    // Versi sebelumnya hanya/grouping dari transaksi_detail, jadi divisi
    // sepi lenyap dari laporan - padahal absennya itu informasi: divisi itu
    // tidak menerima order hari ini. Kalau daftar divisi ikut berubah-ubah
    // setiap hari, operator tidak bisa membandingkan hari ini dengan kemarin.
    $omzetPerDivisi = DB::table('transaksi_detail')
        ->join('transaksi', 'transaksi_detail.id_transaksi', '=', 'transaksi.id_transaksi')
        ->leftJoin('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
        ->leftJoin('mesin', 'produk.id_mesin', '=', 'mesin.id_mesin')
        ->where('transaksi.tgl_order', '>=', $mulaiSesi)
        ->where('transaksi.status_nota', '!=', 'Dibatalkan')
        ->select(
            DB::raw("COALESCE(mesin.nama_mesin, 'Jasa / Lain-lain') as kategori"),
            // Setelah diskon nota, supaya piutang = total nota - terbayar (diskon bukan piutang).
            DB::raw('SUM(' . \App\Support\OmzetBersih::sql('transaksi_detail', 'transaksi') . ') as total')
        )
        ->groupBy('kategori')
        ->get()
        ->keyBy('kategori');

    // Sumber daftar divisi adalah tabel mesin, bukan hasil transaksi - supaya
    // lengkap dan tidak bergantung pada ada/tidaknya order.
    $semuaDivisi = DB::table('mesin')->orderBy('nama_mesin')->pluck('nama_mesin');

    // Bucket "Jasa / Lain-lain" tidak punya baris di tabel mesin (produk tanpa
    // mesin, atau baris jasa), jadi ditambahkan kalau memang ada isinya.
    $divisiAda = $omzetPerDivisi->keys()->reject(fn ($k) => $semuaDivisi->contains($k))->values();

    $rekapKategori = $semuaDivisi->concat($divisiAda)
        ->map(fn ($nama) => (object) [
            'kategori' => $nama,
            'total' => (float) ($omzetPerDivisi[$nama]->total ?? 0),
            'ada_order' => isset($omzetPerDivisi[$nama]),
        ])
        // Yang ada pekerjaannya dulu, lalu yang nol - diurutkan nama supaya
        // posisinya tidak bergeser antar hari.
        ->sortBy([['ada_order', 'desc'], ['total', 'desc'], ['kategori', 'asc']])
        ->values();

    $totalNotaOrder = (float) $rekapKategori->sum('total');

    // Piutang Nota Order - sisa tagihan (belum dibayar) KHUSUS dari nota
    // yang order-nya dibuat hari ini (bukan seluruh piutang lama).
    $notaHariIni = DB::table('transaksi')
        ->where('tgl_order', '>=', $mulaiSesi)
        ->where('status_nota', '!=', 'Dibatalkan')
        ->select('id_transaksi', 'total_bayar')
        ->get();
    $totalTerbayarNotaHariIni = DB::table('pembayaran_riwayat')
        ->whereIn('id_transaksi', $notaHariIni->pluck('id_transaksi'))
        ->sum('nominal');
    $piutangNotaOrder = max(0, $totalNotaOrder - (float) $totalTerbayarNotaHariIni);
    $totalPendapatan = $totalNotaOrder - $piutangNotaOrder;

    // REKAP PEMBAYARAN - seluruh pembayaran yang diterima HARI INI (tgl_bayar),
    // dipecah: "Bayar Pelunasan" (melunasi/mencicil nota yang ORDER-nya dari
    // hari-hari SEBELUMNYA) vs "Bayar Transaksi Hari Ini" (nota yang order &
    // bayarnya sama-sama hari ini).
    $pembayaranHariIni = DB::table('pembayaran_riwayat')
        ->join('transaksi', 'pembayaran_riwayat.id_transaksi', '=', 'transaksi.id_transaksi')
        ->where('pembayaran_riwayat.tgl_bayar', '>=', $mulaiSesi)
        ->where('pembayaran_riwayat.tgl_bayar', '<=', $akhirSesi)
        ->select('pembayaran_riwayat.nominal', 'pembayaran_riwayat.metode', 'transaksi.tgl_order')
        ->get();
    // Pelunasan = nota yang dibuat SEBELUM sesi ini dimulai; sisanya transaksi sesi ini.
    $bayarPelunasan = (float) $pembayaranHariIni->filter(fn ($p) => \Carbon\Carbon::parse($p->tgl_order)->lt($mulaiSesi))->sum('nominal');
    $bayarHariIni = (float) $pembayaranHariIni->filter(fn ($p) => !\Carbon\Carbon::parse($p->tgl_order)->lt($mulaiSesi))->sum('nominal');

    // Hitung Rincian Pemasukan dari Riwayat Pembayaran (per metode, seluruh
    // pembayaran hari ini apa pun tanggal order-nya - dipakai utk mutasi laci).
    $pemasukan = DB::table('pembayaran_riwayat')
        ->where('tgl_bayar', '>=', $mulaiSesi)
        ->where('tgl_bayar', '<=', $akhirSesi)
        ->select(
            DB::raw("SUM(CASE WHEN metode = 'Tunai' THEN nominal ELSE 0 END) as tunai_masuk"),
            DB::raw("SUM(CASE WHEN metode IN ('Transfer','QRIS') THEN nominal ELSE 0 END) as transfer_masuk"),
            DB::raw("SUM(CASE WHEN metode = 'Debit' THEN nominal ELSE 0 END) as debit_masuk"),
            DB::raw("SUM(CASE WHEN metode = 'Deposit' THEN nominal ELSE 0 END) as deposit_masuk")
        )->first();

    // "Kas Tidak Terduga" - satu-satunya sumber pengeluaran tunai riil di
    // app ini (nota selalu pemasukan, tidak pernah pengeluaran).
    $manual = DB::table('kas_manual')
        ->where('id_kas', $kas->id_kas)
        ->select(
            DB::raw("SUM(CASE WHEN status = 'Masuk' THEN nominal ELSE 0 END) as manual_masuk"),
            DB::raw("SUM(CASE WHEN status = 'Keluar' THEN nominal ELSE 0 END) as manual_keluar")
        )->first();
    $manualMasuk = (float) ($manual->manual_masuk ?? 0);
    $pengeluaran_tunai = (float) ($manual->manual_keluar ?? 0);

    // Kalkulasi Saldo Akhir Laci (Hanya Tunai - Transfer/Debit/Deposit tidak
    // pernah menyentuh uang fisik di laci).
    $saldo_akhir_sistem = ($kas->saldo_awal + $pemasukan->tunai_masuk + $manualMasuk) - $pengeluaran_tunai;

    return view('kasir.preview_closing', compact(
        'kas', 'pemasukan', 'pengeluaran_tunai', 'saldo_akhir_sistem',
        'rekapKategori', 'totalNotaOrder', 'piutangNotaOrder', 'totalPendapatan',
        'bayarPelunasan', 'bayarHariIni', 'manualMasuk', 'mulaiSesi', 'tutupPaksa'
    ));
}

public function simpanClosing(Request $request)
{
    $request->validate([
        'status_rolling' => 'required|in:Rolling,Tidak Rolling',
    ]);

    // Closing SELALU boleh (termasuk hari Minggu libur): sesi kas tetap open
    // sampai di-closing, jadi sesi yang dibuka hari Sabtu harus bisa ditutup.

    $id_kas = $request->id_kas;
    $saldo_fisik = (float) str_replace('.', '', $request->saldo_fisik ?? 0);

    if ($saldo_fisik < 0) {
        return back()->with('error', 'Saldo fisik tidak boleh negatif.');
    }

    DB::beginTransaction();
    try {
        // Lock row supaya dua klik "Verifikasi & Kunci Kasir" yang hampir
        // bersamaan tidak sama-sama lolos menutup kas yang sama.
        $kas = DB::table('kas_harian')->where('id_kas', $id_kas)->lockForUpdate()->first();

        if (!$kas) {
            throw new \Exception('Sesi kas tidak ditemukan.');
        }
        if ($kas->status_lock === 'closed') {
            throw new \Exception('Sesi kas ini sudah ditutup sebelumnya.');
        }
        if (!self::bolehTutup($kas)) {
            throw new \Exception('Hanya pemilik sesi kas atau Administrator yang dapat melakukan closing.');
        }
        $tutupPaksa = (int) $kas->id_user !== (int) auth()->id();
        if ($tutupPaksa && trim((string) $request->keterangan) === '') {
            throw new \Exception('Tutup paksa sesi kas milik kasir lain wajib disertai alasan pada kolom keterangan.');
        }
        $mulaiSesi = KasSesiService::mulai($kas);

        // Total pemasukan DIHITUNG ULANG dari pembayaran_riwayat di server
        // (bukan dipercaya dari hidden field), sama seperti previewClosing() -
        // supaya laporan yang tersimpan permanen tidak bisa diselipi angka
        // yang tidak sesuai riwayat pembayaran sesungguhnya.
        $pemasukan = DB::table('pembayaran_riwayat')
            ->where('tgl_bayar', '>=', $mulaiSesi)
            ->where('tgl_bayar', '<=', now())
            ->select(
                DB::raw("SUM(CASE WHEN metode = 'Tunai' THEN nominal ELSE 0 END) as tunai_masuk"),
                DB::raw("SUM(CASE WHEN metode IN ('Transfer','QRIS') THEN nominal ELSE 0 END) as transfer_masuk"),
                DB::raw("SUM(CASE WHEN metode = 'Debit' THEN nominal ELSE 0 END) as debit_masuk")
            )->first();

        $tunaiMasuk = (float) ($pemasukan->tunai_masuk ?? 0);
        $transferMasuk = (float) ($pemasukan->transfer_masuk ?? 0);
        $debitMasuk = (float) ($pemasukan->debit_masuk ?? 0);

        // "Kas Tidak Terduga" - lihat catatan yang sama di previewClosing().
        $manual = DB::table('kas_manual')
            ->where('id_kas', $kas->id_kas)
            ->select(
                DB::raw("SUM(CASE WHEN status = 'Masuk' THEN nominal ELSE 0 END) as manual_masuk"),
                DB::raw("SUM(CASE WHEN status = 'Keluar' THEN nominal ELSE 0 END) as manual_keluar")
            )->first();
        $manualMasuk = (float) ($manual->manual_masuk ?? 0);
        $tunaiKeluar = (float) ($manual->manual_keluar ?? 0);

        $saldoAkhirSistem = $kas->saldo_awal + $tunaiMasuk + $manualMasuk - $tunaiKeluar;
        $selisih = $saldo_fisik - $saldoAkhirSistem;

        DB::table('kas_harian')->where('id_kas', $id_kas)->update([
            'total_tunai_masuk' => $tunaiMasuk,
            'total_transfer_masuk' => $transferMasuk,
            'total_debit_masuk' => $debitMasuk,
            'total_tunai_keluar' => $tunaiKeluar,
            'saldo_akhir' => $saldoAkhirSistem,
            'saldo_fisik' => $saldo_fisik,
            'selisih' => $selisih,
            'status_lock' => 'closed',
            'waktu_tutup' => now(),
            'keterangan_closing' => $request->keterangan,
            'status_rolling' => $request->status_rolling,
            'updated_at' => now()
        ]);

        if ($tutupPaksa) {
            \App\Services\AuditLogger::log('updated', 'kas_harian', (int) $id_kas, ['status_lock' => 'open'], ['status_lock' => 'closed'], 'Tutup paksa sesi kas milik user #' . $kas->id_user . ': ' . $request->keterangan);
        }

        DB::commit();

        // Flash id_kas-nya supaya halaman Kelola Kas bisa langsung
        // menampilkan pilihan cetak (A4 & thermal). Opsi print bukan
        // PELENGKAP yang harus dicari-cari setelah closing - rekap harian
        // diambil tepat setelah uang dihitung, jadi pintasan ke cetak harus
        // ada di halaman yang dituju setelah closing, bukan di halaman lain.
        return redirect()->route('kasir.kelola')
            ->with('success', 'Kas Berhasil Ditutup dan Dikunci. Selisih: Rp ' . number_format($selisih, 0, ',', '.'))
            ->with('kas_baru_ditutup', (int) $id_kas);

    } catch (\Exception $e) {
        DB::rollBack();
        return back()->with('error', \App\Support\PesanError::untuk($e));
    }
}

/**
 * Cetak nota transaksi. Format kertas (thermal/a4/continuous) diambil dari
 * Pengaturan Aplikasi GLOBAL (dulu dipilih manual per transaksi lewat
 * dropdown "Nota Thermal"/"Invoice A4" - kasir tidak perlu pilih format
 * tiap kali cetak lagi, satu view yang sama, cuma CSS lebar kertasnya
 * beda, supaya tidak perlu template terpisah per format).
 */
public function printOpsi(Request $request, $id)
{
    $transaksi = DB::table('transaksi')
        ->join('konsumen', 'transaksi.id_konsumen', '=', 'konsumen.id_konsumen')
        ->leftJoin('users', 'transaksi.id_user_kasir', '=', 'users.id_user')
        ->leftJoin('karyawan', 'users.id_karyawan', '=', 'karyawan.id_karyawan')
        ->where('transaksi.id_transaksi', $id)
        ->select(
            'transaksi.*',
            'konsumen.nama_konsumen',
            'konsumen.alamat',
            'konsumen.telepon',
            DB::raw('COALESCE(karyawan.nama_karyawan, users.username) as nama_kasir')
        )
        ->first();

    if (!$transaksi) {
        abort(404, 'Transaksi tidak ditemukan.');
    }

    // Metode hitung produk ikut diambil karena nota TIDAK mencetak ukuran
    // untuk produk ber-metode 'ukuran' (ukuran di form mengikuti ukuran
    // bahan, bisa berbeda dari file asli - lihat kasir/print_nota.blade.php).
    // Produk yang sudah dihapus (id_protek tidak ketemu) tetap aman:
    // metode_hitung_produk null, jadi ukuran tidak ikut tercetak.
    $items = DB::table('transaksi_detail')
        ->leftJoin('produk', 'transaksi_detail.id_produk', '=', 'produk.id_produk')
        ->where('transaksi_detail.id_transaksi', $id)
        ->select(
            'transaksi_detail.nama_file',
            'transaksi_detail.qty',
            'transaksi_detail.subtotal',
            'transaksi_detail.biaya_cetak',
            'transaksi_detail.biaya_desain',
            'transaksi_detail.biaya_finishing',
            'transaksi_detail.p_ukuran',
            'transaksi_detail.l_ukuran',
            'transaksi_detail.id_bahan',
            'transaksi_detail.id_produk',
            'transaksi_detail.id_jasa',
            'transaksi_detail.is_minimum_order',
            DB::raw('produk.metode_hitung as metode_hitung_produk')
        )
        ->get();

    $totalTerbayar = DB::table('pembayaran_riwayat')->where('id_transaksi', $id)->sum('nominal') ?? 0;
    $totalUangDiterima = (float) (DB::table('pembayaran_riwayat')->where('id_transaksi', $id)->sum('uang_diterima') ?? 0);
    if ($totalUangDiterima <= 0) $totalUangDiterima = $totalTerbayar;
    $totalCetak = (float) $items->sum('biaya_cetak');
    $totalDesain = (float) $items->sum('biaya_desain');
    $totalFinishing = (float) $items->sum('biaya_finishing');

    // Pembulatan MURNI tampilan nota (lihat AppSetting::bulatkan() &
    // migrasi 2026_09_18_120000) - transaksi.total_bayar, pembayaran_riwayat,
    // dan jurnal_umum TIDAK disentuh/diubah sama sekali di sini, tetap
    // presisi asli untuk keperluan akuntansi. Krg. Bayar/Kembali di nota
    // sengaja dihitung terhadap nominal yang SUDAH dibulatkan (kalau beda)
    // - supaya nota tidak menampilkan "kembalian" yang sebenarnya cuma
    // selisih pembulatan (tidak ada uang fisik yang sungguhan dikembalikan
    // untuk selisih sekecil itu).
    $totalTagihanAsli = (float) $transaksi->total_bayar;
    $totalTagihanBulat = \App\Models\AppSetting::bulatkan($totalTagihanAsli);
    $pembulatan = $totalTagihanBulat - $totalTagihanAsli;

    // "Kembali" hanya bermakna kalau total yang sudah dibayar MELEBIHI
    // tagihan (dibayar tunai lebih lalu dikembalikan kembaliannya) - aplikasi
    // ini tidak menyimpan "uang diterima" terpisah dari "nominal diterapkan
    // ke nota" per pembayaran, jadi ini pendekatan terbaik yang bisa dihitung
    // dari data yang benar-benar ada.
    // Kembalian yang sungguh diserahkan disimpan per pembayaran (kolom kembalian); rumus lama dipertahankan
    // untuk nota lama yang tercatat sebelum kolom itu ada.
    $kembali = max(
        (float) DB::table('pembayaran_riwayat')->where('id_transaksi', $id)->sum('kembalian'),
        max(0, $totalTerbayar - $totalTagihanBulat)
    );
    $kurangBayar = max(0, $totalTagihanBulat - $totalTerbayar);

    // Rincian "Uang Diterima": satu baris per pembayaran yang benar-benar
    // tercatat, lengkap dengan nominal dan metodenya (Tunai/Transfer/Debit/
    // Deposit). Baris "Total Bayar" di ringkasan hanya menjumlahkan angka,
    // sehingga kasir dan pelanggan tidak pernah bisa mencocokkan nominal di
    // nota dengan uang yang benar-benar masuk ke laci. Keterangan status
    // (LUNAS / BELUM BAYAR / DP) ikut dicetak dari aturan yang sama dengan
    // halaman kasir, supaya nota dan layar tidak pernah berbeda pendapat
    // tentang status nota ini.
    $penerimaanNota = DB::table('pembayaran_riwayat')
        ->where('id_transaksi', $id)
        ->orderBy('tgl_bayar')
        ->orderBy('id_pembayaran')
        ->select('nominal', 'metode', 'tgl_bayar', 'kembalian')
        ->get();

    $statusPembayaranNota = StatusPembayaran::dari((float) $transaksi->total_bayar, (float) $totalTerbayar);
    $metodePembayaranNota = $penerimaanNota->pluck('metode')->unique()->implode(', ');

    // Info deposit - dipakai buat baris "Penggunaan deposit pada transaksi
    // ini" & "Saldo deposit anda saat ini" di nota (lihat referensi user).
    $depositTerpakaiTransaksiIni = (float) DB::table('konsumen_deposit')
        ->where('id_transaksi', $id)
        ->where('tipe', 'Pemakaian')
        ->sum('nominal');
    $saldoDepositSaatIni = KonsumenDepositController::saldoUntuk((int) $transaksi->id_konsumen);
    // Blok deposit di nota cuma relevan kalau konsumen ini memang PERNAH
    // pakai deposit (di transaksi ini atau transaksi lain) - konsumen yang
    // tidak pernah sama sekali menyetor/memakai deposit tidak perlu melihat
    // baris "Saldo deposit Rp 0" yang tidak bermakna apa pun buat mereka.
    $konsumenPernahPakaiDeposit = DB::table('konsumen_deposit')->where('id_konsumen', $transaksi->id_konsumen)->exists();

    // Nota Penjualan Barang (transaksi isinya bahan baku dan/atau barang
    // custom/lainnya - tidak ada produk/jasa dari Input Order sama sekali)
    // defaultnya A4/Continuous Form, TIDAK PERNAH Thermal - permintaan
    // eksplisit user, nota jual barang lebih pas sebagai invoice
    // (A4/Continuous) daripada struk kasir sempit. Nota biasa (ada
    // produk/jasa) tetap ikut format global apa adanya. Item custom (lihat
    // PenjualanBarangController::store()) juga tidak punya id_bahan, jadi
    // cek-nya sengaja TIDAK mensyaratkan id_bahan - cukup pastikan tidak ada
    // baris id_produk/id_jasa (ciri transaksi dari Input Order).
    $formatGlobal = \App\Models\AppSetting::current()->format_nota ?? 'a4';
    $dariPenjualanBarang = $items->isNotEmpty()
        && $items->every(fn ($i) => !$i->id_produk && !$i->id_jasa);
    $format = ($dariPenjualanBarang && $formatGlobal === 'thermal') ? 'a4' : $formatGlobal;

    // no-store: nota selalu dicetak dari data dan tata letak terbaru, bukan salinan lama di cache peramban.
    return response()->view('kasir.print_nota', compact(
        'transaksi', 'items', 'totalTerbayar', 'totalUangDiterima', 'format',
        'totalCetak', 'totalDesain', 'totalFinishing', 'kembali', 'kurangBayar',
        'totalTagihanAsli', 'totalTagihanBulat', 'pembulatan',
        'depositTerpakaiTransaksiIni', 'saldoDepositSaatIni', 'konsumenPernahPakaiDeposit',
        'penerimaanNota', 'statusPembayaranNota', 'metodePembayaranNota'
    ))->header('Cache-Control', 'no-store, max-age=0');
}

/**
 * Cetak laporan closing kasir yang SUDAH ditutup (butuh saldo_fisik/selisih,
 * jadi hanya bisa dicetak untuk sesi yang status_lock-nya 'closed').
 */
public function printReport($id, $format)
{
    $kas = DB::table('kas_harian')
        ->leftJoin('users', 'kas_harian.id_user', '=', 'users.id_user')
        ->leftJoin('karyawan', 'users.id_karyawan', '=', 'karyawan.id_karyawan')
        ->where('kas_harian.id_kas', $id)
        ->select('kas_harian.*', DB::raw('COALESCE(karyawan.nama_karyawan, users.username) as nama_kasir'))
        ->first();

    if (!$kas) {
        abort(404, 'Sesi kas tidak ditemukan.');
    }

    $format = $format === 'thermal' ? 'thermal' : 'a4';

    // Sesi yang belum ditutup belum punya angka fisik/selisih - cetakan tanpa
    // itu hanya setengah jadi dan bisa dipakai sebagai bukti rekonsiliasi
    // walaupun angka yang belum final.
    if ($kas->status_lock !== 'closed') {
        return redirect()->route('kasir.kelola')
            ->with('error', 'Rekap closing belum bisa dicetak untuk sesi yang masih berjalan. Selesaikan closing dulu.');
    }

    // Angka yang SUDAH tersimpan di kas_harian dipakai apa adanya (tunai,
    // transfer, debit, pengeluaran, saldo akhir, saldo fisik, selisih).
    // Dihitung ulang dari pembayaran_riwayat berisiko Cetak berbeda dari
    // yang tersimpan bila ada pembayaran terlanjur masuk ke sesi.
    //
    // Yang BELUM tersimpan dan harus dihitung ulang:
    //   - Kas Masuk / Kas Keluar manual. Kas tidak punya kolom totalnya,
    //     hanya sisa pengeluarannya (total_tunai_keluar) - jadi hasil
    //     penjumlahan di sini wajib sama seperti yang dipakai previewClosing().
    //   - Deposit. Tidak pernah ikut closing (tidak menyentuh uang fisik),
    //     tapi tetap perlu dicatat di laporan.
    $manual = DB::table('kas_manual')
        ->where('id_kas', $kas->id_kas)
        ->select(
            DB::raw("SUM(CASE WHEN status = 'Masuk' THEN nominal ELSE 0 END) as manual_masuk"),
            DB::raw("SUM(CASE WHEN status = 'Keluar' THEN nominal ELSE 0 END) as manual_keluar")
        )->first();

    $deposit = DB::table('pembayaran_riwayat')
        ->where('tgl_bayar', '>=', KasSesiService::mulai($kas))
        ->where('tgl_bayar', '<=', $kas->waktu_tutup ?: now())
        ->where('metode', 'Deposit')
        ->sum('nominal');

    return view('kasir.print_closing', [
        'kas' => $kas,
        'format' => $format,
        'manualMasuk' => (float) ($manual->manual_masuk ?? 0),
        'manualKeluar' => (float) ($manual->manual_keluar ?? 0),
        'depositMasuk' => (float) ($deposit ?? 0),
    ]);
}

/**
 * Riwayat proses sebuah nota - dari data yang benar-benar ada (kapan order
 * dibuat, riwayat pembayaran, status SPK tiap item saat ini). transaksi_detail
 * tidak punya kolom timestamp sama sekali, jadi "kapan status SPK berubah"
 * tidak bisa direkonstruksi - yang ditampilkan adalah status TERKINI per item,
 * bukan linimasa perubahan status (jangan berpura-pura tahu yang tidak diketahui).
 */
public function getHistory($id)
{
    $transaksi = DB::table('transaksi')->where('id_transaksi', $id)->first();

    if (!$transaksi) {
        return response()->json(['status' => 'error', 'msg' => 'Transaksi tidak ditemukan.'], 404);
    }

    $items = DB::table('transaksi_detail')
        ->where('id_transaksi', $id)
        ->select('nama_file', 'status_spk', 'qty', 'qty_cetak')
        ->get();

    $pembayaran = DB::table('pembayaran_riwayat')
        ->where('id_transaksi', $id)
        ->orderBy('tgl_bayar')
        ->select('nominal', 'metode', 'tgl_bayar', 'keterangan')
        ->get();

    return response()->json([
        'status' => 'success',
        'nomor_nota' => $transaksi->nomor_nota,
        'tgl_order' => $transaksi->tgl_order,
        'status_pembayaran' => $transaksi->status_pembayaran,
        'items' => $items,
        'pembayaran' => $pembayaran,
    ]);
}
    public function checkNewTransaction()
    {
        // Angka ini yang muncul sebagai notifikasi "antrean baru" di
        // halaman kasir. Kalau nota batal ikut dihitung, bunyi notifikasi
        // tetap berbunyi padahal tidak ada yang bisa dilayani - kasir
        // lalu datang ke meja dan mendapati daftar kosong.
        // Definisinya sama persis dengan $waitingCount di index() dan
        // waitingList(): order yang menunggu diterima kasir. Nota yang sudah
        // Diterima Kasir tapi belum dibayar bukan "baru" lagi.
        $count = DB::table('transaksi')
            ->where('status_order', 'Menunggu Kasir')
            ->where('status_nota', '!=', 'Dibatalkan')
            ->count();

        return response()->json(['total' => $count]);
    }
}
