<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Cache\CachePlugin;
use JayI\Cortex\Plugins\Cache\Contracts\CacheStrategyContract;
use JayI\Cortex\Plugins\Cache\Contracts\ResponseCacheContract;
use JayI\Cortex\Plugins\Cache\ResponseCache;
use JayI\Cortex\Plugins\Cache\Strategies\ExactMatchStrategy;
use JayI\Cortex\Plugins\Cache\Strategies\SemanticCacheStrategy;
use JayI\Cortex\Plugins\ContextManager\ContextManager;
use JayI\Cortex\Plugins\ContextManager\ContextManagerPlugin;
use JayI\Cortex\Plugins\ContextManager\Contracts\ContextManagerContract;
use JayI\Cortex\Plugins\ContextManager\Contracts\ContextStrategyContract;
use JayI\Cortex\Plugins\ContextManager\Strategies\ImportanceStrategy;
use JayI\Cortex\Plugins\ContextManager\Strategies\SlidingWindowStrategy;
use JayI\Cortex\Plugins\ContextManager\Strategies\TruncateOldestStrategy;
use JayI\Cortex\Plugins\Guardrail\Contracts\GuardrailPipelineContract;
use JayI\Cortex\Plugins\Guardrail\GuardrailPipeline;
use JayI\Cortex\Plugins\Guardrail\GuardrailPlugin;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderRegistryContract;
use JayI\Cortex\Plugins\Resilience\Contracts\ResiliencePolicyContract;
use JayI\Cortex\Plugins\Resilience\Contracts\ResilienceStrategyContract;
use JayI\Cortex\Plugins\Resilience\ResiliencePlugin;
use JayI\Cortex\Plugins\Resilience\ResiliencePolicy;
use JayI\Cortex\Plugins\StructuredOutput\Contracts\StructuredOutputContract;
use JayI\Cortex\Plugins\StructuredOutput\StructuredOutputPlugin;

describe('CachePlugin', function () {
    beforeEach(function () {
        $this->container = Mockery::mock(Container::class);
        $this->pluginManager = Mockery::mock(PluginManagerContract::class);
    });

    test('returns correct id', function () {
        $plugin = new CachePlugin($this->container);
        expect($plugin->id())->toBe('cache');
    });

    test('returns correct name', function () {
        $plugin = new CachePlugin($this->container);
        expect($plugin->name())->toBe('Cache');
    });

    test('returns correct version', function () {
        $plugin = new CachePlugin($this->container);
        expect($plugin->version())->toBe('1.0.0');
    });

    test('has no dependencies', function () {
        $plugin = new CachePlugin($this->container);
        expect($plugin->dependencies())->toBe([]);
    });

    test('provides cache capability', function () {
        $plugin = new CachePlugin($this->container);
        expect($plugin->provides())->toBe(['cache']);
    });

    test('registers cache services', function () {
        $container = new \Illuminate\Container\Container;
        $container->instance(CacheRepository::class, new Repository(new ArrayStore));

        $plugin = new CachePlugin($container);
        $plugin->register($this->pluginManager);

        expect($container->bound(CacheStrategyContract::class))->toBeTrue();
        expect($container->bound(ResponseCacheContract::class))->toBeTrue();
        expect($container->bound(ResponseCache::class))->toBeTrue();
    });

    test('creates exact match strategy by default', function () {
        $container = new \Illuminate\Container\Container;
        $container->instance(CacheRepository::class, new Repository(new ArrayStore));

        $plugin = new CachePlugin($container);
        $plugin->register($this->pluginManager);

        $strategy = $container->make(CacheStrategyContract::class);
        expect($strategy)->toBeInstanceOf(ExactMatchStrategy::class);
    });

    test('creates semantic strategy when configured', function () {
        $container = new \Illuminate\Container\Container;
        $container->instance(CacheRepository::class, new Repository(new ArrayStore));

        $plugin = new CachePlugin($container, ['strategy' => 'semantic']);
        $plugin->register($this->pluginManager);

        $strategy = $container->make(CacheStrategyContract::class);
        expect($strategy)->toBeInstanceOf(SemanticCacheStrategy::class);
    });

    test('sets custom prefix when configured', function () {
        $container = new \Illuminate\Container\Container;
        $container->instance(CacheRepository::class, new Repository(new ArrayStore));

        $plugin = new CachePlugin($container, ['strategy' => 'exact', 'prefix' => 'my_prefix_']);
        $plugin->register($this->pluginManager);

        $strategy = $container->make(CacheStrategyContract::class);
        $key = $strategy->generateKey(['test' => 'data']);
        expect($key)->toStartWith('my_prefix_');
    });

    test('boot does nothing', function () {
        $plugin = new CachePlugin($this->container);
        // Should not throw
        $plugin->boot($this->pluginManager);
        expect(true)->toBeTrue();
    });
});

