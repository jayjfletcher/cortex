<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Tool\Contracts\ToolRegistryContract;
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolPlugin;
use JayI\Cortex\Plugins\Tool\ToolResult;
use JayI\Cortex\Support\ExtensionPoint;

describe('ToolPlugin', function () {
    it('returns correct plugin id', function () {
        $container = Mockery::mock(Container::class);
        $plugin = new ToolPlugin($container);

        expect($plugin->id())->toBe('tool');
    });

    it('returns correct plugin name', function () {
        $container = Mockery::mock(Container::class);
        $plugin = new ToolPlugin($container);

        expect($plugin->name())->toBe('Tool Plugin');
    });

    it('returns correct plugin version', function () {
        $container = Mockery::mock(Container::class);
        $plugin = new ToolPlugin($container);

        expect($plugin->version())->toBe('1.0.0');
    });

    it('returns correct dependencies', function () {
        $container = Mockery::mock(Container::class);
        $plugin = new ToolPlugin($container);

        expect($plugin->dependencies())->toBe(['schema']);
    });

    it('returns correct provides', function () {
        $container = Mockery::mock(Container::class);
        $plugin = new ToolPlugin($container);

        expect($plugin->provides())->toBe(['tools']);
    });

    it('registers container bindings and extension point', function () {
        $container = Mockery::mock(Container::class);
        $manager = Mockery::mock(PluginManagerContract::class);

        $container->shouldReceive('singleton')
            ->once()
            ->with(ToolRegistryContract::class, Mockery::type('callable'));

        $manager->shouldReceive('registerExtensionPoint')
            ->once()
            ->with('tools', Mockery::type(ExtensionPoint::class));

        $manager->shouldReceive('addHook')
            ->once()
            ->with('tool.before_execute', Mockery::type('callable'));

        $manager->shouldReceive('addHook')
            ->once()
            ->with('tool.after_execute', Mockery::type('callable'));

        $plugin = new ToolPlugin($container);
        $plugin->register($manager);
    });

    it('boots and registers tools from extension point', function () {
        $container = Mockery::mock(Container::class);
        $manager = Mockery::mock(PluginManagerContract::class);
        $registry = Mockery::mock(ToolRegistryContract::class);
        $extensionPoint = Mockery::mock(ExtensionPoint::class);

        $tool = Tool::make('test_tool')->withHandler(fn () => ToolResult::success('ok'));

        $container->shouldReceive('make')
            ->once()
            ->with(ToolRegistryContract::class)
            ->andReturn($registry);

        $manager->shouldReceive('getExtensionPoint')
            ->once()
            ->with('tools')
            ->andReturn($extensionPoint);

        $extensionPoint->shouldReceive('all')
            ->once()
            ->andReturn(new Collection([$tool]));

        $registry->shouldReceive('register')
            ->once()
            ->with($tool);

        $plugin = new ToolPlugin($container);
        $plugin->boot($manager);
    });

    it('boots with null extension point', function () {
        $container = Mockery::mock(Container::class);
        $manager = Mockery::mock(PluginManagerContract::class);
        $registry = Mockery::mock(ToolRegistryContract::class);

        $container->shouldReceive('make')
            ->once()
            ->with(ToolRegistryContract::class)
            ->andReturn($registry);

        $manager->shouldReceive('getExtensionPoint')
            ->once()
            ->with('tools')
            ->andReturn(null);

        // Should not call register on registry
        $registry->shouldNotReceive('register');

        $plugin = new ToolPlugin($container);
        $plugin->boot($manager);
    });

    it('boots with discovery enabled', function () {
        $container = Mockery::mock(Container::class);
        $manager = Mockery::mock(PluginManagerContract::class);
        $registry = Mockery::mock(ToolRegistryContract::class);
        $extensionPoint = Mockery::mock(ExtensionPoint::class);

        $config = [
            'discovery' => [
                'enabled' => true,
            ],
        ];

        $container->shouldReceive('make')
            ->once()
            ->with(ToolRegistryContract::class)
            ->andReturn($registry);

        $manager->shouldReceive('getExtensionPoint')
            ->once()
            ->with('tools')
            ->andReturn($extensionPoint);

        $extensionPoint->shouldReceive('all')
            ->once()
            ->andReturn(new Collection([]));

        $registry->shouldReceive('discover')
            ->once();

        $plugin = new ToolPlugin($container, $config);
        $plugin->boot($manager);
    });

    it('boots and registers tools from config', function () {
        $container = Mockery::mock(Container::class);
        $manager = Mockery::mock(PluginManagerContract::class);
        $registry = Mockery::mock(ToolRegistryContract::class);
        $extensionPoint = Mockery::mock(ExtensionPoint::class);

        // Create a fake tool class name that exists
        $config = [
            'tools' => [
                Tool::class, // Using the Tool class as it exists
            ],
        ];

        $container->shouldReceive('make')
            ->once()
            ->with(ToolRegistryContract::class)
            ->andReturn($registry);

        $manager->shouldReceive('getExtensionPoint')
            ->once()
            ->with('tools')
            ->andReturn($extensionPoint);

        $extensionPoint->shouldReceive('all')
            ->once()
            ->andReturn(new Collection([]));

        $registry->shouldReceive('register')
            ->once()
            ->with(Tool::class);

        $plugin = new ToolPlugin($container, $config);
        $plugin->boot($manager);
    });

    it('skips non-existent tool classes from config', function () {
        $container = Mockery::mock(Container::class);
        $manager = Mockery::mock(PluginManagerContract::class);
        $registry = Mockery::mock(ToolRegistryContract::class);
        $extensionPoint = Mockery::mock(ExtensionPoint::class);

        $config = [
            'tools' => [
                'NonExistentToolClass',
            ],
        ];

        $container->shouldReceive('make')
            ->once()
            ->with(ToolRegistryContract::class)
            ->andReturn($registry);

        $manager->shouldReceive('getExtensionPoint')
            ->once()
            ->with('tools')
            ->andReturn($extensionPoint);

        $extensionPoint->shouldReceive('all')
            ->once()
            ->andReturn(new Collection([]));

        // Should NOT call register for non-existent class
        $registry->shouldNotReceive('register');

        $plugin = new ToolPlugin($container, $config);
        $plugin->boot($manager);
    });
});
