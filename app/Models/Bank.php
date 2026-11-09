<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $table = 'bank_perusahaan';
    protected $primaryKey = 'id_bank';
    public $timestamps = false;

    protected $fillable = ['nama_bank', 'nomor_rekening', 'atas_nama'];
}
