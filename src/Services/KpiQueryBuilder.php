<?php

namespace Platform\Datawarehouse\Services;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Models\DatawarehouseKpiSnapshot;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseStreamColumn;
use Platform\Datawarehouse\Models\DatawarehouseStreamRelation;

class KpiQueryBuilder
{
    private const ALLOWED_AGGREGATIONS = ['SUM', 'COUNT', 'AVG', 'MIN', 'MAX'];
    private const ALLOWED_OPERATORS = ['=', '!=', '<', '>', '<=', '>=', 'LIKE'];
    private const ALLOWED_TERM_OPERATORS = ['+', '-', '*', '/'];
    private const COLUMN_REGEX = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';
    private const ALIAS_REGEX = '/^s\d+$/';

    private const CALENDAR_ALIAS = '_cal';
    private const CALENDAR_TABLE = 'dw_dim_date';
    private const CALENDAR_COLUMNS = [
        'weekday', 'weekday_num', 'is_weekend', 'kw', 'month', 'quarter', 'year',
    ];

    public const DATE_RANGE_MAP = [
        'current_month'    => 'Laufender Monat',
        'current_quarter'  => 'Laufendes Quartal',
        'current_year'     => 'Laufendes Jahr',
        'current_week'     => 'Laufende Woche',
        'last_7_days'      => 'Letzte 7 Tage',
        'last_30_days'     => 'Letzte 30 Tage',
        'last_90_days'     => 'Letzte 90 Tage',
        'last_12_months'   => 'Letzte 12 Monate',
        'previous_month'   => 'Vormonat',
        'previous_quarter' => 'Vorquartal',
        'previous_year'    => 'Vorjahr',
        'year_to_date'     => 'Jahr bis heute',
    ];

    private const COMPARISON_MAP = [
        'current_month'    => 'previous_month',
        'current_quarter'  => 'previous_quarter',
        'current_year'     => 'previous_year',
        'current_week'     => '_prev_week',
        'last_7_days'      => '_prev_7_days',
        'last_30_days'     => '_prev_30_days',
        'last_90_days'     => '_prev_90_days',
        'last_12_months'   => '_prev_12_months',
        'previous_month'   => '_prev_prev_month',
        'previous_quarter' => '_prev_prev_quarter',
        'previous_year'    => '_prev_prev_year',
        'year_to_date'     => '_prev_ytd',
    ];

    /**
     * Return options for UI dropdowns.
     */
    public static function dateRangeOptions(): array
    {
        return self::DATE_RANGE_MAP;
    }

    /**
     * Execute a KPI query and return the result.
     */
    public function execute(DatawarehouseKpi $kpi): ?float
    {
        $definition = $kpi->definition;

        if (empty($definition['streams'] ?? []) || empty($this->extractAggregationTerms($definition))) {
            throw new \InvalidArgumentException('KPI definition incomplete: streams and aggregation required.');
        }

        [$query, $resolvedStreams, $baseAlias] = $this->buildBaseQuery($kpi);

        $selectRaw = $this->buildAggregationSelect($definition, $resolvedStreams, $baseAlias, $kpi->team_id);
        $result = $query->selectRaw($selectRaw)->first();

        return $result ? (float) $result->kpi_result : null;
    }

    /**
     * Execute a KPI query for a specific date range, overriding any stored range.
     */
    public function executeForRange(DatawarehouseKpi $kpi, string $range): ?float
    {
        if (!$kpi->hasDateColumn()) {
            return $this->execute($kpi);
        }

        $dates = self::resolveDateRange($range);
        if (!$dates) {
            return null;
        }

        // Clone definition and remove date_range from calendar_filters
        // so buildBaseQuery() won't apply it — we apply it ourselves.
        $originalDef = $kpi->definition;
        $modifiedDef = $originalDef;
        unset($modifiedDef['calendar_filters']['date_range']);

        $kpi->definition = $modifiedDef;

        try {
            $definition = $kpi->definition;

            if (empty($definition['streams'] ?? []) || empty($this->extractAggregationTerms($definition))) {
                throw new \InvalidArgumentException('KPI definition incomplete.');
            }

            [$query, $resolvedStreams, $baseAlias] = $this->buildBaseQuery($kpi);

            // Apply our explicit date range
            $this->applyExplicitDateRange($query, $definition, $dates[0], $dates[1]);

            $selectRaw = $this->buildAggregationSelect($definition, $resolvedStreams, $baseAlias, $kpi->team_id);
            $result = $query->selectRaw($selectRaw)->first();

            return $result ? (float) $result->kpi_result : null;
        } finally {
            $kpi->definition = $originalDef;
        }
    }

