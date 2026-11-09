<?php

namespace App\Services;

use App\Models\Quotation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Quotation / Penawaran (LEVEL 2, bagian 14 dokumen audit). Harga tiap baris
 * dihitung di server memakai HargaService yang SAMA dengan layar Input Order
 * (price list per golongan / promo / finishing / jasa), bisa ditimpa manual
 * per baris. Nota (transaksi) tetap menjadi Sales Order; quotation berujung
 * ke nota lewat tandaiMenjadiOrder() saat order dibuat dari penawaran.
 */
class QuotationService
{
    /**
     * @param  array<string,mixed>  $data  Sudah tervalidasi controller
     */
    public static function simpan(array $data, ?Quotation $quotation = null): Quotation
    {
        return DB::transaction(function () use ($data, $quotation) {
            $konsumen = !empty($data['id_konsumen']) ? DB::table('konsumen')->where('id_konsumen', $data['id_konsumen'])->first() : null;

            $idGolongan = $data['id_golongan'] ?? ($konsumen->id_golongan ?? null) ?: DB::table('golongan_konsumen')->orderBy('id_golongan')->value('id_golongan');

            $baris = [];
            foreach ($data['items'] as $i => $item) {
                $baris[] = self::hitungBaris($item, $idGolongan, $i + 1);
            }

            $subtotal = round(array_sum(array_column($baris, 'subtotal')), 2);
            $diskon = round((float) ($data['diskon'] ?? 0), 2);

            if ($diskon > $subtotal) {
                throw new \RuntimeException('Diskon tidak boleh melebihi subtotal.');
            }

            $header = [
                'tanggal' => $data['tanggal'],
                'berlaku_sampai' => $data['berlaku_sampai'],
                'id_konsumen' => $konsumen->id_konsumen ?? null,
                'nama_pelanggan' => $data['nama_pelanggan'] ?: ($konsumen->nama_konsumen ?? ''),
                'telepon' => $data['telepon'] ?? ($konsumen->telepon ?? null),
                'id_golongan' => $idGolongan,
                'subtotal' => $subtotal,
                'diskon' => $diskon,
                'total' => round($subtotal - $diskon, 2),
                'catatan' => $data['catatan'] ?? null,
            ];

            if ($header['nama_pelanggan'] === '') {
                throw new \RuntimeException('Nama pelanggan wajib diisi.');
            }

            if ($quotation) {
                $quotation->update($header);
                $quotation->items()->delete();
            } else {
                $quotation = Quotation::create($header + [
                    'nomor' => DocumentNumberService::generate('QT'),
                    'status' => 'Draft',
                    'id_user_pembuat' => Auth::id(),
                ]);
            }

            $quotation->items()->createMany($baris);

            return $quotation;
        });
    }

