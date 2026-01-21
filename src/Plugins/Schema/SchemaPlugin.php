<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Schema;

use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;

class SchemaPlugin implements PluginContract
{
    /**
     * Unique identifier for this plugin.
     */
    public function id(): string
    {
        return 'schema';
    }

    /**
     * Human-readable name.
     */
    public function name(): string
    {
        return 'Schema';
    }

    /**
     * Plugin version.
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * List of plugin IDs this plugin depends on.
     *
     * @return array<string>
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * List of features/capabilities this plugin provides.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return ['schema', 'validation'];
    }

    /**
     * Register bindings, configs, and extension points.
     */
    public function register(PluginManagerContract $manager): void
    {
        // No bindings needed - Schema uses static factory methods
    }

    /**
     * Bootstrap the plugin after all plugins are registered.
     */
    public function boot(PluginManagerContract $manager): void
    {
        // No boot logic needed for Schema plugin
    }
}
