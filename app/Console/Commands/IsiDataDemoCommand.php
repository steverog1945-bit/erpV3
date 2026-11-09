<?php

namespace App\Console\Commands;

use App\Http\Controllers\PenjualanController;
use App\Models\JerseyOrder;
use App\Models\JerseyOrderPayment;
use App\Models\KasKecil;
use App\Models\KedatanganBahan;
use App\Models\PembelianBahan;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\ApprovalService;
use App\Services\BackupService;
use App\Services\DocumentNumberService;
use App\Services\HargaService;
use App\Services\JerseyAccountingService;
use App\Services\PembayaranHutangService;
use App\Services\PurchaseOrderService;
use App\Services\QcService;
use App\Services\QuotationService;
use App\Services\WasteService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi DATA DUMMY untuk menguji aplikasi. Data riil (bahan baku, mesin,
 * kategori, supplier, jasa, produk) HANYA DIBACA - tidak pernah diubah.
 * Semua transaksi dibuat lewat jalur aplikasi sungguhan (controller/service)
 * supaya stok, jurnal, dan status konsisten. Pelanggan dummy berawalan
 * "DEMO " agar mudah dikenali; data transaksi lain dibersihkan oleh skrip
 * reset data uji menjelang go-live.
 */
class IsiDataDemoCommand extends Command
{
    protected $signature = 'demo:isi {--tambah : Tetap jalan walau data DEMO sudah pernah diisi} {--tanpa-backup : Lewati backup otomatis} {--yes : Tanpa konfirmasi}';

    protected $description = 'Isi data dummy (pelanggan, order, pembayaran, QC, pembelian, dst) untuk menguji aplikasi - tidak menyentuh data riil';

    private User $admin;
    private array $hasil = [];
    /** @var array<int,array{id_transaksi:int,nomor:string,total:float}> */
    private array $nota = [];
    private array $konsumen = [];

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Ditolak: perintah ini tidak boleh dijalankan di lingkungan production.');

