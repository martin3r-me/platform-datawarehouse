<?php

namespace Platform\Datawarehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatawarehouseImport extends Model
{
    protected $table = 'datawarehouse_imports';

    protected $fillable = [
        'stream_id',
        'user_id',
        'status',
        'mode',
        'rows_received',
        'rows_imported',
        'rows_skipped',
        'error_log',
        'raw_payload',
        'duration_ms',
    ];

    protected $casts = [
        'error_log' => 'array',
    ];

    public function stream(): BelongsTo
    {
        return $this->belongsTo(DatawarehouseStream::class, 'stream_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    // --- Scopes ---

    public function scopeLatest($query)
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }
}
