<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Workflow\Edge;
use JayI\Cortex\Plugins\Workflow\NodeResult;
use JayI\Cortex\Plugins\Workflow\Nodes\CallbackNode;
use JayI\Cortex\Plugins\Workflow\Nodes\ConditionNode;
use JayI\Cortex\Plugins\Workflow\Workflow;
use JayI\Cortex\Plugins\Workflow\WorkflowContext;
use JayI\Cortex\Plugins\Workflow\WorkflowDefinition;
use JayI\Cortex\Plugins\Workflow\WorkflowExecutor;
use JayI\Cortex\Plugins\Workflow\WorkflowState;
use JayI\Cortex\Plugins\Workflow\WorkflowStatus;

describe('Workflow', function () {
    it('creates a workflow with fluent builder', function () {
        $workflow = Workflow::make('test-workflow')
            ->withName('Test Workflow')
            ->withDescription('A test workflow');

        expect($workflow->id())->toBe('test-workflow');
        expect($workflow->name())->toBe('Test Workflow');
        expect($workflow->description())->toBe('A test workflow');

        $definition = $workflow->definition();
        expect($definition->name)->toBe('Test Workflow');
        expect($definition->description)->toBe('A test workflow');
    });

    it('adds callback nodes', function () {
        $workflow = Workflow::make('workflow')
            ->callback('step1', fn ($input, $state) => NodeResult::success(['result' => 'done']));

        $definition = $workflow->definition();
        expect($definition->nodes)->toHaveCount(1);
        expect($definition->entryNode)->toBe('step1');
    });

    it('adds multiple nodes with edges', function () {
        $workflow = Workflow::make('workflow')
            ->callback('step1', fn ($input, $state) => NodeResult::success(['a' => 1]))
            ->callback('step2', fn ($input, $state) => NodeResult::success(['b' => 2]))
            ->then('step1', 'step2');

        $definition = $workflow->definition();
        expect($definition->nodes)->toHaveCount(2);
        expect($definition->edges)->toHaveCount(1);
    });

    it('sets custom entry node', function () {
        $workflow = Workflow::make('workflow')
            ->callback('step1', fn () => NodeResult::success([]))
            ->callback('step2', fn () => NodeResult::success([]))
            ->entry('step2');

        expect($workflow->definition()->entryNode)->toBe('step2');
    });

    it('adds condition nodes', function () {
        $workflow = Workflow::make('workflow')
            ->condition(
                'check',
                fn ($input, $state) => $input['value'] > 10,
                ['true' => 'high', 'false' => 'low']
            );

        $definition = $workflow->definition();
        expect($definition->nodes)->toHaveCount(1);
        expect($definition->nodes[0])->toBeInstanceOf(ConditionNode::class);
    });

    it('adds edges with conditions', function () {
        $workflow = Workflow::make('workflow')
            ->callback('start', fn () => NodeResult::success(['value' => 15]))
            ->callback('high', fn () => NodeResult::success(['level' => 'high']))
            ->callback('low', fn () => NodeResult::success(['level' => 'low']))
            ->edge('start', 'high', fn ($input) => ($input['value'] ?? 0) > 10)
            ->edge('start', 'low', fn ($input) => ($input['value'] ?? 0) <= 10);

        $definition = $workflow->definition();
        expect($definition->edges)->toHaveCount(2);
    });

    it('adds metadata', function () {
        $workflow = Workflow::make('workflow')
            ->metadata(['author' => 'test', 'version' => '1.0']);

        $definition = $workflow->definition();
        expect($definition->metadata)->toBe(['author' => 'test', 'version' => '1.0']);
    });

    it('gets node by id', function () {
        $workflow = Workflow::make('workflow')
            ->callback('step1', fn () => NodeResult::success([]));

        expect($workflow->getNode('step1'))->toBeInstanceOf(CallbackNode::class);
        expect($workflow->getNode('nonexistent'))->toBeNull();
    });

    it('gets edges from a node', function () {
        $workflow = Workflow::make('workflow')
            ->callback('step1', fn () => NodeResult::success([]))
            ->callback('step2', fn () => NodeResult::success([]))
            ->callback('step3', fn () => NodeResult::success([]))
            ->then('step1', 'step2')
            ->then('step1', 'step3');

        $edges = $workflow->getEdgesFrom('step1');
        expect($edges)->toHaveCount(2);
    });
});

