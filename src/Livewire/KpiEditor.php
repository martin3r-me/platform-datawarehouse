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

    // Step 4: Meta
    public string $name = '';
    public string $icon = 'chart-bar';
    public string $variant = 'primary';
    public ?string $unit = null;
    public string $format = 'number';
    public int $decimals = 0;

    // Step 1: Streams
    public array $selectedStreams = []; // [{stream_id, alias, join?: {relation_id, type}}]

    // Step 2: Aggregation
    public string $aggFunction = 'SUM';
    public string $aggColumn = '';
    public string $aggStreamAlias = 's0';

    // Step 3: Filters
    public array $filters = []; // [{stream_alias, column, operator, value}]

    // Preview
    public ?string $previewValue = null;
    public ?string $previewError = null;

    public function mount(?DatawarehouseKpi $kpi = null): void
    {
        $user = Auth::user();

        if ($kpi && $kpi->exists) {
            abort_unless($kpi->team_id === $user->currentTeam->id, 403);

            $this->kpiId = $kpi->id;
            $this->name = $kpi->name;
            $this->icon = $kpi->icon;
            $this->variant = $kpi->variant;
            $this->unit = $kpi->unit;
            $this->format = $kpi->format;
            $this->decimals = $kpi->decimals;

            $definition = $kpi->definition ?? [];
            $this->selectedStreams = $definition['streams'] ?? [];
            $this->filters = $definition['filters'] ?? [];

            $agg = $definition['aggregation'] ?? [];
            $this->aggFunction = $agg['function'] ?? 'SUM';
            $this->aggColumn = $agg['column'] ?? '';
            $this->aggStreamAlias = $agg['stream_alias'] ?? 's0';
        }
    }

    // --- Step navigation ---

    public function nextStep(): void
    {
        if ($this->step === 1 && empty($this->selectedStreams)) {
            return;
        }
        if ($this->step === 2 && empty($this->aggColumn)) {
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
        if (empty($this->selectedStreams)) {
            return 1;
        }
        if (empty($this->aggColumn)) {
            return 2;
        }
        return 4;
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
        $this->aggColumn = '';
        $this->aggStreamAlias = 's0';
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

        if (!in_array($this->aggStreamAlias, $validAliases)) {
            $this->aggStreamAlias = 's0';
            $this->aggColumn = '';
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

    // --- Step 4: Preview ---

    public function preview(): void
    {
        $this->previewValue = null;
        $this->previewError = null;

        try {
            $kpi = $this->buildKpiModel();
            $builder = new KpiQueryBuilder();
            $value = $builder->execute($kpi);
            $this->previewValue = $this->formatValue($value);
        } catch (\Throwable $e) {
            $this->previewError = $e->getMessage();
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        $team = $user->currentTeam;

        $definition = [
            'streams'       => $this->selectedStreams,
            'aggregation'   => [
                'function'     => $this->aggFunction,
                'column'       => $this->aggColumn,
                'stream_alias' => $this->aggStreamAlias,
            ],
            'filters'       => array_values(array_filter($this->filters, fn($f) => !empty($f['column']))),
            'snapshot_mode' => 'latest',
        ];

        $data = [
            'name'       => $this->name,
            'icon'       => $this->icon,
            'variant'    => $this->variant,
            'unit'       => $this->unit ?: null,
            'format'     => $this->format,
            'decimals'   => $this->decimals,
            'definition' => $definition,
            'status'     => 'active',
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

        $kpi = new DatawarehouseKpi();
        $kpi->team_id = $team->id;
        $kpi->definition = [
            'streams'       => $this->selectedStreams,
            'aggregation'   => [
                'function'     => $this->aggFunction,
                'column'       => $this->aggColumn,
                'stream_alias' => $this->aggStreamAlias,
            ],
            'filters'       => array_values(array_filter($this->filters, fn($f) => !empty($f['column']))),
            'snapshot_mode' => 'latest',
        ];

        return $kpi;
    }

    private function formatValue(?float $value): string
    {
        if ($value === null) {
            return '-';
        }

        return number_format($value, $this->decimals, ',', '.');
    }

    public function render()
    {
        return view('datawarehouse::livewire.kpi-editor')
            ->layout('platform::layouts.app');
    }
}
