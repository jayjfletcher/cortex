<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent;

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Agent\Contracts\AgentLoopContract;
use JayI\Cortex\Plugins\Agent\Contracts\AgentRegistryContract;
use JayI\Cortex\Plugins\Agent\Loops\SimpleAgentLoop;
use JayI\Cortex\Support\ExtensionPoint;

class AgentPlugin implements PluginContract
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
        return 'agent';
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'Agent';
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
        return ['schema', 'provider', 'chat', 'tool'];
    }

    /**
     * {@inheritdoc}
     */
    public function provides(): array
    {
        return ['agents'];
    }

    /**
     * {@inheritdoc}
     */
    public function register(PluginManagerContract $manager): void
    {
        // Register agent registry
        $this->container->singleton(AgentRegistryContract::class, function () {
            return new AgentRegistry();
        });

        // Register default loop
        $this->container->bind(AgentLoopContract::class, SimpleAgentLoop::class);
        $this->container->bind(SimpleAgentLoop::class);

        // Register extension point for agents
        $manager->registerExtensionPoint(
            'agents',
            ExtensionPoint::make('agents', Contracts\AgentContract::class)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function boot(PluginManagerContract $manager): void
    {
        // Register agents from extension point
        $extensionPoint = $manager->getExtensionPoint('agents');
        $registry = $this->container->make(AgentRegistryContract::class);

        foreach ($extensionPoint->all() as $agent) {
            $registry->register($agent);
        }

        // Discover agents from configured paths
        if ($this->config['discovery']['enabled'] ?? false) {
            $paths = $this->config['discovery']['paths'] ?? [];
            $registry->discover($paths);
        }
    }
}
