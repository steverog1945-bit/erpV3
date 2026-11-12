<?php

namespace App\Models;

use App\Services\StatusPembayaran;
use Illuminate\Database\Eloquent\Model;

class Transaksi extends Model
{
    protected $table = 'transaksi';
    protected $primaryKey = 'id_transaksi';

    protected $fillable = [
        'nomor_nota',
        'tipe_order',
        'no_po',
        'id_konsumen',
        'nama_konsumen_manual',
        'id_user_kasir',
        'tgl_order',
        'total_bayar',
        'status_pembayaran',
        'status_nota',
        'status_order',
        'alasan_batal_nota',
        'tgl_dibatalkan_nota',
        'id_user_pembatal_nota',
        'status_collecting',
        'tgl_collecting',
        'id_user_collecting',
        'metode_pembayaran',
    ];

    protected $casts = [
        'tgl_order'           => 'datetime',
        'total_bayar'         => 'decimal:2',
        'tgl_dibatalkan_nota' => 'datetime',
        'tgl_collecting'      => 'datetime',
    ];

    public function konsumen()
    {
        return $this->belongsTo(Konsumen::class, 'id_konsumen', 'id_konsumen');
    }

    public function kasir()
    {
        return $this->belongsTo(User::class, 'id_user_kasir', 'id_user');
    }

    public function pembatal()
    {
        return $this->belongsTo(User::class, 'id_user_pembatal_nota', 'id_user');
    }

    public function details()
    {
        return $this->hasMany(TransaksiDetail::class, 'id_transaksi', 'id_transaksi');
    }

    public function pembayaran()
    {
        return $this->hasMany(PembayaranRiwayat::class, 'id_transaksi', 'id_transaksi');
    }

    public function deposits()
    {
        return $this->hasMany(KonsumenDeposit::class, 'id_transaksi', 'id_transaksi');
    }

    /**
     * Status pembayaran yang SEBENARNYA, diturunkan dari baris
     * pembayaran_riwayat - bukan dari kolom status_pembayaran yang menyimpan
     * salinan (denormalisasi) dan bisa sudah tertinggal.
     *
     * Kenapa accessor ini ada: kolom status_pembayaran pernah menampilkan
     * "Lunas" sementara pembayaran_riwayat bilang belum ada uang masuk sepeser
     * pun, sehingga satu halaman memuat dua status yang bertentangan. Aturan
     * penentuannya sengaja TIDAK ditulis di sini - semuanya milik
     * App\Services\StatusPembayaran supaya PHP, SQL (Monitoring Kasir), dan
     * perintah CLI memakai satu aturan yang sama.
     */
    public function getComputedStatusPembayaranAttribute(): string
    {
        return StatusPembayaran::dari(
            (float) $this->total_bayar,
            (float) ($this->pembayaran()->sum('nominal') ?? 0)
        );
    }

    /**
     * Status yang ditampilkan ke kasir. Inilah yang dipakai SEMUA layar
     * (badge Monitoring, modal riwayat, form pembayaran, nota cetak) supaya
     * tidak pernah ada dua jawaban berbeda untuk nota yang sama.
     *
     * Kalau kolom tersimpan ternyata belum sinkron, kembalikan status yang
     * benar. Perbaikannya menetap lewat `artisan kas:selaraskan-status`.
     */
    public function getDisplayStatusPembayaranAttribute(): string
    {
        return $this->computed_status_pembayaran;
    }

    /** Nominal uang yang benar-benar sudah diterima untuk nota ini. */
    public function getTotalDiterimaAttribute(): float
    {
        return (float) ($this->pembayaran()->sum('nominal') ?? 0);
    }
}
