<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Provider\Contracts;

use Illuminate\Support\Collection;

interface ProviderRegistryContract
{
    /**
     * Register a provider.
     *
     * @param  ProviderContract|class-string<ProviderContract>  $provider
     */
    public function register(string $id, ProviderContract|string $provider): void;

    /**
     * Get a provider by ID.
     */
    public function get(string $id): ProviderContract;

    /**
     * Check if a provider is registered.
     */
    public function has(string $id): bool;

    /**
     * Get all registered providers.
     *
     * @return Collection<string, ProviderContract>
     */
    public function all(): Collection;

    /**
     * Get the default provider.
     */
    public function default(): ProviderContract;

    /**
     * Set the default provider.
     */
    public function setDefault(string $id): void;

    /**
     * Swap a provider implementation (useful for testing).
     */
    public function swap(string $id, ProviderContract $provider): void;
}
