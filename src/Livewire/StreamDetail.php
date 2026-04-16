<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Platform\Datawarehouse\Jobs\PullStreamJob;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseStreamColumn;
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

        return view('datawarehouse::livewire.stream-detail', [
            'imports'          => $imports,
            'columns'          => $columns,
            'rowCount'         => $rowCount,
            'rows'             => $rows,
            'connection'       => $connection,
            'schemaMigrations' => $schemaMigrations,
        ])->layout('platform::layouts.app');
    }
}
