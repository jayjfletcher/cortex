# Cache Plugin

The Cache plugin provides response caching for LLM interactions to reduce costs and latency for repeated queries.

## Installation

The Cache plugin has no dependencies:

```php
use JayI\Cortex\Plugins\Cache\CachePlugin;

$pluginManager->register(new CachePlugin($container, [
    'strategy' => 'exact', // or 'semantic'
    'ttl' => 3600,
]));
```

## Quick Start

```php
use JayI\Cortex\Plugins\Cache\Contracts\ResponseCacheContract;

$cache = app(ResponseCacheContract::class);

$request = [
    'model' => 'claude-3-5-sonnet',
    'messages' => [['role' => 'user', 'content' => 'What is 2+2?']],
];

// Check cache first
$response = $cache->get($request);

if ($response === null) {
    // Cache miss - call LLM
    $response = $llm->chat($request);

    // Store in cache
    $cache->put($request, $response, ttlSeconds: 3600);
}
```

## Cache Strategies

### ExactMatchStrategy

Caches responses based on exact request matching:

```php
use JayI\Cortex\Plugins\Cache\Strategies\ExactMatchStrategy;
use Illuminate\Cache\Repository;

$strategy = new ExactMatchStrategy(
    cache: app(Repository::class),
    defaultTtl: 3600,
);

$strategy->setPrefix('cortex_cache_');

// Generate cache key
$key = $strategy->generateKey($request);

// Store response
$entry = $strategy->put($request, $response, ttlSeconds: 7200);

// Retrieve response
$entry = $strategy->get($request);

if ($entry !== null) {
    $response = $entry->response;
    $hits = $entry->hits;
}
```

### SemanticCacheStrategy

Caches responses based on semantic similarity (catches similar but not identical queries):

```php
use JayI\Cortex\Plugins\Cache\Strategies\SemanticCacheStrategy;

$strategy = new SemanticCacheStrategy(
    cache: app(Repository::class),
    defaultTtl: 3600,
    similarityThreshold: 0.95, // 0.0-1.0
);

$strategy->setThreshold(0.90); // Lower = more matches

// These might match each other:
// "What is the weather in Paris?"
// "Tell me the weather in Paris"
```

## ResponseCache

The main cache interface:

```php
use JayI\Cortex\Plugins\Cache\ResponseCache;
use JayI\Cortex\Plugins\Cache\Strategies\ExactMatchStrategy;

$cache = new ResponseCache(new ExactMatchStrategy($laravelCache));

// Store
$cache->put($request, $response, ttlSeconds: 3600);

// Retrieve (returns null on miss)
$response = $cache->get($request);

// Check existence
if ($cache->has($request)) {
    // ...
}

// Remove
$cache->forget($request);

// Clear all
$cache->flush();
```

### Cache Statistics

```php
$stats = $cache->stats();
// [
//     'hits' => 150,
//     'misses' => 50,
//     'size' => 0, // implementation dependent
// ]

$hitRate = $cache->hitRate(); // 75.0 (percentage)
```

## CacheEntry

Individual cache entries with metadata:

```php
use JayI\Cortex\Plugins\Cache\Data\CacheEntry;

$entry = CacheEntry::create(
    key: 'cache-key',
    response: $response,
    ttlSeconds: 3600,
    metadata: ['model' => 'claude-3-5-sonnet'],
);

// Check expiration
if ($entry->isExpired()) {
    // Entry has expired
}

// Record cache hit
$updatedEntry = $entry->recordHit();
echo $updatedEntry->hits; // 1
```

## Integration Example

Wrapping LLM calls with caching:

```php
use JayI\Cortex\Plugins\Cache\Contracts\ResponseCacheContract;

class CachedLlmService
{
    public function __construct(
        private LlmProviderContract $llm,
        private ResponseCacheContract $cache,
    ) {}

    public function chat(array $request): Response
    {
        // Try cache first
        $cached = $this->cache->get($request);
        if ($cached !== null) {
            return $cached;
        }

        // Call LLM
        $response = $this->llm->chat($request);

        // Cache successful responses
        if ($response->isSuccess()) {
            $this->cache->put(
                $request,
                $response,
                ttlSeconds: $this->getCacheTtl($request),
            );
        }

        return $response;
    }

    private function getCacheTtl(array $request): int
    {
        // Longer TTL for deterministic queries
        if (($request['temperature'] ?? 1.0) === 0) {
            return 86400; // 24 hours
        }

        return 3600; // 1 hour
    }
}
```

## Configuration

```php
$config = [
    // Strategy: 'exact' or 'semantic'
    'strategy' => 'exact',

    // Default TTL in seconds
    'ttl' => 3600,

    // Cache key prefix
    'prefix' => 'cortex_cache_',

    // Semantic cache threshold (0.0-1.0)
    'semantic_threshold' => 0.95,
];
```

## Best Practices

1. **Use exact match for deterministic queries**: When temperature is 0 or you need consistent responses.

2. **Consider semantic cache for FAQ-style queries**: When users ask similar questions in different ways.

3. **Set appropriate TTLs**:
   - Short for dynamic content
   - Long for factual/static content

4. **Cache based on essential request fields**: Exclude timestamps, request IDs from cache key generation.

5. **Monitor cache hit rates**: Low hit rates might indicate poor cache strategy.

## API Reference

### ResponseCacheContract

| Method | Description |
|--------|-------------|
| `get(array $request)` | Get cached response |
| `put(array $request, $response, ?int $ttl)` | Cache a response |
| `has(array $request)` | Check if cached |
| `forget(array $request)` | Remove cached response |
| `flush()` | Clear all cached responses |
| `stats()` | Get cache statistics |

### CacheStrategyContract

| Method | Description |
|--------|-------------|
| `id()` | Get strategy identifier |
| `generateKey(array $request)` | Generate cache key |
| `isValid(CacheEntry $entry, array $request)` | Check entry validity |
| `get(array $request)` | Get cached entry |
| `put(array $request, $response, ?int $ttl)` | Store entry |
| `forget(string $key)` | Remove entry |
| `flush()` | Clear all entries |
