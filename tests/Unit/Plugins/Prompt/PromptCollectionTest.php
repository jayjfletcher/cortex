<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Prompt\Contracts\PromptContract;
use JayI\Cortex\Plugins\Prompt\PromptCollection;

describe('PromptCollection', function () {
    it('creates an empty collection', function () {
        $collection = PromptCollection::make([]);

        expect($collection->count())->toBe(0);
        expect($collection->isEmpty())->toBeTrue();
        expect($collection->isNotEmpty())->toBeFalse();
    });

    it('creates collection with prompt instances', function () {
        $prompt1 = Mockery::mock(PromptContract::class);
        $prompt1->shouldReceive('id')->andReturn('prompt-1');

        $prompt2 = Mockery::mock(PromptContract::class);
        $prompt2->shouldReceive('id')->andReturn('prompt-2');

        $collection = PromptCollection::make([$prompt1, $prompt2]);

        expect($collection->count())->toBe(2);
        expect($collection->has('prompt-1'))->toBeTrue();
        expect($collection->has('prompt-2'))->toBeTrue();
    });

    it('adds prompts to collection', function () {
        $prompt = Mockery::mock(PromptContract::class);
        $prompt->shouldReceive('id')->andReturn('prompt-1');

        $collection = PromptCollection::make([]);
        $collection = $collection->add($prompt);

        expect($collection->count())->toBe(1);
        expect($collection->has('prompt-1'))->toBeTrue();
    });

    it('removes prompts from collection', function () {
        $prompt1 = Mockery::mock(PromptContract::class);
        $prompt1->shouldReceive('id')->andReturn('prompt-1');

        $prompt2 = Mockery::mock(PromptContract::class);
        $prompt2->shouldReceive('id')->andReturn('prompt-2');

        $collection = PromptCollection::make([$prompt1, $prompt2]);
        $collection = $collection->remove('prompt-1');

        expect($collection->count())->toBe(1);
        expect($collection->has('prompt-1'))->toBeFalse();
        expect($collection->has('prompt-2'))->toBeTrue();
    });

    it('gets prompt by id', function () {
        $prompt = Mockery::mock(PromptContract::class);
        $prompt->shouldReceive('id')->andReturn('prompt-1');

        $collection = PromptCollection::make([$prompt]);

        expect($collection->get('prompt-1'))->toBe($prompt);
        expect($collection->get('nonexistent'))->toBeNull();
    });

    it('returns prompt ids', function () {
        $prompt1 = Mockery::mock(PromptContract::class);
        $prompt1->shouldReceive('id')->andReturn('prompt-1');

        $prompt2 = Mockery::mock(PromptContract::class);
        $prompt2->shouldReceive('id')->andReturn('prompt-2');

        $collection = PromptCollection::make([$prompt1, $prompt2]);

        $ids = $collection->ids();

        expect($ids)->toContain('prompt-1');
        expect($ids)->toContain('prompt-2');
    });

    it('returns only specified prompts', function () {
        $prompt1 = Mockery::mock(PromptContract::class);
        $prompt1->shouldReceive('id')->andReturn('prompt-1');

        $prompt2 = Mockery::mock(PromptContract::class);
        $prompt2->shouldReceive('id')->andReturn('prompt-2');

        $prompt3 = Mockery::mock(PromptContract::class);
        $prompt3->shouldReceive('id')->andReturn('prompt-3');

        $collection = PromptCollection::make([$prompt1, $prompt2, $prompt3]);

        $filtered = $collection->only(['prompt-1', 'prompt-3']);

        expect($filtered->count())->toBe(2);
        expect($filtered->has('prompt-1'))->toBeTrue();
        expect($filtered->has('prompt-3'))->toBeTrue();
        expect($filtered->has('prompt-2'))->toBeFalse();
    });

    it('returns all prompts except specified ones', function () {
        $prompt1 = Mockery::mock(PromptContract::class);
        $prompt1->shouldReceive('id')->andReturn('prompt-1');

        $prompt2 = Mockery::mock(PromptContract::class);
        $prompt2->shouldReceive('id')->andReturn('prompt-2');

        $prompt3 = Mockery::mock(PromptContract::class);
        $prompt3->shouldReceive('id')->andReturn('prompt-3');

        $collection = PromptCollection::make([$prompt1, $prompt2, $prompt3]);

        $filtered = $collection->except(['prompt-2']);

        expect($filtered->count())->toBe(2);
        expect($filtered->has('prompt-1'))->toBeTrue();
        expect($filtered->has('prompt-3'))->toBeTrue();
        expect($filtered->has('prompt-2'))->toBeFalse();
    });

    it('merges collections', function () {
        $prompt1 = Mockery::mock(PromptContract::class);
        $prompt1->shouldReceive('id')->andReturn('prompt-1');

        $prompt2 = Mockery::mock(PromptContract::class);
        $prompt2->shouldReceive('id')->andReturn('prompt-2');

        $collection1 = PromptCollection::make([$prompt1]);
        $collection2 = PromptCollection::make([$prompt2]);

        $merged = $collection1->merge($collection2);

        expect($merged->count())->toBe(2);
        expect($merged->has('prompt-1'))->toBeTrue();
        expect($merged->has('prompt-2'))->toBeTrue();
    });

    it('converts to array', function () {
        $prompt = Mockery::mock(PromptContract::class);
        $prompt->shouldReceive('id')->andReturn('prompt-1');

        $collection = PromptCollection::make([$prompt]);

        $array = $collection->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveCount(1);
    });

    it('is iterable', function () {
        $prompt1 = Mockery::mock(PromptContract::class);
        $prompt1->shouldReceive('id')->andReturn('prompt-1');

        $prompt2 = Mockery::mock(PromptContract::class);
        $prompt2->shouldReceive('id')->andReturn('prompt-2');

        $collection = PromptCollection::make([$prompt1, $prompt2]);

        $count = 0;
        foreach ($collection as $id => $prompt) {
            $count++;
        }

        expect($count)->toBe(2);
    });

    it('filters prompts', function () {
        $prompt1 = Mockery::mock(PromptContract::class);
        $prompt1->shouldReceive('id')->andReturn('prompt-1');
        $prompt1->shouldReceive('version')->andReturn('1.0.0');

        $prompt2 = Mockery::mock(PromptContract::class);
        $prompt2->shouldReceive('id')->andReturn('prompt-2');
        $prompt2->shouldReceive('version')->andReturn('2.0.0');

        $collection = PromptCollection::make([$prompt1, $prompt2]);

        $filtered = $collection->filter(fn ($prompt) => $prompt->version() === '1.0.0');

        expect($filtered->count())->toBe(1);
        expect($filtered->has('prompt-1'))->toBeTrue();
    });

    it('maps prompts', function () {
        $prompt1 = Mockery::mock(PromptContract::class);
        $prompt1->shouldReceive('id')->andReturn('prompt-1');
        $prompt1->shouldReceive('version')->andReturn('1.0.0');

        $prompt2 = Mockery::mock(PromptContract::class);
        $prompt2->shouldReceive('id')->andReturn('prompt-2');
        $prompt2->shouldReceive('version')->andReturn('2.0.0');

        $collection = PromptCollection::make([$prompt1, $prompt2]);

        $mapped = $collection->map(fn ($prompt) => $prompt->version());

        expect($mapped)->toBe([
            'prompt-1' => '1.0.0',
            'prompt-2' => '2.0.0',
        ]);
    });
});
