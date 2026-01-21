<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use JayI\Cortex\Plugins\Cache\Data\CacheEntry;
use JayI\Cortex\Plugins\Cache\ResponseCache;
use JayI\Cortex\Plugins\Cache\Strategies\ExactMatchStrategy;
use JayI\Cortex\Plugins\Cache\Strategies\SemanticCacheStrategy;

describe('CacheEntry', function () {
    test('creates a cache entry', function () {
        $entry = CacheEntry::create(
            key: 'test-key',
            response: ['content' => 'cached response'],
            ttlSeconds: 3600,
        );

        expect($entry->key)->toBe('test-key');
        expect($entry->response)->toBe(['content' => 'cached response']);
        expect($entry->isExpired())->toBeFalse();
    });

    test('detects expired entries', function () {
        $entry = CacheEntry::create(
            key: 'test-key',
            response: 'cached',
            ttlSeconds: -1, // Already expired
        );

        expect($entry->isExpired())->toBeTrue();
    });

    test('records hits', function () {
        $entry = CacheEntry::create(
            key: 'test-key',
            response: 'cached',
            ttlSeconds: 3600,
        );

        expect($entry->hits)->toBe(0);

        $updated = $entry->recordHit();
        expect($updated->hits)->toBe(1);
    });
});

describe('ExactMatchStrategy', function () {
    beforeEach(function () {
        $this->cache = new Repository(new ArrayStore);
        $this->strategy = new ExactMatchStrategy($this->cache, 3600);
    });

    test('generates consistent cache keys', function () {
        $request1 = ['model' => 'claude', 'messages' => [['role' => 'user', 'content' => 'Hello']]];
        $request2 = ['model' => 'claude', 'messages' => [['role' => 'user', 'content' => 'Hello']]];

        $key1 = $this->strategy->generateKey($request1);
        $key2 = $this->strategy->generateKey($request2);

        expect($key1)->toBe($key2);
    });

    test('stores and retrieves cached responses', function () {
        $request = ['model' => 'claude', 'messages' => [['content' => 'Test']]];
        $response = ['content' => 'Cached response'];

        $this->strategy->put($request, $response);
        $entry = $this->strategy->get($request);

        expect($entry)->not->toBeNull();
        expect($entry->response)->toBe($response);
    });

    test('returns null for missing entries', function () {
        $request = ['model' => 'claude', 'messages' => [['content' => 'Not cached']]];

        $entry = $this->strategy->get($request);

        expect($entry)->toBeNull();
    });

    test('can forget entries', function () {
        $request = ['model' => 'claude', 'messages' => [['content' => 'Test']]];

        $entry = $this->strategy->put($request, 'response');
        $this->strategy->forget($entry->key);

        expect($this->strategy->get($request))->toBeNull();
    });
});

describe('SemanticCacheStrategy', function () {
    beforeEach(function () {
        $this->cache = new Repository(new ArrayStore);
        $this->strategy = new SemanticCacheStrategy($this->cache, 3600, 0.5); // Lower threshold
    });

    test('caches and retrieves exact match', function () {
        $request = ['messages' => [['content' => 'What is the weather in Paris?']]];

        $this->strategy->put($request, 'The weather is sunny');
        $entry = $this->strategy->get($request);

        expect($entry)->not->toBeNull();
        expect($entry->response)->toBe('The weather is sunny');
    });

    test('does not match dissimilar queries', function () {
        $request1 = ['messages' => [['content' => 'What is the weather in Paris?']]];
        $request2 = ['messages' => [['content' => 'How do I cook Italian pasta?']]];

        $this->strategy->put($request1, 'The weather is sunny');
        $entry = $this->strategy->get($request2);

        expect($entry)->toBeNull();
    });
});

describe('ResponseCache', function () {
    beforeEach(function () {
        $cache = new Repository(new ArrayStore);
        $strategy = new ExactMatchStrategy($cache, 3600);
        $this->responseCache = new ResponseCache($strategy);
    });

    test('caches and retrieves responses', function () {
        $request = ['model' => 'claude', 'prompt' => 'test'];

        $this->responseCache->put($request, 'cached response');
        $response = $this->responseCache->get($request);

        expect($response)->toBe('cached response');
    });

    test('tracks cache statistics', function () {
        $request = ['model' => 'claude', 'prompt' => 'test'];

        // Miss
        $this->responseCache->get($request);

        // Store
        $this->responseCache->put($request, 'cached');

        // Hit
        $this->responseCache->get($request);

        $stats = $this->responseCache->stats();
        expect($stats['hits'])->toBe(1);
        expect($stats['misses'])->toBe(1);
    });

    test('calculates hit rate', function () {
        $request = ['model' => 'claude', 'prompt' => 'test'];

        $this->responseCache->put($request, 'cached');
        $this->responseCache->get($request); // hit
        $this->responseCache->get($request); // hit
        $this->responseCache->get(['different' => 'request']); // miss

        // 2 hits, 1 miss = 66.67%
        expect($this->responseCache->hitRate())->toBeGreaterThan(60);
        expect($this->responseCache->hitRate())->toBeLessThan(70);
    });

    test('checks if cached', function () {
        $request = ['model' => 'claude', 'prompt' => 'test'];

        expect($this->responseCache->has($request))->toBeFalse();

        $this->responseCache->put($request, 'cached');

        expect($this->responseCache->has($request))->toBeTrue();
    });

    test('forgets cached response', function () {
        $request = ['model' => 'claude', 'prompt' => 'test'];

        $this->responseCache->put($request, 'cached');
        expect($this->responseCache->has($request))->toBeTrue();

        $this->responseCache->forget($request);
        expect($this->responseCache->has($request))->toBeFalse();
    });

    test('flushes all cached responses', function () {
        $request1 = ['model' => 'claude', 'prompt' => 'test1'];
        $request2 = ['model' => 'claude', 'prompt' => 'test2'];

        $this->responseCache->put($request1, 'cached1');
        $this->responseCache->put($request2, 'cached2');

        // Create some stats
        $this->responseCache->get($request1);
        $this->responseCache->get(['missing' => 'request']);

        $this->responseCache->flush();

        expect($this->responseCache->has($request1))->toBeFalse();
        expect($this->responseCache->has($request2))->toBeFalse();

        // Stats should be reset
        $stats = $this->responseCache->stats();
        expect($stats['hits'])->toBe(0);
        expect($stats['misses'])->toBe(0);
    });

    test('returns zero hit rate with no requests', function () {
        expect($this->responseCache->hitRate())->toBe(0.0);
    });

    test('returns underlying strategy', function () {
        $strategy = $this->responseCache->getStrategy();
        expect($strategy)->toBeInstanceOf(ExactMatchStrategy::class);
    });
});

