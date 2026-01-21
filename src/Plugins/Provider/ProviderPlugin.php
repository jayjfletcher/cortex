<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Provider;

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderRegistryContract;
use JayI\Cortex\Support\ExtensionPoint;

class ProviderPlugin implements PluginContract
{
    public function __construct(
        protected Container $container,
    ) {}

    /**
     * Unique identifier for this plugin.
     */
    public function id(): string
    {
        return 'provider';
    }

    /**
     * Human-readable name.
     */
    public function name(): string
    {
        return 'Provider';
    }

    /**
     * Plugin version.
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * List of plugin IDs this plugin depends on.
     *
     * @return array<string>
     */
    public function dependencies(): array
    {
        return ['schema'];
    }

    /**
     * List of features/capabilities this plugin provides.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return ['provider', 'llm'];
    }

    /**
     * Register bindings, configs, and extension points.
     */
    public function register(PluginManagerContract $manager): void
    {
        // Register the provider registry as a singleton
        $this->container->singleton(ProviderRegistryContract::class, function ($app) {
            return new ProviderRegistry($app);
        });

        // Register extension point for providers
        $manager->registerExtensionPoint(
            'providers',
            ExtensionPoint::make('providers', ProviderContract::class)
        );
    }

    /**
     * Bootstrap the plugin after all plugins are registered.
     */
    public function boot(PluginManagerContract $manager): void
    {
        $registry = $this->container->make(ProviderRegistryContract::class);
        $config = $this->container->make('config')->get('cortex.provider', []);

        // Register providers from configuration
        $providers = $config['providers'] ?? [];
        foreach ($providers as $id => $providerConfig) {
            $driver = $providerConfig['driver'] ?? $id;
            $providerClass = $this->resolveProviderClass($driver);

            if ($providerClass !== null) {
                $this->container->singleton("cortex.provider.{$id}", function ($app) use ($providerClass, $id, $providerConfig) {
                    return new $providerClass($id, $providerConfig, $app);
                });

                $registry->register($id, $this->container->make("cortex.provider.{$id}"));
            }
        }

        // Set default provider
        $default = $config['default'] ?? null;
        if ($default !== null && $registry->has($default)) {
            $registry->setDefault($default);
        }

        // Register any providers added through extension points
        $extensionPoint = $manager->getExtensionPoint('providers');
        if ($extensionPoint !== null) {
            foreach ($extensionPoint->all() as $provider) {
                if (! $registry->has($provider->id())) {
                    $registry->register($provider->id(), $provider);
                }
            }
        }
    }

    /**
     * Resolve provider class from driver name.
     *
     * @return class-string<ProviderContract>|null
     */
    protected function resolveProviderClass(string $driver): ?string
    {
        return match ($driver) {
            'bedrock' => Providers\BedrockProvider::class,
            'fake' => Providers\FakeProvider::class,
            default => null,
        };
    }
}
