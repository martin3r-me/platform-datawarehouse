<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseStreamColumn;
use Platform\Datawarehouse\Models\DatawarehouseStreamRelation;
use Platform\Datawarehouse\Services\KpiQueryBuilder;

class KpiEditor extends Component
{
    public ?int $kpiId = null;
    public int $step = 1;

    // Hierarchy / grouping
    public ?int $parentKpiId = null;
    public bool $isGroup = false;

    // Ampel / thresholds
    public ?string $targetValue = null;
    public ?int $targetKpiId = null;
    public string $targetDirection = 'higher_better';
    public ?int $greenPct = 100;
    public ?int $yellowPct = 80;

    // Step 4: Meta
    public string $name = '';
    public ?string $description = null;
    public string $icon = 'chart-bar';
    public string $variant = 'primary';
    public ?string $unit = null;
    public string $format = 'number';
    public int $decimals = 0;

    // Step 1: Streams
    public array $selectedStreams = []; // [{stream_id, alias, join?: {relation_id, type}}]

    // Step 2: Aggregation (one or more terms combined with +, -, *, /)
    // Each term: ['function' => 'SUM', 'column' => '...', 'stream_alias' => 's0', 'operator' => '+']
    // The first term's operator is ignored; subsequent terms use it to chain.
    public array $aggregations = [
        ['function' => 'SUM', 'column' => '', 'stream_alias' => 's0', 'operator' => '+'],
    ];

    // Step 3: Filters
    public array $filters = []; // [{stream_alias, column, operator, value}]

    // Step 3: Calendar Filters
    public bool $calendarEnabled = false;
    public string $calDateColumn = '';
    public string $calDateStreamAlias = 's0';
    public ?string $displayRange = 'current_month';
    public array $calendarConditions = []; // [{column, operator, value}]

    // Preview
    public ?string $previewValue = null;
    public ?string $previewError = null;

    public function mount(?DatawarehouseKpi $kpi = null): void
    {
        $user = Auth::user();

        if ($kpi && $kpi->exists) {
            abort_unless($kpi->team_id === $user->currentTeam->id, 403);

            $this->kpiId = $kpi->id;
            $this->parentKpiId = $kpi->parent_kpi_id;
            $this->isGroup = (bool) $kpi->is_group;
            $this->targetValue = $kpi->target_value !== null ? (string) (float) $kpi->target_value : null;
            $this->targetKpiId = $kpi->target_kpi_id;
            $this->targetDirection = $kpi->target_direction ?: 'higher_better';
            $this->greenPct = $kpi->green_pct;
            $this->yellowPct = $kpi->yellow_pct;
            $this->name = $kpi->name;
            $this->description = $kpi->description;
            $this->icon = $kpi->icon;
            $this->variant = $kpi->variant;
            $this->unit = $kpi->unit;
            $this->format = $kpi->format;
            $this->decimals = $kpi->decimals;

            $definition = $kpi->definition ?? [];
            $this->selectedStreams = $definition['streams'] ?? [];
            $this->filters = $definition['filters'] ?? [];

            // Load aggregations: prefer the new multi-term shape, fall back
            // to the legacy single `aggregation` so existing KPIs still
            // open in the editor.
            $rawTerms = $definition['aggregations'] ?? null;
            if (!is_array($rawTerms) || empty($rawTerms)) {
                $legacy = $definition['aggregation'] ?? null;
                $rawTerms = is_array($legacy) && !empty($legacy) ? [$legacy] : [];
            }

            if (!empty($rawTerms)) {
                $this->aggregations = array_values(array_map(fn ($t) => [
                    'function'     => $t['function'] ?? 'SUM',
                    'column'       => $t['column'] ?? '',
                    'stream_alias' => $t['stream_alias'] ?? 's0',
                    'operator'     => $t['operator'] ?? '+',
                ], $rawTerms));
            }

            $cal = $definition['calendar_filters'] ?? null;
            if ($cal) {
                $this->calendarEnabled = true;
                $this->calDateColumn = $cal['date_column'] ?? '';
                $this->calDateStreamAlias = $cal['date_stream_alias'] ?? 's0';
                $this->calendarConditions = $cal['conditions'] ?? [];
            }

            // Load display_range from model (fallback: old calendar_filters.date_range)
            $this->displayRange = $kpi->display_range ?? $cal['date_range'] ?? 'current_month';
        }
    }

