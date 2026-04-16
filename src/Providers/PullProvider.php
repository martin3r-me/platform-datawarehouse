<?php

namespace Platform\Datawarehouse\Providers;

use Platform\Datawarehouse\Models\DatawarehouseConnection;

/**
 * Contract implemented by every external-data provider (lexoffice, personio,
 * hubspot, …). Providers are code-only singletons registered in the
 * ProviderRegistry — they never live in the database.
 *
 * Rule of thumb: one provider = one third-party service. Endpoints within
 * a provider expose the individual resources (contacts, invoices, …) that
 * can become streams.
 */
interface PullProvider
{
    /**
     * Stable key used to look up this provider and to reference it from a
     * connection row. Lower-case, snake_case, e.g. "lexoffice".
     */
    public function key(): string;

    /**
     * Human-readable label for UI pickers.
     */
    public function label(): string;

    /**
     * Optional short description shown in the provider picker.
     */
    public function description(): ?string;

    /**
     * Optional heroicon name used in navigation/UI.
     */
    public function icon(): ?string;

    /**
     * Fields the user must fill to create a connection for this provider.
     *
     * @return array<int, AuthField>
     */
    public function authFields(): array;

    /**
     * All endpoints this provider can pull from.
     *
     * @return array<string, Endpoint>  Keyed by Endpoint::$key.
     */
    public function endpoints(): array;

    /**
     * Lightweight credentials check. Throw on failure, return true on success.
     */
    public function testConnection(DatawarehouseConnection $connection): bool;

    /**
     * Fetch one page of data. The writer / pull service loops over this call
     * until PullResult::isLastPage() is true, feeding the cursor back in.
     */
    public function fetch(PullContext $context): PullResult;
}