describe('WorkflowDefinition', function () {
    it('creates definition with nodes and edges', function () {
        $definition = Workflow::make('test')
            ->withName('Test')
            ->withDescription('Description')
            ->callback('step1', fn () => NodeResult::success([]))
            ->edge('step1', 'step2')
            ->definition();

        expect($definition->id)->toBe('test');
        expect($definition->name)->toBe('Test');
        expect($definition->nodes)->toHaveCount(1);
        expect($definition->edges)->toHaveCount(1);
        expect($definition->entryNode)->toBe('step1');
    });

    it('gets node by id', function () {
        $definition = Workflow::make('test')
            ->callback('step1', fn () => NodeResult::success([]))
            ->callback('step2', fn () => NodeResult::success([]))
            ->definition();

        expect($definition->getNode('step1'))->not->toBeNull();
        expect($definition->getNode('step1')->id())->toBe('step1');
        expect($definition->getNode('nonexistent'))->toBeNull();
    });

    it('checks if node exists', function () {
        $definition = Workflow::make('test')
            ->callback('step1', fn () => NodeResult::success([]))
            ->definition();

        expect($definition->hasNode('step1'))->toBeTrue();
        expect($definition->hasNode('nonexistent'))->toBeFalse();
    });
});

describe('WorkflowContext', function () {
    it('creates context with defaults', function () {
        $context = new WorkflowContext();

        expect($context->runId)->toBeNull();
        expect($context->correlationId)->toBeNull();
        expect($context->metadata)->toBe([]);
    });

    it('creates context with values', function () {
        $context = new WorkflowContext(
            runId: 'run-123',
            correlationId: 'corr-456',
            tenantId: 'tenant-789',
            metadata: ['key' => 'value'],
        );

        expect($context->runId)->toBe('run-123');
        expect($context->correlationId)->toBe('corr-456');
        expect($context->tenantId)->toBe('tenant-789');
        expect($context->metadata)->toBe(['key' => 'value']);
    });

    it('creates immutable copies', function () {
        $original = new WorkflowContext();

        $withRun = $original->withRunId('run-123');
        expect($withRun->runId)->toBe('run-123');
        expect($original->runId)->toBeNull();

        $withTenant = $original->withTenantId('tenant-123');
        expect($withTenant->tenantId)->toBe('tenant-123');
    });
});

describe('Edge', function () {
    it('creates edge with builder', function () {
        $edge = Edge::start('step1')
            ->to('step2')
            ->priority(10)
            ->build();

        expect($edge->from)->toBe('step1');
        expect($edge->to)->toBe('step2');
        expect($edge->priority)->toBe(10);
        expect($edge->condition)->toBeNull();
    });

    it('creates edge with condition', function () {
        $condition = fn ($input) => $input['ready'] === true;

        $edge = Edge::start('step1')
            ->to('step2')
            ->when($condition)
            ->build();

        expect($edge->condition)->toBe($condition);
    });
});

describe('WorkflowDefinition additional', function () {
    it('gets edges from a node sorted by priority', function () {
        $workflow = Workflow::make('test')
            ->callback('start', fn () => NodeResult::success([]))
            ->callback('branch1', fn () => NodeResult::success([]))
            ->callback('branch2', fn () => NodeResult::success([]))
            ->edge('start', 'branch1', priority: 5)
            ->edge('start', 'branch2', priority: 10);

        $definition = $workflow->definition();
        $edges = $definition->getEdgesFrom('start');

        expect($edges)->toHaveCount(2);
        expect($edges[0]->to)->toBe('branch2'); // Higher priority first
        expect($edges[1]->to)->toBe('branch1');
    });

    it('checks exit points based on outgoing edges', function () {
        $workflow = Workflow::make('test')
            ->callback('start', fn () => NodeResult::success([]))
            ->callback('end', fn () => NodeResult::success([]))
            ->then('start', 'end');

        $definition = $workflow->definition();

        // End has no outgoing edges, so it's an exit point
        expect($definition->isExitPoint('end'))->toBeTrue();
        // Start has outgoing edges, so it's not an exit point
        expect($definition->isExitPoint('start'))->toBeFalse();
    });

    it('gets next node unconditionally', function () {
        $workflow = Workflow::make('test')
            ->callback('step1', fn () => NodeResult::success([]))
            ->callback('step2', fn () => NodeResult::success([]))
            ->then('step1', 'step2');

        $definition = $workflow->definition();

        expect($definition->getNextNode('step1'))->toBe('step2');
        expect($definition->getNextNode('step2'))->toBeNull();
    });

    it('gets next node with condition', function () {
        $workflow = Workflow::make('test')
            ->callback('start', fn () => NodeResult::success([]))
            ->callback('high', fn () => NodeResult::success([]))
            ->callback('low', fn () => NodeResult::success([]))
            ->edge('start', 'high', fn ($ctx) => ($ctx['value'] ?? 0) > 10)
            ->edge('start', 'low', fn ($ctx) => ($ctx['value'] ?? 0) <= 10);

        $definition = $workflow->definition();

        expect($definition->getNextNode('start', ['value' => 15]))->toBe('high');
        expect($definition->getNextNode('start', ['value' => 5]))->toBe('low');
    });
});

