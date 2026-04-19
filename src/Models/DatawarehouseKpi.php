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
        'display_range',
        'cached_comparison_value',
        'status',
        'last_error',
    ];

    protected $casts = [
        'definition'   => 'array',
        'cached_value'            => 'decimal:4',
        'cached_comparison_value' => 'decimal:4',
        'cached_at'               => 'datetime',
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

    public function hasDateColumn(): bool
    {
        return !empty($this->definition['calendar_filters']['date_column']);
    }

    public function trendDirection(): ?string
    {
        if ($this->cached_value === null || $this->cached_comparison_value === null) {
            return null;
        }

        $current = (float) $this->cached_value;
        $comparison = (float) $this->cached_comparison_value;

        if ($comparison == 0) {
            return $current > 0 ? 'up' : null;
        }

        if ($current > $comparison) {
            return 'up';
        }
        if ($current < $comparison) {
            return 'down';
        }

        return null;
    }

    public function trendValue(): ?string
    {
        if ($this->cached_value === null || $this->cached_comparison_value === null) {
            return null;
        }

        $current = (float) $this->cached_value;
        $comparison = (float) $this->cached_comparison_value;

        if ($comparison == 0) {
            return $current != 0 ? '+100%' : null;
        }

        $change = (($current - $comparison) / abs($comparison)) * 100;
        $sign = $change >= 0 ? '+' : '';

        return $sign . number_format($change, 1, ',', '.') . '%';
    }

    public function displayRangeLabel(): ?string
    {
        if (!$this->hasDateColumn() || !$this->display_range) {
            return null;
        }

        return \Platform\Datawarehouse\Services\KpiQueryBuilder::DATE_RANGE_MAP[$this->display_range] ?? null;
    }

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
