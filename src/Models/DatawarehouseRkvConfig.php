<?php

namespace Platform\Datawarehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Per-team RKV Rückvergütung (JRV) configuration. Holds the whole parameter set
 * (staffeln, WKZ, growth factor, prior-year reference, stream/column mapping) in
 * one JSON `config` column, editable via LLM tool and rendered in the UI.
 *
 * Defaults mirror RKV_Tracker_2026.html exactly. Open-ended top staffel bands
 * use `b: null` (= no upper bound).
 */
class DatawarehouseRkvConfig extends Model
{
    protected $table = 'datawarehouse_rkv_configs';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->config)) {
                $model->config = self::defaultConfig();
            }
        });
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    /**
     * Get the team's config row, creating it with tracker defaults if missing.
     */
    public static function forTeamOrDefault(int $teamId, ?int $userId = null): self
    {
        return static::firstOrCreate(
            ['team_id' => $teamId],
            ['user_id' => $userId, 'config' => self::defaultConfig()]
        );
    }

    /**
     * Deep-merge a partial config patch onto the current config and persist.
     * Nested arrays are merged key-by-key; list values (staffel/wkz/vorjahr)
     * are replaced wholesale when present in the patch.
     */
    public function applyPatch(array $patch): void
    {
        $this->config = self::deepMerge($this->config ?? self::defaultConfig(), $patch);
        $this->save();
    }

    private static function deepMerge(array $base, array $patch): array
    {
        foreach ($patch as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && self::isAssoc($value)) {
                $base[$key] = self::deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    private static function isAssoc(array $arr): bool
    {
        return $arr !== [] && array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Tracker defaults (RKV_Tracker_2026.html). Kept as a pure function so the
     * seed is versioned in code and re-creatable.
     */
    public static function defaultConfig(): array
    {
        return [
            'factor'            => 1.87,
            'ist_through_month' => 6,
            'er' => [
                'label'              => 'Event Rent',
                'kreditor'           => 'EVENT RENT',
                'ist_stream_id'      => 14,
                'forecast_stream_id' => 16,
                'staffel' => [
                    ['l' => '< 200.000 €',  'v' => 0,      'b' => 199999,  's' => 0],
                    ['l' => '200 – 300 T€', 'v' => 200000, 'b' => 299999,  's' => 0.02],
                    ['l' => '300 – 400 T€', 'v' => 300000, 'b' => 399999,  's' => 0.03],
                    ['l' => '400 – 500 T€', 'v' => 400000, 'b' => 499999,  's' => 0.05],
                    ['l' => '500 – 600 T€', 'v' => 500000, 'b' => 599999,  's' => 0.065],
                    ['l' => '600 – 700 T€', 'v' => 600000, 'b' => 699999,  's' => 0.07],
                    ['l' => '700 – 800 T€', 'v' => 700000, 'b' => 799999,  's' => 0.0725],
                    ['l' => '800 – 900 T€', 'v' => 800000, 'b' => 899999,  's' => 0.075],
                    ['l' => '≥ 900.000 €',  'v' => 900000, 'b' => null,     's' => 0.0775],
                ],
            ],
            'ev' => [
                'label'              => 'eventura',
                'kreditor'           => 'EVENTURA',
                'ist_stream_id'      => 14,
                'forecast_stream_id' => 17,
                'jrv_schwelle'       => 300000,
                'staffel' => [
                    ['l' => '< 300.000 €',    'v' => 0,       'b' => 299999, 's' => 0],
                    ['l' => '300 – 400 T€',   'v' => 300000,  'b' => 399999, 's' => 0.015],
                    ['l' => '400 – 550 T€',   'v' => 400000,  'b' => 549999, 's' => 0.025],
                    ['l' => '550 – 700 T€',   'v' => 550000,  'b' => 699999, 's' => 0.035],
                    ['l' => '700 – 850 T€',   'v' => 700000,  'b' => 849999, 's' => 0.045],
                    ['l' => '850T€ – 1 Mio',  'v' => 850000,  'b' => 999999, 's' => 0.055],
                    ['l' => '≥ 1.000.000 €',  'v' => 1000000, 'b' => null,   's' => 0.075],
                ],
                'wkz' => [
                    ['ab' => 200000, 'wkz' => 0],
                    ['ab' => 210000, 'wkz' => 2000],
                    ['ab' => 220000, 'wkz' => 4000],
                    ['ab' => 230000, 'wkz' => 6000],
                    ['ab' => 240000, 'wkz' => 8000],
                    ['ab' => 250000, 'wkz' => 10000],
                    ['ab' => 260000, 'wkz' => 12000],
                    ['ab' => 270000, 'wkz' => 14000],
                    ['ab' => 280000, 'wkz' => 16000],
                    ['ab' => 290000, 'wkz' => 18000],
                    ['ab' => 300000, 'wkz' => 20000],
                ],
            ],
            'vorjahr' => [
                'er' => [1 => 1465.09, 2 => 1620.38, 3 => 11718.24, 4 => 11607.72, 5 => 11938.69, 6 => 4465.37, 7 => 39779.35, 8 => 3773.41, 9 => 57676.59, 10 => 27932.37, 11 => 66406.51, 12 => 317290.95],
                'ev' => [1 => 364.98, 2 => 759.71, 3 => 2065.95, 4 => 5434.84, 5 => 1759.48, 6 => 14524, 7 => 390.6, 8 => 104.84, 9 => 4165.38, 10 => 9276.65, 11 => 49206.19, 12 => 109930.08],
            ],
            'columns' => [
                'ist_netto'      => 'positionswert_netto',
                'ist_kreditor'   => 'kreditor_kurzbezeichnung',
                'ist_date'       => 'rechnungsdatum',
                'forecast_value' => 'gesamt_ek',
                'forecast_date'  => 'datum',
            ],
        ];
    }
}
