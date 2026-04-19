<?php

namespace Platform\Datawarehouse\Providers;

/**
 * Registry of available PullProvider instances.
 *
 * Providers are registered at application boot (usually via the module's
 * ServiceProvider::boot). The registry is a singleton resolved from the
 * Laravel container, so provider classes remain stateless and shared.
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
        return isset($this->providers[$key]);
    }

    public function get(string $key): PullProvider
    {
        if (!isset($this->providers[$key])) {
            throw new \RuntimeException("Unknown datawarehouse provider: {$key}");
        }
        return $this->providers[$key];
    }

    /**
     * @return array<string, PullProvider>
     */
    public function all(): array
    {
        return $this->providers;
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
        foreach ($this->providers as $key => $provider) {
            if (\Platform\Datawarehouse\Services\SystemStreamProvisioner::isSystemProvider($key)) {
                continue;
            }
            $out[$key] = $provider->label();
        }
        asort($out);
        return $out;
    }
}
