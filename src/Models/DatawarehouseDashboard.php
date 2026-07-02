<?php

namespace Platform\Datawarehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class DatawarehouseDashboard extends Model
{
    use SoftDeletes;

    protected $table = 'datawarehouse_dashboards';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'name',
        'description',
        'icon',
        'view_type',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $dashboard) {
            if (empty($dashboard->uuid)) {
                $dashboard->uuid = (string) Str::uuid();
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

    public function kpis(): BelongsToMany
    {
        return $this->belongsToMany(DatawarehouseKpi::class, 'datawarehouse_dashboard_kpis', 'dashboard_id', 'kpi_id')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    // --- Scopes ---

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    // --- Custom views ---

    /** A dashboard that renders a registered custom view instead of the KPI grid. */
    public function isCustomView(): bool
    {
        return !empty($this->view_type);
    }

    /** Registry entry for this dashboard's view_type (or null). */
    public function viewConfig(): ?array
    {
        if (!$this->isCustomView()) {
            return null;
        }
        return config('datawarehouse.dashboard_views.' . $this->view_type);
    }

    public static function customViewFor(int $teamId, string $viewType): ?self
    {
        return static::forTeam($teamId)->where('view_type', $viewType)->first();
    }

    /**
     * Ensure a dashboard row exists for every registered custom view of the team,
     * so they appear in the dashboards list under /dashboards/{id}. Idempotent.
     */
    public static function ensureRegisteredViews(int $teamId, ?int $userId = null): void
    {
        foreach ((array) config('datawarehouse.dashboard_views', []) as $type => $def) {
            static::firstOrCreate(
                ['team_id' => $teamId, 'view_type' => $type],
                [
                    'user_id'  => $userId,
                    'name'     => $def['label'] ?? ucfirst($type),
                    'icon'     => $def['icon'] ?? 'squares-2x2',
                    'position' => $def['position'] ?? 900,
                ],
            );
        }
    }
}
