<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Mcp\Contracts\McpRegistryContract;
use JayI\Cortex\Plugins\Mcp\McpPlugin;
use JayI\Cortex\Plugins\Mcp\McpRegistry;
use JayI\Cortex\Plugins\Mcp\McpTransport;
use JayI\Cortex\Plugins\Mcp\Servers\StdioMcpServer;
use JayI\Cortex\Plugins\Usage\BudgetManager;
use JayI\Cortex\Plugins\Usage\Contracts\BudgetManagerContract;
use JayI\Cortex\Plugins\Usage\Contracts\CostEstimatorContract;
use JayI\Cortex\Plugins\Usage\Contracts\UsageTrackerContract;
use JayI\Cortex\Plugins\Usage\Data\Budget;
use JayI\Cortex\Plugins\Usage\Data\BudgetPeriod;
use JayI\Cortex\Plugins\Usage\Estimators\AnthropicCostEstimator;
use JayI\Cortex\Plugins\Usage\InMemoryUsageTracker;
use JayI\Cortex\Plugins\Usage\UsagePlugin;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowExecutorContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowRegistryContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowStateRepositoryContract;
use JayI\Cortex\Plugins\Workflow\PersistentWorkflowExecutor;
use JayI\Cortex\Plugins\Workflow\Repositories\CacheWorkflowStateRepository;
use JayI\Cortex\Plugins\Workflow\Repositories\DatabaseWorkflowStateRepository;
use JayI\Cortex\Plugins\Workflow\WorkflowExecutor;
use JayI\Cortex\Plugins\Workflow\WorkflowPlugin;
use JayI\Cortex\Plugins\Workflow\WorkflowRegistry;
use JayI\Cortex\Support\ExtensionPoint;

describe('UsagePlugin', function () {
    beforeEach(function () {
        $this->pluginManager = Mockery::mock(PluginManagerContract::class);
    });

    test('returns correct id', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new UsagePlugin($container);
        expect($plugin->id())->toBe('usage');
    });

    test('returns correct name', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new UsagePlugin($container);
        expect($plugin->name())->toBe('Usage');
    });

    test('returns correct version', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new UsagePlugin($container);
        expect($plugin->version())->toBe('1.0.0');
    });

    test('has no dependencies', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new UsagePlugin($container);
        expect($plugin->dependencies())->toBe([]);
    });

    test('provides usage capability', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new UsagePlugin($container);
        expect($plugin->provides())->toBe(['usage']);
    });

    test('registers usage services', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new UsagePlugin($container);
        $plugin->register($this->pluginManager);

        expect($container->bound(CostEstimatorContract::class))->toBeTrue();
        expect($container->bound(UsageTrackerContract::class))->toBeTrue();
        expect($container->bound(BudgetManagerContract::class))->toBeTrue();
    });

    test('creates in-memory usage tracker by default', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new UsagePlugin($container);
        $plugin->register($this->pluginManager);

        $tracker = $container->make(UsageTrackerContract::class);
        expect($tracker)->toBeInstanceOf(InMemoryUsageTracker::class);
    });

    test('creates cost estimator with custom pricing', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new UsagePlugin($container, [
            'pricing' => [
                'custom-model' => ['input' => 0.01, 'output' => 0.03],
            ],
        ]);
        $plugin->register($this->pluginManager);

        $estimator = $container->make(CostEstimatorContract::class);
        expect($estimator)->toBeInstanceOf(AnthropicCostEstimator::class);
    });

    test('boots with cost-based budget from config', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new UsagePlugin($container, [
            'budgets' => [
                ['max_cost' => 100.0, 'period' => 'monthly'],
            ],
        ]);
        $plugin->register($this->pluginManager);
        $plugin->boot($this->pluginManager);

        $budgetManager = $container->make(BudgetManagerContract::class);
        expect($budgetManager)->toBeInstanceOf(BudgetManager::class);
    });

    test('boots with token-based budget from config', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new UsagePlugin($container, [
            'budgets' => [
                ['max_tokens' => 1000000, 'period' => 'daily'],
            ],
        ]);
        $plugin->register($this->pluginManager);
        $plugin->boot($this->pluginManager);

        $budgetManager = $container->make(BudgetManagerContract::class);
        expect($budgetManager)->toBeInstanceOf(BudgetManager::class);
    });

    test('boots with request-based budget from config', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new UsagePlugin($container, [
            'budgets' => [
                ['max_requests' => 100, 'period' => 'hourly'],
            ],
        ]);
        $plugin->register($this->pluginManager);
        $plugin->boot($this->pluginManager);

        $budgetManager = $container->make(BudgetManagerContract::class);
        expect($budgetManager)->toBeInstanceOf(BudgetManager::class);
    });

    test('handles invalid budget config gracefully', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new UsagePlugin($container, [
            'budgets' => [
                ['invalid' => 'config'],
            ],
        ]);
        $plugin->register($this->pluginManager);
        $plugin->boot($this->pluginManager);

        expect(true)->toBeTrue();
    });
});

