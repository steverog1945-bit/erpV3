<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alias istilah publik per produk - kamus yang dipakai asisten landing
 * menerjemahkan kata konsumen ("banner", "spanduk", "baliho") ke produk
 * nyata. Dikelola dari Master Produk; tidak ada pemetaan istilah yang
 * di-hardcode di service.
 */
class ProdukAlias extends Model
{
    protected $table = 'produk_alias';

    protected $fillable = ['id_produk', 'alias'];

    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class, 'id_produk', 'id_produk');
    }
}
