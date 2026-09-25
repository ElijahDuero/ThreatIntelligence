<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReconMonitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'investigation_id',
        'title',
        'target_type',
        'target_value',
        'frequency',
        'is_active',
        'webhook_url',
        'last_scanned_at',
        'last_seen_state',
        'findings_count',
        'error_message',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_scanned_at' => 'datetime',
        'last_seen_state' => 'array',
        'findings_count' => 'integer',
    ];

    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(ReconAlert::class);
    }
}