describe('McpPlugin', function () {
    beforeEach(function () {
        $this->pluginManager = Mockery::mock(PluginManagerContract::class);
        $this->pluginManager->shouldReceive('registerExtensionPoint')->andReturnNull();
        $this->pluginManager->shouldReceive('getExtensionPoint')->andReturn(
            ExtensionPoint::make('mcp_servers', \JayI\Cortex\Plugins\Mcp\Contracts\McpServerContract::class)
        );
    });

    test('returns correct id', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new McpPlugin($container);
        expect($plugin->id())->toBe('mcp');
    });

    test('returns correct name', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new McpPlugin($container);
        expect($plugin->name())->toBe('MCP (Model Context Protocol)');
    });

    test('returns correct version', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new McpPlugin($container);
        expect($plugin->version())->toBe('1.0.0');
    });

    test('depends on tool', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new McpPlugin($container);
        expect($plugin->dependencies())->toBe(['tool']);
    });

    test('provides mcp capability', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new McpPlugin($container);
        expect($plugin->provides())->toBe(['mcp']);
    });

    test('registers mcp services', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new McpPlugin($container);
        $plugin->register($this->pluginManager);

        expect($container->bound(McpRegistryContract::class))->toBeTrue();
    });

    test('creates mcp registry', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new McpPlugin($container);
        $plugin->register($this->pluginManager);

        $registry = $container->make(McpRegistryContract::class);
        expect($registry)->toBeInstanceOf(McpRegistry::class);
    });

    test('boots with stdio server from config', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new McpPlugin($container, [
            'servers' => [
                'test-server' => [
                    'transport' => 'stdio',
                    'command' => '/usr/bin/echo',
                    'args' => ['hello'],
                ],
            ],
        ]);
        $plugin->register($this->pluginManager);
        $plugin->boot($this->pluginManager);

        $registry = $container->make(McpRegistryContract::class);
        expect($registry->has('test-server'))->toBeTrue();
    });

    test('boots with http server from config', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new McpPlugin($container, [
            'servers' => [
                'http-server' => [
                    'transport' => 'http',
                    'url' => 'http://localhost:3000',
                ],
            ],
        ]);
        $plugin->register($this->pluginManager);
        $plugin->boot($this->pluginManager);

        $registry = $container->make(McpRegistryContract::class);
        expect($registry->has('http-server'))->toBeTrue();
    });

    test('ignores unsupported transport types', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new McpPlugin($container, [
            'servers' => [
                'sse-server' => [
                    'transport' => 'sse',
                    'url' => 'http://localhost:3000',
                ],
            ],
        ]);
        $plugin->register($this->pluginManager);
        $plugin->boot($this->pluginManager);

        $registry = $container->make(McpRegistryContract::class);
        expect($registry->has('sse-server'))->toBeFalse();
    });
});

