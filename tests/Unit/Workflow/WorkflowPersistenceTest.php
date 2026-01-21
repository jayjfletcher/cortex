<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowStateRepositoryContract;
use JayI\Cortex\Plugins\Workflow\Exceptions\WorkflowNotFoundException;
use JayI\Cortex\Plugins\Workflow\Exceptions\WorkflowNotPausedException;
use JayI\Cortex\Plugins\Workflow\Repositories\CacheWorkflowStateRepository;
use JayI\Cortex\Plugins\Workflow\WorkflowState;
use JayI\Cortex\Plugins\Workflow\WorkflowStatus;

describe('WorkflowState Status Transitions', function () {
    it('starts in running status', function () {
        $state = WorkflowState::start('workflow-1', 'run-123', 'start-node');

        expect($state->status)->toBe(WorkflowStatus::Running);
        expect($state->currentNode)->toBe('start-node');
    });

    it('transitions to paused', function () {
        $state = WorkflowState::start('workflow-1', 'run-123', 'start-node');
        $paused = $state->pause('Waiting for input');

        expect($paused->status)->toBe(WorkflowStatus::Paused);
        expect($paused->pauseReason)->toBe('Waiting for input');
    });

    it('transitions from paused to running', function () {
        $state = WorkflowState::start('workflow-1', 'run-123', 'start-node');
        $paused = $state->pause('Waiting');
        $resumed = $paused->resume();

        expect($resumed->status)->toBe(WorkflowStatus::Running);
        expect($resumed->pauseReason)->toBeNull();
    });

    it('transitions to completed', function () {
        $state = WorkflowState::start('workflow-1', 'run-123', 'start-node');
        $completed = $state->complete();

        expect($completed->status)->toBe(WorkflowStatus::Completed);
        expect($completed->completedAt)->not()->toBeNull();
    });

    it('transitions to failed', function () {
        $state = WorkflowState::start('workflow-1', 'run-123', 'start-node');
        $failed = $state->fail('Something went wrong');

        expect($failed->status)->toBe(WorkflowStatus::Failed);
    });

    it('moves to next node', function () {
        $state = WorkflowState::start('workflow-1', 'run-123', 'start');
        $next = $state->moveTo('process');

        expect($next->currentNode)->toBe('process');
        expect($state->currentNode)->toBe('start'); // Original unchanged
    });

    it('merges data', function () {
        $state = WorkflowState::start('workflow-1', 'run-123', 'start');
        $state = $state->set('key1', 'value1');
        $state = $state->merge(['key2' => 'value2', 'key3' => 'value3']);

        expect($state->get('key1'))->toBe('value1');
        expect($state->get('key2'))->toBe('value2');
        expect($state->get('key3'))->toBe('value3');
    });

    it('records node execution', function () {
        $state = WorkflowState::start('workflow-1', 'run-123', 'start');
        $state = $state->recordNodeExecution('start', ['input' => 'test'], ['output' => 'done'], 0.5);

        expect($state->history)->toHaveCount(1);
    });
});

describe('WorkflowStatus Enum', function () {
    it('has pending status', function () {
        expect(WorkflowStatus::Pending->value)->toBe('pending');
    });

    it('has running status', function () {
        expect(WorkflowStatus::Running->value)->toBe('running');
    });

    it('has paused status', function () {
        expect(WorkflowStatus::Paused->value)->toBe('paused');
    });

    it('has completed status', function () {
        expect(WorkflowStatus::Completed->value)->toBe('completed');
    });

    it('has failed status', function () {
        expect(WorkflowStatus::Failed->value)->toBe('failed');
    });

    it('checks if terminal', function () {
        expect(WorkflowStatus::Pending->isTerminal())->toBeFalse();
        expect(WorkflowStatus::Running->isTerminal())->toBeFalse();
        expect(WorkflowStatus::Paused->isTerminal())->toBeFalse();
        expect(WorkflowStatus::Completed->isTerminal())->toBeTrue();
        expect(WorkflowStatus::Failed->isTerminal())->toBeTrue();
    });

    it('checks if can resume', function () {
        expect(WorkflowStatus::Pending->canResume())->toBeTrue(); // Pending can be resumed
        expect(WorkflowStatus::Running->canResume())->toBeFalse();
        expect(WorkflowStatus::Paused->canResume())->toBeTrue();
        expect(WorkflowStatus::Completed->canResume())->toBeFalse();
        expect(WorkflowStatus::Failed->canResume())->toBeFalse();
    });
});

describe('CacheWorkflowStateRepository', function () {
    it('implements WorkflowStateRepositoryContract', function () {
        $repository = new CacheWorkflowStateRepository();

        expect($repository)->toBeInstanceOf(WorkflowStateRepositoryContract::class);
    });

    it('saves and finds workflow state', function () {
        Cache::flush();
        $repository = new CacheWorkflowStateRepository();
        $state = WorkflowState::start('workflow-1', 'run-'.uniqid(), 'start');

        $repository->save($state);
        $found = $repository->find($state->runId);

        expect($found)->toBeInstanceOf(WorkflowState::class);
        expect($found->runId)->toBe($state->runId);
    });

    it('returns null for missing state', function () {
        Cache::flush();
        $repository = new CacheWorkflowStateRepository();

        $state = $repository->find('nonexistent-run');

        expect($state)->toBeNull();
    });

    it('deletes workflow state', function () {
        Cache::flush();
        $repository = new CacheWorkflowStateRepository();
        $state = WorkflowState::start('workflow-1', 'run-'.uniqid(), 'start');

        $repository->save($state);
        expect($repository->find($state->runId))->not()->toBeNull();

        $repository->delete($state->runId);
        expect($repository->find($state->runId))->toBeNull();
    });

    it('finds workflows by status', function () {
        Cache::flush();
        $repository = new CacheWorkflowStateRepository();

        // Create a paused workflow
        $state = WorkflowState::start('workflow-1', 'run-'.uniqid(), 'start');
        $paused = $state->pause('Waiting');
        $repository->save($paused);

        $found = $repository->findByStatus(WorkflowStatus::Paused);

        expect($found->count())->toBeGreaterThanOrEqual(1);
    });
});

describe('WorkflowNotFoundException', function () {
    it('creates exception for run id', function () {
        $exception = WorkflowNotFoundException::forRunId('missing-run');

        expect($exception)->toBeInstanceOf(WorkflowNotFoundException::class);
        expect($exception->getMessage())->toContain('missing-run');
    });
});

describe('WorkflowNotPausedException', function () {
    it('creates exception for run id', function () {
        $exception = WorkflowNotPausedException::forRunId('run-123');

        expect($exception)->toBeInstanceOf(WorkflowNotPausedException::class);
        expect($exception->getMessage())->toContain('run-123');
    });
});
