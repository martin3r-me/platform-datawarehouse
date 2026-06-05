<?php

namespace Platform\Datawarehouse\Providers\Generic;

use Illuminate\Support\Facades\Http;
use Platform\Datawarehouse\Models\DatawarehouseConnection;
use Platform\Datawarehouse\Models\DatawarehouseProviderDefinition;
use Platform\Datawarehouse\Providers\AuthField;
use Platform\Datawarehouse\Providers\Endpoint;
use Platform\Datawarehouse\Providers\PullContext;
use Platform\Datawarehouse\Providers\PullProvider;
use Platform\Datawarehouse\Providers\PullResult;

/**
 * Data-driven HTTP pull provider.
 *
 * One instance wraps one DatawarehouseProviderDefinition. All behaviour
 * (base URL, auth, endpoints, pagination, incremental filtering, response
 * shape) comes from the definition's JSON config — so new providers can be
 * added via UI/LLM tools without writing code.
 *
 * Rows are returned as-is; column mapping (incl. dot-path extraction) is done
 * downstream by PullStreamService::mapRow() against the stream's columns.
 */
class GenericHttpProvider implements PullProvider
{
    public function __construct(
        protected DatawarehouseProviderDefinition $definition,
    ) {}

    /**
     * The backing definition — used for cross-team guards at pull time.
     */
    public function definition(): DatawarehouseProviderDefinition
    {
        return $this->definition;
    }

    public function key(): string
    {
        return $this->definition->key;
    }

    public function label(): string
    {
        return $this->definition->label ?: $this->definition->key;
    }

    public function description(): ?string
    {
        return $this->definition->description;
    }

    public function icon(): ?string
    {
        return $this->definition->icon ?: 'heroicon-o-globe-alt';
    }

    public function authFields(): array
    {
        return match ($this->definition->auth_type) {
            'bearer' => [
                new AuthField(
                    key: 'token',
                    label: 'Bearer-Token',
                    type: AuthField::TYPE_PASSWORD,
                    required: true,
                    description: 'Wird als "Authorization: Bearer <token>" gesendet.',
                ),
            ],
            'header', 'query' => [
                new AuthField(
                    key: 'api_key',
                    label: 'API-Key',
                    type: AuthField::TYPE_PASSWORD,
                    required: true,
                    description: $this->definition->auth_type === 'header'
                        ? 'Wird im Header "' . ($this->authConfig('header_name') ?: 'X-API-Key') . '" gesendet.'
                        : 'Wird als Query-Parameter "' . ($this->authConfig('query_param') ?: 'api_key') . '" gesendet.',
                ),
            ],
            default => [],
        };
    }

    public function endpoints(): array
    {
        $out = [];
        foreach (($this->definition->endpoints ?? []) as $cfg) {
            if (!is_array($cfg) || empty($cfg['key'])) {
                continue;
            }
            $pagination = $cfg['pagination'] ?? [];
            $incremental = $cfg['incremental'] ?? [];

            $out[$cfg['key']] = new Endpoint(
                key: (string) $cfg['key'],
                label: (string) ($cfg['label'] ?? $cfg['key']),
                description: $cfg['description'] ?? null,
                paginated: ($pagination['strategy'] ?? 'none') !== 'none',
                incrementalField: $incremental['field'] ?? null,
                defaultStrategy: (string) ($cfg['default_strategy'] ?? 'current'),
                naturalKey: $cfg['natural_key'] ?? 'id',
                supportedStrategies: $cfg['supported_strategies'] ?? ['append', 'current', 'snapshot', 'scd2'],
                meta: $cfg, // full raw config; fetch() reads from here
            );
        }
        return $out;
    }

    public function testConnection(DatawarehouseConnection $connection): bool
    {
        $endpoints = $this->endpoints();
        if (empty($endpoints)) {
            throw new \RuntimeException('Provider hat keine Endpunkte konfiguriert.');
        }
        $endpoint = reset($endpoints);

        $context = new PullContext(
            connection:  $connection,
            stream:      new \Platform\Datawarehouse\Models\DatawarehouseStream(),
            endpoint:    $endpoint,
            cursor:      null,
            incremental: false,
        );

        // Throws on a non-2xx response; one page is enough to prove reachability + auth.
        $this->request($context, sizeOverride: 1);
        return true;
    }

