<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Tool;

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Tool\Contracts\ToolContract;
use JayI\Cortex\Plugins\Tool\Contracts\ToolRegistryContract;
use JayI\Cortex\Support\ExtensionPoint;

class ToolPlugin implements PluginContract
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected Container $container,
        protected array $config = [],
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'tool';
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'Tool Plugin';
    }

    /**
     * {@inheritdoc}
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * {@inheritdoc}
     */
    public function dependencies(): array
    {
        return ['schema'];
    }

    /**
     * {@inheritdoc}
     */
    public function provides(): array
    {
        return ['tools'];
    }

    /**
     * {@inheritdoc}
     */
    public function register(PluginManagerContract $manager): void
    {
        // Bind the tool registry
        $this->container->singleton(ToolRegistryContract::class, function (Container $app) {
            return new ToolRegistry(
                container: $app,
                config: $this->config,
            );
        });

        // Register the tools extension point
        $manager->registerExtensionPoint(
            'tools',
            ExtensionPoint::make('tools', ToolContract::class)
        );

        // Add hook for tool execution
        $manager->addHook('tool.before_execute', fn ($input, $tool, $context) => $input);
        $manager->addHook('tool.after_execute', fn ($result, $tool, $input, $context) => $result);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(PluginManagerContract $manager): void
    {
        $registry = $this->container->make(ToolRegistryContract::class);

        // Register tools from extension point
        $toolsExtensionPoint = $manager->getExtensionPoint('tools');
        if ($toolsExtensionPoint !== null) {
            foreach ($toolsExtensionPoint->all() as $tool) {
                $registry->register($tool);
            }
        }

        // Auto-discover tools if enabled
        if ($this->config['discovery']['enabled'] ?? false) {
            $registry->discover();
        }

        // Register tools from config
        foreach ($this->config['tools'] ?? [] as $toolClass) {
            if (class_exists($toolClass)) {
                $registry->register($toolClass);
            }
        }
    }
}