describe('WorkflowContext additional', function () {
    it('adds metadata with withMetadata', function () {
        $context = new WorkflowContext(metadata: ['initial' => 'value']);
        $updated = $context->withMetadata(['added' => 'new']);

        expect($updated->metadata['initial'])->toBe('value');
        expect($updated->metadata['added'])->toBe('new');
        expect($context->metadata)->not->toHaveKey('added');
    });
});

describe('CallbackNode', function () {
    it('executes callback and returns result', function () {
        $node = new \JayI\Cortex\Plugins\Workflow\Nodes\CallbackNode(
            'test-node',
            fn ($input, $state) => NodeResult::success(['message' => 'done'])
        );

        expect($node->id())->toBe('test-node');

        $state = new WorkflowState('workflow-1', 'run-1');
        $result = $node->execute(['data' => 'input'], $state);

        expect($result->success)->toBeTrue();
        expect($result->output['message'])->toBe('done');
    });

    it('wraps array result as NodeResult', function () {
        $node = new \JayI\Cortex\Plugins\Workflow\Nodes\CallbackNode(
            'array-node',
            fn ($input, $state) => ['wrapped' => 'array']
        );

        $state = new WorkflowState('workflow-1', 'run-1');
        $result = $node->execute([], $state);

        expect($result->success)->toBeTrue();
        expect($result->output['wrapped'])->toBe('array');
    });

    it('wraps scalar result as NodeResult', function () {
        $node = new \JayI\Cortex\Plugins\Workflow\Nodes\CallbackNode(
            'scalar-node',
            fn ($input, $state) => 'scalar value'
        );

        $state = new WorkflowState('workflow-1', 'run-1');
        $result = $node->execute([], $state);

        expect($result->success)->toBeTrue();
        expect($result->output['result'])->toBe('scalar value');
    });

    it('handles callback exceptions', function () {
        $node = new \JayI\Cortex\Plugins\Workflow\Nodes\CallbackNode(
            'error-node',
            fn ($input, $state) => throw new RuntimeException('Test error')
        );

        $state = new WorkflowState('workflow-1', 'run-1');
        $result = $node->execute([], $state);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Callback failed');
        expect($result->error)->toContain('Test error');
    });
});

describe('NodeResult', function () {
    it('creates success result', function () {
        $result = NodeResult::success(['key' => 'value']);

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe(['key' => 'value']);
        expect($result->error)->toBeNull();
        expect($result->shouldPause)->toBeFalse();
    });

    it('creates success result with next node', function () {
        $result = NodeResult::success(['key' => 'value'], 'next-step');

        expect($result->nextNode)->toBe('next-step');
    });

    it('creates failure result', function () {
        $result = NodeResult::failure('Something went wrong');

        expect($result->success)->toBeFalse();
        expect($result->error)->toBe('Something went wrong');
        expect($result->output)->toBe([]);
    });

    it('creates pause result', function () {
        $result = NodeResult::pause('Waiting for input', ['context' => 'data']);

        expect($result->success)->toBeTrue();
        expect($result->shouldPause)->toBeTrue();
        expect($result->pauseReason)->toBe('Waiting for input');
        expect($result->output)->toBe(['context' => 'data']);
    });

    it('creates pause result with default output', function () {
        $result = NodeResult::pause('Waiting');

        expect($result->shouldPause)->toBeTrue();
        expect($result->output)->toBe([]);
    });

    it('creates goto result', function () {
        $result = NodeResult::goto('target-node', ['data' => 'value']);

        expect($result->nextNode)->toBe('target-node');
        expect($result->output)->toBe(['data' => 'value']);
    });
});
