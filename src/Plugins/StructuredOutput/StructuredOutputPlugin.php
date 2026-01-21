<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\StructuredOutput;

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderRegistryContract;
use JayI\Cortex\Plugins\StructuredOutput\Contracts\StructuredOutputContract;

class StructuredOutputPlugin implements PluginContract
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected Container $container,
        protected array $config = [],
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'structured-output';
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'Structured Output Plugin';
    }

    /**
     * {@inheritdoc}
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * {@inheritdoc}
     */
    public function dependencies(): array
    {
        return ['schema', 'provider', 'chat'];
    }

    /**
     * {@inheritdoc}
     */
    public function provides(): array
    {
        return ['structured-output'];
    }

    /**
     * {@inheritdoc}
     */
    public function register(PluginManagerContract $manager): void
    {
        $this->container->singleton(StructuredOutputContract::class, function (Container $app) {
            return new StructuredOutputHandler(
                providers: $app->make(ProviderRegistryContract::class),
                config: $this->config,
            );
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(PluginManagerContract $manager): void
    {
        // No boot logic needed
    }
}