describe('CacheEntry additional', function () {
    test('never expires without TTL', function () {
        $entry = CacheEntry::create(
            key: 'test-key',
            response: 'cached',
            ttlSeconds: null,
        );

        expect($entry->isExpired())->toBeFalse();
        expect($entry->expiresAt)->toBeNull();
    });

    test('includes metadata', function () {
        $entry = CacheEntry::create(
            key: 'test-key',
            response: 'cached',
            metadata: ['model' => 'claude', 'source' => 'api'],
        );

        expect($entry->metadata)->toBe(['model' => 'claude', 'source' => 'api']);
    });

    test('recordHit preserves all properties', function () {
        $entry = CacheEntry::create(
            key: 'test-key',
            response: ['data' => 'value'],
            ttlSeconds: 3600,
            metadata: ['source' => 'test'],
        );

        $updated = $entry->recordHit()->recordHit();

        expect($updated->key)->toBe('test-key');
        expect($updated->response)->toBe(['data' => 'value']);
        expect($updated->metadata)->toBe(['source' => 'test']);
        expect($updated->hits)->toBe(2);
        expect($updated->createdAt)->toBe($entry->createdAt);
    });
});

describe('ExactMatchStrategy additional', function () {
    beforeEach(function () {
        $this->cache = new Repository(new ArrayStore);
        $this->strategy = new ExactMatchStrategy($this->cache, 3600);
    });

    test('returns strategy id', function () {
        expect($this->strategy->id())->toBe('exact-match');
    });

    test('can flush all entries', function () {
        $request1 = ['messages' => [['content' => 'Query 1']]];
        $request2 = ['messages' => [['content' => 'Query 2']]];

        $this->strategy->put($request1, 'response1');
        $this->strategy->put($request2, 'response2');

        $this->strategy->flush();

        expect($this->strategy->get($request1))->toBeNull();
        expect($this->strategy->get($request2))->toBeNull();
    });

    test('can set custom prefix', function () {
        $this->strategy->setPrefix('custom_prefix_');

        $request = ['messages' => [['content' => 'Test']]];
        $key = $this->strategy->generateKey($request);

        expect($key)->toStartWith('custom_prefix_');
    });

    test('validates non-expired entry', function () {
        $request = ['messages' => [['content' => 'Test']]];
        $entry = $this->strategy->put($request, 'response');

        // Valid because not expired
        expect($this->strategy->isValid($entry, $request))->toBeTrue();
    });

    test('validates expired entry as invalid', function () {
        $request = ['messages' => [['content' => 'Test']]];

        // Create entry with TTL of -1 (already expired)
        $entry = CacheEntry::create(
            key: 'test-key',
            response: 'cached',
            ttlSeconds: -1,
        );

        // Invalid because expired
        expect($this->strategy->isValid($entry, $request))->toBeFalse();
    });

    test('uses custom TTL when provided', function () {
        $request = ['messages' => [['content' => 'Test']]];
        $entry = $this->strategy->put($request, 'response', 7200);

        expect($entry->expiresAt)->not->toBeNull();
    });
});

describe('SemanticCacheStrategy additional', function () {
    beforeEach(function () {
        $this->cache = new Repository(new ArrayStore);
        $this->strategy = new SemanticCacheStrategy($this->cache, 3600, 0.8);
    });

    test('returns strategy id', function () {
        expect($this->strategy->id())->toBe('semantic');
    });

    test('can adjust similarity threshold', function () {
        $returnedStrategy = $this->strategy->setThreshold(0.95);

        // setThreshold returns self for chaining
        expect($returnedStrategy)->toBe($this->strategy);

        // Put a response
        $request = ['messages' => [['content' => 'What is the weather in Paris?']]];
        $this->strategy->put($request, 'The weather is sunny');

        // Exact match should still work
        $entry = $this->strategy->get($request);
        expect($entry)->not->toBeNull();
    });

    test('generates keys', function () {
        $request = ['messages' => [['content' => 'Test query']]];
        $key = $this->strategy->generateKey($request);

        expect($key)->toBeString();
        expect(strlen($key))->toBeGreaterThan(0);
    });

    test('can forget entries', function () {
        $request = ['messages' => [['content' => 'Test query']]];
        $entry = $this->strategy->put($request, 'response');

        $this->strategy->forget($entry->key);

        expect($this->strategy->get($request))->toBeNull();
    });
});
