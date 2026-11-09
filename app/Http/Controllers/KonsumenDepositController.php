<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\AccountingService;

class KonsumenDepositController extends Controller
{
    public function index()
    {
        // Saldo per konsumen = SUM(Setoran) - SUM(Pemakaian). Bukan kolom
        // running-balance terpisah, supaya tidak ada dua sumber kebenaran.
        //
        // Sebelumnya konsumen yang saldonya persis 0 (deposit pernah ada,
        // tapi sudah habis terpakai semua) DIBUANG total dari daftar ini
        // (havingRaw('saldo_deposit != 0')) - hilang begitu saja tanpa
        // keterangan, seolah-olah tidak pernah punya deposit sama sekali.
        // Sekarang tetap ditampilkan dengan badge "Habis Terpakai" di view
        // (konsumen-deposit/index.blade.php) supaya jelas depositnya PERNAH
        // ada tapi sudah tidak bisa dipakai lagi - bukan sekadar hilang.
        // Konsumen yang memang TIDAK PERNAH punya baris konsumen_deposit
        // sama sekali tetap tidak muncul (join di atas sudah otomatis
        // mengecualikan mereka).
        $saldoPerKonsumen = DB::table('konsumen_deposit')
            ->join('konsumen', 'konsumen_deposit.id_konsumen', '=', 'konsumen.id_konsumen')
            ->leftJoin('golongan_konsumen', 'konsumen.id_golongan', '=', 'golongan_konsumen.id_golongan')
            ->select(
                'konsumen.id_konsumen',
                'konsumen.nama_konsumen',
                'golongan_konsumen.nama_golongan',
                DB::raw("SUM(CASE WHEN konsumen_deposit.tipe = 'Setoran' THEN konsumen_deposit.nominal ELSE -konsumen_deposit.nominal END) as saldo_deposit")
            )
            ->groupBy('konsumen.id_konsumen', 'konsumen.nama_konsumen', 'golongan_konsumen.nama_golongan')
            ->orderByDesc('saldo_deposit')
            ->get();

        $riwayat = DB::table('konsumen_deposit')
            ->join('konsumen', 'konsumen_deposit.id_konsumen', '=', 'konsumen.id_konsumen')
            ->leftJoin('users', 'konsumen_deposit.id_user', '=', 'users.id_user')
            ->leftJoin('transaksi', 'konsumen_deposit.id_transaksi', '=', 'transaksi.id_transaksi')
            ->select('konsumen_deposit.*', 'konsumen.nama_konsumen', 'users.username as dicatat_oleh', 'transaksi.nomor_nota')
            ->orderByDesc('konsumen_deposit.created_at')
            ->limit(50)
            ->get();

        $konsumen = DB::table('konsumen')->orderBy('nama_konsumen')->get();

        // Saldo saat ini per konsumen (map id_konsumen -> saldo_deposit) -
        // dipakai di baris Riwayat bertipe Setoran untuk kasih tahu status
        // pemakaiannya SEKARANG (deposit dicatat sebagai saldo terpool per
        // konsumen, bukan dialokasikan per-setoran/FIFO ke transaksi
        // tertentu, jadi ini status "saldo konsumen saat ini" - bukan klaim
        // persis setoran mana yang dipakai untuk transaksi mana).
        $saldoMap = $saldoPerKonsumen->keyBy('id_konsumen');

        return view('konsumen-deposit.index', compact('saldoPerKonsumen', 'riwayat', 'konsumen', 'saldoMap'));
    }

