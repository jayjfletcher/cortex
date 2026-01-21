<?php

declare(strict_types=1);

namespace JayI\Cortex;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Contracts\TenantResolverContract;
use JayI\Cortex\Events\Subscribers\EventLoggingSubscriber;
use JayI\Cortex\Plugins\Chat\ChatPlugin;
use JayI\Cortex\Plugins\Provider\ProviderPlugin;
use JayI\Cortex\Plugins\Schema\SchemaPlugin;
use JayI\Cortex\Support\NullTenantResolver;
use JayI\Cortex\Support\PluginManager;
use JayI\Cortex\Support\TenantManager;

class CortexServiceProvider extends ServiceProvider
{
    /**
     * Core plugins that are always loaded.
     *
     * @var array<int, class-string>
     */
    protected array $corePlugins = [
        SchemaPlugin::class,
        ProviderPlugin::class,
        ChatPlugin::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cortex.php', 'cortex');

        // Register the plugin manager as a singleton
        $this->app->singleton(PluginManagerContract::class, function ($app) {
            return new PluginManager($app);
        });

        // Register the Cortex manager class
        $this->app->singleton(CortexManager::class, function ($app) {
            return new CortexManager($app, $app->make(PluginManagerContract::class));
        });

        // Alias for the facade
        $this->app->alias(CortexManager::class, 'cortex');

        // Register tenancy bindings
        $this->registerTenancy();

        // Register plugins
        $this->registerPlugins();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/cortex.php' => config_path('cortex.php'),
            ], 'cortex-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'cortex-migrations');
        }

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Boot all plugins
        $this->bootPlugins();

        // Register event subscriber
        if ($this->app->make('config')->get('cortex.events.logging.enabled', false)) {
            Event::subscribe(EventLoggingSubscriber::class);
        }
    }

    /**
     * Register tenancy bindings.
     */
    protected function registerTenancy(): void
    {
        $this->app->singleton(TenantResolverContract::class, function ($app) {
            $resolverClass = $app->make('config')->get('cortex.tenancy.resolver');

            if ($resolverClass && class_exists($resolverClass)) {
                return $app->make($resolverClass);
            }

            return new NullTenantResolver;
        });

        $this->app->singleton(TenantManager::class, function ($app) {
            return new TenantManager($app->make(TenantResolverContract::class));
        });
    }

    /**
     * Register all configured plugins.
     */
    protected function registerPlugins(): void
    {
        $manager = $this->app->make(PluginManagerContract::class);
        $config = $this->app->make('config')->get('cortex.plugins', []);

        $enabled = $config['enabled'] ?? [];
        $disabled = $config['disabled'] ?? [];

        // Register core plugins
        foreach ($this->corePlugins as $pluginClass) {
            $plugin = $this->app->make($pluginClass);
            $manager->register($plugin);
        }

        // Register optional plugins based on config
        $optionalPlugins = $this->getOptionalPlugins();
        foreach ($optionalPlugins as $id => $pluginClass) {
            // Check if plugin is enabled and not disabled
            if (in_array($id, $enabled, true) && ! in_array($id, $disabled, true)) {
                if (class_exists($pluginClass)) {
                    $plugin = $this->app->make($pluginClass);
                    $manager->register($plugin);
                }
            }
        }
    }

    /**
     * Boot all registered plugins.
     */
    protected function bootPlugins(): void
    {
        $manager = $this->app->make(PluginManagerContract::class);
        $manager->boot();
    }

    /**
     * Get optional plugin mappings.
     *
     * @return array<string, class-string>
     */
    protected function getOptionalPlugins(): array
    {
        return [
            'tool' => \JayI\Cortex\Plugins\Tool\ToolPlugin::class,
            'structured-output' => \JayI\Cortex\Plugins\StructuredOutput\StructuredOutputPlugin::class,
            'agent' => \JayI\Cortex\Plugins\Agent\AgentPlugin::class,
            'workflow' => \JayI\Cortex\Plugins\Workflow\WorkflowPlugin::class,
            'mcp' => \JayI\Cortex\Plugins\Mcp\McpPlugin::class,
            'guardrail' => \JayI\Cortex\Plugins\Guardrail\GuardrailPlugin::class,
            'resilience' => \JayI\Cortex\Plugins\Resilience\ResiliencePlugin::class,
            'prompt' => \JayI\Cortex\Plugins\Prompt\PromptPlugin::class,
            'usage' => \JayI\Cortex\Plugins\Usage\UsagePlugin::class,
            'cache' => \JayI\Cortex\Plugins\Cache\CachePlugin::class,
            'context-manager' => \JayI\Cortex\Plugins\ContextManager\ContextManagerPlugin::class,
        ];
    }
}
