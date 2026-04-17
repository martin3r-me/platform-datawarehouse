<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Platform\Datawarehouse\Jobs\PullStreamJob;
use Platform\Datawarehouse\Models\DatawarehouseKpi;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseStreamColumn;
use Platform\Datawarehouse\Models\DatawarehouseStreamRelation;
use Platform\Datawarehouse\Services\StreamSchemaService;

class StreamDetail extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public const DATA_TYPES = [
        'string'   => 'String (VARCHAR 255)',
        'text'     => 'Text (lang)',
        'integer'  => 'Integer',
        'decimal'  => 'Decimal',
        'boolean'  => 'Boolean',
        'date'     => 'Date',
        'datetime' => 'Datetime',
        'json'     => 'JSON',
    ];

    public DatawarehouseStream $stream;

    public string $activeTab = 'overview';

    public ?string $flash = null;

    // Column-Edit State
    public bool $showColumnEditModal = false;
    public ?int $editingColumnId = null;
    public string $editingColumnLabel = '';
    public string $editingType = 'string';
    public int $editingPrecision = 10;
    public int $editingScale = 2;
    public ?string $editingError = null;

    // Delete State
    public bool $showDeleteModal = false;
    public ?string $deleteError = null;
    public string $deleteConfirmName = '';

    // Relation State
    public bool $showRelationModal = false;
    public string $relSourceColumn = '';
    public ?int $relTargetStreamId = null;
    public string $relTargetColumn = '';
    public string $relLabel = '';
    public ?string $relError = null;

    public function mount(DatawarehouseStream $stream): void
    {
        $user = Auth::user();

        abort_unless($stream->team_id === $user->currentTeam->id, 403);

        // Onboarding streams belong on the onboarding page
        if ($stream->isOnboarding()) {
            $this->redirect(route('datawarehouse.stream.onboarding', $stream));
            return;
        }

        $this->stream = $stream;
    }

    public function setTab(string $tab): void
    {
        // Reset pagination when entering the data tab so users always start
        // on page 1 — otherwise a stale page number from a different stream
        // or previous visit can lead to an empty page.
        if ($tab === 'data' && $this->activeTab !== 'data') {
            $this->resetPage();
        }
        $this->activeTab = $tab;
    }

    public function pause(): void
    {
        if ($this->stream->status === 'active') {
            $this->stream->update(['status' => 'paused']);
        }
    }

    public function resume(): void
    {
        if ($this->stream->status === 'paused') {
            $this->stream->update(['status' => 'active']);
        }
    }

    public function archive(): void
    {
        $this->stream->update(['status' => 'archived']);
    }

    public function unarchive(): void
    {
        if ($this->stream->status === 'archived') {
            $this->stream->update(['status' => 'paused']);
        }
    }

    // --- Delete stream ---

    public function openDeleteModal(): void
    {
        $this->deleteError = null;
        $this->deleteConfirmName = '';
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->deleteError = null;
        $this->deleteConfirmName = '';
    }

    /**
     * Check if the stream can be deleted (no relations, no KPIs referencing it).
     */
    public function getDeleteBlockersProperty(): array
    {
        $blockers = [];

        // Check for relations where this stream is referenced by others
        $outgoing = $this->stream->outgoingRelations()->with('targetStream:id,name')->get();
        $incoming = $this->stream->incomingRelations()->with('sourceStream:id,name')->get();

        foreach ($outgoing as $rel) {
            $blockers[] = "Relation '{$rel->label}' → {$rel->targetStream->name}";
        }
        foreach ($incoming as $rel) {
            $blockers[] = "Relation '{$rel->label}' ← {$rel->sourceStream->name}";
        }

        // Check for KPIs that reference this stream
        $kpis = DatawarehouseKpi::forTeam($this->stream->team_id)
            ->whereNull('deleted_at')
            ->get();

        foreach ($kpis as $kpi) {
            $streams = $kpi->definition['streams'] ?? [];
            foreach ($streams as $s) {
                if (($s['stream_id'] ?? null) == $this->stream->id) {
                    $blockers[] = "Kennzahl '{$kpi->name}'";
                    break;
                }
            }
        }

        return $blockers;
    }

    public function deleteStream(StreamSchemaService $schema): void
    {
        $this->deleteError = null;

        // Verify name confirmation
        if (trim($this->deleteConfirmName) !== $this->stream->name) {
            $this->deleteError = 'Der eingegebene Name stimmt nicht überein.';
            return;
        }

        // Re-check blockers
        $blockers = $this->deleteBlockers;
        if (!empty($blockers)) {
            $this->deleteError = 'Löschen nicht möglich: ' . implode(', ', $blockers);
            return;
        }

        // Drop the dynamic table
        if ($this->stream->table_created) {
            $schema->dropTable($this->stream, Auth::id());
        }

        // Delete related records
        $this->stream->columns()->delete();
        $this->stream->imports()->delete();
        $this->stream->schemaMigrations()->delete();

        // Soft-delete the stream
        $this->stream->delete();

        $this->redirect(route('datawarehouse.dashboard'));
    }

    public function triggerPull(): void
    {
        if (!$this->stream->isPull()) {
            return;
        }
        if (!$this->stream->connection_id || !$this->stream->endpoint_key) {
            $this->flash = 'Pull-Konfiguration unvollständig (Verbindung/Endpoint fehlen).';
            return;
        }

        PullStreamJob::dispatch($this->stream->id, Auth::id());
        $this->flash = 'Pull-Run wurde in die Queue gestellt.';
    }

    public function editColumn(int $columnId): void
    {
        $column = $this->stream->columns()->where('id', $columnId)->first();
        if (!$column) {
            return;
        }

        $this->editingColumnId    = $column->id;
        $this->editingColumnLabel = (string) ($column->label ?? $column->column_name);
        $this->editingType        = $column->data_type;
        $this->editingPrecision   = (int) ($column->precision ?? 10);
        $this->editingScale       = (int) ($column->scale ?? 2);
        $this->editingError       = null;
        $this->showColumnEditModal = true;
    }

    public function cancelColumnEdit(): void
    {
        $this->showColumnEditModal = false;
        $this->editingColumnId     = null;
        $this->editingColumnLabel  = '';
        $this->editingType         = 'string';
        $this->editingPrecision    = 10;
        $this->editingScale        = 2;
        $this->editingError        = null;
    }

    public function saveColumnType(StreamSchemaService $schema): void
    {
        $this->editingError = null;

        if (!$this->editingColumnId) {
            return;
        }

        if (!array_key_exists($this->editingType, self::DATA_TYPES)) {
            $this->editingError = 'Ungültiger Datentyp.';
            return;
        }

        $column = $this->stream->columns()->where('id', $this->editingColumnId)->first();
        if (!$column) {
            $this->editingError = 'Spalte nicht gefunden.';
            return;
        }

        $oldDefinition = [
            'data_type' => $column->data_type,
            'precision' => $column->precision,
            'scale'     => $column->scale,
        ];

        // Decimal needs precision/scale; keep old values for other types so
        // the underlying column definition is predictable.
        $precision = $this->editingType === 'decimal' ? max(1, min(65, $this->editingPrecision)) : $column->precision;
        $scale     = $this->editingType === 'decimal' ? max(0, min(30, $this->editingScale)) : $column->scale;

        if ($this->editingType === 'decimal' && $scale > $precision) {
            $this->editingError = 'Scale darf nicht größer als Precision sein.';
            return;
        }

        $column->update([
            'data_type' => $this->editingType,
            'precision' => $precision,
            'scale'     => $scale,
        ]);

        // Only run the DDL migration when the target table actually exists.
        if ($this->stream->table_name && $this->stream->table_created) {
            try {
                $schema->modifyColumn($this->stream, $column, $oldDefinition, Auth::id());
            } catch (\Throwable $e) {
                // Revert the model change so state stays consistent with the table.
                $column->update($oldDefinition);
                $this->editingError = 'Migration fehlgeschlagen: ' . $e->getMessage();
                return;
            }
        }

        $this->flash = "Spalte '{$column->column_name}' wurde auf Typ '{$this->editingType}' geändert.";
        $this->cancelColumnEdit();
    }

    // --- Relation management ---

    public function openRelationModal(): void
    {
        $this->relSourceColumn   = '';
        $this->relTargetStreamId = null;
        $this->relTargetColumn   = '';
        $this->relLabel          = '';
        $this->relError          = null;
        $this->showRelationModal = true;
    }

    public function cancelRelation(): void
    {
        $this->showRelationModal = false;
    }

    /**
     * Return columns of the selected target stream (for the modal dropdown).
     */
    public function getTargetColumnsProperty(): array
    {
        if (!$this->relTargetStreamId) {
            return [];
        }
        $target = DatawarehouseStream::forTeam($this->stream->team_id)
            ->find($this->relTargetStreamId);
        if (!$target) {
            return [];
        }

        return $target->columns()
            ->where('is_active', true)
            ->orderBy('position')
            ->pluck('column_name')
            ->toArray();
    }

    /**
     * Pre-fill target column with the target stream's natural key.
     */
    public function updatedRelTargetStreamId(): void
    {
        $this->relTargetColumn = '';

        if (!$this->relTargetStreamId) {
            return;
        }
        $target = DatawarehouseStream::forTeam($this->stream->team_id)
            ->find($this->relTargetStreamId);
        if ($target && $target->natural_key) {
            $this->relTargetColumn = $target->natural_key;
        }
    }

    public function saveRelation(): void
    {
        $this->relError = null;

        if ($this->relSourceColumn === '') {
            $this->relError = 'Bitte eine Quell-Spalte wählen.';
            return;
        }
        if (!$this->relTargetStreamId) {
            $this->relError = 'Bitte einen Ziel-Datenstrom wählen.';
            return;
        }
        if ($this->relTargetColumn === '') {
            $this->relError = 'Bitte eine Ziel-Spalte wählen.';
            return;
        }
        if (trim($this->relLabel) === '') {
            $this->relError = 'Bitte einen Namen für die Relation vergeben.';
            return;
        }

        // Ensure target stream belongs to same team.
        $target = DatawarehouseStream::forTeam($this->stream->team_id)
            ->find($this->relTargetStreamId);
        if (!$target) {
            $this->relError = 'Ziel-Datenstrom nicht gefunden.';
            return;
        }

        // Prevent duplicates.
        $exists = DatawarehouseStreamRelation::where('source_stream_id', $this->stream->id)
            ->where('source_column', $this->relSourceColumn)
            ->where('target_stream_id', $target->id)
            ->exists();
        if ($exists) {
            $this->relError = "Für '{$this->relSourceColumn}' existiert bereits eine Relation zu diesem Ziel.";
            return;
        }

        DatawarehouseStreamRelation::create([
            'team_id'          => $this->stream->team_id,
            'source_stream_id' => $this->stream->id,
            'source_column'    => $this->relSourceColumn,
            'target_stream_id' => $target->id,
            'target_column'    => $this->relTargetColumn,
            'label'            => trim($this->relLabel),
        ]);

        $this->flash = "Relation '{$this->relLabel}' angelegt: {$this->relSourceColumn} → {$target->name}.{$this->relTargetColumn}";
        $this->showRelationModal = false;
    }

    public function deleteRelation(int $relationId): void
    {
        $relation = DatawarehouseStreamRelation::where('id', $relationId)
            ->where(function ($q) {
                $q->where('source_stream_id', $this->stream->id)
                  ->orWhere('target_stream_id', $this->stream->id);
            })
            ->first();

        if ($relation) {
            $label = $relation->label ?: $relation->source_column;
            $relation->delete();
            $this->flash = "Relation '{$label}' wurde gelöscht.";
        }
    }

    public function render()
    {
        $imports = $this->stream->imports()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $columns = $this->stream->columns()->orderBy('position')->get();

        $tableName = $this->stream->table_name;
        $rowCount  = null;
        $rows      = null;

        if ($tableName && Schema::hasTable($tableName)) {
            $rowCount = DB::table($tableName)->count();
            // Only build the paginator when the data tab is actually visible —
            // otherwise every render hits the table unnecessarily.
            if ($this->activeTab === 'data') {
                $rows = DB::table($tableName)
                    ->orderByDesc('id')
                    ->paginate(25);
            }
        }

        $connection = $this->stream->isPull() ? $this->stream->connection : null;

        $schemaMigrations = $this->stream->schemaMigrations()
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        // Relations: outgoing (this stream references others) + incoming
        // (other streams reference this one).
        $outgoingRelations = $this->stream->outgoingRelations()
            ->with('targetStream:id,name,slug')
            ->get();
        $incomingRelations = $this->stream->incomingRelations()
            ->with('sourceStream:id,name,slug')
            ->get();

        // Available target streams for the relation modal (same team, excl. self).
        $availableStreams = $this->activeTab === 'relations'
            ? DatawarehouseStream::forTeam($this->stream->team_id)
                ->where('id', '!=', $this->stream->id)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'natural_key'])
            : collect();

        return view('datawarehouse::livewire.stream-detail', [
            'imports'            => $imports,
            'columns'            => $columns,
            'rowCount'           => $rowCount,
            'rows'               => $rows,
            'connection'         => $connection,
            'schemaMigrations'   => $schemaMigrations,
            'outgoingRelations'  => $outgoingRelations,
            'incomingRelations'  => $incomingRelations,
            'availableStreams'    => $availableStreams,
        ])->layout('platform::layouts.app');
    }
}