    /**
     * Deposit tunai berarti uang masuk/keluar LACI kasir. Jurnalnya sudah memakai akun Kas (1000), jadi di sini
     * cukup baris cermin di Kas Tidak Terduga (tanpa jurnal kedua) supaya hitungan laci saat closing ikut benar.
     * Pola sama dengan "Isi Kas Kecil dari laci" (KasKecilController).
     */
    private static function cerminLaci(string $status, float $nominal, string $keterangan, bool $periksaIsi): void
    {
        $sesi = \App\Services\KasSesiService::aktif();
        if (! $sesi) {
            throw new \RuntimeException('Kas kasir belum dibuka. Deposit tunai masuk atau keluar dari laci, jadi buka kas dulu (atau pakai metode Transfer).');
        }
        if ($periksaIsi) {
            \App\Services\KasSesiService::pastikanLaciCukup($sesi, $nominal);
        }
        DB::table('kas_manual')->insert([
            'id_kas' => $sesi->id_kas, 'waktu' => now(), 'keterangan' => $keterangan, 'status' => $status,
            'nominal' => $nominal, 'id_user' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_konsumen'  => 'required|exists:konsumen,id_konsumen',
            'nominal'      => 'required|numeric|min:1',
            'metode_bayar' => 'required|in:Tunai,Transfer',
            'tgl_deposit'  => ['required', 'date', new \App\Rules\TanggalTransaksi],
            'keterangan'   => 'nullable',
        ]);

        DB::beginTransaction();
        try {
            $noReferensi = 'DEP-' . now()->format('YmdHis') . '-' . $request->id_konsumen;

            $dataDeposit = [
                'id_konsumen'  => $request->id_konsumen,
                'tipe'         => 'Setoran',
                'nominal'      => $request->nominal,
                'metode_bayar' => $request->metode_bayar,
                'keterangan'   => $request->keterangan,
                'tgl_deposit'  => $request->tgl_deposit,
                'id_user'      => Auth::id(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ];
            $idDeposit = DB::table('konsumen_deposit')->insertGetId($dataDeposit);

            // Contoh pola audit trail untuk jalur DB::table() murni (tabel
            // ini tidak dikelola lewat Eloquent sama sekali di controller
            // ini - lihat catatan di App\Services\AuditLogger).
            \App\Services\AuditLogger::log('created', 'konsumen_deposit', $idDeposit, null, $dataDeposit, "Setoran deposit {$noReferensi}");

            AccountingService::postSetoranDeposit($noReferensi, (float) $request->nominal, $request->metode_bayar);
            if ($request->metode_bayar === 'Tunai') {
                $nama = DB::table('konsumen')->where('id_konsumen', $request->id_konsumen)->value('nama_konsumen');
                self::cerminLaci('Masuk', (float) $request->nominal, "Setoran deposit {$nama} [{$noReferensi}]", false);
            }

            DB::commit();
            return redirect()->back()->with('success', 'Deposit berhasil dicatat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', \App\Support\PesanError::untuk($e, 'Gagal mencatat deposit'));
        }
    }

    /**
     * Mengembalikan saldo deposit kepada pelanggan sebagai uang. Hanya Administrator (rute hanya.admin);
     * uang keluar dari kas, jadi alasan wajib dan semuanya tercatat di audit dan jurnal.
     */
    public function kembalikan(Request $request)
    {
        $data = $request->validate([
            'id_konsumen' => 'required|exists:konsumen,id_konsumen',
            'nominal' => 'required|numeric|min:1',
            'metode_bayar' => 'required|in:Tunai,Transfer',
            'alasan' => 'required|string|min:5|max:500',
        ]);

        try {
            DB::transaction(function () use ($data) {
                // Mutex per konsumen sebelum membaca saldo (sama seperti pembayaran via deposit).
                DB::table('konsumen')->where('id_konsumen', $data['id_konsumen'])->lockForUpdate()->first();

                $saldo = self::saldoUntuk((int) $data['id_konsumen'], true);
                if ((float) $data['nominal'] > $saldo) {
                    throw new \RuntimeException('Nominal melebihi saldo deposit. Saldo tersedia: Rp ' . number_format($saldo, 0, ',', '.'));
                }

                $noReferensi = 'DEPRET-' . now()->format('YmdHis') . '-' . $data['id_konsumen'];
                $baris = [
                    'id_konsumen' => $data['id_konsumen'],
                    'tipe' => 'Pengembalian',
                    'nominal' => $data['nominal'],
                    'metode_bayar' => $data['metode_bayar'],
                    'keterangan' => 'Dikembalikan ke pelanggan: ' . $data['alasan'],
                    'tgl_deposit' => now()->toDateString(),
                    'id_user' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $id = DB::table('konsumen_deposit')->insertGetId($baris);
                \App\Services\AuditLogger::log('created', 'konsumen_deposit', $id, null, $baris, "Pengembalian deposit {$noReferensi}");
                AccountingService::postPengembalianDeposit($noReferensi, (float) $data['nominal'], $data['metode_bayar']);
                if ($data['metode_bayar'] === 'Tunai') {
                    $nama = DB::table('konsumen')->where('id_konsumen', $data['id_konsumen'])->value('nama_konsumen');
                    self::cerminLaci('Keluar', (float) $data['nominal'], "Pengembalian deposit {$nama} [{$noReferensi}]", true);
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', \App\Support\PesanError::untuk($e, 'Gagal mengembalikan deposit'));
        }

        return back()->with('success', 'Deposit dikembalikan dan tercatat di jurnal.');
    }

    /**
     * Saldo deposit tersedia untuk satu konsumen (dipakai KasirController
     * saat validasi pembayaran metode Deposit).
     */
    /**
     * $terkunci = true dipakai PENJAGA saldo di dalam transaksi: hitungan dibaca sebagai locking read, yang selalu
     * melihat data terbaru yang sudah di-commit. Pembacaan biasa di MySQL (REPEATABLE READ) memakai potret yang
     * terbentuk pada bacaan biasa pertama di transaksi; bila potret itu terbentuk sebelum kunci didapat, dua
     * pengeluaran bersamaan sama-sama melihat saldo lama dan sama-sama lolos (saldo minus).
     */
    public static function saldoUntuk(int $idKonsumen, bool $terkunci = false): float
    {
        $setoran = (float) DB::table('konsumen_deposit')
            ->where('id_konsumen', $idKonsumen)->where('tipe', 'Setoran')->when($terkunci, fn ($q) => $q->sharedLock())->sum('nominal');
        // Pemakaian (bayar nota) dan Pengembalian (uang dikembalikan) sama-sama mengurangi saldo.
        $pemakaian = (float) DB::table('konsumen_deposit')
            ->where('id_konsumen', $idKonsumen)->whereIn('tipe', ['Pemakaian', 'Pengembalian'])->when($terkunci, fn ($q) => $q->sharedLock())->sum('nominal');

        return $setoran - $pemakaian;
    }
}
