<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Workflow\NodeResult;
use JayI\Cortex\Plugins\Workflow\Workflow;
use JayI\Cortex\Plugins\Workflow\WorkflowContext;
use JayI\Cortex\Plugins\Workflow\WorkflowExecutor;

describe('WorkflowExecutor', function () {
    it('executes a simple single-node workflow', function () {
        $executor = new WorkflowExecutor;

        $workflow = Workflow::make('simple')
            ->callback('step1', fn ($input, $state) => NodeResult::success([
                'result' => 'Hello, '.($input['name'] ?? 'World'),
            ]));

        $result = $executor->execute($workflow, ['name' => 'Test']);

        expect($result->isCompleted())->toBeTrue();
        expect($result->output()['result'])->toBe('Hello, Test');
    });

    it('executes a multi-node workflow', function () {
        $executor = new WorkflowExecutor;

        $workflow = Workflow::make('multi')
            ->callback('step1', fn ($input, $state) => NodeResult::success(['a' => 1]))
            ->callback('step2', fn ($input, $state) => NodeResult::success(['b' => $input['a'] + 1]))
            ->callback('step3', fn ($input, $state) => NodeResult::success(['c' => $input['b'] + 1]))
            ->then('step1', 'step2')
            ->then('step2', 'step3');

        $result = $executor->execute($workflow);

        expect($result->isCompleted())->toBeTrue();
        expect($result->output())->toMatchArray(['a' => 1, 'b' => 2, 'c' => 3]);
    });

    it('handles node failures', function () {
        $executor = new WorkflowExecutor;

        $workflow = Workflow::make('failing')
            ->callback('step1', fn () => NodeResult::failure('Something went wrong'));

        $result = $executor->execute($workflow);

        expect($result->isFailed())->toBeTrue();
        expect($result->error)->toContain('Something went wrong');
    });

    it('handles node exceptions', function () {
        $executor = new WorkflowExecutor;

        $workflow = Workflow::make('throwing')
            ->callback('step1', fn () => throw new RuntimeException('Unexpected error'));

        $result = $executor->execute($workflow);

        expect($result->isFailed())->toBeTrue();
        expect($result->error)->toContain('Unexpected error');
    });

    it('handles pause requests', function () {
        $executor = new WorkflowExecutor;

        $workflow = Workflow::make('pausing')
            ->callback('step1', fn () => NodeResult::pause('Waiting for input'));

        $result = $executor->execute($workflow);

        expect($result->isPaused())->toBeTrue();
        expect($result->pauseReason)->toBe('Waiting for input');
    });

    it('resumes paused workflow', function () {
        $executor = new WorkflowExecutor;

        $workflow = Workflow::make('resumable')
            ->callback('step1', function ($input) {
                if (! isset($input['human_input'])) {
                    return NodeResult::pause('Waiting for input');
                }

                return NodeResult::success(['received' => $input['human_input']]);
            })
            ->callback('step2', fn ($input) => NodeResult::success(['final' => $input['received'].'!']))
            ->then('step1', 'step2');

        // Initial run - should pause
        $result = $executor->execute($workflow);
        expect($result->isPaused())->toBeTrue();

        // Resume with input
        $resumed = $executor->resume($workflow, $result->state, ['human_input' => 'test']);
        expect($resumed->isCompleted())->toBeTrue();
        expect($resumed->output()['final'])->toBe('test!');
    });

    it('uses conditional edges', function () {
        $executor = new WorkflowExecutor;

        $workflow = Workflow::make('conditional')
            ->callback('check', fn ($input) => NodeResult::success(['value' => $input['value']]))
            ->callback('high', fn () => NodeResult::success(['result' => 'high']))
            ->callback('low', fn () => NodeResult::success(['result' => 'low']))
            ->edge('check', 'high', fn ($input) => ($input['value'] ?? 0) > 10, priority: 1)
            ->edge('check', 'low', fn ($input) => ($input['value'] ?? 0) <= 10, priority: 0);

        $highResult = $executor->execute($workflow, ['value' => 15]);
        expect($highResult->output()['result'])->toBe('high');

        $lowResult = $executor->execute($workflow, ['value' => 5]);
        expect($lowResult->output()['result'])->toBe('low');
    });

    it('respects max steps limit', function () {
        $executor = (new WorkflowExecutor)->maxSteps(5);

        $iterations = 0;
        $workflow = Workflow::make('infinite')
            ->callback('loop', function () use (&$iterations) {
                $iterations++;

                return NodeResult::success(['iteration' => $iterations]);
            })
            ->edge('loop', 'loop'); // Self-loop

        $result = $executor->execute($workflow);

        expect($result->isFailed())->toBeTrue();
        expect($result->error)->toContain('Maximum steps');
        expect($iterations)->toBeLessThanOrEqual(5);
    });

    it('records execution history', function () {
        $executor = new WorkflowExecutor;

        $workflow = Workflow::make('tracked')
            ->callback('step1', fn () => NodeResult::success(['a' => 1]))
            ->callback('step2', fn () => NodeResult::success(['b' => 2]))
            ->then('step1', 'step2');

        $result = $executor->execute($workflow);

        expect($result->state->history)->toHaveCount(2);
        expect($result->state->history[0]->nodeId)->toBe('step1');
        expect($result->state->history[1]->nodeId)->toBe('step2');
    });

    it('preserves state across nodes', function () {
        $executor = new WorkflowExecutor;

        $workflow = Workflow::make('stateful')
            ->callback('step1', fn ($input, $state) => NodeResult::success(['count' => 1]))
            ->callback('step2', fn ($input, $state) => NodeResult::success(['count' => $state->get('count') + 1]))
            ->callback('step3', fn ($input, $state) => NodeResult::success(['final' => $state->get('count')]))
            ->then('step1', 'step2')
            ->then('step2', 'step3');

        $result = $executor->execute($workflow);

        expect($result->output()['final'])->toBe(2);
    });

    it('passes context to workflow', function () {
        $executor = new WorkflowExecutor;

        $workflow = Workflow::make('with-context')
            ->callback('step1', fn () => NodeResult::success(['done' => true]));

        $context = new WorkflowContext(
            correlationId: 'corr-123',
            metadata: ['source' => 'test'],
        );

        $result = $executor->execute($workflow, [], $context);

        expect($result->state->runId)->toBe('corr-123');
    });
});
