<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Mcp\Contracts\McpRegistryContract;
use JayI\Cortex\Plugins\Mcp\McpPlugin;
use JayI\Cortex\Plugins\Mcp\McpRegistry;
use JayI\Cortex\Support\ExtensionPoint;

describe('McpPlugin', function () {
    it('has correct plugin metadata', function () {
        $plugin = new McpPlugin(app());

        expect($plugin->id())->toBe('mcp');
        expect($plugin->name())->toBe('MCP (Model Context Protocol)');
        expect($plugin->version())->toBe('1.0.0');
        expect($plugin->dependencies())->toBe(['tool']);
        expect($plugin->provides())->toBe(['mcp']);
    });

    it('registers mcp registry as singleton', function () {
        $plugin = new McpPlugin(app());

        $manager = Mockery::mock(PluginManagerContract::class);
        $manager->shouldReceive('registerExtensionPoint')->once();

        $plugin->register($manager);

        $registry1 = app(McpRegistryContract::class);
        $registry2 = app(McpRegistryContract::class);

        expect($registry1)->toBe($registry2);
        expect($registry1)->toBeInstanceOf(McpRegistry::class);
    });

    it('registers mcp_servers extension point', function () {
        $plugin = new McpPlugin(app());

        $manager = Mockery::mock(PluginManagerContract::class);
        $manager->shouldReceive('registerExtensionPoint')
            ->once()
            ->withArgs(function ($name, $extensionPoint) {
                return $name === 'mcp_servers'
                    && $extensionPoint instanceof ExtensionPoint;
            });

        $plugin->register($manager);
    });
});

