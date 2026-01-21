<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Agent\Agent;
use JayI\Cortex\Plugins\Agent\AgentCollection;
use JayI\Cortex\Plugins\Agent\AgentContext;
use JayI\Cortex\Plugins\Agent\AgentResponse;
use JayI\Cortex\Plugins\Agent\AgentStopReason;
use JayI\Cortex\Plugins\Agent\AgentTool;
use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Chat\Usage;
use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Tool\Contracts\ToolContract;
use JayI\Cortex\Plugins\Tool\ToolContext;

describe('AgentTool', function () {
    it('implements ToolContract', function () {
        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('id')->andReturn('test-agent');

        $tool = AgentTool::make($agent);

        expect($tool)->toBeInstanceOf(ToolContract::class);
    });

    it('uses agent id as tool name by default', function () {
        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('id')->andReturn('research-agent');

        $tool = AgentTool::make($agent);

        expect($tool->name())->toBe('research-agent');
    });

    it('allows custom tool name', function () {
        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('id')->andReturn('research-agent');

        $tool = AgentTool::make($agent)->withName('custom-name');

        expect($tool->name())->toBe('custom-name');
    });

    it('uses agent description as tool description by default', function () {
        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('description')->andReturn('A helpful research assistant');

        $tool = AgentTool::make($agent);

        expect($tool->description())->toBe('A helpful research assistant');
    });

    it('generates default description when agent has none', function () {
        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('description')->andReturn('');
        $agent->shouldReceive('name')->andReturn('Research Agent');

        $tool = AgentTool::make($agent);

        expect($tool->description())->toBe('Delegate task to the Research Agent agent.');
    });

    it('allows custom tool description', function () {
        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('description')->andReturn('Original description');

        $tool = AgentTool::make($agent)->withDescription('Custom description');

        expect($tool->description())->toBe('Custom description');
    });

    it('has default input schema with task property', function () {
        $agent = Mockery::mock(AgentContract::class);

        $tool = AgentTool::make($agent);
        $schema = $tool->inputSchema();

        expect($schema)->toBeInstanceOf(Schema::class);

        $jsonSchema = $schema->toJsonSchema();
        expect($jsonSchema['type'])->toBe('object');
        expect($jsonSchema['properties'])->toHaveKey('task');
        expect($jsonSchema['required'])->toContain('task');
    });

    it('allows custom input schema', function () {
        $agent = Mockery::mock(AgentContract::class);

        $customSchema = Schema::object()
            ->property('query', Schema::string())
            ->property('context', Schema::string())
            ->required('query');

        $tool = AgentTool::make($agent)->withInputSchema($customSchema);

        $jsonSchema = $tool->inputSchema()->toJsonSchema();
        expect($jsonSchema['properties'])->toHaveKey('query');
        expect($jsonSchema['properties'])->toHaveKey('context');
    });

    it('executes agent and returns success result', function () {
        $agentResponse = new AgentResponse(
            content: 'The answer is 42',
            messages: MessageCollection::make([]),
            iterationCount: 2,
            iterations: [],
            totalUsage: new Usage(inputTokens: 100, outputTokens: 50),
            stopReason: AgentStopReason::Completed,
        );

        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('run')
            ->with('What is the meaning of life?', Mockery::type(AgentContext::class))
            ->andReturn($agentResponse);

        $tool = AgentTool::make($agent);
        $context = new ToolContext;

        $result = $tool->execute(['task' => 'What is the meaning of life?'], $context);

        expect($result->success)->toBeTrue();
        expect($result->output['response'])->toBe('The answer is 42');
        expect($result->output['iterations'])->toBe(2);
        expect($result->output['stop_reason'])->toBe('completed');
    });

    it('returns error result on agent exception', function () {
        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('run')
            ->andThrow(new \RuntimeException('Agent failed'));

        $tool = AgentTool::make($agent);
        $context = new ToolContext;

        $result = $tool->execute(['task' => 'Do something'], $context);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Agent execution failed');
    });

    it('passes context metadata to agent', function () {
        $agentResponse = new AgentResponse(
            content: 'Done',
            messages: MessageCollection::make([]),
            iterationCount: 1,
            iterations: [],
            totalUsage: new Usage(inputTokens: 50, outputTokens: 25),
            stopReason: AgentStopReason::Completed,
        );

        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('run')
            ->withArgs(function ($task, $context) {
                return $context->conversationId === 'conv-123'
                    && $context->tenantId === 'tenant-456'
                    && $context->metadata['parent_agent'] === 'orchestrator';
            })
            ->andReturn($agentResponse);

        $tool = AgentTool::make($agent);
        $context = new ToolContext(
            conversationId: 'conv-123',
            agentId: 'orchestrator',
            tenantId: 'tenant-456',
        );

        $result = $tool->execute(['task' => 'Do something'], $context);

        expect($result->success)->toBeTrue();
    });

    it('allows setting timeout', function () {
        $agent = Mockery::mock(AgentContract::class);

        $tool = AgentTool::make($agent)->withTimeout(60);

        expect($tool->timeout())->toBe(60);
    });

    it('returns null timeout by default', function () {
        $agent = Mockery::mock(AgentContract::class);

        $tool = AgentTool::make($agent);

        expect($tool->timeout())->toBeNull();
    });

    it('converts to tool definition', function () {
        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('id')->andReturn('test-agent');
        $agent->shouldReceive('description')->andReturn('Test description');

        $tool = AgentTool::make($agent);
        $definition = $tool->toDefinition();

        expect($definition)->toHaveKey('name');
        expect($definition)->toHaveKey('description');
        expect($definition)->toHaveKey('input_schema');
        expect($definition['name'])->toBe('test-agent');
        expect($definition['description'])->toBe('Test description');
    });

    it('exposes the wrapped agent', function () {
        $agent = Mockery::mock(AgentContract::class);

        $tool = AgentTool::make($agent);

        expect($tool->agent())->toBe($agent);
    });

    it('returns null for output schema', function () {
        $agent = Mockery::mock(AgentContract::class);

        $tool = AgentTool::make($agent);

        expect($tool->outputSchema())->toBeNull();
    });
});

