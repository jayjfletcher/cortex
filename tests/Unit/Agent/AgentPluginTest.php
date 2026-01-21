<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Agent\Agent;
use JayI\Cortex\Plugins\Agent\AgentPlugin;
use JayI\Cortex\Plugins\Agent\AgentRegistry;
use JayI\Cortex\Plugins\Agent\Contracts\AgentLoopContract;
use JayI\Cortex\Plugins\Agent\Contracts\AgentRegistryContract;
use JayI\Cortex\Plugins\Agent\Loops\SimpleAgentLoop;
use JayI\Cortex\Support\ExtensionPoint;

describe('AgentPlugin', function () {
    it('returns correct plugin id', function () {
        $container = Mockery::mock(Container::class);
        $plugin = new AgentPlugin($container);

        expect($plugin->id())->toBe('agent');
    });

    it('returns correct plugin name', function () {
        $container = Mockery::mock(Container::class);
        $plugin = new AgentPlugin($container);

        expect($plugin->name())->toBe('Agent');
    });

    it('returns correct plugin version', function () {
        $container = Mockery::mock(Container::class);
        $plugin = new AgentPlugin($container);

        expect($plugin->version())->toBe('1.0.0');
    });

    it('returns correct dependencies', function () {
        $container = Mockery::mock(Container::class);
        $plugin = new AgentPlugin($container);

        expect($plugin->dependencies())->toBe(['schema', 'provider', 'chat', 'tool']);
    });

    it('returns correct provides', function () {
        $container = Mockery::mock(Container::class);
        $plugin = new AgentPlugin($container);

        expect($plugin->provides())->toBe(['agents']);
    });

    it('registers container bindings', function () {
        $container = Mockery::mock(Container::class);
        $manager = Mockery::mock(PluginManagerContract::class);

        $container->shouldReceive('singleton')
            ->once()
            ->with(AgentRegistryContract::class, Mockery::type('callable'));

        $container->shouldReceive('bind')
            ->once()
            ->with(AgentLoopContract::class, SimpleAgentLoop::class);

        $container->shouldReceive('bind')
            ->once()
            ->with(SimpleAgentLoop::class);

        $manager->shouldReceive('registerExtensionPoint')
            ->once()
            ->with('agents', Mockery::type(ExtensionPoint::class));

        $plugin = new AgentPlugin($container);
        $plugin->register($manager);
    });

    it('boots and registers agents from extension point', function () {
        $container = Mockery::mock(Container::class);
        $manager = Mockery::mock(PluginManagerContract::class);
        $registry = Mockery::mock(AgentRegistryContract::class);
        $extensionPoint = Mockery::mock(ExtensionPoint::class);

        $agent = Agent::make('test-agent');

        $manager->shouldReceive('getExtensionPoint')
            ->once()
            ->with('agents')
            ->andReturn($extensionPoint);

        $extensionPoint->shouldReceive('all')
            ->once()
            ->andReturn(new Collection([$agent]));

        $container->shouldReceive('make')
            ->once()
            ->with(AgentRegistryContract::class)
            ->andReturn($registry);

        $registry->shouldReceive('register')
            ->once()
            ->with($agent);

        $plugin = new AgentPlugin($container);
        $plugin->boot($manager);
    });

    it('boots with discovery enabled', function () {
        $container = Mockery::mock(Container::class);
        $manager = Mockery::mock(PluginManagerContract::class);
        $registry = Mockery::mock(AgentRegistryContract::class);
        $extensionPoint = Mockery::mock(ExtensionPoint::class);

        $config = [
            'discovery' => [
                'enabled' => true,
                'paths' => ['/path/to/agents'],
            ],
        ];

        $manager->shouldReceive('getExtensionPoint')
            ->once()
            ->with('agents')
            ->andReturn($extensionPoint);

        $extensionPoint->shouldReceive('all')
            ->once()
            ->andReturn(new Collection([]));

        $container->shouldReceive('make')
            ->once()
            ->with(AgentRegistryContract::class)
            ->andReturn($registry);

        $registry->shouldReceive('discover')
            ->once()
            ->with(['/path/to/agents']);

        $plugin = new AgentPlugin($container, $config);
        $plugin->boot($manager);
    });

    it('boots without discovery when disabled', function () {
        $container = Mockery::mock(Container::class);
        $manager = Mockery::mock(PluginManagerContract::class);
        $registry = Mockery::mock(AgentRegistryContract::class);
        $extensionPoint = Mockery::mock(ExtensionPoint::class);

        $config = [
            'discovery' => [
                'enabled' => false,
            ],
        ];

        $manager->shouldReceive('getExtensionPoint')
            ->once()
            ->with('agents')
            ->andReturn($extensionPoint);

        $extensionPoint->shouldReceive('all')
            ->once()
            ->andReturn(new Collection([]));

        $container->shouldReceive('make')
            ->once()
            ->with(AgentRegistryContract::class)
            ->andReturn($registry);

        // Should NOT call discover
        $registry->shouldNotReceive('discover');

        $plugin = new AgentPlugin($container, $config);
        $plugin->boot($manager);
    });
});
