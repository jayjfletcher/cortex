<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Workflow\NodeResult;
use JayI\Cortex\Plugins\Workflow\WorkflowHistoryEntry;
use JayI\Cortex\Plugins\Workflow\WorkflowResult;
use JayI\Cortex\Plugins\Workflow\WorkflowState;
use JayI\Cortex\Plugins\Workflow\WorkflowStatus;

describe('WorkflowStatus', function () {
    it('has correct terminal states', function () {
        expect(WorkflowStatus::Pending->isTerminal())->toBeFalse();
        expect(WorkflowStatus::Running->isTerminal())->toBeFalse();
        expect(WorkflowStatus::Paused->isTerminal())->toBeFalse();
        expect(WorkflowStatus::Completed->isTerminal())->toBeTrue();
        expect(WorkflowStatus::Failed->isTerminal())->toBeTrue();
        expect(WorkflowStatus::Cancelled->isTerminal())->toBeTrue();
    });

    it('has correct resumable states', function () {
        expect(WorkflowStatus::Pending->canResume())->toBeTrue();
        expect(WorkflowStatus::Running->canResume())->toBeFalse();
        expect(WorkflowStatus::Paused->canResume())->toBeTrue();
        expect(WorkflowStatus::Completed->canResume())->toBeFalse();
        expect(WorkflowStatus::Failed->canResume())->toBeFalse();
        expect(WorkflowStatus::Cancelled->canResume())->toBeFalse();
    });

    it('has correct active states', function () {
        expect(WorkflowStatus::Pending->isActive())->toBeTrue();
        expect(WorkflowStatus::Running->isActive())->toBeTrue();
        expect(WorkflowStatus::Paused->isActive())->toBeFalse();
        expect(WorkflowStatus::Completed->isActive())->toBeFalse();
    });
});

describe('WorkflowState', function () {
    it('creates state with start factory', function () {
        $state = WorkflowState::start('workflow-1', 'run-1', 'step1');

        expect($state->workflowId)->toBe('workflow-1');
        expect($state->runId)->toBe('run-1');
        expect($state->currentNode)->toBe('step1');
        expect($state->status)->toBe(WorkflowStatus::Running);
        expect($state->startedAt)->not->toBeNull();
    });

    it('creates state with defaults', function () {
        $state = new WorkflowState(
            workflowId: 'workflow-1',
            runId: 'run-1',
        );

        expect($state->workflowId)->toBe('workflow-1');
        expect($state->runId)->toBe('run-1');
        expect($state->status)->toBe(WorkflowStatus::Pending);
        expect($state->currentNode)->toBeNull();
        expect($state->data)->toBe([]);
        expect($state->history)->toBe([]);
    });

    it('gets and sets data values', function () {
        $state = new WorkflowState(
            workflowId: 'workflow-1',
            runId: 'run-1',
            data: ['name' => 'John', 'age' => 30],
        );

        expect($state->get('name'))->toBe('John');
        expect($state->get('age'))->toBe(30);
        expect($state->get('nonexistent'))->toBeNull();
        expect($state->get('nonexistent', 'default'))->toBe('default');
    });

    it('sets data immutably', function () {
        $original = new WorkflowState(
            workflowId: 'workflow-1',
            runId: 'run-1',
            data: ['a' => 1],
        );

        $updated = $original->set('b', 2);

        expect($original->get('b'))->toBeNull();
        expect($updated->get('a'))->toBe(1);
        expect($updated->get('b'))->toBe(2);
    });

    it('merges data immutably', function () {
        $original = new WorkflowState(
            workflowId: 'workflow-1',
            runId: 'run-1',
            data: ['a' => 1, 'b' => 2],
        );

        $merged = $original->merge(['b' => 20, 'c' => 3]);

        expect($original->get('b'))->toBe(2);
        expect($merged->get('a'))->toBe(1);
        expect($merged->get('b'))->toBe(20);
        expect($merged->get('c'))->toBe(3);
    });

    it('moves to node immutably', function () {
        $original = new WorkflowState(
            workflowId: 'workflow-1',
            runId: 'run-1',
            currentNode: 'step1',
        );

        $moved = $original->moveTo('step2');

        expect($original->currentNode)->toBe('step1');
        expect($moved->currentNode)->toBe('step2');
    });

    it('pauses workflow', function () {
        $state = WorkflowState::start('wf', 'run', 'step1');

        $paused = $state->pause('Waiting for input');

        expect($paused->status)->toBe(WorkflowStatus::Paused);
        expect($paused->pauseReason)->toBe('Waiting for input');
        expect($paused->pausedAt)->not->toBeNull();
    });

    it('resumes paused workflow', function () {
        $paused = WorkflowState::start('wf', 'run', 'step1')->pause('reason');

        $resumed = $paused->resume();

        expect($resumed->status)->toBe(WorkflowStatus::Running);
        expect($resumed->pauseReason)->toBeNull();
        expect($resumed->pausedAt)->toBeNull();
    });

    it('completes workflow', function () {
        $state = WorkflowState::start('wf', 'run', 'step1');

        $completed = $state->complete();

        expect($completed->status)->toBe(WorkflowStatus::Completed);
        expect($completed->completedAt)->not->toBeNull();
    });

    it('fails workflow', function () {
        $state = WorkflowState::start('wf', 'run', 'step1');

        $failed = $state->fail();

        expect($failed->status)->toBe(WorkflowStatus::Failed);
        expect($failed->completedAt)->not->toBeNull();
    });

    it('records node execution history', function () {
        $state = WorkflowState::start('wf', 'run', 'step1');

        $updated = $state
            ->recordNodeExecution('step1', ['in' => 'a'], ['out' => 'b'], 0.1)
            ->recordNodeExecution('step2', ['in' => 'b'], ['out' => 'c'], 0.2);

        expect($state->history)->toHaveCount(0);
        expect($updated->history)->toHaveCount(2);
        expect($updated->history[0]->nodeId)->toBe('step1');
        expect($updated->history[1]->nodeId)->toBe('step2');
    });

    it('records errors in history', function () {
        $state = WorkflowState::start('wf', 'run', 'step1');

        $updated = $state->recordNodeExecution('step1', ['in' => 'a'], [], 0.1, 'Something went wrong');

        expect($updated->history[0]->error)->toBe('Something went wrong');
        expect($updated->history[0]->success)->toBeFalse();
    });
});