describe('Agent::asTool', function () {
    it('converts agent to AgentTool', function () {
        $agent = Agent::make('test-agent')
            ->withName('Test Agent')
            ->withDescription('A test agent');

        $tool = $agent->asTool();

        expect($tool)->toBeInstanceOf(AgentTool::class);
        expect($tool->name())->toBe('test-agent');
        expect($tool->description())->toBe('A test agent');
    });

    it('allows chaining customization after asTool', function () {
        $agent = Agent::make('test-agent')
            ->withDescription('Original description');

        $tool = $agent->asTool()
            ->withName('custom-tool-name')
            ->withDescription('Custom tool description')
            ->withTimeout(30);

        expect($tool->name())->toBe('custom-tool-name');
        expect($tool->description())->toBe('Custom tool description');
        expect($tool->timeout())->toBe(30);
    });
});

describe('AgentTool edge cases', function () {
    it('handles empty task input gracefully', function () {
        $agentResponse = new AgentResponse(
            content: 'Empty response',
            messages: MessageCollection::make([]),
            iterationCount: 1,
            iterations: [],
            totalUsage: new Usage(inputTokens: 10, outputTokens: 5),
            stopReason: AgentStopReason::Completed,
        );

        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('run')
            ->with('', Mockery::type(AgentContext::class))
            ->andReturn($agentResponse);

        $tool = AgentTool::make($agent);
        $result = $tool->execute(['task' => ''], new ToolContext);

        expect($result->success)->toBeTrue();
    });

    it('handles missing task key in input', function () {
        $agentResponse = new AgentResponse(
            content: 'Response',
            messages: MessageCollection::make([]),
            iterationCount: 1,
            iterations: [],
            totalUsage: new Usage(inputTokens: 10, outputTokens: 5),
            stopReason: AgentStopReason::Completed,
        );

        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('run')
            ->with('', Mockery::type(AgentContext::class))
            ->andReturn($agentResponse);

        $tool = AgentTool::make($agent);
        $result = $tool->execute([], new ToolContext);

        expect($result->success)->toBeTrue();
    });

    it('handles max iterations stop reason', function () {
        $agentResponse = new AgentResponse(
            content: 'Partial response',
            messages: MessageCollection::make([]),
            iterationCount: 10,
            iterations: [],
            totalUsage: new Usage(inputTokens: 500, outputTokens: 250),
            stopReason: AgentStopReason::MaxIterations,
        );

        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('run')
            ->andReturn($agentResponse);

        $tool = AgentTool::make($agent);
        $result = $tool->execute(['task' => 'Complex task'], new ToolContext);

        expect($result->success)->toBeTrue();
        expect($result->output['stop_reason'])->toBe('max_iterations');
        expect($result->output['iterations'])->toBe(10);
    });

    it('handles tool stopped reason', function () {
        $agentResponse = new AgentResponse(
            content: 'Stopped by tool',
            messages: MessageCollection::make([]),
            iterationCount: 3,
            iterations: [],
            totalUsage: new Usage(inputTokens: 100, outputTokens: 50),
            stopReason: AgentStopReason::ToolStopped,
        );

        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('run')
            ->andReturn($agentResponse);

        $tool = AgentTool::make($agent);
        $result = $tool->execute(['task' => 'Task'], new ToolContext);

        expect($result->success)->toBeTrue();
        expect($result->output['stop_reason'])->toBe('tool_stopped');
    });

    it('preserves tool context with null optional fields', function () {
        $agentResponse = new AgentResponse(
            content: 'Done',
            messages: MessageCollection::make([]),
            iterationCount: 1,
            iterations: [],
            totalUsage: new Usage(inputTokens: 10, outputTokens: 5),
            stopReason: AgentStopReason::Completed,
        );

        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('run')
            ->withArgs(function ($task, $context) {
                return $context->conversationId === null
                    && $context->tenantId === null;
            })
            ->andReturn($agentResponse);

        $tool = AgentTool::make($agent);
        $context = new ToolContext; // No optional fields set

        $result = $tool->execute(['task' => 'Task'], $context);

        expect($result->success)->toBeTrue();
    });

    it('propagates metadata from tool context', function () {
        $agentResponse = new AgentResponse(
            content: 'Done',
            messages: MessageCollection::make([]),
            iterationCount: 1,
            iterations: [],
            totalUsage: new Usage(inputTokens: 10, outputTokens: 5),
            stopReason: AgentStopReason::Completed,
        );

        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('run')
            ->withArgs(function ($task, $context) {
                return isset($context->metadata['custom_key'])
                    && $context->metadata['custom_key'] === 'custom_value'
                    && $context->metadata['delegated_from_tool'] === true;
            })
            ->andReturn($agentResponse);

        $tool = AgentTool::make($agent);
        $context = new ToolContext(
            metadata: ['custom_key' => 'custom_value'],
        );

        $result = $tool->execute(['task' => 'Task'], $context);

        expect($result->success)->toBeTrue();
    });

    it('supports fluent method chaining', function () {
        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('id')->andReturn('original-id');

        $tool = AgentTool::make($agent)
            ->withName('custom-name')
            ->withDescription('Custom description')
            ->withTimeout(60);

        expect($tool->name())->toBe('custom-name');
        expect($tool->description())->toBe('Custom description');
        expect($tool->timeout())->toBe(60);
    });
});

