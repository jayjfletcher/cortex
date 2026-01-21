# Resilience Plugin

The Resilience plugin provides fault tolerance patterns for handling failures gracefully when interacting with LLM providers. It includes retry strategies, circuit breakers, rate limiting, and more.

## Installation

The Resilience plugin has no dependencies on other plugins:

```php
use JayI\Cortex\Plugins\Resilience\ResiliencePlugin;

$pluginManager->register(new ResiliencePlugin($container, [
    'retry' => ['enabled' => true, 'max_attempts' => 3],
    'circuit_breaker' => ['enabled' => true, 'failure_threshold' => 5],
]));
```

## Quick Start

### Using ResiliencePolicy

Compose multiple strategies into a single policy:

```php
use JayI\Cortex\Plugins\Resilience\ResiliencePolicy;

$policy = ResiliencePolicy::make()
    ->withRetry(maxAttempts: 3, delayMs: 1000)
    ->withCircuitBreaker(failureThreshold: 5)
    ->withFallback(fn ($e) => 'Fallback response');

$response = $policy->execute(fn () => $llm->chat($request));
```

## Strategies

### RetryStrategy

Automatically retry failed operations with exponential backoff:

```php
use JayI\Cortex\Plugins\Resilience\Strategies\RetryStrategy;

$strategy = new RetryStrategy(
    maxAttempts: 3,          // Maximum retry attempts
    delayMs: 1000,           // Initial delay in milliseconds
    multiplier: 2.0,         // Exponential backoff multiplier
    maxDelayMs: 30000,       // Maximum delay cap
    jitter: true,            // Add random jitter to prevent thundering herd
    retryOn: [               // Only retry specific exceptions
        ConnectionException::class,
        TimeoutException::class,
    ],
);

$result = $strategy->execute(fn () => $llm->chat($request));
```

### CircuitBreakerStrategy

Prevent cascading failures by stopping requests when a service is unhealthy:

```php
use JayI\Cortex\Plugins\Resilience\Strategies\CircuitBreakerStrategy;
use JayI\Cortex\Plugins\Resilience\CircuitState;

$breaker = new CircuitBreakerStrategy(
    failureThreshold: 5,      // Failures before opening
    successThreshold: 3,      // Successes in half-open to close
    resetTimeoutSeconds: 60,  // Time before attempting reset
    tripOn: [                 // Exceptions that trip the breaker
        ServiceUnavailableException::class,
    ],
);

try {
    $result = $breaker->execute(fn () => $llm->chat($request));
} catch (CircuitOpenException $e) {
    // Circuit is open, service is unhealthy
    $remainingCooldown = $e->remainingCooldown;
}

// Check circuit state
$state = $breaker->getState(); // Closed, Open, or HalfOpen
```

### TimeoutStrategy

Enforce time limits on operations:

```php
use JayI\Cortex\Plugins\Resilience\Strategies\TimeoutStrategy;

$strategy = new TimeoutStrategy(timeoutSeconds: 30);

try {
    $result = $strategy->execute(fn () => $llm->chat($request));
} catch (TimeoutException $e) {
    // Operation timed out
}
```

### FallbackStrategy

Provide fallback values when operations fail:

```php
use JayI\Cortex\Plugins\Resilience\Strategies\FallbackStrategy;

// Fallback with custom logic
$strategy = new FallbackStrategy(
    fallback: fn ($exception) => "Sorry, I couldn't process that: {$exception->getMessage()}",
    handleOn: [RuntimeException::class],
);

// Static value fallbacks
$nullFallback = FallbackStrategy::null();
$emptyArrayFallback = FallbackStrategy::emptyArray();
$valueFallback = FallbackStrategy::value('Default response');
```

### RateLimiterStrategy

Implement rate limiting using token bucket algorithm:

```php
use JayI\Cortex\Plugins\Resilience\Strategies\RateLimiterStrategy;

$limiter = new RateLimiterStrategy(
    maxTokens: 10,        // Maximum tokens in bucket
    refillRate: 1.0,      // Tokens per second
    waitForToken: false,  // Throw immediately if no token
    maxWaitSeconds: 60,   // Max wait if waitForToken is true
);

try {
    $result = $limiter->execute(fn () => $llm->chat($request));
} catch (RateLimitExceededException $e) {
    $retryAfter = $e->retryAfterSeconds;
}
```

### BulkheadStrategy

Isolate resources and limit concurrent executions:

```php
use JayI\Cortex\Plugins\Resilience\Strategies\BulkheadStrategy;

$bulkhead = new BulkheadStrategy(
    maxConcurrent: 10,  // Maximum concurrent executions
    maxQueue: 100,      // Maximum queued requests
);

try {
    $result = $bulkhead->execute(fn () => $llm->chat($request));
} catch (BulkheadRejectedException $e) {
    // Bulkhead at capacity
}
```

## Combining Strategies

The order of strategy addition matters. Strategies are wrapped in reverse order:

```php
// Order: [fallback, retry] -> Wrapped: fallback(retry(operation))
// retry exhausts attempts, then fallback catches

$policy = ResiliencePolicy::make()
    ->withFallback(fn () => 'fallback')
    ->withRetry(maxAttempts: 3);

// Order: [retry, fallback] -> Wrapped: retry(fallback(operation))
// fallback catches inside retry, making retry see success

$policy = ResiliencePolicy::make()
    ->withRetry(maxAttempts: 3)
    ->withFallback(fn () => 'fallback');
```

## Configuration

Configure default policies in the plugin config:

```php
$config = [
    'retry' => [
        'enabled' => true,
        'max_attempts' => 3,
        'delay_ms' => 1000,
        'multiplier' => 2.0,
        'max_delay_ms' => 30000,
        'jitter' => true,
    ],
    'circuit_breaker' => [
        'enabled' => true,
        'failure_threshold' => 5,
        'success_threshold' => 3,
        'reset_timeout_seconds' => 60,
    ],
    'timeout' => [
        'enabled' => false,
        'seconds' => 30,
    ],
    'rate_limiter' => [
        'enabled' => false,
        'max_tokens' => 10,
        'refill_rate' => 1.0,
        'wait_for_token' => false,
    ],
    'bulkhead' => [
        'enabled' => false,
        'max_concurrent' => 10,
        'max_queue' => 100,
    ],
];
```

## API Reference

### ResiliencePolicy

| Method | Description |
|--------|-------------|
| `make()` | Create a new policy |
| `withStrategy(ResilienceStrategyContract $strategy)` | Add custom strategy |
| `withRetry(...)` | Add retry strategy |
| `withCircuitBreaker(...)` | Add circuit breaker |
| `withTimeout(int $seconds)` | Add timeout strategy |
| `withFallback(Closure $fallback)` | Add fallback with closure |
| `withFallbackValue(mixed $value)` | Add static fallback value |
| `withRateLimiter(...)` | Add rate limiter |
| `withBulkhead(...)` | Add bulkhead |
| `execute(Closure $operation)` | Execute with all strategies |
| `getStrategy(string $class)` | Get strategy by class |
| `getStrategies()` | Get all strategies |

### CircuitBreakerStrategy

| Method | Description |
|--------|-------------|
| `getState()` | Get current state (Closed/Open/HalfOpen) |
| `getFailureCount()` | Get current failure count |
| `getRemainingCooldown()` | Get seconds until reset attempt |
| `reset()` | Manually reset the breaker |
| `forceState(CircuitState $state)` | Force state (for testing) |
