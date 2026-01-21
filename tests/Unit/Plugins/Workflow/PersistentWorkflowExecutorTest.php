<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowStateRepositoryContract;
use JayI\Cortex\Plugins\Workflow\Exceptions\WorkflowNotFoundException;
use JayI\Cortex\Plugins\Workflow\Exceptions\WorkflowNotPausedException;
use JayI\Cortex\Plugins\Workflow\PersistentWorkflowExecutor;
use JayI\Cortex\Plugins\Workflow\WorkflowContext;
use JayI\Cortex\Plugins\Workflow\WorkflowDefinition;
use JayI\Cortex\Plugins\Workflow\WorkflowExecutor;
use JayI\Cortex\Plugins\Workflow\WorkflowResult;
use JayI\Cortex\Plugins\Workflow\WorkflowState;
use JayI\Cortex\Plugins\Workflow\WorkflowStatus;

describe('PersistentWorkflowExecutor', function () {
    beforeEach(function () {
        // Disable events via config to avoid event dispatch overhead in tests
        config()->set('cortex.events.enabled', false);

        $this->repository = Mockery::mock(WorkflowStateRepositoryContract::class);
        $this->baseExecutor = Mockery::mock(WorkflowExecutor::class);
        $this->executor = new PersistentWorkflowExecutor(
            $this->baseExecutor,
            $this->repository,
        );
    });

    function createWorkflowDefinition(string $id = 'test-workflow', string $entryNode = 'start'): WorkflowDefinition
    {
        return new WorkflowDefinition(
            id: $id,
            name: $id,
            description: 'Test workflow',
            nodes: [],
            edges: [],
            entryNode: $entryNode,
        );
    }

    test('executes workflow and persists state', function () {
        $workflow = Mockery::mock(WorkflowContract::class);
        $workflow->shouldReceive('definition')->andReturn(createWorkflowDefinition('test-workflow', 'start'));

        $completedState = new WorkflowState(
            workflowId: 'test-workflow',
            runId: 'run-123',
            currentNode: null,
            status: WorkflowStatus::Completed,
            data: ['result' => 'done'],
        );
        $completedResult = WorkflowResult::completed($completedState);

        // Repository should save initial state and final state
        $this->repository->shouldReceive('save')->twice();

        // Base executor should be called to resume
        $this->baseExecutor->shouldReceive('resume')->andReturn($completedResult);

        $result = $this->executor->execute($workflow, ['input' => 'value']);

        expect($result->completed)->toBeTrue();
        expect($result->state->status)->toBe(WorkflowStatus::Completed);
    });

    test('persists state with custom context', function () {
        $workflow = Mockery::mock(WorkflowContract::class);
        $workflow->shouldReceive('definition')->andReturn(createWorkflowDefinition('ctx-workflow', 'start'));

        $completedState = new WorkflowState(
            workflowId: 'ctx-workflow',
            runId: 'custom-correlation-id',
            currentNode: null,
            status: WorkflowStatus::Completed,
            data: ['ok' => true],
        );
        $completedResult = WorkflowResult::completed($completedState);

        $this->repository->shouldReceive('save')->twice();
        $this->baseExecutor->shouldReceive('resume')->andReturn($completedResult);

        $context = new WorkflowContext(correlationId: 'custom-correlation-id');
        $result = $this->executor->execute($workflow, [], $context);

        expect($result->state->runId)->toBe('custom-correlation-id');
    });

    test('resumes paused workflow', function () {
        $workflow = Mockery::mock(WorkflowContract::class);

        $pausedState = new WorkflowState(
            workflowId: 'pause-workflow',
            runId: 'run-paused',
            currentNode: 'pause-node',
            status: WorkflowStatus::Paused,
            pauseReason: 'Waiting for continue signal',
        );

        $completedState = new WorkflowState(
            workflowId: 'pause-workflow',
            runId: 'run-paused',
            currentNode: null,
            status: WorkflowStatus::Completed,
            data: ['resumed' => true],
        );
        $completedResult = WorkflowResult::completed($completedState);

        $this->repository->shouldReceive('save')->twice();
        $this->baseExecutor->shouldReceive('resume')->andReturn($completedResult);

        $result = $this->executor->resume($workflow, $pausedState, ['continue' => true]);

        expect($result->completed)->toBeTrue();
        expect($result->state->data['resumed'])->toBeTrue();
    });

    test('resumes workflow by run id', function () {
        $workflow = Mockery::mock(WorkflowContract::class);

        $pausedState = new WorkflowState(
            workflowId: 'resume-by-id',
            runId: 'run-to-resume',
            currentNode: 'node',
            status: WorkflowStatus::Paused,
        );

        $completedState = new WorkflowState(
            workflowId: 'resume-by-id',
            runId: 'run-to-resume',
            currentNode: null,
            status: WorkflowStatus::Completed,
            data: ['done' => true],
        );
        $completedResult = WorkflowResult::completed($completedState);

        $this->repository->shouldReceive('find')
            ->with('run-to-resume')
            ->andReturn($pausedState);
        $this->repository->shouldReceive('save')->twice();
        $this->baseExecutor->shouldReceive('resume')->andReturn($completedResult);

        $result = $this->executor->resumeByRunId($workflow, 'run-to-resume', ['ready' => true]);

        expect($result->completed)->toBeTrue();
    });

    test('throws exception when resuming non-existent run', function () {
        $workflow = Mockery::mock(WorkflowContract::class);

        $this->repository->shouldReceive('find')
            ->with('non-existent')
            ->andReturn(null);

        expect(fn() => $this->executor->resumeByRunId($workflow, 'non-existent'))
            ->toThrow(WorkflowNotFoundException::class);
    });

    test('throws exception when resuming non-paused workflow', function () {
        $workflow = Mockery::mock(WorkflowContract::class);

        $completedState = new WorkflowState(
            workflowId: 'completed-workflow',
            runId: 'run-completed',
            currentNode: null,
            status: WorkflowStatus::Completed,
        );

        expect(fn() => $this->executor->resume($workflow, $completedState))
            ->toThrow(WorkflowNotPausedException::class);
    });

    test('gets state by run id', function () {
        $state = new WorkflowState(
            workflowId: 'get-state-test',
            runId: 'run-to-get',
            currentNode: 'node',
            status: WorkflowStatus::Running,
            data: ['data' => 123],
        );

        $this->repository->shouldReceive('find')
            ->with('run-to-get')
            ->andReturn($state);

        $found = $this->executor->getState('run-to-get');

        expect($found)->not()->toBeNull();
        expect($found->data['data'])->toBe(123);
    });

    test('returns null for non-existent state', function () {
        $this->repository->shouldReceive('find')
            ->with('does-not-exist')
            ->andReturn(null);

        $state = $this->executor->getState('does-not-exist');

        expect($state)->toBeNull();
    });

    test('sets max steps on base executor', function () {
        $this->baseExecutor->shouldReceive('maxSteps')
            ->with(100)
            ->andReturn($this->baseExecutor);

        $result = $this->executor->maxSteps(100);

        expect($result)->toBe($this->executor);
    });

    test('persists failed workflow state', function () {
        $workflow = Mockery::mock(WorkflowContract::class);
        $workflow->shouldReceive('definition')->andReturn(createWorkflowDefinition('failing-workflow', 'fail'));

        $failedState = new WorkflowState(
            workflowId: 'failing-workflow',
            runId: 'run-failed',
            currentNode: 'fail',
            status: WorkflowStatus::Failed,
        );
        $failedResult = WorkflowResult::failed($failedState, 'Something went wrong');

        $this->repository->shouldReceive('save')->twice();
        $this->baseExecutor->shouldReceive('resume')->andReturn($failedResult);

        $result = $this->executor->execute($workflow);

        expect($result->state->status)->toBe(WorkflowStatus::Failed);
    });

    test('persists paused workflow state', function () {
        $workflow = Mockery::mock(WorkflowContract::class);
        $workflow->shouldReceive('definition')->andReturn(createWorkflowDefinition('pausing-workflow', 'start'));

        $pausedState = new WorkflowState(
            workflowId: 'pausing-workflow',
            runId: 'run-paused',
            currentNode: 'wait-node',
            status: WorkflowStatus::Paused,
            pauseReason: 'Waiting for user input',
        );
        $pausedResult = WorkflowResult::paused($pausedState, 'Waiting for user input');

        $this->repository->shouldReceive('save')->twice();
        $this->baseExecutor->shouldReceive('resume')->andReturn($pausedResult);

        $result = $this->executor->execute($workflow);

        expect($result->paused)->toBeTrue();
        expect($result->state->status)->toBe(WorkflowStatus::Paused);
    });
});