            return self::FAILURE;
        }

        $admin = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Administrator'))->first();
        if (!$admin) {
            $this->error('Tidak ada user Administrator.');

            return self::FAILURE;
        }
        $this->admin = $admin;
        Auth::setUser($admin);

        if (DB::table('konsumen')->where('nama_konsumen', 'like', 'DEMO %')->exists() && !$this->option('tambah')) {
            $this->error('Data DEMO sudah pernah diisi. Pakai --tambah bila memang ingin menambah lagi.');

            return self::FAILURE;
        }

        if (!$this->option('yes') && !$this->confirm('Isi data dummy ke database "' . DB::connection()->getDatabaseName() . '"? Data riil tidak diubah.', true)) {
            return self::FAILURE;
        }

        if (!$this->option('tanpa-backup')) {
            try {
                $b = BackupService::run('manual');
                $this->info("Backup dibuat: {$b->file_path}");
            } catch (\Throwable $e) {
                $this->error('Backup gagal (' . $e->getMessage() . '). Batal. Pakai --tanpa-backup bila yakin.');

                return self::FAILURE;
            }
        }

        mt_srand(20260924);

        $this->bagian('Pelanggan', fn () => $this->pelanggan());
        $this->bagian('Sesi kas', fn () => $this->sesiKas());
        $this->bagian('Order + SPK', fn () => $this->order());
        $this->bagian('Progres produksi', fn () => $this->progresProduksi());
        $this->bagian('Pembayaran & serah terima', fn () => $this->pembayaran());
        $this->bagian('QC & Rework', fn () => $this->qcRework());
        $this->bagian('Waste', fn () => $this->waste());
        $this->bagian('Quotation', fn () => $this->quotation());
        $this->bagian('Purchasing (PR/PO/kedatangan/faktur/hutang)', fn () => $this->purchasing());
        $this->bagian('Kas kecil', fn () => $this->kasKecil());
        $this->bagian('Deposit pelanggan', fn () => $this->deposit());
        $this->bagian('Order Jersey', fn () => $this->jersey());

        $this->newLine();
        $this->table(['Bagian', 'Hasil'], collect($this->hasil)->map(fn ($v, $k) => [$k, $v])->values()->all());

        $selisih = round((float) DB::table('jurnal_umum')->sum('debet') - (float) DB::table('jurnal_umum')->sum('kredit'), 2);
        $selisih == 0.0
            ? $this->info('Jurnal akuntansi seimbang (total debet = total kredit).')
            : $this->error("PERINGATAN: jurnal tidak seimbang, selisih {$selisih}.");

        return self::SUCCESS;
    }

    private function bagian(string $nama, \Closure $aksi): void
    {
        try {
            $this->hasil[$nama] = $aksi();
        } catch (\Throwable $e) {
            $this->hasil[$nama] = 'GAGAL: ' . substr($e->getMessage(), 0, 90);
            $this->warn("[{$nama}] gagal: {$e->getMessage()}");
        }
    }

    private function golonganIds()
    {
        return DB::table('golongan_konsumen')->orderBy('id_golongan')->pluck('id_golongan');
    }

    private function pelanggan(): string
    {
        $nama = ['Budi Santoso', 'Siti Rahayu', 'Agus Wijaya', 'Dewi Lestari', 'Hendra Kusuma', 'Rina Marlina', 'Toko Maju Jaya', 'CV Berkah Abadi'];
        $gol = $this->golonganIds();

        foreach ($nama as $i => $n) {
            $id = DB::table('konsumen')->insertGetId([
                'nama_konsumen' => "DEMO {$n}", 'telepon' => '0812' . str_pad((string) (3000000 + $i * 111), 7, '0'),
                'alamat' => 'Jl. Contoh No. ' . ($i + 1) . ', Sampang', 'id_golongan' => $gol[$i % max(1, $gol->count())],
            ]);
            $this->konsumen[] = ['id' => $id, 'golongan' => $gol[$i % max(1, $gol->count())]];
        }

        return count($nama) . ' pelanggan';
    }

    private function sesiKas(): string
    {
        $buka = DB::table('kas_harian')->where('tgl_kas', now()->toDateString())->where('status_lock', 'open')->exists();

        if ($buka) {
            return 'sudah ada sesi kas terbuka';
        }

        DB::table('kas_harian')->insert([
            'tgl_kas' => now()->toDateString(), 'id_user' => $this->admin->id_user, 'saldo_awal' => 500000,
            'status_lock' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return 'sesi kas dibuka (saldo awal Rp 500.000) - tutup lewat menu Kasir bila perlu';
    }

    private function order(): string
    {
        $produk = DB::table('produk')->where('status', 'aktif')
            ->whereIn('id_produk', DB::table('setting_harga')->select('id_produk'))
            ->whereIn('id_produk', DB::table('bom_detail')->select('id_produk'))
            ->get();

        if ($produk->isEmpty() || empty($this->konsumen)) {
            throw new \RuntimeException('Tidak ada produk berharga & ber-BOM atau pelanggan.');
        }

        $finishing = DB::table('finishing')->pluck('id_finishing');
        $dibuat = 0;
        $gagal = 0;

        foreach (range(0, 13) as $i) {
            $k = $this->konsumen[$i % count($this->konsumen)];
            $items = [];

            foreach (range(1, mt_rand(1, 3)) as $j) {
                $p = $produk[mt_rand(0, $produk->count() - 1)];
                $ukuran = $p->metode_hitung === 'ukuran';
                $pj = $ukuran ? mt_rand(60, 300) : 0;
                $lb = $ukuran ? mt_rand(60, 200) : 0;
                $qty = $ukuran ? mt_rand(1, 3) : mt_rand(10, 100);
                $qtyHitung = $ukuran ? ($pj * $lb / 10000) * $qty : $qty;
                $harga = HargaService::getHargaEfektif((int) $p->id_produk, (int) $k['golongan'], $qtyHitung)['harga_jual'];

                if ($harga <= 0) {
                    continue;
                }

                $items[] = [
                    'id_produk' => $p->id_produk, 'nama_file' => 'demo-' . ($i + 1) . '-' . $j . '.pdf', 'p' => $pj, 'l' => $lb, 'qty_input' => $qty,
                    'biaya_cetak' => round($harga * $qtyHitung, 2), 'biaya_desain' => mt_rand(0, 1) ? 25000 : 0,
                    'id_finishing' => ($ukuran && $finishing->isNotEmpty() && mt_rand(0, 1)) ? $finishing[0] : null,
                    'biaya_finishing' => ($ukuran && $finishing->isNotEmpty() && mt_rand(0, 1)) ? 15000 : 0,
                    'status_urgensi' => ['Normal', 'Normal', 'Urgent'][mt_rand(0, 2)], 'status_finishing' => 'Standar',
                    'catatan_produksi' => 'Data DEMO',
                ];
            }

            if (empty($items)) {
                continue;
            }

            $req = Request::create('/penjualan/store', 'POST', [
                'id_konsumen' => $k['id'], 'id_golongan' => $k['golongan'], 'tipe_order' => 'Reguler',
                'tgl_order' => now()->subDays(20 - $i)->toDateString(), 'items' => $items,
            ]);
            $res = app(PenjualanController::class)->store($req)->getData(true);

            if (($res['status'] ?? '') === 'success') {
                $dibuat++;
                $this->nota[] = ['id_transaksi' => (int) $res['id_transaksi'], 'nomor' => $res['nomor_nota'], 'total' => (float) DB::table('transaksi')->where('id_transaksi', $res['id_transaksi'])->value('total_bayar')];
            } else {
                $gagal++;
                $this->warn('Order dilewati: ' . ($res['msg'] ?? '?'));
            }
        }

        return "{$dibuat} nota dibuat" . ($gagal ? ", {$gagal} dilewati" : '');
    }

    private function progresProduksi(): string
    {
        $n = 0;

        foreach ($this->nota as $i => $nt) {
            $detail = DB::table('transaksi_detail')->where('id_transaksi', $nt['id_transaksi'])->get();
            $status = match (true) {
                $i < 2 => 'Antre',
                $i < 4 => 'Desain',
                $i < 6 => 'Cetak',
                default => 'Selesai',
            };

            foreach ($detail as $d) {
                if ($d->status_spk === 'Selesai' && $d->id_produk === null) {
                    continue; // baris jasa sudah Selesai sejak dibuat
                }
                DB::table('transaksi_detail')->where('id_detail', $d->id_detail)->update([
                    'status_spk' => $status, 'qty_cetak' => in_array($status, ['Cetak', 'Selesai'], true) ? $d->qty : null,
                ]);
                $n++;
            }
        }

        return "{$n} item diberi status SPK bervariasi (Antre/Desain/Cetak/Selesai)";
    }

    private function pembayaran(): string
    {
        $bank = DB::table('bank_perusahaan')->value('id_bank');
        $lunas = $dp = $diambil = 0;

        foreach ($this->nota as $i => $nt) {
            if ($i % 4 === 3) {
                continue; // sebagian dibiarkan Belum Bayar
            }

            $penuh = $i % 4 !== 1;
            $nominal = $penuh ? $nt['total'] : round($nt['total'] * 0.5, 2);
            $metode = $i % 2 === 0 ? 'Tunai' : 'Transfer';

            $row = ['id_transaksi' => $nt['id_transaksi'], 'nominal' => $nominal, 'metode' => $metode, 'tgl_bayar' => now()->subDays(max(0, 18 - $i)), 'keterangan' => 'DEMO'];
            if ($metode === 'Transfer' && $bank) {
                $row['id_bank'] = $bank;
            }

            DB::table('pembayaran_riwayat')->insert($row);
            AccountingService::postPembayaran($nt['nomor'], $nominal, $metode);
            DB::table('transaksi')->where('id_transaksi', $nt['id_transaksi'])->update(['status_pembayaran' => $penuh ? 'Lunas' : 'DP']);
            $penuh ? $lunas++ : $dp++;

            $semuaSelesai = !DB::table('transaksi_detail')->where('id_transaksi', $nt['id_transaksi'])->whereNotIn('status_spk', ['Selesai', 'Batal'])->exists();

            if ($penuh && $semuaSelesai && $diambil < 3) {
                DB::table('transaksi')->where('id_transaksi', $nt['id_transaksi'])->update([
                    'status_collecting' => 'Sudah Diambil', 'tgl_collecting' => now(), 'id_user_collecting' => $this->admin->id_user, 'updated_at' => now(),
                ]);
                DB::table('pengambilan')->insert([
                    'id_transaksi' => $nt['id_transaksi'], 'jenis' => $diambil === 1 ? 'Diambil Perwakilan' : 'Diambil Sendiri',
                    'nama_penerima' => $diambil === 1 ? 'Perwakilan DEMO' : null, 'catatan' => 'Data DEMO', 'id_user' => $this->admin->id_user,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $diambil++;
            }
        }

        return "{$lunas} lunas, {$dp} DP, {$diambil} sudah diambil";
    }

    private function qcRework(): string
    {
        $d = DB::table('transaksi_detail')->whereIn('id_transaksi', collect($this->nota)->pluck('id_transaksi'))
            ->where('status_spk', 'Selesai')->whereNotNull('id_produk')->where('qty', '>', 1)->first();

        if (!$d) {
            return 'tidak ada item Selesai untuk di-QC';
        }

        $qc = QcService::catat((int) $d->id_detail, (int) ($d->qty_cetak ?? $d->qty), 1, ['cacat_cetak' => true], 'Ada goresan (DEMO)');
        $biaya = QcService::prosesRework((int) $qc['id_rework']);

        $lulus = DB::table('transaksi_detail')->whereIn('id_transaksi', collect($this->nota)->pluck('id_transaksi'))
            ->where('status_spk', 'Selesai')->whereNotNull('id_produk')->where('id_detail', '!=', $d->id_detail)->first();
        if ($lulus) {
            QcService::catat((int) $lulus->id_detail, (int) ($lulus->qty_cetak ?? $lulus->qty), 0, [], 'Lulus (DEMO)');
        }

        return 'QC dengan reject + rework diproses (biaya material Rp ' . number_format($biaya, 0, ',', '.') . ')' . ($lulus ? ', 1 QC lulus' : '');
    }

    private function waste(): string
    {
        $bahan = DB::table('bahan_baku')->where('status', 'aktif')->where('stok', '>=', 3)->orderByDesc('stok')->first();

        if (!$bahan) {
            return 'tidak ada bahan dengan stok cukup';
        }

        WasteService::catat((int) $bahan->id_bahan, 0.5, 'Normal', 'Sisa potongan (DEMO)');
        WasteService::catat((int) $bahan->id_bahan, 1, 'Abnormal', 'Basah kena air (DEMO)');

        return '1 waste normal diterapkan, 1 abnormal menunggu approval';
    }

    private function quotation(): string
    {
        $produk = DB::table('produk')->where('status', 'aktif')->whereIn('id_produk', DB::table('setting_harga')->select('id_produk'))->first();

        if (!$produk || empty($this->konsumen)) {
            return 'tidak ada produk berharga';
        }

        $dibuat = 0;

        foreach (['Draft', 'Terkirim', 'Disetujui'] as $i => $status) {
            $k = $this->konsumen[$i];
            $q = QuotationService::simpan([
                'tanggal' => now()->subDays(3)->toDateString(), 'berlaku_sampai' => now()->addDays(10)->toDateString(), 'id_konsumen' => $k['id'],
                'nama_pelanggan' => '', 'diskon' => 0, 'catatan' => 'Penawaran DEMO',
                'items' => [['tipe' => 'Produk', 'id_produk' => $produk->id_produk, 'p' => 300, 'l' => 200, 'qty' => 1]],
            ]);
            if ($status !== 'Draft') {
                QuotationService::kirim($q);
            }
            if ($status === 'Disetujui') {
                QuotationService::tanggapi($q->fresh(), 'Disetujui');
            }
            $dibuat++;
        }

        return "{$dibuat} penawaran (Draft, Terkirim, Disetujui)";
    }

    private function purchasing(): string
    {
        $supplier = DB::table('supplier')->value('id_supplier');
        $gudang = DB::table('gudang')->value('id_gudang');
        $bahan = DB::table('bahan_baku')->where('status', 'aktif')->orderBy('id_bahan')->limit(3)->get();

        if (!$supplier || $bahan->isEmpty()) {
            return 'tidak ada supplier/bahan';
        }

        // PR diajukan (menunggu approval) + PR disetujui.
        $pr1 = PurchaseRequest::create(['nomor' => DocumentNumberService::generate('PR'), 'tanggal' => now()->subDays(6), 'status' => 'Diajukan', 'catatan' => 'DEMO stok menipis', 'id_user_pembuat' => $this->admin->id_user]);
        $pr1->details()->create(['id_bahan' => $bahan[0]->id_bahan, 'qty' => 5, 'catatan' => 'DEMO']);
        ApprovalService::ajukan('purchase_request', 'purchase_requests', $pr1->id, [], "Purchase Request {$pr1->nomor} (1 item)", 'DEMO');

        $pr2 = PurchaseRequest::create(['nomor' => DocumentNumberService::generate('PR'), 'tanggal' => now()->subDays(8), 'status' => 'Diproses PO', 'catatan' => 'DEMO', 'id_user_pembuat' => $this->admin->id_user]);
        $pr2->details()->create(['id_bahan' => $bahan[1]->id_bahan ?? $bahan[0]->id_bahan, 'qty' => 4]);

        // PO Dipesan dari PR2, diterima sebagian lewat kedatangan tervalidasi.
        $idBahanPo = $bahan[1]->id_bahan ?? $bahan[0]->id_bahan;
        $subtotal = 4 * 1200000;
        $po = PurchaseOrder::create([
            'nomor' => DocumentNumberService::generate('PO'), 'id_supplier' => $supplier, 'id_purchase_request' => $pr2->id, 'tanggal' => now()->subDays(7),
            'tgl_pengiriman_estimasi' => now()->subDay()->toDateString(), 'termin_pembayaran' => 'Net 30', 'status' => 'Dipesan',
            'subtotal' => $subtotal, 'diskon' => 0, 'pajak_persen' => 0, 'ongkir' => 0, 'total' => $subtotal, 'catatan' => 'DEMO', 'id_user_pembuat' => $this->admin->id_user,
        ]);
        $po->details()->create(['id_bahan' => $idBahanPo, 'qty' => 4, 'harga' => 1200000, 'subtotal' => $subtotal, 'qty_diterima' => 0]);

        $ked = KedatanganBahan::create([
            'no_surat_jalan' => 'DEMO-SJ-' . now()->format('His'), 'id_supplier' => $supplier, 'id_gudang' => $gudang, 'tgl_datang' => now()->subDays(2)->toDateString(),
            'status' => 'Draft', 'keterangan' => "Penerimaan dari {$po->nomor}", 'id_purchase_order' => $po->id,
        ]);
        DB::table('kedatangan_bahan_detail')->insert(['id_kedatangan' => $ked->id_kedatangan, 'id_bahan' => $idBahanPo, 'qty' => 2, 'satuan' => $bahan[1]->satuan_stok ?? '', 'status' => 'OK']);

        DB::transaction(function () use ($ked, $idBahanPo) {
            $b = DB::table('bahan_baku')->where('id_bahan', $idBahanPo)->lockForUpdate()->first();
            $sesudah = (float) $b->stok + 2;
            DB::table('bahan_baku')->where('id_bahan', $idBahanPo)->update(['stok' => $sesudah]);
            DB::table('kartu_stok')->insert([
                'id_bahan' => $idBahanPo, 'tanggal' => now(), 'tipe' => 'IN', 'qty' => 2, 'stok_sebelum' => $b->stok, 'stok_sesudah' => $sesudah,
                'referensi' => $ked->no_surat_jalan, 'keterangan' => 'Penerimaan Gudang: DEMO', 'created_by' => $this->admin->id_user, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('kedatangan_bahan')->where('id_kedatangan', $ked->id_kedatangan)->update(['status' => 'Selesai']);
        });
        PurchaseOrderService::sinkronPenerimaan($po->id);

        // Faktur Tempo (sebagian dibayar, sebagian lewat jatuh tempo) untuk hutang & aging.
        $fak = PembelianBahan::create([
            'id_kedatangan' => $ked->id_kedatangan, 'no_faktur' => 'DEMO-FAK-' . now()->format('His'), 'tgl_transaksi' => now()->subDays(45)->toDateString(),
            'tipe_pembelian' => 'Tempo', 'jatuh_tempo_hari' => 30, 'jatuh_tempo_tgl' => now()->subDays(15)->toDateString(), 'grand_total' => 2400000,
        ]);
        $fak->status_pembayaran = 'Hutang';
        $fak->save();
        DB::table('pembelian_bahan_detail')->insert(['id_pembelian' => $fak->id_pembelian, 'id_bahan' => $idBahanPo, 'qty' => 2, 'harga' => 1200000, 'diskon' => 0, 'potongan' => 0, 'subtotal' => 2400000]);
        AccountingService::postPembelianBahan($fak->no_faktur, 2400000, 'Tempo');
        PembayaranHutangService::bayar((int) $fak->id_pembelian, 1000000, 'Transfer', now()->subDays(5)->toDateString(), 'Cicilan DEMO');

        return '2 PR (1 menunggu approval), 1 PO diterima sebagian, 1 kedatangan tervalidasi, 1 faktur tempo dicicil & lewat jatuh tempo';
    }

    private function kasKecil(): string
    {
        $kategori = DB::table('kategori_kas_kecil')->whereNotNull('kode_akun')->limit(3)->get();
        $n = 0;

        foreach ($kategori as $i => $kat) {
            $k = KasKecil::create([
                'tanggal' => now()->subDays(4 - $i)->toDateString(), 'id_kategori' => $kat->id_kategori_kas_kecil, 'keterangan' => 'Pengeluaran DEMO ' . ($i + 1),
                'nominal' => 25000 * ($i + 1), 'id_user' => $this->admin->id_user,
            ]);
            AccountingService::postKasKecil('KK-' . $k->id, $kat->kode_akun, (float) $k->nominal);
            $n++;
        }

        return "{$n} catatan kas kecil";
    }

    private function deposit(): string
    {
        $k = $this->konsumen[0]['id'] ?? null;

        if (!$k) {
            return 'tidak ada pelanggan';
        }

        DB::table('konsumen_deposit')->insert([
            'id_konsumen' => $k, 'tipe' => 'Setoran', 'nominal' => 300000, 'metode_bayar' => 'Tunai', 'keterangan' => 'Setoran DEMO',
            'tgl_deposit' => now()->subDays(2)->toDateString(), 'id_user' => $this->admin->id_user, 'created_at' => now(), 'updated_at' => now(),
        ]);
        AccountingService::postSetoranDeposit('DEMO-DEP-' . now()->format('His'), 300000, 'Tunai');

        return '1 setoran deposit Rp 300.000';
    }

    private function jersey(): string
    {
        $k = $this->konsumen[6] ?? $this->konsumen[0] ?? null;
        $n = 0;

        foreach ([[1500000, 700000, 'Tunai'], [2500000, 2500000, 'Transfer']] as [$biaya, $bayar, $metode]) {
            $o = JerseyOrder::create([
                'order_number' => JerseyOrder::generateOrderNumber(now()), 'order_date' => now()->subDays(6 - $n * 3)->toDateString(),
                'customer_name' => 'DEMO Tim Garuda ' . ($n + 1), 'id_konsumen' => $k['id'] ?? null, 'team_name' => 'Garuda FC', 'additional_charge' => $biaya,
                'notes' => 'Order Jersey DEMO',
            ]);
            $o->recalculateTotal();

            $p = JerseyOrderPayment::create([
                'jersey_order_id' => $o->id, 'tanggal_bayar' => now()->toDateString(), 'jumlah' => $bayar, 'metode_bayar' => $metode, 'keterangan' => 'DEMO', 'created_by' => $this->admin->id_user,
            ]);
            JerseyAccountingService::catatPembayaran($o->fresh(), $p);
            $o->fresh()->recalculatePembayaran();
            $n++;
        }

        return "{$n} order jersey (1 DP, 1 lunas)";
    }
}
