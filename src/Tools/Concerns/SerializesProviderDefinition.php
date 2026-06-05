<?php

namespace Platform\Datawarehouse\Tools\Concerns;

use Platform\Datawarehouse\Models\DatawarehouseProviderDefinition;

/**
 * Shared serializer so all provider-definition tools return the same shape.
 */
trait SerializesProviderDefinition
{
    protected function serializeProviderDefinition(DatawarehouseProviderDefinition $def): array
    {
        return [
            'id'          => $def->id,
            'uuid'        => $def->uuid,
            'key'         => $def->key,
            'label'       => $def->label,
            'description' => $def->description,
            'icon'        => $def->icon,
            'is_active'   => (bool) $def->is_active,
            'base_url'    => $def->base_url,
            'auth_type'   => $def->auth_type,
            'auth_config' => $def->auth_config,
            'endpoints'   => $def->endpoints,
            'team_id'     => $def->team_id,
            'created_at'  => $def->created_at?->toISOString(),
            'updated_at'  => $def->updated_at?->toISOString(),
        ];
    }

    /**
     * Validate the endpoints array. Returns an error string or null when valid.
     *
     * @param  mixed  $endpoints
     */
    protected function validateEndpoints(mixed $endpoints): ?string
    {
        if (!is_array($endpoints)) {
            return 'endpoints muss ein Array von Endpoint-Objekten sein.';
        }
        foreach ($endpoints as $i => $ep) {
            if (!is_array($ep)) {
                return "endpoints[{$i}] muss ein Objekt sein.";
            }
            if (empty($ep['key'])) {
                return "endpoints[{$i}].key ist erforderlich.";
            }
            if (empty($ep['path'])) {
                return "endpoints[{$i}].path ist erforderlich.";
            }
            $strategy = $ep['pagination']['strategy'] ?? 'none';
            if (!in_array($strategy, ['page', 'offset', 'cursor', 'none'], true)) {
                return "endpoints[{$i}].pagination.strategy muss page|offset|cursor|none sein.";
            }
        }
        return null;
    }

    /**
     * JSON schema fragment describing one endpoint config — reused by create/update.
     */
    protected function endpointsSchema(): array
    {
        return [
            'type' => 'array',
            'description' =>
                'Liste der Endpunkte. Jeder Endpunkt: {key, label?, description?, path (relativ zu base_url), '
                . 'query? (statische Query-Params als Objekt), '
                . 'pagination? {strategy: page|offset|cursor|none, page_param?, size_param?, page_size?, start_page?, '
                . 'offset_param?, cursor_param?, data_path (Pfad zu den Zeilen in der Antwort, z.B. "data.data"), '
                . 'last_page_path?, cursor_path?}, '
                . 'incremental? {field, param, format (PHP date format, z.B. "Y-m-d")}, '
                . 'natural_key? (eindeutiges ID-Feld, default "id"), default_strategy?, supported_strategies?}.',
            'items' => [
                'type' => 'object',
                'additionalProperties' => true,
            ],
        ];
    }
}
