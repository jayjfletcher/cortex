<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Workflow\NodeResult;
use JayI\Cortex\Plugins\Workflow\Nodes\CallbackNode;
use JayI\Cortex\Plugins\Workflow\Nodes\ConditionNode;
use JayI\Cortex\Plugins\Workflow\Nodes\HumanInputNode;
use JayI\Cortex\Plugins\Workflow\Nodes\LoopNode;
use JayI\Cortex\Plugins\Workflow\Nodes\ParallelNode;
use JayI\Cortex\Plugins\Workflow\WorkflowState;

describe('CallbackNode', function () {
    it('executes callback and returns result', function () {
        $node = new CallbackNode('test', fn ($input, $state) => NodeResult::success([
            'result' => $input['value'] * 2,
        ]));

        $state = WorkflowState::start('wf', 'run', 'test');
        $result = $node->execute(['value' => 5], $state);

        expect($node->id())->toBe('test');
        expect($result->success)->toBeTrue();
        expect($result->output['result'])->toBe(10);
    });

    it('handles callback exceptions', function () {
        $node = new CallbackNode('test', fn () => throw new RuntimeException('Test error'));

        $state = WorkflowState::start('wf', 'run', 'test');
        $result = $node->execute([], $state);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Test error');
    });
});

describe('ConditionNode', function () {
    it('returns true branch when condition passes', function () {
        $node = new ConditionNode(
            'check',
            fn ($input) => $input['value'] > 10,
            ['true' => 'high', 'false' => 'low']
        );

        $state = WorkflowState::start('wf', 'run', 'check');
        $result = $node->execute(['value' => 15], $state);

        expect($result->success)->toBeTrue();
        expect($result->output['_next_node'])->toBe('high');
        expect($result->output['condition_result'])->toBeTrue();
    });

    it('returns false branch when condition fails', function () {
        $node = new ConditionNode(
            'check',
            fn ($input) => $input['value'] > 10,
            ['true' => 'high', 'false' => 'low']
        );

        $state = WorkflowState::start('wf', 'run', 'check');
        $result = $node->execute(['value' => 5], $state);

        expect($result->output['_next_node'])->toBe('low');
        expect($result->output['condition_result'])->toBeFalse();
    });

    it('returns null branch when no match', function () {
        $node = new ConditionNode(
            'check',
            fn ($input) => $input['value'] > 10,
            [] // No branches defined
        );

        $state = WorkflowState::start('wf', 'run', 'check');
        $result = $node->execute(['value' => 15], $state);

        expect($result->output['_next_node'])->toBeNull();
    });
});

describe('LoopNode', function () {
    it('executes body while condition is true', function () {
        $body = new CallbackNode('increment', fn ($input, $state) => NodeResult::success([
            'counter' => ($input['counter'] ?? 0) + 1,
        ]));

        $node = new LoopNode(
            'loop',
            $body,
            fn ($input, $state, $iteration) => ($input['counter'] ?? 0) < 5
        );

        $state = WorkflowState::start('wf', 'run', 'loop');
        $result = $node->execute(['counter' => 0], $state);

        expect($result->success)->toBeTrue();
        expect($result->output['iterations'])->toBe(5);
        expect($result->output['final_output']['counter'])->toBe(5);
    });

    it('respects max iterations', function () {
        $body = new CallbackNode('always', fn () => NodeResult::success(['ok' => true]));

        $node = new LoopNode(
            'loop',
            $body,
            fn () => true, // Always true
            maxIterations: 3
        );

        $state = WorkflowState::start('wf', 'run', 'loop');
        $result = $node->execute([], $state);

        expect($result->output['iterations'])->toBe(3);
    });

    it('handles body failures', function () {
        $iteration = 0;
        $body = new CallbackNode('failing', function () use (&$iteration) {
            $iteration++;
            if ($iteration >= 3) {
                return NodeResult::failure('Too many');
            }

            return NodeResult::success(['counter' => $iteration]);
        });

        $node = new LoopNode(
            'loop',
            $body,
            fn () => true
        );

        $state = WorkflowState::start('wf', 'run', 'loop');
        $result = $node->execute([], $state);

        expect($result->success)->toBeFalse();
        expect($result->error)->toContain('Too many');
    });

    it('propagates pause from body', function () {
        $body = new CallbackNode('pausing', fn () => NodeResult::pause('Need input'));

        $node = new LoopNode(
            'loop',
            $body,
            fn () => true
        );

        $state = WorkflowState::start('wf', 'run', 'loop');
        $result = $node->execute([], $state);

        expect($result->shouldPause)->toBeTrue();
    });
});

