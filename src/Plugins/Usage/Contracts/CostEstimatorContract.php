<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Usage\Contracts;

interface CostEstimatorContract
{
    /**
     * Estimate the cost for a given model and token counts.
     */
    public function estimate(string $model, int $inputTokens, int $outputTokens): float;

    /**
     * Get the input token price per million tokens.
     */
    public function getInputPricePerMillion(string $model): float;

    /**
     * Get the output token price per million tokens.
     */
    public function getOutputPricePerMillion(string $model): float;

    /**
     * Check if the estimator supports a model.
     */
    public function supports(string $model): bool;
}
