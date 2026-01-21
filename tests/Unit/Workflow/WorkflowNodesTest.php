<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Agent\AgentContext;
use JayI\Cortex\Plugins\Agent\AgentResponse;
use JayI\Cortex\Plugins\Agent\AgentStopReason;
use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;
use JayI\Cortex\Plugins\Agent\Contracts\AgentRegistryContract;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Chat\Usage;
use JayI\Cortex\Plugins\Tool\Contracts\ToolContract;
use JayI\Cortex\Plugins\Tool\Contracts\ToolRegistryContract;
use JayI\Cortex\Plugins\Tool\ToolContext;
use JayI\Cortex\Plugins\Tool\ToolResult;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowRegistryContract;
use JayI\Cortex\Plugins\Workflow\Nodes\AgentNode;
use JayI\Cortex\Plugins\Workflow\Nodes\SubWorkflowNode;
use JayI\Cortex\Plugins\Workflow\Nodes\ToolNode;
use JayI\Cortex\Plugins\Workflow\WorkflowContext;
use JayI\Cortex\Plugins\Workflow\WorkflowResult;
use JayI\Cortex\Plugins\Workflow\WorkflowState;

describe('AgentNode', function () {
    beforeEach(function () {
        $this->state = WorkflowState::start('test-workflow', 'run-123', 'start');
    });

    test('returns correct id', function () {
        $mockAgent = Mockery::mock(AgentContract::class);
        $node = new AgentNode('my-agent-node', $mockAgent);

        expect($node->id())->toBe('my-agent-node');
    });

    test('executes agent with direct agent instance', function () {
        $agentResponse = new AgentResponse(
            content: 'Task completed',
            messages: new MessageCollection,
            iterationCount: 2,
            iterations: [],
            totalUsage: new Usage(100, 50, 150),
            stopReason: AgentStopReason::Completed,
        );

        $mockAgent = Mockery::mock(AgentContract::class);
        $mockAgent->shouldReceive('run')
            ->once()
            ->withArgs(function ($input, $context) {
                return is_string($input) && $context instanceof AgentContext;
            })
            ->andReturn($agentResponse);

        $node = new AgentNode('agent-node', $mockAgent);
        $result = $node->execute(['message' => 'Hello'], $this->state);

        expect($result->success)->toBeTrue();
        expect($result->output['content'])->toBe('Task completed');
        expect($result->output['iterations'])->toBe(2);
        expect($result->output['stop_reason'])->toBe('completed');
    });

    test('uses input from state when inputKey specified', function () {
        $agentResponse = new AgentResponse(
            content: 'Done',
            messages: new MessageCollection,
            iterationCount: 1,
            iterations: [],
            totalUsage: Usage::zero(),
            stopReason: AgentStopReason::Completed,
        );

        $mockAgent = Mockery::mock(AgentContract::class);
        $mockAgent->shouldReceive('run')
            ->once()
            ->withArgs(function ($input) {
                return $input === 'State input value';
            })
            ->andReturn($agentResponse);

        $node = new AgentNode('agent-node', $mockAgent, inputKey: 'my_input');
        $state = $this->state->set('my_input', 'State input value');

        $result = $node->execute(['message' => 'Should be ignored'], $state);

        expect($result->success)->toBeTrue();
    });

    test('stores output in specific key when outputKey specified', function () {
        $agentResponse = new AgentResponse(
            content: 'Agent result',
            messages: new MessageCollection,
            iterationCount: 1,
            iterations: [],
            totalUsage: Usage::zero(),
            stopReason: AgentStopReason::Completed,
        );

        $mockAgent = Mockery::mock(AgentContract::class);
        $mockAgent->shouldReceive('run')->andReturn($agentResponse);

        $node = new AgentNode('agent-node', $mockAgent, outputKey: 'agent_output');
        $result = $node->execute([], $this->state);

        expect($result->output)->toBe(['agent_output' => 'Agent result']);
    });

    test('returns failure on exception', function () {
        $mockAgent = Mockery::mock(AgentContract::class);
        $mockAgent->shouldReceive('run')->andThrow(new RuntimeException('Agent crashed'));

        $node = new AgentNode('agent-node', $mockAgent);
        $result = $node->execute([], $this->state);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Agent execution failed');
    });

    test('resolves agent from registry when string provided', function () {
        $agentResponse = new AgentResponse(
            content: 'Done',
            messages: new MessageCollection,
            iterationCount: 1,
            iterations: [],
            totalUsage: Usage::zero(),
            stopReason: AgentStopReason::Completed,
        );

        $mockAgent = Mockery::mock(AgentContract::class);
        $mockAgent->shouldReceive('run')->andReturn($agentResponse);

        $mockRegistry = Mockery::mock(AgentRegistryContract::class);
        $mockRegistry->shouldReceive('get')
            ->with('my-agent')
            ->andReturn($mockAgent);

        app()->instance(AgentRegistryContract::class, $mockRegistry);

        $node = new AgentNode('agent-node', 'my-agent');
        $result = $node->execute(['message' => 'Test'], $this->state);

        expect($result->success)->toBeTrue();
    });
});

