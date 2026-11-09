<?php

namespace App\Services;

use App\Models\KasKecil;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Biaya desain per operator + bagi hasil menurut kelas desainer.
 * Komisi dihitung SAAT NOTA DIBUAT (snapshot persen & nominal), dibayarkan berkala dari Kas Kecil
 * (Dr Beban Komisi Desain 5210 / Cr Kas Kecil 1005). Pendapatan biaya desain sendiri tetap diakui penuh di nota.
 */
class DesainKomisiService
{
    public const ROLE_DESAIN = 'Operator Desain';

    /** Operator desain yang boleh dipilih sebagai desainer. */
    public static function daftarDesainer(): \Illuminate\Support\Collection
    {
        return DB::table('users as u')->join('roles as r', 'r.id_role', '=', 'u.id_role')->leftJoin('karyawan as k', 'k.id_karyawan', '=', 'u.id_karyawan')
            ->where('r.nama_role', self::ROLE_DESAIN)->orderBy('u.username')
            ->get(['u.id_user', DB::raw('COALESCE(k.nama_karyawan, u.username) as nama')]);
    }

    /** Nama tampilan desainer (users id): nama karyawan bila ada, selain itu username. */
    public static function namaDesainer(?int $idDesainer): ?string
    {
        if (!$idDesainer) return null;
        $r = DB::table('users as u')->leftJoin('karyawan as k', 'k.id_karyawan', '=', 'u.id_karyawan')
            ->where('u.id_user', $idDesainer)
            ->first([DB::raw('COALESCE(k.nama_karyawan, u.username) as nama')]);

        return $r->nama ?? null;
    }

    /** Desainer yang dipakai untuk item: pilihan valid, atau pengguna sendiri bila Operator Desain, atau null. */
    public static function tentukanDesainer(?int $dipilih): ?int
    {
        if ($dipilih && self::daftarDesainer()->contains('id_user', $dipilih)) {
            return $dipilih;
        }
        $user = Auth::user();

        return $user && optional($user->role)->nama_role === self::ROLE_DESAIN ? (int) $user->id_user : null;
    }

    /**
     * Id user yang HARUS jadi desainer, atau null bila nama desainer boleh dipilih.
     *
     * Kunci ini berlaku kalau pengguna yang sedang login punya role Operator
     * Desain: orang yang mengerjakan desain itu adalah dirinya sendiri, jadi
     * tidak ada alasan memilih nama desainer lain. Tanpa kunci ini, komisi
     * bisa dialihkan ke rekan yang tidak mengerjakan apa pun dan laporan
     * biaya desain kehilangan maknanya.
     *
     * Role lain (Kasir, Administrator, Owner, ...) TIDAK dikunci di sini:
     * mereka bukan yang mengerjakan desain, jadi tetap perlu memilih
     * desainer yang benar-benar bekerja pada item itu.
     */
    public static function desainerTerkunci(): ?int
    {
        $user = Auth::user();

        return $user && optional($user->role)->nama_role === self::ROLE_DESAIN ? (int) $user->id_user : null;
    }

    /**
     * Sama seperti tentukanDesainer(), tapi untuk Operator Desain hasilnya
     * SELALU dirinya sendiri - nilai dari browser diabaikan sepenuhnya.
     *
     * Dipakai saat nota dibuat (PenjualanController::store) supaya
     * penguncian di layar Input Pesanan tidak bisa dilewati dengan mengedit
     * payload dari DevTools.
     */
    public static function kunciDesainer(?int $dipilih): ?int
    {
        $terkunci = self::desainerTerkunci();
        if ($terkunci) {
            return $terkunci;
        }

        return self::tentukanDesainer($dipilih);
    }

    /** @return array{persen: float, komisi: float} */
    public static function hitung(?int $idDesainer, float $biayaDesain): array
    {
        if (!$idDesainer || $biayaDesain <= 0) {
            return ['persen' => 0.0, 'komisi' => 0.0];
        }
        $persen = (float) DB::table('users as u')->leftJoin('kelas_desainer as c', 'c.id_kelas', '=', 'u.id_kelas_desainer')
            ->where('u.id_user', $idDesainer)->value('c.persen_komisi');

        return ['persen' => $persen, 'komisi' => round($biayaDesain * $persen / 100, 2)];
    }

    /** Baris komisi yang siap dibayar milik satu desainer pada rentang tanggal order (nota tidak dibatalkan). */
    public static function queryBelumDibayar(int $idDesainer, string $dari, string $sampai)
    {
        return DB::table('transaksi_detail as d')->join('transaksi as t', 't.id_transaksi', '=', 'd.id_transaksi')
            ->where('d.id_desainer', $idDesainer)->where('d.komisi_status', 'Belum')->where('d.komisi_desain', '>', 0)
            ->where('t.status_nota', '!=', 'Dibatalkan')->where('d.status_spk', '!=', 'Batal')
            // tgl_order = TIMESTAMP: rentang [dari 00:00, sampai+1 hari 00:00) - sargable, hasil sama dengan whereDate.
            ->where('t.tgl_order', '>=', \Carbon\Carbon::parse($dari)->startOfDay())
            ->where('t.tgl_order', '<', \Carbon\Carbon::parse($sampai)->addDay()->startOfDay());
    }

    /** Bayar semua komisi "Belum" seorang desainer di rentang tanggal: satu pengeluaran Kas Kecil + status baris jadi Dibayar. */
    public static function bayar(int $idDesainer, string $dari, string $sampai): array
    {
        return DB::transaction(function () use ($idDesainer, $dari, $sampai) {
            $ids = self::queryBelumDibayar($idDesainer, $dari, $sampai)->lockForUpdate()->pluck('d.id_detail');
            if ($ids->isEmpty()) {
                throw new \RuntimeException('Tidak ada komisi yang belum dibayar pada rentang ini.');
            }
            $total = round((float) DB::table('transaksi_detail')->whereIn('id_detail', $ids)->sum('komisi_desain'), 2);
            KasKecilService::pastikanSaldoCukup($total);
            $kategori = DB::table('kategori_kas_kecil')->where('nama_kategori', 'Komisi Desainer')->first();
            if (!$kategori || !$kategori->kode_akun) {
                throw new \RuntimeException('Kategori Kas Kecil "Komisi Desainer" belum dipetakan ke akun Beban.');
            }
            $nama = DB::table('users as u')->leftJoin('karyawan as k', 'k.id_karyawan', '=', 'u.id_karyawan')->where('u.id_user', $idDesainer)->value(DB::raw('COALESCE(k.nama_karyawan, u.username)'));

            $kk = KasKecil::create([
                'tanggal' => now()->toDateString(), 'jenis' => 'Keluar', 'sumber' => 'manual', 'metode' => 'Tunai', 'id_kategori' => $kategori->id_kategori_kas_kecil,
                'keterangan' => "Komisi desain {$nama} - order {$dari} s/d {$sampai} ({$ids->count()} item)", 'nominal' => $total, 'id_user' => Auth::id(),
            ]);
            AccountingService::postKasKecil('KK-' . $kk->id, $kategori->kode_akun, $total);

            DB::table('transaksi_detail')->whereIn('id_detail', $ids)->update([
                'komisi_status' => 'Dibayar', 'komisi_dibayar_at' => now(), 'id_kas_kecil_komisi' => $kk->id,
            ]);

            return ['total' => $total, 'item' => $ids->count(), 'id_kas_kecil' => $kk->id];
        });
    }
}
