<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseStreamColumn;
use Platform\Datawarehouse\Models\DatawarehouseImport;
use Platform\Datawarehouse\Services\DataTypeDetector;
use Platform\Datawarehouse\Services\PullStreamService;
use Platform\Datawarehouse\Services\StreamSchemaService;
use Platform\Datawarehouse\Services\StreamImportService;

class StreamOnboarding extends Component
{
    public DatawarehouseStream $stream;

    public array $fields = [];
    public bool $activating = false;

    // Sample fetch (Pull-Streams only)
    public bool $fetchingSample = false;
    public ?string $sampleError = null;

    // Strategy configuration
    public string $syncStrategy = 'append';
    public ?string $naturalKeyField = null;
    public bool $changeDetection = true;
    public bool $softDelete = false;

    public function mount(DatawarehouseStream $stream): void
    {
        $user = Auth::user();

        // Ensure stream belongs to the current team
        abort_unless($stream->team_id === $user->currentTeam->id, 403);
        abort_unless($stream->isOnboarding(), 404);

        $this->stream = $stream;
        $this->syncStrategy = $stream->sync_strategy ?: 'append';
        $this->naturalKeyField = $stream->natural_key;
        $this->changeDetection = $stream->change_detection ?? true;
        $this->softDelete = $stream->soft_delete ?? false;
        $this->buildFieldsFromSample();
    }

    public function updatedSyncStrategy(): void
    {
        // Snapshot/append never need soft-delete; reset for clarity.
        if (!in_array($this->syncStrategy, ['current', 'scd2'], true)) {
            $this->softDelete = false;
            $this->naturalKeyField = null;
        }
    }

    public function buildFieldsFromSample(): void
    {
        $sample = $this->stream->sample_payload;

        if (!$sample) {
            $this->fields = [];
            return;
        }

        $detectedTypes = DataTypeDetector::detectFromPayload($sample);

        $this->fields = [];
        $position = 0;
        foreach ($detectedTypes as $key => $type) {
            $this->fields[] = [
                'source_key'  => $key,
                'label'       => $this->humanizeKey($key),
                'data_type'   => $type,
                'is_nullable' => true,
                'is_indexed'  => false,
                'transform'   => '',
                'selected'    => true,
                'position'    => $position++,
            ];
        }
    }

    public function refreshSample(): void
    {
        $this->stream->refresh();
        $this->buildFieldsFromSample();
    }

    /**
     * Manually trigger a one-shot pull against the provider to capture a
     * sample payload. Only meaningful for pull streams during onboarding.
     */
    public function fetchSample(): void
    {
        if (!$this->stream->isPull()) {
            return;
        }

        $this->sampleError = null;
        $this->fetchingSample = true;

        try {
            app(PullStreamService::class)->fetchSample($this->stream);
            $this->stream->refresh();
            $this->buildFieldsFromSample();
        } catch (\Throwable $e) {
            $this->sampleError = $e->getMessage();
        } finally {
            $this->fetchingSample = false;
        }
    }

    public function activate(): void
    {
        $this->activating = true;

        $user = Auth::user();
        $selectedFields = collect($this->fields)->where('selected', true);

        if ($selectedFields->isEmpty()) {
            $this->addError('fields', 'Mindestens ein Feld muss ausgewählt sein.');
            $this->activating = false;
            return;
        }

        // Validate strategy requirements
        if (in_array($this->syncStrategy, ['current', 'scd2'], true)) {
            if (!$this->naturalKeyField) {
                $this->addError('naturalKeyField', 'Für die gewählte Strategie muss ein natürlicher Schlüssel ausgewählt werden.');
                $this->activating = false;
                return;
            }
            $keyIsSelected = $selectedFields->contains(fn ($f) => $f['source_key'] === $this->naturalKeyField);
            if (!$keyIsSelected) {
                $this->addError('naturalKeyField', 'Das Schlüssel-Feld muss unter den übernommenen Feldern sein.');
                $this->activating = false;
                return;
            }
        }

        // Persist strategy settings on the stream BEFORE table creation,
        // because SchemaService::createTable inspects sync_strategy for meta columns.
        $this->stream->update([
            'sync_strategy'    => $this->syncStrategy,
            'natural_key'      => $this->naturalKeyField
                ? Str::snake($this->naturalKeyField)  // map source_key → column_name
                : null,
            'change_detection' => $this->changeDetection,
            'soft_delete'      => $this->softDelete,
        ]);

        // Create columns
        foreach ($selectedFields as $field) {
            $columnName = Str::snake($field['source_key']);

            DatawarehouseStreamColumn::create([
                'stream_id'   => $this->stream->id,
                'source_key'  => $field['source_key'],
                'column_name' => $columnName,
                'label'       => $field['label'],
                'data_type'   => $field['data_type'],
                'is_nullable' => $field['is_nullable'],
                'is_indexed'  => $field['is_indexed'],
                'transform'   => !empty($field['transform']) ? $field['transform'] : null,
                'position'    => $field['position'],
            ]);
        }

        // Create dynamic table
        try {
            app(StreamSchemaService::class)->createTable($this->stream, $user->id);
        } catch (\Throwable $e) {
            $this->addError('activation', 'Tabelle konnte nicht erstellt werden: ' . $e->getMessage());
            // Clean up columns
            $this->stream->columns()->delete();
            $this->activating = false;
            return;
        }

        // Set status to active
        $this->stream->update(['status' => 'active']);

        // Import any pending payloads
        $this->importPendingPayloads($user->id);

        $this->redirect(route('datawarehouse.dashboard'));
    }

    protected function importPendingPayloads(?int $userId): void
    {
        $pendingImports = DatawarehouseImport::where('stream_id', $this->stream->id)
            ->where('status', 'pending')
            ->whereNotNull('raw_payload')
            ->get();

        $importService = app(StreamImportService::class);

        foreach ($pendingImports as $pending) {
            $payload = json_decode($pending->raw_payload, true);
            if ($payload) {
                $importService->importFromPayload($this->stream, $payload, $userId);
            }
            $pending->update(['status' => 'processing']);
        }
    }

    protected function humanizeKey(string $key): string
    {
        return Str::of($key)
            ->replace(['_', '-', '.'], ' ')
            ->title()
            ->toString();
    }

    public function getWebhookUrlProperty(): string
    {
        return url('/api/datawarehouse/ingest/' . $this->stream->endpoint_token);
    }

    public function getHasSampleProperty(): bool
    {
        return !empty($this->stream->sample_payload);
    }

    public function getSampleRowProperty(): ?array
    {
        $sample = $this->stream->sample_payload;
        if (!$sample) {
            return null;
        }

        // Normalize to single row for display
        if (isset($sample[0]) && is_array($sample[0])) {
            return $sample[0];
        }

        foreach (['data', 'rows', 'items', 'records'] as $wrapper) {
            if (isset($sample[$wrapper]) && is_array($sample[$wrapper])) {
                $inner = $sample[$wrapper];
                return (isset($inner[0]) && is_array($inner[0])) ? $inner[0] : $inner;
            }
        }

        return $sample;
    }

    public function render()
    {
        return view('datawarehouse::livewire.stream-onboarding')
            ->layout('platform::layouts.app');
    }
}