describe('AgentCollection::asTools', function () {
    it('preserves agent order in tool collection', function () {
        $agent1 = Mockery::mock(AgentContract::class);
        $agent1->shouldReceive('id')->andReturn('first');

        $agent2 = Mockery::mock(AgentContract::class);
        $agent2->shouldReceive('id')->andReturn('second');

        $agent3 = Mockery::mock(AgentContract::class);
        $agent3->shouldReceive('id')->andReturn('third');

        $collection = AgentCollection::make([$agent1, $agent2, $agent3]);
        $tools = $collection->asTools();

        $names = $tools->names();
        expect($names)->toBe(['first', 'second', 'third']);
    });

    it('creates independent tools for each agent', function () {
        $agent1 = Mockery::mock(AgentContract::class);
        $agent1->shouldReceive('id')->andReturn('agent-1');

        $agent2 = Mockery::mock(AgentContract::class);
        $agent2->shouldReceive('id')->andReturn('agent-2');

        $collection = AgentCollection::make([$agent1, $agent2]);
        $tools = $collection->asTools();

        $tool1 = $tools->find('agent-1');
        $tool2 = $tools->find('agent-2');

        expect($tool1->agent())->toBe($agent1);
        expect($tool2->agent())->toBe($agent2);
        expect($tool1)->not->toBe($tool2);
    });
});
