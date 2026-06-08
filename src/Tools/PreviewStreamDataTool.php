<?php

namespace Platform\Datawarehouse\Tools;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Tools\Concerns\ResolvesDwhTeam;

/**
 * Read-only preview of a stream's dynamic table. Lets an operator (or LLM)
 * inspect actual rows, filter them, restrict to the latest snapshot, or get
 * distinct value counts for a single column (group_by) — all without writing.
 *
 * Every column reference is validated against the stream's active columns
 * (plus a system-column whitelist) and every operator against a whitelist,
 * so no raw SQL fragment can reach the query.
 */
class PreviewStreamDataTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesDwhTeam;

    private const ALLOWED_OPERATORS = ['=', '!=', '<', '>', '<=', '>=', 'LIKE'];
    private const COLUMN_REGEX = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';
    private const SYSTEM_COLUMNS = [
        'id', '_snapshot_at', '_external_id', '_row_hash', '_synced_at',
        '_source_run_id', '_valid_from', '_valid_to', '_is_current',
        '_deleted_at', 'import_id', 'imported_at', 'created_at', 'updated_at',
    ];
    private const MAX_LIMIT = 200;

    public function getName(): string
    {
        return 'datawarehouse.stream.preview';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/streams/{id}/preview - Liest Beispielzeilen aus der dynamischen Tabelle eines Streams (read-only, nichts wird geschrieben). ERFORDERLICH: stream_id. Optional: limit (default 20, max 200), offset, columns[] (Spaltenauswahl), filters[{column, operator(=,!=,<,>,<=,>=,LIKE), value}], latest_snapshot_only (bei snapshot-Streams nur die neueste _snapshot_at), group_by (eine Spalte → liefert distinct Werte + count(*) statt Rohzeilen, ideal um z. B. die Werte der Spalte "typ" zu sehen). Spalten/Operatoren werden gegen Whitelists geprüft.';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id'   => ['type' => 'integer', 'description' => 'Optional: Team-ID. Default: aktuelles Team aus Kontext.'],
                'stream_id' => ['type' => 'integer', 'description' => 'ID des Streams (ERFORDERLICH). Nutze "datawarehouse.streams.GET" um IDs zu finden.'],
                'limit'     => ['type' => 'integer', 'description' => 'Optional: Anzahl Zeilen (default 20, max 200).'],
                'offset'    => ['type' => 'integer', 'description' => 'Optional: Offset für Paging (default 0).'],
                'columns'   => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: Spaltenauswahl (column_name). Default: alle Spalten.'],
                'filters'   => [
                    'type' => 'array',
                    'description' => 'Optional: Filterbedingungen. Jede: {column, operator, value}.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'column'   => ['type' => 'string'],
                            'operator' => ['type' => 'string', 'enum' => self::ALLOWED_OPERATORS],
                            'value'    => ['description' => 'Vergleichswert. Bei LIKE inkl. %-Wildcards.'],
                        ],
                        'required' => ['column', 'value'],
                    ],
                ],
                'latest_snapshot_only' => ['type' => 'boolean', 'description' => 'Optional: Bei snapshot-Streams nur Zeilen des neuesten _snapshot_at (wie snapshot_mode=latest). Default false.'],
                'group_by' => ['type' => 'string', 'description' => 'Optional: Spalte. Liefert dann distinct Werte dieser Spalte + count(*) statt Rohzeilen.'],
            ],
            'required' => ['stream_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeam($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $teamId = (int) $resolved['team_id'];

            $found = $this->validateAndFindModel(
                $arguments, $context, 'stream_id', DatawarehouseStream::class,
                'NOT_FOUND', 'Stream nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }
            /** @var DatawarehouseStream $stream */
            $stream = $found['model'];

            if ((int) $stream->team_id !== $teamId) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diesen Stream.');
            }

            if (!$stream->table_created) {
                return ToolResult::error('VALIDATION_ERROR', 'Für diesen Stream wurde noch keine Tabelle erzeugt (table_created=false).');
            }

            $table = $stream->getDynamicTableName();
            if (!Schema::hasTable($table)) {
                return ToolResult::error('NOT_FOUND', "Tabelle '{$table}' existiert nicht.");
            }

            // Build the set of selectable/filterable column names.
            $stream->load('columns');
            $activeColumns = $stream->columns->where('is_active', true)->pluck('column_name')->all();
            $allowed = array_values(array_unique(array_merge($activeColumns, self::SYSTEM_COLUMNS)));
            // Only keep columns that physically exist on the table.
            $tableColumns = Schema::getColumnListing($table);
            $allowed = array_values(array_intersect($allowed, $tableColumns));

            $query = DB::table($table);

            // Latest-snapshot restriction (mirror of KpiQueryBuilder's latest mode).
            $latestOnly = (bool) ($arguments['latest_snapshot_only'] ?? false);
            if ($latestOnly && $stream->isSnapshotStrategy() && in_array('_snapshot_at', $tableColumns, true)) {
                $query->where('_snapshot_at', '=', DB::table($table)->selectRaw('MAX(_snapshot_at)'));
            }

            // Filters
            foreach ($arguments['filters'] ?? [] as $i => $filter) {
                $column = $filter['column'] ?? '';
                $operator = strtoupper($filter['operator'] ?? '=');
                if (!array_key_exists('value', $filter)) {
                    return ToolResult::error('VALIDATION_ERROR', "filters[$i].value ist erforderlich.");
                }
                if ($err = $this->guardColumn($column, $allowed)) {
                    return ToolResult::error('VALIDATION_ERROR', "filters[$i]: $err");
                }
                if (!in_array($operator, self::ALLOWED_OPERATORS, true)) {
                    return ToolResult::error('VALIDATION_ERROR', "filters[$i].operator ungültig. Erlaubt: " . implode(', ', self::ALLOWED_OPERATORS) . '.');
                }
                $query->where($column, $operator, $filter['value']);
            }

            // group_by mode → distinct value counts for one column.
            if (!empty($arguments['group_by'])) {
                $groupColumn = $arguments['group_by'];
                if ($err = $this->guardColumn($groupColumn, $allowed)) {
                    return ToolResult::error('VALIDATION_ERROR', "group_by: $err");
                }
                $groups = $query
                    ->select($groupColumn . ' as value', DB::raw('COUNT(*) as count'))
                    ->groupBy($groupColumn)
                    ->orderByDesc('count')
                    ->limit(self::MAX_LIMIT)
                    ->get()
                    ->map(fn ($r) => ['value' => $r->value, 'count' => (int) $r->count])
                    ->all();

                return ToolResult::success([
                    'stream_id' => $stream->id,
                    'table'     => $table,
                    'group_by'  => $groupColumn,
                    'groups'    => $groups,
                    'team_id'   => $stream->team_id,
                ]);
            }

            // Column selection
            $select = ['*'];
            if (!empty($arguments['columns']) && is_array($arguments['columns'])) {
                $select = [];
                foreach ($arguments['columns'] as $i => $col) {
                    if ($err = $this->guardColumn($col, $allowed)) {
                        return ToolResult::error('VALIDATION_ERROR', "columns[$i]: $err");
                    }
                    $select[] = $col;
                }
            }

            $limit = (int) ($arguments['limit'] ?? 20);
            $limit = max(1, min($limit, self::MAX_LIMIT));
            $offset = max(0, (int) ($arguments['offset'] ?? 0));

            $total = (clone $query)->count();
            $rows = $query
                ->select($select)
                ->orderBy('id')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();

            return ToolResult::success([
                'stream_id'      => $stream->id,
                'table'          => $table,
                'sync_strategy'  => $stream->sync_strategy,
                'latest_snapshot_only' => $latestOnly,
                'total_matching' => $total,
                'returned'       => count($rows),
                'limit'          => $limit,
                'offset'         => $offset,
                'rows'           => $rows,
                'team_id'        => $stream->team_id,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Lesen der Stream-Daten: ' . $e->getMessage());
        }
    }

    /**
     * Validate a column name: syntactically safe and in the allowed set.
     * Returns an error string or null when valid.
     */
    private function guardColumn(string $column, array $allowed): ?string
    {
        if (!preg_match(self::COLUMN_REGEX, $column)) {
            return "Spalte '{$column}' enthält ungültige Zeichen.";
        }
        if (!in_array($column, $allowed, true)) {
            return "Spalte '{$column}' existiert nicht auf diesem Stream.";
        }
        return null;
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'streams', 'preview', 'data'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => true,
            'idempotent' => true,
        ];
    }
}
