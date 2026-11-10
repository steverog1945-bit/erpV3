<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    use Auditable;
    protected $table = 'app_settings';

    protected $fillable = [
        'nama_aplikasi',
        'alamat',
        'telepon',
        'fax',
        'email',
        'website',
        'url_akses',
        'logo_path',
        'logo_ukuran',
        'logo_gaya',
        'logo_ringkas',
        'logo_ikon_path',
        'libur_kas_minggu',
        'batasi_jam_kerja',
        'approval_edit_nota',
        'format_nota',
        'nomor_nota_prefix',
        'nota_footer_text',
        'pembulatan_nota',
    ];

    protected $casts = [
        'libur_kas_minggu' => 'boolean',
        'approval_edit_nota' => 'boolean',
    ];

    /**
     * Selalu tepat 1 baris - dibuat otomatis kalau migrasinya belum sempat
     * jalan/baris sudah terhapus, supaya halaman lain (header, print) tidak
     * pernah pecah gara-gara baris pengaturan hilang.
     */
    public static function current(): self
    {
        return Cache::rememberForever('app_settings_current', function () {
            return self::first() ?? self::create(['nama_aplikasi' => 'Orion Grafika']);
        });
    }

    public static function clearCache(): void
    {
        Cache::forget('app_settings_current');
    }

    /**
     * Pembulatan MURNI untuk tampilan nota cetak (lihat catatan migrasi
     * 2026_09_18_120000) - tidak pernah dipakai untuk menghitung
     * total_bayar/pembayaran_riwayat/jurnal_umum, hanya untuk baris
     * "Pembulatan"/"Total Tagihan" & basis Krg. Bayar/Kembali di
     * kasir.print_nota. 'tidak' (atau nilai bukan angka apa pun) berarti
     * tidak ada pembulatan sama sekali - nominal dikembalikan apa adanya.
     */
    public static function bulatkan(float $nominal, ?string $kelipatanOverride = null): float
    {
        $kelipatan = (int) ($kelipatanOverride ?? self::current()->pembulatan_nota ?? 0);

        if ($kelipatan <= 0) {
            return $nominal;
        }

        return round($nominal / $kelipatan) * $kelipatan;
    }

    /** Alamat dasar aplikasi untuk QR yang dipindai dari HP (pengaturan url_akses, atau alamat saat ini). */
    public static function urlAkses(): string
    {
        return rtrim(static::current()->url_akses ?: url('/'), '/');
    }
}