describe('ContextManagerPlugin', function () {
    beforeEach(function () {
        $this->pluginManager = Mockery::mock(PluginManagerContract::class);
    });

    test('returns correct id', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ContextManagerPlugin($container);
        expect($plugin->id())->toBe('context-manager');
    });

    test('returns correct name', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ContextManagerPlugin($container);
        expect($plugin->name())->toBe('Context Manager');
    });

    test('returns correct version', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ContextManagerPlugin($container);
        expect($plugin->version())->toBe('1.0.0');
    });

    test('has no dependencies', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ContextManagerPlugin($container);
        expect($plugin->dependencies())->toBe([]);
    });

    test('provides context-manager capability', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ContextManagerPlugin($container);
        expect($plugin->provides())->toBe(['context-manager']);
    });

    test('registers context manager services', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ContextManagerPlugin($container);
        $plugin->register($this->pluginManager);

        expect($container->bound(ContextStrategyContract::class))->toBeTrue();
        expect($container->bound(ContextManagerContract::class))->toBeTrue();
        expect($container->bound(ContextManager::class))->toBeTrue();
    });

    test('creates truncate-oldest strategy by default', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ContextManagerPlugin($container);
        $plugin->register($this->pluginManager);

        $strategy = $container->make(ContextStrategyContract::class);
        expect($strategy)->toBeInstanceOf(TruncateOldestStrategy::class);
    });

    test('creates importance strategy when configured', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ContextManagerPlugin($container, ['strategy' => 'importance']);
        $plugin->register($this->pluginManager);

        $strategy = $container->make(ContextStrategyContract::class);
        expect($strategy)->toBeInstanceOf(ImportanceStrategy::class);
    });

    test('creates sliding-window strategy when configured', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ContextManagerPlugin($container, ['strategy' => 'sliding-window']);
        $plugin->register($this->pluginManager);

        $strategy = $container->make(ContextStrategyContract::class);
        expect($strategy)->toBeInstanceOf(SlidingWindowStrategy::class);
    });

    test('boot does nothing', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ContextManagerPlugin($container);
        $plugin->boot($this->pluginManager);
        expect(true)->toBeTrue();
    });
});

describe('GuardrailPlugin', function () {
    beforeEach(function () {
        $this->pluginManager = Mockery::mock(PluginManagerContract::class);
    });

    test('returns correct id', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new GuardrailPlugin($container);
        expect($plugin->id())->toBe('guardrail');
    });

    test('returns correct name', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new GuardrailPlugin($container);
        expect($plugin->name())->toBe('Guardrail');
    });

    test('returns correct version', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new GuardrailPlugin($container);
        expect($plugin->version())->toBe('1.0.0');
    });

    test('has no dependencies', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new GuardrailPlugin($container);
        expect($plugin->dependencies())->toBe([]);
    });

    test('provides guardrail capability', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new GuardrailPlugin($container);
        expect($plugin->provides())->toBe(['guardrail']);
    });

    test('registers guardrail services', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new GuardrailPlugin($container);
        $plugin->register($this->pluginManager);

        expect($container->bound(GuardrailPipelineContract::class))->toBeTrue();
        expect($container->bound(GuardrailPipeline::class))->toBeTrue();
    });

    test('creates pipeline with prompt injection by default', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new GuardrailPlugin($container);
        $plugin->register($this->pluginManager);

        $pipeline = $container->make(GuardrailPipelineContract::class);
        expect($pipeline->has('prompt-injection'))->toBeTrue();
    });

    test('disables prompt injection when configured', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new GuardrailPlugin($container, [
            'prompt_injection' => ['enabled' => false],
        ]);
        $plugin->register($this->pluginManager);

        $pipeline = $container->make(GuardrailPipelineContract::class);
        expect($pipeline->has('prompt-injection'))->toBeFalse();
    });

    test('enables pii detection when configured', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new GuardrailPlugin($container, [
            'prompt_injection' => ['enabled' => false],
            'pii' => ['enabled' => true],
        ]);
        $plugin->register($this->pluginManager);

        $pipeline = $container->make(GuardrailPipelineContract::class);
        expect($pipeline->has('pii'))->toBeTrue();
    });

    test('enables keyword filter when configured', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new GuardrailPlugin($container, [
            'prompt_injection' => ['enabled' => false],
            'keyword' => ['enabled' => true, 'keywords' => ['banned']],
        ]);
        $plugin->register($this->pluginManager);

        $pipeline = $container->make(GuardrailPipelineContract::class);
        expect($pipeline->has('keyword'))->toBeTrue();
    });

    test('enables content length when configured', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new GuardrailPlugin($container, [
            'prompt_injection' => ['enabled' => false],
            'content_length' => ['enabled' => true, 'max' => 1000],
        ]);
        $plugin->register($this->pluginManager);

        $pipeline = $container->make(GuardrailPipelineContract::class);
        expect($pipeline->has('content-length'))->toBeTrue();
    });

    test('boot does nothing', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new GuardrailPlugin($container);
        $plugin->boot($this->pluginManager);
        expect(true)->toBeTrue();
    });
});

