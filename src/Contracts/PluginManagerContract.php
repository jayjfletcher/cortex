<?php

declare(strict_types=1);

namespace JayI\Cortex\Contracts;

use Illuminate\Support\Collection;

interface PluginManagerContract
{
    /**
     * Register a plugin.
     */
    public function register(PluginContract $plugin): void;

    /**
     * Boot all registered plugins in dependency order.
     */
    public function boot(): void;

    /**
     * Get a registered plugin by ID.
     */
    public function get(string $id): ?PluginContract;

    /**
     * Check if a plugin is registered.
     */
    public function has(string $id): bool;

    /**
     * Get all registered plugins.
     *
     * @return Collection<string, PluginContract>
     */
    public function all(): Collection;

    /**
     * Check if a feature is provided by any plugin.
     */
    public function hasFeature(string $feature): bool;

    /**
     * Get the plugin providing a feature.
     */
    public function getFeatureProvider(string $feature): ?PluginContract;

    /**
     * Register an extension point.
     */
    public function registerExtensionPoint(string $name, ExtensionPointContract $point): void;

    /**
     * Get an extension point by name.
     */
    public function getExtensionPoint(string $name): ?ExtensionPointContract;

    /**
     * Extend an extension point.
     */
    public function extend(string $extensionPoint, mixed $extension): void;

    /**
     * Register a hook (filter/modify data).
     */
    public function addHook(string $name, callable $callback, int $priority = 10): void;

    /**
     * Apply hooks to filter data.
     */
    public function applyHooks(string $name, mixed $value, mixed ...$args): mixed;

    /**
     * Replace a bound implementation.
     */
    public function replace(string $abstract, string $concrete): void;

    /**
     * Get the container instance.
     */
    public function getContainer(): \Illuminate\Contracts\Container\Container;
}
