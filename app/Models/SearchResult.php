<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SearchResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'search_query_id',
        'title',
        'url',
        'description',
        'engine',
        'position',
        'is_onion',
        'is_blacklisted',
        'severity',
        'category',
        'credentials_found',
        'metadata',
    ];

    protected $casts = [
        'is_onion' => 'boolean',
        'is_blacklisted' => 'boolean',
        'credentials_found' => 'array',
        'metadata' => 'array',
    ];

    public function searchQuery(): BelongsTo
    {
        return $this->belongsTo(SearchQuery::class);
    }

    public function scrapedTarget(): HasOne
    {
        return $this->hasOne(ScrapedTarget::class);
    }

    public function bookmark(): HasOne
    {
        return $this->hasOne(Bookmark::class);
    }

    public function bookmarks(): HasOne
    {
        return $this->bookmark();
    }
}
