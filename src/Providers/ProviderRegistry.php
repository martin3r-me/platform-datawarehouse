<?php

namespace Platform\Datawarehouse\Providers;

use Illuminate\Support\Facades\Auth;
use Platform\Datawarehouse\Models\DatawarehouseProviderDefinition;
use Platform\Datawarehouse\Providers\Generic\GenericHttpProvider;

/**
 * Registry of available PullProvider instances.
 *
 * Two sources of providers:
 *  1. Code providers (Lexoffice, Land, …) registered at application boot. The
 *     registry is a singleton, so these stateless instances are shared.
 *  2. Config providers — team-scoped DatawarehouseProviderDefinition rows wrapped
 *     in a GenericHttpProvider on demand. These let users/LLMs add providers
 *     without writing code.
 *
 * Code providers always win on key collisions. Resolution by key (has/get) is
 * team-agnostic so console pull jobs can resolve a provider from a stream's
 * connection without an authenticated team; listing (all/options) is scoped to
 * the current team so each team only sees its own config providers.
 */
class ProviderRegistry
{
    /** @var array<string, PullProvider> */
    protected array $providers = [];

    public function register(PullProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function has(string $key): bool
    {
        if (isset($this->providers[$key])) {
            return true;
        }
        return $this->findDefinition($key) !== null;
    }

    public function get(string $key): PullProvider
    {
        if (isset($this->providers[$key])) {
            return $this->providers[$key];
        }

        if ($definition = $this->findDefinition($key)) {
            return new GenericHttpProvider($definition);
        }

        throw new \RuntimeException("Unknown datawarehouse provider: {$key}");
    }

    /**
     * Look up an active config definition by key. Returns null if not found or
     * if the table is unavailable (e.g. migrations not yet run) — so code
     * providers keep working regardless.
     */
    protected function findDefinition(string $key): ?DatawarehouseProviderDefinition
    {
        try {
            return DatawarehouseProviderDefinition::query()
                ->where('key', $key)
                ->where('is_active', true)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * All code providers plus the current team's config providers.
     *
     * @return array<string, PullProvider>
     */
    public function all(): array
    {
        $all = $this->providers;

        foreach ($this->teamDefinitions() as $definition) {
            // Code providers win on key collision.
            if (!isset($all[$definition->key])) {
                $all[$definition->key] = new GenericHttpProvider($definition);
            }
        }

        return $all;
    }

    /**
     * Quick accessor for UI pickers — returns [key => label, …].
     * Excludes system providers (auto-provisioned, not user-selectable).
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        $out = [];
        foreach ($this->all() as $key => $provider) {
            if (\Platform\Datawarehouse\Services\SystemStreamProvisioner::isSystemProvider($key)) {
                continue;
            }
            $out[$key] = $provider->label();
        }
        asort($out);
        return $out;
    }

    /**
     * Config provider definitions visible to the current team (UI/listing only).
     *
     * @return \Illuminate\Support\Collection<int, DatawarehouseProviderDefinition>
     */
    protected function teamDefinitions()
    {
        $teamId = Auth::user()?->currentTeam?->id;
        if (!$teamId) {
            return collect();
        }

        try {
            return DatawarehouseProviderDefinition::query()
                ->where('team_id', $teamId)
                ->where('is_active', true)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
