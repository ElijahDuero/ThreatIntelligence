<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Investigation extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'tags',
        'graph_data',
    ];

    protected $casts = [
        'tags' => 'array',
        'graph_data' => 'array',
    ];

    public function searchQueries(): HasMany
    {
        return $this->hasMany(SearchQuery::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function reconMonitors(): HasMany
    {
        return $this->hasMany(ReconMonitor::class);
    }

    public function reconAlerts(): HasMany
    {
        return $this->hasMany(ReconAlert::class);
    }
}
