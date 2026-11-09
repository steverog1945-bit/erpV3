<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LandingPage extends Model
{
    use HasFactory;

    protected $table = 'landing_pages';
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $fillable = [
        'slug',
        'title',
        'status',
        'published_revision_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => 'string',
        'published_revision_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function draft(): HasOne
    {
        return $this->hasOne(LandingPageDraft::class, 'landing_page_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(LandingPageRevision::class, 'landing_page_id')->orderByDesc('revision_number');
    }

    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(LandingPageRevision::class, 'published_revision_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(LandingPageMedia::class, 'landing_page_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id_user');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'id_user');
    }
}