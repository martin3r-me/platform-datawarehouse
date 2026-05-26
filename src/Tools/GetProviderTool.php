<?php

namespace Platform\Datawarehouse\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Datawarehouse\Providers\ProviderRegistry;
use Platform\Datawarehouse\Services\SystemStreamProvisioner;

class GetProviderTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'dwh.provider.GET';
    }

    public function getDescription(): string
    {
        return 'GET /datawarehouse/providers/{key} - Holt einen Provider inkl. authFields[] (welche Credentials beim Anlegen einer Connection erforderlich sind) und endpoints[] (welche Stream-Endpunkte verfügbar sind). ERFORDERLICH: provider_key.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'provider_key' => [
                    'type' => 'string',
                    'description' => 'Key des Providers (ERFORDERLICH), z.B. "lexoffice".',
                ],
            ],
            'required' => ['provider_key'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $providerKey = trim((string)($arguments['provider_key'] ?? ''));
            if ($providerKey === '') {
                return ToolResult::error('VALIDATION_ERROR', 'provider_key ist erforderlich.');
            }

            $registry = app(ProviderRegistry::class);
            if (!$registry->has($providerKey)) {
                return ToolResult::error('NOT_FOUND', 'Provider "'.$providerKey.'" ist nicht registriert.');
            }
            $provider = $registry->get($providerKey);

            $authFields = [];
            foreach ($provider->authFields() as $f) {
                $authFields[] = [
                    'key'         => $f->key,
                    'label'       => $f->label,
                    'type'        => $f->type,
                    'required'    => $f->required,
                    'description' => $f->description,
                    'placeholder' => $f->placeholder,
                    'options'     => $f->options,
                ];
            }
            $endpoints = [];
            foreach ($provider->endpoints() as $endpoint) {
                $endpoints[] = $endpoint->toArray();
            }

            return ToolResult::success([
                'key'         => $provider->key(),
                'label'       => $provider->label(),
                'description' => $provider->description(),
                'icon'        => $provider->icon(),
                'is_system'   => SystemStreamProvisioner::isSystemProvider($provider->key()),
                'auth_fields' => $authFields,
                'endpoints'   => $endpoints,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden des Providers: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'read_only' => true,
            'category' => 'read',
            'tags' => ['datawarehouse', 'providers', 'get'],
            'risk_level' => 'safe',
            'requires_auth' => true,
            'requires_team' => false,
            'idempotent' => true,
        ];
    }
}
