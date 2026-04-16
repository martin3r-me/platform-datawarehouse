<?php

namespace Platform\Datawarehouse\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Providers\ProviderRegistry;

class ModalCreateConnection extends Component
{
    public bool $open = false;

    public ?int $editingId = null;

    public string $providerKey = '';
    public string $name = '';
    public string $description = '';
    public array $credentials = [];   // keyed by AuthField::$key
    public array $meta = [];
    public bool $isActive = true;

    public ?string $testStatus = null; // success|error
    public ?string $testMessage = null;

    public function mount(): void
    {
        $registry = app(ProviderRegistry::class);
        $first = array_key_first($registry->options());
        if ($first) {
            $this->providerKey = $first;
        }
    }

    #[On('datawarehouse:create-connection')]
    public function openForCreate(): void
    {
        $this->resetForm();
        $this->open = true;
    }

    #[On('datawarehouse:edit-connection')]
    public function openForEdit(int $id): void
    {
        $user = Auth::user();
        $conn = DatawarehouseConnection::where('team_id', $user->currentTeam->id)->find($id);
        if (!$conn) {
            return;
        }

        $this->resetForm();
        $this->editingId   = $conn->id;
        $this->providerKey = $conn->provider_key;
        $this->name        = $conn->name;
        $this->description = (string) $conn->description;
        $this->credentials = $conn->credentials ?? [];
        $this->meta        = $conn->meta ?? [];
        $this->isActive    = $conn->is_active;
        $this->open = true;
    }

    public function updatedProviderKey(): void
    {
        // Reset credentials so the new provider's fields start empty.
        $this->credentials = [];
        $this->testStatus = null;
        $this->testMessage = null;
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $this->validate([
            'providerKey' => 'required|string',
            'name'        => 'required|string|max:255',
        ], [
            'name.required' => 'Name ist erforderlich.',
        ]);

        $registry = app(ProviderRegistry::class);
        if (!$registry->has($this->providerKey)) {
            $this->addError('providerKey', 'Unbekannter Provider.');
            return;
        }

        $provider = $registry->get($this->providerKey);

        // Validate required auth fields.
        foreach ($provider->authFields() as $field) {
            if ($field->required && empty($this->credentials[$field->key])) {
                $this->addError("credentials.{$field->key}", "{$field->label} ist erforderlich.");
                return;
            }
        }

        $user = Auth::user();

        $attrs = [
            'team_id'      => $user->currentTeam->id,
            'user_id'      => $user->id,
            'provider_key' => $this->providerKey,
            'name'         => $this->name,
            'description'  => $this->description ?: null,
            'credentials'  => $this->credentials,
            'meta'         => $this->meta,
            'is_active'    => $this->isActive,
        ];

        if ($this->editingId) {
            $conn = DatawarehouseConnection::where('team_id', $user->currentTeam->id)->find($this->editingId);
            if (!$conn) {
                return;
            }
            $conn->update($attrs);
        } else {
            DatawarehouseConnection::create($attrs);
        }

        $this->dispatch('datawarehouse:connection-saved');
        $this->close();
    }

    public function test(): void
    {
        $registry = app(ProviderRegistry::class);
        if (!$registry->has($this->providerKey)) {
            $this->testStatus = 'error';
            $this->testMessage = 'Unbekannter Provider.';
            return;
        }

        // Build a transient connection (not persisted) so testing works before save.
        $conn = new DatawarehouseConnection([
            'provider_key' => $this->providerKey,
            'credentials'  => $this->credentials,
            'meta'         => $this->meta,
            'is_active'    => true,
        ]);

        try {
            $provider = $registry->get($this->providerKey);
            $ok = $provider->testConnection($conn);
            $this->testStatus = $ok ? 'success' : 'error';
            $this->testMessage = $ok ? 'Verbindung erfolgreich.' : 'Test fehlgeschlagen.';
        } catch (\Throwable $e) {
            $this->testStatus = 'error';
            $this->testMessage = $e->getMessage();
        }
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->description = '';
        $this->credentials = [];
        $this->meta = [];
        $this->isActive = true;
        $this->testStatus = null;
        $this->testMessage = null;
        $this->resetValidation();
    }

    public function getAuthFieldsProperty(): array
    {
        $registry = app(ProviderRegistry::class);
        if (!$registry->has($this->providerKey)) {
            return [];
        }
        return $registry->get($this->providerKey)->authFields();
    }

    public function render()
    {
        $registry = app(ProviderRegistry::class);

        return view('datawarehouse::livewire.modal-create-connection', [
            'providerOptions' => $registry->options(),
        ]);
    }
}
