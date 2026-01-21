<?php

declare(strict_types=1);

namespace JayI\Cortex\Exceptions;

class PluginException extends CortexException
{
    /**
     * Plugin is already registered.
     */
    public static function alreadyRegistered(string $pluginId): static
    {
        return static::make("Plugin [{$pluginId}] is already registered.")
            ->withContext(['plugin_id' => $pluginId]);
    }

    /**
     * Cannot register plugins after boot.
     */
    public static function alreadyBooted(string $pluginId): static
    {
        return static::make("Cannot register plugin [{$pluginId}] after the plugin manager has booted.")
            ->withContext(['plugin_id' => $pluginId]);
    }

    /**
     * Plugin dependency not found.
     */
    public static function dependencyNotFound(string $pluginId, string $dependencyId): static
    {
        return static::make("Plugin [{$pluginId}] requires [{$dependencyId}] which is not registered.")
            ->withContext([
                'plugin_id' => $pluginId,
                'dependency_id' => $dependencyId,
            ]);
    }

    /**
     * Circular dependency detected.
     */
    public static function circularDependency(string $pluginId): static
    {
        return static::make("Circular dependency detected in plugin [{$pluginId}].")
            ->withContext(['plugin_id' => $pluginId]);
    }

    /**
     * Plugin not found.
     */
    public static function notFound(string $pluginId): static
    {
        return static::make("Plugin [{$pluginId}] is not registered.")
            ->withContext(['plugin_id' => $pluginId]);
    }

    /**
     * Extension point not found.
     */
    public static function extensionPointNotFound(string $name): static
    {
        return static::make("Extension point [{$name}] is not registered.")
            ->withContext(['extension_point' => $name]);
    }

    /**
     * Plugin is disabled.
     */
    public static function disabled(string $pluginId): static
    {
        return static::make("Plugin [{$pluginId}] is disabled.")
            ->withContext(['plugin_id' => $pluginId]);
    }
}
