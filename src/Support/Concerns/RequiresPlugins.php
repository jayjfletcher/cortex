<?php

declare(strict_types=1);

namespace JayI\Cortex\Support\Concerns;

use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Exceptions\PluginException;

trait RequiresPlugins
{
    /**
     * Ensure a plugin is enabled.
     *
     * @throws PluginException
     */
    protected function ensurePluginEnabled(string $pluginId): void
    {
        $pluginManager = app(PluginManagerContract::class);

        if (! $pluginManager->has($pluginId)) {
            throw PluginException::disabled($pluginId);
        }
    }
}
