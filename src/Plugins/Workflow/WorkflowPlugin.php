<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow;

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowExecutorContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowRegistryContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowStateRepositoryContract;
use JayI\Cortex\Plugins\Workflow\Repositories\CacheWorkflowStateRepository;
use JayI\Cortex\Plugins\Workflow\Repositories\DatabaseWorkflowStateRepository;
use JayI\Cortex\Support\ExtensionPoint;

class WorkflowPlugin implements PluginContract
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
        return 'workflow';
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'Workflow';
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
        return ['schema', 'provider', 'chat', 'tool', 'agent'];
    }

    /**
     * {@inheritdoc}
     */
    public function provides(): array
    {
        return ['workflows'];
    }

    /**
     * {@inheritdoc}
     */
    public function register(PluginManagerContract $manager): void
    {
        // Register workflow registry
        $this->container->singleton(WorkflowRegistryContract::class, function () {
            return new WorkflowRegistry;
        });

        // Register state repository based on config
        $this->container->singleton(WorkflowStateRepositoryContract::class, function () {
            $driver = $this->config['persistence']['driver'] ?? 'database';

            return match ($driver) {
                'cache' => new CacheWorkflowStateRepository(),
                default => new DatabaseWorkflowStateRepository(),
            };
        });

        // Register workflow executor (with persistence if enabled)
        $this->container->singleton(WorkflowExecutorContract::class, function () {
            $baseExecutor = new WorkflowExecutor;

            if (isset($this->config['max_steps'])) {
                $baseExecutor->maxSteps($this->config['max_steps']);
            }

            // Wrap with persistence if repository is available
            if ($this->container->bound(WorkflowStateRepositoryContract::class)) {
                $repository = $this->container->make(WorkflowStateRepositoryContract::class);

                return new PersistentWorkflowExecutor($baseExecutor, $repository);
            }

            return $baseExecutor;
        });

        // Register extension point for workflows
        $manager->registerExtensionPoint(
            'workflows',
            ExtensionPoint::make('workflows', WorkflowContract::class)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function boot(PluginManagerContract $manager): void
    {
        // Register workflows from extension point
        $extensionPoint = $manager->getExtensionPoint('workflows');
        $registry = $this->container->make(WorkflowRegistryContract::class);

        foreach ($extensionPoint->all() as $workflow) {
            $registry->register($workflow);
        }

        // Register workflows from config
        if (isset($this->config['workflows']) && is_array($this->config['workflows'])) {
            foreach ($this->config['workflows'] as $workflowClass) {
                if (is_string($workflowClass) && class_exists($workflowClass)) {
                    $workflow = $this->container->make($workflowClass);

                    if ($workflow instanceof WorkflowContract) {
                        $registry->register($workflow);
                    }
                }
            }
        }
    }
}
