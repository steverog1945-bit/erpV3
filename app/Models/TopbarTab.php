<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tab modul yang tampil di baris kedua topbar. Isi bawaan (6 tab) dipakai
 * apa adanya kalau tabel ini kosong - supaya aplikasi langsung berjalan
 * seperti sebelum fitur pengaturan ini ada, tanpa migration data.
 *
 * URL adalah path internal situs (mis. "/dashboard"), bukan URL absolut;
 * ikon dibatasi pada preset di ICONS supaya tidak ada injeksi SVG dari
 * formulir.
 */
class TopbarTab extends Model
{
    protected $table = 'topbar_tabs';

    protected $fillable = ['urutan', 'label', 'url', 'icon', 'aktif'];

    protected $casts = [
        'aktif' => 'boolean',
        'urutan' => 'integer',
    ];

    /** Preset ikon -> path SVG (viewBox 24x24, stroke). */
    public const ICONS = [
        'dasbor' => 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
        'kasir' => 'M6 3h12v18l-2-1.4-2 1.4-2-1.4L10 21l-2-1.4L6 21zM9.5 8.5h5M9.5 12.5h5',
        'spk' => 'M4 9h16v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zM8 9V6a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v3',
        'bahan' => 'M4 6h16M4 12h16M4 18h16',
        'laporan' => 'M5 19h14M7.5 16l4-8 4 5 3-7.5',
        'pengaturan' => 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm8.5 4a7.6 7.6 0 0 0-.1-1.3l2-1.5-2-3.4-2.3 1a7.9 7.9 0 0 0-2.2-1.2L15.6 2H8.4l-.3 2.6a7.9 7.9 0 0 0-2.2 1.2l-2.3-1-2 3.4 2 1.5a7.6 7.6 0 0 0 0 2.6l-2 1.5 2 3.4 2.3-1a7.9 7.9 0 0 0 2.2 1.2l.3 2.6h7.2l.3-2.6a7.9 7.9 0 0 0 2.2-1.2l2.3 1 2-3.4-2-1.5c.1-.4.1-.9.1-1.3z',
        'daftar' => 'M4 6h16M4 12h16M4 18h7',
        'kotak' => 'M3 8l9-5 9 5-9 5-9-5zM3 8v8l9 5 9-5V8M12 13v8',
        'keranjang' => 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 8h14M9 21a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm8 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2z',
        'bintang' => 'M12 3l2.7 5.6 6.1.8-4.5 4.2 1.1 6-5.4-3-5.4 3 1.1-6L4.3 9.4l6.1-.8L12 3z',
        'kalender' => 'M4 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2zM4 10h16M9 3v4M15 3v4',
        'roda' => 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm9.4 4a8.4 8.4 0 0 0-.12-1.52l2.02-1.52-2-3.46-2.34 1a8.6 8.6 0 0 0-2.62-1.5L15.9 2.6h-4l-.44 2.4a8.6 8.6 0 0 0-2.62 1.5l-2.34-1-2 3.46 2.02 1.52a8.4 8.4 0 0 0 0 3.04l-2.02 1.52 2 3.46 2.34-1a8.6 8.6 0 0 0 2.62 1.5l.44 2.4h4l.44-2.4a8.6 8.6 0 0 0 2.62-1.5l2.34 1 2-3.46-2.02-1.52c.08-.5.12-1 .12-1.52z',
    ];

    /** Tab bawaan kalau tabel kosong (persis topbar sebelum fitur ini ada). */
    public const DEFAULTS = [
        ['urutan' => 10, 'label' => 'Dasbor', 'url' => '/dashboard', 'icon' => 'dasbor', 'aktif' => true],
        ['urutan' => 20, 'label' => 'Kasir', 'url' => '/penjualan/kasir', 'icon' => 'kasir', 'aktif' => true],
        ['urutan' => 30, 'label' => 'Produksi', 'url' => '/spk', 'icon' => 'spk', 'aktif' => true],
        ['urutan' => 40, 'label' => 'Bahan Baku', 'url' => '/bahan-baku', 'icon' => 'bahan', 'aktif' => true],
        ['urutan' => 50, 'label' => 'Laporan', 'url' => '/laporan/order', 'icon' => 'laporan', 'aktif' => true],
        ['urutan' => 60, 'label' => 'Pengaturan', 'url' => '/pengaturan-aplikasi', 'icon' => 'pengaturan', 'aktif' => true],
    ];

    /** Tab yang tampil di topbar (aktif saja, urut sesuai setingan). */
    public static function aktif(): array
    {
        $tabs = self::query()->where('aktif', true)->orderBy('urutan')->get(['label', 'url', 'icon']);

        $sumber = $tabs->isEmpty() ? self::DEFAULTS : $tabs->map(fn ($t) => [
            'label' => $t->label,
            'url' => $t->url,
            'icon' => $t->icon,
        ])->all();

        return array_map(fn ($t) => [
            'label' => $t['label'],
            'url' => $t['url'],
            'icon' => self::ICONS[$t['icon']] ?? self::ICONS['dasbor'],
        ], $sumber);
    }
}
