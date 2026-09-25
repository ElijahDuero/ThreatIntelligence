<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'recon_monitor_id',
        'investigation_id',
        'title',
        'summary',
        'external_url',
        'severity',
        'payload',
        'is_read',
    ];

    protected $casts = [
        'payload' => 'array',
        'is_read' => 'boolean',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(ReconMonitor::class, 'recon_monitor_id');
    }

    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class);
    }
}