describe('McpPlugin config-based discovery', function () {
    beforeEach(function () {
        app()->forgetInstance(McpRegistryContract::class);
    });

    it('registers servers from config when discovery is enabled', function () {
        $plugin = new McpPlugin(app(), [
            'discovery' => [
                'enabled' => true,
            ],
            'servers' => [
                'test-http' => [
                    'name' => 'Test HTTP Server',
                    'transport' => 'http',
                    'url' => 'https://example.com/mcp',
                ],
            ],
        ]);

        $extensionPoint = Mockery::mock(ExtensionPoint::class);
        $extensionPoint->shouldReceive('all')->andReturn(collect([]));

        $manager = Mockery::mock(PluginManagerContract::class);
        $manager->shouldReceive('registerExtensionPoint');
        $manager->shouldReceive('getExtensionPoint')
            ->with('mcp_servers')
            ->andReturn($extensionPoint);

        $plugin->register($manager);
        $plugin->boot($manager);

        $registry = app(McpRegistryContract::class);

        expect($registry->has('test-http'))->toBeTrue();
        expect($registry->get('test-http')->name())->toBe('Test HTTP Server');
    });

    it('does not register servers from config when discovery is disabled', function () {
        $plugin = new McpPlugin(app(), [
            'discovery' => [
                'enabled' => false,
            ],
            'servers' => [
                'test-http' => [
                    'name' => 'Test HTTP Server',
                    'transport' => 'http',
                    'url' => 'https://example.com/mcp',
                ],
            ],
        ]);

        $extensionPoint = Mockery::mock(ExtensionPoint::class);
        $extensionPoint->shouldReceive('all')->andReturn(collect([]));

        $manager = Mockery::mock(PluginManagerContract::class);
        $manager->shouldReceive('registerExtensionPoint');
        $manager->shouldReceive('getExtensionPoint')
            ->with('mcp_servers')
            ->andReturn($extensionPoint);

        $plugin->register($manager);
        $plugin->boot($manager);

        $registry = app(McpRegistryContract::class);

        expect($registry->has('test-http'))->toBeFalse();
        expect($registry->all()->isEmpty())->toBeTrue();
    });

    it('enables discovery by default when not specified', function () {
        $plugin = new McpPlugin(app(), [
            'servers' => [
                'test-http' => [
                    'name' => 'Test HTTP Server',
                    'transport' => 'http',
                    'url' => 'https://example.com/mcp',
                ],
            ],
        ]);

        $extensionPoint = Mockery::mock(ExtensionPoint::class);
        $extensionPoint->shouldReceive('all')->andReturn(collect([]));

        $manager = Mockery::mock(PluginManagerContract::class);
        $manager->shouldReceive('registerExtensionPoint');
        $manager->shouldReceive('getExtensionPoint')
            ->with('mcp_servers')
            ->andReturn($extensionPoint);

        $plugin->register($manager);
        $plugin->boot($manager);

        $registry = app(McpRegistryContract::class);

        expect($registry->has('test-http'))->toBeTrue();
    });

    it('registers multiple servers from config', function () {
        $plugin = new McpPlugin(app(), [
            'discovery' => [
                'enabled' => true,
            ],
            'servers' => [
                'server-1' => [
                    'name' => 'Server One',
                    'transport' => 'http',
                    'url' => 'https://one.example.com/mcp',
                ],
                'server-2' => [
                    'name' => 'Server Two',
                    'transport' => 'http',
                    'url' => 'https://two.example.com/mcp',
                ],
            ],
        ]);

        $extensionPoint = Mockery::mock(ExtensionPoint::class);
        $extensionPoint->shouldReceive('all')->andReturn(collect([]));

        $manager = Mockery::mock(PluginManagerContract::class);
        $manager->shouldReceive('registerExtensionPoint');
        $manager->shouldReceive('getExtensionPoint')
            ->with('mcp_servers')
            ->andReturn($extensionPoint);

        $plugin->register($manager);
        $plugin->boot($manager);

        $registry = app(McpRegistryContract::class);

        expect($registry->has('server-1'))->toBeTrue();
        expect($registry->has('server-2'))->toBeTrue();
        expect($registry->all()->count())->toBe(2);
    });

    it('skips servers with unsupported transport', function () {
        $plugin = new McpPlugin(app(), [
            'servers' => [
                'valid-server' => [
                    'name' => 'Valid Server',
                    'transport' => 'http',
                    'url' => 'https://example.com/mcp',
                ],
                'invalid-server' => [
                    'name' => 'Invalid Server',
                    'transport' => 'unsupported',
                ],
            ],
        ]);

        $extensionPoint = Mockery::mock(ExtensionPoint::class);
        $extensionPoint->shouldReceive('all')->andReturn(collect([]));

        $manager = Mockery::mock(PluginManagerContract::class);
        $manager->shouldReceive('registerExtensionPoint');
        $manager->shouldReceive('getExtensionPoint')
            ->with('mcp_servers')
            ->andReturn($extensionPoint);

        $plugin->register($manager);
        $plugin->boot($manager);

        $registry = app(McpRegistryContract::class);

        expect($registry->has('valid-server'))->toBeTrue();
        expect($registry->has('invalid-server'))->toBeFalse();
    });

    it('handles empty servers config', function () {
        $plugin = new McpPlugin(app(), [
            'servers' => [],
        ]);

        $extensionPoint = Mockery::mock(ExtensionPoint::class);
        $extensionPoint->shouldReceive('all')->andReturn(collect([]));

        $manager = Mockery::mock(PluginManagerContract::class);
        $manager->shouldReceive('registerExtensionPoint');
        $manager->shouldReceive('getExtensionPoint')
            ->with('mcp_servers')
            ->andReturn($extensionPoint);

        $plugin->register($manager);
        $plugin->boot($manager);

        $registry = app(McpRegistryContract::class);

        expect($registry->all()->isEmpty())->toBeTrue();
    });

    it('handles missing servers config', function () {
        $plugin = new McpPlugin(app(), []);

        $extensionPoint = Mockery::mock(ExtensionPoint::class);
        $extensionPoint->shouldReceive('all')->andReturn(collect([]));

        $manager = Mockery::mock(PluginManagerContract::class);
        $manager->shouldReceive('registerExtensionPoint');
        $manager->shouldReceive('getExtensionPoint')
            ->with('mcp_servers')
            ->andReturn($extensionPoint);

        $plugin->register($manager);
        $plugin->boot($manager);

        $registry = app(McpRegistryContract::class);

        expect($registry->all()->isEmpty())->toBeTrue();
    });
});

