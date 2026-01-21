<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use JayI\Cortex\Plugins\Agent\AgentContext;
use JayI\Cortex\Plugins\Agent\AgentRunStatus;
use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;
use JayI\Cortex\Plugins\Agent\PendingAgentRun;

describe('PendingAgentRun', function () {
    it('creates with agent and input', function () {
        $agent = Mockery::mock(AgentContract::class);

        $pending = new PendingAgentRun($agent, 'test input');

        expect($pending->agent)->toBe($agent);
        expect($pending->input)->toBe('test input');
        expect($pending->runId)->not()->toBeEmpty();
    });

    it('creates with agent id and array input', function () {
        $pending = new PendingAgentRun('my-agent', ['key' => 'value']);

        expect($pending->agent)->toBe('my-agent');
        expect($pending->input)->toBe(['key' => 'value']);
    });

    it('creates with context', function () {
        $agent = Mockery::mock(AgentContract::class);
        $context = new AgentContext;

        $pending = new PendingAgentRun($agent, 'input', $context);

        expect($pending->context)->toBe($context);
    });

    it('returns run id', function () {
        $agent = Mockery::mock(AgentContract::class);
        $pending = new PendingAgentRun($agent, 'input');

        expect($pending->id())->toBe($pending->runId);
    });

    it('sets custom queue', function () {
        $agent = Mockery::mock(AgentContract::class);
        $pending = new PendingAgentRun($agent, 'input');

        $result = $pending->onQueue('high-priority');

        expect($result)->toBe($pending);
    });

    it('sets broadcast channel', function () {
        $agent = Mockery::mock(AgentContract::class);
        $pending = new PendingAgentRun($agent, 'input');

        $result = $pending->broadcastTo('agent.run.123');

        expect($result)->toBe($pending);
    });

    it('returns pending status for unknown run', function () {
        Cache::flush();

        $status = PendingAgentRun::status('unknown-run-id');

        expect($status)->toBe(AgentRunStatus::Pending);
    });

    it('returns null error for unknown run', function () {
        Cache::flush();

        $error = PendingAgentRun::error('unknown-run-id');

        expect($error)->toBeNull();
    });

    it('returns null result for unknown run', function () {
        Cache::flush();

        $result = PendingAgentRun::result('unknown-run-id');

        expect($result)->toBeNull();
    });

    it('status retrieves stored status', function () {
        Cache::flush();
        Cache::put('cortex:agent_run:test-run:status', [
            'status' => AgentRunStatus::Running->value,
            'error' => null,
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(24));

        $status = PendingAgentRun::status('test-run');

        expect($status)->toBe(AgentRunStatus::Running);
    });

    it('error retrieves stored error', function () {
        Cache::flush();
        Cache::put('cortex:agent_run:test-run:status', [
            'status' => AgentRunStatus::Failed->value,
            'error' => 'Something went wrong',
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(24));

        $error = PendingAgentRun::error('test-run');

        expect($error)->toBe('Something went wrong');
    });

    it('result retrieves stored response', function () {
        Cache::flush();
        $response = new \JayI\Cortex\Plugins\Agent\AgentResponse(
            content: 'Done',
            messages: new \JayI\Cortex\Plugins\Chat\Messages\MessageCollection,
            iterationCount: 1,
            iterations: [],
            totalUsage: \JayI\Cortex\Plugins\Chat\Usage::zero(),
            stopReason: \JayI\Cortex\Plugins\Agent\AgentStopReason::Completed,
        );

        Cache::put('cortex:agent_run:test-run:result', $response, now()->addHours(24));

        $result = PendingAgentRun::result('test-run');

        expect($result)->toBeInstanceOf(\JayI\Cortex\Plugins\Agent\AgentResponse::class);
        expect($result->content)->toBe('Done');
    });

    it('handle executes agent and stores result', function () {
        Cache::flush();
        $response = new \JayI\Cortex\Plugins\Agent\AgentResponse(
            content: 'Task completed',
            messages: new \JayI\Cortex\Plugins\Chat\Messages\MessageCollection,
            iterationCount: 2,
            iterations: [],
            totalUsage: new \JayI\Cortex\Plugins\Chat\Usage(100, 50, 150),
            stopReason: \JayI\Cortex\Plugins\Agent\AgentStopReason::Completed,
        );

        $mockAgent = Mockery::mock(AgentContract::class);
        $mockAgent->shouldReceive('run')
            ->once()
            ->andReturn($response);

        $run = new PendingAgentRun($mockAgent, 'Do the task');
        $run->handle();

        $status = PendingAgentRun::status($run->id());
        expect($status)->toBe(AgentRunStatus::Completed);

        $result = PendingAgentRun::result($run->id());
        expect($result)->toBeInstanceOf(\JayI\Cortex\Plugins\Agent\AgentResponse::class);
        expect($result->content)->toBe('Task completed');
    });

    it('handle updates status to failed on exception', function () {
        Cache::flush();
        $mockAgent = Mockery::mock(AgentContract::class);
        $mockAgent->shouldReceive('run')
            ->once()
            ->andThrow(new RuntimeException('Agent crashed'));

        $run = new PendingAgentRun($mockAgent, 'Do the task');

        expect(fn () => $run->handle())->toThrow(RuntimeException::class);

        $status = PendingAgentRun::status($run->id());
        expect($status)->toBe(AgentRunStatus::Failed);

        $error = PendingAgentRun::error($run->id());
        expect($error)->toBe('Agent crashed');
    });

    it('handle resolves agent from registry when string provided', function () {
        Cache::flush();
        $response = new \JayI\Cortex\Plugins\Agent\AgentResponse(
            content: 'Done',
            messages: new \JayI\Cortex\Plugins\Chat\Messages\MessageCollection,
            iterationCount: 1,
            iterations: [],
            totalUsage: \JayI\Cortex\Plugins\Chat\Usage::zero(),
            stopReason: \JayI\Cortex\Plugins\Agent\AgentStopReason::Completed,
        );

        $mockAgent = Mockery::mock(AgentContract::class);
        $mockAgent->shouldReceive('run')->andReturn($response);

        $mockRegistry = Mockery::mock(\JayI\Cortex\Plugins\Agent\Contracts\AgentRegistryContract::class);
        $mockRegistry->shouldReceive('get')
            ->with('my-agent')
            ->andReturn($mockAgent);

        app()->instance(\JayI\Cortex\Plugins\Agent\Contracts\AgentRegistryContract::class, $mockRegistry);

        $run = new PendingAgentRun('my-agent', 'Test');
        $run->handle();

        $status = PendingAgentRun::status($run->id());
        expect($status)->toBe(AgentRunStatus::Completed);
    });
});

describe('AgentRunStatus', function () {
    it('pending status is not complete', function () {
        expect(AgentRunStatus::Pending->isComplete())->toBeFalse();
        expect(AgentRunStatus::Pending->isPending())->toBeTrue();
    });

    it('running status is not complete', function () {
        expect(AgentRunStatus::Running->isComplete())->toBeFalse();
        expect(AgentRunStatus::Running->isRunning())->toBeTrue();
    });

    it('completed status is complete', function () {
        expect(AgentRunStatus::Completed->isComplete())->toBeTrue();
        expect(AgentRunStatus::Completed->isTerminal())->toBeTrue();
        expect(AgentRunStatus::Completed->isSuccessful())->toBeTrue();
    });

    it('failed status is complete', function () {
        expect(AgentRunStatus::Failed->isComplete())->toBeTrue();
        expect(AgentRunStatus::Failed->isTerminal())->toBeTrue();
        expect(AgentRunStatus::Failed->isSuccessful())->toBeFalse();
    });
});
