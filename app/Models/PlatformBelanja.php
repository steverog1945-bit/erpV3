<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Platform belanja online (Tokopedia, Shopee, dst) - master yang bisa dikelola pengguna. */
class PlatformBelanja extends Model
{
    protected $table = 'platform_belanja';

    protected $fillable = ['nama', 'catatan', 'aktif', 'urutan'];

    protected $casts = ['aktif' => 'boolean'];
}