describe('WorkflowPlugin', function () {
    beforeEach(function () {
        $this->pluginManager = Mockery::mock(PluginManagerContract::class);
        $this->pluginManager->shouldReceive('registerExtensionPoint')->andReturnNull();
        $this->pluginManager->shouldReceive('getExtensionPoint')->andReturn(
            ExtensionPoint::make('workflows', WorkflowContract::class)
        );
    });

    test('returns correct id', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new WorkflowPlugin($container);
        expect($plugin->id())->toBe('workflow');
    });

    test('returns correct name', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new WorkflowPlugin($container);
        expect($plugin->name())->toBe('Workflow');
    });

    test('returns correct version', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new WorkflowPlugin($container);
        expect($plugin->version())->toBe('1.0.0');
    });

    test('has correct dependencies', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new WorkflowPlugin($container);
        expect($plugin->dependencies())->toBe(['schema', 'provider', 'chat', 'tool', 'agent']);
    });

    test('provides workflows capability', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new WorkflowPlugin($container);
        expect($plugin->provides())->toBe(['workflows']);
    });

    test('registers workflow services', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new WorkflowPlugin($container);
        $plugin->register($this->pluginManager);

        expect($container->bound(WorkflowRegistryContract::class))->toBeTrue();
        expect($container->bound(WorkflowStateRepositoryContract::class))->toBeTrue();
        expect($container->bound(WorkflowExecutorContract::class))->toBeTrue();
    });

    test('creates workflow registry', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new WorkflowPlugin($container);
        $plugin->register($this->pluginManager);

        $registry = $container->make(WorkflowRegistryContract::class);
        expect($registry)->toBeInstanceOf(WorkflowRegistry::class);
    });

    test('creates database state repository by default', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new WorkflowPlugin($container);
        $plugin->register($this->pluginManager);

        $repository = $container->make(WorkflowStateRepositoryContract::class);
        expect($repository)->toBeInstanceOf(DatabaseWorkflowStateRepository::class);
    });

    test('creates cache state repository when configured', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new WorkflowPlugin($container, [
            'persistence' => ['driver' => 'cache'],
        ]);
        $plugin->register($this->pluginManager);

        $repository = $container->make(WorkflowStateRepositoryContract::class);
        expect($repository)->toBeInstanceOf(CacheWorkflowStateRepository::class);
    });

    test('creates persistent executor', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new WorkflowPlugin($container);
        $plugin->register($this->pluginManager);

        $executor = $container->make(WorkflowExecutorContract::class);
        expect($executor)->toBeInstanceOf(PersistentWorkflowExecutor::class);
    });

    test('applies max_steps configuration', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new WorkflowPlugin($container, [
            'max_steps' => 50,
        ]);
        $plugin->register($this->pluginManager);

        $executor = $container->make(WorkflowExecutorContract::class);
        expect($executor)->toBeInstanceOf(PersistentWorkflowExecutor::class);
    });

    test('boots with empty config', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new WorkflowPlugin($container);
        $plugin->register($this->pluginManager);
        $plugin->boot($this->pluginManager);

        expect(true)->toBeTrue();
    });
});

describe('StdioMcpServer', function () {
    test('creates from config', function () {
        $server = StdioMcpServer::fromConfig('test', [
            'name' => 'Test Server',
            'command' => '/bin/echo',
            'args' => ['hello'],
            'cwd' => '/tmp',
            'env' => ['FOO' => 'bar'],
        ]);

        expect($server->id())->toBe('test');
        expect($server->name())->toBe('Test Server');
        expect($server->transport())->toBe(McpTransport::Stdio);
    });

    test('creates with default name from id', function () {
        $server = StdioMcpServer::fromConfig('my-server', [
            'command' => '/bin/echo',
        ]);

        expect($server->name())->toBe('my-server');
    });

    test('is not connected by default', function () {
        $server = new StdioMcpServer(
            'test',
            'Test',
            '/bin/echo',
        );

        expect($server->isConnected())->toBeFalse();
    });
});
