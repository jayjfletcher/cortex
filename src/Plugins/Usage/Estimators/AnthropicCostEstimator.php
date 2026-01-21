<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Usage\Estimators;

use JayI\Cortex\Plugins\Usage\Contracts\CostEstimatorContract;

/**
 * Cost estimator for Anthropic Claude models.
 */
class AnthropicCostEstimator implements CostEstimatorContract
{
    /**
     * Pricing per million tokens (as of 2024).
     * [model pattern => [input price, output price]]
     *
     * @var array<string, array{input: float, output: float}>
     */
    protected array $pricing = [
        'claude-3-opus' => ['input' => 15.00, 'output' => 75.00],
        'claude-3-5-sonnet' => ['input' => 3.00, 'output' => 15.00],
        'claude-3-sonnet' => ['input' => 3.00, 'output' => 15.00],
        'claude-3-haiku' => ['input' => 0.25, 'output' => 1.25],
        'claude-3-5-haiku' => ['input' => 1.00, 'output' => 5.00],
        'claude-instant' => ['input' => 0.80, 'output' => 2.40],
        'claude-2' => ['input' => 8.00, 'output' => 24.00],
    ];

    /**
     * {@inheritdoc}
     */
    public function estimate(string $model, int $inputTokens, int $outputTokens): float
    {
        $inputPrice = $this->getInputPricePerMillion($model);
        $outputPrice = $this->getOutputPricePerMillion($model);

        $inputCost = ($inputTokens / 1_000_000) * $inputPrice;
        $outputCost = ($outputTokens / 1_000_000) * $outputPrice;

        return $inputCost + $outputCost;
    }

    /**
     * {@inheritdoc}
     */
    public function getInputPricePerMillion(string $model): float
    {
        $pricing = $this->findPricing($model);

        return $pricing['input'];
    }

    /**
     * {@inheritdoc}
     */
    public function getOutputPricePerMillion(string $model): float
    {
        $pricing = $this->findPricing($model);

        return $pricing['output'];
    }

    /**
     * {@inheritdoc}
     */
    public function supports(string $model): bool
    {
        return $this->matchModel($model) !== null;
    }

    /**
     * Find pricing for a model.
     *
     * @return array{input: float, output: float}
     */
    protected function findPricing(string $model): array
    {
        $match = $this->matchModel($model);

        if ($match === null) {
            // Default to Sonnet pricing for unknown models
            return $this->pricing['claude-3-5-sonnet'];
        }

        return $this->pricing[$match];
    }

    /**
     * Match a model name to a pricing key.
     */
    protected function matchModel(string $model): ?string
    {
        $normalizedModel = strtolower($model);

        // Check for exact matches first
        foreach (array_keys($this->pricing) as $pattern) {
            if (str_contains($normalizedModel, $pattern)) {
                return $pattern;
            }
        }

        // Check for partial matches (Bedrock model IDs)
        if (str_contains($normalizedModel, 'opus')) {
            return 'claude-3-opus';
        }

        if (str_contains($normalizedModel, 'sonnet')) {
            if (str_contains($normalizedModel, '3-5') || str_contains($normalizedModel, '3.5')) {
                return 'claude-3-5-sonnet';
            }

            return 'claude-3-sonnet';
        }

        if (str_contains($normalizedModel, 'haiku')) {
            if (str_contains($normalizedModel, '3-5') || str_contains($normalizedModel, '3.5')) {
                return 'claude-3-5-haiku';
            }

            return 'claude-3-haiku';
        }

        if (str_contains($normalizedModel, 'instant')) {
            return 'claude-instant';
        }

        if (str_contains($normalizedModel, 'claude-2')) {
            return 'claude-2';
        }

        return null;
    }

    /**
     * Update pricing for a model.
     */
    public function setPricing(string $model, float $inputPrice, float $outputPrice): void
    {
        $this->pricing[$model] = ['input' => $inputPrice, 'output' => $outputPrice];
    }
}
