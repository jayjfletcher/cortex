<?php

declare(strict_types=1);

namespace JayI\Cortex\Contracts;

interface PluginContract
{
    /**
     * Unique identifier for this plugin.
     */
    public function id(): string;

    /**
     * Human-readable name.
     */
    public function name(): string;

    /**
     * Plugin version.
     */
    public function version(): string;

    /**
     * List of plugin IDs this plugin depends on.
     *
     * @return array<string>
     */
    public function dependencies(): array;

    /**
     * List of features/capabilities this plugin provides.
     *
     * @return array<string>
     */
    public function provides(): array;

    /**
     * Register bindings, configs, and extension points.
     * Called before boot, during container setup.
     */
    public function register(PluginManagerContract $manager): void;

    /**
     * Bootstrap the plugin after all plugins are registered.
     * Safe to resolve dependencies here.
     */
    public function boot(PluginManagerContract $manager): void;
}
