<?php

declare(strict_types=1);

use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Agent\Agent;
use JayI\Cortex\Plugins\Agent\AgentContext;
use JayI\Cortex\Plugins\Agent\AgentLoopStrategy;
use JayI\Cortex\Plugins\Agent\Memory\BufferMemory;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Mcp\Contracts\McpServerContract;
use JayI\Cortex\Plugins\Mcp\McpServerCollection;
use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolCollection;
use JayI\Cortex\Plugins\Tool\ToolResult;

beforeEach(function () {
    // Mock the plugin manager to allow cross-plugin methods
    $pluginManager = Mockery::mock(PluginManagerContract::class);
    $pluginManager->shouldReceive('has')->andReturn(true);
    app()->instance(PluginManagerContract::class, $pluginManager);
});

describe('Agent', function () {
    it('creates an agent with fluent builder', function () {
        $agent = Agent::make('test-agent')
            ->withName('Test Agent')
            ->withDescription('A test agent')
            ->withSystemPrompt('You are a helpful assistant.')
            ->withMaxIterations(5);

        expect($agent->id())->toBe('test-agent');
        expect($agent->name())->toBe('Test Agent');
        expect($agent->description())->toBe('A test agent');
        expect($agent->systemPrompt())->toBe('You are a helpful assistant.');
        expect($agent->maxIterations())->toBe(5);
    });

    it('defaults name to id if not set', function () {
        $agent = Agent::make('my-agent');

        expect($agent->name())->toBe('my-agent');
    });

    it('configures tools', function () {
        $tool = Tool::make('test_tool')
            ->withDescription('A test tool')
            ->withInput(Schema::object())
            ->withHandler(fn () => ToolResult::success('done'));

        $agent = Agent::make('agent')
            ->withTools([$tool]);

        expect($agent->tools()->count())->toBe(1);
        expect($agent->tools()->has('test_tool'))->toBeTrue();
    });

    it('adds individual tools', function () {
        $tool1 = Tool::make('tool1')
            ->withHandler(fn () => ToolResult::success('1'));

        $tool2 = Tool::make('tool2')
            ->withHandler(fn () => ToolResult::success('2'));

        $agent = Agent::make('agent')
            ->addTool($tool1)
            ->addTool($tool2);

        expect($agent->tools()->count())->toBe(2);
    });

    it('configures model and provider', function () {
        $agent = Agent::make('agent')
            ->withModel('claude-3-sonnet')
            ->withProvider('bedrock');

        expect($agent->model())->toBe('claude-3-sonnet');
        expect($agent->provider())->toBe('bedrock');
    });

    it('configures memory', function () {
        $memory = new BufferMemory;

        $agent = Agent::make('agent')
            ->withMemory($memory);

        expect($agent->memory())->toBe($memory);
    });

    it('configures loop strategy', function () {
        $agent = Agent::make('agent')
            ->withLoopStrategy(AgentLoopStrategy::ReAct);

        // We can't directly access loopStrategy, but we can verify the agent was created
        expect($agent)->toBeInstanceOf(Agent::class);
    });
});

describe('AgentContext', function () {
    it('creates context with defaults', function () {
        $context = new AgentContext;

        expect($context->conversationId)->toBeNull();
        expect($context->runId)->toBeNull();
        expect($context->tenantId)->toBeNull();
        expect($context->history)->toBeNull();
        expect($context->metadata)->toBe([]);
    });

    it('creates context with values', function () {
        $context = new AgentContext(
            conversationId: 'conv-123',
            runId: 'run-456',
            tenantId: 'tenant-789',
            metadata: ['key' => 'value'],
        );

        expect($context->conversationId)->toBe('conv-123');
        expect($context->runId)->toBe('run-456');
        expect($context->tenantId)->toBe('tenant-789');
        expect($context->metadata)->toBe(['key' => 'value']);
    });

    it('creates immutable copies with new values', function () {
        $original = new AgentContext;

        $withConversation = $original->withConversationId('conv-123');
        expect($withConversation->conversationId)->toBe('conv-123');
        expect($original->conversationId)->toBeNull();

        $withRun = $original->withRunId('run-456');
        expect($withRun->runId)->toBe('run-456');

        $withMeta = $original->withMetadata(['key' => 'value']);
        expect($withMeta->metadata)->toBe(['key' => 'value']);
    });

    it('creates context with history', function () {
        $original = new AgentContext;
        $history = MessageCollection::make()->user('Hello')->assistant('Hi');

        $withHistory = $original->withHistory($history);

        expect($withHistory->history)->toBe($history);
        expect($withHistory->history->count())->toBe(2);
        expect($original->history)->toBeNull();
    });

    it('preserves existing values when adding history', function () {
        $original = new AgentContext(
            conversationId: 'conv-123',
            runId: 'run-456',
            metadata: ['key' => 'value'],
        );
        $history = MessageCollection::make()->user('Test');

        $withHistory = $original->withHistory($history);

        expect($withHistory->conversationId)->toBe('conv-123');
        expect($withHistory->runId)->toBe('run-456');
        expect($withHistory->metadata)->toBe(['key' => 'value']);
        expect($withHistory->history)->toBe($history);
    });
});

