<?php

namespace Platform\Datawarehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'description',
        'icon',
        'variant',
        'unit',
        'format',
        'decimals',
        'position',
        'parent_kpi_id',
        'is_group',
        'definition',
        'cached_value',
        'cached_at',
        'display_range',
        'target_value',
        'target_kpi_id',
        'target_direction',
        'green_pct',
        'yellow_pct',
        'cached_comparison_value',
        'status',
        'last_error',
    ];

    protected $casts = [
        'definition'   => 'array',
        'is_group'     => 'boolean',
        'target_value'            => 'decimal:4',
        'green_pct'               => 'integer',
        'yellow_pct'              => 'integer',
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

    /**
     * Parent KPI in a drill-down hierarchy (null for top-level KPIs).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_kpi_id');
    }

    /**
     * Child KPIs that drill down from this one.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_kpi_id')->orderBy('position');
    }

    /**
     * Reference KPI used as the Ampel target (e.g. the Plan KPI), if any.
     */
    public function targetKpi(): BelongsTo
    {
        return $this->belongsTo(self::class, 'target_kpi_id');
    }

    /**
     * Resolve the Ampel target: a fixed target_value, or the current cached
     * value of the referenced target KPI. Null when no target is configured.
     */
    public function resolveTarget(): ?float
    {
        if ($this->target_value !== null) {
            return (float) $this->target_value;
        }
        if ($this->target_kpi_id) {
            $ref = self::find($this->target_kpi_id);
            return $ref && $ref->cached_value !== null ? (float) $ref->cached_value : null;
        }
        return null;
    }

    /**
     * Evaluate the RAG (Ampel) status against the configured target.
     * Returns ['status' => green|yellow|red, 'achievement' => %, 'target' => float]
     * or null when no target is configured / value is missing.
     */
    public function ampel(): ?array
    {
        $target = $this->resolveTarget();
        if ($target === null || (float) $target === 0.0 || $this->cached_value === null) {
            return null;
        }

        $value = (float) $this->cached_value;
        $green = $this->green_pct ?? 100;
        $yellow = $this->yellow_pct ?? 80;

        if (($this->target_direction ?? 'higher_better') === 'lower_better') {
            // Lower is better (costs, cancellations): being under target is good.
            $achievement = $value == 0.0 ? 100.0 : ($target / $value) * 100;
        } else {
            $achievement = ($value / $target) * 100;
        }

        $status = $achievement >= $green ? 'green' : ($achievement >= $yellow ? 'yellow' : 'red');

        return [
            'status'      => $status,
            'achievement' => round($achievement, 1),
            'target'      => $target,
        ];
    }

    public function dashboards(): BelongsToMany
    {
        return $this->belongsToMany(DatawarehouseDashboard::class, 'datawarehouse_dashboard_kpis', 'kpi_id', 'dashboard_id')
            ->withPivot('position')
            ->withTimestamps();
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