describe('ToolNode', function () {
    beforeEach(function () {
        $this->state = WorkflowState::start('test-workflow', 'run-123', 'start');
    });

    test('returns correct id', function () {
        $mockTool = Mockery::mock(ToolContract::class);
        $node = new ToolNode('my-tool-node', $mockTool, []);

        expect($node->id())->toBe('my-tool-node');
    });

    test('executes tool with direct instance', function () {
        $toolResult = ToolResult::success(['data' => 'result']);

        $mockTool = Mockery::mock(ToolContract::class);
        $mockTool->shouldReceive('execute')
            ->once()
            ->withArgs(function ($input, $context) {
                return is_array($input) && $context instanceof ToolContext;
            })
            ->andReturn($toolResult);

        $node = new ToolNode('tool-node', $mockTool, ['key' => 'value']);
        $result = $node->execute([], $this->state);

        expect($result->success)->toBeTrue();
        expect($result->output['data'])->toBe('result');
    });

    test('resolves input from state variables', function () {
        $toolResult = ToolResult::success('done');

        $mockTool = Mockery::mock(ToolContract::class);
        $mockTool->shouldReceive('execute')
            ->once()
            ->withArgs(function ($input) {
                return $input === ['query' => 'state value', 'limit' => 10];
            })
            ->andReturn($toolResult);

        $state = $this->state->set('search_query', 'state value');

        $node = new ToolNode('tool-node', $mockTool, [
            'query' => '$state.search_query',
            'limit' => 10,
        ]);
        $result = $node->execute([], $state);

        expect($result->success)->toBeTrue();
    });

    test('resolves input from input variables', function () {
        $toolResult = ToolResult::success('done');

        $mockTool = Mockery::mock(ToolContract::class);
        $mockTool->shouldReceive('execute')
            ->once()
            ->withArgs(function ($input) {
                return $input === ['query' => 'input value'];
            })
            ->andReturn($toolResult);

        $node = new ToolNode('tool-node', $mockTool, [
            'query' => '$input.search',
        ]);
        $result = $node->execute(['search' => 'input value'], $this->state);

        expect($result->success)->toBeTrue();
    });

    test('uses closure for input mapping', function () {
        $toolResult = ToolResult::success('done');

        $mockTool = Mockery::mock(ToolContract::class);
        $mockTool->shouldReceive('execute')
            ->once()
            ->withArgs(function ($input) {
                return $input === ['computed' => 'custom value'];
            })
            ->andReturn($toolResult);

        $node = new ToolNode('tool-node', $mockTool, function ($input, $state) {
            return ['computed' => 'custom value'];
        });
        $result = $node->execute([], $this->state);

        expect($result->success)->toBeTrue();
    });

    test('stores output in specific key when outputKey specified', function () {
        $toolResult = ToolResult::success('tool output');

        $mockTool = Mockery::mock(ToolContract::class);
        $mockTool->shouldReceive('execute')->andReturn($toolResult);

        $node = new ToolNode('tool-node', $mockTool, [], outputKey: 'my_output');
        $result = $node->execute([], $this->state);

        expect($result->output)->toBe(['my_output' => 'tool output']);
    });

    test('returns failure when tool fails', function () {
        $toolResult = ToolResult::error('Tool failed');

        $mockTool = Mockery::mock(ToolContract::class);
        $mockTool->shouldReceive('execute')->andReturn($toolResult);

        $node = new ToolNode('tool-node', $mockTool, []);
        $result = $node->execute([], $this->state);

        expect($result->success)->toBeFalse();
        expect($result->error)->toBe('Tool failed');
    });

    test('returns failure on exception', function () {
        $mockTool = Mockery::mock(ToolContract::class);
        $mockTool->shouldReceive('execute')->andThrow(new RuntimeException('Crashed'));

        $node = new ToolNode('tool-node', $mockTool, []);
        $result = $node->execute([], $this->state);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Tool execution failed');
    });

    test('resolves tool from registry when string provided', function () {
        $toolResult = ToolResult::success('done');

        $mockTool = Mockery::mock(ToolContract::class);
        $mockTool->shouldReceive('execute')->andReturn($toolResult);

        $mockRegistry = Mockery::mock(ToolRegistryContract::class);
        $mockRegistry->shouldReceive('get')
            ->with('my-tool')
            ->andReturn($mockTool);

        app()->instance(ToolRegistryContract::class, $mockRegistry);

        $node = new ToolNode('tool-node', 'my-tool', []);
        $result = $node->execute([], $this->state);

        expect($result->success)->toBeTrue();
    });
});