describe('WorkflowHistoryEntry', function () {
    it('creates success history entry', function () {
        $entry = WorkflowHistoryEntry::success(
            'step1',
            ['input' => 'value'],
            ['output' => 'result'],
            0.123
        );

        expect($entry->nodeId)->toBe('step1');
        expect($entry->input)->toBe(['input' => 'value']);
        expect($entry->output)->toBe(['output' => 'result']);
        expect($entry->duration)->toBe(0.123);
        expect($entry->success)->toBeTrue();
        expect($entry->error)->toBeNull();
        expect($entry->executedAt)->not->toBeNull();
    });

    it('creates failure history entry', function () {
        $entry = WorkflowHistoryEntry::failure(
            'step1',
            ['input' => 'value'],
            'Failed to execute',
            0.456
        );

        expect($entry->nodeId)->toBe('step1');
        expect($entry->success)->toBeFalse();
        expect($entry->error)->toBe('Failed to execute');
        expect($entry->output)->toBe([]);
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

    it('creates failure result', function () {
        $result = NodeResult::failure('Something went wrong');

        expect($result->success)->toBeFalse();
        expect($result->output)->toBe([]);
        expect($result->error)->toBe('Something went wrong');
    });

    it('creates pause result', function () {
        $result = NodeResult::pause('Waiting for input', ['data' => 'state']);

        expect($result->success)->toBeTrue();
        expect($result->shouldPause)->toBeTrue();
        expect($result->pauseReason)->toBe('Waiting for input');
        expect($result->output)->toBe(['data' => 'state']);
    });

    it('creates goto result', function () {
        $result = NodeResult::goto('step3', ['output' => 'value']);

        expect($result->success)->toBeTrue();
        expect($result->nextNode)->toBe('step3');
        expect($result->output)->toBe(['output' => 'value']);
    });
});

describe('WorkflowResult', function () {
    it('creates completed result', function () {
        $state = WorkflowState::start('wf', 'run', 'step1')->complete();
        $state = $state->merge(['result' => 'done']);

        $result = WorkflowResult::completed($state);

        expect($result->state)->toBe($state);
        expect($result->paused)->toBeFalse();
        expect($result->pauseReason)->toBeNull();
        expect($result->error)->toBeNull();
        expect($result->isCompleted())->toBeTrue();
        expect($result->isFailed())->toBeFalse();
    });

    it('creates failed result', function () {
        $state = WorkflowState::start('wf', 'run', 'step1')->fail();

        $result = WorkflowResult::failed($state, 'Execution failed');

        expect($result->error)->toBe('Execution failed');
        expect($result->isFailed())->toBeTrue();
        expect($result->isCompleted())->toBeFalse();
    });

    it('creates paused result', function () {
        $state = WorkflowState::start('wf', 'run', 'step1')->pause('Waiting for human input');

        $result = WorkflowResult::paused($state, 'Waiting for human input');

        expect($result->paused)->toBeTrue();
        expect($result->pauseReason)->toBe('Waiting for human input');
        expect($result->isPaused())->toBeTrue();
    });

    it('gets output from state data', function () {
        $state = WorkflowState::start('wf', 'run', 'step1')
            ->merge(['result' => 'done', 'count' => 42])
            ->complete();

        $result = WorkflowResult::completed($state);

        expect($result->output())->toMatchArray(['result' => 'done', 'count' => 42]);
    });

    it('gets value from state', function () {
        $state = WorkflowState::start('wf', 'run', 'step1')
            ->merge(['name' => 'John'])
            ->complete();

        $result = WorkflowResult::completed($state);

        expect($result->get('name'))->toBe('John');
        expect($result->get('missing', 'default'))->toBe('default');
    });
});
