<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPageMedia extends Model
{
    use HasFactory;

    protected $table = 'landing_page_media';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'landing_page_id',
        'disk',
        'path',
        'mime_type',
        'size',
        'metadata',
        'uploaded_by',
    ];

    protected $casts = [
        'landing_page_id' => 'integer',
        'size' => 'integer',
        'metadata' => 'array',
        'uploaded_by' => 'integer',
    ];

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'landing_page_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'id_user');
    }
}