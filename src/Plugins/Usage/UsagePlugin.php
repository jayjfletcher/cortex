<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Usage;

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Usage\Contracts\BudgetManagerContract;
use JayI\Cortex\Plugins\Usage\Contracts\CostEstimatorContract;
use JayI\Cortex\Plugins\Usage\Contracts\UsageTrackerContract;
use JayI\Cortex\Plugins\Usage\Estimators\AnthropicCostEstimator;

class UsagePlugin implements PluginContract
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
        return 'usage';
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'Usage';
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
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function provides(): array
    {
        return ['usage'];
    }

    /**
     * {@inheritdoc}
     */
    public function register(PluginManagerContract $manager): void
    {
        // Register cost estimator
        $this->container->singleton(CostEstimatorContract::class, function () {
            $estimator = new AnthropicCostEstimator;

            // Apply custom pricing from config
            if (isset($this->config['pricing'])) {
                foreach ($this->config['pricing'] as $model => $prices) {
                    $estimator->setPricing(
                        $model,
                        $prices['input'] ?? 0.0,
                        $prices['output'] ?? 0.0
                    );
                }
            }

            return $estimator;
        });

        // Register usage tracker
        $this->container->singleton(UsageTrackerContract::class, function () {
            // TODO: Support database-backed tracker via config
            return new InMemoryUsageTracker;
        });

        // Register budget manager
        $this->container->singleton(BudgetManagerContract::class, function () {
            return new BudgetManager(
                $this->container->make(UsageTrackerContract::class)
            );
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(PluginManagerContract $manager): void
    {
        // Register default budgets from config
        if (isset($this->config['budgets'])) {
            $budgetManager = $this->container->make(BudgetManagerContract::class);

            foreach ($this->config['budgets'] as $budgetConfig) {
                $budget = $this->createBudgetFromConfig($budgetConfig);

                if ($budget !== null) {
                    $budgetManager->addBudget($budget);
                }
            }
        }
    }

    /**
     * Create a budget from configuration array.
     *
     * @param  array<string, mixed>  $config
     */
    protected function createBudgetFromConfig(array $config): ?Data\Budget
    {
        $period = Data\BudgetPeriod::tryFrom($config['period'] ?? 'monthly')
            ?? Data\BudgetPeriod::Monthly;

        if (isset($config['max_cost'])) {
            return Data\Budget::cost(
                maxCost: (float) $config['max_cost'],
                period: $period,
                userId: $config['user_id'] ?? null,
                model: $config['model'] ?? null,
                hardLimit: $config['hard_limit'] ?? true,
            );
        }

        if (isset($config['max_tokens'])) {
            return Data\Budget::tokens(
                maxTokens: (int) $config['max_tokens'],
                period: $period,
                userId: $config['user_id'] ?? null,
                model: $config['model'] ?? null,
                hardLimit: $config['hard_limit'] ?? true,
            );
        }

        if (isset($config['max_requests'])) {
            return Data\Budget::requests(
                maxRequests: (int) $config['max_requests'],
                period: $period,
                userId: $config['user_id'] ?? null,
                model: $config['model'] ?? null,
                hardLimit: $config['hard_limit'] ?? true,
            );
        }

        return null;
    }
}
