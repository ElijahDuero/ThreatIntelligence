<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchQuery extends Model
{
    use HasFactory;

    protected $fillable = [
        'investigation_id',
        'query',
        'engine',
        'scan_type',
        'use_tor',
        'deep_scrape',
        'collect_images',
        'results_count',
        'execution_time_seconds',
        'status',
        'error_message',
    ];

    protected $casts = [
        'use_tor' => 'boolean',
        'deep_scrape' => 'boolean',
        'collect_images' => 'boolean',
        'execution_time_seconds' => 'float',
    ];

    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(SearchResult::class);
    }

    /**
     * Scope query to search term.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where('query', 'like', "%{$term}%");
    }

    /**
     * Scope query by engine.
     */
    public function scopeEngine(Builder $query, string $engine): Builder
    {
        return $query->where('engine', $engine);
    }

    /**
     * Scope query to Tor routing status.
     */
    public function scopeTor(Builder $query, bool $useTor = true): Builder
    {
        return $query->where('use_tor', $useTor);
    }

    /**
     * Scope query by status.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
