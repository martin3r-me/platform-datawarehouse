<?php

namespace Platform\Datawarehouse\Services;

use Illuminate\Support\Facades\DB;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseStreamColumn;
use Platform\Datawarehouse\Models\DatawarehouseStreamRelation;

class KpiQueryBuilder
{
    private const ALLOWED_AGGREGATIONS = ['SUM', 'COUNT', 'AVG', 'MIN', 'MAX'];
    private const ALLOWED_OPERATORS = ['=', '!=', '<', '>', '<=', '>=', 'LIKE'];
    private const COLUMN_REGEX = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';
    private const ALIAS_REGEX = '/^s\d+$/';

    private const CALENDAR_ALIAS = '_cal';
    private const CALENDAR_TABLE = 'dw_dim_date';
    private const CALENDAR_COLUMNS = [
        'weekday', 'weekday_num', 'is_weekend', 'kw', 'month', 'quarter', 'year',
    ];

    /**
     * Execute a KPI query and return the result.
     */
    public function execute(DatawarehouseKpi $kpi): ?float
    {
        $definition = $kpi->definition;
        $teamId = $kpi->team_id;

        $streams = $definition['streams'] ?? [];
        $aggregation = $definition['aggregation'] ?? null;
        $filters = $definition['filters'] ?? [];
        $snapshotMode = $definition['snapshot_mode'] ?? 'latest';

        if (empty($streams) || !$aggregation) {
            throw new \InvalidArgumentException('KPI definition incomplete: streams and aggregation required.');
        }

        // Resolve and validate all streams
        $resolvedStreams = $this->resolveStreams($streams, $teamId);

        // Build the base query
        $baseAlias = $streams[0]['alias'];
        $baseStream = $resolvedStreams[$baseAlias];
        $query = DB::table($baseStream->getDynamicTableName() . ' as ' . $baseAlias);

        // Apply JOINs for chained streams
        for ($i = 1; $i < count($streams); $i++) {
            $streamDef = $streams[$i];
            $alias = $streamDef['alias'];
            $joinDef = $streamDef['join'] ?? null;

            if (!$joinDef || !isset($joinDef['relation_id'])) {
                throw new \InvalidArgumentException("Stream '{$alias}' missing join definition.");
            }

            $relation = DatawarehouseStreamRelation::where('id', $joinDef['relation_id'])
                ->where('team_id', $teamId)
                ->first();

            if (!$relation) {
                throw new \InvalidArgumentException("Relation {$joinDef['relation_id']} not found.");
            }

            $joinedStream = $resolvedStreams[$alias];
            $joinTable = $joinedStream->getDynamicTableName() . ' as ' . $alias;
            $joinType = strtoupper($joinDef['type'] ?? 'INNER');

            // Determine join columns based on relation direction
            if ($relation->source_stream_id === $joinedStream->id) {
                // The joined stream is the source side
                $leftColumn = $this->resolveJoinAlias($relation->target_stream_id, $streams) . '.' . $relation->target_column;
                $rightColumn = $alias . '.' . $relation->source_column;
            } else {
                // The joined stream is the target side
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

        // Apply filters
        foreach ($filters as $filter) {
            $this->applyFilter($query, $filter, $resolvedStreams, $teamId);
        }

        // Apply aggregation
        $aggFunction = strtoupper($aggregation['function'] ?? 'SUM');
        if (!in_array($aggFunction, self::ALLOWED_AGGREGATIONS, true)) {
            throw new \InvalidArgumentException("Invalid aggregation function: {$aggFunction}");
        }

        $aggAlias = $aggregation['stream_alias'] ?? $baseAlias;
        $aggColumn = $aggregation['column'] ?? '*';

        if ($aggColumn === '*') {
            if ($aggFunction !== 'COUNT') {
                throw new \InvalidArgumentException('Wildcard (*) only allowed with COUNT.');
            }
            $selectRaw = 'COUNT(*) as kpi_result';
        } else {
            $this->validateColumnName($aggColumn);
            $this->validateAlias($aggAlias);
            $this->validateColumnExists($aggColumn, $resolvedStreams[$aggAlias] ?? null, $teamId);
            $selectRaw = "{$aggFunction}({$aggAlias}.{$aggColumn}) as kpi_result";
        }

        $result = $query->selectRaw($selectRaw)->first();

        return $result ? (float) $result->kpi_result : null;
    }

    /**
     * Execute and update the cache on the KPI model.
     */
    public function executeAndCache(DatawarehouseKpi $kpi): ?float
    {
        try {
            $value = $this->execute($kpi);

            $kpi->update([
                'cached_value' => $value,
                'cached_at'    => now(),
                'status'       => 'active',
                'last_error'   => null,
            ]);

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
        $conditions = $calendarFilters['conditions'] ?? [];

        if (!$dateColumn || empty($conditions)) {
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