describe('ParallelNode', function () {
    it('executes all nodes and merges results with all strategy', function () {
        $nodes = [
            new CallbackNode('a', fn () => NodeResult::success(['a' => 1])),
            new CallbackNode('b', fn () => NodeResult::success(['b' => 2])),
            new CallbackNode('c', fn () => NodeResult::success(['c' => 3])),
        ];

        $node = new ParallelNode('parallel', $nodes, 'all');

        $state = WorkflowState::start('wf', 'run', 'parallel');
        $result = $node->execute([], $state);

        expect($result->success)->toBeTrue();
        expect($result->output['a'])->toBe(['a' => 1]);
        expect($result->output['b'])->toBe(['b' => 2]);
        expect($result->output['c'])->toBe(['c' => 3]);
    });

    it('fails with all strategy if any node fails', function () {
        $nodes = [
            new CallbackNode('a', fn () => NodeResult::success(['a' => 1])),
            new CallbackNode('b', fn () => NodeResult::failure('Failed')),
        ];

        $node = new ParallelNode('parallel', $nodes, 'all');

        $state = WorkflowState::start('wf', 'run', 'parallel');
        $result = $node->execute([], $state);

        expect($result->success)->toBeFalse();
    });

    it('succeeds with any strategy if at least one succeeds', function () {
        $nodes = [
            new CallbackNode('a', fn () => NodeResult::failure('Failed A')),
            new CallbackNode('b', fn () => NodeResult::success(['b' => 2])),
        ];

        $node = new ParallelNode('parallel', $nodes, 'any');

        $state = WorkflowState::start('wf', 'run', 'parallel');
        $result = $node->execute([], $state);

        expect($result->success)->toBeTrue();
        expect($result->output)->toBe(['b' => ['b' => 2]]);
    });

    it('fails with any strategy if all fail', function () {
        $nodes = [
            new CallbackNode('a', fn () => NodeResult::failure('Failed A')),
            new CallbackNode('b', fn () => NodeResult::failure('Failed B')),
        ];

        $node = new ParallelNode('parallel', $nodes, 'any');

        $state = WorkflowState::start('wf', 'run', 'parallel');
        $result = $node->execute([], $state);

        expect($result->success)->toBeFalse();
    });

    it('uses custom merger', function () {
        $nodes = [
            new CallbackNode('a', fn () => NodeResult::success(['value' => 1])),
            new CallbackNode('b', fn () => NodeResult::success(['value' => 2])),
        ];

        $merger = fn ($results) => [
            'sum' => array_sum(array_map(fn ($r) => $r->output['value'], $results)),
        ];

        $node = new ParallelNode('parallel', $nodes, 'custom', $merger);

        $state = WorkflowState::start('wf', 'run', 'parallel');
        $result = $node->execute([], $state);

        expect($result->output['sum'])->toBe(3);
    });

    it('propagates pause from child nodes', function () {
        $nodes = [
            new CallbackNode('a', fn () => NodeResult::success(['a' => 1])),
            new CallbackNode('b', fn () => NodeResult::pause('Need input')),
        ];

        $node = new ParallelNode('parallel', $nodes);

        $state = WorkflowState::start('wf', 'run', 'parallel');
        $result = $node->execute([], $state);

        expect($result->shouldPause)->toBeTrue();
    });
});

describe('HumanInputNode', function () {
    it('pauses when no human input provided', function () {
        $node = new HumanInputNode('input', 'Please provide your name');

        $state = WorkflowState::start('wf', 'run', 'input');
        $result = $node->execute([], $state);

        expect($result->shouldPause)->toBeTrue();
        expect($result->pauseReason)->toBe('Please provide your name');
        expect($result->output['awaiting_input'])->toBeTrue();
    });

    it('returns success when human input is provided', function () {
        $node = new HumanInputNode('input', 'Please provide your name');

        $state = WorkflowState::start('wf', 'run', 'input');
        $result = $node->execute(['human_input' => 'John'], $state);

        expect($result->success)->toBeTrue();
        expect($result->output['human_input'])->toBe('John');
    });

    it('validates input against schema', function () {
        $schema = Schema::object([
            'name' => Schema::string(),
            'age' => Schema::integer(),
        ])->required('name', 'age');

        $node = new HumanInputNode('input', 'Provide details', $schema);

        $state = WorkflowState::start('wf', 'run', 'input');

        // Valid input
        $validResult = $node->execute(['human_input' => ['name' => 'John', 'age' => 30]], $state);
        expect($validResult->success)->toBeTrue();

        // Invalid input (missing age)
        $invalidResult = $node->execute(['human_input' => ['name' => 'John']], $state);
        expect($invalidResult->success)->toBeFalse();
        expect($invalidResult->error)->toContain('Invalid human input');
    });

    it('exposes prompt and schema', function () {
        $schema = Schema::string();
        $node = new HumanInputNode('input', 'Enter value', $schema, 30);

        expect($node->getPrompt())->toBe('Enter value');
        expect($node->getInputSchema())->toBe($schema);
    });
});
