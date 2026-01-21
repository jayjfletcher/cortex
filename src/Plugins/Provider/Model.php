<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Provider;

use Spatie\LaravelData\Data;

class Model extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $provider,
        public int $contextWindow,
        public int $maxOutputTokens,
        public ?float $inputCostPer1kTokens = null,
        public ?float $outputCostPer1kTokens = null,
        public ?ProviderCapabilities $capabilities = null,
        public array $metadata = [],
    ) {}

    /**
     * Estimate the cost for a given number of tokens.
     */
    public function estimateCost(int $inputTokens, int $outputTokens): ?float
    {
        if ($this->inputCostPer1kTokens === null || $this->outputCostPer1kTokens === null) {
            return null;
        }

        return (($inputTokens / 1000) * $this->inputCostPer1kTokens)
            + (($outputTokens / 1000) * $this->outputCostPer1kTokens);
    }

    /**
     * Check if this model supports a specific feature.
     */
    public function supports(string $feature): bool
    {
        return $this->capabilities?->supports($feature) ?? false;
    }
}
