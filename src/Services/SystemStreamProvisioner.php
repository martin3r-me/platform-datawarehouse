<?php

namespace Platform\Datawarehouse\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Platform\Datawarehouse\Models\DatawarehouseStream;
use Platform\Datawarehouse\Models\DatawarehouseStreamColumn;
use Platform\Datawarehouse\Providers\Endpoint;
use Platform\Datawarehouse\Providers\PullContext;
use Platform\Datawarehouse\Providers\ProviderRegistry;

class SystemStreamProvisioner
{
    /**
     * Provider key => stream metadata.
     * Order matters: Land before Bundesland before Feiertage (FK dependencies).
     */
    public const SYSTEM_PROVIDERS = [
        'land'       => ['endpoint' => 'laender',       'name' => 'Länder'],
        'sprache'    => ['endpoint' => 'sprachen',      'name' => 'Sprachen'],
        'waehrung'   => ['endpoint' => 'waehrungen',    'name' => 'Währungen'],
        'bundesland' => ['endpoint' => 'bundeslaender', 'name' => 'Bundesländer / Kantone'],
        'feiertage'  => ['endpoint' => 'feiertage',     'name' => 'Feiertage DACH'],
    ];

    public function ensureForTeam(int $teamId): void
    {
        $existingSlugs = DatawarehouseStream::forTeam($teamId)
            ->system()
            ->pluck('slug')
            ->toArray();

        $registry = app(ProviderRegistry::class);

        foreach (self::SYSTEM_PROVIDERS as $providerKey => $config) {
            $slug = Str::slug($config['name']);

            if (in_array($slug, $existingSlugs, true)) {
                continue;
            }

            if (!$registry->has($providerKey)) {
                continue;
            }

            try {
                $this->provisionStream($teamId, $providerKey, $config, $registry);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    "Datawarehouse: system stream '{$providerKey}' provisioning failed for team {$teamId}: " . $e->getMessage()
                );
            }
        }
    }

    private function provisionStream(int $teamId, string $providerKey, array $config, ProviderRegistry $registry): void
    {
        $provider = $registry->get($providerKey);
        $endpoint = $provider->endpoints()[$config['endpoint']];

        // 1. Create the stream record
        $stream = DatawarehouseStream::create([
            'team_id'       => $teamId,
            'name'          => $config['name'],
            'slug'          => Str::slug($config['name']),
            'description'   => $provider->description(),
            'source_type'   => 'pull_get',
            'endpoint_key'  => $config['endpoint'],
            'sync_strategy' => $endpoint->defaultStrategy,
            'natural_key'   => $endpoint->naturalKey,
            'mode'          => $endpoint->defaultStrategy === 'current' ? 'upsert' : 'snapshot',
            'upsert_key'    => $endpoint->defaultStrategy === 'current' ? $endpoint->naturalKey : null,
            'status'        => 'active',
            'is_system'     => true,
        ]);

        // 2. Fetch data from the provider
        $context = new PullContext(
            connection: null,
            stream: $stream,
            endpoint: $endpoint,
        );

        $result = $provider->fetch($context);

        if (empty($result->rows)) {
            return;
        }

        // 3. Create column definitions from the first row
        $sampleRow = $result->rows[0];
        $position = 0;

        foreach ($sampleRow as $key => $value) {
            $columnName = StreamSchemaService::sanitizeColumnName(Str::snake($key));

            DatawarehouseStreamColumn::create([
                'stream_id'   => $stream->id,
                'source_key'  => $key,
                'column_name' => $columnName,
                'label'       => $this->humanLabel($key),
                'data_type'   => $this->detectType($value),
                'is_nullable' => $key !== 'id',
                'is_indexed'  => in_array($key, ['id', $endpoint->naturalKey], true),
                'is_active'   => true,
                'position'    => $position++,
            ]);
        }

        // 4. Create the dynamic table
        $schemaService = new StreamSchemaService();
        $schemaService->createTable($stream);

        // 5. Import data
        $this->importRows($stream, $result->rows);
    }

    private function importRows(DatawarehouseStream $stream, array $rows): void
    {
        $columns = $stream->columns()->get()->keyBy('source_key');
        $tableName = $stream->getDynamicTableName();
        $now = now();

        $mapped = [];
        foreach ($rows as $row) {
            $dbRow = [
                '_external_id' => $row['id'] ?? null,
                '_synced_at'   => $now,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];

            foreach ($row as $key => $value) {
                if (!isset($columns[$key])) {
                    continue;
                }
                $col = $columns[$key];
                $dbRow[$col->column_name] = $col->applyTransform($value);
            }

            $mapped[] = $dbRow;
        }

        if (empty($mapped)) {
            return;
        }

        // Find the natural key column name for upsert
        $naturalKey = $stream->natural_key;
        $upsertColumn = '_external_id';
        if ($naturalKey && isset($columns[$naturalKey])) {
            $upsertColumn = $columns[$naturalKey]->column_name;
        }

        $updateColumns = array_keys($mapped[0]);

        foreach (array_chunk($mapped, 500) as $chunk) {
            DB::table($tableName)->upsert($chunk, [$upsertColumn], $updateColumns);
        }
    }

    private function detectType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'decimal';
        }

        return 'string';
    }

    private function humanLabel(string $key): string
    {
        return Str::of($key)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    /**
     * Check if a provider key is a system provider.
     */
    public static function isSystemProvider(string $providerKey): bool
    {
        return isset(self::SYSTEM_PROVIDERS[$providerKey]);
    }
}
