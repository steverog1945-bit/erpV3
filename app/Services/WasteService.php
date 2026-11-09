<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Waste Management (LEVEL 2, bagian 47 dokumen audit). Normal waste = susut
 * wajar proses cetak, langsung diterapkan. Abnormal waste (rusak, salah
 * potong, hilang) hanya diajukan lewat Approval Workflow (tipe_aksi
 * 'waste_abnormal') - stok baru berkurang saat disetujui, supaya jalur ini
 * tidak jadi jalan pintas mengurangi stok tanpa kontrol.
 */
class WasteService
{
    /**
     * Cara mengisi qty waste untuk sebuah bahan: 'luas' (Panjang x Lebar, satuan hitung m2/cm2),
     * 'hitung' (jumlah dalam satuan hitung, mis. pcs/lembar/meter), atau 'stok' (langsung satuan stok).
     */
    public static function modeInput(object $bahan): string
    {
        $hitung = strtolower(trim((string) ($bahan->satuan_hitung ?? '')));
        $adaKonversi = (float) ($bahan->nilai_konversi ?? 0) > 0
            && !empty($bahan->id_satuan_hitung) && (int) $bahan->id_satuan_hitung !== (int) ($bahan->id_satuan_stok ?? 0);

        if (!$adaKonversi || $hitung === '') {
            return 'stok';
        }

        return in_array($hitung, ['m2', 'm²', 'cm2', 'cm²'], true) ? 'luas' : 'hitung';
    }

    /** @return array{id:int, nomor:string, status:string} */
    public static function catat(int $idBahan, float $qty, string $jenis, string $alasan, ?int $idDetail = null, ?string $tanggal = null): array
    {
        if ($qty <= 0) {
            throw new \RuntimeException('Qty waste harus lebih dari 0.');
        }

        return DB::transaction(function () use ($idBahan, $qty, $jenis, $alasan, $idDetail, $tanggal) {
            if (!DB::table('bahan_baku')->where('id_bahan', $idBahan)->exists()) {
                throw new \RuntimeException('Bahan tidak ditemukan.');
            }

            $nomor = DocumentNumberService::generate('WS');

            $id = DB::table('waste_log')->insertGetId([
                'nomor' => $nomor,
                'tanggal' => $tanggal ?: now()->toDateString(),
                'id_bahan' => $idBahan,
                'qty' => $qty,
                'jenis' => $jenis,
                'alasan' => $alasan,
                'id_detail' => $idDetail,
                'status' => 'Menunggu',
                'id_user' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($jenis === 'Normal') {
                self::terapkan($id);

                return ['id' => $id, 'nomor' => $nomor, 'status' => 'Diterapkan'];
            }

            $namaBahan = DB::table('bahan_baku')->where('id_bahan', $idBahan)->value('nama_bahan');

            ApprovalService::ajukan(
                'waste_abnormal', 'waste_log', $id, [],
                "Waste abnormal {$nomor}: {$namaBahan} " . angka($qty, 4),
                $alasan
            );

            return ['id' => $id, 'nomor' => $nomor, 'status' => 'Menunggu'];
        });
    }

    /** Menerapkan waste: keluarkan stok, catat kartu stok, jurnalkan beban. Dipanggil langsung (Normal) atau lewat approval (Abnormal). */
    public static function terapkan(int $idWaste): void
    {
        DB::transaction(function () use ($idWaste) {
            $w = DB::table('waste_log')->where('id', $idWaste)->lockForUpdate()->first();

            if (!$w) {
                throw new \RuntimeException('Data waste tidak ditemukan.');
            }

            if ($w->status !== 'Menunggu') {
                throw new \RuntimeException("Waste {$w->nomor} sudah berstatus {$w->status}.");
            }

            $biaya = StokKeluarService::keluarkan((int) $w->id_bahan, (float) $w->qty, $w->nomor, "Waste {$w->jenis}: {$w->alasan}");

            AccountingService::postBebanRework($w->nomor, "Waste {$w->jenis} {$w->nomor}", $biaya);

            DB::table('waste_log')->where('id', $idWaste)->update(['status' => 'Diterapkan', 'biaya' => $biaya, 'updated_at' => now()]);
        });
    }

    public static function tolak(int $idWaste): void
    {
        $w = DB::table('waste_log')->where('id', $idWaste)->lockForUpdate()->first();

        if (!$w || $w->status !== 'Menunggu') {
            throw new \RuntimeException('Waste ini sudah tidak berstatus Menunggu.');
        }

        DB::table('waste_log')->where('id', $idWaste)->update(['status' => 'Ditolak', 'updated_at' => now()]);
    }
}
