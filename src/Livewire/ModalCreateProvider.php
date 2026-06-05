<?php

namespace Platform\Datawarehouse\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Datawarehouse\Models\DatawarehouseProviderDefinition;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Providers\Generic\GenericHttpProvider;
use Platform\Datawarehouse\Providers\PullContext;

class ModalCreateProvider extends Component
{
    public bool $open = false;
    public ?int $editingId = null;

    public string $label = '';
    public string $description = '';
    public string $icon = '';
    public string $baseUrl = '';
    public string $authType = 'none';
    public string $headerName = '';
    public string $queryParam = '';
    public bool $isActive = true;

    /** @var array<int, array<string, mixed>> */
    public array $endpoints = [];

    // Test panel state.
    public string $testToken = '';
    public ?string $testStatus = null; // success|error
    public ?string $testMessage = null;
    public array $testSample = [];
    public array $testFields = [];

    #[On('datawarehouse:create-provider-definition')]
    public function openForCreate(): void
    {
        $this->resetForm();
        $this->endpoints = [$this->blankEndpoint()];
        $this->open = true;
    }

    #[On('datawarehouse:edit-provider-definition')]
    public function openForEdit(int $id): void
    {
        $user = Auth::user();
        $def = DatawarehouseProviderDefinition::where('team_id', $user->currentTeam->id)->find($id);
        if (!$def) {
            return;
        }

        $this->resetForm();
        $this->editingId   = $def->id;
        $this->label       = $def->label;
        $this->description = (string) $def->description;
        $this->icon        = (string) $def->icon;
        $this->baseUrl     = (string) $def->base_url;
        $this->authType    = $def->auth_type;
        $this->headerName  = (string) ($def->auth_config['header_name'] ?? '');
        $this->queryParam  = (string) ($def->auth_config['query_param'] ?? '');
        $this->isActive    = (bool) $def->is_active;
        $this->endpoints   = array_map([$this, 'endpointToForm'], $def->endpoints ?? []);
        if (empty($this->endpoints)) {
            $this->endpoints = [$this->blankEndpoint()];
        }
        $this->open = true;
    }

    public function addEndpoint(): void
    {
        $this->endpoints[] = $this->blankEndpoint();
    }

    public function removeEndpoint(int $index): void
    {
        unset($this->endpoints[$index]);
        $this->endpoints = array_values($this->endpoints);
        if (empty($this->endpoints)) {
            $this->endpoints = [$this->blankEndpoint()];
        }
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $this->validate([
            'label'           => 'required|string|max:255',
            'authType'        => 'required|in:none,bearer,header,query',
            'endpoints'       => 'required|array|min:1',
            'endpoints.*.key' => 'required|string',
            'endpoints.*.path' => 'required|string',
        ], [
            'label.required'         => 'Name ist erforderlich.',
            'endpoints.*.key.required'  => 'Endpunkt-Key ist erforderlich.',
            'endpoints.*.path.required' => 'Endpunkt-Pfad ist erforderlich.',
        ]);

        $user = Auth::user();

        $attrs = [
            'team_id'     => $user->currentTeam->id,
            'user_id'     => $user->id,
            'label'       => $this->label,
            'description' => $this->description ?: null,
            'icon'        => $this->icon ?: null,
            'base_url'    => $this->baseUrl ?: null,
            'auth_type'   => $this->authType,
            'auth_config' => $this->buildAuthConfig(),
            'endpoints'   => array_map([$this, 'formToEndpoint'], $this->endpoints),
            'is_active'   => $this->isActive,
        ];

        if ($this->editingId) {
            $def = DatawarehouseProviderDefinition::where('team_id', $user->currentTeam->id)->find($this->editingId);
            if (!$def) {
                return;
            }
            $def->update($attrs);
        } else {
            DatawarehouseProviderDefinition::create($attrs);
        }

        $this->dispatch('datawarehouse:provider-definition-saved');
        $this->close();
    }

    /**
     * Test the given endpoint with a transient (unsaved) definition + credentials.
     */
    public function test(int $index): void
    {
        $this->testStatus = null;
        $this->testMessage = null;
        $this->testSample = [];
        $this->testFields = [];

        if (!isset($this->endpoints[$index])) {
            return;
        }

        try {
            $definition = new DatawarehouseProviderDefinition([
                'label'       => $this->label ?: 'test',
                'base_url'    => $this->baseUrl ?: null,
                'auth_type'   => $this->authType,
                'auth_config' => $this->buildAuthConfig(),
                'endpoints'   => [$this->formToEndpoint($this->endpoints[$index])],
            ]);

            $provider = new GenericHttpProvider($definition);
            $endpoints = $provider->endpoints();
            if (empty($endpoints)) {
                throw new \RuntimeException('Endpunkt unvollständig (key/path?).');
            }
            $endpoint = reset($endpoints);

            $connection = new \Platform\Datawarehouse\Models\DatawarehouseConnection([
                'team_id'      => Auth::user()->currentTeam->id,
                'provider_key' => 'test',
                'name'         => 'test',
            ]);
            $connection->credentials = $this->buildTestCredentials();

            $context = new PullContext(
                connection:  $connection,
                stream:      new DatawarehouseStream(),
                endpoint:    $endpoint,
                cursor:      null,
                incremental: false,
            );

            $result = $provider->fetch($context);
            $this->testSample = array_slice($result->rows, 0, 3);
            $this->testFields = !empty($result->rows) && is_array($result->rows[0]) ? array_keys($result->rows[0]) : [];
            $this->testStatus = 'success';
            $this->testMessage = $result->count() . ' Zeile(n) empfangen.';
        } catch (\Throwable $e) {
            $this->testStatus = 'error';
            $this->testMessage = $e->getMessage();
        }
    }

