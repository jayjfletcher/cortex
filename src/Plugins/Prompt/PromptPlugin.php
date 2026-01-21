<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Prompt;

use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Prompt\Contracts\PromptRegistryContract;

class PromptPlugin implements PluginContract
{
    public function id(): string
    {
        return 'prompt';
    }

    public function name(): string
    {
        return 'Prompt Plugin';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function provides(): array
    {
        return ['prompt', 'prompt-registry'];
    }

    public function register(PluginManagerContract $manager): void
    {
        $container = $manager->getContainer();

        $container->singleton(PromptRegistryContract::class, PromptRegistry::class);
    }

    public function boot(PluginManagerContract $manager): void
    {
        $container = $manager->getContainer();
        $config = $container->make('config');

        if (! $config->get('cortex.prompt.discovery.enabled', true)) {
            return;
        }

        $registry = $container->make(PromptRegistryContract::class);
        $loader = new FilePromptLoader($registry);

        foreach ($config->get('cortex.prompt.discovery.paths', []) as $path) {
            if (is_string($path)) {
                $loader->loadFromPath($path);
            }
        }
    }
}
