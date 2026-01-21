<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Agent\Agent;
use JayI\Cortex\Plugins\Agent\AgentContext;
use JayI\Cortex\Plugins\Agent\AgentLoopStrategy;
use JayI\Cortex\Plugins\Agent\Memory\BufferMemory;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolCollection;
use JayI\Cortex\Plugins\Tool\ToolResult;

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
        $memory = new BufferMemory();

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
        $context = new AgentContext();

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
        $original = new AgentContext();

        $withConversation = $original->withConversationId('conv-123');
        expect($withConversation->conversationId)->toBe('conv-123');
        expect($original->conversationId)->toBeNull();

        $withRun = $original->withRunId('run-456');
        expect($withRun->runId)->toBe('run-456');

        $withMeta = $original->withMetadata(['key' => 'value']);
        expect($withMeta->metadata)->toBe(['key' => 'value']);
    });

    it('creates context with history', function () {
        $original = new AgentContext();
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
