<?php

namespace Platform\Datawarehouse\Livewire;

use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseStreamColumn;
use Platform\Datawarehouse\Services\StreamSchemaService;

class ModalCreateStream extends Component
{
    public bool $open = false;

    // Stream fields
    public string $name = '';
    public string $description = '';
    public string $source_type = 'webhook_post';
    public string $mode = 'append';
    public string $upsert_key = '';

    // Column definitions
    public array $columns = [];

    // Success state - shows webhook URL after creation
    public bool $created = false;
    public string $webhookUrl = '';
    public string $createdStreamName = '';

    #[On('datawarehouse:create-stream')]
    public function openModal(): void
    {
        $this->reset('name', 'description', 'source_type', 'mode', 'upsert_key', 'columns', 'created', 'webhookUrl', 'createdStreamName');
        $this->resetValidation();
        $this->addColumn();
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->created = false;
    }

    public function addColumn(): void
    {
        $this->columns[] = [
            'source_key'  => '',
            'label'       => '',
            'data_type'   => 'string',
            'is_nullable' => true,
            'is_indexed'  => false,
            'transform'   => '',
        ];
    }

    public function removeColumn(int $index): void
    {
        unset($this->columns[$index]);
        $this->columns = array_values($this->columns);
    }

    public function rules(): array
    {
        return [
            'name'                    => 'required|string|max:255',
            'description'             => 'nullable|string|max:1000',
            'source_type'             => 'required|in:manual,webhook_post,pull_get',
            'mode'                    => 'required|in:snapshot,append,upsert',
            'upsert_key'              => 'required_if:mode,upsert|nullable|string|max:255',
            'columns'                 => 'required|array|min:1',
            'columns.*.source_key'    => 'required|string|max:255',
            'columns.*.label'         => 'required|string|max:255',
            'columns.*.data_type'     => 'required|in:string,integer,decimal,boolean,date,datetime,text,json',
            'columns.*.is_nullable'   => 'boolean',
            'columns.*.is_indexed'    => 'boolean',
            'columns.*.transform'     => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                 => 'Name ist erforderlich.',
            'columns.required'              => 'Mindestens eine Spalte ist erforderlich.',
            'columns.min'                   => 'Mindestens eine Spalte ist erforderlich.',
            'columns.*.source_key.required' => 'Source-Key ist erforderlich.',
            'columns.*.label.required'      => 'Label ist erforderlich.',
            'upsert_key.required_if'        => 'Upsert-Key ist erforderlich im Upsert-Modus.',
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
        ]);

        foreach ($this->columns as $position => $col) {
            $columnName = Str::snake($col['source_key']);

            DatawarehouseStreamColumn::create([
                'stream_id'   => $stream->id,
                'source_key'  => $col['source_key'],
                'column_name' => $columnName,
                'label'       => $col['label'],
                'data_type'   => $col['data_type'],
                'is_nullable' => $col['is_nullable'] ?? true,
                'is_indexed'  => $col['is_indexed'] ?? false,
                'transform'   => !empty($col['transform']) ? $col['transform'] : null,
                'position'    => $position,
            ]);
        }

        // Create dynamic table
        try {
            app(StreamSchemaService::class)->createTable($stream, $user->id);
        } catch (\Throwable $e) {
            // Table will be created on first import
        }

        // Switch to success view with webhook URL
        $this->createdStreamName = $stream->name;
        $this->webhookUrl = url('/api/datawarehouse/ingest/' . $stream->endpoint_token);
        $this->created = true;

        $this->dispatch('datawarehouse:stream-created');
    }

    public function render()
    {
        return view('datawarehouse::livewire.modal-create-stream');
    }
}