describe('ResiliencePlugin', function () {
    beforeEach(function () {
        $this->pluginManager = Mockery::mock(PluginManagerContract::class);
    });

    test('returns correct id', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ResiliencePlugin($container);
        expect($plugin->id())->toBe('resilience');
    });

    test('returns correct name', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ResiliencePlugin($container);
        expect($plugin->name())->toBe('Resilience');
    });

    test('returns correct version', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ResiliencePlugin($container);
        expect($plugin->version())->toBe('1.0.0');
    });

    test('has no dependencies', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ResiliencePlugin($container);
        expect($plugin->dependencies())->toBe([]);
    });

    test('provides resilience capability', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ResiliencePlugin($container);
        expect($plugin->provides())->toBe(['resilience']);
    });

    test('registers resilience services', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ResiliencePlugin($container);
        $plugin->register($this->pluginManager);

        expect($container->bound(ResilienceStrategyContract::class))->toBeTrue();
        expect($container->bound(ResiliencePolicyContract::class))->toBeTrue();
        expect($container->bound(ResiliencePolicy::class))->toBeTrue();
    });

    test('creates policy with retry when configured', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ResiliencePlugin($container, [
            'retry' => ['enabled' => true, 'max_attempts' => 5],
        ]);
        $plugin->register($this->pluginManager);

        $policy = $container->make(ResiliencePolicyContract::class);
        expect($policy)->toBeInstanceOf(ResiliencePolicy::class);
    });

    test('creates policy with circuit breaker when configured', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ResiliencePlugin($container, [
            'circuit_breaker' => ['enabled' => true, 'failure_threshold' => 3],
        ]);
        $plugin->register($this->pluginManager);

        $policy = $container->make(ResiliencePolicyContract::class);
        expect($policy)->toBeInstanceOf(ResiliencePolicy::class);
    });

    test('creates policy with timeout when configured', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ResiliencePlugin($container, [
            'timeout' => ['enabled' => true, 'seconds' => 60],
        ]);
        $plugin->register($this->pluginManager);

        $policy = $container->make(ResiliencePolicyContract::class);
        expect($policy)->toBeInstanceOf(ResiliencePolicy::class);
    });

    test('creates policy with rate limiter when configured', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ResiliencePlugin($container, [
            'rate_limiter' => ['enabled' => true, 'max_tokens' => 5],
        ]);
        $plugin->register($this->pluginManager);

        $policy = $container->make(ResiliencePolicyContract::class);
        expect($policy)->toBeInstanceOf(ResiliencePolicy::class);
    });

    test('creates policy with bulkhead when configured', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ResiliencePlugin($container, [
            'bulkhead' => ['enabled' => true, 'max_concurrent' => 5],
        ]);
        $plugin->register($this->pluginManager);

        $policy = $container->make(ResiliencePolicyContract::class);
        expect($policy)->toBeInstanceOf(ResiliencePolicy::class);
    });

    test('boot does nothing', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new ResiliencePlugin($container);
        $plugin->boot($this->pluginManager);
        expect(true)->toBeTrue();
    });
});

