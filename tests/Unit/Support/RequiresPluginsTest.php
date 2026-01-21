<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Exceptions\PluginException;
use JayI\Cortex\Plugins\Agent\Agent;
use JayI\Cortex\Plugins\Chat\ChatRequestBuilder;
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolResult;
use JayI\Cortex\Plugins\Workflow\Workflow;
use JayI\Cortex\Support\PluginManager;

beforeEach(function () {
    // Create a fresh container mock
    $this->container = Mockery::mock(Container::class)->shouldIgnoreMissing();

    // Create a plugin manager with no plugins registered
    $this->emptyPluginManager = new PluginManager($this->container);

    // Create a plugin manager with only core plugins
    $this->coreOnlyPluginManager = new PluginManager($this->container);

    // Register mock core plugins
    $schemaPlugin = createMockPlugin('schema', [], ['schema', 'validation']);
    $providerPlugin = createMockPlugin('provider', ['schema'], ['provider', 'llm']);
    $chatPlugin = createMockPlugin('chat', ['provider'], ['chat', 'streaming']);

    $this->coreOnlyPluginManager->register($schemaPlugin);
    $this->coreOnlyPluginManager->register($providerPlugin);
    $this->coreOnlyPluginManager->register($chatPlugin);
    $this->coreOnlyPluginManager->boot();

    // Create a plugin manager with tool plugin
    $this->withToolPluginManager = new PluginManager($this->container);
    $this->withToolPluginManager->register($schemaPlugin);
    $this->withToolPluginManager->register($providerPlugin);
    $this->withToolPluginManager->register($chatPlugin);
    $this->withToolPluginManager->register(createMockPlugin('tool', ['schema'], ['tools']));
    $this->withToolPluginManager->boot();

    // Create a plugin manager with mcp plugin
    $this->withMcpPluginManager = new PluginManager($this->container);
    $this->withMcpPluginManager->register($schemaPlugin);
    $this->withMcpPluginManager->register($providerPlugin);
    $this->withMcpPluginManager->register($chatPlugin);
    $this->withMcpPluginManager->register(createMockPlugin('tool', ['schema'], ['tools']));
    $this->withMcpPluginManager->register(createMockPlugin('mcp', ['tool'], ['mcp']));
    $this->withMcpPluginManager->boot();

    // Create a plugin manager with agent plugin
    $this->withAgentPluginManager = new PluginManager($this->container);
    $this->withAgentPluginManager->register($schemaPlugin);
    $this->withAgentPluginManager->register($providerPlugin);
    $this->withAgentPluginManager->register($chatPlugin);
    $this->withAgentPluginManager->register(createMockPlugin('tool', ['schema'], ['tools']));
    $this->withAgentPluginManager->register(createMockPlugin('agent', ['schema', 'provider', 'chat', 'tool'], ['agents']));
    $this->withAgentPluginManager->boot();
});

describe('Agent cross-plugin methods', function () {
    describe('withTools', function () {
        it('throws exception when tool plugin is disabled', function () {
            app()->instance(PluginManagerContract::class, $this->coreOnlyPluginManager);

            $agent = Agent::make('test-agent');

            expect(fn () => $agent->withTools([]))
                ->toThrow(PluginException::class, 'Plugin [tool] is disabled.');
        });

        it('works when tool plugin is enabled', function () {
            app()->instance(PluginManagerContract::class, $this->withToolPluginManager);

            $agent = Agent::make('test-agent');

            expect(fn () => $agent->withTools([]))->not->toThrow(PluginException::class);
        });
    });

    describe('addTool', function () {
        it('throws exception when tool plugin is disabled', function () {
            app()->instance(PluginManagerContract::class, $this->coreOnlyPluginManager);

            $agent = Agent::make('test-agent');
            $tool = Tool::make('test_tool')
                ->withHandler(fn () => ToolResult::success('done'));

            expect(fn () => $agent->addTool($tool))
                ->toThrow(PluginException::class, 'Plugin [tool] is disabled.');
        });

        it('works when tool plugin is enabled', function () {
            app()->instance(PluginManagerContract::class, $this->withToolPluginManager);

            $agent = Agent::make('test-agent');
            $tool = Tool::make('test_tool')
                ->withHandler(fn () => ToolResult::success('done'));

            expect(fn () => $agent->addTool($tool))->not->toThrow(PluginException::class);
        });
    });

    describe('withMcpServers', function () {
        it('throws exception when mcp plugin is disabled', function () {
            app()->instance(PluginManagerContract::class, $this->coreOnlyPluginManager);

            $agent = Agent::make('test-agent');

            expect(fn () => $agent->withMcpServers([]))
                ->toThrow(PluginException::class, 'Plugin [mcp] is disabled.');
        });

        it('works when mcp plugin is enabled', function () {
            app()->instance(PluginManagerContract::class, $this->withMcpPluginManager);

            $agent = Agent::make('test-agent');

            expect(fn () => $agent->withMcpServers([]))->not->toThrow(PluginException::class);
        });
    });

    describe('addMcpServer', function () {
        it('throws exception when mcp plugin is disabled', function () {
            app()->instance(PluginManagerContract::class, $this->coreOnlyPluginManager);

            $agent = Agent::make('test-agent');

            expect(fn () => $agent->addMcpServer('my-server'))
                ->toThrow(PluginException::class, 'Plugin [mcp] is disabled.');
        });

        it('works when mcp plugin is enabled', function () {
            app()->instance(PluginManagerContract::class, $this->withMcpPluginManager);

            $agent = Agent::make('test-agent');

            expect(fn () => $agent->addMcpServer('my-server'))->not->toThrow(PluginException::class);
        });
    });
});

