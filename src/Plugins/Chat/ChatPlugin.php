<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat;

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Chat\Broadcasting\BroadcasterContract;
use JayI\Cortex\Plugins\Chat\Broadcasting\EchoBroadcaster;
use JayI\Cortex\Plugins\Chat\Contracts\ChatClientContract;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderRegistryContract;

class ChatPlugin implements PluginContract
{
    public function __construct(
        protected Container $container,
    ) {}

    /**
     * Unique identifier for this plugin.
     */
    public function id(): string
    {
        return 'chat';
    }

    /**
     * Human-readable name.
     */
    public function name(): string
    {
        return 'Chat';
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
        return ['provider'];
    }

    /**
     * List of features/capabilities this plugin provides.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return ['chat', 'streaming', 'broadcasting'];
    }

    /**
     * Register bindings, configs, and extension points.
     */
    public function register(PluginManagerContract $manager): void
    {
        // Register the chat client as a singleton
        $this->container->singleton(ChatClientContract::class, function ($app) use ($manager) {
            return new ChatClient(
                $app,
                $app->make(ProviderRegistryContract::class),
                $manager,
            );
        });

        // Register broadcaster based on config
        $this->container->singleton(BroadcasterContract::class, function ($app) {
            $driver = $app->make('config')->get('cortex.chat.broadcasting.driver', 'echo');

            return match ($driver) {
                'echo' => new EchoBroadcaster($app->make('events')),
                default => new EchoBroadcaster($app->make('events')),
            };
        });
    }

    /**
     * Bootstrap the plugin after all plugins are registered.
     */
    public function boot(PluginManagerContract $manager): void
    {
        // Register default hooks
        $config = $this->container->make('config')->get('cortex.chat', []);

        // Apply default options from config
        $defaultOptions = $config['default_options'] ?? [];
        if (! empty($defaultOptions)) {
            $manager->addHook('chat.before_send', function (ChatRequest $request) use ($defaultOptions) {
                // Merge default options with request options
                $requestOptions = $request->options;
                $mergedOptions = ChatOptions::from(array_merge(
                    $defaultOptions,
                    array_filter($requestOptions->toArray(), fn ($v) => $v !== null)
                ));

                return new ChatRequest(
                    messages: $request->messages,
                    systemPrompt: $request->systemPrompt,
                    model: $request->model ?? ($this->container->make('config')->get('cortex.chat.default_model')),
                    options: $mergedOptions,
                    tools: $request->tools,
                    responseSchema: $request->responseSchema,
                    metadata: $request->metadata,
                );
            }, priority: 1);
        }
    }
}
