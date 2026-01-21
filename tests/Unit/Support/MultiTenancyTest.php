<?php

declare(strict_types=1);

use JayI\Cortex\Contracts\TenantContextContract;
use JayI\Cortex\Contracts\TenantResolverContract;
use JayI\Cortex\Support\NullTenantResolver;
use JayI\Cortex\Support\TenantContext;
use JayI\Cortex\Support\TenantManager;

describe('TenantContext', function () {
    it('creates tenant context with id', function () {
        $tenant = new TenantContext('tenant-123');

        expect($tenant->id())->toBe('tenant-123');
    });

    it('stores and retrieves provider config via constructor', function () {
        $tenant = new TenantContext(
            tenantId: 'tenant-123',
            providerConfigs: [
                'bedrock' => [
                    'region' => 'us-west-2',
                    'credentials' => ['key' => 'xxx'],
                ],
            ],
        );

        $config = $tenant->getProviderConfig('bedrock');

        expect($config['region'])->toBe('us-west-2');
    });

    it('returns empty array for missing provider config', function () {
        $tenant = new TenantContext('tenant-123');

        expect($tenant->getProviderConfig('nonexistent'))->toBe([]);
    });

    it('stores and retrieves api key via constructor', function () {
        $tenant = new TenantContext(
            tenantId: 'tenant-123',
            apiKeys: ['bedrock' => 'secret-key'],
        );

        expect($tenant->getApiKey('bedrock'))->toBe('secret-key');
    });

    it('returns null for missing api key', function () {
        $tenant = new TenantContext('tenant-123');

        expect($tenant->getApiKey('nonexistent'))->toBeNull();
    });

    it('stores and retrieves settings', function () {
        $tenant = new TenantContext(
            tenantId: 'tenant-123',
            settings: ['plan' => 'premium', 'quota' => 1000],
        );

        $settings = $tenant->getSettings();
        expect($settings['plan'])->toBe('premium');
        expect($settings['quota'])->toBe(1000);
    });

    it('creates tenant with provider via static method', function () {
        $tenant = TenantContext::withProvider(
            tenantId: 'tenant-456',
            provider: 'bedrock',
            config: ['region' => 'eu-west-1'],
            apiKey: 'key-123',
        );

        expect($tenant->id())->toBe('tenant-456');
        expect($tenant->getProviderConfig('bedrock')['region'])->toBe('eu-west-1');
        expect($tenant->getApiKey('bedrock'))->toBe('key-123');
    });

    it('adds provider config immutably', function () {
        $tenant = new TenantContext('tenant-789');
        $newTenant = $tenant->addProviderConfig('bedrock', ['region' => 'ap-south-1'], 'key-xyz');

        // Original should be unchanged
        expect($tenant->getProviderConfig('bedrock'))->toBe([]);
        expect($tenant->getApiKey('bedrock'))->toBeNull();

        // New tenant should have the config
        expect($newTenant->getProviderConfig('bedrock')['region'])->toBe('ap-south-1');
        expect($newTenant->getApiKey('bedrock'))->toBe('key-xyz');
    });

    it('sets settings immutably', function () {
        $tenant = new TenantContext('tenant-789');
        $newTenant = $tenant->withSettings(['tier' => 'enterprise']);

        // Original should be unchanged
        expect($tenant->getSettings())->toBe([]);

        // New tenant should have the settings
        expect($newTenant->getSettings()['tier'])->toBe('enterprise');
    });
});

describe('NullTenantResolver', function () {
    it('always returns null', function () {
        $resolver = new NullTenantResolver;

        expect($resolver->resolve())->toBeNull();
    });
});

describe('TenantManager', function () {
    it('sets and gets current tenant', function () {
        $resolver = new NullTenantResolver;
        $manager = new TenantManager($resolver);

        $tenant = new TenantContext('tenant-123');
        $manager->set($tenant);

        expect($manager->current())->toBe($tenant);
        expect($manager->current()->id())->toBe('tenant-123');
    });

    it('clears current tenant', function () {
        $resolver = new NullTenantResolver;
        $manager = new TenantManager($resolver);

        $tenant = new TenantContext('tenant-123');
        $manager->set($tenant);
        $manager->clear();

        expect($manager->current())->toBeNull();
    });

    it('runs callback in tenant context', function () {
        $resolver = new NullTenantResolver;
        $manager = new TenantManager($resolver);

        $originalTenant = new TenantContext('original');
        $manager->set($originalTenant);

        $scopedTenant = new TenantContext('scoped');
        $capturedId = null;

        $result = $manager->withTenant($scopedTenant, function () use ($manager, &$capturedId) {
            $capturedId = $manager->current()->id();

            return 'result';
        });

        expect($capturedId)->toBe('scoped');
        expect($result)->toBe('result');
        expect($manager->current()->id())->toBe('original');
    });

    it('restores tenant after exception in withTenant', function () {
        $resolver = new NullTenantResolver;
        $manager = new TenantManager($resolver);

        $originalTenant = new TenantContext('original');
        $manager->set($originalTenant);

        $scopedTenant = new TenantContext('scoped');

        try {
            $manager->withTenant($scopedTenant, function () {
                throw new RuntimeException('Test error');
            });
        } catch (RuntimeException $e) {
            // Expected
        }

        expect($manager->current()->id())->toBe('original');
    });

    it('uses resolver when no tenant set', function () {
        $tenant = new TenantContext('resolved-tenant');
        $resolver = Mockery::mock(TenantResolverContract::class);
        $resolver->shouldReceive('resolve')->once()->andReturn($tenant);

        $manager = new TenantManager($resolver);

        expect($manager->current())->toBe($tenant);
    });

    it('checks if tenant is active', function () {
        $resolver = new NullTenantResolver;
        $manager = new TenantManager($resolver);

        expect($manager->hasTenant())->toBeFalse();

        $manager->set(new TenantContext('tenant-123'));

        expect($manager->hasTenant())->toBeTrue();
    });

    it('returns the resolver instance', function () {
        $resolver = new NullTenantResolver;
        $manager = new TenantManager($resolver);

        expect($manager->resolver())->toBe($resolver);
    });
});

describe('TenantContext Contract Implementation', function () {
    it('implements TenantContextContract', function () {
        $tenant = new TenantContext('test');

        expect($tenant)->toBeInstanceOf(TenantContextContract::class);
    });
});

describe('TenantResolver Contract Implementation', function () {
    it('implements TenantResolverContract', function () {
        $resolver = new NullTenantResolver;

        expect($resolver)->toBeInstanceOf(TenantResolverContract::class);
    });
});
