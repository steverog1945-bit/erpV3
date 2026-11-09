<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Post extends Model
{
    use SoftDeletes;

    protected $table = 'posts';

    protected $fillable = ['judul', 'slug', 'ringkasan', 'isi', 'gambar_sampul', 'id_kategori', 'tags', 'status', 'terbit_at', 'disematkan', 'tampil_publik', 'id_penulis', 'menit_baca'];

    protected $casts = ['terbit_at' => 'datetime', 'disematkan' => 'boolean', 'tampil_publik' => 'boolean'];

    public function kategori()
    {
        return $this->belongsTo(PostKategori::class, 'id_kategori');
    }

    public function penulis()
    {
        return $this->belongsTo(User::class, 'id_penulis', 'id_user');
    }

    /** Yang boleh dibaca semua orang: berstatus terbit dan waktu terbitnya sudah tiba. */
    public function scopeTerbit($q)
    {
        return $q->where('status', 'terbit')->where(fn ($w) => $w->whereNull('terbit_at')->orWhere('terbit_at', '<=', now()));
    }

    /** Yang boleh dibaca TANPA login: sudah terbit dan sengaja ditandai tampil di halaman depan. */
    public function scopePublik($q)
    {
        return $q->terbit()->where('tampil_publik', true);
    }

    /** Draft | Terjadwal | Terbit (untuk label). */
    public function getStatusTampilAttribute(): string
    {
        if ($this->status !== 'terbit') {
            return 'Draft';
        }

        return $this->terbit_at && $this->terbit_at->isFuture() ? 'Terjadwal' : 'Terbit';
    }

    public function getUrlSampulAttribute(): ?string
    {
        return $this->gambar_sampul ? asset('storage/' . $this->gambar_sampul) : null;
    }

    /** Isi siap tampil (penanda alamat gambar diganti alamat storage saat ini). */
    public function getIsiHtmlAttribute(): string
    {
        return str_replace('@@STORAGE@@', asset('storage'), (string) $this->isi);
    }

    public function getNamaPenulisAttribute(): string
    {
        return optional(optional($this->penulis)->karyawan)->nama_karyawan ?: (optional($this->penulis)->username ?: 'Admin');
    }

    public function getDaftarTagAttribute(): array
    {
        return collect(explode(',', (string) $this->tags))->map(fn ($t) => trim($t))->filter()->values()->all();
    }

    public static function buatSlug(string $judul, ?int $abaikanId = null): string
    {
        $dasar = Str::slug(Str::limit($judul, 80, '')) ?: 'postingan';
        $slug = $dasar;
        $i = 2;
        while (static::withTrashed()->where('slug', $slug)->when($abaikanId, fn ($q) => $q->where('id', '!=', $abaikanId))->exists()) {
            $slug = $dasar . '-' . $i++;
        }

        return $slug;
    }
}
