<?php

namespace Platform\Datawarehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatawarehouseKpiSnapshot extends Model
{
    public $timestamps = false;

    protected $table = 'datawarehouse_kpi_snapshots';

    protected $fillable = [
        'kpi_id',
        'value',
        'calculated_at',
        'trigger',
    ];

    protected $casts = [
        'value'         => 'decimal:4',
        'calculated_at' => 'datetime',
    ];

    public function kpi(): BelongsTo
    {
        return $this->belongsTo(DatawarehouseKpi::class, 'kpi_id');
    }

    /**
     * Prune snapshots beyond the retention limit.
     */
    public static function prune(int $kpiId, ?int $keep = null): int
    {
        $keep = $keep ?? (int) config('datawarehouse.kpi.snapshot_retention', 365);

        $cutoff = static::where('kpi_id', $kpiId)
            ->orderByDesc('calculated_at')
            ->skip($keep)
            ->take(1)
            ->value('calculated_at');

        if (!$cutoff) {
            return 0;
        }

        return static::where('kpi_id', $kpiId)
            ->where('calculated_at', '<=', $cutoff)
            ->delete();
    }
}
