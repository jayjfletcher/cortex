<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Exceptions\ProviderException;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;
use JayI\Cortex\Plugins\Provider\ProviderRegistry;

describe('ProviderRegistry', function () {
    it('registers and retrieves a provider instance', function () {
        $container = Mockery::mock(Container::class);
        $container->shouldReceive('bound')->andReturn(false);
        $provider = Mockery::mock(ProviderContract::class);

        $registry = new ProviderRegistry($container);
        $registry->register('test', $provider);

        expect($registry->has('test'))->toBeTrue();
        expect($registry->get('test'))->toBe($provider);
    });

    it('sets first registered provider as default', function () {
        $container = Mockery::mock(Container::class);
        $container->shouldReceive('bound')->andReturn(false);
        $provider = Mockery::mock(ProviderContract::class);

        $registry = new ProviderRegistry($container);
        $registry->register('first', $provider);

        expect($registry->default())->toBe($provider);
    });

    it('checks if provider exists', function () {
        $container = Mockery::mock(Container::class);
        $registry = new ProviderRegistry($container);

        expect($registry->has('nonexistent'))->toBeFalse();

        $provider = Mockery::mock(ProviderContract::class);
        $registry->register('exists', $provider);

        expect($registry->has('exists'))->toBeTrue();
        expect($registry->has('nonexistent'))->toBeFalse();
    });

    it('throws exception when provider not found', function () {
        $container = Mockery::mock(Container::class);
        $registry = new ProviderRegistry($container);

        expect(fn () => $registry->get('nonexistent'))
            ->toThrow(ProviderException::class);
    });

    it('throws exception when no default provider', function () {
        $container = Mockery::mock(Container::class);
        $registry = new ProviderRegistry($container);

        expect(fn () => $registry->default())
            ->toThrow(ProviderException::class);
    });

    it('resolves provider from class string', function () {
        $container = Mockery::mock(Container::class);
        $container->shouldReceive('bound')->andReturn(false);
        $provider = Mockery::mock(ProviderContract::class);

        $container->shouldReceive('make')
            ->once()
            ->with('SomeProviderClass')
            ->andReturn($provider);

        $registry = new ProviderRegistry($container);
        $registry->register('lazy', 'SomeProviderClass');

        $resolved = $registry->get('lazy');

        expect($resolved)->toBe($provider);
    });

    it('caches resolved providers', function () {
        $container = Mockery::mock(Container::class);
        $container->shouldReceive('bound')->andReturn(false);
        $provider = Mockery::mock(ProviderContract::class);

        $container->shouldReceive('make')
            ->once() // Should only be called once
            ->with('SomeProviderClass')
            ->andReturn($provider);

        $registry = new ProviderRegistry($container);
        $registry->register('cached', 'SomeProviderClass');

        // Call get twice
        $first = $registry->get('cached');
        $second = $registry->get('cached');

        expect($first)->toBe($second);
    });

    it('returns all resolved providers', function () {
        $container = Mockery::mock(Container::class);
        $container->shouldReceive('bound')->andReturn(false);
        $provider1 = Mockery::mock(ProviderContract::class);
        $provider2 = Mockery::mock(ProviderContract::class);

        $registry = new ProviderRegistry($container);
        $registry->register('first', $provider1);
        $registry->register('second', $provider2);

        $all = $registry->all();

        expect($all->count())->toBe(2);
        expect($all->get('first'))->toBe($provider1);
        expect($all->get('second'))->toBe($provider2);
    });

    it('sets custom default provider', function () {
        $container = Mockery::mock(Container::class);
        $container->shouldReceive('bound')->andReturn(false);
        $provider1 = Mockery::mock(ProviderContract::class);
        $provider2 = Mockery::mock(ProviderContract::class);

        $registry = new ProviderRegistry($container);
        $registry->register('first', $provider1);
        $registry->register('second', $provider2);

        $registry->setDefault('second');

        expect($registry->default())->toBe($provider2);
    });

    it('throws exception when setting nonexistent default', function () {
        $container = Mockery::mock(Container::class);
        $registry = new ProviderRegistry($container);

        expect(fn () => $registry->setDefault('nonexistent'))
            ->toThrow(ProviderException::class);
    });

    it('swaps provider implementation', function () {
        $container = Mockery::mock(Container::class);
        $container->shouldReceive('bound')->andReturn(false);
        $original = Mockery::mock(ProviderContract::class);
        $replacement = Mockery::mock(ProviderContract::class);

        $registry = new ProviderRegistry($container);
        $registry->register('swappable', $original);

        expect($registry->get('swappable'))->toBe($original);

        $registry->swap('swappable', $replacement);

        expect($registry->get('swappable'))->toBe($replacement);
    });
});

describe('ProviderException', function () {
    it('creates not found exception', function () {
        $exception = ProviderException::notFound('test-provider');

        expect($exception)->toBeInstanceOf(ProviderException::class);
        expect($exception->getMessage())->toContain('test-provider');
        expect($exception->getMessage())->toContain('not registered');
    });

    it('creates no default exception', function () {
        $exception = ProviderException::noDefault();

        expect($exception)->toBeInstanceOf(ProviderException::class);
        expect($exception->getMessage())->toContain('No default provider');
    });

    it('creates model not found exception', function () {
        $exception = ProviderException::modelNotFound('provider-1', 'model-1');

        expect($exception)->toBeInstanceOf(ProviderException::class);
        expect($exception->getMessage())->toContain('model-1');
        expect($exception->getMessage())->toContain('provider-1');
    });

    it('creates feature not supported exception', function () {
        $exception = ProviderException::featureNotSupported('provider-1', 'streaming');

        expect($exception)->toBeInstanceOf(ProviderException::class);
        expect($exception->getMessage())->toContain('provider-1');
        expect($exception->getMessage())->toContain('streaming');
    });

    it('creates api error exception', function () {
        $previous = new RuntimeException('Original error');
        $exception = ProviderException::apiError('provider-1', 'API error message', 500, $previous);

        expect($exception)->toBeInstanceOf(ProviderException::class);
        expect($exception->getMessage())->toContain('provider-1');
        expect($exception->getMessage())->toContain('API error message');
        expect($exception->getCode())->toBe(500);
        expect($exception->getPrevious())->toBe($previous);
    });

    it('creates rate limited exception without retry', function () {
        $exception = ProviderException::rateLimited('provider-1');

        expect($exception)->toBeInstanceOf(ProviderException::class);
        expect($exception->getMessage())->toContain('provider-1');
        expect($exception->getMessage())->toContain('rate limited');
    });

    it('creates rate limited exception with retry', function () {
        $exception = ProviderException::rateLimited('provider-1', 60);

        expect($exception)->toBeInstanceOf(ProviderException::class);
        expect($exception->getMessage())->toContain('Retry after 60 seconds');
    });

    it('creates authentication failed exception', function () {
        $exception = ProviderException::authenticationFailed('provider-1');

        expect($exception)->toBeInstanceOf(ProviderException::class);
        expect($exception->getMessage())->toContain('provider-1');
        expect($exception->getMessage())->toContain('authentication failed');
    });

    it('creates invalid configuration exception', function () {
        $exception = ProviderException::invalidConfiguration('provider-1', 'Missing API key');

        expect($exception)->toBeInstanceOf(ProviderException::class);
        expect($exception->getMessage())->toContain('provider-1');
        expect($exception->getMessage())->toContain('Missing API key');
    });
});
