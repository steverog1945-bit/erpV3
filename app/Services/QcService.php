<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Quality Control & Rework (LEVEL 2, bagian 21-22 dokumen audit).
 * QC per item order: qty lolos + qty reject = qty diperiksa; reject > 0
 * otomatis membuka Rework. Rework diproses dengan mengeluarkan material
 * tambahan sesuai BOM (stok, kartu stok, jurnal Beban Rework 5200).
 */
class QcService
{
    public const CHECKLIST = [
        'ukuran' => 'Ukuran',
        'warna' => 'Warna',
        'posisi_desain' => 'Posisi desain',
        'jumlah' => 'Jumlah',
        'finishing' => 'Finishing',
        'cacat_cetak' => 'Cacat cetak',
    ];

    public static function catat(int $idDetail, int $qtyDiperiksa, int $qtyReject, array $checklist, ?string $catatan, ?string $tanggal = null): array
    {
        return DB::transaction(function () use ($idDetail, $qtyDiperiksa, $qtyReject, $checklist, $catatan, $tanggal) {
            $detail = DB::table('transaksi_detail')->where('id_detail', $idDetail)->lockForUpdate()->first();

            if (!$detail) {
                throw new \RuntimeException('Item order tidak ditemukan.');
            }

            if (in_array($detail->status_spk, ['Batal', 'Antre', 'Pending'], true)) {
                throw new \RuntimeException("Item berstatus {$detail->status_spk} belum bisa di-QC.");
            }

            if ($qtyDiperiksa < 1 || $qtyReject < 0 || $qtyReject > $qtyDiperiksa) {
                throw new \RuntimeException('Qty diperiksa harus minimal 1 dan qty reject tidak boleh melebihi qty diperiksa.');
            }

            // Batas total pemeriksaan: qty produksi + qty yang sudah selesai
            // di-rework (unit hasil rework diperiksa ulang).
            $batas = (int) ($detail->qty_cetak ?? $detail->qty)
                + (int) DB::table('rework')->where('id_detail', $idDetail)->where('status', 'Selesai')->sum('qty_rework');
            $sudah = (int) DB::table('qc_inspeksi')->where('id_detail', $idDetail)->sum('qty_diperiksa');

            if ($sudah + $qtyDiperiksa > $batas) {
                throw new \RuntimeException("Total qty diperiksa ({$sudah} + {$qtyDiperiksa}) melebihi qty produksi + rework ({$batas}).");
            }

            $nomor = DocumentNumberService::generate('QC');
            $qtyLolos = $qtyDiperiksa - $qtyReject;

            $idQc = DB::table('qc_inspeksi')->insertGetId([
                'nomor' => $nomor,
                'id_detail' => $idDetail,
                'tanggal' => $tanggal ?: now()->toDateString(),
                'qty_diperiksa' => $qtyDiperiksa,
                'qty_lolos' => $qtyLolos,
                'qty_reject' => $qtyReject,
                'checklist' => json_encode(array_intersect_key($checklist, self::CHECKLIST)),
                'hasil' => $qtyReject > 0 ? 'Rework' : 'Lulus',
                'catatan' => $catatan,
                'id_user' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $idRework = null;

            if ($qtyReject > 0) {
                $idRework = DB::table('rework')->insertGetId([
                    'nomor' => DocumentNumberService::generate('RW'),
                    'id_qc_inspeksi' => $idQc,
                    'id_detail' => $idDetail,
                    'qty_rework' => $qtyReject,
                    'status' => 'Dibuka',
                    'catatan' => $catatan,
                    'id_user' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return ['id_qc' => $idQc, 'nomor' => $nomor, 'id_rework' => $idRework];
        });
    }

    /**
     * Memproses rework: keluarkan material tambahan sesuai BOM & jurnalkan biayanya. Mengembalikan biaya material.
     * Bahan cetak ulang sudah terpakai secara fisik, jadi stok yang kurang tidak menghalangi (sama seperti order baru):
     * stok boleh minus, dan pemanggil memberi peringatan lewat reworkStokMinus().
     */
    public static function prosesRework(int $idRework): float
    {
        return DB::transaction(function () use ($idRework) {
            $rw = DB::table('rework')->where('id', $idRework)->lockForUpdate()->first();

            if (!$rw) {
                throw new \RuntimeException('Rework tidak ditemukan.');
            }

            if ($rw->status !== 'Dibuka') {
                throw new \RuntimeException("Rework {$rw->nomor} sudah {$rw->status}.");
            }

            $detail = DB::table('transaksi_detail')->where('id_detail', $rw->id_detail)->first();
            $total = 0.0;

            if ($detail && $detail->id_produk) {
                foreach (BomCalculationService::komponen((int) $detail->id_produk, (float) $detail->p_ukuran, (float) $detail->l_ukuran, (float) $rw->qty_rework) as $bom) {
                    $qtyPakai = $bom->qty_pakai;

                    if ($qtyPakai <= 0) {
                        continue;
                    }

                    $bahan = DB::table('bahan_baku')->where('id_bahan', $bom->id_bahan)->first();
                    $konversi = (float) ($bahan->nilai_konversi ?? 0) > 0 ? (float) $bahan->nilai_konversi : 1.0;

                    $total += StokKeluarService::keluarkan(
                        (int) $bom->id_bahan, $qtyPakai / $konversi, $rw->nomor, "Material rework {$rw->nomor}", true
                    );
                }
            }

            $total = round($total, 2);

            if ($total > 0) {
                AccountingService::postBebanRework($rw->nomor, "Rework {$rw->nomor}", $total);
            }

            DB::table('rework')->where('id', $idRework)->update([
                'status' => 'Selesai', 'biaya_material' => $total, 'selesai_at' => now(), 'updated_at' => now(),
            ]);

            return $total;
        });
    }

    /** Peringatan bahan yang stoknya menjadi minus karena rework ini. */
    public static function reworkStokMinus(int $idRework): array
    {
        $nomor = DB::table('rework')->where('id', $idRework)->value('nomor');

        return DB::table('kartu_stok as ks')->join('bahan_baku as b', 'b.id_bahan', '=', 'ks.id_bahan')
            ->where('ks.referensi', $nomor)->where('ks.tipe', 'OUT')->where('ks.stok_sesudah', '<', 0)
            ->get(['b.nama_bahan', 'b.satuan_stok', 'ks.stok_sesudah'])
            ->map(fn ($r) => "{$r->nama_bahan} menjadi minus (" . angka((float) $r->stok_sesudah, 2) . " {$r->satuan_stok})")
            ->all();
    }

    /** Rework yang tidak jadi dicetak ulang (mis. pelanggan menerima hasilnya): ditutup dengan alasan, tanpa stok & jurnal. */
    public static function tutupRework(int $idRework, string $alasan): void
    {
        DB::transaction(function () use ($idRework, $alasan) {
            $rw = DB::table('rework')->where('id', $idRework)->lockForUpdate()->first();

            if (!$rw) {
                throw new \RuntimeException('Rework tidak ditemukan.');
            }
            if ($rw->status !== 'Dibuka') {
                throw new \RuntimeException("Rework {$rw->nomor} sudah {$rw->status}.");
            }

            DB::table('rework')->where('id', $idRework)->update([
                'status' => 'Ditutup',
                'catatan' => trim(($rw->catatan ? $rw->catatan . ' | ' : '') . 'Ditutup tanpa cetak ulang: ' . $alasan),
                'selesai_at' => now(), 'updated_at' => now(),
            ]);
            AuditLogger::log('updated', 'rework', $idRework, ['status' => 'Dibuka'], ['status' => 'Ditutup'], "Rework {$rw->nomor} ditutup tanpa cetak ulang: {$alasan}");
        });
    }
}
