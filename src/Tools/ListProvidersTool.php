<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Datawarehouse\Providers\ProviderRegistry;
use Platform\Datawarehouse\Services\SystemStreamProvisioner;

class ListProvidersTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'dwh.providers.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/providers - Listet alle registrierten Pull-Provider (Lexoffice, Land, Sprache, Feiertage, …) mit Key, Label, Description und Icon. System-Provider werden über include_system einbezogen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'include_system' => [
                    'type' => 'boolean',
                    'description' => 'Optional: System-Provider (Stammdaten wie Land, Sprache, Feiertage) einschließen. Default: false.',
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $registry = app(ProviderRegistry::class);
            $includeSystem = (bool)($arguments['include_system'] ?? false);

            $providers = [];
            foreach ($registry->all() as $key => $provider) {
                $isSystem = SystemStreamProvisioner::isSystemProvider($key);
                if ($isSystem && !$includeSystem) {
                    continue;
                }
                $providers[] = [
                    'key'            => $provider->key(),
                    'label'          => $provider->label(),
                    'description'    => $provider->description(),
                    'icon'           => $provider->icon(),
                    'is_system'      => $isSystem,
                    'endpoints_count' => count($provider->endpoints()),
                    'auth_fields_count' => count($provider->authFields()),
                ];
            }

            return ToolResult::success([
                'data' => $providers,
                'total' => count($providers),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Provider: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'providers', 'list'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => false,
            'idempotent' => true,
        ];
    }
}
