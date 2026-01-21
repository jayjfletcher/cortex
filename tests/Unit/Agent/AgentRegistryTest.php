<?php

declare(strict_types=1);

use JayI\Cortex\Exceptions\AgentException;
use JayI\Cortex\Plugins\Agent\Agent;
use JayI\Cortex\Plugins\Agent\AgentRegistry;

describe('AgentRegistry', function () {
    it('registers and retrieves an agent', function () {
        $registry = new AgentRegistry();
        $agent = Agent::make('test-agent')->withName('Test Agent');

        $registry->register($agent);

        expect($registry->has('test-agent'))->toBeTrue();
        expect($registry->get('test-agent'))->toBe($agent);
    });

    it('checks if agent exists', function () {
        $registry = new AgentRegistry();

        expect($registry->has('nonexistent'))->toBeFalse();

        $registry->register(Agent::make('exists'));

        expect($registry->has('exists'))->toBeTrue();
        expect($registry->has('nonexistent'))->toBeFalse();
    });

    it('throws exception when agent not found', function () {
        $registry = new AgentRegistry();

        expect(fn () => $registry->get('nonexistent'))
            ->toThrow(AgentException::class);
    });

    it('returns all registered agents', function () {
        $registry = new AgentRegistry();

        $agent1 = Agent::make('agent-1')->withName('Agent 1');
        $agent2 = Agent::make('agent-2')->withName('Agent 2');
        $agent3 = Agent::make('agent-3')->withName('Agent 3');

        $registry->register($agent1);
        $registry->register($agent2);
        $registry->register($agent3);

        $all = $registry->all();

        expect($all)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($all->count())->toBe(3);
        expect($all->has('agent-1'))->toBeTrue();
        expect($all->has('agent-2'))->toBeTrue();
        expect($all->has('agent-3'))->toBeTrue();
    });

    it('overwrites agents with same id', function () {
        $registry = new AgentRegistry();

        $agent1 = Agent::make('same-id')->withName('First');
        $agent2 = Agent::make('same-id')->withName('Second');

        $registry->register($agent1);
        $registry->register($agent2);

        expect($registry->all()->count())->toBe(1);
        expect($registry->get('same-id')->name())->toBe('Second');
    });

    it('discovers agents from paths', function () {
        $registry = new AgentRegistry();

        // discover() is a stub that doesn't do anything yet
        $registry->discover(['/path/to/agents']);

        // Should not throw
        expect(true)->toBeTrue();
    });
});

describe('AgentException', function () {
    it('creates not found exception', function () {
        $exception = AgentException::notFound('test-agent');

        expect($exception)->toBeInstanceOf(AgentException::class);
        expect($exception->getMessage())->toContain('test-agent');
        expect($exception->getMessage())->toContain('not found');
    });

    it('creates run failed exception', function () {
        $previous = new RuntimeException('Inner error');
        $exception = AgentException::runFailed('agent-1', 'Something went wrong', $previous);

        expect($exception)->toBeInstanceOf(AgentException::class);
        expect($exception->getMessage())->toContain('agent-1');
        expect($exception->getMessage())->toContain('run failed');
        expect($exception->getMessage())->toContain('Something went wrong');
        expect($exception->getPrevious())->toBe($previous);
    });

    it('creates run failed exception without previous', function () {
        $exception = AgentException::runFailed('agent-1', 'Error message');

        expect($exception)->toBeInstanceOf(AgentException::class);
        expect($exception->getPrevious())->toBeNull();
    });

    it('creates max iterations exceeded exception', function () {
        $exception = AgentException::maxIterationsExceeded('agent-1', 10);

        expect($exception)->toBeInstanceOf(AgentException::class);
        expect($exception->getMessage())->toContain('agent-1');
        expect($exception->getMessage())->toContain('max iterations');
        expect($exception->getMessage())->toContain('10');
    });

    it('creates invalid loop strategy exception', function () {
        $exception = AgentException::invalidLoopStrategy('invalid-strategy');

        expect($exception)->toBeInstanceOf(AgentException::class);
        expect($exception->getMessage())->toContain('invalid-strategy');
        expect($exception->getMessage())->toContain('Invalid');
    });
});
