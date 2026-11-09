<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kategori extends Model
{
    // Nama tabel sesuai SQL Dump
    protected $table = 'kategori';
    
    // Primary key custom (bukan 'id')
    protected $primaryKey = 'id_kategori';
    
    // Karena kita input manual (tidak pakai timestamps created_at/updated_at)
    public $timestamps = false;

    // Kolom yang boleh diisi secara massal
    protected $fillable = ['nama_kategori'];

    public function bahanBaku()
    {
        return $this->hasMany(BahanBaku::class, 'id_kategori', 'id_kategori');
    }
}