describe('McpPlugin auto_connect', function () {
    beforeEach(function () {
        app()->forgetInstance(McpRegistryContract::class);
    });

    it('does not auto-connect by default', function () {
        Http::fake(); // No requests should be made

        $plugin = new McpPlugin(app(), [
            'servers' => [
                'test-server' => [
                    'transport' => 'http',
                    'url' => 'https://example.com/mcp',
                ],
            ],
        ]);

        $extensionPoint = Mockery::mock(ExtensionPoint::class);
        $extensionPoint->shouldReceive('all')->andReturn(collect([]));

        $manager = Mockery::mock(PluginManagerContract::class);
        $manager->shouldReceive('registerExtensionPoint');
        $manager->shouldReceive('getExtensionPoint')
            ->with('mcp_servers')
            ->andReturn($extensionPoint);

        $plugin->register($manager);
        $plugin->boot($manager);

        $registry = app(McpRegistryContract::class);

        expect($registry->get('test-server')->isConnected())->toBeFalse();
        Http::assertNothingSent();
    });

    it('auto-connects when enabled', function () {
        Http::fake([
            '*' => Http::sequence()
                ->push(['jsonrpc' => '2.0', 'id' => 1, 'result' => []])
                ->push(['jsonrpc' => '2.0', 'id' => 2])
                ->push(['jsonrpc' => '2.0', 'id' => 3, 'result' => ['tools' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 4, 'result' => ['resources' => []]])
                ->push(['jsonrpc' => '2.0', 'id' => 5, 'result' => ['prompts' => []]]),
        ]);

        $plugin = new McpPlugin(app(), [
            'auto_connect' => true,
            'servers' => [
                'test-server' => [
                    'transport' => 'http',
                    'url' => 'https://example.com/mcp',
                ],
            ],
        ]);

        $extensionPoint = Mockery::mock(ExtensionPoint::class);
        $extensionPoint->shouldReceive('all')->andReturn(collect([]));

        $manager = Mockery::mock(PluginManagerContract::class);
        $manager->shouldReceive('registerExtensionPoint');
        $manager->shouldReceive('getExtensionPoint')
            ->with('mcp_servers')
            ->andReturn($extensionPoint);

        $plugin->register($manager);
        $plugin->boot($manager);

        $registry = app(McpRegistryContract::class);

        expect($registry->get('test-server')->isConnected())->toBeTrue();
    });
});

describe('McpPlugin extension point', function () {
    beforeEach(function () {
        app()->forgetInstance(McpRegistryContract::class);
    });

    it('registers servers from extension point', function () {
        $mockServer = Mockery::mock(\JayI\Cortex\Plugins\Mcp\Contracts\McpServerContract::class);
        $mockServer->shouldReceive('id')->andReturn('extension-server');

        $extensionPoint = Mockery::mock(ExtensionPoint::class);
        $extensionPoint->shouldReceive('all')->andReturn(collect([$mockServer]));

        $manager = Mockery::mock(PluginManagerContract::class);
        $manager->shouldReceive('registerExtensionPoint');
        $manager->shouldReceive('getExtensionPoint')
            ->with('mcp_servers')
            ->andReturn($extensionPoint);

        $plugin = new McpPlugin(app(), []);
        $plugin->register($manager);
        $plugin->boot($manager);

        $registry = app(McpRegistryContract::class);

        expect($registry->has('extension-server'))->toBeTrue();
    });

    it('registers servers from both config and extension point', function () {
        $mockServer = Mockery::mock(\JayI\Cortex\Plugins\Mcp\Contracts\McpServerContract::class);
        $mockServer->shouldReceive('id')->andReturn('extension-server');

        $extensionPoint = Mockery::mock(ExtensionPoint::class);
        $extensionPoint->shouldReceive('all')->andReturn(collect([$mockServer]));

        $manager = Mockery::mock(PluginManagerContract::class);
        $manager->shouldReceive('registerExtensionPoint');
        $manager->shouldReceive('getExtensionPoint')
            ->with('mcp_servers')
            ->andReturn($extensionPoint);

        $plugin = new McpPlugin(app(), [
            'servers' => [
                'config-server' => [
                    'transport' => 'http',
                    'url' => 'https://example.com/mcp',
                ],
            ],
        ]);
        $plugin->register($manager);
        $plugin->boot($manager);

        $registry = app(McpRegistryContract::class);

        expect($registry->has('config-server'))->toBeTrue();
        expect($registry->has('extension-server'))->toBeTrue();
        expect($registry->all()->count())->toBe(2);
    });

    it('extension point servers are registered even when config discovery is disabled', function () {
        $mockServer = Mockery::mock(\JayI\Cortex\Plugins\Mcp\Contracts\McpServerContract::class);
        $mockServer->shouldReceive('id')->andReturn('extension-server');

        $extensionPoint = Mockery::mock(ExtensionPoint::class);
        $extensionPoint->shouldReceive('all')->andReturn(collect([$mockServer]));

        $manager = Mockery::mock(PluginManagerContract::class);
        $manager->shouldReceive('registerExtensionPoint');
        $manager->shouldReceive('getExtensionPoint')
            ->with('mcp_servers')
            ->andReturn($extensionPoint);

        $plugin = new McpPlugin(app(), [
            'discovery' => [
                'enabled' => false,
            ],
            'servers' => [
                'config-server' => [
                    'transport' => 'http',
                    'url' => 'https://example.com/mcp',
                ],
            ],
        ]);
        $plugin->register($manager);
        $plugin->boot($manager);

        $registry = app(McpRegistryContract::class);

        // Config server should NOT be registered (discovery disabled)
        expect($registry->has('config-server'))->toBeFalse();
        // Extension point server should still be registered
        expect($registry->has('extension-server'))->toBeTrue();
        expect($registry->all()->count())->toBe(1);
    });
});
