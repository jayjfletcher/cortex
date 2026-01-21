<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use JayI\Cortex\Plugins\Workflow\Repositories\DatabaseWorkflowStateRepository;
use JayI\Cortex\Plugins\Workflow\WorkflowHistoryEntry;
use JayI\Cortex\Plugins\Workflow\WorkflowState;
use JayI\Cortex\Plugins\Workflow\WorkflowStatus;

uses(RefreshDatabase::class);

describe('DatabaseWorkflowStateRepository', function () {
    beforeEach(function () {
        $this->repository = new DatabaseWorkflowStateRepository;
    });

    test('saves and finds workflow state', function () {
        $state = WorkflowState::start('test-workflow', 'run-123', 'start-node')
            ->merge(['key' => 'value']);

        $this->repository->save($state);

        $found = $this->repository->find('run-123');

        expect($found)->not()->toBeNull();
        expect($found->workflowId)->toBe('test-workflow');
        expect($found->runId)->toBe('run-123');
        expect($found->currentNode)->toBe('start-node');
        expect($found->status)->toBe(WorkflowStatus::Running);
        expect($found->data['key'])->toBe('value');
    });

    test('returns null for non-existent run', function () {
        $found = $this->repository->find('non-existent');

        expect($found)->toBeNull();
    });

    test('updates existing state', function () {
        $state = WorkflowState::start('test-workflow', 'run-456', 'start');
        $this->repository->save($state);

        $updatedState = $state->merge(['new_key' => 'new_value'])->complete();
        $this->repository->save($updatedState);

        $found = $this->repository->find('run-456');

        expect($found->status)->toBe(WorkflowStatus::Completed);
        expect($found->data['new_key'])->toBe('new_value');
    });

    test('finds states by workflow id', function () {
        $state1 = WorkflowState::start('workflow-a', 'run-1', 'start');
        $state2 = WorkflowState::start('workflow-a', 'run-2', 'start');
        $state3 = WorkflowState::start('workflow-b', 'run-3', 'start');

        $this->repository->save($state1);
        $this->repository->save($state2);
        $this->repository->save($state3);

        $found = $this->repository->findByWorkflow('workflow-a');

        expect($found)->toHaveCount(2);
        expect($found->pluck('runId')->toArray())->toContain('run-1', 'run-2');
    });

    test('finds states by status', function () {
        $running = WorkflowState::start('wf', 'running-1', 'start');
        $completed = WorkflowState::start('wf', 'completed-1', 'start')->complete();
        $paused = WorkflowState::start('wf', 'paused-1', 'start')->pause('waiting');

        $this->repository->save($running);
        $this->repository->save($completed);
        $this->repository->save($paused);

        $foundRunning = $this->repository->findByStatus(WorkflowStatus::Running);
        $foundCompleted = $this->repository->findByStatus(WorkflowStatus::Completed);
        $foundPaused = $this->repository->findByStatus(WorkflowStatus::Paused);

        expect($foundRunning)->toHaveCount(1);
        expect($foundCompleted)->toHaveCount(1);
        expect($foundPaused)->toHaveCount(1);
    });

    test('deletes workflow state', function () {
        $state = WorkflowState::start('test', 'run-to-delete', 'start');
        $this->repository->save($state);

        expect($this->repository->find('run-to-delete'))->not()->toBeNull();

        $this->repository->delete('run-to-delete');

        expect($this->repository->find('run-to-delete'))->toBeNull();
    });

    test('deletes expired states', function () {
        // Create old completed state
        $oldState = WorkflowState::start('wf', 'old-run', 'start')->complete();
        $this->repository->save($oldState);

        // Manually set updated_at to old date
        DB::table('cortex_workflow_states')
            ->where('run_id', 'old-run')
            ->update(['updated_at' => now()->subDays(10)]);

        // Create fresh state
        $freshState = WorkflowState::start('wf', 'fresh-run', 'start')->complete();
        $this->repository->save($freshState);

        // Default TTL is 7 days, so old should be deleted
        $deleted = $this->repository->deleteExpired();

        expect($deleted)->toBe(1);
        expect($this->repository->find('old-run'))->toBeNull();
        expect($this->repository->find('fresh-run'))->not()->toBeNull();
    });

    test('preserves history entries', function () {
        $state = WorkflowState::start('wf', 'history-run', 'start');
        $state = $state->addHistory(WorkflowHistoryEntry::success(
            nodeId: 'node-1',
            input: ['in' => 1],
            output: ['out' => 2],
            duration: 1.5,
        ));

        $this->repository->save($state);

        $found = $this->repository->find('history-run');

        expect($found->history)->toHaveCount(1);
        expect($found->history[0]->nodeId)->toBe('node-1');
        expect($found->history[0]->input)->toBe(['in' => 1]);
        expect($found->history[0]->output)->toBe(['out' => 2]);
    });

    test('preserves pause reason', function () {
        $state = WorkflowState::start('wf', 'paused-run', 'start')
            ->pause('Waiting for user input');

        $this->repository->save($state);

        $found = $this->repository->find('paused-run');

        expect($found->pauseReason)->toBe('Waiting for user input');
        expect($found->pausedAt)->not()->toBeNull();
    });

    test('preserves timestamps', function () {
        $state = WorkflowState::start('wf', 'ts-run', 'start');
        $this->repository->save($state);

        $found = $this->repository->find('ts-run');

        expect($found->startedAt)->not()->toBeNull();
    });

    test('handles empty data', function () {
        $state = WorkflowState::start('wf', 'empty-run', 'start');
        $this->repository->save($state);

        $found = $this->repository->find('empty-run');

        expect($found->data)->toBe([]);
    });

    test('handles complex data', function () {
        $state = WorkflowState::start('wf', 'complex-run', 'start')
            ->merge([
                'nested' => ['key' => 'value'],
                'array' => [1, 2, 3],
                'null' => null,
                'bool' => true,
            ]);

        $this->repository->save($state);

        $found = $this->repository->find('complex-run');

        expect($found->data['nested'])->toBe(['key' => 'value']);
        expect($found->data['array'])->toBe([1, 2, 3]);
        expect($found->data['null'])->toBeNull();
        expect($found->data['bool'])->toBeTrue();
    });
});
