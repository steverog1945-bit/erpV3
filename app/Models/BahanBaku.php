<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BahanBaku extends Model
{
    protected $table = 'bahan_baku';
    protected $primaryKey = 'id_bahan';
    // $timestamps=false SEBELUMNYA di sini dengan komentar "karena di SQL
    // Dump tidak ada created_at" - sudah tidak benar, migration
    // 2026_02_25_112610_add_missing_erp_tables.php sudah menambahkan kolom
    // created_at/updated_at, dan BahanBakuController bahkan sort default-nya
    // pakai created_at. Tanpa Eloquent mengelola timestamps, updated_at
    // selalu NULL selamanya (tidak ada DEFAULT di kolomnya) tiap kali bahan
    // diedit lewat update() di controller.

    protected $casts = [
        'stok'             => 'decimal:4',
        'stok_minimal'     => 'decimal:3',
        'nilai_konversi'   => 'decimal:3',
        'lebar_roll'       => 'decimal:3',
        'panjang_roll'     => 'decimal:3',
        'harga_rata_rata'  => 'decimal:2',
    ];

    protected $fillable = [
    'nama_bahan', 
    'kode_bahan', 
    'id_mesin', 
    'id_kategori', 
    'stok', 
    'stok_minimal', // WAJIB ADA
    'id_satuan_stok', 
    'id_satuan_hitung', 
    'nilai_konversi',
    'lebar_roll',
    'panjang_roll',
    'satuan_stok',
    'harga_rata_rata',
    'id_merk',
    'id_jenis_bahan',
    'status'
];
public function kategori()
{
    return $this->belongsTo(Kategori::class, 'id_kategori');
}

public function merk()
{
    return $this->belongsTo(Merk::class, 'id_merk');
}

public function jenisBahan()
{
    return $this->belongsTo(JenisBahan::class, 'id_jenis_bahan');
}

public function mesin()
{
    return $this->belongsTo(Mesin::class, 'id_mesin');
}
public function satuanStok() {
    return $this->belongsTo(Satuan::class, 'id_satuan_stok', 'id_satuan');
}

public function satuanHitung() {
    return $this->belongsTo(Satuan::class, 'id_satuan_hitung', 'id_satuan');
}
}