<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Provider\Contracts;

use JayI\Cortex\Plugins\Provider\ProviderCollection;

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
     */
    public function all(): ProviderCollection;

    /**
     * Get only the specified providers.
     *
     * @param  array<int, string>  $ids
     */
    public function only(array $ids): ProviderCollection;

    /**
     * Get all providers except the specified ones.
     *
     * @param  array<int, string>  $ids
     */
    public function except(array $ids): ProviderCollection;

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