    // --- Step navigation ---

    public function nextStep(): void
    {
        // A group is a pure folder — skip the data-source/aggregation steps
        // and jump straight to naming.
        if ($this->isGroup) {
            $this->step = 4;
            return;
        }
        if ($this->step === 1 && empty($this->selectedStreams)) {
            return;
        }
        if ($this->step === 2 && !$this->hasAtLeastOneCompleteTerm()) {
            return;
        }
        if ($this->step < 4) {
            $this->step++;
        }
    }

    public function prevStep(): void
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= 4 && $step <= $this->maxReachableStep()) {
            $this->step = $step;
        }
    }

    private function maxReachableStep(): int
    {
        if ($this->isGroup) {
            return 4;
        }
        if (empty($this->selectedStreams)) {
            return 1;
        }
        if (!$this->hasAtLeastOneCompleteTerm()) {
            return 2;
        }
        return 4;
    }

    private function hasAtLeastOneCompleteTerm(): bool
    {
        foreach ($this->aggregations as $term) {
            if (!empty($term['column'])) {
                return true;
            }
        }
        return false;
    }

    // --- Step 1: Stream management ---

    public function selectBaseStream(int $streamId): void
    {
        $team = Auth::user()->currentTeam;
        $stream = DatawarehouseStream::forTeam($team->id)
            ->where('id', $streamId)
            ->where('table_created', true)
            ->first();

        if (!$stream) {
            return;
        }

        $this->selectedStreams = [
            ['stream_id' => $stream->id, 'alias' => 's0'],
        ];

        // Reset dependent state
        $this->aggregations = [
            ['function' => 'SUM', 'column' => '', 'stream_alias' => 's0', 'operator' => '+'],
        ];
        $this->filters = [];
        $this->previewValue = null;
        $this->previewError = null;
    }

    public function addChainedStream(int $relationId): void
    {
        $team = Auth::user()->currentTeam;

        $relation = DatawarehouseStreamRelation::where('id', $relationId)
            ->where('team_id', $team->id)
            ->first();

        if (!$relation) {
            return;
        }

        // Determine which stream to add (the one not already selected)
        $selectedIds = collect($this->selectedStreams)->pluck('stream_id')->toArray();

        if (in_array($relation->target_stream_id, $selectedIds) && !in_array($relation->source_stream_id, $selectedIds)) {
            $newStreamId = $relation->source_stream_id;
        } elseif (in_array($relation->source_stream_id, $selectedIds) && !in_array($relation->target_stream_id, $selectedIds)) {
            $newStreamId = $relation->target_stream_id;
        } else {
            return; // Both already selected or neither connected
        }

        $newStream = DatawarehouseStream::forTeam($team->id)
            ->where('id', $newStreamId)
            ->where('table_created', true)
            ->first();

        if (!$newStream) {
            return;
        }

        $alias = 's' . count($this->selectedStreams);

        $this->selectedStreams[] = [
            'stream_id' => $newStream->id,
            'alias'     => $alias,
            'join'      => [
                'relation_id' => $relation->id,
                'type'        => 'INNER',
            ],
        ];
    }

    public function removeStream(int $index): void
    {
        if ($index === 0) {
            return; // Can't remove base stream
        }

        // Remove the stream and all streams after it (they may depend on this join)
        $this->selectedStreams = array_slice($this->selectedStreams, 0, $index);

        // Re-index aliases
        foreach ($this->selectedStreams as $i => &$s) {
            $s['alias'] = 's' . $i;
        }

        // Clean up references to removed streams
        $validAliases = collect($this->selectedStreams)->pluck('alias')->toArray();
        $this->filters = array_values(array_filter($this->filters, function ($f) use ($validAliases) {
            return in_array($f['stream_alias'] ?? 's0', $validAliases);
        }));

        // Drop any aggregation term that referenced a removed stream alias.
        $this->aggregations = array_values(array_filter(
            $this->aggregations,
            fn ($term) => in_array($term['stream_alias'] ?? 's0', $validAliases, true)
        ));

        if (empty($this->aggregations)) {
            $this->aggregations = [
                ['function' => 'SUM', 'column' => '', 'stream_alias' => 's0', 'operator' => '+'],
            ];
        }
    }

    // --- Step 2: Aggregation terms ---

    public function addAggregation(): void
    {
        $this->aggregations[] = [
            'function'     => 'SUM',
            'column'       => '',
            'stream_alias' => 's0',
            'operator'     => '+',
        ];
    }

    public function removeAggregation(int $index): void
    {
        if (count($this->aggregations) <= 1) {
            return; // Always keep at least one term.
        }
        unset($this->aggregations[$index]);
        $this->aggregations = array_values($this->aggregations);
    }

    /**
     * Set the column for an aggregation term. Accepts either a plain
     * column name (backwards-compat) or the encoded "alias:column" form
     * used by the updated UI to auto-bind the stream_alias.
     */
    public function setAggregationColumn(int $index, string $value): void
    {
        if (!isset($this->aggregations[$index])) {
            return;
        }

        // Wildcard (*) has no alias prefix
        if ($value === '*' || $value === '') {
            $this->aggregations[$index]['column'] = $value;
            return;
        }

        // Decode "alias:column" if present
        if (str_contains($value, ':')) {
            [$alias, $column] = explode(':', $value, 2);
            $this->aggregations[$index]['stream_alias'] = $alias;
            $this->aggregations[$index]['column'] = $column;
        } else {
            $this->aggregations[$index]['column'] = $value;
        }
    }

    public function setAggregationFunction(int $index, string $function): void
    {
        if (!isset($this->aggregations[$index])) {
            return;
        }
        $this->aggregations[$index]['function'] = $function;

        // COUNT supports the "*" wildcard; if a term switches away from
        // COUNT we must clear "*" since other functions need a real column.
        if ($function !== 'COUNT' && ($this->aggregations[$index]['column'] ?? '') === '*') {
            $this->aggregations[$index]['column'] = '';
        }
    }

    // --- Step 3: Filters ---

    public function addFilter(): void
    {
        $this->filters[] = [
            'stream_alias' => 's0',
            'column'       => '',
            'operator'     => '=',
            'value'        => '',
        ];
    }

    public function removeFilter(int $index): void
    {
        unset($this->filters[$index]);
        $this->filters = array_values($this->filters);
    }

    // --- Step 3: Calendar Filters ---

    public function toggleCalendar(): void
    {
        $this->calendarEnabled = !$this->calendarEnabled;

        if (!$this->calendarEnabled) {
            $this->calDateColumn = '';
            $this->calDateStreamAlias = 's0';
            $this->displayRange = 'current_month';
            $this->calendarConditions = [];
        }
    }

    public function addCalendarCondition(): void
    {
        $this->calendarConditions[] = [
            'column'   => 'is_weekend',
            'operator' => '=',
            'value'    => '',
        ];
    }

    public function removeCalendarCondition(int $index): void
    {
        unset($this->calendarConditions[$index]);
        $this->calendarConditions = array_values($this->calendarConditions);
    }

    #[Computed]
    public function dateColumns(): array
    {
        $result = [];

        foreach ($this->selectedStreams as $streamDef) {
            $stream = DatawarehouseStream::find($streamDef['stream_id']);
            if (!$stream) {
                continue;
            }

            $columns = DatawarehouseStreamColumn::where('stream_id', $stream->id)
                ->where('is_active', true)
                ->whereIn('data_type', ['date', 'datetime'])
                ->orderBy('position')
                ->get();

            if ($columns->isNotEmpty()) {
                $result[$streamDef['alias']] = [
                    'stream_name' => $stream->name,
                    'columns'     => $columns,
                ];
            }
        }

        return $result;
    }

    // --- Step 4: Preview ---

    public function preview(): void
    {
        $this->previewValue = null;
        $this->previewError = null;

        try {
            $kpi = $this->buildKpiModel();
            $builder = new KpiQueryBuilder();
            $value = ($kpi->hasDateColumn() && $kpi->display_range)
                ? $builder->executeForRange($kpi, $kpi->display_range)
                : $builder->execute($kpi);
            $this->previewValue = $this->formatValue($value);
        } catch (\Throwable $e) {
            $this->previewError = $e->getMessage();
        }
    }

    public function save(): void
    {
        $this->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        $team = $user->currentTeam;

        if ($this->isGroup) {
            // A folder/group has no aggregation and no own value.
            $definition = [];
        } else {
            $aggregations = $this->normalizedAggregationTerms();

            $definition = [
                'streams'       => $this->selectedStreams,
                'aggregations'  => $aggregations,
                // Mirror the first term to the legacy `aggregation` key so an
                // older deployment that still reads it keeps working.
                'aggregation'   => $aggregations[0] ?? null,
                'filters'       => array_values(array_filter($this->filters, fn($f) => !empty($f['column']))),
                'snapshot_mode' => 'latest',
            ];

            if ($this->calendarEnabled && $this->calDateColumn) {
                $definition['calendar_filters'] = [
                    'date_column'       => $this->calDateColumn,
                    'date_stream_alias' => $this->calDateStreamAlias,
                    'conditions'        => array_values(array_filter(
                        $this->calendarConditions,
                        fn($c) => !empty($c['column']) && $c['value'] !== '',
                    )),
                ];
            }
        }

        $data = [
            'name'          => $this->name,
            'description'   => $this->description ?: null,
            'icon'          => $this->icon,
            'variant'       => $this->variant,
            'unit'          => $this->unit ?: null,
            'format'        => $this->format,
            'decimals'      => $this->decimals,
            'parent_kpi_id' => $this->parentKpiId ?: null,
            'is_group'      => $this->isGroup,
            'target_value'     => ($this->targetValue !== null && $this->targetValue !== '') ? (float) str_replace(',', '.', $this->targetValue) : null,
            'target_kpi_id'    => $this->targetKpiId ?: null,
            'target_direction' => $this->targetDirection ?: 'higher_better',
            'green_pct'        => $this->greenPct !== null && $this->greenPct !== '' ? (int) $this->greenPct : null,
            'yellow_pct'       => $this->yellowPct !== null && $this->yellowPct !== '' ? (int) $this->yellowPct : null,
            'definition'    => $definition,
            'display_range' => (!$this->isGroup && $this->calendarEnabled && $this->calDateColumn) ? $this->displayRange : null,
            'status'        => 'active',
        ];

        if ($this->kpiId) {
            $kpi = DatawarehouseKpi::forTeam($team->id)->findOrFail($this->kpiId);
            $kpi->update($data);
        } else {
            $data['team_id'] = $team->id;
            $data['user_id'] = $user->id;
            $data['position'] = DatawarehouseKpi::forTeam($team->id)->max('position') + 1;
            $kpi = DatawarehouseKpi::create($data);
        }

        // Try to cache the initial value
        try {
            $builder = new KpiQueryBuilder();
            $builder->executeAndCache($kpi);
        } catch (\Throwable) {
            // Non-critical — value will be computed on next dashboard load
        }

        $this->redirect(route('datawarehouse.dashboard'));
    }

    // --- Computed Properties ---

    #[Computed]
    public function availableParents(): \Illuminate\Support\Collection
    {
        $team = Auth::user()->currentTeam;

        $query = DatawarehouseKpi::forTeam($team->id)->orderBy('name');

        // Exclude self and own descendants to prevent hierarchy cycles.
        if ($this->kpiId) {
            $exclude = $this->descendantIds($this->kpiId);
            $exclude[] = $this->kpiId;
            $query->whereNotIn('id', $exclude);
        }

        return $query->get(['id', 'name', 'is_group']);
    }

    private function descendantIds(int $id): array
    {
        $ids = [];
        foreach (DatawarehouseKpi::where('parent_kpi_id', $id)->pluck('id') as $childId) {
            $ids[] = (int) $childId;
            $ids = array_merge($ids, $this->descendantIds((int) $childId));
        }
        return $ids;
    }

    #[Computed]
    public function availableBaseStreams(): \Illuminate\Support\Collection
    {
        $team = Auth::user()->currentTeam;

        return DatawarehouseStream::forTeam($team->id)
            ->where('table_created', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    #[Computed]
    public function chainableRelations(): \Illuminate\Support\Collection
    {
        if (empty($this->selectedStreams)) {
            return collect();
        }

        $team = Auth::user()->currentTeam;
        $selectedIds = collect($this->selectedStreams)->pluck('stream_id')->toArray();

        return DatawarehouseStreamRelation::where('team_id', $team->id)
            ->where(function ($q) use ($selectedIds) {
                $q->whereIn('source_stream_id', $selectedIds)
                  ->orWhereIn('target_stream_id', $selectedIds);
            })
            ->where(function ($q) use ($selectedIds) {
                // At least one side must NOT be already selected (so we can add it)
                $q->whereNotIn('source_stream_id', $selectedIds)
                  ->orWhereNotIn('target_stream_id', $selectedIds);
            })
            ->with(['sourceStream:id,name', 'targetStream:id,name'])
            ->get()
            ->filter(function ($relation) use ($selectedIds) {
                // Ensure exactly one side is selected and the other is not
                $sourceSelected = in_array($relation->source_stream_id, $selectedIds);
                $targetSelected = in_array($relation->target_stream_id, $selectedIds);
                return $sourceSelected !== $targetSelected;
            });
    }

    #[Computed]
    public function availableColumns(): array
    {
        $grouped = [];

        foreach ($this->selectedStreams as $streamDef) {
            $stream = DatawarehouseStream::find($streamDef['stream_id']);
            if (!$stream) {
                continue;
            }

            $columns = DatawarehouseStreamColumn::where('stream_id', $stream->id)
                ->where('is_active', true)
                ->orderBy('position')
                ->get();

            $grouped[$streamDef['alias']] = [
                'stream_name' => $stream->name,
                'columns'     => $columns,
            ];
        }

        return $grouped;
    }

    #[Computed]
    public function selectedStreamModels(): array
    {
        $models = [];
        foreach ($this->selectedStreams as $streamDef) {
            $stream = DatawarehouseStream::find($streamDef['stream_id']);
            if ($stream) {
                $models[$streamDef['alias']] = $stream;
            }
        }
        return $models;
    }

    // --- Helpers ---

    private function buildKpiModel(): DatawarehouseKpi
    {
        $team = Auth::user()->currentTeam;

        $aggregations = $this->normalizedAggregationTerms();

        $definition = [
            'streams'       => $this->selectedStreams,
            'aggregations'  => $aggregations,
            'aggregation'   => $aggregations[0] ?? null,
            'filters'       => array_values(array_filter($this->filters, fn($f) => !empty($f['column']))),
            'snapshot_mode' => 'latest',
        ];

        if ($this->calendarEnabled && $this->calDateColumn) {
            $calFilters = [
                'date_column'        => $this->calDateColumn,
                'date_stream_alias'  => $this->calDateStreamAlias,
                'conditions'         => array_values(array_filter(
                    $this->calendarConditions,
                    fn($c) => !empty($c['column']) && $c['value'] !== '',
                )),
            ];

            $definition['calendar_filters'] = $calFilters;
        }

        $kpi = new DatawarehouseKpi();
        $kpi->team_id = $team->id;
        $kpi->is_group = $this->isGroup;
        $kpi->definition = $definition;
        $kpi->display_range = ($this->calendarEnabled && $this->calDateColumn) ? $this->displayRange : null;

        return $kpi;
    }

    private function formatValue(?float $value): string
    {
        if ($value === null) {
            return '-';
        }

        return number_format($value, $this->decimals, ',', '.');
    }

    /**
     * Drop empty terms and snap operators to a valid set before persisting.
     * The first term's operator is irrelevant for SQL but normalized to '+'
     * so the stored shape is consistent.
     */
    private function normalizedAggregationTerms(): array
    {
        $allowedOps = ['+', '-', '*', '/'];
        $terms = [];

        foreach ($this->aggregations as $term) {
            if (empty($term['column'])) {
                continue;
            }

            $operator = $term['operator'] ?? '+';
            if (!in_array($operator, $allowedOps, true)) {
                $operator = '+';
            }

            $terms[] = [
                'function'     => $term['function'] ?? 'SUM',
                'column'       => $term['column'],
                'stream_alias' => $term['stream_alias'] ?? 's0',
                'operator'     => $operator,
            ];
        }

        // Force the first surviving term to use '+' so the persisted shape
        // doesn't carry stale leading operators that the SQL builder ignores.
        if (!empty($terms)) {
            $terms[0]['operator'] = '+';
        }

        return $terms;
    }

    public function render()
    {
        return view('datawarehouse::livewire.kpi-editor')
            ->layout('platform::layouts.app');
    }
}
