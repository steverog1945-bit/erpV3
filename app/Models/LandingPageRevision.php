<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingPageRevision extends Model
{
    use HasFactory;

    protected $table = 'landing_page_revisions';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'landing_page_id',
        'revision_number',
        'schema_version',
        'content_json',
        'theme_json',
        'seo_json',
        'created_by',
    ];

    protected $casts = [
        'landing_page_id' => 'integer',
        'revision_number' => 'integer',
        'schema_version' => 'string',
        'content_json' => 'array',
        'theme_json' => 'array',
        'seo_json' => 'array',
        'created_by' => 'integer',
    ];

    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class, 'landing_page_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id_user');
    }
}