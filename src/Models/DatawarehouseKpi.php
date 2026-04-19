<?php

namespace Platform\Datawarehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DatawarehouseKpi extends Model
{
    use SoftDeletes;

    protected $table = 'datawarehouse_kpis';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'name',
        'icon',
        'variant',
        'unit',
        'format',
        'decimals',
        'position',
        'definition',
        'cached_value',
        'cached_at',
        'status',
        'last_error',
    ];

    protected $casts = [
        'definition'   => 'array',
        'cached_value' => 'decimal:4',
        'cached_at'    => 'datetime',
        'decimals'     => 'integer',
        'position'     => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $kpi) {
            if (empty($kpi->uuid)) {
                $kpi->uuid = (string) Str::uuid();
            }
        });
    }

    // --- Relationships ---

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(DatawarehouseKpiSnapshot::class, 'kpi_id');
    }

    // --- Scopes ---

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // --- Helpers ---

    public function isCacheValid(): bool
    {
        if (!$this->cached_at) {
            return false;
        }

        $streams = $this->definition['streams'] ?? [];
        if (empty($streams)) {
            return false;
        }

        $streamIds = collect($streams)->pluck('stream_id')->toArray();

        $lastImport = DatawarehouseStream::whereIn('id', $streamIds)
            ->where('team_id', $this->team_id)
            ->max('last_run_at');

        if (!$lastImport) {
            return true;
        }

        return $this->cached_at->greaterThan($lastImport);
    }
}
