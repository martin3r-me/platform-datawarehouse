<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Platform\Datawarehouse\Models\DatawarehouseStream;

class ModalCreateStream extends Component
{
    public bool $open = false;

    // Stream fields
    public string $name = '';
    public string $description = '';
    public string $source_type = 'webhook_post';
    public string $mode = 'append';
    public string $upsert_key = '';

    // Success state - shows webhook URL after creation
    public bool $created = false;
    public string $webhookUrl = '';
    public string $createdStreamName = '';
    public ?int $createdStreamId = null;

    #[On('datawarehouse:create-stream')]
    public function openModal(): void
    {
        $this->reset('name', 'description', 'source_type', 'mode', 'upsert_key', 'created', 'webhookUrl', 'createdStreamName', 'createdStreamId');
        $this->resetValidation();
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->created = false;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'source_type' => 'required|in:manual,webhook_post,pull_get',
            'mode'        => 'required|in:snapshot,append,upsert',
            'upsert_key'  => 'required_if:mode,upsert|nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'Name ist erforderlich.',
            'upsert_key.required_if' => 'Upsert-Key ist erforderlich im Upsert-Modus.',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $user = Auth::user();
        $team = $user->currentTeam;

        $stream = DatawarehouseStream::create([
            'team_id'     => $team->id,
            'user_id'     => $user->id,
            'name'        => $this->name,
            'slug'        => Str::slug($this->name),
            'description' => $this->description ?: null,
            'source_type' => $this->source_type,
            'mode'        => $this->mode,
            'upsert_key'  => $this->mode === 'upsert' ? $this->upsert_key : null,
            'status'      => 'onboarding',
        ]);

        // Switch to success view with webhook URL
        $this->createdStreamName = $stream->name;
        $this->createdStreamId = $stream->id;
        $this->webhookUrl = url('/api/datawarehouse/ingest/' . $stream->endpoint_token);
        $this->created = true;

        $this->dispatch('datawarehouse:stream-created');
    }

    public function render()
    {
        return view('datawarehouse::livewire.modal-create-stream');
    }
}
