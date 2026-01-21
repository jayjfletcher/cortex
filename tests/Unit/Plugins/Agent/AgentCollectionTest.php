<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Agent\AgentCollection;
use JayI\Cortex\Plugins\Agent\AgentTool;
use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;
use JayI\Cortex\Plugins\Tool\ToolCollection;

describe('AgentCollection', function () {
    it('creates an empty collection', function () {
        $collection = AgentCollection::make([]);

        expect($collection->count())->toBe(0);
        expect($collection->isEmpty())->toBeTrue();
        expect($collection->isNotEmpty())->toBeFalse();
    });

    it('creates collection with agent instances', function () {
        $agent1 = Mockery::mock(AgentContract::class);
        $agent1->shouldReceive('id')->andReturn('agent-1');

        $agent2 = Mockery::mock(AgentContract::class);
        $agent2->shouldReceive('id')->andReturn('agent-2');

        $collection = AgentCollection::make([$agent1, $agent2]);

        expect($collection->count())->toBe(2);
        expect($collection->has('agent-1'))->toBeTrue();
        expect($collection->has('agent-2'))->toBeTrue();
    });

    it('adds agents to collection', function () {
        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('id')->andReturn('agent-1');

        $collection = AgentCollection::make([]);
        $collection = $collection->add($agent);

        expect($collection->count())->toBe(1);
        expect($collection->has('agent-1'))->toBeTrue();
    });

    it('removes agents from collection', function () {
        $agent1 = Mockery::mock(AgentContract::class);
        $agent1->shouldReceive('id')->andReturn('agent-1');

        $agent2 = Mockery::mock(AgentContract::class);
        $agent2->shouldReceive('id')->andReturn('agent-2');

        $collection = AgentCollection::make([$agent1, $agent2]);
        $collection = $collection->remove('agent-1');

        expect($collection->count())->toBe(1);
        expect($collection->has('agent-1'))->toBeFalse();
        expect($collection->has('agent-2'))->toBeTrue();
    });

    it('gets agent by id', function () {
        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('id')->andReturn('agent-1');

        $collection = AgentCollection::make([$agent]);

        expect($collection->get('agent-1'))->toBe($agent);
        expect($collection->get('nonexistent'))->toBeNull();
    });

    it('returns agent ids', function () {
        $agent1 = Mockery::mock(AgentContract::class);
        $agent1->shouldReceive('id')->andReturn('agent-1');

        $agent2 = Mockery::mock(AgentContract::class);
        $agent2->shouldReceive('id')->andReturn('agent-2');

        $collection = AgentCollection::make([$agent1, $agent2]);

        $ids = $collection->ids();

        expect($ids)->toContain('agent-1');
        expect($ids)->toContain('agent-2');
    });

    it('returns only specified agents', function () {
        $agent1 = Mockery::mock(AgentContract::class);
        $agent1->shouldReceive('id')->andReturn('agent-1');

        $agent2 = Mockery::mock(AgentContract::class);
        $agent2->shouldReceive('id')->andReturn('agent-2');

        $agent3 = Mockery::mock(AgentContract::class);
        $agent3->shouldReceive('id')->andReturn('agent-3');

        $collection = AgentCollection::make([$agent1, $agent2, $agent3]);

        $filtered = $collection->only(['agent-1', 'agent-3']);

        expect($filtered->count())->toBe(2);
        expect($filtered->has('agent-1'))->toBeTrue();
        expect($filtered->has('agent-3'))->toBeTrue();
        expect($filtered->has('agent-2'))->toBeFalse();
    });

    it('returns all agents except specified ones', function () {
        $agent1 = Mockery::mock(AgentContract::class);
        $agent1->shouldReceive('id')->andReturn('agent-1');

        $agent2 = Mockery::mock(AgentContract::class);
        $agent2->shouldReceive('id')->andReturn('agent-2');

        $agent3 = Mockery::mock(AgentContract::class);
        $agent3->shouldReceive('id')->andReturn('agent-3');

        $collection = AgentCollection::make([$agent1, $agent2, $agent3]);

        $filtered = $collection->except(['agent-2']);

        expect($filtered->count())->toBe(2);
        expect($filtered->has('agent-1'))->toBeTrue();
        expect($filtered->has('agent-3'))->toBeTrue();
        expect($filtered->has('agent-2'))->toBeFalse();
    });

    it('merges collections', function () {
        $agent1 = Mockery::mock(AgentContract::class);
        $agent1->shouldReceive('id')->andReturn('agent-1');

        $agent2 = Mockery::mock(AgentContract::class);
        $agent2->shouldReceive('id')->andReturn('agent-2');

        $collection1 = AgentCollection::make([$agent1]);
        $collection2 = AgentCollection::make([$agent2]);

        $merged = $collection1->merge($collection2);

        expect($merged->count())->toBe(2);
        expect($merged->has('agent-1'))->toBeTrue();
        expect($merged->has('agent-2'))->toBeTrue();
    });

    it('converts to array', function () {
        $agent = Mockery::mock(AgentContract::class);
        $agent->shouldReceive('id')->andReturn('agent-1');

        $collection = AgentCollection::make([$agent]);

        $array = $collection->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveCount(1);
    });

    it('is iterable', function () {
        $agent1 = Mockery::mock(AgentContract::class);
        $agent1->shouldReceive('id')->andReturn('agent-1');

        $agent2 = Mockery::mock(AgentContract::class);
        $agent2->shouldReceive('id')->andReturn('agent-2');

        $collection = AgentCollection::make([$agent1, $agent2]);

        $count = 0;
        foreach ($collection as $id => $agent) {
            $count++;
        }

        expect($count)->toBe(2);
    });

    it('filters agents', function () {
        $agent1 = Mockery::mock(AgentContract::class);
        $agent1->shouldReceive('id')->andReturn('agent-1');
        $agent1->shouldReceive('name')->andReturn('Research Agent');

        $agent2 = Mockery::mock(AgentContract::class);
        $agent2->shouldReceive('id')->andReturn('agent-2');
        $agent2->shouldReceive('name')->andReturn('Writing Agent');

        $collection = AgentCollection::make([$agent1, $agent2]);

        $filtered = $collection->filter(fn ($agent) => $agent->name() === 'Research Agent');

        expect($filtered->count())->toBe(1);
        expect($filtered->has('agent-1'))->toBeTrue();
    });

    it('maps agents', function () {
        $agent1 = Mockery::mock(AgentContract::class);
        $agent1->shouldReceive('id')->andReturn('agent-1');
        $agent1->shouldReceive('name')->andReturn('Research Agent');

        $agent2 = Mockery::mock(AgentContract::class);
        $agent2->shouldReceive('id')->andReturn('agent-2');
        $agent2->shouldReceive('name')->andReturn('Writing Agent');

        $collection = AgentCollection::make([$agent1, $agent2]);

        $mapped = $collection->map(fn ($agent) => $agent->name());

        expect($mapped)->toBe([
            'agent-1' => 'Research Agent',
            'agent-2' => 'Writing Agent',
        ]);
    });

    it('converts agents to tools', function () {
        $agent1 = Mockery::mock(AgentContract::class);
        $agent1->shouldReceive('id')->andReturn('agent-1');

        $agent2 = Mockery::mock(AgentContract::class);
        $agent2->shouldReceive('id')->andReturn('agent-2');

        $collection = AgentCollection::make([$agent1, $agent2]);

        $tools = $collection->asTools();

        expect($tools)->toBeInstanceOf(ToolCollection::class);
        expect($tools->count())->toBe(2);
        expect($tools->has('agent-1'))->toBeTrue();
        expect($tools->has('agent-2'))->toBeTrue();

        // Verify each tool is an AgentTool
        expect($tools->find('agent-1'))->toBeInstanceOf(AgentTool::class);
        expect($tools->find('agent-2'))->toBeInstanceOf(AgentTool::class);
    });

    it('returns empty tool collection for empty agent collection', function () {
        $collection = AgentCollection::make([]);

        $tools = $collection->asTools();

        expect($tools)->toBeInstanceOf(ToolCollection::class);
        expect($tools->isEmpty())->toBeTrue();
    });
});
