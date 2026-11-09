<?php

namespace App\Services;

use App\Models\User;
use App\Support\JalurLanjut;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Daftar "pekerjaan yang menunggu" per role (lonceng notifikasi + badge di sidebar).
 * Hanya item yang halaman tujuannya boleh dibuka role tersebut yang ikut tampil,
 * dan angka dihitung dari data yang sudah ada (tidak ada tabel baru).
 * Hasil di-cache 10 detik per pengguna.
 */
class NotifikasiService
{
    /** @return array{total:int, items:array<int,array{key:string,label:string,count:int,url:string,tone:string}>} */
    public static function untuk(?User $user): array
    {
        if (!$user) {
            return ['total' => 0, 'items' => []];
        }

        return Cache::remember('notif_user_' . $user->id_user, 10, function () use ($user) {
            $items = [];
            foreach (self::definisi($user) as $d) {
                $url = self::urlBisa($d['routes']);
                if (!$url) {
                    continue;
                }
                // Item yang halamannya tidak boleh dibuka role ini dilewati.
                if (!JalurLanjut::bolehUntuk($user, $url)) {
                    continue;
                }
                // Angka notifikasi sama untuk semua pengguna yang berhak, dan diminta setiap 30 detik oleh
                // setiap layar yang terbuka. Disimpan 20 detik supaya sepuluh layar tidak menghitung ulang
                // sepuluh kali (siapa yang berhak melihat tetap diperiksa per pengguna di atas).
                $n = (int) \Illuminate\Support\Facades\Cache::remember('notif:' . $d['key'], 20, $d['hitung']);
                if ($n > 0) {
                    $items[] = ['key' => $d['key'], 'label' => $d['label'], 'count' => $n, 'url' => $url, 'tone' => $d['tone']];
                }
            }

            return ['total' => (int) array_sum(array_column($items, 'count')), 'items' => $items];
        });
    }

    private static function urlBisa(array $routes): ?string
    {
        foreach ($routes as $r) {
            if (\Illuminate\Support\Facades\Route::has($r)) {
                return route($r);
            }
        }

        return null;
    }

