<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract;
use JayI\Cortex\Plugins\Workflow\WorkflowCollection;

describe('WorkflowCollection', function () {
    it('creates an empty collection', function () {
        $collection = WorkflowCollection::make([]);

        expect($collection->count())->toBe(0);
        expect($collection->isEmpty())->toBeTrue();
        expect($collection->isNotEmpty())->toBeFalse();
    });

    it('creates collection with workflow instances', function () {
        $workflow1 = Mockery::mock(WorkflowContract::class);
        $workflow1->shouldReceive('id')->andReturn('workflow-1');

        $workflow2 = Mockery::mock(WorkflowContract::class);
        $workflow2->shouldReceive('id')->andReturn('workflow-2');

        $collection = WorkflowCollection::make([$workflow1, $workflow2]);

        expect($collection->count())->toBe(2);
        expect($collection->has('workflow-1'))->toBeTrue();
        expect($collection->has('workflow-2'))->toBeTrue();
    });

    it('adds workflows to collection', function () {
        $workflow = Mockery::mock(WorkflowContract::class);
        $workflow->shouldReceive('id')->andReturn('workflow-1');

        $collection = WorkflowCollection::make([]);
        $collection = $collection->add($workflow);

        expect($collection->count())->toBe(1);
        expect($collection->has('workflow-1'))->toBeTrue();
    });

    it('removes workflows from collection', function () {
        $workflow1 = Mockery::mock(WorkflowContract::class);
        $workflow1->shouldReceive('id')->andReturn('workflow-1');

        $workflow2 = Mockery::mock(WorkflowContract::class);
        $workflow2->shouldReceive('id')->andReturn('workflow-2');

        $collection = WorkflowCollection::make([$workflow1, $workflow2]);
        $collection = $collection->remove('workflow-1');

        expect($collection->count())->toBe(1);
        expect($collection->has('workflow-1'))->toBeFalse();
        expect($collection->has('workflow-2'))->toBeTrue();
    });

    it('gets workflow by id', function () {
        $workflow = Mockery::mock(WorkflowContract::class);
        $workflow->shouldReceive('id')->andReturn('workflow-1');

        $collection = WorkflowCollection::make([$workflow]);

        expect($collection->get('workflow-1'))->toBe($workflow);
        expect($collection->get('nonexistent'))->toBeNull();
    });

    it('returns workflow ids', function () {
        $workflow1 = Mockery::mock(WorkflowContract::class);
        $workflow1->shouldReceive('id')->andReturn('workflow-1');

        $workflow2 = Mockery::mock(WorkflowContract::class);
        $workflow2->shouldReceive('id')->andReturn('workflow-2');

        $collection = WorkflowCollection::make([$workflow1, $workflow2]);

        $ids = $collection->ids();

        expect($ids)->toContain('workflow-1');
        expect($ids)->toContain('workflow-2');
    });

    it('returns only specified workflows', function () {
        $workflow1 = Mockery::mock(WorkflowContract::class);
        $workflow1->shouldReceive('id')->andReturn('workflow-1');

        $workflow2 = Mockery::mock(WorkflowContract::class);
        $workflow2->shouldReceive('id')->andReturn('workflow-2');

        $workflow3 = Mockery::mock(WorkflowContract::class);
        $workflow3->shouldReceive('id')->andReturn('workflow-3');

        $collection = WorkflowCollection::make([$workflow1, $workflow2, $workflow3]);

        $filtered = $collection->only(['workflow-1', 'workflow-3']);

        expect($filtered->count())->toBe(2);
        expect($filtered->has('workflow-1'))->toBeTrue();
        expect($filtered->has('workflow-3'))->toBeTrue();
        expect($filtered->has('workflow-2'))->toBeFalse();
    });

    it('returns all workflows except specified ones', function () {
        $workflow1 = Mockery::mock(WorkflowContract::class);
        $workflow1->shouldReceive('id')->andReturn('workflow-1');

        $workflow2 = Mockery::mock(WorkflowContract::class);
        $workflow2->shouldReceive('id')->andReturn('workflow-2');

        $workflow3 = Mockery::mock(WorkflowContract::class);
        $workflow3->shouldReceive('id')->andReturn('workflow-3');

        $collection = WorkflowCollection::make([$workflow1, $workflow2, $workflow3]);

        $filtered = $collection->except(['workflow-2']);

        expect($filtered->count())->toBe(2);
        expect($filtered->has('workflow-1'))->toBeTrue();
        expect($filtered->has('workflow-3'))->toBeTrue();
        expect($filtered->has('workflow-2'))->toBeFalse();
    });

    it('merges collections', function () {
        $workflow1 = Mockery::mock(WorkflowContract::class);
        $workflow1->shouldReceive('id')->andReturn('workflow-1');

        $workflow2 = Mockery::mock(WorkflowContract::class);
        $workflow2->shouldReceive('id')->andReturn('workflow-2');

        $collection1 = WorkflowCollection::make([$workflow1]);
        $collection2 = WorkflowCollection::make([$workflow2]);

        $merged = $collection1->merge($collection2);

        expect($merged->count())->toBe(2);
        expect($merged->has('workflow-1'))->toBeTrue();
        expect($merged->has('workflow-2'))->toBeTrue();
    });

    it('converts to array', function () {
        $workflow = Mockery::mock(WorkflowContract::class);
        $workflow->shouldReceive('id')->andReturn('workflow-1');

        $collection = WorkflowCollection::make([$workflow]);

        $array = $collection->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveCount(1);
    });

    it('is iterable', function () {
        $workflow1 = Mockery::mock(WorkflowContract::class);
        $workflow1->shouldReceive('id')->andReturn('workflow-1');

        $workflow2 = Mockery::mock(WorkflowContract::class);
        $workflow2->shouldReceive('id')->andReturn('workflow-2');

        $collection = WorkflowCollection::make([$workflow1, $workflow2]);

        $count = 0;
        foreach ($collection as $id => $workflow) {
            $count++;
        }

        expect($count)->toBe(2);
    });

    it('filters workflows', function () {
        $workflow1 = Mockery::mock(WorkflowContract::class);
        $workflow1->shouldReceive('id')->andReturn('workflow-1');
        $workflow1->shouldReceive('name')->andReturn('Data Pipeline');

        $workflow2 = Mockery::mock(WorkflowContract::class);
        $workflow2->shouldReceive('id')->andReturn('workflow-2');
        $workflow2->shouldReceive('name')->andReturn('ETL Process');

        $collection = WorkflowCollection::make([$workflow1, $workflow2]);

        $filtered = $collection->filter(fn ($workflow) => $workflow->name() === 'Data Pipeline');

        expect($filtered->count())->toBe(1);
        expect($filtered->has('workflow-1'))->toBeTrue();
    });

    it('maps workflows', function () {
        $workflow1 = Mockery::mock(WorkflowContract::class);
        $workflow1->shouldReceive('id')->andReturn('workflow-1');
        $workflow1->shouldReceive('name')->andReturn('Data Pipeline');

        $workflow2 = Mockery::mock(WorkflowContract::class);
        $workflow2->shouldReceive('id')->andReturn('workflow-2');
        $workflow2->shouldReceive('name')->andReturn('ETL Process');

        $collection = WorkflowCollection::make([$workflow1, $workflow2]);

        $mapped = $collection->map(fn ($workflow) => $workflow->name());

        expect($mapped)->toBe([
            'workflow-1' => 'Data Pipeline',
            'workflow-2' => 'ETL Process',
        ]);
    });
});