describe('ChatRequestBuilder cross-plugin methods', function () {
    describe('withTools', function () {
        it('throws exception when tool plugin is disabled', function () {
            app()->instance(PluginManagerContract::class, $this->coreOnlyPluginManager);

            $builder = new ChatRequestBuilder;

            expect(fn () => $builder->withTools([]))
                ->toThrow(PluginException::class, 'Plugin [tool] is disabled.');
        });

        it('works when tool plugin is enabled', function () {
            app()->instance(PluginManagerContract::class, $this->withToolPluginManager);

            $builder = new ChatRequestBuilder;

            expect(fn () => $builder->withTools([]))->not->toThrow(PluginException::class);
        });
    });

    describe('withMcpServers', function () {
        it('throws exception when mcp plugin is disabled', function () {
            app()->instance(PluginManagerContract::class, $this->coreOnlyPluginManager);

            $builder = new ChatRequestBuilder;

            expect(fn () => $builder->withMcpServers([]))
                ->toThrow(PluginException::class, 'Plugin [mcp] is disabled.');
        });

        it('works when mcp plugin is enabled', function () {
            app()->instance(PluginManagerContract::class, $this->withMcpPluginManager);

            $builder = new ChatRequestBuilder;

            expect(fn () => $builder->withMcpServers([]))->not->toThrow(PluginException::class);
        });
    });

    describe('addMcpServer', function () {
        it('throws exception when mcp plugin is disabled', function () {
            app()->instance(PluginManagerContract::class, $this->coreOnlyPluginManager);

            $builder = new ChatRequestBuilder;

            expect(fn () => $builder->addMcpServer('my-server'))
                ->toThrow(PluginException::class, 'Plugin [mcp] is disabled.');
        });

        it('works when mcp plugin is enabled', function () {
            app()->instance(PluginManagerContract::class, $this->withMcpPluginManager);

            $builder = new ChatRequestBuilder;

            expect(fn () => $builder->addMcpServer('my-server'))->not->toThrow(PluginException::class);
        });
    });
});

describe('Workflow cross-plugin methods', function () {
    describe('agent', function () {
        it('throws exception when agent plugin is disabled', function () {
            app()->instance(PluginManagerContract::class, $this->coreOnlyPluginManager);

            $workflow = Workflow::make('test-workflow');

            expect(fn () => $workflow->agent('node-1', 'my-agent'))
                ->toThrow(PluginException::class, 'Plugin [agent] is disabled.');
        });

        it('works when agent plugin is enabled', function () {
            app()->instance(PluginManagerContract::class, $this->withAgentPluginManager);

            $workflow = Workflow::make('test-workflow');

            expect(fn () => $workflow->agent('node-1', 'my-agent'))->not->toThrow(PluginException::class);
        });
    });

    describe('tool', function () {
        it('throws exception when tool plugin is disabled', function () {
            app()->instance(PluginManagerContract::class, $this->coreOnlyPluginManager);

            $workflow = Workflow::make('test-workflow');

            expect(fn () => $workflow->tool('node-1', 'my-tool'))
                ->toThrow(PluginException::class, 'Plugin [tool] is disabled.');
        });

        it('works when tool plugin is enabled', function () {
            app()->instance(PluginManagerContract::class, $this->withToolPluginManager);

            $workflow = Workflow::make('test-workflow');

            expect(fn () => $workflow->tool('node-1', 'my-tool'))->not->toThrow(PluginException::class);
        });
    });
});

// Helper function to create mock plugins
function createMockPlugin(
    string $id,
    array $dependencies = [],
    array $provides = [],
): PluginContract {
    return new class($id, $dependencies, $provides) implements PluginContract
    {
        public function __construct(
            private string $pluginId,
            private array $deps,
            private array $prov,
        ) {}

        public function id(): string
        {
            return $this->pluginId;
        }

        public function name(): string
        {
            return 'Test Plugin: '.$this->pluginId;
        }

        public function version(): string
        {
            return '1.0.0';
        }

        public function dependencies(): array
        {
            return $this->deps;
        }

        public function provides(): array
        {
            return $this->prov;
        }

        public function register(PluginManagerContract $manager): void {}

        public function boot(PluginManagerContract $manager): void {}
    };
}
