<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Chat\Broadcasting\BroadcasterContract;
use JayI\Cortex\Plugins\Chat\ChatPlugin;
use JayI\Cortex\Plugins\Chat\Contracts\ChatClientContract;

describe('ChatPlugin', function () {
    it('implements PluginContract', function () {
        $container = Mockery::mock(Container::class);
        $plugin = new ChatPlugin($container);

        expect($plugin)->toBeInstanceOf(PluginContract::class);
    });

    it('has correct id', function () {
        $container = Mockery::mock(Container::class);
        $plugin = new ChatPlugin($container);

        expect($plugin->id())->toBe('chat');
    });

    it('has correct name', function () {
        $container = Mockery::mock(Container::class);
        $plugin = new ChatPlugin($container);

        expect($plugin->name())->toBe('Chat');
    });

    it('has correct version', function () {
        $container = Mockery::mock(Container::class);
        $plugin = new ChatPlugin($container);

        expect($plugin->version())->toBe('1.0.0');
    });

    it('depends on provider plugin', function () {
        $container = Mockery::mock(Container::class);
        $plugin = new ChatPlugin($container);

        expect($plugin->dependencies())->toBe(['provider']);
    });

    it('provides chat capabilities', function () {
        $container = Mockery::mock(Container::class);
        $plugin = new ChatPlugin($container);

        expect($plugin->provides())->toContain('chat');
        expect($plugin->provides())->toContain('streaming');
        expect($plugin->provides())->toContain('broadcasting');
    });

    it('registers chat client in container', function () {
        $container = Mockery::mock(Container::class);
        $manager = Mockery::mock(PluginManagerContract::class);

        $container->shouldReceive('singleton')
            ->with(ChatClientContract::class, Mockery::type('Closure'))
            ->once();

        $container->shouldReceive('singleton')
            ->with(BroadcasterContract::class, Mockery::type('Closure'))
            ->once();

        $plugin = new ChatPlugin($container);
        $plugin->register($manager);
    });

    it('boots with config options', function () {
        $container = Mockery::mock(Container::class);
        $config = Mockery::mock();
        $manager = Mockery::mock(PluginManagerContract::class);

        $container->shouldReceive('make')
            ->with('config')
            ->andReturn($config);

        $config->shouldReceive('get')
            ->with('cortex.chat', [])
            ->andReturn([]);

        $plugin = new ChatPlugin($container);
        $plugin->boot($manager);

        expect(true)->toBeTrue(); // Assert to avoid risky test
    });
});
