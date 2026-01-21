# Multi-Tenancy Support

Cortex supports multi-tenant applications with per-tenant provider configurations, API keys, and metadata.

## Overview

Multi-tenancy allows you to:

- Configure different providers per tenant
- Store tenant-specific API keys
- Override default configurations
- Run code in specific tenant contexts
- Automatically resolve tenant from request context

## Tenant Context

The `TenantContext` holds all tenant-specific configuration:

```php
use JayI\Cortex\Support\TenantContext;

$tenant = new TenantContext('tenant-123');

// Set provider-specific configuration
$tenant->setProviderConfig('bedrock', [
    'region' => 'us-west-2',
]);

// Set API keys
$tenant->setApiKey('bedrock', 'tenant-specific-key');

// Set metadata
$tenant->setMetadata([
    'plan' => 'premium',
    'quota' => 10000,
]);
```

### Creating from Array

```php
$tenant = TenantContext::fromArray([
    'id' => 'tenant-456',
    'providers' => [
        'bedrock' => ['region' => 'eu-west-1'],
    ],
    'api_keys' => [
        'bedrock' => 'key-123',
    ],
    'metadata' => [
        'name' => 'Acme Corp',
        'plan' => 'enterprise',
    ],
]);
```

### Converting to Array

```php
$array = $tenant->toArray();
// [
//     'id' => 'tenant-456',
//     'providers' => [...],
//     'api_keys' => [...],
//     'metadata' => [...],
// ]
```

## Tenant Manager

The `TenantManager` manages the current tenant context:

```php
use JayI\Cortex\Support\TenantManager;

$manager = app(TenantManager::class);

// Set current tenant
$manager->set($tenant);

// Get current tenant
$current = $manager->current();

// Get current tenant ID
$tenantId = $manager->id();

// Check if tenant is active
if ($manager->hasTenant()) {
    // Tenant context is set
}

// Clear tenant
$manager->clear();
```

### Running in Tenant Context

Execute code in a specific tenant's context:

```php
$result = $manager->runAs($otherTenant, function () use ($chatClient, $request) {
    // All Cortex operations here use $otherTenant's configuration
    return $chatClient->send($request);
});

// Original tenant is automatically restored, even if an exception occurs
```

## Custom Tenant Resolver

Implement automatic tenant resolution based on your application's logic:

```php
use JayI\Cortex\Contracts\TenantResolverContract;
use JayI\Cortex\Contracts\TenantContextContract;

class MyTenantResolver implements TenantResolverContract
{
    public function resolve(): ?TenantContextContract
    {
        $tenantId = auth()->user()?->tenant_id;

        if (!$tenantId) {
            return null;
        }

        $config = TenantConfig::find($tenantId);

        return TenantContext::fromArray([
            'id' => $tenantId,
            'providers' => $config->provider_configs,
            'api_keys' => $config->api_keys,
            'metadata' => [
                'name' => $config->name,
                'plan' => $config->plan,
            ],
        ]);
    }
}

// Register in service provider
$this->app->bind(TenantResolverContract::class, MyTenantResolver::class);
```

## Provider Configuration Override

Tenant-specific configurations automatically override default provider settings:

```php
// Default config (config/cortex.php)
'bedrock' => [
    'region' => 'us-east-1',
]

// Tenant config
$tenant->setProviderConfig('bedrock', [
    'region' => 'eu-west-1',  // Overrides default
]);
```

When a request is made, Cortex automatically merges:
1. Default provider configuration
2. Tenant-specific overrides
3. Request-specific options

## Events with Tenant Context

All Cortex events include tenant information:

```php
use JayI\Cortex\Events\Chat\AfterChatReceive;

Event::listen(AfterChatReceive::class, function ($event) {
    Log::info('Chat completed', [
        'tenant_id' => $event->tenantId,
        'correlation_id' => $event->correlationId,
    ]);
});
```

## Middleware Example

Auto-resolve tenant from subdomain:

```php
class ResolveTenant
{
    public function handle($request, Closure $next)
    {
        $subdomain = explode('.', $request->getHost())[0];

        $config = TenantConfig::where('subdomain', $subdomain)->first();

        if ($config) {
            $tenant = TenantContext::fromArray([
                'id' => $config->id,
                'providers' => $config->provider_configs,
                'api_keys' => $config->api_keys,
            ]);

            app(TenantManager::class)->set($tenant);
        }

        return $next($request);
    }
}
```

## Configuration

```php
// config/cortex.php
'tenancy' => [
    'enabled' => true,
    'resolver' => \App\Tenancy\TenantResolver::class,
],
```

## Complete Example

```php
use JayI\Cortex\Support\TenantContext;
use JayI\Cortex\Support\TenantManager;
use JayI\Cortex\Plugins\Chat\ChatClient;

class TenantAwareService
{
    public function __construct(
        private TenantManager $tenantManager,
        private ChatClient $chatClient,
    ) {}

    public function processForTenant(string $tenantId, string $message): string
    {
        // Load tenant configuration
        $config = TenantConfig::find($tenantId);

        $tenant = TenantContext::fromArray([
            'id' => $tenantId,
            'providers' => $config->provider_configs,
            'api_keys' => $config->api_keys,
        ]);

        // Run in tenant context
        return $this->tenantManager->runAs($tenant, function () use ($message) {
            $request = ChatRequest::make()
                ->model('anthropic.claude-3-5-sonnet-20241022-v2:0')
                ->user($message);

            $response = $this->chatClient->send($request);

            return $response->content();
        });
    }
}
```