describe('StructuredOutputPlugin', function () {
    beforeEach(function () {
        $this->pluginManager = Mockery::mock(PluginManagerContract::class);
    });

    test('returns correct id', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new StructuredOutputPlugin($container);
        expect($plugin->id())->toBe('structured-output');
    });

    test('returns correct name', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new StructuredOutputPlugin($container);
        expect($plugin->name())->toBe('Structured Output Plugin');
    });

    test('returns correct version', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new StructuredOutputPlugin($container);
        expect($plugin->version())->toBe('1.0.0');
    });

    test('has correct dependencies', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new StructuredOutputPlugin($container);
        expect($plugin->dependencies())->toBe(['schema', 'provider', 'chat']);
    });

    test('provides structured-output capability', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new StructuredOutputPlugin($container);
        expect($plugin->provides())->toBe(['structured-output']);
    });

    test('registers structured output services', function () {
        $container = new \Illuminate\Container\Container;
        $providerRegistry = Mockery::mock(ProviderRegistryContract::class);
        $container->instance(ProviderRegistryContract::class, $providerRegistry);

        $plugin = new StructuredOutputPlugin($container);
        $plugin->register($this->pluginManager);

        expect($container->bound(StructuredOutputContract::class))->toBeTrue();
    });

    test('boot does nothing', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new StructuredOutputPlugin($container);
        $plugin->boot($this->pluginManager);
        expect(true)->toBeTrue();
    });
});

describe('ChatPlugin', function () {
    beforeEach(function () {
        $this->pluginManager = Mockery::mock(PluginManagerContract::class);
    });

    test('returns correct id', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new \JayI\Cortex\Plugins\Chat\ChatPlugin($container);
        expect($plugin->id())->toBe('chat');
    });

    test('returns correct name', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new \JayI\Cortex\Plugins\Chat\ChatPlugin($container);
        expect($plugin->name())->toBe('Chat');
    });

    test('returns correct version', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new \JayI\Cortex\Plugins\Chat\ChatPlugin($container);
        expect($plugin->version())->toBe('1.0.0');
    });

    test('depends on provider', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new \JayI\Cortex\Plugins\Chat\ChatPlugin($container);
        expect($plugin->dependencies())->toBe(['provider']);
    });

    test('provides chat, streaming and broadcasting capabilities', function () {
        $container = new \Illuminate\Container\Container;
        $plugin = new \JayI\Cortex\Plugins\Chat\ChatPlugin($container);
        expect($plugin->provides())->toBe(['chat', 'streaming', 'broadcasting']);
    });

    test('registers chat services', function () {
        $container = new \Illuminate\Container\Container;
        $providerRegistry = Mockery::mock(ProviderRegistryContract::class);
        $container->instance(ProviderRegistryContract::class, $providerRegistry);

        $plugin = new \JayI\Cortex\Plugins\Chat\ChatPlugin($container);
        $plugin->register($this->pluginManager);

        expect($container->bound(\JayI\Cortex\Plugins\Chat\Contracts\ChatClientContract::class))->toBeTrue();
        expect($container->bound(\JayI\Cortex\Plugins\Chat\Broadcasting\BroadcasterContract::class))->toBeTrue();
    });

    test('boot registers hooks when default options configured', function () {
        $container = new \Illuminate\Container\Container;
        $container->instance('config', new \Illuminate\Config\Repository([
            'cortex.chat' => [
                'default_options' => ['temperature' => 0.7],
            ],
        ]));

        $this->pluginManager->shouldReceive('addHook')
            ->once()
            ->with('chat.before_send', Mockery::type('callable'), Mockery::any());

        $plugin = new \JayI\Cortex\Plugins\Chat\ChatPlugin($container);
        $plugin->boot($this->pluginManager);
    });

    test('boot skips hooks when no default options configured', function () {
        $container = new \Illuminate\Container\Container;
        $container->instance('config', new \Illuminate\Config\Repository([
            'cortex.chat' => [],
        ]));

        $this->pluginManager->shouldNotReceive('addHook');

        $plugin = new \JayI\Cortex\Plugins\Chat\ChatPlugin($container);
        $plugin->boot($this->pluginManager);
    });
});