    private static function hitungBaris(array $item, $idGolongan, int $nomorBaris): array
    {
        $qty = (float) $item['qty'];
        $hargaManual = isset($item['harga_satuan']) && $item['harga_satuan'] !== '' ? (float) $item['harga_satuan'] : 0.0;
        $catatan = $item['catatan'] ?? null;

        if (($item['tipe'] ?? 'Produk') === 'Jasa') {
            $jasa = DB::table('jasa')->where('id_jasa', $item['id_jasa'] ?? null)->first();

            if (!$jasa) {
                throw new \RuntimeException("Jasa pada baris {$nomorBaris} tidak ditemukan.");
            }

            $harga = $hargaManual > 0 ? $hargaManual : HargaService::getHargaJasaEfektif((int) $jasa->id_jasa, $qty);

            if ($harga <= 0) {
                throw new \RuntimeException("Harga jasa \"{$jasa->nama_jasa}\" (baris {$nomorBaris}) belum diatur - isi harga manual.");
            }

            return [
                'tipe' => 'Jasa', 'id_jasa' => $jasa->id_jasa, 'nama_item' => $jasa->nama_jasa, 'qty' => $qty, 'qty_hitung' => $qty,
                'harga_satuan' => $harga, 'biaya_finishing' => 0, 'biaya_desain' => 0, 'subtotal' => round($harga * $qty, 2), 'catatan' => $catatan,
            ];
        }

        $produk = DB::table('produk')->where('id_produk', $item['id_produk'] ?? null)->first();

        if (!$produk) {
            throw new \RuntimeException("Produk pada baris {$nomorBaris} tidak ditemukan.");
        }

        $p = (float) ($item['p'] ?? 0);
        $l = (float) ($item['l'] ?? 0);
        // Produk yang dihitung per qty tidak punya ukuran: P/L yang ikut terkirim (mis. sisa isian sebelum
        // produk diganti) dibuang supaya tidak tercetak di penawaran.
        if ($produk->metode_hitung !== 'ukuran') {
            $p = 0.0;
            $l = 0.0;
        }
        $qtyHitung = $produk->metode_hitung === 'ukuran' ? ($p * $l / 10000) * $qty : $qty;

        if ($qtyHitung <= 0) {
            throw new \RuntimeException("Ukuran/qty pada baris {$nomorBaris} tidak valid.");
        }

        $harga = $hargaManual > 0 ? $hargaManual : HargaService::getHargaEfektif((int) $produk->id_produk, (int) $idGolongan, $qtyHitung)['harga_jual'];

        if ($harga <= 0) {
            throw new \RuntimeException("Harga \"{$produk->nama_produk}\" (baris {$nomorBaris}) belum diatur di Price List untuk golongan ini - isi harga manual.");
        }

        $idFinishing = !empty($item['id_finishing']) ? (int) $item['id_finishing'] : null;
        $biayaFinishing = 0.0;

        if ($idFinishing) {
            $biayaFinishing = isset($item['biaya_finishing']) && $item['biaya_finishing'] !== ''
                ? max(0, (float) $item['biaya_finishing'])
                : HargaService::getHargaFinishingEfektif($idFinishing, $qty);
        }

        $biayaDesain = max(0, (float) ($item['biaya_desain'] ?? 0));

        return [
            'tipe' => 'Produk', 'id_produk' => $produk->id_produk, 'id_finishing' => $idFinishing, 'nama_item' => $produk->nama_produk,
            'p_ukuran' => $p, 'l_ukuran' => $l, 'qty' => $qty, 'qty_hitung' => $qtyHitung, 'harga_satuan' => $harga,
            'biaya_finishing' => $biayaFinishing, 'biaya_desain' => $biayaDesain,
            'subtotal' => round($harga * $qtyHitung + $biayaFinishing + $biayaDesain, 2), 'catatan' => $catatan,
        ];
    }

    public static function kirim(Quotation $q): void
    {
        self::ubahStatus($q, ['Draft'], 'Terkirim', 'Hanya penawaran Draft yang bisa ditandai terkirim.', true);
    }

    public static function tanggapi(Quotation $q, string $keputusan): void
    {
        self::ubahStatus($q, ['Terkirim'], $keputusan, 'Tanggapan pelanggan hanya bisa dicatat untuk penawaran berstatus Terkirim.', true);
    }

    /** Dipanggil PenjualanController::store setelah order dari penawaran tersimpan. False = tidak memenuhi syarat (order tetap sah). */
    public static function tandaiMenjadiOrder(int $idQuotation, int $idTransaksi): bool
    {
        return DB::transaction(function () use ($idQuotation, $idTransaksi) {
            $q = Quotation::whereKey($idQuotation)->lockForUpdate()->first();

            if (!$q || !in_array($q->status, ['Terkirim', 'Disetujui'], true)) {
                return false;
            }

            if ($q->status === 'Terkirim' && $q->status_efektif === 'Kedaluwarsa') {
                return false;
            }

            $q->update(['status' => 'Menjadi Order', 'id_transaksi' => $idTransaksi]);

            return true;
        });
    }

    private static function ubahStatus(Quotation $q, array $dari, string $ke, string $pesanGagal, bool $tolakKedaluwarsa): void
    {
        DB::transaction(function () use ($q, $dari, $ke, $pesanGagal, $tolakKedaluwarsa) {
            $fresh = Quotation::whereKey($q->id)->lockForUpdate()->firstOrFail();

            if (!in_array($fresh->status, $dari, true)) {
                throw new \RuntimeException($pesanGagal);
            }

            if ($tolakKedaluwarsa && $ke === 'Disetujui' && $fresh->status_efektif === 'Kedaluwarsa') {
                throw new \RuntimeException('Penawaran sudah kedaluwarsa - buat penawaran baru.');
            }

            $fresh->update(['status' => $ke]);
        });
    }
}
