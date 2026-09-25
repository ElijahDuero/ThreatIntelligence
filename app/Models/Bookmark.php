<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bookmark extends Model
{
    use HasFactory;

    protected $fillable = [
        'investigation_id',
        'search_result_id',
        'title',
        'url',
        'notes',
        'severity',
    ];

    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class);
    }

    public function searchResult(): BelongsTo
    {
        return $this->belongsTo(SearchResult::class);
    }
}
