<?php

namespace Platform\Datawarehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable panel on a dashboard (chart / progress / summary / value tile).
 * `type` maps to config('datawarehouse.dashboard_panels'); `config` holds the
 * panel's KPI reference(s) + options.
 */
class DatawarehouseDashboardPanel extends Model
{
    protected $table = 'datawarehouse_dashboard_panels';

    protected $fillable = [
        'dashboard_id',
        'type',
        'title',
        'config',
        'position',
    ];

    protected $casts = [
        'config'   => 'array',
        'position' => 'integer',
    ];

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(DatawarehouseDashboard::class, 'dashboard_id');
    }
}
