<?php

namespace Platform\Datawarehouse\Providers;

/**
 * Describes a single callable resource (endpoint) exposed by a pull provider.
 *
 * Endpoints are declared statically by the provider class — they do not live
 * in the database. A stream references an endpoint by its key (e.g. "contacts").
 */
class Endpoint
{
    /**
     * @param  string        $key                Stable identifier, e.g. "contacts".
     * @param  string        $label              Human-readable label for the UI.
     * @param  string|null   $description        Optional description shown in pickers.
     * @param  bool          $paginated          Whether the provider returns pages.
     * @param  string|null   $incrementalField   Field name used for incremental fetches (e.g. 'updatedDate').
     * @param  string        $defaultStrategy    append | current | snapshot | scd2
     * @param  string|null   $naturalKey         Field name that uniquely identifies an entity (e.g. 'id').
     * @param  array<string> $supportedStrategies  Strategies allowed for this endpoint.
     * @param  array<string, mixed>  $meta        Arbitrary provider-specific hints.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $description = null,
        public readonly bool $paginated = false,
        public readonly ?string $incrementalField = null,
        public readonly string $defaultStrategy = 'current',
        public readonly ?string $naturalKey = 'id',
        public readonly array $supportedStrategies = ['append', 'current', 'snapshot', 'scd2'],
        public readonly array $meta = [],
    ) {}

    public function supportsIncremental(): bool
    {
        return $this->incrementalField !== null;
    }

    public function toArray(): array
    {
        return [
            'key'                   => $this->key,
            'label'                 => $this->label,
            'description'           => $this->description,
            'paginated'             => $this->paginated,
            'incremental_field'     => $this->incrementalField,
            'default_strategy'      => $this->defaultStrategy,
            'natural_key'           => $this->naturalKey,
            'supported_strategies'  => $this->supportedStrategies,
            'meta'                  => $this->meta,
        ];
    }
}