    public function render()
    {
        return view('datawarehouse::livewire.modal-create-provider');
    }

    // --- Helpers ---

    protected function buildAuthConfig(): ?array
    {
        return match ($this->authType) {
            'header' => ['header_name' => $this->headerName ?: 'X-API-Key'],
            'query'  => ['query_param' => $this->queryParam ?: 'api_key'],
            default  => null,
        };
    }

    protected function buildTestCredentials(): array
    {
        return match ($this->authType) {
            'bearer' => ['token' => $this->testToken],
            'header', 'query' => ['api_key' => $this->testToken],
            default => [],
        };
    }

    protected function blankEndpoint(): array
    {
        return [
            'key' => '', 'label' => '', 'path' => '',
            'query' => '',                      // raw "k=v" lines or JSON
            'strategy' => 'none',
            'page_param' => 'page', 'size_param' => '', 'page_size' => '',
            'last_page_path' => '', 'data_path' => '',
            'incremental_field' => '', 'incremental_param' => '', 'incremental_format' => 'Y-m-d',
            'natural_key' => 'id',
        ];
    }

    /**
     * Map a stored endpoint config → flat form row.
     */
    protected function endpointToForm(array $ep): array
    {
        $p = $ep['pagination'] ?? [];
        $inc = $ep['incremental'] ?? [];
        $query = $ep['query'] ?? [];

        return [
            'key' => $ep['key'] ?? '',
            'label' => $ep['label'] ?? '',
            'path' => $ep['path'] ?? '',
            'query' => is_array($query) ? json_encode($query, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $query,
            'strategy' => $p['strategy'] ?? 'none',
            'page_param' => $p['page_param'] ?? 'page',
            'size_param' => $p['size_param'] ?? '',
            'page_size' => (string) ($p['page_size'] ?? ''),
            'last_page_path' => $p['last_page_path'] ?? '',
            'data_path' => $p['data_path'] ?? '',
            'incremental_field' => $inc['field'] ?? '',
            'incremental_param' => $inc['param'] ?? '',
            'incremental_format' => $inc['format'] ?? 'Y-m-d',
            'natural_key' => $ep['natural_key'] ?? 'id',
        ];
    }

    /**
     * Map a flat form row → stored endpoint config.
     */
    protected function formToEndpoint(array $row): array
    {
        $ep = [
            'key'         => trim((string) ($row['key'] ?? '')),
            'label'       => trim((string) ($row['label'] ?? '')) ?: ($row['key'] ?? ''),
            'path'        => trim((string) ($row['path'] ?? '')),
            'natural_key' => trim((string) ($row['natural_key'] ?? 'id')) ?: 'id',
        ];

        $query = $this->parseQuery($row['query'] ?? '');
        if (!empty($query)) {
            $ep['query'] = $query;
        }

        $strategy = $row['strategy'] ?? 'none';
        $pagination = ['strategy' => $strategy];
        if ($strategy !== 'none') {
            if (!empty($row['page_param']))     $pagination['page_param'] = $row['page_param'];
            if (!empty($row['size_param']))     $pagination['size_param'] = $row['size_param'];
            if (($row['page_size'] ?? '') !== '') $pagination['page_size'] = (int) $row['page_size'];
            if (!empty($row['last_page_path'])) $pagination['last_page_path'] = $row['last_page_path'];
        }
        if (!empty($row['data_path'])) {
            $pagination['data_path'] = $row['data_path'];
        }
        $ep['pagination'] = $pagination;

        if (!empty($row['incremental_field'])) {
            $ep['incremental'] = [
                'field'  => $row['incremental_field'],
                'param'  => $row['incremental_param'] ?: $row['incremental_field'],
                'format' => $row['incremental_format'] ?: 'Y-m-d',
            ];
        }

        return $ep;
    }

    /**
     * Parse the query textarea: accepts JSON ({"a":1}) or "key=value" per line.
     *
     * @return array<string, mixed>
     */
    protected function parseQuery(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $out = [];
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $out[trim($k)] = trim($v);
        }
        return $out;
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->label = '';
        $this->description = '';
        $this->icon = '';
        $this->baseUrl = '';
        $this->authType = 'none';
        $this->headerName = '';
        $this->queryParam = '';
        $this->isActive = true;
        $this->endpoints = [];
        $this->testToken = '';
        $this->testStatus = null;
        $this->testMessage = null;
        $this->testSample = [];
        $this->testFields = [];
        $this->resetValidation();
    }
}