    public function fetch(PullContext $context): PullResult
    {
        [$json, $rows] = $this->request($context);

        $cfg        = $context->endpoint->meta;
        $pagination = $cfg['pagination'] ?? [];
        $strategy   = $pagination['strategy'] ?? 'none';
        $naturalKey = $cfg['natural_key'] ?? 'id';

        $seenIds = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $id = data_get($row, $naturalKey);
                if ($id !== null) {
                    $seenIds[] = (string) $id;
                }
            }
        }

        $nextCursor = $this->nextCursor($strategy, $pagination, $json, $rows, $context->cursor);

        return new PullResult(
            rows: array_values(array_filter($rows, 'is_array')),
            nextCursor: $nextCursor,
            seenExternalIds: $seenIds,
            meta: ['strategy' => $strategy, 'received' => count($rows)],
        );
    }

    // ---------------------------------------------------------------------

    /**
     * Build and execute one HTTP GET. Returns [decodedJson, rows[]].
     *
     * @return array{0: mixed, 1: array<int, mixed>}
     */
    protected function request(PullContext $context, ?int $sizeOverride = null): array
    {
        $cfg        = $context->endpoint->meta;
        $pagination = $cfg['pagination'] ?? [];
        $strategy   = $pagination['strategy'] ?? 'none';

        $url   = $this->url((string) ($cfg['path'] ?? ''));
        $query = is_array($cfg['query'] ?? null) ? $cfg['query'] : [];

        // Page size.
        $sizeParam = $pagination['size_param'] ?? null;
        $pageSize  = $sizeOverride ?? ($pagination['page_size'] ?? null);
        if ($sizeParam && $pageSize !== null) {
            $query[$sizeParam] = $pageSize;
        }

        // Pagination cursor → request params.
        $cursor = $context->cursor ?? [];
        if ($strategy === 'page') {
            $pageParam = $pagination['page_param'] ?? 'page';
            $startPage = $pagination['start_page'] ?? 1;
            $query[$pageParam] = $cursor['page'] ?? $startPage;
        } elseif ($strategy === 'offset') {
            $offsetParam = $pagination['offset_param'] ?? 'offset';
            $query[$offsetParam] = $cursor['offset'] ?? 0;
        } elseif ($strategy === 'cursor' && !empty($cursor['cursor'])) {
            $cursorParam = $pagination['cursor_param'] ?? 'cursor';
            $query[$cursorParam] = $cursor['cursor'];
        }

        // Incremental filter.
        $incremental = $cfg['incremental'] ?? [];
        if ($context->incremental && $context->since && !empty($incremental['param'])) {
            $format = $incremental['format'] ?? \DateTimeInterface::ATOM;
            $query[$incremental['param']] = $context->since->format($format);
        }

        // Auth.
        $request = Http::acceptJson()->timeout(60);
        $authType = $this->definition->auth_type;
        if ($authType === 'bearer') {
            $request = $request->withToken((string) $context->connection?->credential('token', ''));
        } elseif ($authType === 'header') {
            $headerName = $this->authConfig('header_name') ?: 'X-API-Key';
            $request = $request->withHeaders([$headerName => (string) $context->connection?->credential('api_key', '')]);
        } elseif ($authType === 'query') {
            $queryParam = $this->authConfig('query_param') ?: 'api_key';
            $query[$queryParam] = (string) $context->connection?->credential('api_key', '');
        }

        $response = $request->get($url, $query);

        if (!$response->successful()) {
            throw new \RuntimeException(
                "HTTP {$response->status()} bei GET {$url}: " . \Illuminate\Support\Str::limit($response->body(), 300)
            );
        }

        $json = $response->json();
        $dataPath = $pagination['data_path'] ?? null;
        $rows = $dataPath ? data_get($json, $dataPath) : $json;

        if (!is_array($rows)) {
            $rows = [];
        }

        return [$json, $rows];
    }

    /**
     * Determine the cursor for the next page, or null when finished.
     *
     * @param  array<string, mixed>  $pagination
     * @param  array<int, mixed>     $rows
     * @param  array<string, mixed>|null  $cursor
     * @return array<string, mixed>|null
     */
    protected function nextCursor(string $strategy, array $pagination, mixed $json, array $rows, ?array $cursor): ?array
    {
        $cursor = $cursor ?? [];

        if ($strategy === 'page') {
            $startPage   = $pagination['start_page'] ?? 1;
            $currentPage = (int) ($cursor['page'] ?? $startPage);
            $lastPagePath = $pagination['last_page_path'] ?? null;
            if ($lastPagePath !== null) {
                $lastPage = (int) data_get($json, $lastPagePath, $currentPage);
                return $currentPage < $lastPage ? ['page' => $currentPage + 1] : null;
            }
            // No last-page hint: assume more while a full page came back.
            $pageSize = $pagination['page_size'] ?? null;
            return ($pageSize && count($rows) >= (int) $pageSize) ? ['page' => $currentPage + 1] : null;
        }

        if ($strategy === 'offset') {
            $offset   = (int) ($cursor['offset'] ?? 0);
            $pageSize = (int) ($pagination['page_size'] ?? 0);
            return ($pageSize > 0 && count($rows) >= $pageSize) ? ['offset' => $offset + $pageSize] : null;
        }

        if ($strategy === 'cursor') {
            $cursorPath = $pagination['cursor_path'] ?? null;
            $next = $cursorPath ? data_get($json, $cursorPath) : null;
            return !empty($next) ? ['cursor' => $next] : null;
        }

        return null;
    }

    protected function url(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        $base = $this->definition->base_url ?: (string) config('app.url');
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    protected function authConfig(string $key, mixed $default = null): mixed
    {
        $cfg = $this->definition->auth_config ?? [];
        return $cfg[$key] ?? $default;
    }
}
