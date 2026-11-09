<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostKategori extends Model
{
    protected $table = 'post_kategori';

    protected $fillable = ['nama', 'slug'];

    public function posts()
    {
        return $this->hasMany(Post::class, 'id_kategori');
    }
}