describe('SubWorkflowNode', function () {
    beforeEach(function () {
        $this->state = WorkflowState::start('test-workflow', 'run-123', 'start');
    });

    test('returns correct id', function () {
        $mockWorkflow = Mockery::mock(WorkflowContract::class);
        $node = new SubWorkflowNode('my-sub-workflow', $mockWorkflow, []);

        expect($node->id())->toBe('my-sub-workflow');
    });

    test('executes sub-workflow and returns success', function () {
        $subState = WorkflowState::start('sub-workflow', 'sub-run', 'start')
            ->merge(['result' => 'output data'])
            ->complete();

        $workflowResult = WorkflowResult::completed($subState);

        $mockWorkflow = Mockery::mock(WorkflowContract::class);
        $mockWorkflow->shouldReceive('run')
            ->once()
            ->withArgs(function ($input, $context) {
                return is_array($input) && $context instanceof WorkflowContext;
            })
            ->andReturn($workflowResult);

        $node = new SubWorkflowNode('sub-node', $mockWorkflow, ['key' => 'value']);
        $result = $node->execute([], $this->state);

        expect($result->success)->toBeTrue();
    });

    test('resolves input from state and input variables', function () {
        $subState = WorkflowState::start('sub', 'run', 'start')->complete();
        $workflowResult = WorkflowResult::completed($subState);

        $mockWorkflow = Mockery::mock(WorkflowContract::class);
        $mockWorkflow->shouldReceive('run')
            ->once()
            ->withArgs(function ($input) {
                return $input === [
                    'from_state' => 'state value',
                    'from_input' => 'input value',
                    'static' => 'literal',
                ];
            })
            ->andReturn($workflowResult);

        $state = $this->state->set('my_key', 'state value');

        $node = new SubWorkflowNode('sub-node', $mockWorkflow, [
            'from_state' => '$state.my_key',
            'from_input' => '$input.provided',
            'static' => 'literal',
        ]);

        $result = $node->execute(['provided' => 'input value'], $state);

        expect($result->success)->toBeTrue();
    });

    test('uses closure for input mapping', function () {
        $subState = WorkflowState::start('sub', 'run', 'start')->complete();
        $workflowResult = WorkflowResult::completed($subState);

        $mockWorkflow = Mockery::mock(WorkflowContract::class);
        $mockWorkflow->shouldReceive('run')
            ->once()
            ->withArgs(function ($input) {
                return $input === ['dynamic' => 'computed'];
            })
            ->andReturn($workflowResult);

        $node = new SubWorkflowNode('sub-node', $mockWorkflow, fn ($input, $state) => ['dynamic' => 'computed']);

        $result = $node->execute([], $this->state);

        expect($result->success)->toBeTrue();
    });

    test('stores output in specific key when outputKey specified', function () {
        $subState = WorkflowState::start('sub', 'run', 'start')
            ->merge(['result' => 'workflow output'])
            ->complete();
        $workflowResult = WorkflowResult::completed($subState);

        $mockWorkflow = Mockery::mock(WorkflowContract::class);
        $mockWorkflow->shouldReceive('run')->andReturn($workflowResult);

        $node = new SubWorkflowNode('sub-node', $mockWorkflow, [], outputKey: 'sub_result');

        $result = $node->execute([], $this->state);

        expect($result->output)->toHaveKey('sub_result');
    });

    test('returns failure when sub-workflow fails', function () {
        $subState = WorkflowState::start('sub', 'run', 'start')->fail();
        $workflowResult = WorkflowResult::failed($subState);

        $mockWorkflow = Mockery::mock(WorkflowContract::class);
        $mockWorkflow->shouldReceive('run')->andReturn($workflowResult);

        $node = new SubWorkflowNode('sub-node', $mockWorkflow, []);

        $result = $node->execute([], $this->state);

        expect($result->success)->toBeFalse();
        expect($result->error)->toBe('Sub-workflow failed');
    });

    test('returns pause when sub-workflow pauses', function () {
        $subState = WorkflowState::start('sub', 'run', 'start')->pause('Needs input');
        $workflowResult = WorkflowResult::paused($subState, 'Needs input');

        $mockWorkflow = Mockery::mock(WorkflowContract::class);
        $mockWorkflow->shouldReceive('run')->andReturn($workflowResult);

        $node = new SubWorkflowNode('sub-node', $mockWorkflow, []);

        $result = $node->execute([], $this->state);

        expect($result->shouldPause)->toBeTrue();
        expect($result->pauseReason)->toContain('Needs input');
    });

    test('returns failure on exception', function () {
        $mockWorkflow = Mockery::mock(WorkflowContract::class);
        $mockWorkflow->shouldReceive('run')->andThrow(new RuntimeException('Crashed'));

        $node = new SubWorkflowNode('sub-node', $mockWorkflow, []);

        $result = $node->execute([], $this->state);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Sub-workflow failed');
    });

    test('resolves workflow from registry when string provided', function () {
        $subState = WorkflowState::start('sub', 'run', 'start')->complete();
        $workflowResult = WorkflowResult::completed($subState);

        $mockWorkflow = Mockery::mock(WorkflowContract::class);
        $mockWorkflow->shouldReceive('run')->andReturn($workflowResult);

        $mockRegistry = Mockery::mock(WorkflowRegistryContract::class);
        $mockRegistry->shouldReceive('get')
            ->with('my-workflow')
            ->andReturn($mockWorkflow);

        app()->instance(WorkflowRegistryContract::class, $mockRegistry);

        $node = new SubWorkflowNode('sub-node', 'my-workflow', []);

        $result = $node->execute([], $this->state);

        expect($result->success)->toBeTrue();
    });
});
