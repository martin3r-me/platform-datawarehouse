<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Providers\ProviderRegistry;

class ModalCreateStream extends Component
{
    public bool $open = false;

    // Stream fields
    public string $name = '';
    public string $description = '';
    public string $source_type = 'webhook_post';
    public string $mode = 'append';
    public string $upsert_key = '';

    // Pull-specific fields
    public ?int $connection_id = null;
    public string $endpoint_key = '';
    public string $pull_mode = 'full';          // full | incremental
    public string $pull_schedule = 'hourly';    // every_minute|every_5_min|every_15_min|hourly|daily|<cron>
    public string $incremental_field = '';

    // Success state - shows webhook URL after creation
    public bool $created = false;
    public string $webhookUrl = '';
    public string $createdStreamName = '';
    public ?int $createdStreamId = null;

    #[On('datawarehouse:create-stream')]
    public function openModal(): void
    {
        $this->reset(
            'name', 'description', 'source_type', 'mode', 'upsert_key',
            'connection_id', 'endpoint_key', 'pull_mode', 'pull_schedule', 'incremental_field',
            'created', 'webhookUrl', 'createdStreamName', 'createdStreamId',
        );
        $this->resetValidation();
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->created = false;
    }

    public function updatedSourceType(): void
    {
        // Reset pull fields when switching away from pull.
        if ($this->source_type !== 'pull_get') {
            $this->connection_id = null;
            $this->endpoint_key = '';
            $this->incremental_field = '';
        }
    }

    public function updatedConnectionId(): void
    {
        // When connection changes the available endpoints change too.
        $this->endpoint_key = '';
        $this->incremental_field = '';
    }

    public function updatedEndpointKey(): void
    {
        // Pre-fill strategy/mode defaults based on endpoint metadata.
        $endpoint = $this->resolveEndpoint();
        if (!$endpoint) {
            return;
        }
        if ($endpoint->defaultStrategy) {
            $this->mode = match ($endpoint->defaultStrategy) {
                'current'  => 'upsert',
                'snapshot' => 'snapshot',
                default    => 'append',
            };
            if ($endpoint->naturalKey && $this->mode === 'upsert') {
                $this->upsert_key = $endpoint->naturalKey;
            }
        }
        if ($endpoint->incrementalField) {
            $this->pull_mode = 'incremental';
            $this->incremental_field = $endpoint->incrementalField;
        } else {
            $this->pull_mode = 'full';
            $this->incremental_field = '';
        }
    }

    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:255',
            'description'       => 'nullable|string|max:1000',
            'source_type'       => 'required|in:manual,webhook_post,pull_get',
            'mode'              => 'required|in:snapshot,append,upsert',
            'upsert_key'        => 'required_if:mode,upsert|nullable|string|max:255',
            'connection_id'     => 'required_if:source_type,pull_get|nullable|integer',
            'endpoint_key'      => 'required_if:source_type,pull_get|nullable|string|max:100',
            'pull_mode'         => 'required_if:source_type,pull_get|nullable|in:full,incremental',
            'pull_schedule'     => 'required_if:source_type,pull_get|nullable|string|max:100',
            'incremental_field' => 'required_if:pull_mode,incremental|nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                   => 'Name ist erforderlich.',
            'upsert_key.required_if'          => 'Upsert-Key ist erforderlich im Upsert-Modus.',
            'connection_id.required_if'       => 'Verbindung ist erforderlich bei Pull-Streams.',
            'endpoint_key.required_if'        => 'Endpoint ist erforderlich bei Pull-Streams.',
            'pull_schedule.required_if'       => 'Frequenz ist erforderlich bei Pull-Streams.',
            'incremental_field.required_if'   => 'Inkrementelles Feld ist erforderlich im inkrementellen Modus.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $user = Auth::user();
        $team = $user->currentTeam;

        // Derive a reasonable default sync_strategy from the legacy mode field.
        // This can be fine-tuned in the onboarding step.
        $syncStrategy = match ($this->mode) {
            'upsert'   => 'current',
            'snapshot' => 'snapshot',
            default    => 'append',
        };

        $attributes = [
            'team_id'       => $team->id,
            'user_id'       => $user->id,
            'name'          => $this->name,
            'slug'          => Str::slug($this->name),
            'description'   => $this->description ?: null,
            'source_type'   => $this->source_type,
            'mode'          => $this->mode,
            'sync_strategy' => $syncStrategy,
            'upsert_key'    => $this->mode === 'upsert' ? $this->upsert_key : null,
            'natural_key'   => $this->mode === 'upsert' ? $this->upsert_key : null,
            'status'        => 'onboarding',
        ];

        if ($this->source_type === 'pull_get') {
            // Verify the connection belongs to the current team.
            $conn = DatawarehouseConnection::forTeam($team->id)->find($this->connection_id);
            if (!$conn) {
                $this->addError('connection_id', 'Verbindung nicht gefunden.');
                return;
            }

            $attributes['connection_id']     = $conn->id;
            $attributes['endpoint_key']      = $this->endpoint_key;
            $attributes['pull_mode']         = $this->pull_mode;
            $attributes['pull_schedule']     = $this->pull_schedule;
            $attributes['incremental_field'] = $this->pull_mode === 'incremental' ? $this->incremental_field : null;
        }

        $stream = DatawarehouseStream::create($attributes);

        // Switch to success view with webhook URL
        $this->createdStreamName = $stream->name;
        $this->createdStreamId = $stream->id;
        $this->webhookUrl = url('/api/datawarehouse/ingest/' . $stream->endpoint_token);
        $this->created = true;

        $this->dispatch('datawarehouse:stream-created');
    }

    /**
     * Return the currently selected Endpoint value object (or null).
     */
    protected function resolveEndpoint(): ?\Platform\Datawarehouse\Providers\Endpoint
    {
        if (!$this->connection_id || $this->endpoint_key === '') {
            return null;
        }
        $conn = DatawarehouseConnection::find($this->connection_id);
        if (!$conn) {
            return null;
        }
        $registry = app(ProviderRegistry::class);
        if (!$registry->has($conn->provider_key)) {
            return null;
        }
        foreach ($registry->get($conn->provider_key)->endpoints() as $ep) {
            if ($ep->key === $this->endpoint_key) {
                return $ep;
            }
        }
        return null;
    }

    public function getConnectionsProperty()
    {
        $user = Auth::user();
        if (!$user?->currentTeam) {
            return collect();
        }
        return DatawarehouseConnection::forTeam($user->currentTeam->id)
            ->active()
            ->orderBy('name')
            ->get();
    }

    public function getEndpointsProperty(): array
    {
        if (!$this->connection_id) {
            return [];
        }
        $conn = DatawarehouseConnection::find($this->connection_id);
        if (!$conn) {
            return [];
        }
        $registry = app(ProviderRegistry::class);
        if (!$registry->has($conn->provider_key)) {
            return [];
        }
        return $registry->get($conn->provider_key)->endpoints();
    }

    public function render()
    {
        return view('datawarehouse::livewire.modal-create-stream');
    }
}
