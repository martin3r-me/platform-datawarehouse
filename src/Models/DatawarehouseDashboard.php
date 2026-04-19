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
}
