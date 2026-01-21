<?php

declare(strict_types=1);

use JayI\Cortex\Exceptions\WorkflowException;
use JayI\Cortex\Plugins\Workflow\NodeResult;
use JayI\Cortex\Plugins\Workflow\Workflow;
use JayI\Cortex\Plugins\Workflow\WorkflowRegistry;

describe('WorkflowRegistry', function () {
    it('registers and retrieves workflows', function () {
        $registry = new WorkflowRegistry;

        $workflow = Workflow::make('test-workflow')
            ->callback('step1', fn () => NodeResult::success([]));

        $registry->register($workflow);

        expect($registry->has('test-workflow'))->toBeTrue();
        expect($registry->get('test-workflow'))->toBe($workflow);
    });

    it('throws exception when workflow not found', function () {
        $registry = new WorkflowRegistry;

        expect(fn () => $registry->get('nonexistent'))
            ->toThrow(WorkflowException::class, "Workflow 'nonexistent' not found");
    });

    it('returns all registered workflows', function () {
        $registry = new WorkflowRegistry;

        $workflow1 = Workflow::make('wf1')->callback('step', fn () => NodeResult::success([]));
        $workflow2 = Workflow::make('wf2')->callback('step', fn () => NodeResult::success([]));

        $registry->register($workflow1);
        $registry->register($workflow2);

        $all = $registry->all();
        expect($all)->toHaveCount(2);
        expect($all->has('wf1'))->toBeTrue();
        expect($all->has('wf2'))->toBeTrue();
    });

    it('removes workflows', function () {
        $registry = new WorkflowRegistry;

        $workflow = Workflow::make('test')->callback('step', fn () => NodeResult::success([]));
        $registry->register($workflow);

        expect($registry->has('test'))->toBeTrue();

        $registry->remove('test');

        expect($registry->has('test'))->toBeFalse();
    });

    it('overwrites workflows with same id', function () {
        $registry = new WorkflowRegistry;

        $workflow1 = Workflow::make('test')
            ->withName('First')
            ->callback('step', fn () => NodeResult::success([]));

        $workflow2 = Workflow::make('test')
            ->withName('Second')
            ->callback('step', fn () => NodeResult::success([]));

        $registry->register($workflow1);
        $registry->register($workflow2);

        $retrieved = $registry->get('test');
        expect($retrieved->name())->toBe('Second');
    });

    it('returns only specified workflows', function () {
        $registry = new WorkflowRegistry;

        $workflow1 = Workflow::make('wf1')->callback('step', fn () => NodeResult::success([]));
        $workflow2 = Workflow::make('wf2')->callback('step', fn () => NodeResult::success([]));
        $workflow3 = Workflow::make('wf3')->callback('step', fn () => NodeResult::success([]));

        $registry->register($workflow1);
        $registry->register($workflow2);
        $registry->register($workflow3);

        $only = $registry->only(['wf1', 'wf3']);

        expect($only->count())->toBe(2);
        expect($only->has('wf1'))->toBeTrue();
        expect($only->has('wf3'))->toBeTrue();
        expect($only->has('wf2'))->toBeFalse();
    });

    it('returns all workflows except specified ones', function () {
        $registry = new WorkflowRegistry;

        $workflow1 = Workflow::make('wf1')->callback('step', fn () => NodeResult::success([]));
        $workflow2 = Workflow::make('wf2')->callback('step', fn () => NodeResult::success([]));
        $workflow3 = Workflow::make('wf3')->callback('step', fn () => NodeResult::success([]));

        $registry->register($workflow1);
        $registry->register($workflow2);
        $registry->register($workflow3);

        $except = $registry->except(['wf2']);

        expect($except->count())->toBe(2);
        expect($except->has('wf1'))->toBeTrue();
        expect($except->has('wf3'))->toBeTrue();
        expect($except->has('wf2'))->toBeFalse();
    });

    it('returns empty collection when only specified non-existent ids', function () {
        $registry = new WorkflowRegistry;

        $workflow1 = Workflow::make('wf1')->callback('step', fn () => NodeResult::success([]));
        $registry->register($workflow1);

        $only = $registry->only(['nonexistent']);

        expect($only->count())->toBe(0);
    });

    it('returns all workflows when except specified non-existent ids', function () {
        $registry = new WorkflowRegistry;

        $workflow1 = Workflow::make('wf1')->callback('step', fn () => NodeResult::success([]));
        $workflow2 = Workflow::make('wf2')->callback('step', fn () => NodeResult::success([]));

        $registry->register($workflow1);
        $registry->register($workflow2);

        $except = $registry->except(['nonexistent']);

        expect($except->count())->toBe(2);
    });
});

describe('WorkflowException', function () {
    it('creates workflow not found exception', function () {
        $exception = WorkflowException::workflowNotFound('my-workflow');

        expect($exception->getMessage())->toBe("Workflow 'my-workflow' not found");
        expect($exception->context())->toBe(['workflow_id' => 'my-workflow']);
    });

    it('creates node not found exception', function () {
        $exception = WorkflowException::nodeNotFound('step1');

        expect($exception->getMessage())->toBe("Node 'step1' not found in workflow");
        expect($exception->context())->toBe(['node_id' => 'step1']);
    });

    it('creates invalid state exception', function () {
        $exception = WorkflowException::invalidState('completed');

        expect($exception->getMessage())->toBe("Cannot resume workflow in 'completed' state");
        expect($exception->context())->toBe(['state' => 'completed']);
    });

    it('creates execution failed exception', function () {
        $previous = new RuntimeException('Root cause');
        $exception = WorkflowException::executionFailed('wf-123', 'Node error', $previous);

        expect($exception->getMessage())->toBe("Workflow 'wf-123' execution failed: Node error");
        expect($exception->getPrevious())->toBe($previous);
    });

    it('creates node execution failed exception', function () {
        $exception = WorkflowException::nodeExecutionFailed('step1', 'Timeout');

        expect($exception->getMessage())->toBe("Node 'step1' execution failed: Timeout");
    });

    it('creates invalid edge exception', function () {
        $exception = WorkflowException::invalidEdge('step1', 'step2', 'Target not found');

        expect($exception->getMessage())->toBe("Invalid edge from 'step1' to 'step2': Target not found");
        expect($exception->context())->toMatchArray([
            'from_node' => 'step1',
            'to_node' => 'step2',
        ]);
    });

    it('creates max steps exceeded exception', function () {
        $exception = WorkflowException::maxStepsExceeded('wf-123', 1000);

        expect($exception->getMessage())->toBe("Workflow 'wf-123' exceeded max steps (1000)");
    });

    it('creates circular dependency exception', function () {
        $exception = WorkflowException::circularDependency('wf-123', ['A', 'B', 'C', 'A']);

        expect($exception->getMessage())->toContain('Circular dependency');
        expect($exception->getMessage())->toContain('A -> B -> C -> A');
    });
});
