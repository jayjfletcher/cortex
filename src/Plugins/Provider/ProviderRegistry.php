<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Provider;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use JayI\Cortex\Events\Concerns\DispatchesCortexEvents;
use JayI\Cortex\Events\Provider\ProviderRegistered;
use JayI\Cortex\Exceptions\ProviderException;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderRegistryContract;
use JayI\Cortex\Support\TenantManager;

class ProviderRegistry implements ProviderRegistryContract
{
    use DispatchesCortexEvents;
    /**
     * @var Collection<string, ProviderContract|class-string<ProviderContract>>
     */
    protected Collection $providers;

    /**
     * @var Collection<string, ProviderContract>
     */
    protected Collection $resolved;

    protected ?string $defaultProvider = null;

    public function __construct(
        protected Container $container,
    ) {
        $this->providers = new Collection();
        $this->resolved = new Collection();
    }

    /**
     * Register a provider.
     *
     * @param  ProviderContract|class-string<ProviderContract>  $provider
     */
    public function register(string $id, ProviderContract|string $provider): void
    {
        $this->providers->put($id, $provider);

        // Set as default if first provider
        if ($this->defaultProvider === null) {
            $this->defaultProvider = $id;
        }

        $this->dispatchCortexEvent(new ProviderRegistered(
            provider: $provider,
            providerId: $id,
        ));
    }

    /**
     * Get a provider by ID.
     *
     * @throws ProviderException
     */
    public function get(string $id): ProviderContract
    {
        if (! $this->has($id)) {
            throw ProviderException::notFound($id);
        }

        // Check if already resolved
        if ($this->resolved->has($id)) {
            $provider = $this->resolved->get($id);
        } else {
            $provider = $this->providers->get($id);

            // Resolve if it's a class string
            if (is_string($provider)) {
                $provider = $this->container->make($provider);
            }

            // Cache the resolved provider
            $this->resolved->put($id, $provider);
        }

        // Apply tenant-specific configuration if available
        return $this->applyTenantConfig($id, $provider);
    }

    /**
     * Apply tenant-specific configuration to a provider.
     */
    protected function applyTenantConfig(string $id, ProviderContract $provider): ProviderContract
    {
        // Check if TenantManager is bound and tenancy is enabled
        if (! $this->container->bound(TenantManager::class)) {
            return $provider;
        }

        $tenantManager = $this->container->make(TenantManager::class);
        $tenant = $tenantManager->current();

        if ($tenant === null) {
            return $provider;
        }

        $tenantConfig = $tenant->getProviderConfig($id);
        $apiKey = $tenant->getApiKey($id);

        if (empty($tenantConfig) && $apiKey === null) {
            return $provider;
        }

        // Build config with API key if present
        $config = $tenantConfig;
        if ($apiKey !== null) {
            $config['credentials']['key'] = $apiKey;
        }

        return $provider->withConfig($config);
    }

    /**
     * Check if a provider is registered.
     */
    public function has(string $id): bool
    {
        return $this->providers->has($id);
    }

    /**
     * Get all registered providers.
     *
     * @return Collection<string, ProviderContract>
     */
    public function all(): Collection
    {
        // Resolve all providers
        foreach ($this->providers->keys() as $id) {
            if (! $this->resolved->has($id)) {
                $this->get($id);
            }
        }

        return $this->resolved;
    }

    /**
     * Get the default provider.
     *
     * @throws ProviderException
     */
    public function default(): ProviderContract
    {
        if ($this->defaultProvider === null) {
            throw ProviderException::noDefault();
        }

        return $this->get($this->defaultProvider);
    }

    /**
     * Set the default provider.
     *
     * @throws ProviderException
     */
    public function setDefault(string $id): void
    {
        if (! $this->has($id)) {
            throw ProviderException::notFound($id);
        }

        $this->defaultProvider = $id;
    }

    /**
     * Swap a provider implementation (useful for testing).
     */
    public function swap(string $id, ProviderContract $provider): void
    {
        $this->providers->put($id, $provider);
        $this->resolved->put($id, $provider);
    }
}
