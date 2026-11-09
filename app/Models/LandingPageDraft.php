<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPageDraft extends Model
{
    use HasFactory;

    protected $table = 'landing_page_drafts';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'landing_page_id',
        'version',
        'content_json',
        'theme_json',
        'seo_json',
        'updated_by',
    ];

    protected $casts = [
        'landing_page_id' => 'integer',
        'version' => 'integer',
        'content_json' => 'array',
        'theme_json' => 'array',
        'seo_json' => 'array',
        'updated_by' => 'integer',
    ];

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'landing_page_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id_user');
    }
}