    /**
     * Execute all 11 date ranges + their comparison values.
     */
    public function executeAllRanges(DatawarehouseKpi $kpi): array
    {
        if (!$kpi->hasDateColumn()) {
            return [];
        }

        $results = [];

        foreach (array_keys(self::DATE_RANGE_MAP) as $range) {
            $value = $this->executeForRange($kpi, $range);
            $comparisonDates = $this->resolveComparisonRange($range);
            $comparison = null;

            if ($comparisonDates) {
                $comparison = $this->executeForDatePair($kpi, $comparisonDates[0], $comparisonDates[1]);
            }

            $results[$range] = [
                'value'      => $value,
                'comparison' => $comparison,
                'label'      => self::DATE_RANGE_MAP[$range],
            ];
        }

        return $results;
    }

    /**
     * Resolve the comparison period [start, end] for a given range.
     */
    public function resolveComparisonRange(string $range): ?array
    {
        $now = Carbon::now();

        return match ($range) {
            'current_month'    => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'current_quarter'  => [$now->copy()->subQuarter()->startOfQuarter(), $now->copy()->subQuarter()->endOfQuarter()],
            'current_year'     => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'current_week'     => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'last_7_days'      => [$now->copy()->subDays(13)->startOfDay(), $now->copy()->subDays(7)->endOfDay()],
            'last_30_days'     => [$now->copy()->subDays(59)->startOfDay(), $now->copy()->subDays(30)->endOfDay()],
            'last_90_days'     => [$now->copy()->subDays(179)->startOfDay(), $now->copy()->subDays(90)->endOfDay()],
            'last_12_months'   => [$now->copy()->subMonths(24)->startOfMonth(), $now->copy()->subMonths(12)->subDay()->endOfMonth()],
            'previous_month'   => [$now->copy()->subMonths(2)->startOfMonth(), $now->copy()->subMonths(2)->endOfMonth()],
            'previous_quarter' => [$now->copy()->subQuarters(2)->startOfQuarter(), $now->copy()->subQuarters(2)->endOfQuarter()],
            'previous_year'    => [$now->copy()->subYears(2)->startOfYear(), $now->copy()->subYears(2)->endOfYear()],
            'year_to_date'     => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->setMonth($now->month)->setDay($now->day)->endOfDay()],
            default            => null,
        };
    }

    /**
     * Execute and update the cache on the KPI model.
     */
    public function executeAndCache(DatawarehouseKpi $kpi, string $trigger = 'pull_refresh'): ?float
    {
        try {
            $displayRange = $kpi->display_range;
            $value = null;
            $comparisonValue = null;

            if ($kpi->hasDateColumn() && $displayRange) {
                $value = $this->executeForRange($kpi, $displayRange);

                $compDates = $this->resolveComparisonRange($displayRange);
                if ($compDates) {
                    $comparisonValue = $this->executeForDatePair($kpi, $compDates[0], $compDates[1]);
                }
            } else {
                $value = $this->execute($kpi);
            }

            $kpi->update([
                'cached_value'            => $value,
                'cached_comparison_value' => $comparisonValue,
                'cached_at'               => now(),
                'status'                  => 'active',
                'last_error'              => null,
            ]);

            DatawarehouseKpiSnapshot::create([
                'kpi_id'        => $kpi->id,
                'value'         => $value,
                'calculated_at' => now(),
                'trigger'       => $trigger,
            ]);

            DatawarehouseKpiSnapshot::prune($kpi->id);

            return $value;
        } catch (\Throwable $e) {
            $kpi->update([
                'status'     => 'error',
                'last_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the KPI value, using cache if valid.
     */
    public function getValue(DatawarehouseKpi $kpi): ?float
    {
        if ($kpi->isCacheValid()) {
            return $kpi->cached_value !== null ? (float) $kpi->cached_value : null;
        }

        return $this->executeAndCache($kpi);
    }

    /**
     * Resolve a date range key to [start, end] Carbon instances.
     */
    public static function resolveDateRange(string $range): ?array
    {
        $now = Carbon::now();

        return match ($range) {
            'current_year'     => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'current_quarter'  => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'current_month'    => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'current_week'     => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_7_days'      => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'last_30_days'     => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'last_90_days'     => [$now->copy()->subDays(89)->startOfDay(), $now->copy()->endOfDay()],
            'last_12_months'   => [$now->copy()->subMonths(12)->startOfMonth(), $now->copy()->endOfMonth()],
            'previous_month'   => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'previous_quarter' => [$now->copy()->subQuarter()->startOfQuarter(), $now->copy()->subQuarter()->endOfQuarter()],
            'previous_year'    => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'year_to_date'     => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            default            => null,
        };
    }

    // ----------------------------------------------------------------
    // Internal: shared query construction
    // ----------------------------------------------------------------

    /**
     * Build the base query with JOINs, calendar filters, snapshot filters,
     * and data filters applied. Returns [Builder, resolvedStreams, baseAlias].
     *
     * Used by execute(), executeForRange(), and executeForDatePair() to
     * avoid duplicating the query-setup logic.
     */
    private function buildBaseQuery(
        DatawarehouseKpi $kpi,
        bool $strict = true
    ): array {
        $definition = $kpi->definition;
        $teamId = $kpi->team_id;
        $streams = $definition['streams'] ?? [];
        $filters = $definition['filters'] ?? [];
        $snapshotMode = $definition['snapshot_mode'] ?? 'latest';

        $resolvedStreams = $this->resolveStreams($streams, $teamId);

        $baseAlias = $streams[0]['alias'];
        $baseStream = $resolvedStreams[$baseAlias];
        $query = DB::table($baseStream->getDynamicTableName() . ' as ' . $baseAlias);

        // Apply JOINs for chained streams
        for ($i = 1; $i < count($streams); $i++) {
            $streamDef = $streams[$i];
            $alias = $streamDef['alias'];
            $joinDef = $streamDef['join'] ?? null;

            if (!$joinDef || !isset($joinDef['relation_id'])) {
                if ($strict) {
                    throw new \InvalidArgumentException("Stream '{$alias}' missing join definition.");
                }
                continue;
            }

            $relation = DatawarehouseStreamRelation::where('id', $joinDef['relation_id'])
                ->where('team_id', $teamId)
                ->first();

            if (!$relation) {
                if ($strict) {
                    throw new \InvalidArgumentException("Relation {$joinDef['relation_id']} not found.");
                }
                continue;
            }

            $joinedStream = $resolvedStreams[$alias];
            $joinTable = $joinedStream->getDynamicTableName() . ' as ' . $alias;
            $joinType = strtoupper($joinDef['type'] ?? 'INNER');

            // Determine join columns based on relation direction
            if ($relation->source_stream_id === $joinedStream->id) {
                $leftColumn = $this->resolveJoinAlias($relation->target_stream_id, $streams) . '.' . $relation->target_column;
                $rightColumn = $alias . '.' . $relation->source_column;
            } else {
                $leftColumn = $this->resolveJoinAlias($relation->source_stream_id, $streams) . '.' . $relation->source_column;
                $rightColumn = $alias . '.' . $relation->target_column;
            }

            if ($joinType === 'LEFT') {
                $query->leftJoin($joinTable, $leftColumn, '=', $rightColumn);
            } else {
                $query->join($joinTable, $leftColumn, '=', $rightColumn);
            }
        }

        // Apply calendar filters (JOIN on dw_dim_date)
        $calendarFilters = $definition['calendar_filters'] ?? null;
        if ($calendarFilters) {
            $this->applyCalendarFilters($query, $calendarFilters);
        }

        // Apply snapshot filter for snapshot-strategy streams
        if ($snapshotMode === 'latest') {
            foreach ($resolvedStreams as $alias => $stream) {
                if ($stream->isSnapshotStrategy()) {
                    $tableName = $stream->getDynamicTableName();
                    $subQuery = DB::table($tableName)->selectRaw('MAX(_snapshot_at)');
                    $query->where($alias . '._snapshot_at', '=', $subQuery);
                }
            }
        }

        // Apply currency filter for SCD2-strategy streams: only count rows
        // that represent the entity's *current* version. Closed historical
        // versions stay queryable for point-in-time analysis elsewhere.
        foreach ($resolvedStreams as $alias => $stream) {
            if ($stream->isScd2Strategy()) {
                $query->where($alias . '._is_current', true);
            }
        }

        // Apply filters
        foreach ($filters as $filter) {
            $this->applyFilter($query, $filter, $resolvedStreams, $teamId);
        }

        return [$query, $resolvedStreams, $baseAlias];
    }

    /**
     * Execute a KPI query for an explicit [start, end] date pair.
     */
    private function executeForDatePair(DatawarehouseKpi $kpi, Carbon $start, Carbon $end): ?float
    {
        $definition = $kpi->definition;

        if (empty($definition['streams'] ?? []) || empty($this->extractAggregationTerms($definition))) {
            return null;
        }

        // Strip date_range so buildBaseQuery doesn't apply it — we use our own pair.
        $originalDef = $kpi->definition;
        $modifiedDef = $originalDef;
        unset($modifiedDef['calendar_filters']['date_range']);
        $kpi->definition = $modifiedDef;

        try {
            [$query, $resolvedStreams, $baseAlias] = $this->buildBaseQuery($kpi, strict: false);

            // Apply explicit date range
            $this->applyExplicitDateRange($query, $modifiedDef, $start, $end);

            try {
                $selectRaw = $this->buildAggregationSelect($modifiedDef, $resolvedStreams, $baseAlias, $kpi->team_id, strict: false);
            } catch (\InvalidArgumentException) {
                return null;
            }

            $result = $query->selectRaw($selectRaw)->first();

            return $result ? (float) $result->kpi_result : null;
        } finally {
            $kpi->definition = $originalDef;
        }
    }

    /**
     * Apply an explicit date range filter using the calendar_filters config.
     */
    private function applyExplicitDateRange(Builder $query, array $definition, Carbon $start, Carbon $end): void
    {
        $cal = $definition['calendar_filters'] ?? [];
        $dateColumn = $cal['date_column'] ?? null;
        $dateAlias = $cal['date_stream_alias'] ?? 's0';

        if (!$dateColumn) {
            return;
        }

        $this->validateColumnName($dateColumn);
        $this->validateAlias($dateAlias);
        $query->whereBetween(
            DB::raw("DATE({$dateAlias}.{$dateColumn})"),
            [$start->toDateString(), $end->toDateString()]
        );
    }

    // ----------------------------------------------------------------
    // Internal: aggregation SELECT builder
    // ----------------------------------------------------------------

    /**
     * Build the SELECT clause that aggregates one or more terms into a
     * single kpi_result. Supports the new `aggregations` shape (an ordered
     * list of {function, column, stream_alias, operator}) and falls back
     * to the legacy single `aggregation` shape.
     *
     * Division terms are wrapped with NULLIF(..., 0) to prevent
     * division-by-zero errors.
     *
     * Set $strict=false to skip the DB-backed column-existence check
     * (used in the per-range executors where the same KPI is run many
     * times in a row).
     */
    private function buildAggregationSelect(
        array $definition,
        array $resolvedStreams,
        string $baseAlias,
        int $teamId,
        bool $strict = true
    ): string {
        $terms = $this->extractAggregationTerms($definition);
        if (empty($terms)) {
            throw new \InvalidArgumentException('KPI definition incomplete: at least one aggregation term required.');
        }

        $expression = '';
        foreach ($terms as $i => $term) {
            $aggFunction = strtoupper($term['function'] ?? 'SUM');
            if (!in_array($aggFunction, self::ALLOWED_AGGREGATIONS, true)) {
                throw new \InvalidArgumentException("Invalid aggregation function: {$aggFunction}");
            }

            $aggAlias = $term['stream_alias'] ?? $baseAlias;
            $aggColumn = $term['column'] ?? '*';

            if ($aggColumn === '*') {
                if ($aggFunction !== 'COUNT') {
                    throw new \InvalidArgumentException('Wildcard (*) only allowed with COUNT.');
                }
                $piece = 'COUNT(*)';
            } else {
                $this->validateColumnName($aggColumn);
                $this->validateAlias($aggAlias);
                if ($strict) {
                    $this->validateColumnExists($aggColumn, $resolvedStreams[$aggAlias] ?? null, $teamId);
                }
                $piece = "{$aggFunction}({$aggAlias}.{$aggColumn})";
            }

            if ($i === 0) {
                $expression = $piece;
                continue;
            }

            $operator = (string) ($term['operator'] ?? '+');
            if (!in_array($operator, self::ALLOWED_TERM_OPERATORS, true)) {
                throw new \InvalidArgumentException("Invalid term operator: {$operator}");
            }

            // Wrap divisor with NULLIF to prevent division-by-zero errors.
            // MySQL returns NULL instead of throwing an error.
            if ($operator === '/') {
                $piece = "NULLIF({$piece}, 0)";
            }

            $expression .= " {$operator} {$piece}";
        }

        return "({$expression}) as kpi_result";
    }

    /**
     * Normalize the aggregation portion of a KPI definition into a list
     * of terms. Accepts both the new `aggregations` array shape and the
     * legacy single `aggregation` object so older KPIs keep working.
     */
    private function extractAggregationTerms(array $definition): array
    {
        $terms = $definition['aggregations'] ?? null;

        if (!is_array($terms) || empty($terms)) {
            $single = $definition['aggregation'] ?? null;
            if (is_array($single) && !empty($single)) {
                return [$single];
            }
            return [];
        }

        return array_values($terms);
    }

    // ----------------------------------------------------------------
    // Internal: filters & validation
    // ----------------------------------------------------------------

    /**
     * Apply a date range filter on a date column.
     */
    private function applyDateRangeFilter($query, string $alias, string $column, string $range): void
    {
        $dates = self::resolveDateRange($range);
        if (!$dates) {
            return;
        }

        [$start, $end] = $dates;

        $query->whereBetween(
            DB::raw("DATE({$alias}.{$column})"),
            [$start->toDateString(), $end->toDateString()]
        );
    }

    /**
     * Resolve stream definitions to DatawarehouseStream models.
     */
    private function resolveStreams(array $streams, int $teamId): array
    {
        $resolved = [];

        foreach ($streams as $streamDef) {
            $alias = $streamDef['alias'];
            $this->validateAlias($alias);

            $stream = DatawarehouseStream::forTeam($teamId)
                ->where('id', $streamDef['stream_id'])
                ->where('table_created', true)
                ->first();

            if (!$stream) {
                throw new \InvalidArgumentException("Stream {$streamDef['stream_id']} not found or table not created.");
            }

            $resolved[$alias] = $stream;
        }

        return $resolved;
    }

    /**
     * Find the alias for a given stream_id in the stream definitions.
     */
    private function resolveJoinAlias(int $streamId, array $streams): string
    {
        foreach ($streams as $streamDef) {
            if ($streamDef['stream_id'] === $streamId) {
                return $streamDef['alias'];
            }
        }

        throw new \InvalidArgumentException("No alias found for stream {$streamId} in definition.");
    }

    /**
     * Apply calendar dimension filters by joining dw_dim_date.
     */
    private function applyCalendarFilters($query, array $calendarFilters): void
    {
        $dateColumn = $calendarFilters['date_column'] ?? null;
        $dateAlias = $calendarFilters['date_stream_alias'] ?? 's0';
        $dateRange = $calendarFilters['date_range'] ?? null;
        $conditions = $calendarFilters['conditions'] ?? [];

        if (!$dateColumn) {
            return;
        }

        // Apply dynamic date range filter directly (no calendar JOIN needed)
        if ($dateRange) {
            $this->validateColumnName($dateColumn);
            $this->validateAlias($dateAlias);
            $this->applyDateRangeFilter($query, $dateAlias, $dateColumn, $dateRange);
        }

        if (empty($conditions)) {
            return;
        }

        $this->validateColumnName($dateColumn);
        $this->validateAlias($dateAlias);

        $calAlias = self::CALENDAR_ALIAS;
        $calTable = self::CALENDAR_TABLE . ' as ' . $calAlias;

        // DATE cast to support both date and datetime source columns
        $joinLeft = DB::raw("DATE({$dateAlias}.{$dateColumn})");

        $query->leftJoin($calTable, $joinLeft, '=', $calAlias . '.date_key');

        foreach ($conditions as $condition) {
            $column = $condition['column'] ?? '';
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? '';

            if (!in_array($column, self::CALENDAR_COLUMNS, true)) {
                throw new \InvalidArgumentException("Invalid calendar column: {$column}");
            }

            if (!in_array($operator, self::ALLOWED_OPERATORS, true)) {
                throw new \InvalidArgumentException("Invalid operator in calendar filter: {$operator}");
            }

            // Cast boolean-looking values
            if ($value === true || $value === 'true') {
                $value = 1;
            } elseif ($value === false || $value === 'false') {
                $value = 0;
            }

            $query->where("{$calAlias}.{$column}", $operator, $value);
        }
    }

    /**
     * Apply a single filter condition to the query.
     */
    private function applyFilter($query, array $filter, array $resolvedStreams, int $teamId): void
    {
        $alias = $filter['stream_alias'] ?? 's0';
        $column = $filter['column'] ?? '';
        $operator = $filter['operator'] ?? '=';
        $value = $filter['value'] ?? '';

        $this->validateAlias($alias);
        $this->validateColumnName($column);

        if (!in_array($operator, self::ALLOWED_OPERATORS, true)) {
            throw new \InvalidArgumentException("Invalid operator: {$operator}");
        }

        if (!isset($resolvedStreams[$alias])) {
            throw new \InvalidArgumentException("Unknown stream alias in filter: {$alias}");
        }

        $this->validateColumnExists($column, $resolvedStreams[$alias], $teamId);

        $query->where("{$alias}.{$column}", $operator, $value);
    }

    private function validateColumnName(string $column): void
    {
        if (!preg_match(self::COLUMN_REGEX, $column)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }
    }

    private function validateAlias(string $alias): void
    {
        if (!preg_match(self::ALIAS_REGEX, $alias)) {
            throw new \InvalidArgumentException("Invalid alias: {$alias}");
        }
    }

    private function validateColumnExists(string $column, ?DatawarehouseStream $stream, int $teamId): void
    {
        if (!$stream) {
            throw new \InvalidArgumentException("Stream not resolved for column validation.");
        }

        // Allow system columns
        if (in_array($column, ['id', '_snapshot_at', '_imported_at', '_valid_from', '_valid_to', '_is_deleted', 'created_at', 'updated_at'], true)) {
            return;
        }

        $exists = DatawarehouseStreamColumn::where('stream_id', $stream->id)
            ->where('column_name', $column)
            ->where('is_active', true)
            ->exists();

        if (!$exists) {
            throw new \InvalidArgumentException("Column '{$column}' not found on stream '{$stream->name}'.");
        }
    }
}
