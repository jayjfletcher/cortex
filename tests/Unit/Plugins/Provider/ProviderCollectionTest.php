<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;
use JayI\Cortex\Plugins\Provider\ProviderCollection;

describe('ProviderCollection', function () {
    it('creates an empty collection', function () {
        $collection = ProviderCollection::make([]);

        expect($collection->count())->toBe(0);
        expect($collection->isEmpty())->toBeTrue();
        expect($collection->isNotEmpty())->toBeFalse();
    });

    it('creates collection with provider instances', function () {
        $provider1 = Mockery::mock(ProviderContract::class);
        $provider2 = Mockery::mock(ProviderContract::class);

        $collection = ProviderCollection::make([
            'bedrock' => $provider1,
            'openai' => $provider2,
        ]);

        expect($collection->count())->toBe(2);
        expect($collection->has('bedrock'))->toBeTrue();
        expect($collection->has('openai'))->toBeTrue();
    });

    it('adds providers to collection', function () {
        $provider = Mockery::mock(ProviderContract::class);

        $collection = ProviderCollection::make([]);
        $collection = $collection->add('bedrock', $provider);

        expect($collection->count())->toBe(1);
        expect($collection->has('bedrock'))->toBeTrue();
    });

    it('removes providers from collection', function () {
        $provider1 = Mockery::mock(ProviderContract::class);
        $provider2 = Mockery::mock(ProviderContract::class);

        $collection = ProviderCollection::make([
            'bedrock' => $provider1,
            'openai' => $provider2,
        ]);
        $collection = $collection->remove('bedrock');

        expect($collection->count())->toBe(1);
        expect($collection->has('bedrock'))->toBeFalse();
        expect($collection->has('openai'))->toBeTrue();
    });

    it('gets provider by id', function () {
        $provider = Mockery::mock(ProviderContract::class);

        $collection = ProviderCollection::make(['bedrock' => $provider]);

        expect($collection->get('bedrock'))->toBe($provider);
        expect($collection->get('nonexistent'))->toBeNull();
    });

    it('returns provider ids', function () {
        $provider1 = Mockery::mock(ProviderContract::class);
        $provider2 = Mockery::mock(ProviderContract::class);

        $collection = ProviderCollection::make([
            'bedrock' => $provider1,
            'openai' => $provider2,
        ]);

        $ids = $collection->ids();

        expect($ids)->toContain('bedrock');
        expect($ids)->toContain('openai');
    });

    it('returns only specified providers', function () {
        $provider1 = Mockery::mock(ProviderContract::class);
        $provider2 = Mockery::mock(ProviderContract::class);
        $provider3 = Mockery::mock(ProviderContract::class);

        $collection = ProviderCollection::make([
            'bedrock' => $provider1,
            'openai' => $provider2,
            'anthropic' => $provider3,
        ]);

        $filtered = $collection->only(['bedrock', 'anthropic']);

        expect($filtered->count())->toBe(2);
        expect($filtered->has('bedrock'))->toBeTrue();
        expect($filtered->has('anthropic'))->toBeTrue();
        expect($filtered->has('openai'))->toBeFalse();
    });

    it('returns all providers except specified ones', function () {
        $provider1 = Mockery::mock(ProviderContract::class);
        $provider2 = Mockery::mock(ProviderContract::class);
        $provider3 = Mockery::mock(ProviderContract::class);

        $collection = ProviderCollection::make([
            'bedrock' => $provider1,
            'openai' => $provider2,
            'anthropic' => $provider3,
        ]);

        $filtered = $collection->except(['openai']);

        expect($filtered->count())->toBe(2);
        expect($filtered->has('bedrock'))->toBeTrue();
        expect($filtered->has('anthropic'))->toBeTrue();
        expect($filtered->has('openai'))->toBeFalse();
    });

    it('merges collections', function () {
        $provider1 = Mockery::mock(ProviderContract::class);
        $provider2 = Mockery::mock(ProviderContract::class);

        $collection1 = ProviderCollection::make(['bedrock' => $provider1]);
        $collection2 = ProviderCollection::make(['openai' => $provider2]);

        $merged = $collection1->merge($collection2);

        expect($merged->count())->toBe(2);
        expect($merged->has('bedrock'))->toBeTrue();
        expect($merged->has('openai'))->toBeTrue();
    });

    it('converts to array', function () {
        $provider = Mockery::mock(ProviderContract::class);

        $collection = ProviderCollection::make(['bedrock' => $provider]);

        $array = $collection->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveCount(1);
        expect($array['bedrock'])->toBe($provider);
    });

    it('is iterable', function () {
        $provider1 = Mockery::mock(ProviderContract::class);
        $provider2 = Mockery::mock(ProviderContract::class);

        $collection = ProviderCollection::make([
            'bedrock' => $provider1,
            'openai' => $provider2,
        ]);

        $count = 0;
        foreach ($collection as $id => $provider) {
            $count++;
        }

        expect($count)->toBe(2);
    });

    it('filters providers', function () {
        $provider1 = Mockery::mock(ProviderContract::class);
        $provider1->shouldReceive('name')->andReturn('Bedrock');
        $provider2 = Mockery::mock(ProviderContract::class);
        $provider2->shouldReceive('name')->andReturn('OpenAI');

        $collection = ProviderCollection::make([
            'bedrock' => $provider1,
            'openai' => $provider2,
        ]);

        $filtered = $collection->filter(fn ($provider, $id) => $id === 'bedrock');

        expect($filtered->count())->toBe(1);
        expect($filtered->has('bedrock'))->toBeTrue();
    });

    it('maps providers', function () {
        $provider1 = Mockery::mock(ProviderContract::class);
        $provider1->shouldReceive('name')->andReturn('Bedrock');
        $provider2 = Mockery::mock(ProviderContract::class);
        $provider2->shouldReceive('name')->andReturn('OpenAI');

        $collection = ProviderCollection::make([
            'bedrock' => $provider1,
            'openai' => $provider2,
        ]);

        $mapped = $collection->map(fn ($provider, $id) => $provider->name());

        expect($mapped)->toBe([
            'bedrock' => 'Bedrock',
            'openai' => 'OpenAI',
        ]);
    });
});
