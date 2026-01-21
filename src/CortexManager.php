<?php

declare(strict_types=1);

namespace JayI\Cortex;

use Closure;
use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Contracts\TenantContextContract;
use JayI\Cortex\Plugins\Chat\ChatRequest;
use JayI\Cortex\Plugins\Chat\Contracts\ChatClientContract;
use JayI\Cortex\Plugins\Prompt\Contracts\PromptContract;
use JayI\Cortex\Plugins\Prompt\Contracts\PromptRegistryContract;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderRegistryContract;
use JayI\Cortex\Support\TenantManager;

class CortexManager
{
    public function __construct(
        protected Container $container,
        protected PluginManagerContract $pluginManager,
    ) {}

    /**
     * Get the chat client.
     */
    public function chat(): ChatClientContract
    {
        return $this->container->make(ChatClientContract::class);
    }

    /**
     * Get a provider by ID or the default provider.
     */
    public function provider(?string $id = null): ProviderContract
    {
        $registry = $this->container->make(ProviderRegistryContract::class);

        if ($id !== null) {
            return $registry->get($id);
        }

        return $registry->default();
    }

    /**
     * Get the provider registry.
     */
    public function providers(): ProviderRegistryContract
    {
        return $this->container->make(ProviderRegistryContract::class);
    }

    /**
     * Get the plugin manager.
     */
    public function plugins(): PluginManagerContract
    {
        return $this->pluginManager;
    }

    /**
     * Quick helper to send a chat message.
     */
    public function send(ChatRequest $request): \JayI\Cortex\Plugins\Chat\ChatResponse
    {
        return $this->chat()->send($request);
    }

    /**
     * Quick helper to stream a chat message.
     */
    public function stream(ChatRequest $request): \JayI\Cortex\Plugins\Chat\StreamedResponse
    {
        return $this->chat()->stream($request);
    }

    /**
     * Use a specific provider.
     */
    public function using(string|ProviderContract $provider): ChatClientContract
    {
        return $this->chat()->using($provider);
    }

    /**
     * Set the current tenant context.
     */
    public function forTenant(TenantContextContract $tenant): static
    {
        $this->container->make(TenantManager::class)->set($tenant);

        return $this;
    }

    /**
     * Execute a callback within a tenant context.
     */
    public function withTenant(TenantContextContract $tenant, Closure $callback): mixed
    {
        return $this->container->make(TenantManager::class)->withTenant($tenant, $callback);
    }

    /**
     * Get the current tenant context.
     */
    public function tenant(): ?TenantContextContract
    {
        return $this->container->make(TenantManager::class)->current();
    }

    /**
     * Clear the current tenant context.
     */
    public function clearTenant(): static
    {
        $this->container->make(TenantManager::class)->clear();

        return $this;
    }

    /**
     * Get a prompt by ID and optionally version.
     */
    public function prompt(string $id, ?string $version = null): PromptContract
    {
        return $this->container->make(PromptRegistryContract::class)->get($id, $version);
    }

    /**
     * Get the prompt registry.
     */
    public function prompts(): PromptRegistryContract
    {
        return $this->container->make(PromptRegistryContract::class);
    }
}