    /** Definisi notifikasi: key, label, route tujuan, warna (rose = mendesak, amber = perlu perhatian, indigo = info), penghitung. */
    private static function definisi(User $user): array
    {
        $hariIni = now()->toDateString();

        return [
            ['key' => 'integritas', 'label' => 'Temuan pemeriksaan pembukuan', 'routes' => ['audit-owner.index'], 'tone' => 'rose',
                'hitung' => fn () => (int) (Cache::get('audit_integritas_terakhir')['jumlah'] ?? 0)],
            ['key' => 'approval', 'label' => 'Pengajuan menunggu persetujuan', 'routes' => ['approval.index'], 'tone' => 'rose',
                'hitung' => fn () => DB::table('approval_requests')->where('status', 'Menunggu')->count()],
            ['key' => 'kasir-bayar', 'label' => 'Nota belum dibayar', 'routes' => ['kasir.index'], 'tone' => 'amber',
                'hitung' => fn () => DB::table('transaksi')->where('status_pembayaran', 'Belum Bayar')->where('status_nota', '!=', 'Dibatalkan')->count()],
            ['key' => 'spk-antre', 'label' => 'SPK antre dikerjakan', 'routes' => ['spk.index'], 'tone' => 'amber',
                // Harus memakai syarat yang sama dengan tabel /spk
                // (SpkController::index): status_nota Aktif dan status_order
                // Diterima Kasir. Sebelumnya syarat kedua tidak ada, sehingga
                // lonceng ikut menghitung nota yang transaksinya belum dibuka
                // kasir sama sekali, sementara tabel /spk tidak pernah
                // menampilkannya. Akibatnya lonceng berbunyi lalu operator
                // membuka halaman dan mendapati tabel kosong.
                //
                // Tanggal SENGAJA tidak ikut di sini. Tabel /spk terbuka di
                // rentang hari ini, tapi kalau lonceng ikut difilter tanggal
                // yang sama, order tertinggal dari hari-hari sebelumnya akan
                // hilang dari keduanya dan pekerjaan itu menggantung tanpa
                // ada yang tahu. Lonceng tetap menghitung seluruh
                // pekerjaan yang menunggu, berapa pun umurnya.
                'hitung' => fn () => DB::table('transaksi_detail as d')->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
                    ->where('d.status_spk', 'Antre')
                    ->where('t.status_nota', 'Aktif')
                    ->where('t.status_order', 'Diterima Kasir')
                    ->count()],
            ['key' => 'produksi', 'label' => 'Item sedang diproses (desain/cetak)', 'routes' => ['produksi.index'], 'tone' => 'indigo',
                'hitung' => fn () => DB::table('transaksi_detail as d')->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
                    ->whereIn('d.status_spk', ['Desain', 'Cetak'])->where('t.status_nota', '!=', 'Dibatalkan')->count()],
            ['key' => 'collecting', 'label' => 'Nota siap diambil/diserahkan', 'routes' => ['collecting.index'], 'tone' => 'indigo',
                // Dihitung di database; sebelumnya semua nota dikelompokkan lalu dihitung di PHP.
                'hitung' => fn () => DB::table('transaksi as t')
                    ->where('t.status_collecting', 'Belum Diambil')->where('t.status_nota', '!=', 'Dibatalkan')
                    ->whereExists(fn ($e) => $e->select(DB::raw(1))->from('transaksi_detail as d')->whereColumn('d.id_transaksi', 't.id_transaksi'))
                    ->whereNotExists(fn ($e) => $e->select(DB::raw(1))->from('transaksi_detail as d')->whereColumn('d.id_transaksi', 't.id_transaksi')->whereNotIn('d.status_spk', ['Selesai', 'Batal']))
                    ->count()],
            ['key' => 'rework', 'label' => 'Rework belum selesai', 'routes' => ['qc.index'], 'tone' => 'rose',
                'hitung' => fn () => DB::table('rework')->where('status', 'Dibuka')->count()],
            ['key' => 'waste', 'label' => 'Waste menunggu persetujuan', 'routes' => ['waste.index'], 'tone' => 'amber',
                'hitung' => fn () => DB::table('waste_log')->where('status', 'Menunggu')->count()],
            ['key' => 'stok', 'label' => 'Bahan habis / di bawah stok minimal', 'routes' => ['laporan.stok-bahan', 'kartu-stok.index'], 'tone' => 'rose',
                'hitung' => fn () => DB::table('bahan_baku')->where('status', 'aktif')->where('stok_minimal', '>', 0)->whereColumn('stok', '<=', 'stok_minimal')->count()],
            ['key' => 'pr-po', 'label' => 'PR disetujui, belum dibuat PO', 'routes' => ['purchase-order.index'], 'tone' => 'amber',
                'hitung' => fn () => DB::table('purchase_requests')->where('status', 'Disetujui')->count()],
            ['key' => 'po-datang', 'label' => 'PO menunggu barang datang', 'routes' => ['purchase-order.index'], 'tone' => 'indigo',
                'hitung' => fn () => DB::table('purchase_orders')->whereIn('status', ['Dipesan', 'Diterima Sebagian'])->count()],
            ['key' => 'kedatangan', 'label' => 'Kedatangan draft belum divalidasi', 'routes' => ['kedatangan-bahan.index'], 'tone' => 'amber',
                'hitung' => fn () => DB::table('kedatangan_bahan')->where('status', 'Draft')->count()],
            ['key' => 'opname', 'label' => 'Stock opname belum selesai', 'routes' => ['stock-opname.index'], 'tone' => 'indigo',
                'hitung' => fn () => DB::table('stock_opnames')->whereIn('status', ['Draft', 'Diajukan'])->count()],
            ['key' => 'hutang', 'label' => 'Faktur hutang jatuh tempo (7 hari)', 'routes' => ['pembelian-bahan.index'], 'tone' => 'rose',
                'hitung' => fn () => DB::table('pembelian_bahan')->whereNull('deleted_at')->where('status_pembayaran', 'Hutang')
                    ->whereNotNull('jatuh_tempo_tgl')->where('jatuh_tempo_tgl', '<=', now()->addDays(7)->toDateString())->count()],
            ['key' => 'piutang', 'label' => 'Nota belum lunas (DP/belum bayar)', 'routes' => ['laporan.piutang'], 'tone' => 'indigo',
                'hitung' => fn () => DB::table('transaksi')->where('status_nota', '!=', 'Dibatalkan')->whereIn('status_pembayaran', ['DP', 'Belum Bayar'])->count()],
            ['key' => 'quotation', 'label' => 'Quotation menunggu jawaban pelanggan', 'routes' => ['quotation.index'], 'tone' => 'indigo',
                'hitung' => fn () => DB::table('quotations')->where('status', 'Terkirim')->count()],
            ['key' => 'jersey', 'label' => 'Order jersey deadline 3 hari', 'routes' => ['jersey.orders.index'], 'tone' => 'rose',
                'hitung' => fn () => DB::table('jersey_orders')->whereNotNull('deadline')->whereBetween('deadline', [$hariIni, now()->addDays(3)->toDateString()])
                    ->whereNotIn(DB::raw('LOWER(status)'), ['selesai', 'batal', 'dibatalkan', 'diambil'])->count()],
            ['key' => 'backup-gagal', 'label' => 'Backup bermasalah (gagal / belum tersalin ke lokasi kedua)', 'routes' => ['backup.index'], 'tone' => 'rose',
                'hitung' => fn () => DB::table('backup_logs')->where('started_at', '>=', now()->subDays(3))
                    ->where(fn ($q) => $q->where('status', 'Gagal')->orWhere('offsite_status', 'Gagal'))->count()],
            ['key' => 'kas-basi', 'label' => 'Kas belum ditutup lebih dari 24 jam', 'routes' => ['kasir.kelola'], 'tone' => 'rose',
                'hitung' => function () use ($user) {
                    // Hanya Administrator dan kasir yang membuka saldo sesi itu yang perlu diingatkan.
                    $kas = KasSesiService::aktif();
                    $berhak = $kas && (optional($user->role)->nama_role === 'Administrator' || (int) $kas->id_user === (int) $user->id_user);

                    return $berhak && KasSesiService::basi($kas) ? 1 : 0;
                }],
        ];
    }
}