describe('Agent additional', function () {
    it('accepts ToolCollection directly', function () {
        $tool = Tool::make('test_tool')
            ->withHandler(fn () => ToolResult::success('done'));

        $collection = ToolCollection::make([$tool]);

        $agent = Agent::make('agent')
            ->withTools($collection);

        expect($agent->tools())->toBe($collection);
        expect($agent->tools()->count())->toBe(1);
    });

    it('sets custom loop', function () {
        $mockLoop = Mockery::mock(\JayI\Cortex\Plugins\Agent\Contracts\AgentLoopContract::class);

        $agent = Agent::make('agent')
            ->withCustomLoop($mockLoop);

        // The agent should use the custom loop strategy
        expect($agent)->toBeInstanceOf(Agent::class);
    });
});

describe('Agent MCP servers', function () {
    it('configures MCP servers with objects', function () {
        $server1 = Mockery::mock(McpServerContract::class);
        $server1->shouldReceive('id')->andReturn('server-1');

        $server2 = Mockery::mock(McpServerContract::class);
        $server2->shouldReceive('id')->andReturn('server-2');

        $agent = Agent::make('agent')
            ->withMcpServers([$server1, $server2]);

        expect($agent->mcpServers())->toBeInstanceOf(McpServerCollection::class);
        expect($agent->mcpServers()->count())->toBe(2);
        expect($agent->mcpServers()->has('server-1'))->toBeTrue();
        expect($agent->mcpServers()->has('server-2'))->toBeTrue();
    });

    it('configures MCP servers with strings', function () {
        $agent = Agent::make('agent')
            ->withMcpServers(['my-server', 'App\\Mcp\\CustomServer']);

        expect($agent->mcpServers())->toBeInstanceOf(McpServerCollection::class);
        expect($agent->mcpServers()->count())->toBe(2);
        expect($agent->mcpServers()->has('my-server'))->toBeTrue();
        expect($agent->mcpServers()->has('App\\Mcp\\CustomServer'))->toBeTrue();
    });

    it('configures MCP servers with mixed array', function () {
        $server = Mockery::mock(McpServerContract::class);
        $server->shouldReceive('id')->andReturn('server-1');

        $agent = Agent::make('agent')
            ->withMcpServers([$server, 'registry-entry', 'App\\Mcp\\Server']);

        expect($agent->mcpServers())->toBeInstanceOf(McpServerCollection::class);
        expect($agent->mcpServers()->count())->toBe(3);
        expect($agent->mcpServers()->has('server-1'))->toBeTrue();
        expect($agent->mcpServers()->has('registry-entry'))->toBeTrue();
        expect($agent->mcpServers()->has('App\\Mcp\\Server'))->toBeTrue();
    });

    it('adds individual MCP servers', function () {
        $server = Mockery::mock(McpServerContract::class);
        $server->shouldReceive('id')->andReturn('server-1');

        $agent = Agent::make('agent')
            ->addMcpServer('my-server')
            ->addMcpServer($server);

        expect($agent->mcpServers())->toBeInstanceOf(McpServerCollection::class);
        expect($agent->mcpServers()->count())->toBe(2);
        expect($agent->mcpServers()->has('my-server'))->toBeTrue();
        expect($agent->mcpServers()->has('server-1'))->toBeTrue();
    });

    it('returns empty collection when no MCP servers configured', function () {
        $agent = Agent::make('agent');

        expect($agent->mcpServers())->toBeInstanceOf(McpServerCollection::class);
        expect($agent->mcpServers()->isEmpty())->toBeTrue();
    });

    it('accepts McpServerCollection directly', function () {
        $server = Mockery::mock(McpServerContract::class);
        $server->shouldReceive('id')->andReturn('server-1');

        $collection = McpServerCollection::make([$server, 'string-server']);

        $agent = Agent::make('agent')
            ->withMcpServers($collection);

        expect($agent->mcpServers())->toBe($collection);
        expect($agent->mcpServers()->count())->toBe(2);
    });
});
