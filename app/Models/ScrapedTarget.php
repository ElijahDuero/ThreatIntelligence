<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScrapedTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'search_result_id',
        'url',
        'screenshot_path',
        'screenshot_metadata',
        'title',
        'status_code',
        'is_blacklisted',
        'response_time_seconds',
        'server',
        'headers',
        'metadata',
        'keywords',
        'custom_keywords',
        'keyword_intel',
        'sentiment',
        'emails',
        'documents',
        'images',
        'internal_links',
        'external_links',
        'raw_text_sample',
    ];

    protected $casts = [
        'is_blacklisted' => 'boolean',
        'screenshot_metadata' => 'array',
        'headers' => 'array',
        'metadata' => 'array',
        'keywords' => 'array',
        'custom_keywords' => 'array',
        'keyword_intel' => 'array',
        'sentiment' => 'array',
        'emails' => 'array',
        'documents' => 'array',
        'images' => 'array',
        'internal_links' => 'array',
        'external_links' => 'array',
        'response_time_seconds' => 'float',
    ];

    protected $appends = [
        'screenshot_url',
    ];

    public function searchResult(): BelongsTo
    {
        return $this->belongsTo(SearchResult::class);
    }

    public function getScreenshotUrlAttribute(): ?string
    {
        return $this->screenshot_path ? asset('storage/'.$this->screenshot_path) : null;
    }
}
